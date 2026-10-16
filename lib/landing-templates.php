<?php
/**
 * Shared landing-template resolution for the public home, marketplace, and
 * academy homepages.
 *
 * Each area exposes a Template A (original layout) and a Template B
 * (focus-driven, less dense). Operators pick the default with an admin
 * setting (`<area>_template`), and may live-preview/switch with
 * `?template=a|b` — the choice sticks in a per-area cookie so it persists
 * while browsing that area. Invalid values always fall back to "a".
 *
 * Usage (per page):
 *   $tpl = landing_template($pdo, 'home');     // sets/respects cookie home_template
 *   $tpl = landing_template($pdo, 'market');   // cookie market_template
 *   $tpl = landing_template($pdo, 'academy');  // cookie academy_template
 *
 * Relies on the config.php bootstrap for admin_setting() (same as its other
 * callers).
 */

if (!function_exists('landing_allowed_templates')) {
    function landing_allowed_templates(): array
    {
        return ['a', 'b'];
    }
}

if (!function_exists('landing_template')) {
    function landing_template(PDO $pdo, string $area, string $default = 'a'): string
    {
        $allowed = landing_allowed_templates();
        $cookieName = $area . '_template';

        // Explicit opt-in (e.g. operator preview). Validated and persisted.
        $param = (string) ($_GET['template'] ?? '');
        if (in_array($param, $allowed, true)) {
            $template = $param;
            setcookie($cookieName, $template, [
                'expires' => time() + 30 * 86400,
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return $template;
        }

        // Per-browser preference set by a previous switch.
        $cookie = (string) ($_COOKIE[$cookieName] ?? '');
        if (in_array($cookie, $allowed, true)) {
            return $cookie;
        }

        // Operator-set default (admin settings), else "a".
        $template = (string) admin_setting($pdo, $cookieName, $default);
        return in_array($template, $allowed, true) ? $template : $default;
    }
}

if (!function_exists('landing_template_class')) {
    /**
     * Body class for a given area/template, e.g. "home-template-a".
     */
    function landing_template_class(string $area, string $template): string
    {
        $template = in_array($template, landing_allowed_templates(), true) ? $template : 'a';
        return $area . '-template-' . $template;
    }
}
