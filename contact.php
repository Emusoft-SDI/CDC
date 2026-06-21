<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/support.php';

$logo = app_primary_logo_url();
$year = date('Y');
$sent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $topic = trim((string) ($_POST['topic'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '') {
        $errors[] = 'Enter your name.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($topic === '') {
        $errors[] = 'Choose a contact topic.';
    }
    if ($message === '') {
        $errors[] = 'Tell us how NATCODEV can help.';
    }

    if (!$errors) {
        try {
            $category = [
                'Registry and grower onboarding' => 'general',
                'Provider or seller onboarding' => 'provider',
                'Marketplace order or seller support' => 'marketplace',
                'Academy and certificates' => 'academy',
                'Certificate verification' => 'verification',
                'Field operations' => 'field',
                'Partnerships' => 'general',
                'General enquiry' => 'general',
            ][$topic] ?? 'general';
            support_create_ticket(db(), [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $topic,
                'description' => $message,
                'category' => $category,
                'priority' => 'medium',
                'source' => 'contact_page',
            ]);
            $sent = true;
        } catch (Throwable $e) {
            error_log('Contact page submit failed: ' . $e->getMessage());
            $sent = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact NATCODEV</title>
  <meta name="description" content="Contact NATCODEV for registry, marketplace, academy, certificate verification, support, and partnership enquiries.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--shadow:0 18px 42px rgba(16,24,40,.1);--max:1180px}
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:linear-gradient(135deg,#eef8ee,#fff 45%,#f6faf3)}a{text-decoration:none;color:inherit}input,select,textarea,button{font:inherit}
    .top{background:#063f20;color:#fff}.top-inner{max-width:var(--max);margin:0 auto;padding:12px 22px;display:flex;justify-content:space-between;gap:18px;align-items:center;font-weight:850}.top a{color:#fff}
    .header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}.nav{max-width:var(--max);margin:0 auto;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{display:flex;gap:12px;align-items:center}.brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}.brand strong{display:block;color:var(--green);font-size:1.55rem;line-height:1}.brand span span{display:block;font-size:.68rem;font-weight:900;color:#111}.links{display:flex;gap:18px;align-items:center;font-weight:900}.links a{color:#111827}.links a:hover{color:var(--green2)}
    .hero{max-width:var(--max);margin:0 auto;padding:54px 22px 28px;display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:24px;align-items:end}.kicker{color:var(--green2);font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;font-weight:950}.hero h1{margin:8px 0 10px;color:var(--green);font-size:clamp(2.4rem,5vw,4.6rem);line-height:1}.hero p{margin:0;color:#344054;font-size:1.16rem;line-height:1.65;max-width:760px}.hero-card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;box-shadow:var(--shadow)}.hero-card strong{display:block;color:var(--green);font-size:1.05rem}.hero-card span{display:block;color:var(--muted);margin-top:5px}
    .wrap{max-width:var(--max);margin:0 auto 42px;padding:0 22px;display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:24px;align-items:start}.panel{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow);padding:24px}.panel h2,.side h2{margin:0 0 14px;color:var(--green)}.notice{border-radius:10px;padding:13px 15px;margin-bottom:14px;font-weight:850}.notice.ok{background:#e8f6ec;color:#075c34;border:1px solid #bfe7c9}.notice.err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wide{grid-column:1/-1}label{display:block;font-weight:900;color:#102033;margin-bottom:6px}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px 13px;background:#fbfdf9}textarea{min-height:150px;resize:vertical}input:focus,select:focus,textarea:focus{outline:0;border-color:var(--green2);box-shadow:0 0 0 3px rgba(8,117,58,.12)}.btn{border:0;border-radius:9px;background:var(--green2);color:#fff;font-weight:950;padding:13px 18px;display:inline-flex;align-items:center;gap:9px;cursor:pointer}
    .side{display:grid;gap:14px}.contact-card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px;display:grid;gap:12px}.contact-row{display:grid;grid-template-columns:42px 1fr;gap:12px;align-items:start}.contact-row i{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green2)}.contact-row strong{display:block;color:var(--green)}.contact-row span,.contact-row a{display:block;color:#475467;line-height:1.45}.quick{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.quick a{border:1px solid var(--line);border-radius:9px;padding:12px;background:#fbfdf9;color:var(--green);font-weight:900}
    .footer{background:#052915;color:#d8eadc;margin-top:36px}.footer-inner{max-width:var(--max);margin:0 auto;padding:22px;display:flex;justify-content:space-between;gap:18px;align-items:center}.footer a{color:#fff;font-weight:850}.footer-bottom{text-align:center;border-top:1px solid rgba(255,255,255,.12);padding:16px;color:#bdd5c1}
    @media(max-width:900px){.hero,.wrap{grid-template-columns:1fr}.links,.top-inner,.nav{flex-wrap:wrap}.form-grid,.quick{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="top">
    <div class="top-inner">
      <div><i class="fas fa-leaf"></i> NATCODEV support and stakeholder enquiries</div>
      <a href="index.php">Back to Home</a>
    </div>
  </div>
  <header class="header">
    <div class="nav">
      <a class="brand" href="index.php">
        <img src="<?= e($logo) ?>" alt="NATCODEV">
        <span><strong>NATCODEV</strong><span>NATIONAL COCONUT DEVELOPMENT & PROPAGATION INITIATIVE</span></span>
      </a>
      <nav class="links" aria-label="Contact navigation">
        <a href="index.php">Home</a>
        <a href="index.php#registry">Registry</a>
        <a href="market/index.php">Marketplace</a>
        <a href="academy/index.php?screen=catalog">Academy</a>
        <a href="verify-certificate.php">Verify</a>
        <a href="support/index.php">Support</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero">
      <div>
        <div class="kicker">Contact NATCODEV</div>
        <h1>Reach the right NATCODEV desk.</h1>
        <p>Use this page for registry questions, provider onboarding, marketplace support, Academy learning, certificate verification, field operations, partnerships, and general stakeholder enquiries.</p>
      </div>
      <aside class="hero-card">
        <strong>Need account help?</strong>
        <span>For signed-in users, the Support Desk gives the fastest tracking and follow-up.</span>
      </aside>
    </section>

    <section class="wrap">
      <article class="panel">
        <h2>Send an enquiry</h2>
        <?php if ($sent): ?>
          <div class="notice ok">Your enquiry has been received. NATCODEV support will follow up through the details provided.</div>
        <?php elseif ($errors): ?>
          <div class="notice err"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <div class="form-grid">
            <div><label for="name">Full Name</label><input id="name" name="name" value="<?= e((string) ($_POST['name'] ?? '')) ?>" required></div>
            <div><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>" required></div>
            <div><label for="phone">Phone Number</label><input id="phone" name="phone" value="<?= e((string) ($_POST['phone'] ?? '')) ?>"></div>
            <div>
              <label for="topic">Topic</label>
              <select id="topic" name="topic" required>
                <?php foreach (['Registry and grower onboarding','Provider or seller onboarding','Marketplace order or seller support','Academy and certificates','Certificate verification','Field operations','Partnerships','General enquiry'] as $topic): ?>
                  <option value="<?= e($topic) ?>" <?= (($_POST['topic'] ?? '') === $topic) ? 'selected' : '' ?>><?= e($topic) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="wide"><label for="message">Message</label><textarea id="message" name="message" required><?= e((string) ($_POST['message'] ?? '')) ?></textarea></div>
            <div class="wide"><button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Submit Enquiry</button></div>
          </div>
        </form>
      </article>

      <aside class="side">
        <div class="contact-card">
          <h2>Direct paths</h2>
          <div class="contact-row"><i class="fas fa-user-plus"></i><div><strong>Registry</strong><a href="apply.php?type=farmer">Start grower registration</a><a href="provider/index.php">Register as provider</a></div></div>
          <div class="contact-row"><i class="fas fa-headset"></i><div><strong>Support Desk</strong><a href="support/index.php">Open support desk</a><span>Track platform issues and service requests.</span></div></div>
          <div class="contact-row"><i class="fas fa-award"></i><div><strong>Verification</strong><a href="verify-certificate.php">Verify certificate</a><span>Confirm NATCODEV certificates and credentials.</span></div></div>
        </div>
        <div class="contact-card">
          <h2>Explore</h2>
          <div class="quick">
            <a href="market/index.php"><i class="fas fa-store"></i> Marketplace</a>
            <a href="academy/index.php?screen=catalog"><i class="fas fa-graduation-cap"></i> Academy</a>
            <a href="recruitment.php"><i class="fas fa-location-dot"></i> Field Network</a>
            <a href="login.php"><i class="fas fa-right-to-bracket"></i> Login</a>
          </div>
        </div>
      </aside>
    </section>
  </main>

  <footer class="footer">
    <div class="footer-inner">
      <strong>NATCODEV</strong>
      <div><a href="index.php">Home</a> &nbsp; <a href="support/index.php">Support</a> &nbsp; <a href="verify-certificate.php">Verify</a></div>
    </div>
    <div class="footer-bottom">&copy; <?= e($year) ?> NATCODEV. All rights reserved.</div>
  </footer>
</body>
</html>
>Support</a> &nbsp; <a href="verify-certificate.php">Verify</a></div>
    </div>
    <div class="footer-bottom">&copy; <?= e($year) ?> NATCODEV. All rights reserved.</div>
  </footer>
</body>
</html>
