<?php defined('NATCODEV_SUPER_ADMIN') || exit; 
admin_ensure_action_request_schema($pdo);
$pendingRevocationRequests = $pdo->query("SELECT ar.*, c.certificate_ref, c.status certificate_status, u.name requester_name, u.email requester_email FROM admin_action_requests ar LEFT JOIN certificates c ON c.id = ar.target_id LEFT JOIN users u ON u.id = ar.requested_by WHERE ar.request_type = 'revoke_certificate' AND ar.target_table = 'certificates' AND ar.status = 'pending' ORDER BY ar.created_at DESC LIMIT 25")->fetchAll();
$settings = super_admin_control_settings($pdo);
$accessMatrix = super_admin_access_matrix($pdo, $roles);
$moduleSettings = super_admin_module_settings($pdo);
$trainingSettings = super_admin_training_settings($pdo);
$announcements = $pdo->query("SELECT id, title, body, audience_role, is_active, created_at FROM system_announcements ORDER BY created_at DESC LIMIT 20")->fetchAll();
$auditRows = app_table_exists($pdo, 'audit_log')
    ? $pdo->query("SELECT action, description, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT 60")->fetchAll()
    : [];
?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Module Setup and Entry Points</h2>
      <p>Define where each module lives, who owns it operationally, and how it should behave. Access Control below still decides which roles can use each module.</p>
    </div>
    <a class="button secondary" href="../admin/admin.php">Open Admin Console</a>
  </div>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_module_settings">
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

<section class="console-grid">
  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_access_controls">
    <h2>Access Control Management</h2>
    <p>Universal role permissions. Admins operate inside these boundaries; Super Admin defines them.</p>
    <div class="access-matrix">
      <?php foreach ($roles as $role => $label): ?>
        <fieldset>
          <legend><?= e($label) ?></legend>
          <input type="hidden" name="access_roles[]" value="<?= e($role) ?>">
          <?php foreach (super_admin_feature_catalog() as $feature => $featureLabel): ?>
            <label><input type="checkbox" name="access[<?= e($role) ?>][]" value="<?= e($feature) ?>" <?= in_array($feature, $accessMatrix[$role] ?? [], true) ? 'checked' : '' ?>> <?= e($featureLabel) ?></label>
          <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
    <button type="submit" data-busy-text="Saving access controls...">Save Access Controls</button>
  </form>

  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_training_onboarding">
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
</section>

<section class="console-grid">
  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_announcement">
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

<section class="console-grid">
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
            <input name="review_note" placeholder="Optional review note">
            <button type="submit" name="decision" value="approve" data-busy-text="Approving...">Approve & Revoke</button>
            <button type="submit" name="decision" value="reject" class="secondary" data-busy-text="Rejecting...">Reject</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$pendingRevocationRequests): ?><p class="empty">No pending certificate revocation requests.</p><?php endif; ?>
    </div>
  </section>
</section>
<section class="console-grid">
  <section class="panel">
    <h2>Recent Audit Trail</h2>
    <div class="audit-list">
      <?php foreach ($auditRows as $row): ?>
        <div>
          <strong><?= e($row['action']) ?></strong>
          <span><?= e((string) $row['description']) ?></span>
          <small><?= e(date('M j, Y g:i A', strtotime((string) $row['created_at']))) ?> <?= $row['ip_address'] ? ' | ' . e((string) $row['ip_address']) : '' ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$auditRows): ?><p class="empty">No audit activity recorded yet.</p><?php endif; ?>
    </div>
  </section>
</section>

