<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';

$pdo = provider_boot();
$message = '';
$error = '';
$socialNotice = isset($_GET['social']) ? 'Social sign-in is designed into the provider entry. Connect Google OAuth keys to activate live one-click provider login.' : '';

$providerIntents = [
    'Input Provider' => 'Supply seedlings, fertilizer, tools, or other farm inputs.',
    'Service Provider' => 'Offer farm services, advisory, training, or field support.',
    'Marketplace Seller' => 'Sell coconut products, equipment, or value-chain goods.',
    'Processor / Aggregator' => 'Process, aggregate, or coordinate value-chain supply.',
    'Cooperative / Partner' => 'Register a group, partner organization, or cooperative.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('provider_registration', 5, 3600)) {
        $error = 'Too many registration attempts. Please try again in an hour.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Please refresh the page.';
    } else {
        $businessName = trim((string) ($_POST['company_name'] ?? ''));
    $fullName = trim((string) ($_POST['contact_person'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $providerIntent = trim((string) ($_POST['provider_intent'] ?? ''));
    $allowedIntents = array_keys($providerIntents);

    if ($businessName === '' || $fullName === '' || !$email || $phone === '' || strlen($password) < 6) {
        $error = 'Business name, contact person, valid email, phone, and a 6-character password are required.';
    } elseif (!in_array($providerIntent, $allowedIntents, true)) {
        $error = 'Select the provider pathway that best describes your entry point.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([(string) $email]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId > 0) {
                throw new RuntimeException('email_exists');
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, 'provider', 'provider', 'active')");
                $stmt->execute([$fullName, (string) $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
                $userId = (int) $pdo->lastInsertId();
            }

            $token = strtoupper(bin2hex(random_bytes(5)));
            $providerType = $providerIntent === 'Input Provider' ? 'input' : 'service';
            $stmt = $pdo->prepare("
                INSERT INTO provider_registry
                    (user_id, provider_type, company_name, company_description, contact_person, email, phone, coverage_scope, states_served,
                     status, primary_category, service_categories, input_categories, bank_name, account_name, account_number,
                     dashboard_token, state_ids, lga_ids, business_registration_number, tax_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'state', ?, 'pending_review', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $providerType,
                $businessName,
                '',
                $fullName,
                (string) $email,
                $phone,
                '',
                $providerIntent,
                $providerIntent,
                '',
                '',
                '',
                '',
                $token,
                '',
                '',
                '',
                '',
            ]);
            if ($pdo->inTransaction()) {
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                redirect_to('dashboard.php');
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getMessage() === 'email_exists') {
                $error = 'An account already exists with this email. Please sign in first, then complete or request provider access from your provider workspace.';
            } else {
                error_log('Provider registration error: ' . $e->getMessage());
                $error = 'Provider registration could not be completed right now.';
            }
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as Provider - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#08753a;--mint:#eef8ef;--gold:#d89b10;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#fbfcf8;--soft:#f7fbf4;--shadow:0 18px 48px rgba(16,24,40,.08)}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.top{height:82px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 42px;position:sticky;top:0;z-index:10}.brand{display:flex;gap:12px;align-items:center}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.7rem;color:var(--green)}.brand small{display:block;font-weight:800;color:#344054}.nav{display:flex;gap:28px;font-weight:900}.nav a.active{color:var(--green);border-bottom:3px solid var(--green);padding-bottom:27px}.btn{display:inline-flex;gap:10px;align-items:center;justify-content:center;border:1px solid var(--green);border-radius:8px;background:var(--green);color:#fff;font-weight:950;padding:12px 18px;cursor:pointer}.btn.light{background:#fff;color:var(--green)}.hero{min-height:255px;background:linear-gradient(90deg,rgba(255,255,255,.98) 0%,rgba(255,255,255,.85) 52%,rgba(255,255,255,.3) 100%),url("../assets/public/provider-commerce-hero.png") center/cover;padding:42px 60px;display:flex;align-items:center}.hero-copy{max-width:740px}.hero h1{font-size:clamp(2.15rem,4vw,4rem);line-height:1.05;color:var(--green);margin:0 0 12px}.hero p{font-size:1.1rem;line-height:1.55;color:#344054;font-weight:750;margin:0}.wrap{padding:0 36px 40px}.entry-shell{display:grid;grid-template-columns:minmax(0,1fr) 360px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);margin-top:-34px;overflow:hidden;position:relative;z-index:2}.entry-form{padding:28px 34px}.entry-form h2,.entry-guide h2{margin:0;color:var(--green)}.entry-form p{color:var(--muted);font-weight:750;line-height:1.5;margin:8px 0 18px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.wide{grid-column:1/-1}label{display:block;font-weight:850;font-size:.94rem;color:#1f2937}input,select{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px;margin-top:7px;font:inherit;background:#fff}input:focus,select:focus{outline:3px solid #dff3e5;border-color:#7ccf94}.pass{position:relative}.pass button{position:absolute;right:8px;top:34px;border:0;background:#eef8ef;color:var(--green);padding:8px;border-radius:7px;font-weight:900;cursor:pointer}.alert{padding:12px;border-radius:10px;margin:12px 0;font-weight:850}.err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}.ok{background:#e8f6ec;color:var(--green);border:1px solid #b9e3c3}.submit-row{display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:16px}.submit-row span{color:#475467;font-weight:750}.entry-guide{border-left:1px solid var(--line);background:var(--soft);padding:24px}.guide-card{position:sticky;top:106px}.guide-intro{color:var(--muted);font-weight:750;line-height:1.5;margin:8px 0 16px}.mini-card{display:flex;gap:12px;align-items:flex-start;background:#fff;border:1px solid var(--line);border-radius:10px;padding:14px;margin-bottom:12px}.mini-card i{color:var(--green);font-size:1.25rem;margin-top:2px}.mini-card strong{display:block;color:#1f2937}.mini-card span{color:#475467;font-weight:750;line-height:1.45}.after-list{margin:0;padding:0;list-style:none;display:grid;gap:10px}.after-list li{display:flex;gap:10px;color:#475467;font-weight:750}.after-list i{color:var(--green);margin-top:3px}.trust{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;margin-top:18px}.trust-item{display:flex;gap:12px;align-items:center}.trust-item i{font-size:1.55rem;color:var(--green)}.trust-item strong{display:block}.trust-item span{color:#475467;font-weight:750}.footer{background:#052d15;color:#dff6e4;padding:28px 42px;display:grid;grid-template-columns:2fr repeat(3,1fr);gap:24px}.footer a{display:block;color:#dff6e4;margin:8px 0}
    @media(max-width:1100px){.entry-shell,.footer{grid-template-columns:1fr}.entry-guide{border-left:0;border-top:1px solid var(--line)}.guide-card{position:static}.trust{grid-template-columns:1fr}}@media(max-width:720px){.top{height:auto;padding:14px;align-items:flex-start;flex-direction:column}.nav{gap:14px;flex-wrap:wrap}.hero{padding:30px 18px}.wrap{padding:0 14px 28px}.entry-form{padding:22px}.form-grid{grid-template-columns:1fr}.footer{padding:24px 18px}}
  </style>
</head>
<body>
<header class="top">
  <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>National Coconut Development & Propagation Initiative</small></span></a>
  <nav class="nav"><a class="active" href="index.php">Registry</a><a href="../market/index.php">Marketplace</a><a href="../academy/index.php?screen=catalog">Academy</a><a href="../verify-certificate.php">Certificate Verify</a><a href="../support/index.php?category=provider">Support</a></nav>
  <a class="btn light" href="login.php"><i class="fas fa-user-check"></i> Sign in</a>
</header>
<section class="hero">
  <div class="hero-copy"><h1>Start your provider profile</h1><p>Create a secure NATCODEV provider workspace. Add coverage, documents, products, settlement details, and accreditation evidence after sign-in.</p></div>
</section>
<main class="wrap">
  <section class="entry-shell">
    <section class="entry-form">
      <h2>Provider starter registration</h2>
      <p>Only the essentials are required here. Your provider workspace will guide the rest based on your role and approval status.</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($socialNotice): ?><div class="alert ok"><?= e($socialNotice) ?></div><?php endif; ?>
    <form id="registration" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="form-grid">
        <label>Business / Organization Name *<input name="company_name" required placeholder="e.g., Green Palm Supplies"></label>
        <label>Provider Pathway *<select name="provider_intent" required><option value="">Select one</option><?php foreach ($providerIntents as $intent => $description): ?><option value="<?= e($intent) ?>"><?= e($intent) ?></option><?php endforeach; ?></select></label>
        <label>Contact Person *<input name="contact_person" required placeholder="Full name"></label>
        <label>Phone Number *<input name="phone" required placeholder="+234..."></label>
        <label>Email Address *<input type="email" name="email" required placeholder="name@example.com"></label>
        <label class="pass">Password *<input id="provider-password" type="password" name="password" minlength="6" required placeholder="Create password"><button type="button" data-toggle-password="provider-password">Show</button></label>
      </div>
      <div class="submit-row"><button class="btn" type="submit">Create Provider Workspace <i class="fas fa-arrow-right"></i></button><span>Already registered? <a style="color:var(--green);font-weight:950" href="login.php">Sign in</a></span></div>
    </form>
    </section>
    <aside class="entry-guide">
      <div class="guide-card">
        <h2>Complete after login</h2>
        <p class="guide-intro">Detailed actions remain inside the authenticated workspace and review flow.</p>
        <div class="mini-card"><i class="fas fa-shield-halved"></i><span><strong>Secure access</strong>New provider accounts get a dedicated workspace. Existing emails must sign in before a provider profile is linked.</span></div>
        <ul class="after-list">
          <li><i class="fas fa-building"></i><span>Business profile, CAC, tax, and description</span></li>
          <li><i class="fas fa-location-dot"></i><span>Coverage states and LGAs</span></li>
          <li><i class="fas fa-box"></i><span>Products, services, marketplace listings, and Academy offers</span></li>
          <li><i class="fas fa-wallet"></i><span>Settlement details and wallet setup</span></li>
          <li><i class="fas fa-award"></i><span>Accreditation documents and review status</span></li>
        </ul>
      </div>
    </aside>
  </section>
  <section class="trust"><div class="trust-item"><i class="fas fa-users"></i><span><strong>Provider workspace</strong>Built for suppliers, service teams, sellers, and partners.</span></div><div class="trust-item"><i class="fas fa-lock"></i><span><strong>Secure onboarding</strong>Deeper records stay behind login.</span></div><div class="trust-item"><i class="fas fa-leaf"></i><span><strong>NATCODEV aligned</strong>Supports coconut value-chain growth.</span></div></section>
</main>
<footer class="footer"><div><h2>NATCODEV Provider Registry</h2><p>Register, verify, sell, train, and serve coconut communities through one trusted ecosystem.</p></div><div><h3>Provider</h3><a href="login.php">Provider Login</a><a href="dashboard.php">Dashboard</a></div><div><h3>Marketplace</h3><a href="../market/index.php">Browse</a><a href="../market/seller-central.php">Seller Central</a></div><div><h3>Academy</h3><a href="../academy/index.php?screen=catalog">Courses</a><a href="../verify-certificate.php">Verify Certificate</a></div></footer>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.dataset.togglePassword);var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});
</script>
</body>
</html>
