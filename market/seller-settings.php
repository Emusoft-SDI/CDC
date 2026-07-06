<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
require_once __DIR__ . '/../lib/workspace-account.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$accountMessage = '';
$accountError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string) ($_POST['action'] ?? ''), ['account_profile', 'account_password'], true)) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $accountError = 'Security session expired. Refresh and try again.';
    } else {
        try {
            if ((string) $_POST['action'] === 'account_password') {
                workspace_account_change_password($pdo, (int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                $accountMessage = 'Password changed.';
            } else {
                workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                $accountMessage = 'Account profile updated.';
            }
            $user = market_require_user($pdo);
        } catch (Throwable $e) {
            $accountError = $e->getMessage();
        }
    }
}
$ctx = seller_query_context($pdo, $user, true); $seller = $ctx['seller']; $sellerTypes = $ctx['sellerTypes']; seller_header('Settings', 'settings', $user, $seller);
if ($ctx['message']): ?><div class="alert ok"><?= e($ctx['message']) ?></div><?php endif; if ($ctx['error']): ?><div class="alert err"><?= e($ctx['error']) ?></div><?php endif; ?>
<?php if ($accountMessage): ?><div class="alert ok"><?= e($accountMessage) ?></div><?php endif; ?><?php if ($accountError): ?><div class="alert err"><?= e($accountError) ?></div><?php endif; ?>
<section class="sc-grid" style="margin-bottom:16px"><?php workspace_account_render_profile_forms($user, 'seller', 'Account Profile', 'Change Password'); ?></section>
<section class="sc-card sc-panel"><div class="sc-panel-head"><h2>Store Profile</h2><a class="sc-btn secondary" href="<?= e(market_dashboard_url_for_user($pdo, $user, 'seller-central.php')) ?>">Back to My Workspace</a></div><form method="post" enctype="multipart/form-data" class="sc-form sc-form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_seller"><div><label>Store Name *</label><input name="store_name" value="<?= e((string) ($seller['store_name'] ?? ($user['name'] ?? ''))) ?>" required></div><div><label>Store Logo</label><input type="file" name="store_logo" accept="image/png,image/jpeg,image/webp"><small class="muted">Square PNG, JPG, or WebP up to 3MB. This appears on seller cards and storefronts.</small></div><?php if (!empty($seller['logo_path'])): ?><div><label>Current Logo</label><img class="thumb" style="width:86px;height:86px;border-radius:14px;object-fit:cover" src="<?= e('../' . ltrim((string) $seller['logo_path'], '/')) ?>" alt="Store logo"></div><?php endif; ?><div><label>Seller Type</label><select name="seller_type"><?php foreach ($sellerTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) ($seller['seller_type'] ?? marketplace_user_default_seller_type($user)) === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div><label>Contact Person</label><input name="contact_person" value="<?= e((string) ($seller['contact_person'] ?? $user['name'] ?? '')) ?>"></div><div><label>Email</label><input type="email" name="email" value="<?= e((string) ($seller['email'] ?? $user['email'] ?? '')) ?>"></div><div><label>Phone</label><input name="phone" value="<?= e((string) ($seller['phone'] ?? '')) ?>"></div><div><label>WhatsApp</label><input name="whatsapp" value="<?= e((string) ($seller['whatsapp'] ?? '')) ?>"></div><div><label>Location</label><input name="location_label" value="<?= e((string) ($seller['location_label'] ?? '')) ?>"></div><div><label>Coverage Area</label><input name="coverage_area" value="<?= e((string) ($seller['coverage_area'] ?? '')) ?>"></div><div class="wide"><label>Description</label><textarea name="description"><?= e((string) ($seller['description'] ?? '')) ?></textarea></div><div class="wide"><label>Fulfillment Options</label><textarea name="fulfillment_options"><?= e((string) ($seller['fulfillment_options'] ?? '')) ?></textarea></div><div class="wide"><button class="sc-btn" type="submit">Save Store Profile</button></div></form></section>
<?php seller_footer(); ?>
