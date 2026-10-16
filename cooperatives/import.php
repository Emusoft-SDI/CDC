<?php
declare(strict_types=1);
require_once __DIR__ . '/_cooperative.php';

/**
 * Scoped member import for cooperatives.
 *
 * A cooperative admin imports members into their own
 * cooperative. Every row is stamped with the importing
 * cooperative's identity (resolved through
 * cooperative_match_or_create() from the importing
 * cooperative, never from CSV values), while the row's
 * state / LGA / district / village geography comes from
 * the CSV row. Each imported member becomes an
 * applications row in the grower engagement flow:
 * confirmation link -> confirm_email.php creates the
 * users row with a temporary password, sets
 * users.cooperative_id, and inserts the
 * cooperative_members row (role 'member').
 *
 * @return array{state_id: int, lga_id: int, district: ?string, village: ?string, cooperative_id: int, error: string}
 */
function coop_import_stamp(PDO $pdo, array $cooperative, array $record): array
{
    $stamp = ['state_id' => 0, 'lga_id' => 0, 'district' => null, 'village' => null, 'cooperative_id' => 0, 'error' => ''];

    // The importing cooperative owns every row: match (or
    // create) it by the importing cooperative's own name +
    // registration number, so CSV cooperative columns can
    // never re-point a member at a different cooperative.
    $matched = cooperative_match_or_create(
        $pdo,
        (string) ($cooperative['name'] ?? ''),
        (string) ($cooperative['registration_number'] ?? ''),
        coop_state_name($pdo, (int) ($cooperative['state_id'] ?? 0)),
        (string) ($cooperative['lga'] ?? ''),
        admin_current_user_id($pdo) ?: null
    );
    $stamp['cooperative_id'] = (int) ($matched['cooperative']['id'] ?? 0);
    if ($stamp['cooperative_id'] <= 0) {
        $stamp['error'] = 'Your cooperative could not be resolved for this import.';
        return $stamp;
    }

    // Geography comes from the CSV row, resolved against
    // the state / LGA reference tables when possible.
    $stateName = trim((string) ($record['state'] ?? ''));
    if ($stateName !== '') {
        $stateStmt = $pdo->prepare('SELECT id FROM nigeria_states WHERE LOWER(state_name) = LOWER(?) OR LOWER(state_code) = LOWER(?) LIMIT 1');
        $stateStmt->execute([$stateName, $stateName]);
        $stamp['state_id'] = (int) ($stateStmt->fetchColumn() ?: 0);
    }
    $lgaName = trim((string) ($record['lga'] ?? ''));
    if ($lgaName !== '' && $stamp['state_id'] > 0) {
        $lgaStmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE state_id = ? AND LOWER(lga_name) = LOWER(?) LIMIT 1');
        $lgaStmt->execute([$stamp['state_id'], $lgaName]);
        $stamp['lga_id'] = (int) ($lgaStmt->fetchColumn() ?: 0);
    }
    $stamp['district'] = trim((string) ($record['district'] ?? '')) ?: null;
    $stamp['village'] = trim((string) ($record['village'] ?? '')) ?: null;
    return $stamp;
}

$pdo = coop_pdo();
$user = coop_require($pdo);
$cooperative = cooperative_current($pdo);
$cooperativeId = (int) ($cooperative['id'] ?? 0);
$cooperativeName = (string) ($cooperative['name'] ?? 'Your Cooperative');
$stateName = coop_state_name($pdo, (int) ($cooperative['state_id'] ?? 0));
$lgaName = (string) ($cooperative['lga'] ?? '');

$error = '';
$message = '';
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('cooperative_import', 20, 900)) {
        $error = 'Too many import attempts. Please try again in 15 minutes.';
    } elseif (!verify_csrf((string) ($_POST['_csrf'] ?? null))) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            $upload = app_uploaded_file_info((array) ($_FILES['member_upload'] ?? []), ['csv', 'xlsx'], 15 * 1024 * 1024, 'Member spreadsheet');
            $rows = admin_import_mapped_rows($upload['tmp_name'], $upload['name']);
            $batch = 'BATCH-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $recordStmt = $pdo->prepare("
                INSERT INTO user_import_records
                    (batch_ref, source_file, source_row, name, email, phone, phone_e164, role, address, farm_size, state, lga, district, village, cooperative_name, cooperative_registration_number, scope_type, scope_value, status, status_note, application_id, engagement_token, engagement_channel, engagement_deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $imported = 0;
            $skipped = 0;
            $failed = 0;
            $failures = [];

            foreach ($rows as $row) {
                $rowNumber = (int) ($row['_row_number'] ?? 0);
                $record = [
                    'name' => admin_import_value($row, ['name', 'full name', 'applicant name', 'member name']),
                    'email' => (admin_import_emails(admin_import_value($row, ['email', 'email address', 'e-mail'])))[0] ?? '',
                    'phone' => (admin_import_phones(admin_import_value($row, ['phone', 'phone number', 'mobile', 'telephone', 'whatsapp'])))[0] ?? '',
                    'address' => admin_import_value($row, ['address', 'location', 'farm location', 'residential address']),
                    'farm_size' => admin_import_decimal(admin_import_value($row, ['number of acres hectares', 'number of acres/hectares', 'farm size', 'acreage', 'hectares', 'acres'])),
                    'state' => admin_import_value($row, ['state']),
                    'lga' => admin_import_value($row, ['lga', 'local government', 'local government area']),
                    'district' => admin_import_value($row, ['district', 'district / ward', 'ward']),
                    'village' => admin_import_value($row, ['village', 'community', 'village / community']),
                    'member_type' => admin_import_value($row, ['member type', 'member_type', 'type', 'category']),
                ];
                $record['phone_e164'] = admin_import_phone_e164($record['phone']);
                $token = bin2hex(random_bytes(32));
                $status = 'skipped';
                $note = '';
                $applicationId = null;
                $stamp = ['state_id' => 0, 'lga_id' => 0, 'district' => null, 'village' => null, 'cooperative_id' => 0, 'error' => ''];

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
                        $stamp = coop_import_stamp($pdo, $cooperative, $record);
                        if ($stamp['error'] !== '') {
                            $note = $stamp['error'];
                            $skipped++;
                        } else {
                            $applicationId = admin_import_insert_application($pdo, $record, $token);
                            if ($applicationId !== null) {
                                // Stamp THIS cooperative onto the member's
                                // registry record (CSV cooperative columns are
                                // ignored), plus the row's geography and member
                                // type. confirm_email.php picks up
                                // applications.cooperative_id and links the
                                // member through cooperative_link_member().
                                $pdo->prepare("UPDATE applications
                                    SET cooperative_id = ?,
                                        state_id = COALESCE(NULLIF(?, 0), state_id),
                                        lga_id = COALESCE(NULLIF(?, 0), lga_id),
                                        district = COALESCE(?, district),
                                        village = COALESCE(?, village),
                                        member_type = COALESCE(NULLIF(?, ''), member_type)
                                    WHERE id = ?")->execute([
                                    $stamp['cooperative_id'],
                                    $stamp['state_id'],
                                    $stamp['lga_id'],
                                    $stamp['district'],
                                    $stamp['village'],
                                    $record['member_type'],
                                    $applicationId,
                                ]);
                                admin_import_send_confirmation($pdo, $applicationId, true, $record['phone_e164'] !== '', $record['phone_e164'] !== '');
                                $status = 'pending_engagement';
                                $note = 'Confirmation link sent; member registered under ' . $cooperativeName . '.';
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
                    error_log("Cooperative import {$batch} row {$rowNumber} failed: " . $rowError->getMessage());
                }

                $recordStmt->execute([
                    $batch,
                    $upload['name'],
                    $rowNumber,
                    $record['name'] ?: null,
                    $record['email'] ?: null,
                    $record['phone'] ?: null,
                    $record['phone_e164'] ?: null,
                    'cooperative_member',
                    $record['address'] ?: null,
                    $record['farm_size'],
                    $stamp['state_id'] > 0 ? (string) $stamp['state_id'] : ($record['state'] ?: null),
                    $stamp['lga_id'] > 0 ? (string) $stamp['lga_id'] : ($record['lga'] ?: null),
                    $stamp['district'],
                    $stamp['village'],
                    $cooperativeName,
                    (string) ($cooperative['registration_number'] ?? '') !== '' ? (string) $cooperative['registration_number'] : null,
                    'cooperative',
                    $cooperativeName,
                    $status,
                    $note,
                    $applicationId,
                    $status === 'pending_engagement' ? $token : null,
                    $record['phone_e164'] !== '' ? 'phone' : 'email',
                    $status === 'pending_engagement' ? date('Y-m-d H:i:s', strtotime('+14 days')) : null,
                ]);
            }

            $summary = ['batch' => $batch, 'total' => count($rows), 'imported' => $imported, 'skipped' => $skipped, 'failed' => $failed, 'failures' => $failures];
            $message = "Import {$batch} captured {$summary['total']} rows: {$imported} members registered under {$cooperativeName}, {$skipped} skipped, {$failed} failed.";
            if (function_exists('admin_audit_event')) {
                admin_audit_event(
                    $pdo,
                    'cooperative_import_batch',
                    'user_import',
                    null,
                    [],
                    [
                        'batch' => $batch,
                        'rows' => count($rows),
                        'imported' => $imported,
                        'skipped' => $skipped,
                        'failed' => $failed,
                        'cooperative_id' => $cooperativeId,
                        'cooperative' => $cooperativeName,
                    ],
                    $failed === 0 ? 'success' : 'partial',
                    'Cooperative admin imported ' . count($rows) . ' member rows for ' . $cooperativeName
                );
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

coop_header(
    'Import Members',
    'Register members into ' . $cooperativeName . '. Each member receives a confirmation link; their account is created inside your cooperative.',
    $user,
    'import'
);
?>
<?php if ($error): ?><div class="card" style="border-color:#fda29b;background:#fff1f2"><h2>Import Blocked</h2><p><?= e($error) ?></p></div><?php endif; ?>
<?php if ($message): ?><div class="card" style="border-color:#abefc6;background:#ecfdf3"><h2>Import Complete</h2><p><?= e($message) ?></p></div><?php endif; ?>

<section class="card">
  <h2>Import Members Into <?= e($cooperativeName) ?></h2>
  <p style="color:var(--muted)">
    Upload a CSV or XLSX with a header row. Required columns: <code>Name</code>, <code>Email</code>.
    Optional: <code>Phone</code>, <code>Farm Size</code>, <code>Address</code>, <code>State</code>, <code>LGA</code>,
    <code>District</code>, <code>Village</code>, <code>Member Type</code>.
    Every row is stamped with <strong>your</strong> cooperative (<?= e($cooperativeName) ?><?= $stateName !== '' || $lgaName !== '' ? ' — ' . e(trim($stateName . ($lgaName !== '' ? ' / ' . $lgaName : ''))) : '' ?>): cooperative columns in the file are ignored, and members can only ever land inside this cooperative.
  </p>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label style="display:block;font-weight:900;margin-top:12px">Member spreadsheet (CSV / XLSX, max 15MB)<input type="file" name="member_upload" accept=".csv,.xlsx" required style="width:100%;border:1px solid var(--line);border-radius:8px;padding:11px;margin-top:6px"></label>
    <p style="color:var(--muted);font-size:.85rem">Each member receives an engagement confirmation link by email (and SMS/WhatsApp when a phone is provided). Once they confirm, their platform account is created with a temporary password and linked to this cooperative as a member.</p>
    <button class="btn" type="submit"><i class="fas fa-file-import"></i> Import Members</button>
  </form>
</section>

<?php if ($summary): ?>
<section class="card" style="margin-top:16px">
  <h2>Batch Summary — <?= e($summary['batch']) ?></h2>
  <div class="kpis">
    <div class="kpi"><i class="fas fa-users"></i><b><?= number_format($summary['total']) ?></b><span>Rows in file</span></div>
    <div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($summary['imported']) ?></b><span>Registered in cooperative</span></div>
    <div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($summary['skipped']) ?></b><span>Skipped</span></div>
    <div class="kpi"><i class="fas fa-triangle-exclamation"></i><b><?= number_format($summary['failed']) ?></b><span>Failed</span></div>
    <div class="kpi"><i class="fas fa-building-columns"></i><b><?= e($cooperativeName) ?></b><span>Stamped cooperative</span></div>
  </div>
  <?php if ($summary['failures']): ?>
    <h3 style="margin-top:14px">Failures</h3>
    <div class="list"><?php foreach (array_slice($summary['failures'], 0, 20) as $failure): ?><div class="row"><span><small><?= e($failure) ?></small></span></div><?php endforeach; ?></div>
  <?php endif; ?>
  <p style="margin-top:14px"><a class="btn light" href="members.php"><i class="fas fa-users"></i> Back to Members</a></p>
</section>
<?php endif; ?>
<?php coop_footer(); ?>
