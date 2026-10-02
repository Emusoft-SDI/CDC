<?php
declare(strict_types=1);

/**
 * One footer for every visitor-facing page.
 *
 * The site previously had four different footers: a rich one shared by index.php and
 * news.php, a short one on contact.php, one-line badge strips on apply.php and
 * verify-certificate.php, and a four-column partial used only by about.php. The
 * partial also said "©2023" and linked to five legal pages that do not exist.
 *
 * This replaces all of them. Design rules kept deliberately strict so the footer does
 * not become a wall of links:
 *   - three columns only, about nine links in total
 *   - no icon font, so it cannot render as empty squares on pages that do not load one
 *   - every link points at a page that exists
 *   - links are relative to the current page, so the same markup works at / and at /win
 *
 * Usage, immediately before </body>:
 *   <?= public_footer() ?>
 *
 * Loaded automatically from config.php.
 */

if (!function_exists('nc_footer_root_prefix')) {
    /**
     * Relative path back to the application root, so one footer works at any depth.
     * "/win/index.php" -> "", "/win/market/index.php" -> "../".
     */
    function nc_footer_root_prefix(): string
    {
        static $prefix = null;
        if ($prefix !== null) {
            return $prefix;
        }

        $root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

        if ($root !== '' && $script !== '' && str_starts_with($script, $root)) {
            $relative = ltrim(substr($script, strlen($root)), '/');
            $depth = substr_count($relative, '/');
            $prefix = str_repeat('../', $depth);
        } else {
            $prefix = '';
        }

        return $prefix;
    }
}

if (!function_exists('nc_footer_link')) {
    function nc_footer_link(string $path): string
    {
        return e(nc_footer_root_prefix() . $path);
    }
}

if (!function_exists('public_footer_styles')) {
    /** Self-contained styling: the footer looks the same whatever the host page loads. */
    function public_footer_styles(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        return <<<'CSS'
<style>
.nc-footer{background:#052915;color:#dce9de;margin-top:48px;font-family:inherit}
.nc-footer *{box-sizing:border-box}
.nc-footer__inner{max-width:1120px;margin:0 auto;padding:38px 24px 26px;display:grid;grid-template-columns:minmax(210px,1.15fr) repeat(3,minmax(0,1fr));gap:28px;align-items:start}
.nc-footer__brand img{height:38px;width:auto;display:block;margin-bottom:14px}
.nc-footer__name{font-weight:800;font-size:1.05rem;letter-spacing:.02em;color:#fff;margin:0 0 8px}
.nc-footer__mission{margin:0;line-height:1.55;font-size:.92rem;color:#b8ccbb}
.nc-footer__address{display:grid;gap:9px;font-style:normal;font-size:.92rem;line-height:1.5}
.nc-footer__address span{color:#dce9de}
.nc-footer__address a{color:#dce9de;text-decoration:none;border-bottom:1px solid rgba(220,233,222,.28)}
.nc-footer__address a:hover{border-bottom-color:#7fd68f;color:#fff}
.nc-footer__col h2{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#7fd68f;margin:0 0 14px;font-weight:800}
.nc-footer__col ul{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.nc-footer__col a{color:#dce9de;text-decoration:none;font-size:.92rem}
.nc-footer__col a:hover{color:#fff;text-decoration:underline;text-underline-offset:3px}
.nc-footer__bottom{border-top:1px solid rgba(255,255,255,.14)}
.nc-footer__bottom-inner{max-width:1120px;margin:0 auto;padding:16px 24px;display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;justify-content:space-between;font-size:.85rem;color:#a9bfad}
.nc-footer__legal{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center}
.nc-footer__legal a,.nc-footer__legal button{color:#a9bfad;background:none;border:0;padding:0;font:inherit;cursor:pointer;text-decoration:none}
.nc-footer__legal a:hover,.nc-footer__legal button:hover{color:#fff;text-decoration:underline;text-underline-offset:3px}
.nc-footer a:focus-visible,.nc-footer button:focus-visible{outline:2px solid #7fd68f;outline-offset:3px;border-radius:3px}
/* Cookie consent: a discreet bottom card, never a full-screen blocker. */
.nc-cookie{position:fixed;left:16px;right:16px;bottom:16px;z-index:9000;max-width:640px;margin:0 auto;background:#0b3a22;color:#e8f6ec;border:1px solid rgba(127,214,143,.35);border-radius:12px;box-shadow:0 18px 44px rgba(0,0,0,.34);padding:18px 20px;display:none}
.nc-cookie[data-open="1"]{display:block}
.nc-cookie h2{margin:0 0 8px;font-size:1rem;color:#fff}
.nc-cookie p{margin:0 0 14px;font-size:.9rem;line-height:1.55;color:#cfe4d4}
.nc-cookie a{color:#7fd68f}
.nc-cookie__actions{display:flex;flex-wrap:wrap;gap:10px}
.nc-cookie button{border-radius:8px;padding:10px 16px;font:inherit;font-weight:700;cursor:pointer;border:1px solid transparent}
.nc-cookie__accept{background:#39a84d;color:#04250f}
.nc-cookie__accept:hover{background:#4bbd5f}
.nc-cookie__essential{background:transparent;color:#e8f6ec;border-color:rgba(232,246,236,.45)}
.nc-cookie__essential:hover{border-color:#e8f6ec;background:rgba(232,246,236,.08)}
.nc-cookie button:focus-visible{outline:2px solid #7fd68f;outline-offset:2px}
@media (max-width:980px){.nc-footer__inner{grid-template-columns:repeat(2,minmax(0,1fr));gap:26px}}
@media (max-width:600px){.nc-footer__inner{grid-template-columns:1fr}}
@media print{.nc-footer,.nc-cookie{display:none !important}}
</style>
CSS;
    }
}

if (!function_exists('public_footer_context')) {
    /** @return array{office:array,year:int,logo:?string,phone_suffix:string} */
    function public_footer_context(): array
    {
        static $context = null;
        if ($context !== null) {
            return $context;
        }

        $office = ['address_lines' => [], 'phone_display' => '', 'phone_tel' => '', 'email' => ''];
        try {
            $office = app_contact_office();
        } catch (Throwable $e) {
            // A footer must never be the reason a page fails to render.
        }

        $logo = null;
        try {
            $logo = app_primary_logo_url();
        } catch (Throwable $e) {
        }

        $context = [
            'office' => $office,
            'year' => (int) date('Y'),
            'logo' => $logo,
        ];

        return $context;
    }
}

if (!function_exists('public_footer_links')) {
    /**
     * The whole link set: two short columns. Kept small on purpose.
     *
     * @return array<string, array<string, string>>
     */
    function public_footer_links(): array
    {
        return [
            'Platform' => [
                'Marketplace' => 'market/',
                'Academy' => 'academy/',
                'Verify a Certificate' => 'verify-certificate.php',
                'Support' => 'support/',
            ],
            'Company' => [
                'About Us' => 'about.php',
                'News & Updates' => 'news.php',
                'Contact Us' => 'contact.php',
                'Recruitment' => 'recruitment.php',
                'Register' => 'apply.php',
            ],
        ];
    }
}

if (!function_exists('cookie_consent_modal')) {
    /**
     * Cookie consent card. Uses localStorage, falls back to a cookie, and can be
     * reopened from the footer's "Cookie settings" button.
     */
    function cookie_consent_modal(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        $privacy = nc_footer_link('privacy.php');

        return <<<HTML
<div class="nc-cookie" id="nc-cookie" role="dialog" aria-modal="false" aria-labelledby="nc-cookie-title" data-open="0">
  <h2 id="nc-cookie-title">Cookies on NATCODEV</h2>
  <p>
    We use essential cookies to keep you signed in and to secure forms. With your consent we
    also use analytics cookies to understand which parts of the platform are useful.
    Read our <a href="{$privacy}">Privacy Policy</a>.
  </p>
  <div class="nc-cookie__actions">
    <button type="button" class="nc-cookie__accept" data-nc-cookie="all">Accept all</button>
    <button type="button" class="nc-cookie__essential" data-nc-cookie="essential">Essential only</button>
  </div>
</div>
<script>
(function () {
  var KEY = 'natcodev_cookie_consent';
  var box = document.getElementById('nc-cookie');
  if (!box) { return; }

  function read() {
    try { return window.localStorage.getItem(KEY); } catch (e) {}
    var m = document.cookie.match(/(?:^|;\s*)natcodev_cookie_consent=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : null;
  }

  function write(value) {
    try { window.localStorage.setItem(KEY, value); } catch (e) {}
    document.cookie = KEY + '=' + encodeURIComponent(value) + ';path=/;max-age=' + (60 * 60 * 24 * 180) + ';samesite=lax';
  }

  if (!read()) { box.setAttribute('data-open', '1'); }

  box.addEventListener('click', function (event) {
    var choice = event.target.getAttribute && event.target.getAttribute('data-nc-cookie');
    if (!choice) { return; }
    write(choice);
    box.setAttribute('data-open', '0');
  });

  document.querySelectorAll('[data-nc-cookie-settings]').forEach(function (trigger) {
    trigger.addEventListener('click', function () {
      box.setAttribute('data-open', '1');
      var first = box.querySelector('button');
      if (first) { first.focus(); }
    });
  });
}());
</script>
HTML;
    }
}

if (!function_exists('public_footer')) {
    /**
     * The footer itself. Renders once per request, so a page that includes it twice
     * (or a layout plus a page) cannot produce a duplicate.
     */
    function public_footer(array $options = []): string
    {
        static $rendered = false;
        if ($rendered) {
            return '';
        }
        $rendered = true;

        $compact = (bool) ($options['compact'] ?? false);
        $ctx = public_footer_context();
        $office = $ctx['office'];
        $links = public_footer_links();

        $brand = '';
        if ($ctx['logo']) {
            $brand = '<img src="' . e((string) $ctx['logo']) . '" alt="NATCODEV">';
        }

        $contact = '';
        if ($office['address_lines']) {
            $contact .= '<span>' . e(implode(', ', $office['address_lines'])) . '</span>';
        }
        if ($office['phone_display']) {
            $contact .= '<a href="tel:' . e((string) $office['phone_tel']) . '">'
                . e((string) $office['phone_display']) . '</a>';
        }
        if ($office['email']) {
            $contact .= '<a href="mailto:' . e((string) $office['email']) . '">'
                . e((string) $office['email']) . '</a>';
        }

        $columns = '';
        foreach ($links as $heading => $items) {
            $itemsHtml = '';
            foreach ($items as $label => $path) {
                $itemsHtml .= '<li><a href="' . nc_footer_link($path) . '">' . e($label) . '</a></li>';
            }
            $columns .= '<nav class="nc-footer__col" aria-label="' . e($heading) . '">'
                . '<h2>' . e($heading) . '</h2><ul>' . $itemsHtml . '</ul></nav>';
        }

        $html = public_footer_styles();
        $html .= '<footer class="nc-footer' . ($compact ? ' nc-footer--compact' : '') . '">';
        $html .= '<div class="nc-footer__inner">';
        $html .= '<div class="nc-footer__brand">' . $brand
            . '<p class="nc-footer__name">NATCODEV</p>'
            . '<p class="nc-footer__mission">Building productive coconut communities and a sustainable value chain across Nigeria.</p>'
            . '</div>';
        $html .= $columns;
        // Contact sits in its own column. Stacked under the brand it made the first
        // column twice as tall as the others and the whole footer needlessly deep.
        if ($contact !== '') {
            $html .= '<div class="nc-footer__col"><h2>Contact</h2>'
                . '<address class="nc-footer__address">' . $contact . '</address></div>';
        }
        $html .= '</div>';
        $html .= '<div class="nc-footer__bottom"><div class="nc-footer__bottom-inner">'
            . '<span>&copy; ' . $ctx['year'] . ' NATCODEV. All rights reserved.</span>'
            . '<span class="nc-footer__legal">'
            . '<a href="' . nc_footer_link('terms.php') . '">Terms of Service</a>'
            . '<a href="' . nc_footer_link('privacy.php') . '">Privacy Policy</a>'
            . '<button type="button" data-nc-cookie-settings>Cookie settings</button>'
            . '</span>'
            . '</div></div>';
        $html .= '</footer>';

        if (!$compact) {
            $html .= cookie_consent_modal();
        }

        return $html;
    }
}
