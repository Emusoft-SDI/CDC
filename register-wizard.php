<?php
declare(strict_types=1);

/**
 * The registration wizard, shared by every board.
 *
 * Reached in one of three ways:
 *   - ?board=grower                     the minimal sign-up / resume screen
 *   - ?board=grower&resume=1            after verify-otp.php, straight into the steps
 *   - ?board=grower&step=3              a specific step
 *
 * The steps are only reachable by a signed-in user whose email OTP has been verified:
 * session user_id is only ever set by verify-otp.php (or a verified login), and the
 * draft is looked up by that user id. Nothing is activated until the last step is
 * submitted, which also stamps email_verified_at.
 */

require_once __DIR__ . '/config.php';

// Must happen before any output: csrf_token() and the OTP flow both need the session,
// and starting it late emits a warning that corrupts the rendered token value.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
registration_wizard_ensure_schema($pdo);

$boardSlug = strtolower((string) preg_replace('/[^a-z0-9_]/i', '', (string) ($_GET['board'] ?? $_POST['board'] ?? 'grower')));
$board = registration_wizard_board($boardSlug);
if (!$board) {
    $boardSlug = 'grower';
    $board = registration_wizard_board('grower');
}
$boardSlug = (string) ($board['slug'] ?? $boardSlug);
$steps = registration_wizard_steps($board);
$stepCount = count($steps);

$errors = [];
$messages = [];
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$mode = 'entry';
$draft = null;
$payload = [];
$currentIndex = 0;
$done = false;

/* ------------------------------------------------------------------- actions -- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        try {
            if ($action === 'start') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = (string) filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $password = (string) ($_POST['password'] ?? '');

                $digits = (string) preg_replace('/\D+/', '', $phone);
                if ($name === '') {
                    $errors[] = 'Enter your full name.';
                }
                if ($email === '') {
                    $errors[] = 'Enter a valid email address.';
                }
                if (strlen($digits) < 10 || strlen($digits) > 15) {
                    $errors[] = 'Enter a valid phone number.';
                }
                if (strlen($password) < 8) {
                    $errors[] = 'Choose a password of at least 8 characters.';
                }
                if (!app_check_rate_limit('registration_start', 6, 900)) {
                    $errors[] = 'Too many registration attempts. Please try again in a few minutes.';
                }

                if (!$errors) {
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch() ?: null;

                    if ($user && (string) $user['account_status'] === 'active' && !empty($user['email_verified_at'])) {
                        $errors[] = 'That email is already registered. Please sign in instead, or use a different email.';
                    } elseif (($phoneOwner = registration_wizard_phone_owner($pdo, $phone, $user ? (int) $user['id'] : 0)) !== null) {
                        // Phone is not unique in the users table, so without this two
                        // accounts could share a number and SMS about either one would go
                        // to a single handset.
                        $errors[] = registration_wizard_phone_conflict_message($phoneOwner);
                    } else {
                        if ($user) {
                            // Unverified account from an earlier attempt: reuse it rather
                            // than creating a duplicate the unique index would reject.
                            $userId = (int) $user['id'];
                            $pdo->prepare('UPDATE users SET name = ?, phone = ?, password = ?, role = ?, platform_role = ? WHERE id = ?')
                                ->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), (string) $board['role'], (string) $board['platform_role'], $userId]);
                        } else {
                            $pdo->prepare('INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, (string) $board['role'], (string) $board['platform_role'], 'needs_confirmation']);
                            $userId = (int) $pdo->lastInsertId();
                        }

                        $existing = registration_wizard_find_by_email($pdo, $boardSlug, $email);
                        if ($existing) {
                            $pdo->prepare('UPDATE registration_drafts SET user_id = ?, phone = ?, updated_at = NOW() WHERE id = ?')
                                ->execute([$userId, $phone, (int) $existing['id']]);
                        } else {
                            // Seed the first step with what the entry form already
                            // collected, plus the application type when the entry page
                            // passed one (apply.php?type=cooperative, for example).
                            $seed = ['name' => $name, 'phone' => $phone];
                            $memberType = strtolower(trim((string) ($_POST['member_type'] ?? '')));
                            if (in_array($memberType, ['individual', 'corporate', 'cooperative'], true)) {
                                $seed['member_type'] = $memberType;
                            }
                            $pdo->prepare('INSERT INTO registration_drafts (board, user_id, email, phone, payload, current_step, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())')
                                ->execute([$boardSlug, $userId, $email, $phone, json_encode([(string) $steps[0]['key'] => $seed])]);
                        }

                        $otp = registration_wizard_otp_begin($pdo, $boardSlug, ['id' => $userId, 'email' => $email]);
                        if (!empty($otp['ok'])) {
                            redirect_to('verify-otp.php');
                        }
                        $errors[] = (string) $otp['message'];
                    }
                }
            } elseif ($action === 'resume') {
                $email = (string) filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
                if ($email === '') {
                    $errors[] = 'Enter the email you registered with.';
                } elseif (!app_check_rate_limit('registration_resume', 8, 900)) {
                    $errors[] = 'Too many attempts. Please try again in a few minutes.';
                } else {
                    $existing = registration_wizard_find_by_email($pdo, $boardSlug, $email);
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch() ?: null;

                    if (!$user) {
                        $errors[] = 'We could not find a registration for that email. You can start a new one below.';
                    } elseif ((string) $user['account_status'] === 'active' && !empty($user['email_verified_at'])) {
                        $errors[] = 'That registration is already complete. Please sign in.';
                    } elseif (!$existing) {
                        // Registered before this wizard existed, or a draft was removed:
                        // recreate the draft so they can still continue.
                        $pdo->prepare('INSERT INTO registration_drafts (board, user_id, email, phone, payload, current_step, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())')
                            ->execute([$boardSlug, (int) $user['id'], $email, (string) ($user['phone'] ?? ''), json_encode([])]);
                        $otp = registration_wizard_otp_begin($pdo, $boardSlug, ['id' => (int) $user['id'], 'email' => $email]);
                        if (!empty($otp['ok'])) {
                            redirect_to('verify-otp.php');
                        }
                        $errors[] = (string) $otp['message'];
                    } else {
                        $otp = registration_wizard_otp_begin($pdo, $boardSlug, ['id' => (int) $user['id'], 'email' => $email]);
                        if (!empty($otp['ok'])) {
                            redirect_to('verify-otp.php');
                        }
                        $errors[] = (string) $otp['message'];
                    }
                }
            } elseif ($action === 'back') {
                $index = max(0, (int) ($_POST['step'] ?? 0) - 1);
                redirect_to('register-wizard.php?board=' . rawurlencode($boardSlug) . '&step=' . $index);
            }
        } catch (Throwable $e) {
            error_log('Registration wizard action failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong on our side. Please try again.';
        }
    }
}

/* ---------------------------------------------------------------- view state -- */

if ($sessionUserId > 0) {
    $draft = registration_wizard_find_by_user($pdo, $boardSlug, $sessionUserId);
    if ($draft) {
        $payload = registration_wizard_payload($draft);
        if ((string) $draft['status'] === 'submitted') {
            $mode = 'done';
            $done = true;
        } else {
            // A draft belonging to the signed-in user is only reachable with a verified
            // session, so record that the gate was passed.
            if (empty($draft['otp_verified'])) {
                $pdo->prepare('UPDATE registration_drafts SET otp_verified = 1, updated_at = NOW() WHERE id = ?')->execute([(int) $draft['id']]);
                $draft['otp_verified'] = 1;
            }
            $mode = 'wizard';
            if (isset($_GET['step'])) {
                $currentIndex = max(0, min($stepCount - 1, (int) $_GET['step']));
            } elseif (isset($_GET['resume']) || isset($_POST['resume'])) {
                $currentIndex = registration_wizard_first_incomplete($board, $payload);
            } else {
                $currentIndex = max(0, min($stepCount - 1, (int) $draft['current_step']));
            }
        }
    } else {
        $stmt = $pdo->prepare('SELECT email, account_status, email_verified_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$sessionUserId]);
        $me = $stmt->fetch() ?: null;
        if ($me && (string) $me['account_status'] === 'active' && !empty($me['email_verified_at'])) {
            $mode = 'already';
        }
    }
}

/* Step submission: validate, store, then either advance or finalise. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'step' && $draft && !$errors) {
    $index = max(0, min($stepCount - 1, (int) ($_POST['step'] ?? 0)));
    $step = $steps[$index];
    $existing = $payload[$step['key']] ?? [];
    $validated = registration_wizard_validate_step($step, $_POST, array_merge(registration_wizard_all_values($payload), $existing));

    if ($validated['errors']) {
        $errors = array_merge($errors, $validated['errors']);
        $currentIndex = $index;
        $mode = 'wizard';
    } else {
        // The phone can be edited on a later step, so re-apply the one-number-per-account
        // rule here as well as at sign-up.
        $changedPhone = trim((string) ($validated['values']['phone'] ?? ''));
        if ($changedPhone !== '') {
            $phoneOwner = registration_wizard_phone_owner($pdo, $changedPhone, (int) $draft['user_id']);
            if ($phoneOwner !== null) {
                $errors[] = registration_wizard_phone_conflict_message($phoneOwner);
                $currentIndex = $index;
                $mode = 'wizard';
            }
        }
    }

    if (!$errors) {
        $payload[$step['key']] = array_merge($existing, $validated['values']);
        $isLast = $index >= $stepCount - 1;
        registration_wizard_save_progress($pdo, (int) $draft['id'], $payload, $isLast ? $index : $index + 1);

        if ($isLast) {
            $result = registration_wizard_finalize($pdo, $boardSlug, $draft);
            if (!empty($result['ok'])) {
                redirect_to('register-wizard.php?board=' . rawurlencode($boardSlug) . '&done=1');
            }
            $errors[] = (string) $result['message'];
            $currentIndex = $index;
            $mode = 'wizard';
        } else {
            redirect_to('register-wizard.php?board=' . rawurlencode($boardSlug) . '&step=' . ($index + 1));
        }
    }
}

if ($mode === 'wizard' && $draft) {
    $draft = registration_wizard_find($pdo, (int) $draft['id']) ?? $draft;
    $payload = registration_wizard_payload($draft);
}
if (isset($_GET['done']) && $draft) {
    $mode = 'done';
    $done = true;
}

$pageTitle = (string) $board['label'];
$prefillEmail = '';
if ($mode === 'entry' && $sessionUserId > 0) {
    try {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$sessionUserId]);
        $prefillEmail = (string) ($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | NATCODEV</title>
<meta name="robots" content="noindex">
<style>
  :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--max:1040px}
  *{box-sizing:border-box}
  body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:linear-gradient(135deg,#eef8ee,#fff 45%,#f6faf3)}
  a{color:var(--green2)}
  .rw-top{background:var(--green);color:#fff}
  .rw-top__in{max-width:var(--max);margin:0 auto;padding:11px 22px;display:flex;flex-wrap:wrap;gap:8px 18px;justify-content:space-between;font-size:.9rem}
  .rw-top a{color:#fff}
  .rw-head{background:#fff;border-bottom:1px solid var(--line)}
  .rw-head__in{max-width:var(--max);margin:0 auto;padding:14px 22px;display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;justify-content:space-between}
  .rw-brand{display:flex;gap:12px;align-items:center;text-decoration:none}
  .rw-brand img{width:50px;height:50px;border-radius:50%;object-fit:contain}
  .rw-brand strong{display:block;color:var(--green);font-size:1.3rem;line-height:1}
  .rw-brand span span{display:block;font-size:.62rem;font-weight:900;color:#111}
  .rw-wrap{max-width:var(--max);margin:0 auto;padding:30px 22px 10px}
  .rw-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,40,.07);padding:28px;margin-bottom:20px}
  .rw-card h1{margin:0 0 6px;color:var(--green);font-size:clamp(1.5rem,3vw,2rem);line-height:1.15}
  .rw-card h2{margin:0 0 6px;color:var(--green);font-size:1.2rem}
  .rw-card p.lead{margin:0 0 20px;color:#344054;line-height:1.6}
  .rw-alert{border-radius:10px;padding:13px 15px;margin:0 0 16px;font-weight:700;line-height:1.5}
  .rw-alert--err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}
  .rw-alert--ok{background:#e8f6ec;color:#075c34;border:1px solid #bfe7c9}
  .rw-alert ul{margin:6px 0 0;padding-left:20px}
  .rw-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .rw-field{display:flex;flex-direction:column;gap:6px}
  .rw-wide{grid-column:1/-1}
  .rw-field label{font-weight:800;color:#1f2937;font-size:.93rem}
  .rw-req{color:#b42318}
  .rw-field input,.rw-field select,.rw-field textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px;font:inherit;background:#fff}
  .rw-field input:focus,.rw-field select:focus,.rw-field textarea:focus{outline:3px solid #dff3e5;border-color:#7ccf94}
  .rw-check{display:flex;gap:10px;align-items:flex-start;font-weight:600;line-height:1.5}
  .rw-check input{width:auto;margin-top:3px}
  .rw-progress{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 24px;padding:0}
  .rw-progress li{display:flex;gap:8px;align-items:center;border:1px solid var(--line);border-radius:9px;padding:8px 12px;background:#fff;font-size:.85rem;font-weight:800;color:var(--muted)}
  .rw-progress li.is-current{border-color:#7ccf94;background:var(--soft);color:var(--green)}
  .rw-progress li.is-done .rw-progress__n{background:var(--leaf);color:#fff}
  .rw-progress__n{width:24px;height:24px;border-radius:50%;background:#e6ebe6;color:#344054;display:grid;place-items:center;font-size:.78rem}
  .rw-progress li.is-current .rw-progress__n{background:var(--green);color:#fff}
  .rw-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:24px}
  .rw-btn{border:1px solid var(--green);border-radius:9px;background:var(--green);color:#fff;font-weight:800;padding:12px 20px;cursor:pointer;text-decoration:none;display:inline-flex;gap:8px;align-items:center;font:inherit;font-weight:800}
  .rw-btn:hover{background:var(--green2);border-color:var(--green2)}
  .rw-btn--ghost{background:#fff;color:var(--green)}
  .rw-btn--ghost:hover{background:var(--soft);color:var(--green)}
  .rw-hint{color:var(--muted);font-size:.88rem;line-height:1.5}
  .rw-split{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:26px}
  .rw-aside{background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:20px}
  .rw-aside h3{margin:0 0 10px;color:var(--green);font-size:1rem}
  .rw-aside ol,.rw-aside ul{margin:0;padding-left:20px;line-height:1.65;color:#344054;font-size:.92rem}
  .rw-review{display:grid;gap:14px;margin:0 0 20px}
  .rw-review__group{border:1px solid var(--line);border-radius:10px;padding:14px 16px}
  .rw-review__group h3{margin:0 0 10px;color:var(--green);font-size:.98rem;text-transform:uppercase;letter-spacing:.05em}
  .rw-review__row{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:12px;padding:5px 0;border-bottom:1px dashed #e8efe8}
  .rw-review__row:last-child{border-bottom:0}
  .rw-review__row dt{color:var(--muted);font-size:.88rem;margin:0}
  .rw-review__row dd{margin:0;font-weight:700;font-size:.92rem;word-break:break-word}
  .rw-review__edit{font-size:.85rem;font-weight:800}
  .rw-note{background:var(--soft);border-left:4px solid var(--leaf);border-radius:8px;padding:12px 14px;color:#344054;font-size:.9rem;line-height:1.55;margin:0 0 18px}
  .rw-done{text-align:center;padding:14px 0}
  .rw-done__tick{width:64px;height:64px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center;font-size:1.8rem;margin:0 auto 14px}
  a:focus-visible,button:focus-visible{outline:2px solid var(--green2);outline-offset:3px;border-radius:3px}
  @media (max-width:820px){.rw-grid,.rw-split{grid-template-columns:1fr}.rw-review__row{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="rw-top">
    <div class="rw-top__in">
      <span><?= e($pageTitle) ?></span>
      <span><a href="index.php">NATCODEV home</a> &nbsp;·&nbsp; <a href="login.php">Sign in</a> &nbsp;·&nbsp; <a href="support/index.php">Need help?</a></span>
    </div>
  </div>
  <header class="rw-head">
    <div class="rw-head__in">
      <a class="rw-brand" href="index.php">
        <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
        <span><strong>NATCODEV</strong><span>NATIONAL COCONUT DEVELOPMENT &amp; PROPAGATION INITIATIVE</span></span>
      </a>
      <span class="rw-hint"><?= e((string) $board['short']) ?> registration</span>
    </div>
  </header>

  <main class="rw-wrap">
<?php if ($errors): ?>
    <div class="rw-alert rw-alert--err" role="alert">
      <strong>Please check the following:</strong>
      <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if ($messages): ?>
    <div class="rw-alert rw-alert--ok" role="status">
      <ul><?php foreach ($messages as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if ($mode === 'done'): ?>
    <section class="rw-card rw-done">
      <div class="rw-done__tick" aria-hidden="true">&#10003;</div>
      <h1>Registration complete</h1>
      <p class="lead">Your <?= e(strtolower((string) $board['short'])) ?> registration has been submitted<?= $boardSlug === 'grower' ? ' and is now awaiting review' : '' ?>.</p>
      <div class="rw-actions" style="justify-content:center">
        <a class="rw-btn" href="<?= e((string) $board['dashboard']) ?>">Continue to my dashboard</a>
        <a class="rw-btn rw-btn--ghost" href="index.php">Back to home</a>
      </div>
    </section>

<?php elseif ($mode === 'already'): ?>
    <section class="rw-card">
      <h1>You are already registered</h1>
      <p class="lead">This account is verified, so there is nothing left to complete here.</p>
      <div class="rw-actions">
        <a class="rw-btn" href="<?= e((string) $board['dashboard']) ?>">Go to my dashboard</a>
        <a class="rw-btn rw-btn--ghost" href="index.php">Back to home</a>
      </div>
    </section>

<?php elseif ($mode === 'entry'): ?>
    <div class="rw-split">
      <section class="rw-card">
        <h1>Start your <?= e(strtolower((string) $board['short'])) ?> registration</h1>
        <p class="lead">Four details to begin. You will verify your email, then answer the rest step by step &mdash; and you can stop at any point and come back.</p>

        <form method="post" autocomplete="on">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="board" value="<?= e($boardSlug) ?>">
          <input type="hidden" name="action" value="start">
          <div class="rw-grid">
            <div class="rw-field"><label for="rw-name">Full Name <span class="rw-req">*</span></label><input id="rw-name" name="name" required autocomplete="name"></div>
            <div class="rw-field"><label for="rw-phone">Phone Number <span class="rw-req">*</span></label><input id="rw-phone" name="phone" type="tel" required autocomplete="tel" placeholder="08012345678"></div>
            <div class="rw-field rw-wide"><label for="rw-email">Email Address <span class="rw-req">*</span></label><input id="rw-email" name="email" type="email" required autocomplete="email" value="<?= e($prefillEmail) ?>"></div>
            <div class="rw-field rw-wide"><label for="rw-password">Create a Password <span class="rw-req">*</span></label><input id="rw-password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></div>
          </div>
          <div class="rw-actions">
            <button class="rw-btn" type="submit">Continue &amp; verify email</button>
            <span class="rw-hint">We will email you a 6-digit code to confirm it is really you.</span>
          </div>
        </form>

        <hr style="border:0;border-top:1px solid var(--line);margin:26px 0">

        <h2>Already started?</h2>
        <p class="lead" style="margin-bottom:14px">Enter the email you used and we will send a fresh code, then take you back to the step you stopped at.</p>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="board" value="<?= e($boardSlug) ?>">
          <input type="hidden" name="action" value="resume">
          <div class="rw-grid">
            <div class="rw-field rw-wide"><label for="rw-resume-email">Email you registered with</label><input id="rw-resume-email" name="email" type="email" required value="<?= e($prefillEmail) ?>"></div>
          </div>
          <div class="rw-actions">
            <button class="rw-btn rw-btn--ghost" type="submit">Send me a new code</button>
          </div>
        </form>
      </section>

      <aside class="rw-aside">
        <h3>What happens next</h3>
        <ol>
          <li>Confirm your email with the 6-digit code.</li>
          <li>Answer the remaining steps &mdash; each one saves as you go.</li>
          <li>Submit for review.</li>
        </ol>
        <h3 style="margin-top:18px">Stopped part-way?</h3>
        <p class="rw-hint">Nothing is lost. Come back to this page, enter your email, and you will receive a new code and resume where you left off. Your code lasts 10 minutes.</p>
        <h3 style="margin-top:18px">No code arriving?</h3>
        <p class="rw-hint">Check your spam folder. You can request a new code as often as you need, or <a href="support/index.php?category=account">contact support</a>.</p>
      </aside>
    </div>

<?php elseif ($mode === 'wizard' && $draft): ?>
    <?php $step = $steps[$currentIndex]; $isLast = $currentIndex >= $stepCount - 1; ?>
    <section class="rw-card">
      <h1><?= e((string) $step['title']) ?></h1>
      <p class="lead"><?= e((string) ($step['intro'] ?? '')) ?></p>
      <?= registration_wizard_progress_html($board, $currentIndex, $payload) ?>

      <p class="rw-note"><strong>Saved as you go.</strong> Every step you complete is stored against <strong><?= e((string) $draft['email']) ?></strong>. If you stop now, return to this page, enter that email and we will send a new code so you can continue from here.</p>

      <?php if (!empty($step['review'])): ?>
        <?= registration_wizard_review_html($board, $payload, $boardSlug) ?>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="board" value="<?= e($boardSlug) ?>">
        <input type="hidden" name="step" value="<?= (int) $currentIndex ?>">
        <input type="hidden" name="action" value="step">
        <?= registration_wizard_fields_html($step, $payload[$step['key']] ?? [], ['pdo' => $pdo, 'id_prefix' => 'rw']) ?>
        <div class="rw-actions">
          <?php if ($currentIndex > 0): ?>
            <button class="rw-btn rw-btn--ghost" type="submit" name="action" value="back" formnovalidate>&larr; Back</button>
          <?php endif; ?>
          <button class="rw-btn" type="submit"><?= $isLast ? 'Submit registration' : 'Save &amp; continue' ?></button>
          <span class="rw-hint">Step <?= (int) $currentIndex + 1 ?> of <?= (int) $stepCount ?></span>
        </div>
      </form>
    </section>
    <script>
    (function () {
      var lga = document.querySelector('select[data-rw-lgas]');
      var state = document.querySelector('select[data-rw-states]');
      if (!state || !lga) { return; }
      var saved = lga.value;
      function load() {
        var option = state.options[state.selectedIndex];
        var stateId = option ? option.getAttribute('data-state-id') : '';
        if (!stateId) {
          lga.innerHTML = '<option value="">Select your LGA</option>';
          return;
        }
        lga.innerHTML = '<option value="">Loading LGAs…</option>';
        fetch('api/get-lgas.php?state_id=' + encodeURIComponent(stateId))
          .then(function (r) { return r.json(); })
          .then(function (payload) {
            var items = payload.items || [];
            var html = '<option value="">Select your LGA</option>';
            items.forEach(function (item) {
              var selected = saved && String(item.id) === String(saved) ? ' selected' : '';
              html += '<option value="' + item.id + '"' + selected + '>' + item.lga_name + '</option>';
            });
            lga.innerHTML = html;
          })
          .catch(function () { lga.innerHTML = '<option value="">Unable to load LGAs</option>'; });
      }
      state.addEventListener('change', function () { saved = ''; load(); });
      if (state.value) { load(); }
    }());

    // Conditional fields. Without this the follow-up questions (business details for a
    // corporate farm, the free-text "Other" answers) stayed hidden and were never
    // collected, even though the server expects them.
    (function () {
      var form = document.querySelector('.rw-card form');
      if (!form) { return; }
      var fields = Array.prototype.slice.call(form.querySelectorAll('.rw-field[data-show-if]'));
      if (!fields.length) { return; }

      function valueOf(name) {
        var el = form.elements[name];
        if (!el) { return ''; }
        if (el.length && !el.tagName) { // radio/checkbox collection
          for (var i = 0; i < el.length; i++) { if (el[i].checked) { return el[i].value; } }
          return '';
        }
        return el.value || '';
      }

      function apply() {
        fields.forEach(function (wrap) {
          var rules;
          try { rules = JSON.parse(wrap.getAttribute('data-show-if')); } catch (e) { return; }
          var show = true;
          for (var key in rules) {
            if (!Object.prototype.hasOwnProperty.call(rules, key)) { continue; }
            if (String(valueOf(key)) !== String(rules[key])) { show = false; break; }
          }
          wrap.style.display = show ? '' : 'none';
          // A hidden required field must not block submission.
          Array.prototype.slice.call(wrap.querySelectorAll('input, select, textarea')).forEach(function (el) {
            el.required = show && el.getAttribute('data-was-required') === '1';
          });
        });
      }

      // remember which inputs the server marked required
      fields.forEach(function (wrap) {
        Array.prototype.slice.call(wrap.querySelectorAll('input, select, textarea')).forEach(function (el) {
          if (el.required) { el.setAttribute('data-was-required', '1'); }
        });
      });

      form.addEventListener('change', apply);
      form.addEventListener('input', apply);
      apply();
    }());

    // Show the age as the date of birth is chosen.
    (function () {
      var inputs = document.querySelectorAll('input[type="date"][id]');
      Array.prototype.forEach.call(inputs, function (input) {
        var out = document.querySelector('[data-age-out="' + input.id + '"]');
        if (!out) { return; }
        var minAge = parseInt(out.getAttribute('data-age-min') || '16', 10);
        function update() {
          if (!input.value) { out.textContent = 'Your age is calculated from this.'; out.style.color = ''; return; }
          var dob = new Date(input.value + 'T00:00:00');
          if (isNaN(dob.getTime())) { out.textContent = 'Enter a valid date.'; out.style.color = '#b42318'; return; }
          var today = new Date();
          var age = today.getFullYear() - dob.getFullYear();
          var m = today.getMonth() - dob.getMonth();
          if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) { age--; }
          if (age < 0) { out.textContent = 'That date is in the future.'; out.style.color = '#b42318'; return; }
          out.textContent = 'Age: ' + age + (age < minAge ? ' — you must be at least ' + minAge : '');
          out.style.color = age < minAge ? '#b42318' : '#075c34';
        }
        input.addEventListener('change', update);
        input.addEventListener('input', update);
        update();
      });
    }());
    </script>
<?php endif; ?>
  </main>
<?= public_footer() ?>
</body>
</html>
