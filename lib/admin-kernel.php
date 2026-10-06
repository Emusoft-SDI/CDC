<?php
declare(strict_types=1);

/**
 * Admin Kernel (Phase 1, additive).
 *
 * A tiny, optional request pipeline that new admin endpoints can adopt without
 * changing any existing page. Nothing here is wired into legacy pages by default,
 * so behaviour is unchanged until a caller opts in.
 */

/**
 * Stable per-request identifier. Reads X-Request-Id when provided (so a proxy or
 * front-end can correlate), otherwise generates one. Emitted as a response header
 * when possible.
 */
function admin_request_id(): string
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }

    $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{6,64}$/', $incoming)) {
        $id = $incoming;
    } else {
        try {
            $id = 'req_' . bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $id = 'req_' . substr(md5((string) microtime(true)), 0, 16);
        }
    }

    if (!headers_sent()) {
        header('X-Request-Id: ' . $id);
    }

    return $id;
}

/**
 * Session-backed flash messages. Additive: only used by callers that opt in.
 *
 * @param mixed $value When provided, sets the flash; when null, reads and clears it.
 * @return mixed
 */
function admin_flash(string $key, $value = null)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if ($value !== null) {
        $_SESSION['__admin_flash'][$key] = $value;
        return null;
    }
    $stored = $_SESSION['__admin_flash'][$key] ?? null;
    unset($_SESSION['__admin_flash'][$key]);
    return $stored;
}

/**
 * Optional boot helper for new admin endpoints. Mirrors admin_require() semantics
 * (session auth + feature gate) and adds a request id + JSON/HTML negotiation.
 *
 * @param array{feature?:string,json?:bool} $options
 */
function admin_boot(PDO $pdo, array $options = []): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    admin_request_id();

    $feature = (string) ($options['feature'] ?? admin_feature_for_script());
    if (!admin_session_is_authenticated($pdo)) {
        if (!empty($options['json'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'unauthenticated']);
            exit;
        }
        redirect_to('login.php');
    }
    admin_require_feature($pdo, $feature);
}

/**
 * Send a JSON payload and stop. Used by the generic admin action endpoint.
 *
 * @param array<string,mixed> $payload
 */
function admin_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('X-Request-Id: ' . admin_request_id());
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
