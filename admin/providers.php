<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';

session_start();
$pdo = db();
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
                $pdo->prepare("UPDATE provider_registry SET status = ?, verified_by = ?, verified_at = IF(? IN ('approved','verified'), NOW(), verified_at) WHERE id = ?")
                    ->execute([$status, (int) ($user['id'] ?? 0), $status, (int) ($_POST['provider_id'] ?? 0)]);
                $message = 'Provider status updated.';
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
    SELECT pr.*, COUNT(po.id) offerings
    FROM provider_registry pr
    LEFT JOIN provider_offerings po ON po.provider_id = pr.id
    GROUP BY pr.id
    ORDER BY FIELD(pr.status,'pending_review','approved','verified','suspended','rejected'), pr.created_at DESC
    LIMIT 80
")->fetchAll();

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
