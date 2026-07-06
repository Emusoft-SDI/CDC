<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-otp.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin_otp_hash'])) {
    redirect_to('login.php');
}

$error = '';
$restartRequired = false;
$resendAvailable = true;
$notice = 'OTP sent to ' . e((string) ($_SESSION['admin_otp_email'] ?? 'the configured admin email')) . '.';
$debugCode = !app_is_production() ? (string) ($_SESSION['admin_otp_debug_code'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } elseif (isset($_POST['resend_otp'])) {
        $graceExpires = (int) ($_SESSION['admin_otp_grace_expires'] ?? 0);
        if ($graceExpires > 0 && $graceExpires < time()) {
            unset($_SESSION['admin_otp_hash'], $_SESSION['admin_otp_expires'], $_SESSION['admin_otp_grace_expires'], $_SESSION['admin_otp_email'], $_SESSION['admin_otp_next'], $_SESSION['admin_otp_debug_code']);
            $error = 'Operator OTP session has expired. Please start admin login again.';
            $restartRequired = true;
            $resendAvailable = false;
        } elseif (!app_check_rate_limit('admin_otp_resend', 3, 60)) {
            $error = 'Too many resend requests. Please wait 60 seconds.';
        } else {
            $next = (string) ($_SESSION['admin_otp_next'] ?? 'index.php');
            $result = admin_operator_otp_begin($next);
            if (!empty($result['ok'])) {
                $notice = 'A new operator OTP has been sent. Use the latest code; older codes are no longer valid.';
                $debugCode = !app_is_production() ? (string) ($_SESSION['admin_otp_debug_code'] ?? '') : '';
            } else {
                $error = (string) ($result['message'] ?? 'Unable to resend operator OTP.');
            }
        }
    } elseif (!app_check_rate_limit('admin_otp_verify', 10, 600)) {
        $error = 'Too many OTP attempts. Please try again in 10 minutes.';
    } else {
        $digits = $_POST['otp_digits'] ?? [];
        $otp = is_array($digits)
            ? implode('', array_map(static fn($digit): string => preg_replace('/[^0-9]/', '', (string) $digit), $digits))
            : (string) ($_POST['otp'] ?? '');
        $result = admin_operator_otp_verify($otp);
        if ($result['ok']) {
            redirect_to((string) ($result['next'] ?? 'index.php'));
        }
        $error = (string) $result['message'];
        $restartRequired = !empty($result['restart']);
        $resendAvailable = !$restartRequired;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Operator OTP - NATCODEV</title>
  <style>
    :root{--green:#1f8a55;--deep:#166b41;--ink:#1f2937;--muted:#667085;--line:#d8e2dc;--red:#a32020}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,rgba(31,138,85,.12),rgba(26,82,118,.08)),#f5f8f6;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);padding:24px}.card{width:min(440px,94vw);background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;padding:32px;box-shadow:0 18px 44px rgba(16,24,40,.12)}.brand{display:flex;gap:12px;align-items:center;color:var(--deep);font-weight:950;margin-bottom:18px}.brand img{width:56px;height:56px;border-radius:50%;object-fit:contain;border:1px solid var(--line)}h1{margin:0 0 8px;color:var(--deep)}p{color:var(--muted);line-height:1.5}.ok{background:#eef7f1;color:var(--deep);border:1px solid #d8e2dc;padding:10px;border-radius:7px}.err{background:#fff3f3;color:var(--red);border:1px solid #ffd2d2;padding:10px;border-radius:7px}.otp-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin:18px 0;width:100%;max-width:100%;overflow:hidden}.otp-grid input{width:100%;min-width:0;height:48px;text-align:center;font-size:1.25rem;font-weight:900;padding:8px 2px;border:1px solid var(--line);border-radius:7px;line-height:1}button{width:100%;border:0;border-radius:7px;background:var(--green);color:#fff;padding:13px;font-weight:950;font-size:1rem;cursor:pointer}.links{margin-top:16px;display:flex;justify-content:space-between;gap:12px}.resend-form{margin-top:12px}.resend-form button{background:#eef7f1;color:var(--deep);box-shadow:none;border:1px solid var(--line)}.links a,.restart-link{color:var(--deep);font-weight:900;text-decoration:none}@media(max-width:420px){.otp-grid{gap:5px}.otp-grid input{height:44px;font-size:1.1rem}.card{padding:24px 18px}}
  </style>
</head>
<body>
  <main class="card">
    <div class="brand"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV<br><small>Operator Verification</small></span></div>
    <h1>Enter operator OTP</h1>
    <p>For platform operator access, confirm the 6-digit code sent after the admin password was accepted.</p>
    <?php if ($notice): ?><p class="ok"><?= $notice ?></p><?php endif; ?>
    <?php if ($debugCode): ?><p class="err">Local test OTP: <?= e($debugCode) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
    <?php if ($restartRequired): ?><p><a class="restart-link" href="login.php">Start admin login again</a></p><?php endif; ?>
    <form method="post" <?= $restartRequired ? 'hidden' : '' ?>>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="otp-grid" aria-label="Operator OTP">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input name="otp_digits[]" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" required>
        <?php endfor; ?>
      </div>
      <button type="submit">Verify and Open Workspace</button>
    </form>
    <?php if ($resendAvailable): ?><form method="post" class="resend-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="resend_otp" value="1"><button type="submit">Resend operator OTP</button></form><?php endif; ?>
    <div class="links"><a href="login.php">Start again</a><a href="../support/index.php">Support</a></div>
  </main>
  <script>
    (() => {
      const fields = Array.from(document.querySelectorAll('.otp-grid input'));
      const form = fields[0]?.closest('form');
      let submitting = false;

      const code = () => fields.map((field) => field.value).join('');
      const submitIfComplete = () => {
        if (!form || submitting || code().length !== fields.length) return;
        submitting = true;
        if (form.requestSubmit) {
          form.requestSubmit();
        } else {
          form.submit();
        }
      };
      const fillFrom = (startIndex, value) => {
        const digits = String(value).replace(/\D/g, '').slice(0, fields.length - startIndex);
        if (!digits) return false;
        digits.split('').forEach((digit, offset) => {
          if (fields[startIndex + offset]) fields[startIndex + offset].value = digit;
        });
        const nextIndex = Math.min(startIndex + digits.length, fields.length - 1);
        fields[nextIndex]?.focus();
        submitIfComplete();
        return true;
      };

      fields.forEach((input, index) => {
        input.addEventListener('paste', (event) => {
          event.preventDefault();
          fillFrom(index, event.clipboardData?.getData('text') || '');
        });
        input.addEventListener('input', () => {
          const raw = input.value;
          input.value = '';
          fillFrom(index, raw);
        });
        input.addEventListener('keydown', (event) => {
          if (event.key === 'Backspace' && !input.value && fields[index - 1]) fields[index - 1].focus();
        });
      });
    })();
</script>
</body>
</html>