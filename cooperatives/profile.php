<?php
declare(strict_types=1);
require_once __DIR__ . '/_cooperative.php';

$pdo = coop_pdo();
$user = coop_require($pdo);
$cooperative = cooperative_current($pdo);
$cooperativeId = (int) ($cooperative['id'] ?? 0);
$cooperativeName = (string) ($cooperative['name'] ?? 'Your Cooperative');

$msg = '';
$error = '';
$stateKept = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['_csrf'] ?? null))) {
        $error = 'Security session expired. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $stateLabel = trim((string) ($_POST['state'] ?? ''));
        $lga = trim((string) ($_POST['lga'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $contactName = trim((string) ($_POST['contact_name'] ?? ''));
        $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
        $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));

        if ($name === '') {
            $error = 'Cooperative name is required.';
        } elseif ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Contact email address is not valid.';
        } else {
            // Resolve the state label against the reference
            // table; an unmatched label keeps the stored state.
            $stateId = (int) ($cooperative['state_id'] ?? 0);
            if ($stateLabel !== '') {
                $stateStmt = $pdo->prepare('SELECT id, state_name FROM nigeria_states WHERE LOWER(state_name) = LOWER(?) OR LOWER(state_code) = LOWER(?) LIMIT 1');
                $stateStmt->execute([$stateLabel, $stateLabel]);
                $resolved = $stateStmt->fetch();
                if (is_array($resolved)) {
                    $stateId = (int) $resolved['id'];
                } else {
                    $stateKept = true;
                }
            }

            $before = [
                'name' => $cooperativeName,
                'state_id' => (int) ($cooperative['state_id'] ?? 0),
                'lga' => (string) ($cooperative['lga'] ?? ''),
                'address' => (string) ($cooperative['address'] ?? ''),
                'contact_name' => (string) ($cooperative['contact_name'] ?? ''),
                'contact_phone' => (string) ($cooperative['contact_phone'] ?? ''),
                'contact_email' => (string) ($cooperative['contact_email'] ?? ''),
            ];
            $after = [
                'name' => $name,
                'state_id' => $stateId > 0 ? $stateId : null,
                'lga' => $lga,
                'address' => $address,
                'contact_name' => $contactName,
                'contact_phone' => $contactPhone,
                'contact_email' => $contactEmail,
            ];

            try {
                $pdo->prepare('UPDATE cooperatives SET name = ?, state_id = ?, lga = ?, address = ?, contact_name = ?, contact_phone = ?, contact_email = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([
                        $name,
                        $stateId > 0 ? $stateId : null,
                        $lga !== '' ? $lga : null,
                        $address !== '' ? $address : null,
                        $contactName !== '' ? $contactName : null,
                        $contactPhone !== '' ? $contactPhone : null,
                        $contactEmail !== '' ? $contactEmail : null,
                        $cooperativeId,
                    ]);
                if (function_exists('admin_audit_event')) {
                    admin_audit_event($pdo, 'cooperative_profile_updated', 'cooperative', $cooperativeId, $before, $after, 'success', 'Cooperative profile updated (' . $name . ')');
                }
                $msg = 'Cooperative profile updated.';
                if ($stateKept) {
                    $msg .= ' Note: state "' . $stateLabel . '" did not match a database state; the previous state was kept.';
                }
                $cooperative = cooperative_current($pdo) ?: $cooperative;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$stateName = coop_state_name($pdo, (int) ($cooperative['state_id'] ?? 0));

coop_header(
    'Cooperative Profile',
    'View and update the cooperative details members and NATCODEV operations see.',
    $user,
    'profile'
);
?>
<?php if ($msg): ?><div class="card" style="margin-bottom:16px;border-color:#abefc6;background:#ecfdf3"><h2 style="color:#067647"><?= e($msg) ?></h2></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="margin-bottom:16px;border-color:#fda29b;background:#fff1f2"><h2 style="color:#b42318"><?= e($error) ?></h2></div><?php endif; ?>

<section class="card">
  <h2>Cooperative Details</h2>
  <p style="color:var(--muted)">The registration number is fixed when the cooperative is created and cannot be changed here. Changes are audited.</p>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-grid">
      <div class="field"><label for="name">Cooperative name</label><input type="text" id="name" name="name" required value="<?= e((string) ($cooperative['name'] ?? '')) ?>"></div>
      <div class="field"><label for="registration_number">Registration number (CAC)</label><input type="text" id="registration_number" value="<?= e((string) ($cooperative['registration_number'] ?? '') !== '' ? (string) $cooperative['registration_number'] : 'Not registered') ?>" disabled></div>
      <div class="field"><label for="state">State</label><input type="text" id="state" name="state" value="<?= e($stateName) ?>" placeholder="State name"></div>
      <div class="field"><label for="lga">Local government area</label><input type="text" id="lga" name="lga" value="<?= e((string) ($cooperative['lga'] ?? '')) ?>" placeholder="LGA name"></div>
      <div class="field" style="grid-column:span 12"><label for="address">Address</label><textarea id="address" name="address" rows="3"><?= e((string) ($cooperative['address'] ?? '')) ?></textarea></div>
      <div class="field"><label for="contact_name">Contact person</label><input type="text" id="contact_name" name="contact_name" value="<?= e((string) ($cooperative['contact_name'] ?? '')) ?>"></div>
      <div class="field"><label for="contact_phone">Contact phone</label><input type="text" id="contact_phone" name="contact_phone" value="<?= e((string) ($cooperative['contact_phone'] ?? '')) ?>"></div>
      <div class="field"><label for="contact_email">Contact email</label><input type="email" id="contact_email" name="contact_email" value="<?= e((string) ($cooperative['contact_email'] ?? '')) ?>"></div>
      <div class="field"><label>Status</label><input type="text" value="<?= e((string) ($cooperative['status'] ?? 'pending')) ?>" disabled></div>
    </div>
    <p style="margin-top:16px"><button class="btn" type="submit"><i class="fas fa-save"></i> Save Cooperative Profile</button></p>
  </form>
</section>

<section class="card" style="margin-top:16px">
  <h2>Workspace Exit</h2>
  <p><a class="btn light" href="index.php"><i class="fas fa-gauge-high"></i> Back to Dashboard</a> <a class="btn light" href="members.php"><i class="fas fa-users"></i> Members</a> <a class="btn light" href="../index.php"><i class="fas fa-house"></i> NATCODEV Home</a> <a class="btn light" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a></p>
</section>
<?php coop_footer(); ?>
