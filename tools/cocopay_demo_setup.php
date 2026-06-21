<?php

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function rowId(PDO $pdo, string $table, string $column, string $value): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM `$table` WHERE `$column` = ? LIMIT 1");
    $stmt->execute([$value]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function execute(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

$now = date('Y-m-d H:i:s');
$staffPassword = password_hash('staff123', PASSWORD_BCRYPT);
$userPassword = password_hash('user123', PASSWORD_BCRYPT);

$pdo->beginTransaction();

try {
    $branchId = rowId($pdo, 'branches', 'code', 'MAIN');
    if ($branchId) {
        execute($pdo, "UPDATE branches SET name=?, email=?, mobile=?, phone=?, routing_number=?, swift_code=?, address=?, status=1, updated_at=? WHERE id=?", [
            'Main Branch', 'branch@example.com', '+10000000001', '+10000000002', '100001', 'COCOUS01', 'Local demo branch', $now, $branchId,
        ]);
    } else {
        execute($pdo, "INSERT INTO branches (name, code, email, mobile, phone, routing_number, swift_code, address, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,1,?,?)", [
            'Main Branch', 'MAIN', 'branch@example.com', '+10000000001', '+10000000002', '100001', 'COCOUS01', 'Local demo branch', $now, $now,
        ]);
        $branchId = (int) $pdo->lastInsertId();
    }

    $staffRows = [
        ['Demo Manager', 'manager@example.com', '+10000000011', 1],
        ['Demo Officer', 'officer@example.com', '+10000000012', 0],
    ];

    $staffIds = [];
    foreach ($staffRows as [$name, $email, $mobile, $designation]) {
        $staffId = rowId($pdo, 'branch_staff', 'email', $email);
        if ($staffId) {
            execute($pdo, "UPDATE branch_staff SET name=?, mobile=?, designation=?, address=?, password=?, status=1, updated_at=? WHERE id=?", [
                $name, $mobile, $designation, 'Local demo staff address', $staffPassword, $now, $staffId,
            ]);
        } else {
            execute($pdo, "INSERT INTO branch_staff (name, email, mobile, designation, address, password, status, created_at, updated_at) VALUES (?,?,?,?,?,?,1,?,?)", [
                $name, $email, $mobile, $designation, 'Local demo staff address', $staffPassword, $now, $now,
            ]);
            $staffId = (int) $pdo->lastInsertId();
        }
        $staffIds[] = $staffId;
        execute($pdo, "DELETE FROM assign_branch_staff WHERE staff_id=? AND branch_id=?", [$staffId, $branchId]);
        execute($pdo, "INSERT INTO assign_branch_staff (staff_id, branch_id) VALUES (?,?)", [$staffId, $branchId]);
    }

    $officerId = $staffIds[1];
    $userId = rowId($pdo, 'users', 'username', 'demo_user');
    $address = json_encode([
        'address' => 'Local demo user address',
        'state' => 'Demo State',
        'zip' => '10001',
        'country' => 'United States',
        'city' => 'Demo City',
    ]);
    if ($userId) {
        execute($pdo, "UPDATE users SET branch_id=?, branch_staff_id=?, account_number=?, firstname=?, lastname=?, email=?, country_code=?, mobile=?, balance=?, password=?, address=?, status=1, ev=1, sv=1, kv=1, profile_complete=1, updated_at=? WHERE id=?", [
            $branchId, $officerId, 'VB000000000001', 'Demo', 'User', 'demo.user@example.com', 'US', '+10000000021', 1000, $userPassword, $address, $now, $userId,
        ]);
    } else {
        execute($pdo, "INSERT INTO users (branch_id, branch_staff_id, account_number, firstname, lastname, username, email, country_code, mobile, balance, password, address, status, ev, sv, kv, profile_complete, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,1,1,1,1,?,?)", [
            $branchId, $officerId, 'VB000000000001', 'Demo', 'User', 'demo_user', 'demo.user@example.com', 'US', '+10000000021', 1000, $userPassword, $address, $now, $now,
        ]);
    }

    $planSql = [
        ["loan_plans", "Demo Personal Loan", "INSERT INTO loan_plans (name, minimum_amount, maximum_amount, per_installment, installment_interval, total_installment, instruction, delay_value, fixed_charge, percent_charge, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?)", [100, 5000, 10, 30, 12, 'Demo loan plan for local testing.', 7, 5, 1, $now, $now], "UPDATE loan_plans SET minimum_amount=?, maximum_amount=?, per_installment=?, installment_interval=?, total_installment=?, instruction=?, delay_value=?, fixed_charge=?, percent_charge=?, status=1, updated_at=? WHERE id=?"],
        ["dps_plans", "Demo Monthly Savings", "INSERT INTO dps_plans (name, per_installment, installment_interval, total_installment, interest_rate, final_amount, delay_value, fixed_charge, percent_charge, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,?,?)", [50, 30, 12, 5, 630, 7, 1, 0, $now, $now], "UPDATE dps_plans SET per_installment=?, installment_interval=?, total_installment=?, interest_rate=?, final_amount=?, delay_value=?, fixed_charge=?, percent_charge=?, status=1, updated_at=? WHERE id=?"],
        ["fdr_plans", "Demo Fixed Deposit", "INSERT INTO fdr_plans (name, minimum_amount, maximum_amount, installment_interval, interest_rate, locked_days, status, created_at, updated_at) VALUES (?,?,?,?,?,?,1,?,?)", [100, 10000, 30, 8, 180, $now, $now], "UPDATE fdr_plans SET minimum_amount=?, maximum_amount=?, installment_interval=?, interest_rate=?, locked_days=?, status=1, updated_at=? WHERE id=?"],
    ];

    foreach ($planSql as [$table, $name, $insert, $insertParams, $update]) {
        $id = rowId($pdo, $table, 'name', $name);
        if ($id) {
            execute($pdo, $update, array_merge(array_slice($insertParams, 0, -2), [$now, $id]));
        } else {
            execute($pdo, $insert, array_merge([$name], $insertParams));
        }
    }

    $bankId = rowId($pdo, 'other_banks', 'name', 'Demo External Bank');
    if ($bankId) {
        execute($pdo, "UPDATE other_banks SET minimum_limit=?, maximum_limit=?, daily_maximum_limit=?, monthly_maximum_limit=?, daily_total_transaction=?, monthly_total_transaction=?, fixed_charge=?, percent_charge=?, processing_time=?, instruction=?, status=1, form_id=0, updated_at=? WHERE id=?", [
            10, 5000, 1000, 20000, 10, 100, 1, 0.5, '1 business day', 'Demo external bank transfer route.', $now, $bankId,
        ]);
    } else {
        execute($pdo, "INSERT INTO other_banks (name, minimum_limit, maximum_limit, daily_maximum_limit, monthly_maximum_limit, daily_total_transaction, monthly_total_transaction, fixed_charge, percent_charge, processing_time, instruction, status, form_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,0,?,?)", [
            'Demo External Bank', 10, 5000, 1000, 20000, 10, 100, 1, 0.5, '1 business day', 'Demo external bank transfer route.', $now, $now,
        ]);
    }

    $withdrawId = rowId($pdo, 'withdraw_methods', 'name', 'Demo Bank Withdrawal');
    if ($withdrawId) {
        execute($pdo, "UPDATE withdraw_methods SET form_id=0, min_limit=?, max_limit=?, fixed_charge=?, rate=?, percent_charge=?, currency=?, description=?, status=1, updated_at=? WHERE id=?", [
            10, 5000, 1, 1, 0.5, 'USD', 'Demo withdrawal method for local testing.', $now, $withdrawId,
        ]);
    } else {
        execute($pdo, "INSERT INTO withdraw_methods (form_id, name, min_limit, max_limit, fixed_charge, rate, percent_charge, currency, description, status, created_at, updated_at) VALUES (0,?,?,?,?,?,?,?,?,1,?,?)", [
            'Demo Bank Withdrawal', 10, 5000, 1, 1, 0.5, 'USD', 'Demo withdrawal method for local testing.', $now, $now,
        ]);
    }

    $manualCode = 1000;
    $gatewayId = rowId($pdo, 'gateways', 'alias', 'manual_bank_deposit');
    if ($gatewayId) {
        execute($pdo, "UPDATE gateways SET form_id=0, code=?, name=?, status=1, gateway_parameters=?, supported_currencies=?, crypto=0, description=?, updated_at=? WHERE id=?", [
            $manualCode, 'Manual Bank Deposit', json_encode([]), json_encode([]), 'Local manual bank deposit for demo testing.', $now, $gatewayId,
        ]);
    } else {
        execute($pdo, "INSERT INTO gateways (form_id, code, name, alias, status, gateway_parameters, supported_currencies, crypto, description, created_at, updated_at) VALUES (0,?,?,?,?,?,?,0,?,?,?)", [
            $manualCode, 'Manual Bank Deposit', 'manual_bank_deposit', 1, json_encode([]), json_encode([]), 'Local manual bank deposit for demo testing.', $now, $now,
        ]);
    }

    execute($pdo, "DELETE FROM gateway_currencies WHERE method_code=?", [$manualCode]);
    execute($pdo, "INSERT INTO gateway_currencies (name, currency, symbol, method_code, gateway_alias, min_amount, max_amount, percent_charge, fixed_charge, rate, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", [
        'Manual Bank Deposit', 'USD', '$', $manualCode, 'manual_bank_deposit', 10, 10000, 0, 0, 1, $now, $now,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "COCOPAY_DEMO_SETUP_OK\n";
echo "branch_id={$branchId}\n";
echo "staff_password=staff123\n";
echo "user_username=demo_user\n";
echo "user_password=user123\n";
