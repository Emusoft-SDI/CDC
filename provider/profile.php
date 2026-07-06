<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/workspace-account.php';

provider_simple_page('profile', 'Business Profile', 'Update business identity, contact person, documents, and settlement basics.', function(PDO $pdo, array $user, array $provider): void {
    $msg = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            $error = 'Security session expired. Refresh the page and try again.';
        } else {
            try {
                $action = (string) ($_POST['action'] ?? 'save_profile');
                if ($action === 'account_profile') {
                    workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                    $msg = 'Account profile updated.';
                } elseif ($action === 'account_password') {
                    workspace_account_change_password($pdo, (int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                    $msg = 'Password changed.';
                } elseif ($action === 'upload_accreditation') {
                    provider_upload_accreditation_document($pdo, $provider, $_FILES['document_file'] ?? [], trim((string) ($_POST['document_type'] ?? '')), trim((string) ($_POST['document_number'] ?? '')));
                    $msg = 'Accreditation evidence uploaded and queued for administrator review.';
                } else {
                    $stmt = $pdo->prepare("UPDATE provider_registry SET company_name=?, contact_person=?, phone=?, business_address=?, company_description=?, business_registration_number=?, tax_id=?, bank_name=?, account_name=?, account_number=? WHERE id=?");
                    $stmt->execute([
                        trim((string) $_POST['company_name']), trim((string) $_POST['contact_person']), trim((string) $_POST['phone']),
                        trim((string) $_POST['business_address']), trim((string) $_POST['company_description']),
                        trim((string) $_POST['business_registration_number']), trim((string) $_POST['tax_id']),
                        trim((string) $_POST['bank_name']), trim((string) $_POST['account_name']),
                        trim((string) $_POST['account_number']), (int) $provider['id'],
                    ]);
                    $msg = 'Business profile updated.';
                    $stmt = $pdo->prepare("SELECT * FROM provider_registry WHERE id=? LIMIT 1");
                    $stmt->execute([(int) $provider['id']]);
                    $provider = $stmt->fetch() ?: $provider;
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
    if ($msg) {
        echo '<div class="notice ok">' . e($msg) . '</div>';
    }
    if ($error) {
        echo '<div class="notice err">' . e($error) . '</div>';
    }
    $documents = provider_accreditation_documents($pdo, (int) $provider['id']);
    ?>
    <div class="grid" style="margin-bottom:16px">
      <?php workspace_account_render_profile_forms($user, 'provider', 'Account Profile', 'Change Password'); ?>
    </div>
    <form method="post" class="card form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_profile">
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
    <section class="card" id="documents" style="margin-top:16px">
      <div class="card-head"><div><h2>Accreditation Evidence</h2><p>Upload authentic documents for NATCODEV administrator review. Uploading or replacing evidence returns accreditation to pending review.</p></div><a class="btn light" href="accreditation.php">Full Accreditation</a></div>
      <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload_accreditation">
        <label>Document Type<select name="document_type" required><option value="">Select evidence</option><?php foreach (provider_accreditation_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Document / Licence Number<input name="document_number" maxlength="120"></label>
        <label class="wide">PDF, JPG or PNG (maximum 10 MB)<input type="file" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required></label>
        <div class="wide"><button class="btn">Upload for Review</button></div>
      </form>
      <div class="list" style="margin-top:18px">
        <?php foreach (provider_accreditation_types() as $key => $label): $document = $documents[$key] ?? null; ?>
          <div class="row"><span><strong><?= e($label) ?></strong><?php if ($document): ?><br><small><?= e((string) $document['original_name']) ?> / uploaded <?= e(date('M j, Y', strtotime((string) $document['uploaded_at']))) ?></small><?php endif; ?></span><span><?php if ($document): ?><a class="btn light" href="document.php?id=<?= (int) $document['id'] ?>" target="_blank">View</a> <span class="badge <?= (string) $document['status'] === 'rejected' ? 'red' : ((string) $document['status'] === 'pending' ? 'warn' : '') ?>"><?= e(provider_status_label((string) $document['status'])) ?></span><?php else: ?><span class="badge warn">Not uploaded</span><?php endif; ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
<?php });
