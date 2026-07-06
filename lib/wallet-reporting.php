<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function wallet_reporting_ensure_schema(PDO $pdo): void
{
    foreach ([
        "CREATE INDEX idx_wallet_transactions_created_at ON wallet_transactions (created_at)",
        "CREATE INDEX idx_wallet_transactions_status_created ON wallet_transactions (status, created_at)",
        "CREATE INDEX idx_wallet_transactions_provider_created ON wallet_transactions (provider, created_at)",
        "CREATE INDEX idx_wallet_transactions_user_created ON wallet_transactions (user_id, created_at)",
        "CREATE INDEX idx_wallet_withdrawals_status_requested ON wallet_withdrawals (status, requested_at)",
        "CREATE INDEX idx_marketplace_orders_payment_created ON marketplace_orders (payment_status, created_at)",
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_report_daily_summary (
            report_date DATE NOT NULL,
            provider VARCHAR(60) NOT NULL DEFAULT 'manual',
            status VARCHAR(60) NOT NULL DEFAULT 'unknown',
            stakeholder_role VARCHAR(100) NOT NULL DEFAULT 'public_user',
            transactions INT NOT NULL DEFAULT 0,
            inflow DECIMAL(14,2) NOT NULL DEFAULT 0,
            outflow DECIMAL(14,2) NOT NULL DEFAULT 0,
            failed_count INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (report_date, provider, status, stakeholder_role),
            INDEX idx_wallet_report_daily_date (report_date),
            INDEX idx_wallet_report_daily_role (stakeholder_role, report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_report_export_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_ref VARCHAR(80) NOT NULL UNIQUE,
            report_type VARCHAR(60) NOT NULL DEFAULT 'wallet_report',
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'ready',
            file_path VARCHAR(255) NULL,
            requested_by INT NULL,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            INDEX idx_wallet_export_status (status, requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function wallet_reporting_role_case(): string
{
    return "CASE WHEN ms.id IS NOT NULL THEN CONCAT('marketplace_',COALESCE(NULLIF(ms.seller_type,''),'seller')) WHEN pr.id IS NOT NULL THEN CONCAT('provider_',COALESCE(NULLIF(pr.provider_type,''),'general')) ELSE COALESCE(NULLIF(u.platform_role,''),NULLIF(u.role,''),'public_user') END";
}

function wallet_reporting_refresh(PDO $pdo, string $from, string $to): void
{
    wallet_reporting_ensure_schema($pdo);
    $fromDt = DateTime::createFromFormat('Y-m-d', $from) ?: new DateTime('-29 days');
    $toDt = DateTime::createFromFormat('Y-m-d', $to) ?: new DateTime();
    if ($fromDt > $toDt) {
        [$fromDt, $toDt] = [$toDt, $fromDt];
    }
    $span = (int) $fromDt->diff($toDt)->days;
    if ($span > 93) {
        $fromDt = (clone $toDt)->modify('-93 days');
    }
    $from = $fromDt->format('Y-m-d');
    $to = $toDt->format('Y-m-d');

    $pdo->prepare("DELETE FROM wallet_report_daily_summary WHERE report_date BETWEEN ? AND ?")->execute([$from, $to]);
    $roleCase = wallet_reporting_role_case();
    $pdo->prepare("
        INSERT INTO wallet_report_daily_summary
            (report_date, provider, status, stakeholder_role, transactions, inflow, outflow, failed_count)
        SELECT
            DATE(wt.created_at) report_date,
            COALESCE(NULLIF(wt.provider,''),'manual') provider,
            COALESCE(NULLIF(wt.status,''),'unknown') status,
            {$roleCase} stakeholder_role,
            COUNT(*) transactions,
            COALESCE(SUM(CASE WHEN wt.type = 'credit' THEN wt.amount ELSE 0 END),0) inflow,
            COALESCE(SUM(CASE WHEN wt.type IN ('debit','withdrawal') THEN ABS(wt.amount) ELSE 0 END),0) outflow,
            SUM(CASE WHEN wt.status IN ('failed','rejected') THEN 1 ELSE 0 END) failed_count
        FROM wallet_transactions wt
        LEFT JOIN users u ON u.id = wt.user_id
        LEFT JOIN marketplace_sellers ms ON ms.user_id = u.id
        LEFT JOIN provider_registry pr ON pr.user_id = u.id
        WHERE wt.created_at >= ? AND wt.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(wt.created_at), COALESCE(NULLIF(wt.provider,''),'manual'), COALESCE(NULLIF(wt.status,''),'unknown'), {$roleCase}
    ")->execute([$from, $to]);
}

function wallet_reporting_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function wallet_reporting_money(float $value): string
{
    return 'NGN ' . number_format($value, 2);
}
