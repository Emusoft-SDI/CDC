<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('profile', 'Business Profile', 'Update business identity, contact person, documents, and settlement basics.', function(PDO $pdo, array $user, array $provider): void {
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
        $stmt = $pdo->prepare("UPDATE provider_registry SET company_name=?, contact_person=?, phone=?, business_address=?, company_description=?, business_registration_number=?, tax_id=?, bank_name=?, account_name=?, account_number=? WHERE id=?");
        $stmt->execute([
            trim((string) $_POST['company_name']),
            trim((string) $_POST['contact_person']),
            trim((string) $_POST['phone']),
            trim((string) $_POST['business_address']),
            trim((string) $_POST['company_description']),
            trim((string) $_POST['business_registration_number']),
            trim((string) $_POST['tax_id']),
            trim((string) $_POST['bank_name']),
            trim((string) $_POST['account_name']),
            trim((string) $_POST['account_number']),
            (int) $provider['id'],
        ]);
        $msg = 'Business profile updated.';
        $stmt = $pdo->prepare("SELECT * FROM provider_registry WHERE id=? LIMIT 1");
        $stmt->execute([(int) $provider['id']]);
        $provider = $stmt->fetch() ?: $provider;
    }
    if ($msg) {
        echo '<div class="notice ok">' . e($msg) . '</div>';
    }
    ?>
    <form method="post" class="card form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Business Name<input name="company_name" value="<?= e((string) $provider['company_name']) ?>" required></label>
      <label>Contact Person<input name="contact_person" value="<?= e((string) $provider['contact_person']) ?>" required></label>
      <label>Phone<input name="phone" value="<?= e((string) $provider['phone']) ?>"></label>
      <label>Business Address<input name="business_address" value="<?= e((string) $provider['business_address']) ?>"></label>
      <label>RC / CAC Number<input name="business_registration_number" value="<?= e((string) $provider['business_registration_number']) ?>"></label>
      <label>Tax ID<input name="tax_id" value="<?= e((string) $provider['tax_id']) ?>"></label>
      <label>Bank Name<input name="bank_name" value="<?= e((string) $provider['bank_name']) ?>"></label>
      <label>Account Name<input name="account_name" value="<?= e((string) $provider['account_name']) ?>"></label>
      <label>Account Number<input name="account_number" value="<?= e((string) $provider['account_number']) ?>"></label>
      <label class="wide">Business Description<textarea name="company_description"><?= e((string) $provider['company_description']) ?></textarea></label>
      <div class="wide"><button class="btn">Save Business Profile</button></div>
    </form>
<?php });
