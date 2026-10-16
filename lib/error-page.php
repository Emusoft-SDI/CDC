<?php
declare(strict_types=1);

if (!function_exists('app_error_page_copy')) {
    /**
     * Copy for the branded error pages. Shared by 404.php and every in-place
     * error exit so the wording stays consistent.
     *
     * @return array{eyebrow: string, title: string, message: string, primary: string, primary_href: string}
     */
    function app_error_page_copy(int $statusCode): array
    {
        $copy = [
            401 => [
                'eyebrow' => 'Sign in required',
                'title' => 'This area needs your account',
                'message' => 'Sign in with your NATCODEV account to view this page.',
                'primary' => 'Sign in',
                'primary_href' => 'login.php',
            ],
            403 => [
                'eyebrow' => 'Access blocked',
                'title' => 'This area is not open to you yet',
                'message' => 'The page exists, but your account does not have permission to view it. Sign in with the right role or ask support to check your access.',
                'primary' => 'Sign in',
                'primary_href' => 'login.php',
            ],
            404 => [
                'eyebrow' => 'Page not found',
                'title' => 'This coconut has gone bad',
                'message' => 'The page may have moved, expired, or never existed. Use the links below to get back to a working part of NATCODEV.',
                'primary' => 'Go home',
                'primary_href' => 'index.php',
            ],
            500 => [
                'eyebrow' => 'Temporary problem',
                'title' => 'Something failed gracefully',
                'message' => 'The request could not be completed right now. Try again, or contact support if the issue continues.',
                'primary' => 'Try home',
                'primary_href' => 'index.php',
            ],
            503 => [
                'eyebrow' => 'Service warming up',
                'title' => 'This page is not ready yet',
                'message' => 'The service is temporarily unavailable. Please try again shortly, or contact support if you need help now.',
                'primary' => 'Try home',
                'primary_href' => 'index.php',
            ],
        ];
        return $copy[$statusCode] ?? $copy[404];
    }
}

if (!function_exists('app_render_error_page')) {
    /**
     * Render the branded NATCODEV error page in place and stop.
     *
     * Replaces the bare `http_response_code(403); exit('Forbidden…')` responses
     * that rendered as a blank screen with plain text. Links are prefixed for
     * the current directory depth (via nc_footer_root_prefix()) so the page
     * works when rendered from /, /admin/, /admin/wallet/ and so on.
     */
    function app_render_error_page(int $statusCode, string $detail = ''): void
    {
        $statusCode = in_array($statusCode, [401, 403, 404, 500, 503], true) ? $statusCode : 404;
        http_response_code($statusCode);

        $page = app_error_page_copy($statusCode);
        $prefix = nc_footer_root_prefix();
        $detailLine = trim($detail);
        $logoPath = app_primary_logo_url();
        $assetPath = $prefix . 'assets/public/rotten-coconut-error.png';
        $homeHref = $prefix . 'index.php';
        $supportHref = $prefix . 'support/index.php';
        $primaryHref = $prefix . $page['primary_href'];

        // "Go back" must always navigate somewhere: history.back() is a no-op
        // when the page was opened directly, so fall back to a same-site
        // referrer and finally to the home page.
        //
        // The referrer is only trusted when it points at a real HTML page.
        // A service worker that re-fetches a navigation (sw.js) sends its
        // own script URL as the Referer, and trusting it made "Go back"
        // open raw .js/.css/.png files rendered as plain text. Only accept
        // .php pages, directory paths, or extension-less page routes.
        $backHref = $homeHref;
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $baseUrl = rtrim((string) app_base_url(), '/') . '/';
        if ($referer !== '' && str_starts_with($referer, $baseUrl)) {
            $refererPath = (string) (parse_url($referer, PHP_URL_PATH) ?: '');
            $lastSegment = basename($refererPath);
            $isAsset = (bool) preg_match(
                '~\.(js|mjs|cjs|css|png|jpe?g|gif|svg|webp|avif|ico|bmp|woff2?|ttf|otf|eot|json|xml|txt|pdf|zip)(\?|$)~i',
                $refererPath
            );
            $extensionlessPage = $lastSegment !== '' && !str_contains($lastSegment, '.');
            $isPage = str_ends_with($refererPath, '.php') || str_ends_with($refererPath, '/') || $extensionlessPage;
            if ($isPage && !$isAsset) {
                $backHref = $referer;
            }
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string) $statusCode) ?> - <?= e($page['eyebrow']) ?> - NATCODEV</title>
  <style>
    :root{--green:#075f2a;--leaf:#0a7a3d;--mint:#edf8ef;--cream:#fffdf8;--soft:#f7faf6;--ink:#17231d;--muted:#66756d;--line:#dfe8d8;--gold:#d6a437}
    *{box-sizing:border-box}html{min-height:100%}body{margin:0;min-height:100vh;background:linear-gradient(180deg,#fffdf8 0%,#f5faf4 100%);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}
    .page{min-height:100vh;display:grid;grid-template-rows:auto 1fr}.top{height:76px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 clamp(18px,5vw,68px);border-bottom:1px solid rgba(7,95,42,.1);background:rgba(255,255,255,.76);backdrop-filter:blur(10px)}
    .brand{display:flex;align-items:center;gap:12px;color:var(--green);text-decoration:none;font-weight:950}.brand img{width:48px;height:48px;border-radius:50%;object-fit:contain;border:1px solid var(--line);background:#fff}.brand small{display:block;color:var(--muted);font-weight:750;font-size:.78rem;margin-top:1px}.support{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--green);padding:10px 13px;text-decoration:none;font-weight:900}
    main{display:grid;place-items:center;padding:clamp(24px,5vw,72px) clamp(18px,5vw,68px)}.shell{width:min(1080px,100%);display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,430px);gap:clamp(28px,6vw,74px);align-items:center}.copy{max-width:590px}.code{display:inline-flex;align-items:center;gap:8px;border:1px solid #dcebdc;background:#fff;border-radius:999px;color:var(--green);font-weight:950;padding:8px 12px;margin-bottom:18px}.dot{width:8px;height:8px;border-radius:50%;background:var(--gold)}
    h1{font-size:clamp(2.35rem,6vw,5.4rem);line-height:.98;letter-spacing:0;margin:0 0 18px;color:#122018}p{font-size:clamp(1rem,2vw,1.14rem);line-height:1.65;color:var(--muted);margin:0 0 26px;max-width:540px}.actions{display:flex;flex-wrap:wrap;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border-radius:8px;padding:12px 16px;text-decoration:none;font-weight:950;border:1px solid var(--line);background:#fff;color:var(--green)}.btn.primary{background:var(--green);border-color:var(--green);color:#fff}.btn:hover{transform:translateY(-1px)}
    .detail{background:var(--mint);border:1px solid #cfe6d4;border-radius:8px;color:#123f24;font-size:.95rem;line-height:1.5;padding:12px 14px;margin:-8px 0 22px;max-width:540px}
    .art{position:relative;min-height:330px;display:grid;place-items:center}.art::before{content:"";position:absolute;inset:6% 0 0 8%;border-radius:50%;background:radial-gradient(circle,rgba(7,95,42,.08),rgba(7,95,42,0) 68%)}.art img{position:relative;z-index:1;width:min(430px,100%);height:auto;display:block;filter:drop-shadow(0 28px 42px rgba(47,64,46,.14))}.hint{margin-top:18px;color:#7a877f;font-size:.92rem}
    @media(max-width:820px){.top{height:auto;padding:14px 18px}.brand small{display:none}.shell{grid-template-columns:1fr;text-align:center}.copy{margin:auto}.actions{justify-content:center}.art{order:-1;min-height:auto}.art img{width:min(300px,78vw)}p{margin-left:auto;margin-right:auto}.detail{margin-left:auto;margin-right:auto}}
  </style>
</head>
<body>
<div class="page">
  <header class="top">
    <a class="brand" href="<?= e($homeHref) ?>"><img src="<?= e($logoPath) ?>" alt="NATCODEV"><span>NATCODEV<small>National Coconut Development</small></span></a>
    <a class="support" href="<?= e($supportHref) ?>">Support</a>
  </header>
  <main>
    <section class="shell" aria-labelledby="error-title">
      <div class="copy">
        <div class="code"><span class="dot"></span><?= e((string) $statusCode) ?> <?= e($page['eyebrow']) ?></div>
        <h1 id="error-title"><?= e($page['title']) ?></h1>
        <p><?= e($page['message']) ?></p>
        <?php if ($detailLine !== ''): ?><p class="detail"><?= e($detailLine) ?></p><?php endif; ?>
        <div class="actions">
          <a class="btn primary" href="<?= e($primaryHref) ?>"><?= e($page['primary']) ?></a>
          <a class="btn" href="<?= e($backHref) ?>">Go back</a>
          <a class="btn" href="<?= e($supportHref) ?>">Get help</a>
        </div>
        <div class="hint">No worries. Nothing has been changed on your account.</div>
      </div>
      <figure class="art" aria-hidden="true"><img src="<?= e($assetPath) ?>" alt=""></figure>
    </section>
  </main>
</div>
<?= public_footer() ?>
</body>
</html>
<?php
        exit;
    }
}
