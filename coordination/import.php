<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';
require_once __DIR__ . '/../lib/admin-user-import.php';

/**
 * Scoped member import for coordinators.
 *
 * A coordinator imports members into their own
 * enclave. Every row is stamped with the
 * coordinator's assigned scope (state / LGA /
 * district / village), so a member can never be
 * registered outside the coordinator's enclave,
 * and rows naming an out-of-enclave value are
 * skipped rather than silently re-scoped.
 *
 * @return array{state_id: int, lga_id: int, district: ?string, village: ?string, error: string}
 */
function coord_import_stamp(PDO $pdo, array $user, array $record): array
{
    $role = coord_current_workspace_role($pdo, $user);
    $tierScope = coord_scope_type($role);
    $stamp = ['state_id' => 0, 'lga_id' => 0, 'district' => null, 'village' => null, 'error' => ''];

    // State and regional coordinators: the row's
    // state must fall inside the assigned states.
    if ($tierScope === 'state') {
        $states = coord_assigned_states($pdo, $user);
        if (!$states) {
            $stamp['error'] = 'No state is assigned to this coordinator.';
            return $stamp;
        }
        $csvState = trim((string) ($record['state'] ?? ''));
        $matchedState = null;
        if ($csvState !== '') {
            foreach ($states as $stateName) {
                $resolved = coord_resolve_state($pdo, (string) $stateName);
                $candidates = array_merge([(string) $stateName, (string) $resolved['state_name']], (array) $resolved['aliases']);
                foreach ($candidates as $candidate) {
                    if ($candidate !== '' && strcasecmp($csvState, (string) $candidate) === 0) {
                        $matchedState = $stateName;
                        break 2;
                    }
                }
            }
        }
        if ($matchedState === null) {
            if ($role === 'regional_coordinator') {
                $stamp['error'] = 'Row state "' . $csvState . '" is outside your assigned region.';
                return $stamp;
            }
            // A single-state coordinator's rows are always
            // stamped with their own state.
            $matchedState = $states[0];
        }
        $resolved = coord_resolve_state($pdo, (string) $matchedState);
        $stamp['state_id'] = (int) $resolved['id'];
        $csvLga = trim((string) ($record['lga'] ?? ''));
        if ($csvLga !== '' && $stamp['state_id'] > 0) {
            $lgaStmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE state_id = ? AND LOWER(lga_name) = LOWER(?) LIMIT 1');
            $lgaStmt->execute([$stamp['state_id'], $csvLga]);
            $stamp['lga_id'] = (int) ($lgaStmt->fetchColumn() ?: 0);
        }
        $stamp['district'] = trim((string) ($record['district'] ?? '')) ?: null;
        $stamp['village'] = trim((string) ($record['village'] ?? '')) ?: null;
        return $stamp;
    }

    // LGA coordinators: the row's LGA must fall
    // inside the assigned LGAs; its state is
    // resolved from the LGA reference table.
    if ($tierScope === 'lga') {
        $lgas = coord_assigned_scopes($pdo, $user, 'lga');
        if (!$lgas) {
            $stamp['error'] = 'No LGA is assigned to this coordinator.';
            return $stamp;
        }
        $csvLga = trim((string) ($record['lga'] ?? ''));
        $matchedLga = null;
        if ($csvLga !== '') {
            foreach ($lgas as $lgaName) {
                if (strcasecmp($csvLga, (string) $lgaName) === 0) {
                    $matchedLga = $lgaName;
                    break;
                }
            }
        }
        if ($matchedLga === null) {
            if (count($lgas) > 1) {
                $stamp['error'] = 'Row LGA "' . $csvLga . '" is outside your assigned LGAs.';
                return $stamp;
            }
            $matchedLga = $lgas[0];
        }
        $lgaStmt = $pdo->prepare('SELECT id, state_id FROM nigeria_lgas WHERE LOWER(lga_name) = LOWER(?) LIMIT 1');
        $lgaStmt->execute([(string) $matchedLga]);
        $lgaRow = $lgaStmt->fetch();
        if ($lgaRow) {
            $stamp['lga_id'] = (int) $lgaRow['id'];
            $stamp['state_id'] = (int) $lgaRow['state_id'];
        }
        $stamp['district'] = trim((string) ($record['district'] ?? '')) ?: null;
        $stamp['village'] = trim((string) ($record['village'] ?? '')) ?: null;
        return $stamp;
    }

    // District and village coordinators: the row's
    // enclave value must match the assigned value;
    // state/LGA context comes from the row or the
    // coordinator's own staff profile.
    $enclaves = coord_assigned_scopes($pdo, $user, $tierScope);
    if (!$enclaves) {
        $stamp['error'] = 'No ' . $tierScope . ' is assigned to this coordinator.';
        return $stamp;
    }
    $csvEnclave = trim((string) ($record[$tierScope] ?? ''));
    $matched = null;
    if ($csvEnclave !== '') {
        foreach ($enclaves as $enclaveName) {
            if (strcasecmp($csvEnclave, (string) $enclaveName) === 0) {
                $matched = $enclaveName;
                break;
            }
        }
    }
    if ($matched === null) {
        if (count($enclaves) > 1) {
            $stamp['error'] = 'Row ' . $tierScope . ' "' . $csvEnclave . '" is outside your assigned enclave.';
            return $stamp;
        }
        $matched = $enclaves[0];
    }
    $stamp[$tierScope] = $matched;

    $stateContexts = coord_assigned_scopes($pdo, $user, 'state');
    $lgaContexts = coord_assigned_scopes($pdo, $user, 'lga');
    $stateName = trim((string) ($record['state'] ?? '')) ?: ($stateContexts[0] ?? '');
    if ($stateName !== '') {
        $resolved = coord_resolve_state($pdo, $stateName);
        $stamp['state_id'] = (int) $resolved['id'];
    }
    $lgaName = trim((string) ($record['lga'] ?? '')) ?: ($lgaContexts[0] ?? '');
    if ($lgaName !== '' && $stamp['state_id'] > 0) {
        $lgaStmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE state_id = ? AND LOWER(lga_name) = LOWER(?) LIMIT 1');
        $lgaStmt->execute([$stamp['state_id'], $lgaName]);
        $stamp['lga_id'] = (int) ($lgaStmt->fetchColumn() ?: 0);
    }
    if ($tierScope === 'district') {
        $stamp['village'] = trim((string) ($record['village'] ?? '')) ?: null;
    } else {
        $stamp['district'] = trim((string) ($record['district'] ?? '')) ?: null;
    }
    return $stamp;
}

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$tier = (string) ($scope['tier'] ?? 'national');
$enclaveLabel = (string) ($scope['label'] ?: 'National');

$error = '';
$message = '';
$summary = null;

// National coordinators and admins manage imports
// through the operations console; this surface is
// for enclave coordinators importing their own members.
if ($tier === 'national') {
    $error = 'National coordinators import members through the operations import console (admin/import-users.php).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tier !== 'national') {
    if (!app_check_rate_limit('coordinator_import', 20, 900)) {
        $error = 'Too many import attempts. Please try again in 15 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } elseif (!empty($scope['missing'])) {
        $error = 'An operator must assign your ' . $tier . ' enclave before you can import members.';
    } else {
        try {
            $upload = app_uploaded_file_info((array) ($_FILES['member_upload'] ?? []), ['csv', 'xlsx'], 15 * 1024 * 1024, 'Member spreadsheet');
            $rows = admin_import_mapped_rows($upload['tmp_name'], $upload['name']);
            $batch = 'BATCH-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $recordStmt = $pdo->prepare("
                INSERT INTO user_import_records
                    (batch_ref, source_file, source_row, name, email, phone, phone_e164, role, address, farm_size, state, lga, district, village, scope_type, scope_value, status, status_note, application_id, engagement_token, engagement_channel, engagement_deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $failures = [];

            foreach ($rows as $row) {
                $rowNumber = (int) ($row['_row_number'] ?? 0);
                $record = [
                    'name' => admin_import_value($row, ['name', 'full name', 'applicant name']),
                    'email' => (admin_import_emails(admin_import_value($row, ['email', 'email address', 'e-mail'])))[0] ?? '',
                    'phone' => (admin_import_phones(admin_import_value($row, ['phone', 'phone number', 'mobile', 'telephone', 'whatsapp'])))[0] ?? '',
                    'address' => admin_import_value($row, ['address', 'location', 'farm location', 'residential address']),
                    'farm_size' => admin_import_decimal(admin_import_value($row, ['number of acres hectares', 'number of acres/hectares', 'farm size', 'acreage', 'hectares', 'acres'])),
                    'state' => admin_import_value($row, ['state']),
                    'lga' => admin_import_value($row, ['lga', 'local government', 'local government area']),
                    'district' => admin_import_value($row, ['district', 'district / ward', 'ward']),
                    'village' => admin_import_value($row, ['village', 'community', 'village / community']),
                ];
                $record['phone_e164'] = admin_import_phone_e164($record['phone']);
                $token = bin2hex(random_bytes(32));
                $status = 'skipped';
                $note = '';
                $applicationId = null;

                try {
                    if ($record['name'] === '' || $record['email'] === '') {
                        $note = 'Members require a full name and a valid email address.';
                        $skipped++;
                    } elseif (!filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                        $note = 'Member email address is not valid.';
                        $skipped++;
                    } elseif ($record['phone_e164'] !== '' && admin_import_phone_exists($pdo, $record['phone_e164'])) {
                        $note = 'Duplicate phone number already exists in applications or imports.';
                        $skipped++;
                    } else {
                        $stamp = coord_import_stamp($pdo, $user, $record);
                        if ($stamp['error'] !== '') {
                            $note = $stamp['error'];
                            $skipped++;
                        } else {
                            $applicationId = admin_import_insert_application($pdo, $record, $token);
                            if ($applicationId !== null) {
                                // Stamp the coordinator's enclave onto the
                                // member's registry record: the member
                                // lands inside the coordinator's scope.
                                $pdo->prepare("UPDATE applications
                                    SET state_id = COALESCE(NULLIF(?, 0), state_id),
                                        lga_id = COALESCE(NULLIF(?, 0), lga_id),
                                        district = COALESCE(?, district),
                                        village = COALESCE(?, village)
                                    WHERE id = ?")->execute([
                                    $stamp['state_id'],
                                    $stamp['lga_id'],
                                    $stamp['district'],
                                    $stamp['village'],
                                    $applicationId,
                                ]);
                                admin_import_send_confirmation($pdo, $applicationId, true, $record['phone_e164'] !== '', $record['phone_e164'] !== '');
                                $status = 'pending_engagement';
                                $note = 'Confirmation link sent; member registered in the ' . $tier . ' enclave "' . $enclaveLabel . '".';
                                $imported++;
                            } else {
                                $note = 'Member application could not be created.';
                                $failed++;
                            }
                        }
                    }
                } catch (Throwable $rowError) {
                    $status = 'failed';
                    $note = 'Row failed: ' . mb_substr($rowError->getMessage(), 0, 450);
                    $failed++;
                    $failures[] = 'Row ' . $rowNumber . ': ' . $rowError->getMessage();
                    error_log("Coordinator import {$batch} row {$rowNumber} failed: " . $rowError->getMessage());
                }

                $recordStmt->execute([
                    $batch,
                    $upload['name'],
                    $rowNumber,
                    $record['name'] ?: null,
                    $record['email'] ?: null,
                    $record['phone'] ?: null,
                    $record['phone_e164'] ?: null,
                    'grower',
                    $record['address'] ?: null,
                    $record['farm_size'],
                    $stamp['state_id'] > 0 ? (string) $stamp['state_id'] : ($record['state'] ?: null),
                    $stamp['lga_id'] > 0 ? (string) $stamp['lga_id'] : ($record['lga'] ?: null),
                    $stamp['district'],
                    $stamp['village'],
                    $tier === 'state' ? 'state' : $tier,
                    $enclaveLabel,
                    $status,
                    $note,
                    $applicationId,
                    $status === 'pending_engagement' ? $token : null,
                    $record['phone_e164'] !== '' ? 'phone' : 'email',
                    $status === 'pending_engagement' ? date('Y-m-d H:i:s', strtotime('+14 days')) : null,
                ]);
            }

            $summary = ['batch' => $batch, 'total' => count($rows), 'imported' => $imported, 'skipped' => $skipped, 'failed' => $failed, 'failures' => $failures];
            $message = "Import {$batch} captured {$summary['total']} rows: {$imported} members registered in your enclave, {$skipped} skipped, {$failed} failed.";
            if (function_exists('admin_audit_event')) {
                admin_audit_event($pdo, 'coordinator_import_batch', 'user_import', null, null, ['batch' => $batch, 'rows' => count($rows), 'imported' => $imported, 'tier' => $tier, 'enclave' => $enclaveLabel], $failed === 0 ? 'ok' : 'partial', 'Coordinator import of ' . count($rows) . ' member rows into ' . $tier . ' enclave ' . $enclaveLabel);
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

coord_header('Import Members', 'Register members into your assigned ' . $tier . ' enclave. Each member receives a confirmation link; their record is scoped to "' . $enclaveLabel . '".', $user, 'operations');
?>
<style>.imp-table{width:100%;border-collapse:collapse}.imp-table th,.imp-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem}.imp-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}</style>
<?php if ($error): ?><div class="card" style="border-color:#fda29b;background:#fff1f2"><h2>Import Blocked</h2><p><?= e($error) ?></p></div><?php endif; ?>
<?php if ($message): ?><div class="card" style="border-color:#abefc6;background:#ecfdf3"><h2>Import Complete</h2><p><?= e($message) ?></p></div><?php endif; ?>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst($tier)) ?> Assignment Required</h2><p>An operator must assign your <?= e($tier) ?> enclave in RBAC before member imports are enabled.</p></div><?php endif; ?>
<?php if ($tier !== 'national' && empty($scope['missing'])): ?>
<section class="card">
  <h2>Import Members Into <?= e($enclaveLabel) ?></h2>
  <p style="color:var(--muted)">Upload a CSV or XLSX with a header row. Required columns: <code>Name</code>, <code>Email</code>. Optional: <code>Phone</code>, <code>Farm Size</code>, <code>Address</code>, <code>State</code>, <code>LGA</code>, <code>District</code>, <code>Village</code>. Rows are stamped with your assigned <?= e($tier) ?> scope — a member can never be registered outside your enclave, and rows naming an out-of-enclave value are skipped.</p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Member spreadsheet (CSV / XLSX, max 15MB)<input type="file" name="member_upload" accept=".csv,.xlsx" required></label>
    <p style="color:var(--muted);font-size:.85rem">Each member receives an engagement confirmation link by email (and SMS/WhatsApp when a phone is provided). Once they confirm, their platform account is created inside your enclave.</p>
    <button class="btn" type="submit"><i class="fas fa-file-import"></i> Import Members</button>
  </form>
</section>
<?php endif; ?>
<?php if ($summary): ?>
<section class="card" style="margin-top:16px">
  <h2>Batch Summary — <?= e($summary['batch']) ?></h2>
  <div class="kpis">
    <div class="kpi"><i class="fas fa-users"></i><b><?= number_format($summary['total']) ?></b><span>Rows in file</span></div>
    <div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($summary['imported']) ?></b><span>Registered in enclave</span></div>
    <div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($summary['skipped']) ?></b><span>Skipped</span></div>
    <div class="kpi"><i class="fas fa-triangle-exclamation"></i><b><?= number_format($summary['failed']) ?></b><span>Failed</span></div>
  </div>
  <?php if ($summary['failures']): ?><h3 style="margin-top:14px">Failures</h3><div class="list"><?php foreach (array_slice($summary['failures'], 0, 20) as $failure): ?><div class="row"><span><small><?= e($failure) ?></small></span></div><?php endforeach; ?></div><?php endif; ?>
  <p style="margin-top:14px"><a class="btn light" href="operations.php"><i class="fas fa-list-check"></i> Back to Operations</a></p>
</section>
<?php endif; ?>
<?php coord_footer(); ?>
