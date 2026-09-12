<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth-layout.php';

session_start();
$pdo = db();
if (!isset($_SESSION['otp_user_id'])) {
    redirect_to('otp-login.php');
}

function otp_safe_next_destination(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
        return '';
    }
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    return $next;
}

function otp_login_destination(PDO $pdo, int $userId): string
{
    $fields = ['role'];
    if (app_column_exists($pdo, 'users', 'platform_role')) {
        $fields[] = 'platform_role';
    }
    if (app_column_exists($pdo, 'users', 'is_super_admin')) {
        $fields[] = 'is_super_admin';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch() ?: [];
    $role = strtolower((string) ($user['role'] ?? ''));
    $platformRole = strtolower((string) ($user['platform_role'] ?? ''));

    if ((int) ($user['is_super_admin'] ?? 0) === 1 || $platformRole === 'super_admin') {
        return 'super-admin/index.php';
    }
    if (in_array($platformRole, ['national_coordinator', 'state_coordinator'], true)) {
        return 'coordination/';
    }
    if ($platformRole === 'admin' || $role === 'admin') {
        return 'admin/index.php';
    }
    if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'farm_hand'], true) || $role === 'field_agent') {
        return 'field-agent/index.php';
    }
    if (in_array($platformRole, ['provider', 'input_provider', 'service_provider', 'seller'], true) || in_array($role, ['provider', 'seller'], true)) {
        return 'provider/dashboard.php';
    }
    if ($platformRole === 'learner' || $role === 'learner') {
        return 'academy/dashboard.php';
    }

    return 'dashboard/index.php';
}

$error = '';
$success = '';
$deliveryMessage = (string) ($_SESSION['otp_delivery_message'] ?? '');
$debugCode = !app_is_production() ? (string) ($_SESSION['otp_debug_code'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } elseif (isset($_POST['resend_otp'])) {
        // Handle OTP resend
        if (!app_check_rate_limit('otp_resend', 3, 60)) {
            $error = 'Too many resend requests. Please wait 60 seconds.';
        } else {
            $userId = (int) ($_SESSION['otp_user_id'] ?? 0);
            if ($userId <= 0) {
                $error = 'Session expired. Please start over.';
            } else {
                $stmt = $pdo->prepare("SELECT email, phone, name FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRow = $stmt->fetch();

                if (!$userRow) {
                    $error = 'User not found.';
                } else {
                    $newOtpCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt = date('Y-m-d H:i:s', time() + 600);

                    $insertStmt = $pdo->prepare("INSERT INTO otp_sessions (user_id, otp_code, expires_at, used) VALUES (?, ?, ?, 0)");
                    $insertStmt->execute([$userId, $newOtpCode, $expiresAt]);

                    $_SESSION['otp_debug_code'] = $newOtpCode;
                    $_SESSION['otp_delivery_message'] = 'A new 6-digit code has been sent. It expires in 10 minutes.';

                    $deliveryMessage = $_SESSION['otp_delivery_message'];
                    $debugCode = !app_is_production() ? $newOtpCode : '';
                    $success = 'New OTP code sent successfully!';
                }
            }
        }
    } else {
        // Handle OTP verification
        if (!app_check_rate_limit('otp_verify', 10, 600)) {
            $error = 'Too many verification attempts. Please try again in 10 minutes.';
        } else {
            $digits = $_POST['otp_digits'] ?? [];
            $otp = is_array($digits)
                ? implode('', array_map(static fn($digit): string => preg_replace('/[^0-9]/', '', (string) $digit), $digits))
                : (string) ($_POST['otp'] ?? '');

            $stmt = $pdo->prepare("SELECT id FROM otp_sessions WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND used = 0");
            $stmt->execute([$_SESSION['otp_user_id'], $otp]);

            if ($sessionId = $stmt->fetchColumn()) {
                $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$sessionId]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $_SESSION['otp_user_id'];
                $nextDestination = otp_safe_next_destination((string) ($_SESSION['otp_next_destination'] ?? ''));
                $destination = $nextDestination !== '' ? $nextDestination : otp_login_destination($pdo, (int) $_SESSION['otp_user_id']);
                if ($destination === 'super-admin/index.php') {
                    $_SESSION['super_admin_authenticated'] = true;
                    $_SESSION['super_admin_user_id'] = (int) $_SESSION['otp_user_id'];
                    unset($_SESSION['super_admin_login_audited']);
                }
                unset($_SESSION['otp_user_id'], $_SESSION['otp_delivery_message'], $_SESSION['otp_debug_code'], $_SESSION['otp_next_destination']);
                redirect_to($destination);
            } else {
                $error = 'Invalid or expired OTP.';
            }
        }
    }
}
?>
<?php auth_page_start('Verify Access Code'); ?>

<?php if ($success): ?><div class="notice ok"><?= e($success) ?></div><?php endif; ?>
<?php if ($deliveryMessage): ?><p class="success">OTP <?= e($deliveryMessage) ?>.</p><?php endif; ?>
<?php if ($debugCode): ?><p class="error">Local test OTP: <?= e($debugCode) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<form method="POST">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <span class="auth-eyebrow">NATCODEV identity check</span>
    <h1>Verify access code</h1>
    <p class="lead">Enter the 6-digit code sent to your registered contact. Your role determines the workspace you enter after verification.</p>

    <div class="otp-grid" aria-label="One time password">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <input name="otp_digits[]" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="one-time-code" required>
        <?php endfor; ?>
    </div>

    <button type="submit">Verify and Continue</button>
</form>

<div class="links">
    <span>Did not receive verification code?</span>
    <button type="button" id="resendOtpBtn" style="background:none;border:0;color:inherit;text-decoration:underline;cursor:pointer;font:inherit">Resend code</button>
</div>

<script>
    (() => {
        const fields = Array.from(document.querySelectorAll('.otp-grid input'));
        const form = fields[0]?.closest('form');
        let submitting = false;

        const code = () => fields.map((field) => field.value).join('');
        const submitIfComplete = () => {
            if (!form || submitting || code().length !== fields.length) return;
            submitting = true;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Verifying...';
            }
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        };

        const fillFrom = (startIndex, value) => {
            const digits = String(value).replace(/\D/g, '');
            if (!digits) return false;
            // When pasting full length code or more, start from beginning
            const actualStart = digits.length >= fields.length ? 0 : startIndex;
            const sliceDigits = digits.slice(0, fields.length - actualStart);
            sliceDigits.split('').forEach((digit, offset) => {
                if (fields[actualStart + offset]) {
                    fields[actualStart + offset].value = digit;
                }
            });
            const nextIndex = Math.min(actualStart + sliceDigits.length, fields.length - 1);
            fields[nextIndex]?.focus();
            submitIfComplete();
            return true;
        };

        fields.forEach((input, index) => {
            input.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                fillFrom(index, pasted);
            });

            input.addEventListener('input', () => {
                const raw = input.value;
                input.value = '';
                fillFrom(index, raw);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace') {
                    if (!input.value && fields[index - 1]) {
                        fields[index - 1].focus();
                        fields[index - 1].value = '';
                    }
                } else if (event.key === 'ArrowLeft' && fields[index - 1]) {
                    fields[index - 1].focus();
                } else if (event.key === 'ArrowRight' && fields[index + 1]) {
                    fields[index + 1].focus();
                }
            });

            input.addEventListener('focus', () => {
                input.select();
            });
        });

        // Auto-focus first empty field
        const firstEmpty = fields.find((f) => !f.value) || fields[0];
        firstEmpty?.focus();
    })();
document.getElementById('resendOtpBtn')?.addEventListener('click', async function () {
        const btn = this;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Sending...';

        const form = document.querySelector('form');
        const csrf = form.querySelector('[name="_csrf"]').value;
        const formData = new FormData();
        formData.append('_csrf', csrf);
        formData.append('resend_otp', '1');

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            if (res.ok) {
                window.location.reload();
            } else {
                btn.textContent = 'Failed. Try again.';
                btn.disabled = false;
            }
        } catch (err) {
            btn.textContent = 'Network error.';
            btn.disabled = false;
        }
    });
</script>

<?php auth_page_end(); ?>