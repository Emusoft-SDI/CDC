<?php
declare(strict_types=1);

/**
 * Staff account claim flow.
 *
 * Staff accounts (field_agent, agronomist, extensionist, coordinators,
 * support_agent) are activated by the staff member through a claim link
 * instead of receiving a plain-text temporary password, mirroring the
 * grower engagement-token flow (confirm-engagement.php).
 *
 * Loaded from config.php so every creation site and the public claim
 * page (claim-account.php) share the same helpers.
 */

function staff_claim_ensure_schema(PDO $pdo): void
{
    // Lazy column addition — same app_add_column_if_missing pattern used
    // for other late-added user columns (e.g. password_reset_token).
    app_add_column_if_missing($pdo, 'users', 'claim_token', 'VARCHAR(64) NULL');
    app_add_column_if_missing($pdo, 'users', 'claim_deadline', 'DATETIME NULL');
    app_add_column_if_missing($pdo, 'users', 'claimed_at', 'DATETIME NULL');
}

/**
 * Issue a staff account claim link.
 *
 * Generates a 32-hex claim token with a 14-day deadline, persists both
 * on the user record, and emails the staff member the claim URL
 * (app_base_url() . '/claim-account.php?token=...').
 *
 * @return array{ok: bool, token: string, claim_url: string, message: string}
 */
function staff_account_claim_begin(PDO $pdo, int $userId): array
{
    staff_claim_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || !filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'ok' => false,
            'token' => '',
            'claim_url' => '',
            'message' => 'Claim link was not issued: the account has no valid email address.',
        ];
    }

    $token = bin2hex(random_bytes(16)); // 32 hex characters
    $deadline = date('Y-m-d H:i:s', strtotime('+14 days'));
    $pdo->prepare('UPDATE users SET claim_token = ?, claim_deadline = ?, claimed_at = NULL WHERE id = ?')
        ->execute([$token, $deadline, $userId]);

    $claimUrl = app_base_url() . '/claim-account.php?token=' . $token;
    $name = trim((string) ($user['name'] ?? '')) ?: 'NATCODEV Staff';
    $email = (string) $user['email'];
    $plain = "Hello {$name},\n\nYour NATCODEV staff account is ready. Set your sign-in name, phone, and password with this secure claim link (valid for 14 days):\n{$claimUrl}\n\nIf you did not expect this email, contact your NATCODEV administrator.";
    $html = '<p>Dear <strong>' . e($name) . '</strong>,</p><p>Your NATCODEV staff account is ready. Set your sign-in details with the secure claim link below (valid for 14 days).</p><p><a href="' . e($claimUrl) . '" style="display:inline-block;padding:10px 18px;background:#075f2a;color:#fff;text-decoration:none;border-radius:6px;">Claim Staff Account</a></p><p>If you did not expect this email, contact your NATCODEV administrator.</p>';
    $sent = app_send_mail($email, 'Claim your NATCODEV staff account', $plain, $html);

    return [
        'ok' => (bool) $sent,
        'token' => $token,
        'claim_url' => $claimUrl,
        'message' => $sent
            ? 'Account claim link sent to the staff email address.'
            : 'Claim link could not be delivered. Check the mail transport configuration.',
    ];
}
