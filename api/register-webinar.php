<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';

session_start();
$pdo = db();
$returnTo = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
$wantsRedirect = $returnTo !== '';
$user = current_user($pdo);
if (!$user) {
    if ($wantsRedirect) {
        redirect_to('../login.php?next=' . urlencode('academy/index.php?screen=catalog'));
    }
    json_response(['success' => false, 'error' => 'Authentication required'], 401);
}

function webinar_ensure_registration_schema(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinar_registrations')) {
        return;
    }
    foreach ([
        'progress_percent' => "TINYINT UNSIGNED NOT NULL DEFAULT 0",
        'completion_status' => "VARCHAR(30) NOT NULL DEFAULT 'registered'",
        'started_at' => "DATETIME NULL",
        'completed_at' => "DATETIME NULL",
        'certificate_status' => "VARCHAR(30) NOT NULL DEFAULT 'not_required'",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'webinar_registrations', $column, $definition);
    }
    try {
        if (app_table_exists($pdo, 'academy_certificates')) {
            $pdo->exec("
                UPDATE academy_certificates c
                JOIN webinar_registrations r ON r.id = c.registration_id
                JOIN (
                    SELECT webinar_id, user_id, MIN(id) keep_id
                    FROM webinar_registrations
                    GROUP BY webinar_id, user_id
                    HAVING COUNT(*) > 1
                ) d ON d.webinar_id = r.webinar_id AND d.user_id = r.user_id
                SET c.registration_id = d.keep_id
            ");
        }
        $pdo->exec("
            DELETE r
            FROM webinar_registrations r
            JOIN (
                SELECT webinar_id, user_id, MIN(id) keep_id
                FROM webinar_registrations
                GROUP BY webinar_id, user_id
                HAVING COUNT(*) > 1
            ) d ON d.webinar_id = r.webinar_id AND d.user_id = r.user_id
            WHERE r.id <> d.keep_id
        ");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE webinar_registrations ADD UNIQUE KEY uniq_webinar_user (webinar_id, user_id)");
    } catch (Throwable $e) {
    }
}

function webinar_user_is_registered(PDO $pdo, int $webinarId, int $userId): bool
{
    $stmt = $pdo->prepare("SELECT id FROM webinar_registrations WHERE webinar_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$webinarId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function webinar_insert_registration(PDO $pdo, int $webinarId, int $userId, string $paymentStatus): void
{
    if (webinar_user_is_registered($pdo, $webinarId, $userId)) {
        return;
    }
    $courseStmt = $pdo->prepare("SELECT certification_required FROM webinars WHERE id = ? LIMIT 1");
    $courseStmt->execute([$webinarId]);
    $certificateStatus = (int) ($courseStmt->fetchColumn() ?: 0) === 1 ? 'not_started' : 'not_required';
    $pdo->prepare("
        INSERT INTO webinar_registrations
            (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status)
        VALUES (?, ?, ?, 0, 'registered', ?)
    ")->execute([$webinarId, $userId, $paymentStatus, $certificateStatus]);
}

function webinar_registration_response(bool $success, string $message, int $status = 200): void
{
    $returnTo = trim((string) ($_POST['return_to'] ?? ''));
    if ($returnTo !== '') {
        $safeReturn = (str_starts_with($returnTo, '../dashboard/webinars.php') || str_starts_with($returnTo, '../dashboard/academy.php') || str_starts_with($returnTo, '../academy/index.php') || str_starts_with($returnTo, '../academy/dashboard.php')) ? $returnTo : '../academy/index.php';
        $separator = str_contains($safeReturn, '?') ? '&' : '?';
        redirect_to($safeReturn . $separator . http_build_query([
            $success ? 'registered' : 'error' => $message,
        ]));
    }

    if (!$success) {
        json_response(['success' => false, 'error' => $message], $status);
    }
    json_response(['success' => true, 'message' => $message], $status);
}

function webinar_return_to(): string
{
    $returnTo = trim((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''));
    return (str_starts_with($returnTo, '../dashboard/webinars.php') || str_starts_with($returnTo, '../dashboard/academy.php') || str_starts_with($returnTo, '../academy/index.php') || str_starts_with($returnTo, '../academy/dashboard.php')) ? $returnTo : '../academy/index.php';
}

function webinar_redirect(bool $success, string $message): void
{
    $returnTo = webinar_return_to();
    $separator = str_contains($returnTo, '?') ? '&' : '?';
    redirect_to($returnTo . $separator . http_build_query([$success ? 'registered' : 'error' => $message]));
}

function webinar_direct_reference(int $userId, int $webinarId): string
{
    return 'NAT-TRAIN-' . $userId . '-' . $webinarId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function webinar_initialize_direct_payment(PDO $pdo, array $user, array $webinar, string $returnTo): array
{
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }
    wallet_ensure_schema($pdo);
    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    $webinarId = (int) $webinar['id'];
    $reference = webinar_direct_reference((int) $user['id'], $webinarId);
    $redirectUrl = app_base_url() . '/api/register-webinar.php?' . http_build_query([
        'verify_direct' => $reference,
        'return_to' => $returnTo,
    ]);
    $payload = [
        'amount' => round((float) $webinar['price'], 2),
        'customerName' => (string) ($user['name'] ?? 'NATCODEV User'),
        'customerEmail' => (string) ($user['email'] ?? ''),
        'paymentReference' => $reference,
        'paymentDescription' => 'NATCODEV paid training: ' . (string) $webinar['title'],
        'currencyCode' => 'NGN',
        'contractCode' => monnify_env('MONNIFY_CONTRACT_CODE'),
        'redirectUrl' => $redirectUrl,
        'paymentMethods' => monnify_payment_methods(),
    ];
    $res = monnify_request('POST', '/api/v1/merchant/transactions/init-transaction', $payload);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Monnify payment'];
    }
    $body = $res['data']['responseBody'] ?? [];
    $store = $body + ['training_webinar_id' => $webinarId, 'return_to' => $returnTo];
    $pdo->prepare("
        INSERT INTO wallet_transactions
            (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status)
        VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'monnify_training', ?, ?, 'pending')
    ")->execute([
        (int) $wallet['id'],
        (int) $user['id'],
        (float) $webinar['price'],
        'Direct Monnify payment for training: ' . (string) $webinar['title'],
        $reference,
        (string) ($body['transactionReference'] ?? ''),
        json_encode($store, JSON_UNESCAPED_SLASHES),
    ]);
    return ['success' => true, 'payment_url' => (string) ($body['checkoutUrl'] ?? ''), 'reference' => $reference];
}

function webinar_verify_direct_payment(PDO $pdo, array $user, string $reference): array
{
    wallet_ensure_schema($pdo);
    webinar_ensure_registration_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT *
        FROM wallet_transactions
        WHERE user_id = ? AND reference = ? AND provider = 'monnify_training'
        LIMIT 1
    ");
    $stmt->execute([(int) $user['id'], $reference]);
    $transaction = $stmt->fetch();
    if (!$transaction) {
        return ['success' => false, 'error' => 'Training payment reference was not found.'];
    }
    $payload = json_decode((string) ($transaction['provider_payload'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $webinarId = (int) ($payload['training_webinar_id'] ?? 0);
    if (($transaction['status'] ?? '') === 'completed') {
        if ($webinarId > 0) {
            webinar_insert_registration($pdo, $webinarId, (int) $user['id'], 'paid');
        }
        return ['success' => true, 'message' => 'Payment already confirmed. You are registered.'];
    }
    $providerReference = (string) ($transaction['provider_reference'] ?: $reference);
    $res = monnify_request('GET', '/api/v2/transactions/' . rawurlencode($providerReference));
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to verify Monnify payment.'];
    }
    $body = $res['data']['responseBody'] ?? [];
    if (strtoupper((string) ($body['paymentStatus'] ?? '')) !== 'PAID') {
        return ['success' => false, 'error' => 'Payment is still pending. Complete payment, then try again.'];
    }
    $amountPaid = (float) ($body['amountPaid'] ?? 0);
    if ($amountPaid + 0.01 < (float) $transaction['amount']) {
        return ['success' => false, 'error' => 'Payment amount is incomplete.'];
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE wallet_transactions
            SET status = 'completed', provider_reference = ?, provider_payload = ?, completed_at = NOW()
            WHERE id = ?
        ")->execute([
            (string) ($body['transactionReference'] ?? $providerReference),
            json_encode($body + $payload, JSON_UNESCAPED_SLASHES),
            (int) $transaction['id'],
        ]);
        webinar_insert_registration($pdo, $webinarId, (int) $user['id'], 'paid');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return ['success' => true, 'message' => 'Payment confirmed. You are registered.'];
}

if (isset($_GET['verify_direct'])) {
    if (!app_check_rate_limit('training_verify', 10, 600)) {
        webinar_redirect(false, 'Too many verification attempts. Please wait 10 minutes.');
    }
    $result = webinar_verify_direct_payment($pdo, $user, trim((string) $_GET['verify_direct']));
    webinar_redirect((bool) ($result['success'] ?? false), (string) (($result['message'] ?? '') ?: ($result['error'] ?? 'Unable to verify training payment.')));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('training_enroll', 10, 3600)) {
    webinar_registration_response(false, 'Too many enrollment attempts. Please try again in an hour.', 429);
}

if ($wantsRedirect && !verify_csrf($_POST['_csrf'] ?? null)) {
    webinar_registration_response(false, 'Security token expired. Refresh the training page and try again.', 419);
}

$webinarId = filter_var($_POST['webinar_id'] ?? null, FILTER_VALIDATE_INT);
if (!$webinarId) {
    webinar_registration_response(false, 'Training course ID required.', 422);
}

try {
    app_ensure_farmer_engagement_schema($pdo);
    webinar_ensure_registration_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, title, is_free, price, status FROM webinars WHERE id = ? LIMIT 1");
    $stmt->execute([$webinarId]);
    $webinar = $stmt->fetch();
    if (!$webinar) {
        webinar_registration_response(false, 'Training course not found.', 404);
    }
    if (($webinar['status'] ?? 'active') !== 'active') {
        webinar_registration_response(false, 'This training course is not currently open.', 403);
    }

    if ((int) $webinar['is_free'] === 1) {
        if (webinar_user_is_registered($pdo, $webinarId, (int) $user['id'])) {
            webinar_registration_response(true, 'You are already registered for this training.');
        }
        webinar_insert_registration($pdo, $webinarId, (int) $user['id'], 'free');
        webinar_registration_response(true, 'Registered successfully.');
    }

    wallet_ensure_schema($pdo);
    if (webinar_user_is_registered($pdo, $webinarId, (int) $user['id'])) {
        webinar_registration_response(true, 'You are already registered for this training.');
    }

    $paymentMethod = (string) ($_POST['payment_method'] ?? 'wallet');
    if ($paymentMethod === 'monnify_direct') {
        $checkout = webinar_initialize_direct_payment($pdo, $user, $webinar, webinar_return_to());
        if (!($checkout['success'] ?? false)) {
            webinar_registration_response(false, (string) ($checkout['error'] ?? 'Unable to initialize direct payment.'), 422);
        }
        $paymentUrl = (string) ($checkout['payment_url'] ?? '');
        if ($paymentUrl === '') {
            webinar_registration_response(false, 'Monnify did not return a checkout URL.', 422);
        }
        redirect_to($paymentUrl);
    }

    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
    $stmt->execute([(int) $wallet['id']]);
    $lockedWallet = $stmt->fetch();
    $balance = (float) ($lockedWallet['balance'] ?? 0);
    $price = (float) $webinar['price'];

    if ($balance < $price) {
        $pdo->rollBack();
        webinar_registration_response(false, 'Insufficient wallet balance. Fund your wallet or pay directly with Monnify.', 402);
    }

    if (webinar_user_is_registered($pdo, $webinarId, (int) $user['id'])) {
        $pdo->rollBack();
        webinar_registration_response(true, 'You are already registered for this training.');
    }

    $reference = 'NAT-TRAIN-WALLET-' . (int) $user['id'] . '-' . $webinarId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $after = $balance - $price;
    $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $lockedWallet['id']]);
    $pdo->prepare("
        INSERT INTO wallet_transactions
            (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after, completed_at)
        VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'wallet', ?, ?, 'completed', ?, ?, NOW())
    ")->execute([
        (int) $lockedWallet['id'],
        (int) $user['id'],
        $price,
        'Paid training registration: ' . (string) $webinar['title'],
        $reference,
        $reference,
        json_encode(['webinar_id' => $webinarId, 'title' => (string) $webinar['title']], JSON_UNESCAPED_SLASHES),
        $balance,
        $after,
    ]);
    webinar_insert_registration($pdo, $webinarId, (int) $user['id'], 'paid');
    $pdo->commit();

    webinar_registration_response(true, "Payment processed. You're registered.");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Register webinar API error: ' . $e->getMessage());
    webinar_registration_response(false, 'Unable to register training course.', 500);
}
