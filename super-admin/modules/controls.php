<?php defined('NATCODEV_SUPER_ADMIN') || exit; 
admin_ensure_action_request_schema($pdo);
$pendingRevocationRequests = $pdo->query("SELECT ar.*, c.certificate_ref, c.status certificate_status, u.name requester_name, u.email requester_email FROM admin_action_requests ar LEFT JOIN certificates c ON c.id = ar.target_id LEFT JOIN users u ON u.id = ar.requested_by WHERE ar.request_type = 'revoke_certificate' AND ar.target_table = 'certificates' AND ar.status = 'pending' ORDER BY ar.created_at DESC LIMIT 25")->fetchAll();
$settings = super_admin_control_settings($pdo);
$accessMatrix = super_admin_access_matrix($pdo, $roles);
$moduleSettings = super_admin_module_settings($pdo);
$trainingSettings = super_admin_training_settings($pdo);
$announcements = $pdo->query("SELECT id, title, body, audience_role, is_active, created_at FROM system_announcements ORDER BY created_at DESC LIMIT 20")->fetchAll();
$auditHasActor = app_column_exists($pdo, 'audit_log', 'actor_name');
$auditPerPage = super_admin_per_page(50);
$auditPage = admin_current_page();
$auditRows = [];
$auditTotal = 0;
if (app_table_exists($pdo, 'audit_log')) {
    [$auditWhere, $auditParams] = super_admin_audit_filters($pdo);
    $auditOffset = admin_pagination_offset($auditPage, $auditPerPage);
    $auditCountStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log {$auditWhere}");
    $auditCountStmt->execute($auditParams);
    $auditTotal = (int) $auditCountStmt->fetchColumn();
    $auditStmt = $pdo->prepare("SELECT action, description, ip_address" . ($auditHasActor ? ", actor_name" : "") . ", created_at FROM audit_log {$auditWhere} ORDER BY created_at DESC, id DESC LIMIT {$auditPerPage} OFFSET {$auditOffset}");
    $auditStmt->execute($auditParams);
    $auditRows = $auditStmt->fetchAll();
}
$featureCatalog = super_admin_feature_catalog();
$activeFeatures = array_values(array_filter($announcements, static fn (array $a): bool => (int) $a['is_active'] === 1));
$paystackConfigured = trim(app_secret($pdo, 'paystack_secret_key', 'PAYSTACK_SECRET_KEY')) !== '';
$flutterwaveConfigured = trim(app_secret($pdo, 'flutterwave_secret_key', 'FLUTTERWAVE_SECRET_KEY')) !== '';
$monnifyConfigured = trim(app_secret($pdo, 'monnify_api_key', 'MONNIFY_API_KEY')) !== '' && trim(app_secret($pdo, 'monnify_secret_key', 'MONNIFY_SECRET_KEY')) !== '';
$twilioConfigured = trim(app_secret($pdo, 'twilio_sid', 'TWILIO_ACCOUNT_SID')) !== '' && trim(app_secret($pdo, 'twilio_token', 'TWILIO_AUTH_TOKEN')) !== '';
$activeTab = super_admin_active_tab(['overview', 'access', 'onboarding', 'controls', 'integrations', 'modules', 'announcements', 'approvals', 'audit'], 'overview');
$activeRole = super_admin_active_tab(array_keys($roles), (string) (array_key_first($roles) ?? ''), 'role_tab');
?>
<div class="super-tabs" data-super-tabs data-active="<?= e($activeTab) ?>">
  <?= super_admin_tab_bar([
    'overview' => 'Overview',
    'access' => 'Access Control',
    'onboarding' => 'Onboarding',
    'controls' => 'System Controls',
    'integrations' => 'Integrations & Secrets',
    'modules' => 'Module Setup',
    'announcements' => ['label' => 'Announcements', 'count' => count($announcements)],
    'approvals' => ['label' => 'Certificate Revocations', 'count' => count($pendingRevocationRequests)],
    'audit' => 'Audit Trail',
  ], $activeTab) ?>
  <div class="tab-panels">

    <div class="tab-panel <?= $activeTab === 'overview' ? 'active' : '' ?>" data-panel="overview" role="tabpanel">
      <section class="stats">
        <div class="stat"><span>Total Users</span><strong><?= (int) $stats['total_users'] ?></strong></div>
        <div class="stat"><span>Privileged Profiles</span><strong><?= (int) $stats['privileged'] ?></strong></div>
        <div class="stat"><span>Super Admins</span><strong><?= (int) $stats['super_admins'] ?></strong></div>
        <div class="stat"><span>Suspended</span><strong><?= (int) $stats['suspended'] ?></strong></div>
        <div class="stat"><span>Archived</span><strong><?= (int) $stats['archived'] ?></strong></div>
      </section>
      <section class="readiness-grid">
        <article>
          <span>Controlled Features</span>
          <strong><?= count($featureCatalog) ?></strong>
          <small>Permissions defined across all platform roles.</small>
        </article>
        <article>
          <span>Pending Revocations</span>
          <strong><?= count($pendingRevocationRequests) ?></strong>
          <small>Certificate revocations awaiting Super Admin sign-off.</small>
        </article>
        <article>
          <span>Active Announcements</span>
          <strong><?= count($activeFeatures) ?></strong>
          <small>Announcements currently visible to users.</small>
        </article>
        <article>
          <span>Audit Events</span>
          <strong><?= count($auditRows) ?></strong>
          <small>Recent privileged activity recorded.</small>
        </article>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'access' ? 'active' : '' ?>" data-panel="access" role="tabpanel">
      <form class="panel" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_access_controls">
        <input type="hidden" name="tab" value="access">
        <input type="hidden" name="role_tab" value="<?= e($activeRole) ?>">
        <h2>Access Control Management</h2>
        <p>Universal role permissions. Admins operate inside these boundaries; Super Admin defines them. Pick a role to review and adjust its access.</p>
        <div class="super-tabs sub" data-super-tabs data-active="<?= e($activeRole) ?>">
          <?= super_admin_tab_bar($roles, $activeRole, 'role_tab') ?>
          <div class="tab-panels">
            <?php foreach ($roles as $role => $label): ?>
              <div class="tab-panel <?= $activeRole === $role ? 'active' : '' ?>" data-panel="<?= e($role) ?>">
                <input type="hidden" name="access_roles[]" value="<?= e($role) ?>">
                <div class="access-role-grid">
                  <?php foreach ($featureCatalog as $feature => $featureLabel): ?>
                    <label><input type="checkbox" name="access[<?= e($role) ?>][]" value="<?= e($feature) ?>" <?= in_array($feature, $accessMatrix[$role] ?? [], true) ? 'checked' : '' ?>> <?= e($featureLabel) ?></label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="submit" data-busy-text="Saving access controls...">Save Access Controls</button>
      </form>
    </div>

    <div class="tab-panel <?= $activeTab === 'onboarding' ? 'active' : '' ?>" data-panel="onboarding" role="tabpanel">
      <form class="panel" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_training_onboarding">
        <input type="hidden" name="tab" value="onboarding">
        <h2>User Training and Onboarding</h2>
        <label>Default Onboarding Message</label>
        <textarea name="onboarding_default_message"><?= e($trainingSettings['onboarding_default_message']) ?></textarea>
        <label>Training Curriculum</label>
        <textarea name="training_curriculum"><?= e($trainingSettings['training_curriculum']) ?></textarea>
        <div class="settings-grid">
          <label><span>Certification Required</span><select name="training_certification_required"><option value="1" <?= $trainingSettings['training_certification_required'] === '1' ? 'selected' : '' ?>>Required</option><option value="0" <?= $trainingSettings['training_certification_required'] === '0' ? 'selected' : '' ?>>Optional</option></select></label>
          <label><span>Paid Certification Service</span><select name="training_paid_certification_enabled"><option value="1" <?= $trainingSettings['training_paid_certification_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option><option value="0" <?= $trainingSettings['training_paid_certification_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option></select></label>
        </div>
        <button type="submit" data-busy-text="Saving onboarding...">Save Onboarding Policy</button>
      </form>
    </div>

    <div class="tab-panel <?= $activeTab === 'controls' ? 'active' : '' ?>" data-panel="controls" role="tabpanel">
      <form class="panel" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_controls">
        <input type="hidden" name="tab" value="controls">
        <h2>Security &amp; Access Controls</h2>
        <p>Platform-wide policy enforced across the site. Only Super Admin can change these.</p>
        <label>Dashboard System Notice
          <textarea name="dashboard_system_notice" placeholder="Shown to signed-in users on their dashboard."><?= e($settings['dashboard_system_notice']) ?></textarea>
        </label>
        <div class="settings-grid">
          <label><span>Profile OTP</span>
            <select name="security_require_profile_otp">
              <option value="1" <?= $settings['security_require_profile_otp'] === '1' ? 'selected' : '' ?>>Required</option>
              <option value="0" <?= $settings['security_require_profile_otp'] === '0' ? 'selected' : '' ?>>Optional</option>
            </select>
          </label>
          <label><span>2FA for Admins</span>
            <select name="security_require_2fa_admins">
              <option value="1" <?= $settings['security_require_2fa_admins'] === '1' ? 'selected' : '' ?>>Required</option>
              <option value="0" <?= $settings['security_require_2fa_admins'] === '0' ? 'selected' : '' ?>>Optional</option>
            </select>
          </label>
          <label><span>Investor Dashboard</span>
            <select name="access_investor_dashboard_enabled">
              <option value="1" <?= $settings['access_investor_dashboard_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
              <option value="0" <?= $settings['access_investor_dashboard_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
            </select>
          </label>
          <label><span>User CSV Export</span>
            <select name="access_user_export_enabled">
              <option value="1" <?= $settings['access_user_export_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
              <option value="0" <?= $settings['access_user_export_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
            </select>
          </label>
        </div>
        <button type="submit" data-busy-text="Saving controls...">Save Controls</button>
      </form>
    </div>

    <div class="tab-panel <?= $activeTab === 'integrations' ? 'active' : '' ?>" data-panel="integrations" role="tabpanel">
      <form class="panel" method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_secrets">
        <input type="hidden" name="tab" value="integrations">
        <h2>Integrations &amp; Secrets</h2>
        <p>Rotate payment gateway keys from the console. Stored values override the <code>.env</code> configuration and are never displayed back. Leave a field blank to keep the current value.</p>
        <div class="settings-grid">
          <label>Paystack Secret Key
            <input type="password" name="paystack_secret_key" autocomplete="new-password" placeholder="<?= $paystackConfigured ? 'Configured — enter a new key to rotate' : 'Not configured' ?>">
          </label>
          <label>Flutterwave Secret Key
            <input type="password" name="flutterwave_secret_key" autocomplete="new-password" placeholder="<?= $flutterwaveConfigured ? 'Configured — enter a new key to rotate' : 'Not configured' ?>">
          </label>
          <label>Monnify API Key
            <input type="password" name="monnify_api_key" autocomplete="new-password" placeholder="<?= $monnifyConfigured ? 'Configured — enter a new value to rotate' : 'Not configured' ?>">
          </label>
          <label>Monnify Secret Key
            <input type="password" name="monnify_secret_key" autocomplete="new-password" placeholder="Leave blank to keep current">
          </label>
          <label>Monnify Contract Code
            <input type="password" name="monnify_contract_code" autocomplete="new-password" placeholder="Leave blank to keep current">
          </label>
          <label>Twilio Account SID
            <input type="password" name="twilio_sid" autocomplete="new-password" placeholder="<?= $twilioConfigured ? 'Configured — enter a new value to rotate' : 'Not configured' ?>">
          </label>
          <label>Twilio Auth Token
            <input type="password" name="twilio_token" autocomplete="new-password" placeholder="Leave blank to keep current">
          </label>
          <label>Mail From Address
            <input name="mail_from_address" placeholder="Leave blank to keep current">
          </label>
          <label>Mail From Name
            <input name="mail_from_name" placeholder="Leave blank to keep current">
          </label>
          <label>Mail Reply-To
            <input name="mail_reply_to" placeholder="Leave blank to keep current">
          </label>
          <label>Mail Transport
            <select name="mail_transport">
              <option value="">Keep current</option>
              <option value="mail">mail()</option>
              <option value="log">log (development)</option>
            </select>
          </label>
        </div>
        <div class="check-row compact-checks">
          <label><input type="checkbox" name="clear_paystack_secret_key" value="1"> Clear Paystack key</label>
          <label><input type="checkbox" name="clear_flutterwave_secret_key" value="1"> Clear Flutterwave key</label>
          <label><input type="checkbox" name="clear_monnify_api_key" value="1"> Clear Monnify key</label>
          <label><input type="checkbox" name="clear_twilio_sid" value="1"> Clear Twilio SID</label>
        </div>
        <div class="notice warn"><strong>Status:</strong> Paystack <?= $paystackConfigured ? 'configured' : 'not configured' ?>, Flutterwave <?= $flutterwaveConfigured ? 'configured' : 'not configured' ?>, Monnify <?= $monnifyConfigured ? 'configured' : 'not configured' ?>, SMS/WhatsApp (Twilio) <?= $twilioConfigured ? 'configured' : 'not configured' ?>. Stored values override <code>.env</code>; the backup runner token is configured in the Recovery tab.</div>
        <button type="submit" data-busy-text="Saving secrets...">Save Secrets</button>
      </form>
    </div>

    <div class="tab-panel <?= $activeTab === 'modules' ? 'active' : '' ?>" data-panel="modules" role="tabpanel">
      <section class="panel">
        <div class="section-head">
          <div>
            <h2>Module Setup and Entry Points</h2>
            <p>Define where each module lives, who owns it operationally, and how it should behave. Access Control decides which roles can use each module.</p>
          </div>
          <a class="button secondary" href="../admin/admin.php">Open Admin Console</a>
        </div>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_module_settings">
          <input type="hidden" name="tab" value="modules">
          <div class="module-grid">
            <?php foreach (super_admin_module_catalog() as $feature => $module): ?>
              <?php $moduleState = $moduleSettings[$feature] ?? []; ?>
              <article class="module-card">
                <div class="section-head compact">
                  <div>
                    <h3><?= e($module['label']) ?></h3>
                    <p><?= e($module['purpose']) ?></p>
                  </div>
                  <a class="button secondary" href="<?= e($module['entry']) ?>">Open</a>
                </div>
                <input type="hidden" name="modules[<?= e($feature) ?>][feature]" value="<?= e($feature) ?>">
                <div class="settings-grid">
                  <label>Operating Mode
                    <select name="modules[<?= e($feature) ?>][mode]">
                      <?php foreach (['active' => 'Active', 'pilot' => 'Pilot', 'setup' => 'Setup Required', 'paused' => 'Paused'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= ($moduleState['mode'] ?? $module['mode']) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Owner
                    <input name="modules[<?= e($feature) ?>][owner]" value="<?= e($moduleState['owner'] ?? $module['owner']) ?>">
                  </label>
                </div>
                <label>Setup Notes
                  <textarea name="modules[<?= e($feature) ?>][notes]" placeholder="<?= e($module['setup']) ?>"><?= e($moduleState['notes'] ?? $module['setup']) ?></textarea>
                </label>
                <small class="meta">Entry: <?= e($module['entry']) ?> / Applies to: <?= e($module['surface']) ?></small>
              </article>
            <?php endforeach; ?>
          </div>
          <button type="submit" data-busy-text="Saving module setup...">Save Module Setup</button>
        </form>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'announcements' ? 'active' : '' ?>" data-panel="announcements" role="tabpanel">
      <section class="console-grid">
        <form class="panel" method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_announcement">
          <input type="hidden" name="tab" value="announcements">
          <h2>System Announcement Management</h2>
          <label>Title</label>
          <input name="title" maxlength="180" required>
          <label>Audience</label>
          <select name="audience_role">
            <option value="all">All users</option>
            <?php foreach ($roles as $role => $label): ?>
              <option value="<?= e($role) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Message</label>
          <textarea name="body" required></textarea>
          <label><input type="checkbox" name="is_active" value="1" checked> Active announcement</label>
          <button type="submit" data-busy-text="Publishing announcement...">Create Announcement</button>
        </form>

        <section class="panel">
          <h2>Active and Recent Announcements</h2>
          <div class="announcement-list">
            <?php foreach ($announcements as $announcement): ?>
              <article>
                <div class="section-head compact">
                  <div>
                    <strong><?= e($announcement['title']) ?></strong>
                    <small><?= e($announcement['audience_role'] === 'all' ? 'All users' : ($roles[$announcement['audience_role']] ?? $announcement['audience_role'])) ?> | <?= e(date('M j, Y g:i A', strtotime((string) $announcement['created_at']))) ?></small>
                  </div>
                  <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_announcement">
                    <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                    <input type="hidden" name="tab" value="announcements">
                    <button type="submit" class="<?= (int) $announcement['is_active'] === 1 ? 'secondary' : '' ?>" data-busy-text="Updating..."><?= (int) $announcement['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                  </form>
                </div>
                <p><?= e($announcement['body']) ?></p>
              </article>
            <?php endforeach; ?>
            <?php if (!$announcements): ?><p class="empty">No announcements created yet.</p><?php endif; ?>
          </div>
        </section>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'approvals' ? 'active' : '' ?>" data-panel="approvals" role="tabpanel">
      <section class="panel">
        <div class="section-head">
          <div>
            <h2>Pending Certificate Revocation Approvals</h2>
            <p>Admins can request revocation, but only Super Admin can finalize a public certificate as revoked.</p>
          </div>
          <a class="button secondary" href="../admin/registry/certificates.php?status=issued">Open Certificates</a>
        </div>
        <div class="audit-list">
          <?php foreach ($pendingRevocationRequests as $request): ?>
            <div>
              <strong><?= e((string) ($request['certificate_ref'] ?: $request['target_label'])) ?></strong>
              <span><?= e((string) ($request['reason'] ?? 'No reason supplied.')) ?></span>
              <small>Requested by <?= e((string) ($request['requester_name'] ?: $request['requester_email'] ?: 'Unknown admin')) ?> on <?= e(date('M j, Y g:i A', strtotime((string) $request['created_at']))) ?></small>
              <form method="post" class="actions" style="margin-top:10px">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="review_certificate_revocation">
                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <input type="hidden" name="tab" value="approvals">
                <input name="review_note" placeholder="Optional review note">
                <button type="submit" name="decision" value="approve" data-busy-text="Approving...">Approve & Revoke</button>
                <button type="submit" name="decision" value="reject" class="secondary" data-busy-text="Rejecting...">Reject</button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (!$pendingRevocationRequests): ?><p class="empty">No pending certificate revocation requests.</p><?php endif; ?>
        </div>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'audit' ? 'active' : '' ?>" data-panel="audit" role="tabpanel">
      <section class="panel">
        <div class="section-head">
          <div>
            <h2>Audit Trail</h2>
            <p>Filter privileged activity and export the evidence.</p>
          </div>
          <?php $auditExportQuery = array_merge($_GET, ['view' => 'controls', 'tab' => 'audit', 'export' => 'audit']); ?>
          <a class="button secondary" href="?<?= e(http_build_query($auditExportQuery)) ?>">Export CSV</a>
        </div>
        <form class="filters" method="get">
          <input type="hidden" name="view" value="controls">
          <input type="hidden" name="tab" value="audit">
          <input name="audit_q" value="<?= e((string) ($_GET['audit_q'] ?? '')) ?>" placeholder="Search action, actor, or description">
          <label class="pagination-size">From <input type="date" name="audit_from" value="<?= e((string) ($_GET['audit_from'] ?? '')) ?>"></label>
          <label class="pagination-size">To <input type="date" name="audit_to" value="<?= e((string) ($_GET['audit_to'] ?? '')) ?>"></label>
          <button type="submit">Filter</button>
        </form>
        <?= super_admin_pagination_controls($auditTotal, $auditPage, $auditPerPage, [
          'view' => 'controls',
          'tab' => 'audit',
          'audit_q' => (string) ($_GET['audit_q'] ?? ''),
          'audit_from' => (string) ($_GET['audit_from'] ?? ''),
          'audit_to' => (string) ($_GET['audit_to'] ?? ''),
        ]) ?>
        <div class="audit-list">
          <?php foreach ($auditRows as $row): ?>
            <div>
              <strong><?= e($row['action']) ?></strong>
              <span><?= e((string) $row['description']) ?></span>
              <small><?php if (!empty($row['actor_name'])): ?><?= e((string) $row['actor_name']) ?> &middot; <?php endif; ?><?= e(date('M j, Y g:i A', strtotime((string) $row['created_at']))) ?><?= $row['ip_address'] ? ' | ' . e((string) $row['ip_address']) : '' ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (!$auditRows): ?><p class="empty">No audit activity recorded yet.</p><?php endif; ?>
        </div>
      </section>
    </div>

  </div>
</div>
