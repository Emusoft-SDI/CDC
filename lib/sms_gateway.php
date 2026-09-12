<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Ensures the SMS and WhatsApp gateways and audit logging tables exist.
 */
function sms_gateway_ensure_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    // 1. SMS Gateways Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_gateways (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gateway_key VARCHAR(60) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            channel_type ENUM('sms', 'whatsapp', 'both') NOT NULL DEFAULT 'sms',
            provider_driver VARCHAR(60) NOT NULL DEFAULT 'custom',
            base_url VARCHAR(255) NOT NULL,
            auth_type ENUM('bearer', 'basic', 'apikey_header', 'query_param', 'json_auth') NOT NULL DEFAULT 'bearer',
            api_key VARCHAR(255) NULL,
            api_secret VARCHAR(255) NULL,
            sender_id VARCHAR(50) NOT NULL DEFAULT 'NATCODEV',
            environment ENUM('live', 'simulation') NOT NULL DEFAULT 'live',
            status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            is_primary_sms TINYINT(1) NOT NULL DEFAULT 0,
            is_primary_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
            priority INT NOT NULL DEFAULT 1,
            custom_headers JSON NULL,
            custom_payload_template JSON NULL,
            last_balance_check VARCHAR(100) NULL,
            last_balance_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sms_gw_status (status, channel_type),
            INDEX idx_sms_gw_primary (is_primary_sms, is_primary_whatsapp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. SMS & WhatsApp Message Delivery Logs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sms_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            recipient VARCHAR(50) NOT NULL,
            channel ENUM('sms', 'whatsapp') NOT NULL DEFAULT 'sms',
            gateway_id INT UNSIGNED NULL,
            gateway_key VARCHAR(60) NULL,
            sender_id VARCHAR(50) NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'delivered', 'failed', 'simulated') NOT NULL DEFAULT 'pending',
            provider_reference VARCHAR(120) NULL,
            provider_response TEXT NULL,
            http_code INT NULL,
            error_message VARCHAR(255) NULL,
            metadata JSON NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sms_logs_recipient (recipient),
            INDEX idx_sms_logs_user (user_id),
            INDEX idx_sms_logs_status (status, created_at),
            INDEX idx_sms_logs_channel (channel, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. Seed Default Gateways (eBulkSMS & PaylessBulkSMS) if not present
    $stmt = $pdo->query("SELECT COUNT(*) FROM sms_gateways");
    if ((int) $stmt->fetchColumn() === 0) {
        // eBulkSMS Default
        $stmt = $pdo->prepare("
            INSERT INTO sms_gateways (gateway_key, name, channel_type, provider_driver, base_url, auth_type, api_key, api_secret, sender_id, environment, status, is_primary_sms, is_primary_whatsapp, priority, created_at)
            VALUES ('ebulksms', 'eBulkSMS Nigeria', 'sms', 'ebulksms', 'https://api.ebulksms.com/sendsms.json', 'json_auth', 'dehgracy@gmail.com', '37d9b5abb471d6deeb00640e5659c9592c981c963af44ddd19216b2a5910eeca', 'NATCODEV', 'live', 'active', 1, 0, 1, NOW())
        ");
        $stmt->execute();

        // PaylessBulkSMS Default (SMS & WhatsApp)
        $stmt = $pdo->prepare("
            INSERT INTO sms_gateways (gateway_key, name, channel_type, provider_driver, base_url, auth_type, api_key, sender_id, environment, status, is_primary_sms, is_primary_whatsapp, priority, created_at)
            VALUES ('paylessbulksms', 'PaylessBulkSMS & WhatsApp Gateway', 'both', 'paylessbulksms', 'https://app.paylessbulksms.com.ng/api/v3/', 'bearer', '126|9bUDGRTo5MIGHEG9VMps65SrUXBOmkxAuK1CYFik974446f6', 'NATCODEV', 'live', 'active', 0, 1, 2, NOW())
        ");
        $stmt->execute();
    }

    $ensured = true;
}

/**
 * Retrieve the current Global Brand / Alphanumeric Sender ID from active database configuration.
 */
function sms_get_global_sender_id(PDO $pdo): string
{
    sms_gateway_ensure_schema($pdo);
    try {
        $stmt = $pdo->query("SELECT sender_id FROM sms_gateways WHERE is_primary_sms = 1 AND sender_id IS NOT NULL AND sender_id != '' LIMIT 1");
        $sender = $stmt->fetchColumn();
        if (!empty($sender)) {
            return (string) $sender;
        }
        $stmt = $pdo->query("SELECT sender_id FROM sms_gateways WHERE status = 'active' AND sender_id IS NOT NULL AND sender_id != '' ORDER BY id ASC LIMIT 1");
        $sender = $stmt->fetchColumn();
        if (!empty($sender)) {
            return (string) $sender;
        }
    } catch (Throwable $e) {
        // Fallback
    }
    return 'NATCODEV';
}

/**
 * Update the Global Brand / Alphanumeric Sender ID across all configured SMS gateways.
 */
function sms_set_global_sender_id(PDO $pdo, string $senderId): bool
{
    sms_gateway_ensure_schema($pdo);
    $clean = substr(preg_replace('/[^a-zA-Z0-9]/', '', $senderId), 0, 11);
    if (empty($clean)) {
        $clean = 'NATCODEV';
    }
    try {
        $stmt = $pdo->prepare("UPDATE sms_gateways SET sender_id = ? WHERE channel_type IN ('sms', 'both')");
        return $stmt->execute([$clean]);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Format phone number to international MSISDN format (e.g. 2348012345678).
 */
function sms_normalize_phone(string $phone): string
{
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0') && strlen($clean) === 11) {
        return '234' . substr($clean, 1);
    }
    if (str_starts_with($clean, '234') && strlen($clean) === 13) {
        return $clean;
    }
    if (str_starts_with($clean, '+')) {
        return substr($clean, 1);
    }
    return $clean;
}

/**
 * Execute HTTP cURL request for SMS / WhatsApp gateways.
 */
function sms_http_request(string $url, array $headers = [], $body = null, string $method = 'POST', int $timeout = 15): array
{
    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'cURL extension not available',
            'data' => null,
        ];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body, JSON_UNESCAPED_SLASHES) : $body);
        }
    } elseif ($method === 'GET') {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => $curlError,
            'data' => null,
        ];
    }

    $data = json_decode((string) $response, true);
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => (string) $response,
        'data' => $data,
    ];
}

/**
 * Check if a provider response signifies low or exhausted credit/balance.
 */
function sms_gateway_is_out_of_credit(string $response, int $httpCode): bool
{
    if ($httpCode === 402) {
        return true;
    }
    $lower = strtolower($response);
    $needles = [
        'insufficient',
        'no credit',
        'low balance',
        'low unit',
        'no unit',
        'out of credit',
        'balance is low',
        'not enough unit',
        'units exhausted',
        'insufficient_funds',
        'insufficient_balance',
        'insufficient_units',
        'exceeded your sending limit',
        'sending limit',
        'limit exceeded',
        'quota exceeded',
        'exceeded limit',
        '1001', // eBulkSMS code for insufficient credits
    ];
    foreach ($needles as $n) {
        if (str_contains($lower, $n)) {
            return true;
        }
    }
    return false;
}

/**
 * Live Balance and Connection Check for any configured gateway.
 */
function sms_gateway_check_balance(PDO $pdo, array $gateway): array
{
    sms_gateway_ensure_schema($pdo);
    $driver = $gateway['provider_driver'] ?? 'custom';
    $baseUrl = rtrim((string) ($gateway['base_url'] ?? ''), '/');
    $apiKey = (string) ($gateway['api_key'] ?? '');
    $apiSecret = (string) ($gateway['api_secret'] ?? '');

    // 1. eBulkSMS Balance Check
    if ($driver === 'ebulksms') {
        $url = "https://api.ebulksms.com/balance/{$apiKey}/{$apiSecret}";
        $res = sms_http_request($url, [], null, 'GET');
        $bal = trim((string) $res['response']);
        $isSuccess = $res['http_code'] === 200 && is_numeric($bal);
        $formatted = $isSuccess ? ($bal . ' Units') : ('Error: ' . ($bal ?: 'HTTP ' . $res['http_code']));

        if ($isSuccess && !empty($gateway['id'])) {
            $stmt = $pdo->prepare("UPDATE sms_gateways SET last_balance_check = ?, last_balance_at = NOW() WHERE id = ?");
            $stmt->execute([$formatted, $gateway['id']]);
        }

        return [
            'success' => $isSuccess,
            'balance_raw' => $bal,
            'balance_formatted' => $formatted,
            'http_code' => $res['http_code'],
            'response' => $res['response']
        ];
    }

    // 2. PaylessBulkSMS Balance Check
    if ($driver === 'paylessbulksms') {
        $url = str_contains($baseUrl, '/api/v3') ? ($baseUrl . '/balance') : ($baseUrl . '/api/v3/balance');
        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        $res = sms_http_request($url, $headers, null, 'GET');
        $isSuccess = $res['http_code'] === 200 && isset($res['data']['data']['remaining_balance']);
        $bal = $res['data']['data']['remaining_balance'] ?? ($res['data']['message'] ?? $res['response']);
        $formatted = $isSuccess ? ('₦' . number_format((float)$bal, 2)) : ('Error: ' . $bal);

        if ($isSuccess && !empty($gateway['id'])) {
            $stmt = $pdo->prepare("UPDATE sms_gateways SET last_balance_check = ?, last_balance_at = NOW() WHERE id = ?");
            $stmt->execute([$formatted, $gateway['id']]);
        }

        return [
            'success' => $isSuccess,
            'balance_raw' => (string) $bal,
            'balance_formatted' => $formatted,
            'http_code' => $res['http_code'],
            'response' => $res['response']
        ];
    }

    // 3. Termii Balance Check
    if ($driver === 'termii' || str_contains(strtolower($gateway['gateway_key'] ?? ''), 'termii') || str_contains($baseUrl, 'termii.com')) {
        $url = "https://api.ng.termii.com/api/get-balance?api_key={$apiKey}";
        $res = sms_http_request($url, ['Accept: application/json'], null, 'GET');
        $isSuccess = $res['http_code'] === 200 && isset($res['data']['balance']);
        $bal = $res['data']['balance'] ?? ($res['data']['message'] ?? $res['response']);
        $curr = $res['data']['currency'] ?? 'NGN';
        $formatted = $isSuccess ? ($curr . ' ' . number_format((float)$bal, 2)) : ('Error: ' . $bal);

        if ($isSuccess && !empty($gateway['id'])) {
            $stmt = $pdo->prepare("UPDATE sms_gateways SET last_balance_check = ?, last_balance_at = NOW() WHERE id = ?");
            $stmt->execute([$formatted, $gateway['id']]);
        }

        return [
            'success' => $isSuccess,
            'balance_raw' => (string) $bal,
            'balance_formatted' => $formatted,
            'http_code' => $res['http_code'],
            'response' => $res['response']
        ];
    }

    // Generic Custom Gateway Ping
    if ($baseUrl !== '') {
        $res = sms_http_request($baseUrl, ['Accept: application/json'], null, 'GET');
        return [
            'success' => $res['http_code'] >= 200 && $res['http_code'] < 400,
            'balance_raw' => (string) $res['http_code'],
            'balance_formatted' => 'HTTP ' . $res['http_code'] . ' Response OK',
            'http_code' => $res['http_code'],
            'response' => $res['response']
        ];
    }

    return [
        'success' => false,
        'balance_raw' => '0',
        'balance_formatted' => 'No Base URL configured',
        'http_code' => 0,
        'response' => 'No Base URL'
    ];
}

/**
 * Dispatch an SMS Message via targeted gateway or smart credit auto-failover routing.
 */
function app_send_sms(string $recipient, string $message, array $options = []): array
{
    $pdo = db();
    sms_gateway_ensure_schema($pdo);

    $normPhone = sms_normalize_phone($recipient);
    if (empty($normPhone)) {
        return ['success' => false, 'error' => 'Invalid recipient phone number.'];
    }

    // Check if a specific target gateway was requested (e.g. from Live Test Dispatcher)
    if (!empty($options['gateway_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE id = ? AND channel_type IN ('sms', 'both') LIMIT 1");
        $stmt->execute([(int) $options['gateway_id']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($gateways)) {
            return ['success' => false, 'error' => 'Selected gateway is not available for SMS channel.'];
        }
    } elseif (!empty($options['gateway_key'])) {
        $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE gateway_key = ? AND status = 'active' AND channel_type IN ('sms', 'both') LIMIT 1");
        $stmt->execute([(string) $options['gateway_key']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($gateways)) {
            return ['success' => false, 'error' => "Gateway '{$options['gateway_key']}' not found or inactive."];
        }
    } else {
        // Retrieve all active SMS gateways ordered by default priority
        $stmt = $pdo->prepare("
            SELECT * FROM sms_gateways 
            WHERE status = 'active' AND channel_type IN ('sms', 'both')
            ORDER BY is_primary_sms DESC, priority ASC, id ASC
        ");
        $stmt->execute();
        $allGateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allGateways)) {
            $defaultSender = sms_get_global_sender_id($pdo);
            return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', null, 'none', $defaultSender, $message, 'simulated', null, 'No active SMS gateways configured. Simulated locally.', 200);
        }

        // Smart Credit-Aware Sorting:
        // Gateways with positive credit or unchecked float are attempted first.
        // Gateways detected as 0 units / exhausted are shifted as secondary fallback.
        $viable = [];
        $lowCredit = [];
        foreach ($allGateways as $gw) {
            $lastBal = strtolower((string) ($gw['last_balance_check'] ?? ''));
            if (str_contains($lastBal, '0 units') || str_contains($lastBal, '0.00') || str_contains($lastBal, 'low credit') || str_contains($lastBal, 'exhausted')) {
                $lowCredit[] = $gw;
            } else {
                $viable[] = $gw;
            }
        }
        $gateways = array_merge($viable, $lowCredit);
    }

    $errorsList = [];
    $attemptedGateways = [];

    foreach ($gateways as $gw) {
        $gwKey = (string) $gw['gateway_key'];
        $gwName = (string) $gw['name'];
        $attemptedGateways[] = $gwName;

        if ($gw['environment'] === 'simulation') {
            return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', (int)$gw['id'], $gwKey, $gw['sender_id'], $message, 'simulated', 'SIM-' . strtoupper(bin2hex(random_bytes(4))), 'Simulation mode enabled.', 200);
        }

        $driver = $gw['provider_driver'];
        $rawSender = $options['sender_id'] ?? $gw['sender_id'] ?? sms_get_global_sender_id($pdo);
        $senderId = substr(preg_replace('/[^a-zA-Z0-9]/', '', (string)$rawSender), 0, 11);
        if (empty($senderId)) {
            $senderId = 'NATCODEV';
        }
        $apiKey = $gw['api_key'] ?? '';
        $apiSecret = $gw['api_secret'] ?? '';
        $baseUrl = rtrim((string)$gw['base_url'], '/');

        // 1. eBulkSMS Driver
        if ($driver === 'ebulksms') {
            $payload = [
                'SMS' => [
                    'auth' => [
                        'username' => $apiKey,
                        'apikey' => $apiSecret,
                    ],
                    'message' => [
                        'sender' => $senderId,
                        'messagetext' => $message,
                        'flash' => '0',
                    ],
                    'recipients' => [
                        'gsm' => [
                            ['msisdn' => $normPhone]
                        ]
                    ]
                ]
            ];

            $url = !empty($baseUrl) && str_contains($baseUrl, 'ebulksms.com') ? $baseUrl : 'https://api.ebulksms.com/sendsms.json';
            $res = sms_http_request($url, ['Content-Type: application/json', 'Accept: application/json'], $payload, 'POST');
            $raw = (string) $res['response'];
            $isSuccess = ($res['http_code'] === 200) && (str_contains($raw, 'SUCCESS') || (isset($res['data']['response']['status']) && $res['data']['response']['status'] === 'SUCCESS'));

            if ($isSuccess) {
                $ref = 'EBS-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $raw, $res['http_code']);
            }

            // Specific error diagnostics
            if (str_contains($raw, 'INVALID_SENDER')) {
                $errorsList[] = "[{$gwName}] Sender ID '{$senderId}' is not registered/approved on your eBulkSMS dashboard. (Please register '{$senderId}' on ebulksms.com or update Sender ID in Gateway settings)";
            } elseif (sms_gateway_is_out_of_credit($raw, $res['http_code'])) {
                $pdo->prepare("UPDATE sms_gateways SET last_balance_check = '0 Units (Low Credit)', last_balance_at = NOW() WHERE id = ?")->execute([$gw['id']]);
                $errorsList[] = "[{$gwName}] Out of credits. Auto-switching to next gateway...";
            } else {
                $errorsList[] = "[{$gwName}] " . $raw;
            }
        }

        // 2. PaylessBulkSMS Driver
        elseif ($driver === 'paylessbulksms') {
            $url = str_contains($baseUrl, '/api/v3') ? ($baseUrl . '/sms/send') : ($baseUrl . '/api/v3/sms/send');
            $headers = [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            $payload = [
                'recipient' => $normPhone,
                'sender_id' => $senderId,
                'message' => $message,
                'type' => 'plain'
            ];

            $res = sms_http_request($url, $headers, $payload, 'POST');
            $isSuccess = ($res['http_code'] >= 200 && $res['http_code'] < 300) && (!isset($res['data']['status']) || $res['data']['status'] === 'success');

            if ($isSuccess) {
                $ref = $res['data']['data']['uid'] ?? ('PLS-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $res['response'], $res['http_code']);
            }

            $raw = $res['data']['message'] ?? $res['response'];
            if (sms_gateway_is_out_of_credit((string)$raw, $res['http_code'])) {
                $pdo->prepare("UPDATE sms_gateways SET last_balance_check = '0 Units (Low Credit)', last_balance_at = NOW() WHERE id = ?")->execute([$gw['id']]);
                $errorsList[] = "[{$gwName}] Out of credits. Auto-switching to next gateway...";
            } else {
                $errorsList[] = "[{$gwName}] " . $raw;
            }
        }

        // 3. Termii Driver
        elseif ($driver === 'termii' || str_contains(strtolower($gwKey), 'termii') || str_contains($baseUrl, 'termii.com')) {
            $url = str_contains($baseUrl, '/api/sms/send') ? $baseUrl : 'https://api.ng.termii.com/api/sms/send';
            $payload = [
                'to' => $normPhone,
                'from' => $senderId,
                'sms' => $message,
                'type' => 'plain',
                'channel' => 'generic',
                'api_key' => $apiKey
            ];

            $res = sms_http_request($url, ['Content-Type: application/json', 'Accept: application/json'], $payload, 'POST');
            $isSuccess = ($res['http_code'] === 200) && (isset($res['data']['message_id']) || (isset($res['data']['message']) && str_contains(strtolower((string)$res['data']['message']), 'successfully')));

            if ($isSuccess) {
                $ref = $res['data']['message_id'] ?? ('TRM-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $res['response'], $res['http_code']);
            }

            $raw = $res['data']['message'] ?? $res['response'];
            if (sms_gateway_is_out_of_credit((string)$raw, $res['http_code'])) {
                $pdo->prepare("UPDATE sms_gateways SET last_balance_check = '0 Units (Low Credit)', last_balance_at = NOW() WHERE id = ?")->execute([$gw['id']]);
                $errorsList[] = "[{$gwName}] Low balance. Auto-switching to next gateway...";
            } else {
                $errorsList[] = "[{$gwName}] " . $raw;
            }
        }

        // 4. Custom HTTP API Driver
        else {
            $headers = ['Content-Type: application/json', 'Accept: application/json'];
            if ($gw['auth_type'] === 'bearer') {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            } elseif ($gw['auth_type'] === 'apikey_header') {
                $headers[] = 'X-API-KEY: ' . $apiKey;
            }

            $payload = [
                'to' => $normPhone,
                'from' => $senderId,
                'message' => $message,
            ];

            $res = sms_http_request($baseUrl, $headers, $payload, 'POST');
            if ($res['success']) {
                $ref = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $res['response'], $res['http_code']);
            }

            $errorsList[] = "[{$gwName}] " . $res['response'];
        }
    }

    $combinedError = !empty($errorsList) ? implode(' | ', $errorsList) : 'All active SMS gateways failed to deliver message.';
    $fallbackSender = sms_get_global_sender_id($pdo);
    return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'sms', null, 'failed', $fallbackSender, $message, 'failed', null, $combinedError, 500, $combinedError);
}

/**
 * Dispatch a WhatsApp Message via targeted gateway or smart failover routing.
 */
function app_send_whatsapp(string $recipient, string $message, array $options = []): array
{
    $pdo = db();
    sms_gateway_ensure_schema($pdo);

    $normPhone = sms_normalize_phone($recipient);
    if (empty($normPhone)) {
        return ['success' => false, 'error' => 'Invalid recipient phone number for WhatsApp.'];
    }

    // Check targeted gateway
    if (!empty($options['gateway_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE id = ? AND channel_type IN ('whatsapp', 'both') LIMIT 1");
        $stmt->execute([(int) $options['gateway_id']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($gateways)) {
            return ['success' => false, 'error' => 'Selected gateway does not support WhatsApp channel.'];
        }
    } elseif (!empty($options['gateway_key'])) {
        $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE gateway_key = ? AND status = 'active' AND channel_type IN ('whatsapp', 'both') LIMIT 1");
        $stmt->execute([(string) $options['gateway_key']]);
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($gateways)) {
            return ['success' => false, 'error' => "WhatsApp gateway '{$options['gateway_key']}' not found."];
        }
    } else {
        // Retrieve active WhatsApp gateways
        $stmt = $pdo->prepare("
            SELECT * FROM sms_gateways 
            WHERE status = 'active' AND channel_type IN ('whatsapp', 'both')
            ORDER BY is_primary_whatsapp DESC, priority ASC, id ASC
        ");
        $stmt->execute();
        $gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($gateways)) {
        $defaultSender = sms_get_global_sender_id($pdo);
        return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'whatsapp', null, 'none', $defaultSender, $message, 'simulated', null, 'No WhatsApp gateways configured. Simulated locally.', 200);
    }

    $errorsList = [];

    foreach ($gateways as $gw) {
        $gwKey = (string) $gw['gateway_key'];
        $gwName = (string) $gw['name'];

        if ($gw['environment'] === 'simulation') {
            return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'whatsapp', (int)$gw['id'], $gwKey, $gw['sender_id'], $message, 'simulated', 'WA-SIM-' . strtoupper(bin2hex(random_bytes(4))), 'Simulation mode active.', 200);
        }

        $driver = $gw['provider_driver'];
        $rawSender = $options['sender_id'] ?? $gw['sender_id'] ?? sms_get_global_sender_id($pdo);
        $senderId = substr(preg_replace('/[^a-zA-Z0-9]/', '', (string)$rawSender), 0, 11);
        if (empty($senderId)) {
            $senderId = 'NATCODEV';
        }
        $apiKey = $gw['api_key'] ?? '';
        $baseUrl = rtrim((string)$gw['base_url'], '/');

        // Payless WhatsApp Driver
        if ($driver === 'paylessbulksms') {
            $url = str_contains($baseUrl, '/api/v3') ? ($baseUrl . '/whatsapp/send') : ($baseUrl . '/api/v3/whatsapp/send');
            $headers = [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ];
            $payload = [
                'recipient' => $normPhone,
                'sender_id' => $senderId,
                'message' => $message,
            ];

            $res = sms_http_request($url, $headers, $payload, 'POST');
            $isSuccess = ($res['http_code'] >= 200 && $res['http_code'] < 300) && (!isset($res['data']['status']) || $res['data']['status'] === 'success');

            if ($isSuccess) {
                $ref = $res['data']['data']['uid'] ?? ('WA-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'whatsapp', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $res['response'], $res['http_code']);
            }
            $errorsList[] = "[{$gwName}] " . ($res['data']['message'] ?? $res['response']);
        }

        // Custom WhatsApp API / Webhook Driver
        else {
            $headers = ['Content-Type: application/json', 'Accept: application/json'];
            if ($gw['auth_type'] === 'bearer') {
                $headers[] = 'Authorization: Bearer ' . $apiKey;
            } elseif ($gw['auth_type'] === 'apikey_header') {
                $headers[] = 'X-API-KEY: ' . $apiKey;
            }

            $payload = [
                'to' => $normPhone,
                'from' => $senderId,
                'message' => $message,
                'channel' => 'whatsapp'
            ];

            $res = sms_http_request($baseUrl, $headers, $payload, 'POST');
            if ($res['success']) {
                $ref = 'WA-CUST-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
                return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'whatsapp', (int)$gw['id'], $gwKey, $senderId, $message, 'delivered', $ref, $res['response'], $res['http_code']);
            }
            $errorsList[] = "[{$gwName}] " . $res['response'];
        }
    }

    $combinedError = !empty($errorsList) ? implode(' | ', $errorsList) : 'WhatsApp dispatch failed on all available providers.';
    $fallbackSender = sms_get_global_sender_id($pdo);
    return sms_log_dispatch($pdo, $options['user_id'] ?? null, $normPhone, 'whatsapp', null, 'failed', $fallbackSender, $message, 'failed', null, $combinedError, 500, $combinedError);
}

/**
 * Dispatch message with smart channel selection (SMS or WhatsApp).
 */
function app_send_multichannel_message(string $recipient, string $message, string $preferredChannel = 'auto', array $options = []): array
{
    if ($preferredChannel === 'whatsapp') {
        $res = app_send_whatsapp($recipient, $message, $options);
        if ($res['success']) {
            return $res;
        }
        // Fallback to SMS if WhatsApp failed
        return app_send_sms($recipient, $message, $options);
    }

    return app_send_sms($recipient, $message, $options);
}

/**
 * Internal logger function for sms_logs table and app_log_notification.
 */
function sms_log_dispatch(PDO $pdo, ?int $userId, string $recipient, string $channel, ?int $gatewayId, ?string $gatewayKey, ?string $senderId, string $message, string $status, ?string $ref, ?string $response, int $httpCode, ?string $errorMessage = null): array
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs (user_id, recipient, channel, gateway_id, gateway_key, sender_id, message, status, provider_reference, provider_response, http_code, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $recipient,
            $channel,
            $gatewayId,
            $gatewayKey,
            $senderId,
            $message,
            $status,
            $ref,
            $response,
            $httpCode,
            $errorMessage
        ]);
        $logId = (int) $pdo->lastInsertId();

        // Also record in platform core notification log
        if (function_exists('app_log_notification')) {
            app_log_notification(
                $channel,
                $recipient,
                null,
                $message,
                in_array($status, ['delivered', 'sent', 'simulated'], true) ? 'sent' : 'failed',
                $gatewayKey ?: 'sms_gateway',
                $ref ? 'Ref: ' . $ref : $response,
                $errorMessage
            );
        }

        return [
            'success' => in_array($status, ['delivered', 'sent', 'simulated'], true),
            'log_id' => $logId,
            'status' => $status,
            'channel' => $channel,
            'reference' => $ref,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $errorMessage
        ];
    } catch (Throwable $e) {
        error_log('SMS logging failed: ' . $e->getMessage());
        return [
            'success' => in_array($status, ['delivered', 'sent', 'simulated'], true),
            'status' => $status,
            'error' => $errorMessage ?: $e->getMessage()
        ];
    }
}
