<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../provider/_provider.php';

$pdo = provider_boot();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);

$message = '';
$error = '';
$user = current_user($pdo) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create_provider') {
                $pdo->prepare("
                    INSERT INTO provider_registry
                        (provider_type, company_name, company_description, contact_person, email, phone, business_address, coverage_scope, states_served, years_in_business, certifications, website, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review')
                ")->execute([
                    in_array((string) ($_POST['provider_type'] ?? 'service'), ['service', 'input', 'both'], true) ? (string) $_POST['provider_type'] : 'service',
                    trim((string) ($_POST['company_name'] ?? '')),
                    trim((string) ($_POST['company_description'] ?? '')),
                    trim((string) ($_POST['contact_person'] ?? '')),
                    trim((string) ($_POST['email'] ?? '')),
                    trim((string) ($_POST['phone'] ?? '')),
                    trim((string) ($_POST['business_address'] ?? '')),
                    trim((string) ($_POST['coverage_scope'] ?? 'state')),
                    trim((string) ($_POST['states_served'] ?? '')),
                    ($_POST['years_in_business'] ?? '') === '' ? null : (float) $_POST['years_in_business'],
                    trim((string) ($_POST['certifications'] ?? '')),
                    trim((string) ($_POST['website'] ?? '')),
                ]);
                admin_audit($pdo, 'provider_created', 'Registered provider for review.');
                $message = 'Provider registered for review.';
            } elseif ($action === 'verify_provider') {
                $status = in_array((string) ($_POST['status'] ?? 'pending_review'), ['pending_review', 'approved', 'verified', 'suspended', 'rejected'], true)
                    ? (string) $_POST['status']
                    : 'pending_review';
                $providerId = (int) ($_POST['provider_id'] ?? 0);
                if (in_array($status, ['approved', 'verified'], true)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_accreditation_documents WHERE provider_id=? AND status='approved'");
                    $stmt->execute([$providerId]);
                    if ((int) $stmt->fetchColumn() < count(provider_accreditation_types())) {
                        throw new RuntimeException('Approve every required accreditation document before approving or verifying this provider.');
                    }
                }
                $pdo->prepare("UPDATE provider_registry SET status = ?, verified_by = ?, verified_at = IF(? IN ('approved','verified'), NOW(), verified_at) WHERE id = ?")
                    ->execute([$status, (int) ($user['id'] ?? 0), $status, $providerId]);
                admin_audit($pdo, 'provider_status_updated', 'Set provider #' . $providerId . ' status to ' . $status . '.');
                $message = 'Provider status updated.';
            } elseif ($action === 'review_accreditation_document') {
                $decision = in_array((string) ($_POST['decision'] ?? ''), ['approved', 'rejected'], true) ? (string) $_POST['decision'] : '';
                if ($decision === '') {
                    throw new RuntimeException('Select a valid document decision.');
                }
                $pdo->prepare("UPDATE provider_accreditation_documents SET status=?, reviewer_id=?, reviewer_notes=?, reviewed_at=NOW() WHERE id=?")
                    ->execute([$decision, (int) ($user['id'] ?? 0), trim((string) ($_POST['reviewer_notes'] ?? '')), (int) ($_POST['document_id'] ?? 0)]);
                admin_audit($pdo, 'provider_accreditation_reviewed', 'Reviewed accreditation document #' . (int) ($_POST['document_id'] ?? 0) . ' as ' . $decision . '.');
                $message = 'Accreditation evidence reviewed.';
            } elseif ($action === 'add_offering') {
                $pdo->prepare("
                    INSERT INTO provider_offerings (provider_id, offering_type, category, name, description, price, availability, certifications)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    (int) ($_POST['provider_id'] ?? 0),
                    in_array((string) ($_POST['offering_type'] ?? 'service'), ['service', 'product'], true) ? (string) $_POST['offering_type'] : 'service',
                    trim((string) ($_POST['category'] ?? 'General')),
                    trim((string) ($_POST['name'] ?? '')),
                    trim((string) ($_POST['description'] ?? '')),
                    ($_POST['price'] ?? '') === '' ? null : (float) $_POST['price'],
                    trim((string) ($_POST['availability'] ?? '')),
                    trim((string) ($_POST['certifications'] ?? '')),
                ]);
                $message = 'Provider offering added.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$providers = $pdo->query("
    SELECT pr.*, COALESCE(offering_counts.offerings, 0) offerings
    FROM provider_registry pr
    LEFT JOIN (
        SELECT provider_id, COUNT(*) offerings
        FROM provider_offerings
        GROUP BY provider_id
    ) offering_counts ON offering_counts.provider_id = pr.id
    WHERE pr.deleted_at IS NULL
    ORDER BY FIELD(pr.status,'pending_review','approved','verified','suspended','rejected'), pr.created_at DESC
    LIMIT 80
")->fetchAll();
$providerDocuments = [];
foreach ($pdo->query("SELECT d.*, u.name reviewer_name FROM provider_accreditation_documents d LEFT JOIN users u ON u.id=d.reviewer_id ORDER BY d.uploaded_at DESC")->fetchAll() as $document) {
    $providerDocuments[(int) $document['provider_id']][] = $document;
}

$categories = [
    'Crop Cultivation', 'Pest and Disease Management', 'Soil Testing', 'Irrigation and Water',
    'Training and Education', 'Consulting', 'Equipment Rental and Sales', 'Market Access',
    'Renewable Energy', 'Livestock', 'Post-Harvest Handling', 'Climate Adaptation',
    'Financial Services', 'Agri-Tech', 'Agro-Tourism', 'Precision Agriculture', 'Research and Development',
];

$provTotal = count($providers);
$provVerified = count(array_filter($providers, static fn($p): bool => in_array(strtolower((string) $p['status']), ['approved', 'verified'], true)));
$provPending = count(array_filter($providers, static fn($p): bool => in_array(strtolower((string) $p['status']), ['pending', 'pending_review'], true)));
$provFlagged = count(array_filter($providers, static fn($p): bool => in_array(strtolower((string) $p['status']), ['suspended', 'rejected'], true)));
$provOfferings = (int) array_sum(array_map(static fn($p): int => (int) ($p['offerings'] ?? 0), $providers));

admin_page_start('Service & Input Providers', [
    'active' => 'providers.php',
    'description' => 'Register, verify, and manage agricultural service providers and input providers for the NATCODEV ecosystem.',
    'wide' => true,
    'css' => '
      :root{--primary:#92400e;--green:#b45309;--green-dark:#78350f;--bg:#fffaf3;}
      .provider-hero{background:linear-gradient(135deg,#fffbeb,#fff);border-left:5px solid #b45309}
      .provider-grid{grid-template-columns:380px minmax(0,1fr)}
      @media(max-width:960px){.provider-grid{grid-template-columns:1fr}}
    ',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel provider-hero">
  <h2>Provider Registry</h2>
  <p class="muted">Covers input suppliers, agronomy consultants, soil labs, irrigation vendors, training providers, finance, agri-tech, logistics, and other agricultural services.</p>
  <p><a class="button secondary" href="../provider/dashboard.php"><i class="fas fa-gauge-high"></i> Open Provider Dashboard</a> <a class="button secondary" href="../provider/index.php"><i class="fas fa-arrow-up-right-from-square"></i> Official Provider Registration</a></p>
</section>

<?= admin_kpi_grid([
    ['Providers', number_format($provTotal), 'Registered', 'fa-store', ''],
    ['Verified', number_format($provVerified), 'Approved / verified', 'fa-circle-check', 'blue'],
    ['Pending Review', number_format($provPending), 'Awaiting decision', 'fa-clock', 'orange'],
    ['Flagged', number_format($provFlagged), 'Suspended / rejected', 'fa-triangle-exclamation', 'red'],
    ['Offerings', number_format($provOfferings), 'Products &amp; services', 'fa-boxes-stacked', 'purple'],
]) ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-store"></i></span>
    <span class="collapse-title">Register Provider<small>Add a service or input provider to the registry</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_provider">
      <div class="field-grid">
        <label class="field"><span>Provider Type</span><select name="provider_type"><option value="service">Service Provider</option><option value="input">Input Provider</option><option value="both">Both</option></select></label>
        <label class="field"><span>Company Name</span><input name="company_name" required></label>
        <label class="field"><span>Contact Person</span><input name="contact_person"></label>
        <label class="field"><span>Email</span><input name="email" type="email"></label>
        <label class="field"><span>Phone</span><input name="phone"></label>
        <label class="field"><span>Coverage</span><select name="coverage_scope"><option value="state">Local/Regional</option><option value="national">National</option><option value="international">International</option></select></label>
        <label class="field"><span>Years in Business</span><input name="years_in_business" inputmode="decimal"></label>
        <label class="field"><span>Website</span><input name="website"></label>
        <label class="field"><span>Business Address</span><input name="business_address"></label>
        <label class="field"><span>Description</span><textarea name="company_description"></textarea></label>
        <label class="field"><span>States/Regions Served</span><textarea name="states_served"></textarea></label>
        <label class="field"><span>Certifications/Licenses</span><textarea name="certifications"></textarea></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-plus"></i> Register Provider</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Provider Review &amp; Offerings</h2>
    <span class="meta"><?= number_format($provTotal) ?> provider(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($providers as $provider): ?>
      <?php
        $pStatus = strtolower((string) $provider['status']);
        $pStatusTone = in_array($pStatus, ['approved', 'verified'], true) ? 'ok' : (in_array($pStatus, ['suspended', 'rejected'], true) ? 'bad' : 'warn');
        $pAvatarTone = $pStatusTone === 'ok' ? '' : ($pStatusTone === 'bad' ? 'orange' : 'file');
      ?>
      <article class="record-row stack">
        <span class="record-avatar <?= e($pAvatarTone) ?>"><i class="fas fa-store"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($provider['company_name']) ?>
            <span class="tag <?= e($pStatusTone) ?>"><?= e(ucwords(str_replace('_', ' ', $pStatus))) ?></span>
            <span class="tag info"><?= e(ucwords((string) $provider['provider_type'])) ?></span>
            <span class="tag muted"><?= (int) $provider['offerings'] ?> offering(s)</span>
          </div>
          <?php if (!empty($provider['company_description'])): ?>
            <div class="record-excerpt"><?= e(mb_strimwidth((string) $provider['company_description'], 0, 230, '...')) ?></div>
          <?php endif; ?>

          <form method="post" class="toolbar" style="margin-top:10px">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="verify_provider">
            <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
            <label style="margin:0">Status
              <select name="status">
                <?php foreach (['pending_review', 'approved', 'verified', 'suspended', 'rejected'] as $status): ?>
                  <option value="<?= e($status) ?>" <?= (string) $provider['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button type="submit"><i class="fas fa-floppy-disk"></i> Update Status</button>
          </form>

          <details class="collapse-card" style="margin:12px 0 0">
            <summary>
              <span class="cc-icon"><i class="fas fa-folder-open"></i></span>
              <span class="collapse-title">Accreditation Evidence<small><?= count($providerDocuments[(int) $provider['id']] ?? []) ?>/<?= count(provider_accreditation_types()) ?> uploaded</small></span>
              <span class="caret"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="collapse-body">
              <?php foreach (provider_accreditation_types() as $type => $label): $document = null; foreach ($providerDocuments[(int) $provider['id']] ?? [] as $candidate) { if ((string) $candidate['document_type'] === $type) { $document = $candidate; break; } } ?>
                <div class="card" style="box-shadow:none;margin:10px 0;padding:12px">
                  <strong><?= e($label) ?></strong>
                  <?php if ($document): ?>
                    <p class="muted"><?= e((string) $document['original_name']) ?> / <?= number_format((int) $document['file_size'] / 1024, 1) ?> KB / <?= e(ucwords((string) $document['status'])) ?></p>
                    <p><a class="button secondary sm" target="_blank" rel="noopener" href="../provider/document.php?id=<?= (int) $document['id'] ?>">Open Evidence</a></p>
                    <form method="post" class="toolbar">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="review_accreditation_document">
                      <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                      <select name="decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                      <input name="reviewer_notes" value="<?= e((string) $document['reviewer_notes']) ?>" placeholder="Review notes or rejection reason">
                      <button type="submit">Record Review</button>
                    </form>
                  <?php else: ?><p class="muted">Not uploaded.</p><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </details>

          <details class="collapse-card" style="margin:12px 0 0">
            <summary>
              <span class="cc-icon"><i class="fas fa-boxes-stacked"></i></span>
              <span class="collapse-title">Add Product/Service<small>Publish a new offering</small></span>
              <span class="caret"><i class="fas fa-chevron-down"></i></span>
            </summary>
            <div class="collapse-body">
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add_offering">
                <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
                <div class="field-grid">
                  <label class="field"><span>Type</span><select name="offering_type"><option value="service">Service</option><option value="product">Product/Input</option></select></label>
                  <label class="field"><span>Category</span><select name="category"><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?></select></label>
                  <label class="field"><span>Name</span><input name="name" required></label>
                  <label class="field"><span>Price</span><input name="price" inputmode="decimal"></label>
                  <label class="field"><span>Availability</span><input name="availability"></label>
                  <label class="field"><span>Certifications</span><input name="certifications"></label>
                  <label class="field"><span>Description</span><textarea name="description"></textarea></label>
                </div>
                <div class="actions"><button type="submit"><i class="fas fa-plus"></i> Add Offering</button></div>
              </form>
            </div>
          </details>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$providers): ?><div class="record-empty">No providers registered yet.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
