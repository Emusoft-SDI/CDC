<?php
declare(strict_types=1);

/**
 * CSP hardening pipeline (Phase 9, complete).
 *
 * Converts legacy inline event handlers into CSP-safe data attributes and adds a
 * per-request nonce to every <script>/<style> tag, then emits a nonce-based
 * Content-Security-Policy for admin pages. This lets the admin console run under
 * `script-src 'self' 'nonce-…' 'strict-dynamic'` without hand-editing 119 pages.
 *
 * The matching runtime behaviour lives in assets/js/nc-csp.js.
 */

/** @return array<int,array{0:string,1:string}> [pattern, replacement-template] rules */
function admin_csp_handler_rules(): array
{
    return [
        // Confirm on submit / click.
        ['#^return\s+confirm\(([\'"])(.*?)\1\)\s*;?$#s', 'data-nc-confirm-submit="%s"'],
        ['#^return\s+confirm\(([\'"])(.*?)\1\)\s*;?$#s', 'data-nc-confirm="%s"'],
    ];
}

/**
 * Split a comma-separated argument list, respecting quotes.
 *
 * @return array<int,string>|null
 */
function admin_csp_split_args(string $args): ?array
{
    $args = trim($args);
    if ($args === '') {
        return [];
    }
    $out = [];
    $current = '';
    $quote = null;
    $len = strlen($args);
    for ($i = 0; $i < $len; $i++) {
        $ch = $args[$i];
        if ($quote !== null) {
            if ($ch === $quote) {
                $quote = null;
            }
            $current .= $ch;
            continue;
        }
        if ($ch === "'" || $ch === '"') {
            $quote = $ch;
            $current .= $ch;
            continue;
        }
        if ($ch === ',') {
            $out[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $out[] = trim($current);
    return $out;
}

/**
 * Convert one inline handler expression into CSP-safe data attributes, or return
 * null when the expression is not safely convertible.
 */
function admin_csp_convert_handler(string $event, string $code): ?string
{
    $code = trim(html_entity_decode($code, ENT_QUOTES | ENT_HTML5));

    // Confirm map: one or more `if (this.form.<field>.value === '<key>') return confirm('<msg>'); [return true;]`
    if (preg_match_all("#if\\s*\\(\\s*this\\.form\\.(\\w+)\\.value\\s*===\\s*(['\\\"])(.*?)\\2\\s*\\)\\s*return\\s+confirm\\((['\\\"])(.*?)\\4\\)\\s*;#s", $code, $mm, PREG_SET_ORDER)) {
        $field = $mm[0][1];
        $map = [];
        $sameField = true;
        foreach ($mm as $set) {
            if ($set[1] !== $field) {
                $sameField = false;
                break;
            }
            $map[$set[3]] = $set[5];
        }
        $remainder = trim(preg_replace("#if\\s*\\(\\s*this\\.form\\.(\\w+)\\.value\\s*===\\s*(['\\\"])(.*?)\\2\\s*\\)\\s*return\\s+confirm\\((['\\\"])(.*?)\\4\\)\\s*;#s", '', $code) ?? '');
        if ($sameField && ($remainder === '' || $remainder === 'return true;')) {
            return 'data-nc-confirm-field="' . htmlspecialchars($field, ENT_QUOTES) . '" data-nc-confirm-map="'
                . htmlspecialchars(json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES) . '"';
        }
    }

    // Conditional confirm: if (this.form.<field>.value === 'x') return confirm('MSG')
    if (preg_match("#^if\\s*\\(\\s*this\\.form\\.(\\w+)\\.value\\s*===\\s*(['\\\"])(.*?)\\2\\s*\\)\\s*return\\s+confirm\\((['\\\"])(.*?)\\4\\)\\s*;?$#s", $code, $m)) {
        return 'data-nc-confirm-when-field="' . htmlspecialchars($m[1], ENT_QUOTES) . '"'
            . ' data-nc-confirm-when-value="' . htmlspecialchars($m[3], ENT_QUOTES) . '"'
            . ' data-nc-confirm="' . htmlspecialchars($m[5], ENT_QUOTES) . '"';
    }

    // confirm(...) on submit or click.
    if (preg_match('#^return\s+confirm\(([\'"])(.*?)\1\)\s*;?$#s', $code, $m)) {
        $attr = $event === 'submit' ? 'data-nc-confirm-submit' : 'data-nc-confirm';
        return $attr . '="' . htmlspecialchars($m[2], ENT_QUOTES) . '"';
    }

    // Select-all checkbox.
    if (preg_match('#^document\.querySelectorAll\(([\'"])(.*?)\1\)\.forEach#s', $code, $m)) {
        return 'data-nc-check-all="' . htmlspecialchars($m[2], ENT_QUOTES) . '"';
    }

    // Auto-submit on change (optionally resetting page to 1).
    if (preg_match("#^this\.form\.page\.value\s*=\s*['\"]1['\"]\s*;\s*this\.form\.submit\(\)$#", $code)) {
        return 'data-nc-autosubmit data-nc-reset-page';
    }
    if ($code === 'this.form.submit()') {
        return 'data-nc-autosubmit';
    }

    // Set a hidden field value, optionally submitting the form.
    if (preg_match("#^document\.getElementById\((['\"])(.*?)\1\)\.value\s*=\s*(['\"])(.*?)\3\s*;\s*this\.form\.submit\(\)$#s", $code, $m)) {
        return 'data-nc-set-value="' . htmlspecialchars($m[2], ENT_QUOTES) . '" data-nc-set-to="' . htmlspecialchars($m[4], ENT_QUOTES) . '" data-nc-submit';
    }
    if (preg_match("#^document\.getElementById\((['\"])(.*?)\1\)\.value\s*=\s*(['\"])(.*?)\3$#s", $code, $m)) {
        return 'data-nc-set-value="' . htmlspecialchars($m[2], ENT_QUOTES) . '" data-nc-set-to="' . htmlspecialchars($m[4], ENT_QUOTES) . '"';
    }

    // Generic single function call: fn(...)
    if (preg_match('#^([A-Za-z_$][A-Za-z0-9_$]*)\((.*)\)$#s', $code, $m)) {
        $fn = $m[1];
        $rawArgs = trim($m[2]);
        if ($rawArgs === '') {
            return 'data-nc-call="' . htmlspecialchars($fn, ENT_QUOTES) . '"';
        }
        if ($rawArgs === 'this.value') {
            return 'data-nc-call="' . htmlspecialchars($fn, ENT_QUOTES) . '" data-nc-args-from="value"';
        }
        $parts = admin_csp_split_args($rawArgs);
        if ($parts === null) {
            return null;
        }
        $args = [];
        foreach ($parts as $part) {
            if (preg_match('#^([\'"])(.*)\1$#s', $part, $am)) {
                $args[] = $am[2];
            } elseif (is_numeric($part)) {
                $args[] = $part + 0;
            } else {
                return null; // Not safely convertible.
            }
        }
        return 'data-nc-call="' . htmlspecialchars($fn, ENT_QUOTES) . '" data-nc-args="'
            . htmlspecialchars(json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES) . '"';
    }

    return null;
}

/**
 * Rewrite inline handlers to data attributes and add nonces to script/style tags.
 */
function admin_csp_harden(string $html): string
{
    $nonce = function_exists('admin_csp_nonce') ? admin_csp_nonce() : '';

    // 1. Convert inline event handlers (both quote styles are used in the codebase).
    $html = preg_replace_callback(
        '#\s+on(click|change|submit)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')#i',
        static function (array $m): string {
            $code = ($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '');
            $replacement = admin_csp_convert_handler(strtolower($m[1]), $code);
            if ($replacement === null) {
                return $m[0]; // Leave untouched if we cannot convert it safely.
            }
            return ' ' . $replacement;
        },
        $html
    ) ?? $html;

    // 2. Add the nonce to script/style tags that do not already have one.
    if ($nonce !== '') {
        $html = preg_replace_callback(
            '#<(script|style)\b(?![^>]*\bnonce=)([^>]*)>#i',
            static function (array $m) use ($nonce): string {
                return '<' . $m[1] . ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES) . '"' . $m[2] . '>';
            },
            $html
        ) ?? $html;
    }

    return $html;
}

/**
 * CSP for admin pages. Intersects with the site-wide .htaccess policy: the admin
 * policy drops 'unsafe-inline' for scripts (nonce required) while keeping
 * style-src 'unsafe-inline' (inline style attributes are pervasive).
 */
function admin_csp_policy(): string
{
    $nonce = function_exists('admin_csp_nonce') ? admin_csp_nonce() : '';
    $scriptSrc = "'self' 'nonce-" . $nonce . "' 'strict-dynamic' https:";

    return "default-src 'self'; "
        . "img-src 'self' data: https:; "
        . "style-src 'self' 'unsafe-inline' https:; "
        . "script-src " . $scriptSrc . "; "
        . "font-src 'self' data: https:; "
        . "connect-src 'self' https:; "
        . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
}

/** Emit the admin CSP header (no-op if headers already sent). */
function admin_csp_send_header(): void
{
    if (!headers_sent()) {
        header('Content-Security-Policy: ' . admin_csp_policy());
    }
}
