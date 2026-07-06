<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$type = trim((string) ($_GET['type'] ?? 'growers'));
$validTypes = ['growers', 'providers', 'sellers', 'users', 'pending'];
if (!in_array($type, $validTypes, true)) {
    $type = 'growers';
}

$filename = 'natcodev_registry_' . $type . '_export.csv';
$headers = [];
$rows = [];

if ($type === 'providers') {
    $headers = ['Provider ID', 'Company', 'Contact Person', 'Email', 'Phone', 'Type', 'Status', 'Coverage', 'Created At'];
    $stmt = $pdo->query("SELECT id, company_name, contact_person, email, phone, provider_type, status, coverage_area, created_at FROM provider_registry ORDER BY created_at DESC");
    $rows = (function () use ($stmt): Generator {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['company_name'],
                $row['contact_person'],
                $row['email'],
                $row['phone'],
                $row['provider_type'],
                $row['status'],
                $row['coverage_area'],
                $row['created_at'],
            ];
        }
    })();
} elseif ($type === 'sellers') {
    $headers = ['Seller ID', 'Store Name', 'Email', 'Phone', 'Approval Status', 'Verification Status', 'Location', 'Created At'];
    $stmt = $pdo->query("SELECT id, store_name, email, phone, approval_status, verification_status, location_label, created_at FROM marketplace_sellers ORDER BY created_at DESC");
    $rows = (function () use ($stmt): Generator {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['store_name'],
                $row['email'],
                $row['phone'],
                $row['approval_status'],
                $row['verification_status'],
                $row['location_label'],
                $row['created_at'],
            ];
        }
    })();
} elseif ($type === 'users') {
    $headers = ['User ID', 'Name', 'Email', 'Phone', 'Account Status', 'Role', 'Platform Role', 'Created At'];
    $stmt = $pdo->query("SELECT id, name, email, phone, COALESCE(account_status,'active') account_status, role, platform_role, created_at FROM users ORDER BY created_at DESC");
    $rows = (function () use ($stmt): Generator {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['account_status'],
                $row['role'],
                $row['platform_role'],
                $row['created_at'],
            ];
        }
    })();
} elseif ($type === 'pending') {
    $headers = ['Type', 'Reference', 'Name', 'Email', 'Phone', 'Status', 'Created At'];
    $rows = (function () use ($pdo): Generator {
        $stmt = $pdo->query("SELECT 'Grower' type, app_ref reference, name, email, phone, review_status status, created_at FROM applications WHERE review_status IN ('pending','under_review') OR confirmed = 0 ORDER BY created_at DESC");
        while ($row = $stmt->fetch()) {
            yield [$row['type'], $row['reference'], $row['name'], $row['email'], $row['phone'], $row['status'], $row['created_at']];
        }
        if (app_table_exists($pdo, 'provider_registry')) {
            $stmt2 = $pdo->query("SELECT 'Provider' type, CONCAT('PRV-', id) reference, company_name name, email, phone, status, created_at FROM provider_registry WHERE status IN ('pending_review','under_review','needs_confirmation') ORDER BY created_at DESC");
            while ($row = $stmt2->fetch()) {
                yield [$row['type'], $row['reference'], $row['name'], $row['email'], $row['phone'], $row['status'], $row['created_at']];
            }
        }
        if (app_table_exists($pdo, 'marketplace_sellers')) {
            $stmt3 = $pdo->query("SELECT 'Seller' type, CONCAT('SEL-', id) reference, store_name name, email, phone, approval_status status, created_at FROM marketplace_sellers WHERE approval_status IN ('pending','unverified') ORDER BY created_at DESC");
            while ($row = $stmt3->fetch()) {
                yield [$row['type'], $row['reference'], $row['name'], $row['email'], $row['phone'], $row['status'], $row['created_at']];
            }
        }
    })();
} else {
    $headers = ['ID', 'Reference', 'Name', 'Location', 'Farm Size', 'Phone', 'Email', 'Confirmed', 'Status', 'Applied', 'Confirmed At'];
    $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
    $rows = (function () use ($stmt): Generator {
        while ($row = $stmt->fetch()) {
            yield [
                $row['id'],
                $row['app_ref'],
                $row['name'],
                $row['location'],
                $row['farm_size'],
                $row['phone'],
                $row['email'],
                (int) $row['confirmed'] === 1 ? 'Yes' : 'No',
                $row['review_status'] ?? 'active',
                $row['created_at'],
                $row['confirmed_at'],
            ];
        }
    })();
}

app_export_csv($filename, $headers, $rows);
