<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$logo = app_primary_logo_url();
$year = date('Y');

$roles = [
    ['icon' => 'fa-seedling', 'title' => 'Grower', 'text' => 'Manage your farm, access support, and grow better.', 'href' => 'apply.php', 'tone' => 'green'],
    ['icon' => 'fa-user-gear', 'title' => 'Learners', 'text' => 'Find jobs, get trained, and build your skills.', 'href' => '/academy/register.php', 'tone' => 'green'],
    ['icon' => 'fa-box-open', 'title' => 'Input Provider', 'text' => 'Supply quality inputs and grow your business.', 'href' => 'provider/index.php?', 'tone' => 'green'],
    ['icon' => 'fa-screwdriver-wrench', 'title' => 'Service Provider', 'text' => 'Offer services and solutions to farmers.', 'href' => 'provider/index.php', 'tone' => 'teal'],
    ['icon' => 'fa-store', 'title' => 'Marketplace Seller', 'text' => 'Sell your products and reach more buyers.', 'href' => 'market/seller-central.php', 'tone' => 'gold'],
    ['icon' => 'fa-cart-shopping', 'title' => 'Buyer', 'text' => 'Buy quality products and farm services.', 'href' => 'buyer/register.php', 'tone' => 'gold'],
    ['icon' => 'fa-location-dot', 'title' => 'Field Agent', 'text' => 'Verify, inspect, and support on the field.', 'href' => '/field-agent/login.php', 'tone' => 'blue'],
    ['icon' => 'fa-people-group', 'title' => 'Coordinator', 'text' => 'Coordinate activities and drive impact.', 'href' => 'login.php', 'tone' => 'purple'],
];

$features = [
    ['icon' => 'fa-shield-halved', 'title' => 'Verified Registry', 'text' => 'Secure identity verification for growers, farms, and stakeholders.', 'href' => 'apply.php?type=farmer'],
    ['icon' => 'fa-chart-line', 'title' => 'Farm Performance', 'text' => 'Track productivity, yield, and profitability in real time.', 'href' => 'login.php'],
    ['icon' => 'fa-cow', 'title' => 'Intercrop & Livestock Income', 'text' => 'Diversify income with intercrops and livestock management.', 'href' => 'login.php'],
    ['icon' => 'fa-graduation-cap', 'title' => 'NATCODEV Academy', 'text' => 'Learn practical skills and earn industry-recognized certificates.', 'href' => 'academy/index.php'],
    ['icon' => 'fa-bag-shopping', 'title' => 'Marketplace Access', 'text' => 'Buy and sell verified inputs, products, equipment, and services.', 'href' => 'market/index.php'],
    ['icon' => 'fa-wallet', 'title' => 'Wallet & Payments', 'text' => 'Secure payments, payouts, and access to finance.', 'href' => 'dashboard/wallet.php'],
    ['icon' => 'fa-award', 'title' => 'Certificate Verification', 'text' => 'Verify certificates and accreditations with confidence.', 'href' => 'verify-certificate.php'],
    ['icon' => 'fa-headset', 'title' => 'Support Desk', 'text' => 'Get help when you need it. We are here for your success.', 'href' => 'support/index.php'],
];

$trust = [
    ['icon' => 'fa-shield-heart', 'title' => 'Trusted by thousands', 'text' => 'of coconut farmers'],
    ['icon' => 'fa-map', 'title' => 'Active in all', 'text' => '36 States'],
    ['icon' => 'fa-users', 'title' => 'One platform for', 'text' => 'all stakeholders'],
    ['icon' => 'fa-leaf', 'title' => 'Sustainable coconut', 'text' => 'value chain'],
    ['icon' => 'fa-lock', 'title' => 'Secure, transparent', 'text' => 'and compliant'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV - National Coconut Development & Propagation Initiative</title>
  <meta name="description" content="NATCODEV connects coconut growers, farm hands, providers, sellers, buyers, field teams, academy learners, and coordinators through one coconut development platform.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--teal:#0e7e7d;--blue:#2f72d8;--purple:#5b3ba6;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--shadow:0 18px 42px rgba(16,24,40,.1);--max:1760px}
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f7faf5}a{text-decoration:none;color:inherit}button,input{font:inherit}
    .top-strip{background:linear-gradient(90deg,#063f20,#085d2b);color:#fff;padding:11px 30px;font-weight:800}.top-inner{max-width:var(--max);margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:20px}.top-links{display:flex;gap:18px;align-items:center}.top-links a{color:#fff}.social{display:flex;gap:14px}
    .header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:40}.nav-wrap{max-width:var(--max);margin:0 auto;min-height:86px;padding:10px 24px;display:grid;grid-template-columns:minmax(260px,330px) minmax(0,1fr) auto;gap:18px;align-items:center}.brand{display:flex;gap:12px;align-items:center;min-width:0}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain;flex:0 0 auto}.brand strong{display:block;font-size:1.85rem;line-height:.95;color:var(--green);letter-spacing:.02em}.brand span{display:block;font-size:.68rem;font-weight:900;color:#111;letter-spacing:.03em}.nav{display:flex;justify-content:center;gap:22px;align-items:center;font-weight:900;min-width:0}.nav a{color:#111827;white-space:nowrap}.nav a:hover{color:var(--green2)}.actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;min-width:0}.icon-btn{width:48px;height:48px;border:1px solid var(--line);border-radius:10px;background:#fff;display:grid;place-items:center;font-size:1.05rem;flex:0 0 auto}.btn{border-radius:10px;border:1px solid var(--green);min-height:48px;padding:12px 22px;font-weight:950;display:inline-flex;gap:9px;align-items:center;justify-content:center;white-space:nowrap}.actions .btn{padding-left:20px;padding-right:20px}.btn.primary{background:var(--green2);color:#fff;border-color:var(--green2)}.btn.light{background:#fff;color:var(--green)}
    .hero{min-height:430px;background:linear-gradient(90deg,rgba(0,0,0,.54) 0%,rgba(4,45,19,.44) 36%,rgba(0,0,0,.08) 72%),url("assets/public/natcodev-home-hero.png") center/cover no-repeat;color:#fff;position:relative}.hero-inner{max-width:var(--max);margin:0 auto;padding:58px 30px 85px}.hero-copy{max-width:760px}.hero h1{font-size:clamp(3rem,5.6vw,5.7rem);line-height:1.02;margin:0 0 18px;text-shadow:0 2px 20px rgba(0,0,0,.35)}.hero h1 span{color:#8ed17b}.hero p{font-size:1.38rem;line-height:1.45;margin:0 0 26px;font-weight:700;text-shadow:0 2px 14px rgba(0,0,0,.35)}.hero-actions{display:flex;flex-wrap:wrap;gap:16px}.hero .btn{font-size:1.15rem;min-width:230px}.hero .btn.light{background:rgba(0,0,0,.18);color:#fff;border-color:#fff}
    .role-panel{max-width:1600px;margin:-64px auto 18px;position:relative;z-index:5;background:#fff;border:1px solid var(--line);border-radius:20px;padding:18px 24px 26px;box-shadow:var(--shadow)}.section-title{display:flex;align-items:center;justify-content:center;gap:24px;color:var(--green);font-size:1.8rem;font-weight:950;margin:0 0 16px}.section-title::before,.section-title::after{content:"";height:1px;background:#a9d7ad;flex:0 1 140px}.role-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:18px}.role-card{border:1px solid var(--line);border-radius:12px;background:#fff;min-height:160px;padding:20px 16px;display:grid;text-align:center;position:relative;box-shadow:0 8px 20px rgba(16,24,40,.04);transition:.18s ease}.role-card:hover{transform:translateY(-3px);box-shadow:var(--shadow);border-color:#b9dfbd}.role-icon{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;margin:0 auto 8px;font-size:1.75rem;background:#e8f6ec;color:var(--green2);border:1px solid #ccebd3}.role-card.gold .role-icon{background:#fff4d9;color:#a36d00}.role-card.teal .role-icon{background:#e2f7f5;color:var(--teal)}.role-card.blue .role-icon{background:#e8f1ff;color:var(--blue)}.role-card.purple .role-icon{background:#f0ebff;color:var(--purple)}.role-card h3{margin:0 0 6px;color:var(--green);font-size:1.05rem}.role-card p{margin:0;color:#1f2937;line-height:1.35;font-weight:650}.role-card .arrow{position:absolute;right:17px;bottom:14px;color:var(--green);font-size:1.2rem}
    .platform{max-width:1600px;margin:0 auto 28px;background:linear-gradient(135deg,#f3faf0,#fff);border:1px solid var(--line);border-radius:12px;padding:18px 18px 0;box-shadow:0 10px 28px rgba(16,24,40,.06)}.feature-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:12px}.feature-card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;display:grid;grid-template-columns:54px 1fr;gap:12px;align-items:center;min-height:118px}.feature-card i{font-size:2.25rem;color:var(--green)}.feature-card h3{margin:0 0 4px;color:var(--green);font-size:.98rem}.feature-card p{margin:0;color:#1f2937;font-size:.82rem;line-height:1.35}.trust-row{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:0;border-top:1px solid var(--line);margin-top:16px}.trust-item{padding:18px;display:flex;gap:12px;align-items:center;border-right:1px solid var(--line);font-weight:850}.trust-item:last-child{border-right:0}.trust-item i{font-size:1.8rem;color:var(--green)}.learn{background:var(--green);color:#fff;border-radius:8px;padding:15px 20px;justify-content:center}
    .story{max-width:1600px;margin:0 auto 34px;display:grid;grid-template-columns:1fr 1fr;gap:22px}.story-img{min-height:330px;background:url("assets/public/natcodev-community-impact.png") center/cover no-repeat;border-radius:16px;box-shadow:var(--shadow)}.story-card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:30px;box-shadow:var(--shadow)}.story-card h2{font-size:2.2rem;color:var(--green);margin:0 0 10px}.story-card p{font-size:1.05rem;color:#344054;line-height:1.65}.quick-links{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:18px}.quick-links a{border:1px solid var(--line);border-radius:10px;padding:14px;font-weight:950;color:var(--green);background:#f9fcf7}
    .footer{background:#052915;color:#e8f6ec;margin-top:36px}.footer-inner{max-width:var(--max);margin:0 auto;padding:38px 30px;display:grid;grid-template-columns:1.3fr repeat(4,1fr);gap:28px}.footer h3{margin:0 0 12px;color:#fff}.footer p,.footer a{color:#cfe5d3;line-height:1.65}.footer a{display:block;margin:6px 0}.footer-brand{display:flex;gap:12px;align-items:center;margin-bottom:12px}.footer-brand img{width:58px;height:58px;background:#fff;border-radius:50%}.footer-bottom{border-top:1px solid rgba(255,255,255,.12);padding:18px 30px;text-align:center;color:#bdd5c1}
    @media(max-width:1320px){.role-grid,.feature-grid{grid-template-columns:repeat(4,1fr)}.nav-wrap{grid-template-columns:1fr}.nav,.actions{justify-content:flex-start;flex-wrap:wrap}.trust-row{grid-template-columns:repeat(3,1fr)}.story{grid-template-columns:1fr;padding:0 18px}.platform,.role-panel{margin-left:18px;margin-right:18px}}
    @media(max-width:820px){.top-inner,.top-links,.nav,.actions,.hero-actions{align-items:flex-start;flex-direction:column}.nav-wrap{padding:14px}.role-grid,.feature-grid,.quick-links,.footer-inner{grid-template-columns:1fr}.trust-row{grid-template-columns:1fr}.hero-inner{padding:42px 18px 92px}.role-panel{margin-top:-42px}.section-title{font-size:1.3rem}.brand strong{font-size:1.55rem}}
  </style>
</head>
<body>
  <div class="top-strip">
    <div class="top-inner">
      <div><i class="fas fa-leaf"></i> Building productive coconut communities for Nigeria's future.</div>
      <div class="top-links">
        <a href="#about">About Us</a><span>|</span><a href="#updates">News & Updates</a><span>|</span><a href="contact.php">Contact Us</a>
        <span class="social"><i class="fab fa-facebook-f"></i><i class="fab fa-x-twitter"></i><i class="fab fa-youtube"></i><i class="fab fa-linkedin"></i></span>
      </div>
    </div>
  </div>

  <header class="header">
    <div class="nav-wrap">
      <a class="brand" href="index.php">
        <img src="<?= e($logo) ?>" alt="NATCODEV">
        <span><strong>NATCODEV</strong><span>NATIONAL COCONUT DEVELOPMENT & PROPAGATION INITIATIVE</span></span>
      </a>
      <nav class="nav" aria-label="Primary navigation">
        <a href="index.php">Home</a>
        <a href="/registry">Registry</a>
        <a href="market/index.php">Marketplace</a>
        <a href="academy/index.php?screen=catalog">Academy</a>
        <a href="verify-certificate.php">Verify</a>
        <a href="support/index.php">Support</a>
      </nav>
      <div class="actions">
        <a class="icon-btn" href="market/index.php" aria-label="Search marketplace"><i class="fas fa-search"></i></a>
        <a class="btn light" href="login.php">Login</a>
        <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Register</a>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="hero-inner">
        <div class="hero-copy">
          <h1>National Coconut <span>Development & Propagation</span> Initiative</h1>
          <p>Register, verify, train, trade, and grow with one connected coconut development platform.</p>
          <div class="hero-actions">
            <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Register Now</a>
            <a class="btn light" href="market/index.php"><i class="fas fa-cart-shopping"></i> Explore Marketplace</a>
          </div>
        </div>
      </div>
    </section>

    <section id="registry" class="role-panel">
      <h2 class="section-title"><i class="fas fa-leaf"></i> Choose your role. Start your journey. <i class="fas fa-leaf"></i></h2>
      <div class="role-grid">
        <?php foreach ($roles as $role): ?>
          <a class="role-card <?= e($role['tone']) ?>" href="<?= e($role['href']) ?>">
            <span class="role-icon"><i class="fas <?= e($role['icon']) ?>"></i></span>
            <h3><?= e($role['title']) ?></h3>
            <p><?= e($role['text']) ?></p>
            <span class="arrow"><i class="fas fa-chevron-right"></i></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section id="programs" class="platform">
      <h2 class="section-title"><i class="fas fa-leaf"></i> One Platform, Every Stakeholder <i class="fas fa-leaf"></i></h2>
      <div class="feature-grid">
        <?php foreach ($features as $feature): ?>
          <a class="feature-card" href="<?= e($feature['href']) ?>">
            <i class="fas <?= e($feature['icon']) ?>"></i>
            <span><h3><?= e($feature['title']) ?></h3><p><?= e($feature['text']) ?></p></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="trust-row">
        <?php foreach ($trust as $item): ?>
          <div class="trust-item"><i class="fas <?= e($item['icon']) ?>"></i><span><?= e($item['title']) ?><br><?= e($item['text']) ?></span></div>
        <?php endforeach; ?>
        <a class="trust-item learn" href="#about">Learn More About NATCODEV <i class="fas fa-chevron-right"></i></a>
      </div>
    </section>

    <section id="about" class="story">
      <div class="story-img" aria-label="Coconut farmers and marketplace community"></div>
      <article class="story-card">
        <h2>A meaningful entry point for the whole coconut value chain.</h2>
        <p>NATCODEV brings registry, verification, Academy training, certificates, marketplace access, wallet payments, farm operations, reporting, and support into one public-facing platform for growers, farm hands, providers, sellers, buyers, field teams, coordinators, and visitors.</p>
        <div class="quick-links">
          <a href="market/index.php"><i class="fas fa-store"></i> Marketplace</a>
          <a href="academy/index.php"><i class="fas fa-graduation-cap"></i> Academy</a>
          <a href="verify-certificate.php"><i class="fas fa-award"></i> Verify Certificate</a>
          <a href="support/index.php"><i class="fas fa-headset"></i> Support Desk</a>
        </div>
      </article>
    </section>
  </main>

  <footer id="contact" class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-brand"><img src="<?= e($logo) ?>" alt="NATCODEV"><strong>NATCODEV</strong></div>
        <p>National Coconut Development & Propagation Initiative. Building productive coconut communities and a sustainable coconut value chain.</p>
      </div>
      <div><h3>Registry</h3><a href="apply.php?type=farmer">Grower Registration</a><a href="provider/index.php">Provider Registration</a><a href="field-agent/login.php">Field Network</a></div>
      <div><h3>Marketplace</h3><a href="market/index.php">Browse Marketplace</a><a href="market/stores.php">Seller Directory</a><a href="provider/login.php">Seller Central</a></div>
      <div><h3>Academy</h3><a href="academy/index.php#catalog">Course Catalog</a><a href="academy/">My Learning</a><a href="academy/register.php">Certificates</a></div>
      <div><h3>Support</h3><a href="verify-certificate.php">Verify Certificate</a><a href="login.php">Login</a><a href="support/index.php">Support Desk</a></div>
    </div>
    <div class="footer-bottom">&copy; <?= e($year) ?> NATCODEV. All rights reserved.</div>
  </footer>
</body>
</html>
