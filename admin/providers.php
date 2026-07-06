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
                $message = 'Provider status updated.';
            } elseif ($action === 'review_accreditation_document') {
                $decision = in_array((string) ($_POST['decision'] ?? ''), ['approved', 'rejected'], true) ? (string) $_POST['decision'] : '';
                if ($decision === '') {
                    throw new RuntimeException('Select a valid document decision.');
                }
                $pdo->prepare("UPDATE provider_accreditation_documents SET status=?, reviewer_id=?, reviewer_notes=?, reviewed_at=NOW() WHERE id=?")
                    ->execute([$decision, (int) ($user['id'] ?? 0), trim((string) ($_POST['reviewer_notes'] ?? '')), (int) ($_POST['document_id'] ?? 0)]);
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
  <p><a class="button secondary" href="../provider/dashboard.php">Open Provider Dashboard</a> <a class="button secondary" href="../provider/index.php">Official Provider Registration</a></p>
</section>

<section class="layout provider-grid">
  <aside class="panel">
    <h2>Register Provider</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_provider">
      <label>Provider Type<select name="provider_type"><option value="service">Service Provider</option><option value="input">Input Provider</option><option value="both">Both</option></select></label>
      <label>Company Name<input name="company_name" required></label>
      <label>Description<textarea name="company_description"></textarea></label>
      <label>Contact Person<input name="contact_person"></label>
      <label>Email<input name="email" type="email"></label>
      <label>Phone<input name="phone"></label>
      <label>Business Address<input name="business_address"></label>
      <label>Coverage<select name="coverage_scope"><option value="state">Local/Regional</option><option value="national">National</option><option value="international">International</option></select></label>
      <label>States/Regions Served<textarea name="states_served"></textarea></label>
      <label>Years in Business<input name="years_in_business" inputmode="decimal"></label>
      <label>Certifications/Licenses<textarea name="certifications"></textarea></label>
      <label>Website<input name="website"></label>
      <button type="submit">Register Provider</button>
    </form>
  </aside>

  <section class="panel">
    <h2>Provider Review & Offerings</h2>
    <?php foreach ($providers as $provider): ?>
      <article class="card" style="margin-bottom:14px;box-shadow:none;">
        <h3><?= e($provider['company_name']) ?></h3>
        <p class="muted"><?= e(ucwords((string) $provider['provider_type'])) ?> / <?= e(ucwords(str_replace('_', ' ', (string) $provider['status']))) ?> / <?= (int) $provider['offerings'] ?> offering(s)</p>
        <p><?= nl2br(e((string) $provider['company_description'])) ?></p>
        <form method="post" class="toolbar">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="verify_provider">
          <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
          <select name="status">
            <?php foreach (['pending_review','approved','verified','suspended','rejected'] as $status): ?>
              <option value="<?= e($status) ?>" <?= (string) $provider['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Update Status</button>
        </form>
        <details style="margin-top:12px">
          <summary><strong>Accreditation Evidence (<?= count($providerDocuments[(int) $provider['id']] ?? []) ?>/<?= count(provider_accreditation_types()) ?>)</strong></summary>
          <?php foreach (provider_accreditation_types() as $type => $label): $document = null; foreach ($providerDocuments[(int) $provider['id']] ?? [] as $candidate) { if ((string) $candidate['document_type'] === $type) { $document = $candidate; break; } } ?>
            <div class="card" style="box-shadow:none;margin:10px 0;padding:12px">
              <strong><?= e($label) ?></strong>
              <?php if ($document): ?>
                <p class="muted"><?= e((string) $document['original_name']) ?> / <?= number_format((int) $document['file_size'] / 1024, 1) ?> KB / <?= e(ucwords((string) $document['status'])) ?></p>
                <p><a class="button secondary" target="_blank" href="../provider/document.php?id=<?= (int) $document['id'] ?>">Open Evidence</a></p>
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
        </details>
        <details>
          <summary><strong>Add Product/Service</strong></summary>
          <form method="post" class="grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_offering">
            <input type="hidden" name="provider_id" value="<?= (int) $provider['id'] ?>">
            <label>Type<select name="offering_type"><option value="service">Service</option><option value="product">Product/Input</option></select></label>
            <label>Category<select name="category"><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?></select></label>
            <label>Name<input name="name" required></label>
            <label>Price<input name="price" inputmode="decimal"></label>
            <label>Availability<input name="availability"></label>
            <label>Certifications<input name="certifications"></label>
            <label>Description<textarea name="description"></textarea></label>
            <button type="submit">Add Offering</button>
          </form>
        </details>
      </article>
    <?php endforeach; ?>
    <?php if (!$providers): ?><p class="empty">No providers registered yet.</p><?php endif; ?>
  </section>
</section>
<?php admin_page_end(); ?>
