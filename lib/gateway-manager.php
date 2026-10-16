<?php
declare(strict_types=1);

/**
 * NATCODEV Multi-Gateway Manager & Intelligent Fallback Router
 * Unifies Monnify, Paystack, Fincra, Woven, Korapay, Allawee, and Flutterwave
 * providing automated deposit routing, fallback handling, withdrawal payouts,
 * bank inquiries, and status queries.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/monnify.php';
require_once __DIR__ . '/paystack.php';
require_once __DIR__ . '/gateways/fincra.php';
require_once __DIR__ . '/gateways/woven.php';
require_once __DIR__ . '/gateways/korapay.php';
require_once __DIR__ . '/gateways/allawee.php';
require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/circuit-breaker.php';

/**
 * Returns list of all supported payment gateways and their capability flags
 */
function gateway_supported_providers(): array
{
    return [
        'monnify' => [
            'key' => 'monnify',
            'name' => 'Monnify',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => true,
            'priority' => 1,
        ],
        'paystack' => [
            'key' => 'paystack',
            'name' => 'Paystack',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => false,
            'priority' => 2,
        ],
        'fincra' => [
            'key' => 'fincra',
            'name' => 'Fincra',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => true,
            'priority' => 3,
        ],
        'korapay' => [
            'key' => 'korapay',
            'name' => 'Korapay',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => true,
            'priority' => 4,
        ],
        'woven' => [
            'key' => 'woven',
            'name' => 'Woven Finance',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => true,
            'priority' => 5,
        ],
        'allawee' => [
            'key' => 'allawee',
            'name' => 'Allawee',
            'supports_deposits' => true,
            'supports_withdrawals' => true,
            'supports_bank_resolution' => true,
            'supports_virtual_accounts' => true,
            'priority' => 6,
        ],
        'flutterwave' => [
            'key' => 'flutterwave',
            'name' => 'Flutterwave',
            'supports_deposits' => true,
            'supports_withdrawals' => false,
            'supports_bank_resolution' => false,
            'supports_virtual_accounts' => false,
            'priority' => 7,
        ],
    ];
}

/**
 * Check if a specific gateway is configured with credentials
 */
function gateway_is_configured(string $provider): bool
{
    $p = strtolower(trim($provider));
    return match ($p) {
        'monnify' => monnify_is_configured(),
        'paystack' => paystack_is_configured(),
        'fincra' => fincra_is_configured(),
        'woven' => woven_is_configured(),
        'korapay' => korapay_is_configured(),
        'allawee' => allawee_is_configured(),
        'flutterwave' => (string) app_env('FLUTTERWAVE_SECRET_KEY', '') !== '',
        default => false,
    };
}

/**
 * List all configured and available gateways
 *
 * @param string $capability 'deposits' | 'withdrawals' | 'all'
 */
function gateway_configured_providers(string $capability = 'all'): array
{
    $all = gateway_supported_providers();
    $configured = [];

    foreach ($all as $key => $meta) {
        if (!gateway_is_configured($key)) {
            continue;
        }
        if ($capability === 'deposits' && empty($meta['supports_deposits'])) {
            continue;
        }
        if ($capability === 'withdrawals' && empty($meta['supports_withdrawals'])) {
            continue;
        }
        $configured[$key] = $meta;
    }

    // Sort by priority
    uasort($configured, static fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

    return $configured;
}

/**
 * Return array of configured gateway keys
 */
function gateway_get_configured_gateways(string $capability = 'all'): array
{
    return array_keys(gateway_configured_providers($capability));
}

/**
 * Retrieve bank list from the requested provider with automatic fallback
 */
function gateway_list_banks(?string $provider = null): array
{
    $provider = strtolower(trim((string) $provider));
    $candidates = $provider !== '' && $provider !== 'auto'
        ? [$provider, 'monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee']
        : ['monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee'];

    $errors = [];
    foreach (array_unique($candidates) as $cand) {
        if (!gateway_is_configured($cand)) {
            continue;
        }
        $res = match ($cand) {
            'paystack' => (function_exists('paystack_list_banks') ? paystack_list_banks() : ['success' => false]),
            'monnify' => (function_exists('monnify_list_banks') ? monnify_list_banks() : ['success' => false]),
            'fincra' => fincra_list_banks(),
            'woven' => woven_list_banks(),
            'korapay' => korapay_list_banks(),
            'allawee' => allawee_list_banks(),
            default => ['success' => false, 'error' => 'Unsupported provider: ' . $cand],
        };

        if (!empty($res['success']) && !empty($res['banks'])) {
            $banksList = array_values($res['banks']);
            $out = $banksList;
            $out['success'] = true;
            $out['provider'] = $cand;
            $out['banks'] = $banksList;
            return $out;
        }
        $errors[$cand] = $res['error'] ?? 'No banks returned';
    }

    // Return static verified sandbox Nigerian banks if dev environment
    if (!app_is_production()) {
        $fallbackBanks = [
            ['name' => 'Access Bank', 'code' => '044'],
            ['name' => 'First Bank of Nigeria', 'code' => '011'],
            ['name' => 'Guaranty Trust Bank (GTBank)', 'code' => '058'],
            ['name' => 'United Bank for Africa (UBA)', 'code' => '033'],
            ['name' => 'Zenith Bank', 'code' => '057'],
            ['name' => 'Kuda Microfinance Bank', 'code' => '50211'],
            ['name' => 'Opay (Paycom)', 'code' => '999992'],
            ['name' => 'PalmPay', 'code' => '999991'],
        ];
        $out = $fallbackBanks;
        $out['success'] = true;
        $out['provider'] = 'sandbox_fallback';
        $out['banks'] = $fallbackBanks;
        return $out;
    }

    return ['success' => false, 'error' => 'Unable to retrieve bank list from any configured gateway.', 'details' => $errors];
}

/**
 * Resolve bank account via Monnify API
 */
function monnify_resolve_account(string $bankCode, string $accountNumber): array
{
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }
    $res = monnify_request('GET', '/api/v1/disbursements/account/validate?accountNumber=' . urlencode($accountNumber) . '&bankCode=' . urlencode($bankCode));
    if (!$res['success']) {
        if (!app_is_production()) {
            return [
                'success' => true,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
                'account_name' => 'Sandbox Mock User',
                'provider' => 'monnify',
                'sandbox_fallback' => true,
            ];
        }
        return ['success' => false, 'error' => 'Monnify: ' . (string) ($res['error'] ?? 'unable to resolve that account')];
    }
    $data = $res['data']['responseBody'] ?? [];
    $resolvedName = trim((string) ($data['accountName'] ?? ''));
    if ($resolvedName === '') {
        return ['success' => false, 'error' => 'Monnify could not confirm a name for that account. Check the account number and bank.'];
    }
    return [
        'success' => true,
        'account_number' => (string) ($data['accountNumber'] ?? $accountNumber),
        'account_name' => $resolvedName,
        'bank_name' => (string) ($data['bankName'] ?? ''),
        'bank_code' => (string) ($data['bankCode'] ?? $bankCode),
        'provider' => 'monnify',
    ];
}

/**
 * Resolve bank account details across gateways with automatic fallback
 * Supports both ($provider, $accountNumber, $bankCode) and ($bankCode, $accountNumber, $provider)
 */
function gateway_resolve_bank_account(string $arg1, string $arg2, string $arg3 = ''): array
{
    $knownProviders = ['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee', 'auto', ''];
    if (in_array(strtolower(trim($arg1)), $knownProviders, true)) {
        $provider = strtolower(trim($arg1));
        $accountNumber = preg_replace('/[^0-9]/', '', $arg2);
        $bankCode = trim($arg3);
    } else {
        $bankCode = trim($arg1);
        $accountNumber = preg_replace('/[^0-9]/', '', $arg2);
        $provider = strtolower(trim($arg3));
    }

    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank code and account number are required.'];
    }

    $candidates = $provider !== '' && $provider !== 'auto'
        ? [$provider, 'monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee']
        : ['monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee'];

    $errors = [];
    foreach (array_unique($candidates) as $cand) {
        if (!gateway_is_configured($cand)) {
            continue;
        }

        $res = match ($cand) {
            'paystack' => (function_exists('paystack_resolve_account') ? paystack_resolve_account($bankCode, $accountNumber) : ['success' => false]),
            'monnify' => monnify_resolve_account($bankCode, $accountNumber),
            'fincra' => fincra_resolve_account($bankCode, $accountNumber),
            'woven' => woven_resolve_account($bankCode, $accountNumber),
            'korapay' => korapay_resolve_account($bankCode, $accountNumber),
            'allawee' => allawee_resolve_account($bankCode, $accountNumber),
            default => ['success' => false, 'error' => 'Unsupported provider: ' . $cand],
        };

        if (!empty($res['success']) && !empty($res['account_name'])) {
            $res['resolved_by'] = $cand;
            return $res;
        }
        $errors[$cand] = $res['error'] ?? 'Could not resolve';
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'account_name' => 'Verified Sandbox User',
            'bank_code' => $bankCode,
            'provider' => $provider ?: 'sandbox_fallback',
            'sandbox_fallback' => true,
        ];
    }

    return ['success' => false, 'error' => 'Unable to resolve bank account details.', 'details' => $errors];
}

/**
 * Initialize wallet deposit with seamless fallback routing
 *
 * If the preferred gateway is unavailable or fails to initialize,
 * this function automatically attempts the next configured gateway.
 */
function gateway_initialize_deposit(PDO $pdo, array|int $user, float $amount, ?string $preferredGateway = null, ?string $returnUrl = null): array
{
    wallet_ensure_schema($pdo);
    if (is_int($user)) {
        $userId = $user;
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $userRow = $uStmt->fetch(PDO::FETCH_ASSOC);
        $user = $userRow ?: ['id' => $userId, 'name' => 'User ' . $userId, 'email' => "user{$userId}@natcodev.test"];
    } else {
        $userId = (int) ($user['id'] ?? 0);
    }
    $wallet = wallet_get_or_create($pdo, $userId);

    if ($amount < 100) {
        return ['success' => false, 'error' => 'Minimum deposit amount is NGN 100.'];
    }

    $preferred = strtolower(trim((string) $preferredGateway));
    $candidates = [];

    if ($preferred !== '' && $preferred !== 'auto') {
        $candidates[] = $preferred;
    }

    // Add remaining configured deposit gateways in priority order
    $configured = array_keys(gateway_configured_providers('deposits'));
    foreach ($configured as $gw) {
        if (!in_array($gw, $candidates, true)) {
            $candidates[] = $gw;
        }
    }

    if (empty($candidates)) {
        return ['success' => false, 'error' => 'No payment gateway is currently configured for deposits.'];
    }

    $callbackUrl = $returnUrl ?: app_base_url() . '/dashboard/wallet.php';
    $errors = [];
    $fallbackUsed = false;

    $depositCharge = function_exists('wallet_deposit_charge') ? wallet_deposit_charge($pdo, $amount) : 0.0;
    $totalPayable = round($amount + $depositCharge, 2);

    foreach ($candidates as $index => $provider) {
        if ($index > 0) {
            $fallbackUsed = true;
        }

        $reference = 'NAT-FUND-' . $userId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Record pending transaction in wallet_transactions
        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_payload, status, balance_before, balance_after)
            VALUES (?, ?, ?, 'funding', 'inflow', ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            (int) $wallet['id'],
            $userId,
            $amount,
            'Wallet funding via ' . ucfirst($provider),
            $reference,
            $provider,
            json_encode(['deposit_amount' => $amount, 'charge' => $depositCharge, 'total_payable' => $totalPayable], JSON_UNESCAPED_SLASHES),
            (float) $wallet['balance'],
            (float) $wallet['balance'],
        ]);

        $res = match ($provider) {
            'monnify' => monnify_initialize_wallet_funding($pdo, $user, $totalPayable, $callbackUrl, $reference),
            'paystack' => (function () use ($totalPayable, $userId, $user, $reference, $callbackUrl): array {
                $pRes = paystack_initialize_payment($totalPayable, $userId, (string) ($user['email'] ?? ''), $reference, $callbackUrl);
                return [
                    'success' => !empty($pRes['success']),
                    'payment_url' => $pRes['authorization_url'] ?? '',
                    'reference' => $reference,
                    'error' => $pRes['error'] ?? 'Paystack initialization failed',
                ];
            })(),
            'fincra' => (function () use ($totalPayable, $userId, $user, $reference, $callbackUrl): array {
                $fRes = fincra_initialize_checkout($totalPayable, $userId, (string) ($user['email'] ?? ''), $reference, $callbackUrl, ['name' => (string) ($user['name'] ?? '')]);
                return [
                    'success' => !empty($fRes['success']),
                    'payment_url' => $fRes['authorization_url'] ?? '',
                    'reference' => $reference,
                    'error' => $fRes['error'] ?? 'Fincra initialization failed',
                ];
            })(),
            'woven' => (function () use ($totalPayable, $userId, $user, $reference, $callbackUrl): array {
                $wRes = woven_initialize_checkout($totalPayable, $userId, (string) ($user['email'] ?? ''), $reference, $callbackUrl, ['name' => (string) ($user['name'] ?? '')]);
                return [
                    'success' => !empty($wRes['success']),
                    'payment_url' => $wRes['authorization_url'] ?? '',
                    'reference' => $reference,
                    'error' => $wRes['error'] ?? 'Woven initialization failed',
                ];
            })(),
            'korapay' => (function () use ($totalPayable, $userId, $user, $reference, $callbackUrl): array {
                $kRes = korapay_initialize_checkout($totalPayable, $userId, (string) ($user['email'] ?? ''), $reference, $callbackUrl, ['name' => (string) ($user['name'] ?? '')]);
                return [
                    'success' => !empty($kRes['success']),
                    'payment_url' => $kRes['authorization_url'] ?? '',
                    'reference' => $reference,
                    'error' => $kRes['error'] ?? 'Korapay initialization failed',
                ];
            })(),
            'allawee' => (function () use ($totalPayable, $userId, $user, $reference, $callbackUrl): array {
                $aRes = allawee_initialize_checkout($totalPayable, $userId, (string) ($user['email'] ?? ''), $reference, $callbackUrl, ['name' => (string) ($user['name'] ?? '')]);
                return [
                    'success' => !empty($aRes['success']),
                    'payment_url' => $aRes['authorization_url'] ?? '',
                    'reference' => $reference,
                    'error' => $aRes['error'] ?? 'Allawee initialization failed',
                ];
            })(),
            default => ['success' => false, 'error' => 'Unknown gateway: ' . $provider],
        };

        if (!empty($res['success']) && !empty($res['payment_url'])) {
            return [
                'success' => true,
                'payment_url' => (string) $res['payment_url'],
                'reference' => $reference,
                'provider' => $provider,
                'fallback_used' => $fallbackUsed,
                'amount' => $amount,
                'deposit_amount' => $amount,
                'charge' => $depositCharge,
                'total_payable' => $totalPayable,
            ];
        }

        // Clean up failed pending entry
        $pdo->prepare("UPDATE wallet_transactions SET status = 'failed' WHERE reference = ?")->execute([$reference]);
        $errors[$provider] = $res['error'] ?? 'Payment initialization failed';
    }

    return ['success' => false, 'error' => 'All available payment gateways failed to initialize.', 'details' => $errors];
}

/**
 * Verify and credit a deposit across any supported gateway
 */
function gateway_verify_deposit(PDO $pdo, int $userId, string $reference, ?string $provider = null): array
{
    wallet_ensure_schema($pdo);
    $reference = trim($reference);
    $reference = preg_split('/[?&]/', $reference, 2)[0] ?? $reference;

    if ($reference === '') {
        return ['success' => false, 'error' => 'Missing payment reference'];
    }

    // Resolve transaction and provider from database if not supplied
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$reference, $userId]);
    $tx = $stmt->fetch();

    if ($tx && ($tx['status'] ?? '') === 'completed') {
        return ['success' => true, 'status' => 'completed', 'duplicate' => true, 'credited' => false];
    }

    $provider = $provider ?: (string) ($tx['provider'] ?? 'monnify');
    $expectedAmount = (float) ($tx['amount'] ?? 0);

    return match (strtolower($provider)) {
        'monnify' => monnify_verify_wallet_funding($pdo, $userId, $reference),
        'paystack' => (function () use ($pdo, $userId, $reference, $expectedAmount): array {
            $v = paystack_verify_payment($reference);
            if (!empty($v['success']) && ($v['status'] ?? '') === 'success' && (float) ($v['amount'] ?? 0) + 0.01 >= $expectedAmount) {
                $credit = wallet_credit_once($pdo, $userId, $expectedAmount, 'Wallet funding via Paystack', $reference, 'paystack', $reference, $v);
                return ['success' => true, 'status' => 'completed', 'credited' => true, 'duplicate' => !empty($credit['duplicate'])];
            }
            return ['success' => true, 'status' => $v['status'] ?? 'pending', 'credited' => false];
        })(),
        'fincra' => (function () use ($pdo, $userId, $reference, $expectedAmount): array {
            $v = fincra_verify_payment($reference);
            if (!empty($v['success']) && ($v['status'] ?? '') === 'success' && (float) ($v['amount'] ?? 0) + 0.01 >= $expectedAmount) {
                $credit = wallet_credit_once($pdo, $userId, $expectedAmount, 'Wallet funding via Fincra', $reference, 'fincra', $reference, $v);
                return ['success' => true, 'status' => 'completed', 'credited' => true, 'duplicate' => !empty($credit['duplicate'])];
            }
            return ['success' => true, 'status' => $v['status'] ?? 'pending', 'credited' => false];
        })(),
        'woven' => (function () use ($pdo, $userId, $reference, $expectedAmount): array {
            $v = woven_verify_payment($reference);
            if (!empty($v['success']) && ($v['status'] ?? '') === 'success' && (float) ($v['amount'] ?? 0) + 0.01 >= $expectedAmount) {
                $credit = wallet_credit_once($pdo, $userId, $expectedAmount, 'Wallet funding via Woven', $reference, 'woven', $reference, $v);
                return ['success' => true, 'status' => 'completed', 'credited' => true, 'duplicate' => !empty($credit['duplicate'])];
            }
            return ['success' => true, 'status' => $v['status'] ?? 'pending', 'credited' => false];
        })(),
        'korapay' => (function () use ($pdo, $userId, $reference, $expectedAmount): array {
            $v = korapay_verify_payment($reference);
            if (!empty($v['success']) && ($v['status'] ?? '') === 'success' && (float) ($v['amount'] ?? 0) + 0.01 >= $expectedAmount) {
                $credit = wallet_credit_once($pdo, $userId, $expectedAmount, 'Wallet funding via Korapay', $reference, 'korapay', $reference, $v);
                return ['success' => true, 'status' => 'completed', 'credited' => true, 'duplicate' => !empty($credit['duplicate'])];
            }
            return ['success' => true, 'status' => $v['status'] ?? 'pending', 'credited' => false];
        })(),
        'allawee' => (function () use ($pdo, $userId, $reference, $expectedAmount): array {
            $v = allawee_verify_payment($reference);
            if (!empty($v['success']) && ($v['status'] ?? '') === 'success' && (float) ($v['amount'] ?? 0) + 0.01 >= $expectedAmount) {
                $credit = wallet_credit_once($pdo, $userId, $expectedAmount, 'Wallet funding via Allawee', $reference, 'allawee', $reference, $v);
                return ['success' => true, 'status' => 'completed', 'credited' => true, 'duplicate' => !empty($credit['duplicate'])];
            }
            return ['success' => true, 'status' => $v['status'] ?? 'pending', 'credited' => false];
        })(),
        default => ['success' => false, 'error' => 'Unsupported provider: ' . $provider],
    };
}

/**
 * Initiate transfer withdrawal payout across any supported gateway
 */
function gateway_initiate_withdrawal(array $withdrawal): array
{
    $provider = strtolower((string) ($withdrawal['provider'] ?? 'manual'));
    $pdo = function_exists('db') ? db() : null;

    if ($provider !== 'manual' && $pdo && function_exists('circuit_breaker_is_available')) {
        if (!circuit_breaker_is_available($pdo, $provider)) {
            // Provider is cooling down. Check fallback providers among allowed providers.
            $policy = function_exists('wallet_withdrawal_policy') ? wallet_withdrawal_policy($pdo) : [];
            $allowed = $policy['allowed_providers'] ?? ['monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee'];
            $configured = gateway_get_configured_gateways('withdrawals');
            $fallback = null;
            foreach ($configured as $alt) {
                if ($alt !== $provider && in_array($alt, $allowed, true) && circuit_breaker_is_available($pdo, $alt)) {
                    $fallback = $alt;
                    break;
                }
            }
            if ($fallback !== null) {
                $withdrawal['provider'] = $fallback;
                $provider = $fallback;
            } else {
                return [
                    'success' => false,
                    'circuit_open' => true,
                    'provider' => $provider,
                    'error' => "Payout provider '{$provider}' is in circuit breaker cooldown and no alternative payout gateway is available.",
                ];
            }
        }
    }

    $res = match ($provider) {
        'monnify' => monnify_initiate_wallet_withdrawal($withdrawal),
        'paystack' => paystack_initiate_wallet_withdrawal($withdrawal),
        'fincra' => fincra_initiate_wallet_withdrawal($withdrawal),
        'woven' => woven_initiate_wallet_withdrawal($withdrawal),
        'korapay' => korapay_initiate_wallet_withdrawal($withdrawal),
        'allawee' => allawee_initiate_wallet_withdrawal($withdrawal),
        default => [
            'success' => true,
            'provider' => 'manual',
            'payout_reference' => (string) ($withdrawal['reference'] ?? ''),
            'transfer_code' => '',
            'payout_status' => 'manual_review',
            'payload' => ['note' => 'Manual processing requested'],
        ],
    };

    if (!isset($res['provider'])) {
        $res['provider'] = $provider;
    }

    return $res;
}

/**
 * Query payout status from gateway for reconciliation
 */
function gateway_query_payout_status(array $withdrawal): array
{
    $provider = strtolower((string) ($withdrawal['provider'] ?? ''));

    return match ($provider) {
        'monnify' => (function () use ($withdrawal): array {
            $ref = (string) ($withdrawal['payout_reference'] ?: ($withdrawal['reference'] ?? ''));
            if ($ref === '') {
                return ['state' => 'unknown', 'error' => 'Missing payout reference'];
            }
            $res = monnify_request('GET', '/api/v2/disbursements/single/summary?reference=' . rawurlencode($ref));
            if (!empty($res['success'])) {
                $body = $res['data']['responseBody'] ?? $res['data'] ?? [];
                $rawStatus = strtolower((string) ($body['status'] ?? ''));
                $state = in_array($rawStatus, ['success', 'successful', 'paid'], true) ? 'success'
                    : (in_array($rawStatus, ['failed', 'reversed', 'rejected'], true) ? 'failed' : 'pending');
                return [
                    'state' => $state,
                    'status' => $rawStatus,
                    'reference' => $ref,
                    'amount' => (float) ($body['amount'] ?? 0),
                    'payload' => $body,
                ];
            }
            if (!app_is_production()) {
                return [
                    'state' => 'pending',
                    'status' => 'initiated',
                    'reference' => $ref,
                    'payload' => ['mock' => true, 'response' => $res],
                ];
            }
            return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Monnify payout'];
        })(),
        'paystack' => (function () use ($withdrawal): array {
            $code = (string) ($withdrawal['payout_transfer_code'] ?: ($withdrawal['payout_reference'] ?: ($withdrawal['reference'] ?? '')));
            if ($code === '') {
                return ['state' => 'unknown', 'error' => 'Missing transfer code'];
            }
            $res = paystack_verify_transfer($code);
            if (!empty($res['success'])) {
                $rawStatus = strtolower((string) ($res['status'] ?? ''));
                $state = in_array($rawStatus, ['success', 'successful', 'paid'], true) ? 'success'
                    : (in_array($rawStatus, ['failed', 'reversed', 'rejected', 'abandoned'], true) ? 'failed' : 'pending');
                return [
                    'state' => $state,
                    'status' => $rawStatus,
                    'reference' => (string) ($res['reference'] ?: ($withdrawal['reference'] ?? '')),
                    'amount' => (float) ($res['amount'] ?? 0),
                    'payload' => $res,
                ];
            }
            if (!app_is_production()) {
                return [
                    'state' => 'pending',
                    'status' => 'initiated',
                    'reference' => (string) ($withdrawal['reference'] ?? ''),
                    'payload' => ['mock' => true, 'response' => $res],
                ];
            }
            return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Paystack transfer'];
        })(),
        'fincra' => fincra_verify_transfer((string) ($withdrawal['reference'] ?? '')),
        'woven' => woven_verify_transfer((string) ($withdrawal['reference'] ?? '')),
        'korapay' => korapay_verify_transfer((string) ($withdrawal['reference'] ?? '')),
        'allawee' => allawee_verify_transfer((string) ($withdrawal['reference'] ?? '')),
        default => ['state' => 'unknown'],
    };
}

/**
 * Quick connectivity test for any gateway
 */
function gateway_test_connection(string $provider): array
{
    $provider = strtolower(trim($provider));
    $started = microtime(true);

    if (!gateway_is_configured($provider)) {
        return [
            'success' => false,
            'latency_ms' => 0,
            'error' => ucfirst($provider) . ' is not configured with API credentials.',
        ];
    }

    $probe = match ($provider) {
        'monnify' => monnify_list_banks(),
        'paystack' => paystack_list_banks(),
        'fincra' => fincra_list_banks(),
        'woven' => woven_list_banks(),
        'korapay' => korapay_list_banks(),
        'allawee' => allawee_list_banks(),
        default => ['success' => false, 'error' => 'Unknown gateway: ' . $provider],
    };

    $latency = (int) round((microtime(true) - $started) * 1000);

    return [
        'success' => !empty($probe['success']),
        'latency_ms' => $latency,
        'provider' => $provider,
        'error' => !empty($probe['success']) ? null : (string) ($probe['error'] ?? 'Connection probe failed'),
    ];
}
