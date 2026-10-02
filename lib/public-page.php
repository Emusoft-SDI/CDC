<?php
declare(strict_types=1);

/**
 * Minimal shell for the visitor-facing content pages (Terms, Privacy, and any future
 * static page), so they match the rest of the public site without copying 40 lines of
 * CSS into each file.
 *
 * Pair with public_page_foot(), which closes the page and renders the shared footer.
 *
 *   <?php public_page_head('Privacy Policy', 'How NATCODEV handles personal data.'); ?>
 *   ...content...
 *   <?php public_page_foot(); ?>
 */

if (!function_exists('public_page_nav')) {
    /** @return array<string, string> label => path, relative to the application root */
    function public_page_nav(): array
    {
        return [
            'Home' => 'index.php',
            'About' => 'about.php',
            'Marketplace' => 'market/',
            'Academy' => 'academy/',
            'News' => 'news.php',
            'Contact' => 'contact.php',
        ];
    }
}

if (!function_exists('public_page_head')) {
    function public_page_head(string $title, string $description = ''): void
    {
        $prefix = nc_footer_root_prefix();
        $logo = '';
        try {
            $logo = app_primary_logo_url();
        } catch (Throwable $e) {
        }
        $office = ['phone_display' => '', 'phone_tel' => '', 'email' => ''];
        try {
            $office = app_contact_office();
        } catch (Throwable $e) {
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> | NATCODEV</title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<style>
  :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--max:1120px}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:linear-gradient(135deg,#eef8ee,#fff 45%,#f6faf3)}
  a{color:var(--green2)}
  .pc-top{background:var(--green);color:#fff}
  .pc-top-inner{max-width:var(--max);margin:0 auto;padding:11px 22px;display:flex;flex-wrap:wrap;gap:10px 18px;justify-content:space-between;align-items:center;font-size:.9rem}
  .pc-top a{color:#fff}
  .pc-header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}
  .pc-nav{max-width:var(--max);margin:0 auto;padding:12px 22px;display:flex;flex-wrap:wrap;gap:14px 20px;align-items:center;justify-content:space-between}
  .pc-brand{display:flex;gap:12px;align-items:center;text-decoration:none}
  .pc-brand img{width:50px;height:50px;border-radius:50%;object-fit:contain}
  .pc-brand strong{display:block;color:var(--green);font-size:1.35rem;line-height:1}
  .pc-brand span span{display:block;font-size:.62rem;font-weight:900;color:#111;letter-spacing:.02em}
  .pc-links{display:flex;flex-wrap:wrap;gap:16px;align-items:center;font-weight:800;font-size:.94rem}
  .pc-links a{color:#111827;text-decoration:none}
  .pc-links a:hover{color:var(--green2);text-decoration:underline;text-underline-offset:3px}
  .pc-cta{border:0;border-radius:9px;background:var(--green2);color:#fff;font-weight:900;padding:10px 16px;text-decoration:none;display:inline-block}
  .pc-cta:hover{background:var(--green)}
  .pc-wrap{max-width:820px;margin:0 auto;padding:40px 22px 8px}
  .pc-doc{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,40,.07);padding:34px}
  .pc-doc h1{margin:0 0 6px;color:var(--green);font-size:clamp(1.8rem,4vw,2.6rem);line-height:1.1}
  .pc-meta{margin:0 0 26px;color:var(--muted);font-size:.9rem}
  .pc-doc h2{margin:32px 0 10px;color:var(--green);font-size:1.22rem}
  .pc-doc h3{margin:22px 0 8px;color:#102033;font-size:1.02rem}
  .pc-doc p,.pc-doc li{line-height:1.7;color:#344054}
  .pc-doc ul{padding-left:22px;margin:10px 0}
  .pc-doc li{margin-bottom:7px}
  .pc-note{background:var(--soft);border:1px solid var(--line);border-left:4px solid var(--leaf);border-radius:10px;padding:14px 16px;margin:18px 0}
  .pc-note p{margin:0}
  .pc-toc{background:var(--soft);border:1px solid var(--line);border-radius:10px;padding:14px 18px;margin:0 0 26px}
  .pc-toc ol{margin:0;padding-left:20px}
  .pc-toc a{text-decoration:none}
  .pc-toc a:hover{text-decoration:underline}
  .pc-back{display:inline-block;margin-top:26px;font-weight:800;text-decoration:none}
  a:focus-visible,button:focus-visible{outline:2px solid var(--green2);outline-offset:3px;border-radius:3px}
  @media (max-width:760px){.pc-doc{padding:22px}.pc-links{justify-content:flex-start}}
  @media print{.pc-top,.pc-header{display:none}.pc-doc{box-shadow:none;border:0;padding:0}}
</style>
</head>
<body>
  <div class="pc-top">
    <div class="pc-top-inner">
      <span>NATCODEV — National Coconut Development &amp; Propagation Initiative</span>
      <span>
        <?php if ($office['phone_display']): ?><a href="tel:<?= e((string) $office['phone_tel']) ?>"><?= e((string) $office['phone_display']) ?></a><?php endif; ?>
        <?php if ($office['email']): ?> &nbsp;·&nbsp; <a href="mailto:<?= e((string) $office['email']) ?>"><?= e((string) $office['email']) ?></a><?php endif; ?>
      </span>
    </div>
  </div>
  <header class="pc-header">
    <nav class="pc-nav" aria-label="Main navigation">
      <a class="pc-brand" href="<?= e($prefix) ?>index.php">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="NATCODEV"><?php endif; ?>
        <span><strong>NATCODEV</strong><span>NATIONAL COCONUT DEVELOPMENT &amp; PROPAGATION INITIATIVE</span></span>
      </a>
      <div class="pc-links">
        <?php foreach (public_page_nav() as $label => $path): ?>
          <a href="<?= e($prefix . $path) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="pc-cta" href="<?= e($prefix) ?>login.php">Sign in</a>
      </div>
    </nav>
  </header>
  <main class="pc-wrap" id="main">
        <?php
    }
}

if (!function_exists('public_page_foot')) {
    function public_page_foot(): void
    {
        ?>
  </main>
<?= public_footer() ?>
</body>
</html>
        <?php
    }
}
