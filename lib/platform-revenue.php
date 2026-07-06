<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function revenue_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_fee_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_key VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            revenue_stream VARCHAR(60) NOT NULL,
            applies_to VARCHAR(80) NOT NULL DEFAULT 'all',
            fee_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
            percentage_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
            fixed_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            minimum_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
            maximum_fee DECIMAL(14,2) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_platform_fee_rules_stream (revenue_stream, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'platform_fee_rules');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_revenue_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY,
            revenue_ref VARCHAR(100) NOT NULL UNIQUE,
            source_module VARCHAR(60) NOT NULL,
            source_type VARCHAR(80) NOT NULL,
            source_id INT NULL,
            user_id INT NULL,
            seller_id INT NULL,
            gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            revenue_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            net_payable_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
            rule_key VARCHAR(80) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'earned',
            description VARCHAR(255) NULL,
            metadata_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_platform_revenue_source (source_module, source_type, source_id),
            INDEX idx_platform_revenue_status (status, created_at),
            INDEX idx_platform_revenue_seller (seller_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'platform_revenue_ledger');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seller_subscription_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_key VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            monthly_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
            annual_fee DECIMAL(14,2) NOT NULL DEFAULT 0,
            product_limit INT NOT NULL DEFAULT 25,
            promotion_credits INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            features_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_seller_plans_active (is_active, monthly_fee)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'seller_subscription_plans');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS seller_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            user_id INT NULL,
            plan_id INT NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
            amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL,
            expires_at DATETIME NULL,
            payment_reference VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_seller_subscription_status (status, expires_at),
            INDEX idx_seller_subscription_seller (seller_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'seller_subscriptions');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_promotion_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            listing_id INT NULL,
            title VARCHAR(160) NOT NULL,
            promotion_type VARCHAR(60) NOT NULL DEFAULT 'featured_listing',
            budget_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            platform_fee_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            admin_note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_market_promotions_status (status, starts_at, ends_at),
            INDEX idx_market_promotions_seller (seller_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_promotion_campaigns');

    if (function_exists('app_add_column_if_missing')) {
        app_add_column_if_missing($pdo, 'marketplace_orders', 'platform_fee_amount', "DECIMAL(14,2) NOT NULL DEFAULT 0");
        app_add_column_if_missing($pdo, 'marketplace_orders', 'seller_net_amount', "DECIMAL(14,2) NOT NULL DEFAULT 0");
        app_add_column_if_missing($pdo, 'marketplace_orders', 'platform_fee_rule', "VARCHAR(80) NULL");
        app_add_column_if_missing($pdo, 'marketplace_orders', 'payout_status', "VARCHAR(40) NOT NULL DEFAULT 'pending'");
    }

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO platform_fee_rules
            (rule_key, title, revenue_stream, applies_to, fee_type, percentage_rate, fixed_amount, minimum_fee, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach (revenue_default_fee_rules() as $rule) {
        $stmt->execute([
            $rule['rule_key'],
            $rule['title'],
            $rule['revenue_stream'],
            $rule['applies_to'],
            $rule['fee_type'],
            $rule['percentage_rate'],
            $rule['fixed_amount'],
            $rule['minimum_fee'],
            $rule['notes'],
        ]);
    }
    $planStmt = $pdo->prepare("
        INSERT IGNORE INTO seller_subscription_plans
            (plan_key, title, monthly_fee, annual_fee, product_limit, promotion_credits, features_json)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach (revenue_default_seller_plans() as $plan) {
        $planStmt->execute([
            $plan['plan_key'],
            $plan['title'],
            $plan['monthly_fee'],
            $plan['annual_fee'],
            $plan['product_limit'],
            $plan['promotion_credits'],
            json_encode($plan['features'], JSON_UNESCAPED_SLASHES),
        ]);
    }
}

function revenue_default_fee_rules(): array
{
    return [
        [
            'rule_key' => 'marketplace_commission_default',
            'title' => 'Marketplace Default Commission',
            'revenue_stream' => 'marketplace_commission',
            'applies_to' => 'marketplace_order',
            'fee_type' => 'percentage',
            'percentage_rate' => 5.0000,
            'fixed_amount' => 0,
            'minimum_fee' => 0,
            'notes' => 'Default NATCODEV commission on completed marketplace orders.',
        ],
        [
            'rule_key' => 'buyer_checkout_service_fee',
            'title' => 'Buyer Checkout Service Fee',
            'revenue_stream' => 'buyer_service_fee',
            'applies_to' => 'marketplace_checkout',
            'fee_type' => 'fixed',
            'percentage_rate' => 0,
            'fixed_amount' => 500,
            'minimum_fee' => 0,
            'notes' => 'Buyer-facing checkout service fee currently calculated in marketplace checkout.',
        ],
        [
            'rule_key' => 'withdrawal_processing_fee',
            'title' => 'Withdrawal Processing Fee',
            'revenue_stream' => 'wallet_processing_fee',
            'applies_to' => 'withdrawal',
            'fee_type' => 'fixed',
            'percentage_rate' => 0,
            'fixed_amount' => 100,
            'minimum_fee' => 0,
            'notes' => 'Optional transparent wallet withdrawal processing fee.',
        ],
    ];
}


function revenue_default_seller_plans(): array
{
    return [
        [
            'plan_key' => 'seller_starter',
            'title' => 'Seller Starter',
            'monthly_fee' => 0,
            'annual_fee' => 0,
            'product_limit' => 25,
            'promotion_credits' => 0,
            'features' => ['standard_storefront', 'manual_promotions', 'wallet_payouts'],
        ],
        [
            'plan_key' => 'seller_growth',
            'title' => 'Seller Growth',
            'monthly_fee' => 5000,
            'annual_fee' => 50000,
            'product_limit' => 150,
            'promotion_credits' => 4,
            'features' => ['larger_catalog', 'featured_store_queue', 'promotion_credits', 'sales_insights'],
        ],
        [
            'plan_key' => 'seller_enterprise',
            'title' => 'Seller Enterprise',
            'monthly_fee' => 25000,
            'annual_fee' => 250000,
            'product_limit' => 1000,
            'promotion_credits' => 20,
            'features' => ['priority_review', 'bulk_catalog', 'campaign_support', 'advanced_reports'],
        ],
    ];
}
function revenue_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function revenue_active_rule(PDO $pdo, string $ruleKey): ?array
{
    revenue_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM platform_fee_rules WHERE rule_key = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$ruleKey]);
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    return $rule ?: null;
}

function revenue_calculate_fee(float $gross, ?array $rule): float
{
    if (!$rule || $gross <= 0) {
        return 0.0;
    }
    $fee = (string) ($rule['fee_type'] ?? '') === 'fixed'
        ? (float) ($rule['fixed_amount'] ?? 0)
        : $gross * ((float) ($rule['percentage_rate'] ?? 0) / 100);
    $fee = max($fee, (float) ($rule['minimum_fee'] ?? 0));
    if ($rule['maximum_fee'] !== null && $rule['maximum_fee'] !== '') {
        $fee = min($fee, (float) $rule['maximum_fee']);
    }
    return round(min($fee, $gross), 2);
}

function revenue_record_once(PDO $pdo, array $data): array
{
    revenue_ensure_schema($pdo);
    $sourceModule = (string) ($data['source_module'] ?? 'platform');
    $sourceType = (string) ($data['source_type'] ?? 'manual');
    $sourceId = (int) ($data['source_id'] ?? 0);
    $ruleKey = (string) ($data['rule_key'] ?? '');
    $ref = (string) ($data['revenue_ref'] ?? ('REV-' . strtoupper(bin2hex(random_bytes(5)))));

    $exists = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE source_module = ? AND source_type = ? AND source_id = ? AND rule_key = ? LIMIT 1");
    $exists->execute([$sourceModule, $sourceType, $sourceId, $ruleKey]);
    $existing = $exists->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        return $existing;
    }

    $stmt = $pdo->prepare("
        INSERT INTO platform_revenue_ledger
            (revenue_ref, source_module, source_type, source_id, user_id, seller_id, gross_amount, revenue_amount, net_payable_amount, currency, rule_key, status, description, metadata_json)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ref,
        $sourceModule,
        $sourceType,
        $sourceId > 0 ? $sourceId : null,
        !empty($data['user_id']) ? (int) $data['user_id'] : null,
        !empty($data['seller_id']) ? (int) $data['seller_id'] : null,
        round((float) ($data['gross_amount'] ?? 0), 2),
        round((float) ($data['revenue_amount'] ?? 0), 2),
        round((float) ($data['net_payable_amount'] ?? 0), 2),
        (string) ($data['currency'] ?? 'NGN'),
        $ruleKey !== '' ? $ruleKey : null,
        (string) ($data['status'] ?? 'earned'),
        (string) ($data['description'] ?? ''),
        json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES),
    ]);
    $id = (int) $pdo->lastInsertId();
    $row = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE id = ?");
    $row->execute([$id]);
    return $row->fetch(PDO::FETCH_ASSOC) ?: [];
}

function revenue_apply_marketplace_order(PDO $pdo, array $order): array
{
    revenue_ensure_schema($pdo);
    $gross = round((float) ($order['total_amount'] ?? 0), 2);
    $rule = revenue_active_rule($pdo, 'marketplace_commission_default');
    $fee = revenue_calculate_fee($gross, $rule);
    $net = round(max(0, $gross - $fee), 2);
    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId > 0) {
        $pdo->prepare("UPDATE marketplace_orders SET platform_fee_amount = ?, seller_net_amount = ?, platform_fee_rule = ? WHERE id = ?")
            ->execute([$fee, $net, (string) ($rule['rule_key'] ?? 'marketplace_commission_default'), $orderId]);
    }
    $ledger = revenue_record_once($pdo, [
        'revenue_ref' => 'REV-MKT-' . (string) ($order['order_ref'] ?? $orderId),
        'source_module' => 'marketplace',
        'source_type' => 'order_commission',
        'source_id' => $orderId,
        'user_id' => (int) ($order['buyer_user_id'] ?? 0),
        'seller_id' => (int) ($order['seller_id'] ?? 0),
        'gross_amount' => $gross,
        'revenue_amount' => $fee,
        'net_payable_amount' => $net,
        'rule_key' => (string) ($rule['rule_key'] ?? 'marketplace_commission_default'),
        'description' => 'Marketplace commission for order ' . (string) ($order['order_ref'] ?? $orderId),
        'metadata' => ['order_ref' => (string) ($order['order_ref'] ?? ''), 'rule' => $rule],
    ]);
    return ['gross' => $gross, 'fee' => $fee, 'net' => $net, 'ledger' => $ledger];
}

function revenue_apply_marketplace_promotion(PDO $pdo, array $promotion): array
{
    revenue_ensure_schema($pdo);
    $amount = round((float) ($promotion['amount'] ?? 0), 2);
    $promotionId = (int) ($promotion['id'] ?? 0);
    if ($promotionId <= 0 || $amount <= 0) {
        return [];
    }
    return revenue_record_once($pdo, [
        'revenue_ref' => 'REV-PROMO-' . (string) ($promotion['promo_ref'] ?? $promotionId),
        'source_module' => 'marketplace',
        'source_type' => 'promotion_fee',
        'source_id' => $promotionId,
        'user_id' => (int) ($promotion['seller_user_id'] ?? $promotion['user_id'] ?? 0),
        'seller_id' => (int) ($promotion['seller_id'] ?? 0),
        'gross_amount' => $amount,
        'revenue_amount' => $amount,
        'net_payable_amount' => 0,
        'rule_key' => 'marketplace_promotion_fee',
        'description' => 'Marketplace promotion fee for ' . (string) ($promotion['promo_ref'] ?? $promotionId),
        'metadata' => [
            'placement' => (string) ($promotion['placement'] ?? ''),
            'promo_ref' => (string) ($promotion['promo_ref'] ?? ''),
            'status' => (string) ($promotion['status'] ?? ''),
        ],
    ]);
}

function revenue_sync_active_marketplace_promotions(PDO $pdo): int
{
    revenue_ensure_schema($pdo);
    if (!app_table_exists($pdo, 'marketplace_promotions')) {
        return 0;
    }
    $stmt = $pdo->query("
        SELECT p.*, s.user_id seller_user_id
        FROM marketplace_promotions p
        LEFT JOIN marketplace_sellers s ON s.id = p.seller_id
        WHERE p.status = 'active' AND p.amount > 0
        ORDER BY p.approved_at DESC, p.id DESC
        LIMIT 1000
    ");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $promotion) {
        $ledger = revenue_apply_marketplace_promotion($pdo, $promotion);
        if ($ledger) {
            $count++;
        }
    }
    return $count;
}