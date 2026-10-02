<?php
declare(strict_types=1);

/**
 * Grower registration entry point.
 *
 * This page used to ask for roughly eighteen fields on one screen and drew a
 * decorative "Step 1 of 6" strip whose steps 2-6 did not exist. Now it collects only
 * what is needed to create the account: name, email, phone, password. Verification and
 * the six substantive steps live in the shared wizard (register-wizard.php?board=grower),
 * which is gated behind the emailed OTP and can be resumed at any time.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/nigeria-locations.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
app_ensure_core_schema($pdo);

$applicationType = strtolower((string) ($_GET['type'] ?? 'farmer'));
$applicationTypeLabel = match ($applicationType) {
    'outgrower' => 'Commercial Coconut Outgrowers Registration',
    'cooperative' => 'Coconut Farmers Cooperative Registration',
    default => 'Coconut Grower Registration',
};
$seedMemberType = match ($applicationType) {
    'cooperative' => 'cooperative',
    'corporate' => 'corporate',
    default => 'individual',
};

$error = '';
if (isset($_GET['social'])) {
    app_oauth_begin((string) $_GET['social'], 'grower', 'dashboard/index.php');
}
if (isset($_GET['oauth_callback'])) {
    try {
        $oauthUser = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'grower');
        $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, 'dashboard/index.php');
        if ($otpStart['ok']) {
            redirect_to('verify-otp.php');
        }
        $error = (string) $otpStart['message'];
    } catch (Throwable $e) {
        error_log('Grower OAuth error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
if (isset($_GET['oauth_error'])) {
    $error = app_oauth_missing_credentials_message((string) ($_GET['provider'] ?? 'google'));
}

$logo = app_primary_logo_url();
$stepPreview = [];
foreach (registration_wizard_steps((array) registration_wizard_board('grower')) as $step) {
    $stepPreview[] = (string) $step['title'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as a Grower - NATCODEV</title>
  <meta name="description" content="Register as a coconut grower with NATCODEV. Four fields to start, verify your email, then complete your farm profile step by step.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#08753a;--mint:#eef8ef;--gold:#d89b10;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#fbfcf8;--soft:#f7fbf4;--shadow:0 18px 48px rgba(16,24,40,.08)}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}
    a{text-decoration:none;color:inherit}
    .top{min-height:78px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 36px;position:sticky;top:0;z-index:20}
    .brand{display:flex;gap:12px;align-items:center}
    .brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}
    .brand strong{font-size:1.5rem;color:var(--green);display:block;line-height:1}
    .brand small{display:block;font-weight:800;color:#344054;font-size:.68rem}
    .nav{display:flex;gap:24px;font-weight:800;flex-wrap:wrap}
    .nav a:hover{color:var(--green2)}
    .hero{min-height:230px;background:linear-gradient(90deg,rgba(4,38,16,.88),rgba(4,38,16,.6) 48%,rgba(4,38,16,.15)),url("assets/public/grower-registration-hero.png") center/cover;color:#fff;padding:40px 56px;display:flex;align-items:center}
    .hero h1{font-size:clamp(2rem,4vw,3.6rem);line-height:1.05;margin:0 0 12px}
    .hero p{font-size:1.08rem;line-height:1.55;max-width:660px;font-weight:600;margin:0}
    .wrap{padding:0 52px 34px}
    .shell{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:0;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-top:-38px;position:relative;z-index:2;overflow:hidden}
    .form{padding:30px 34px}
    .form h2{color:var(--green);margin:0 0 8px;font-size:1.4rem}
    .form h3{color:var(--green);margin:0 0 10px;font-size:1.05rem}
    .lead{color:#344054;font-weight:600;line-height:1.6;margin:0 0 22px}
    .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .wide{grid-column:1/-1}
    label{display:block;font-weight:800;color:#1f2937;font-size:.93rem}
    input{width:100%;border:1px solid var(--line);border-radius:9px;padding:13px;margin-top:7px;font:inherit;background:#fff}
    input:focus{outline:3px solid #dff3e5;border-color:#7ccf94}
    .submit-row{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:20px}
    .btn{display:inline-flex;gap:10px;align-items:center;justify-content:center;border:1px solid var(--green);border-radius:8px;background:var(--green);color:#fff;padding:13px 20px;cursor:pointer;font:inherit;font-weight:800}
    .btn:hover{background:var(--green2);border-color:var(--green2)}
    .btn.light{background:#fff;color:var(--green)}
    .btn.light:hover{background:var(--soft);color:var(--green)}
    .hint{color:var(--muted);font-size:.88rem;font-weight:600;line-height:1.5}
    .safe{display:flex;gap:14px;align-items:center;background:#f5fbf4;border:1px solid #cbe8cd;border-radius:10px;padding:14px;margin:18px 0}
    .safe i{color:var(--green);font-size:1.2rem}
    .divider{border:0;border-top:1px solid var(--line);margin:28px 0}
    .steps-preview{display:grid;gap:8px;margin:0;padding:0;list-style:none;counter-reset:sp}
    .steps-preview li{counter-increment:sp;display:flex;gap:10px;align-items:flex-start;font-weight:700;color:#344054;font-size:.93rem}
    .steps-preview li:before{content:counter(sp);width:24px;height:24px;border-radius:50%;background:var(--mint);color:var(--green);display:grid;place-items:center;font-weight:800;flex:0 0 auto;font-size:.78rem}
    .side{border-left:1px solid var(--line);padding:24px;background:var(--soft)}
    .guide-card{position:sticky;top:100px}
    .mini-help{display:flex;gap:12px;align-items:center;border:1px solid #cbe8cd;border-radius:10px;background:#fff;padding:14px;margin-bottom:14px}
    .mini-help i{color:var(--green);font-size:1.3rem}
    .guide-section{border:1px solid var(--line);border-radius:10px;background:#fff;margin-top:10px;overflow:hidden}
    .guide-section summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 15px;font-weight:800;color:#1f2937}
    .guide-section summary::-webkit-details-marker{display:none}
    .guide-section summary:after{content:"+";width:24px;height:24px;border-radius:50%;background:var(--mint);color:var(--green);display:grid;place-items:center}
    .guide-section[open] summary:after{content:"-"}
    .guide-body{border-top:1px solid var(--line);padding:13px 15px;color:#475467;font-weight:600;line-height:1.5;font-size:.92rem}
    .guide-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}
    .guide-list li{display:flex;gap:10px;align-items:flex-start}
    .guide-list i{color:var(--green);margin-top:3px}
    .doc-row{display:flex;justify-content:space-between;gap:10px;margin:10px 0}
    .badge{border-radius:999px;background:#fff3d6;color:#9a6500;padding:4px 8px;font-size:.78rem;font-weight:800;white-space:nowrap}
    .trust{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;margin-top:18px}
    .trust-item{display:flex;gap:12px;align-items:center}
    .trust-item i{font-size:1.5rem;color:var(--green)}
    .trust-item strong{display:block}
    .trust-item span{color:#475467;font-weight:600}
    .alert{padding:13px;border-radius:10px;margin-bottom:16px;font-weight:700;line-height:1.5}
    .alert.err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}
    a:focus-visible,button:focus-visible,input:focus-visible{outline:2px solid var(--green2);outline-offset:2px}
    @media(max-width:900px){.shell{grid-template-columns:1fr}.side{border-left:0;border-top:1px solid var(--line)}.wrap{padding:0 20px 24px}.top{padding:0 18px}.hero{padding:32px 22px}.grid2,.trust{grid-template-columns:1fr}.nav{display:none}}
  </style>
</head>
<body>
<header class="top">
  <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>National Coconut Development &amp; Propagation Initiative</small></span></a>
  <nav class="nav" aria-label="Main navigation">
    <a href="index.php">Home</a>
    <a href="market/index.php">Marketplace</a>
    <a href="academy/index.php">Academy</a>
    <a href="verify-certificate.php">Certificates</a>
    <a href="support/index.php?category=account">Support</a>
    <a href="login.php">Sign in</a>
  </nav>
</header>

<section class="hero">
  <div>
    <h1><?= e($applicationTypeLabel) ?></h1>
    <p>Four details to begin. Confirm your email, then complete the rest step by step &mdash; stopping whenever you need to and picking up exactly where you left off.</p>
  </div>
</section>

<main class="wrap">
  <section class="shell">
    <section class="form">
      <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

      <h2>Start your registration</h2>
      <p class="lead">We will email you a 6-digit code to confirm your address. After that you work through the six steps at your own pace.</p>

      <?= app_social_buttons('grower', 'dashboard/index.php') ?>

      <form method="post" action="register-wizard.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="board" value="grower">
        <input type="hidden" name="action" value="start">
        <input type="hidden" name="member_type" value="<?= e($seedMemberType) ?>">
        <div class="grid2">
          <div class="wide"><label for="ap-name">Full Name *<input id="ap-name" name="name" required autocomplete="name" placeholder="Enter your full name"></label></div>
          <label for="ap-phone">Phone Number *<input id="ap-phone" name="phone" type="tel" required autocomplete="tel" placeholder="08012345678"></label>
          <label for="ap-email">Email Address *<input id="ap-email" name="email" type="email" required autocomplete="email" placeholder="you@example.com"></label>
          <div class="wide"><label for="ap-password">Create a Password *<input id="ap-password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></label></div>
        </div>
        <div class="submit-row">
          <button class="btn" type="submit">Continue &amp; verify email <i class="fas fa-arrow-right"></i></button>
          <span class="hint">Already have an account? <a style="color:var(--green);font-weight:800" href="login.php">Sign in</a></span>
        </div>
      </form>

      <div class="safe"><i class="fas fa-seedling"></i><span><strong>Your Data is Safe</strong><br>Your information is protected and used only for NATCODEV programme purposes.</span></div>

      <hr class="divider">

      <h3>Already started?</h3>
      <p class="lead" style="margin-bottom:14px">Enter the email you used and we will send a fresh code, then return you to the step you stopped at.</p>
      <form method="post" action="register-wizard.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="board" value="grower">
        <input type="hidden" name="action" value="resume">
        <div class="grid2">
          <div class="wide"><label for="ap-resume">Email you registered with<input id="ap-resume" name="email" type="email" required placeholder="you@example.com"></label></div>
        </div>
        <div class="submit-row"><button class="btn light" type="submit">Send me a new code</button></div>
      </form>

      <hr class="divider">

      <h3>What the six steps cover</h3>
      <p class="lead" style="margin-bottom:14px">Each step saves as you finish it. Close the page at any point and come back later.</p>
      <ol class="steps-preview">
        <?php foreach ($stepPreview as $title): ?>
          <li><span><?= e($title) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <aside class="side">
      <div class="guide-card">
        <h2>Registration Guide</h2>
        <p class="hint" style="margin-bottom:16px">Useful details are here when you need them, while the form stays clear.</p>
        <div class="mini-help"><i class="fas fa-headset"></i><span><strong>Need help?</strong><br><a style="color:var(--green);font-weight:800" href="support/index.php?category=account">Contact support</a></span></div>
        <details class="guide-section" open>
          <summary>Why register?</summary>
          <div class="guide-body">
            <ul class="guide-list">
              <li><i class="fas fa-award"></i><span>Verified grower certificate after review.</span></li>
              <li><i class="fas fa-chart-line"></i><span>Farm dashboard for records and performance.</span></li>
              <li><i class="fas fa-cart-shopping"></i><span>Marketplace, academy, wallet, and support access.</span></li>
            </ul>
          </div>
        </details>
        <details class="guide-section">
          <summary>Required documents</summary>
          <div class="guide-body">
            <div class="doc-row"><span><i class="fas fa-id-card"></i> Valid ID Card</span><span class="badge">Required</span></div>
            <div class="doc-row"><span><i class="fas fa-camera"></i> Farm Evidence</span><span class="badge">Required</span></div>
            <div class="doc-row"><span><i class="fas fa-users"></i> Cooperative Membership</span><span class="badge">Optional</span></div>
            <p style="margin:10px 0 0">Uploads can be completed after your dashboard is created.</p>
          </div>
        </details>
        <details class="guide-section">
          <summary>What happens next?</summary>
          <div class="guide-body">
            <ol style="margin:0;padding-left:18px">
              <li>Enter four details and confirm your email.</li>
              <li>Work through the six steps &mdash; each one saves.</li>
              <li>Submit for review.</li>
              <li>Your certificate becomes available when approved.</li>
            </ol>
          </div>
        </details>
        <details class="guide-section">
          <summary>Stopped part-way?</summary>
          <div class="guide-body">
            Nothing is lost. Return to this page, enter your email and we will send a new code so you can continue from where you stopped. Codes last 10 minutes.
          </div>
        </details>
      </div>
    </aside>
  </section>

  <section class="trust">
    <div class="trust-item"><i class="fas fa-shield-heart"></i><span><strong>Free registration</strong>No hidden charges.</span></div>
    <div class="trust-item"><i class="fas fa-lock"></i><span><strong>Secure records</strong>Shared only with authorized officials.</span></div>
    <div class="trust-item"><i class="fas fa-seedling"></i><span><strong>Built for growers</strong>Designed for Nigerian farmers.</span></div>
  </section>
</main>
<?= public_footer() ?>
</body>
</html>
