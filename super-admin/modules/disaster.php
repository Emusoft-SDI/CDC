<?php defined('NATCODEV_SUPER_ADMIN') || exit; 
$drSettings = dr_settings($pdo);
$siteNodes = $pdo->query("SELECT id, node_key, name, base_url, node_role, status, sync_enabled, last_seen_at, last_error, created_at FROM site_nodes ORDER BY created_at DESC")->fetchAll();
$backups = $pdo->query("SELECT backup_ref, backup_type, status, storage_path, file_size, checksum, started_at, completed_at FROM dr_backups ORDER BY created_at DESC LIMIT 10")->fetchAll();
$syncEvents = $pdo->query("SELECT event_uuid, direction, event_type, source_node, target_node, status, attempts, error_message, created_at, processed_at FROM sync_events ORDER BY created_at DESC LIMIT 20")->fetchAll();
$activeTab = super_admin_active_tab(['overview', 'policy', 'nodes', 'evidence'], 'overview');
$deletedRecords = app_table_exists($pdo, 'admin_deleted_records')
    ? $pdo->query("SELECT id, target_table, target_id, target_key, deleted_by, delete_request_id, created_at FROM admin_deleted_records ORDER BY created_at DESC, id DESC LIMIT 15")->fetchAll()
    : [];
?>
<div class="super-tabs" data-super-tabs data-active="<?= e($activeTab) ?>">
  <?= super_admin_tab_bar([
    'overview' => 'Overview',
    'policy' => 'Recovery Policy',
    'nodes' => ['label' => 'Site Nodes', 'count' => count($siteNodes)],
    'evidence' => 'Backup & Sync Evidence',
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
          <span>Site Nodes</span>
          <strong><?= count($siteNodes) ?></strong>
          <small>Registered replica, standby, and reporting sites.</small>
        </article>
        <article>
          <span>Backup Manifests</span>
          <strong><?= count($backups) ?></strong>
          <small>Recent backup evidence on record.</small>
        </article>
        <article>
          <span>Sync Events</span>
          <strong><?= count($syncEvents) ?></strong>
          <small>Recent multisite sync activity.</small>
        </article>
      </section>
      <section class="panel">
        <h2>Recoverable Delete Evidence</h2>
        <p class="muted">A full row snapshot is captured before any approved delete, so deleted records remain recoverable and auditable even though the live row is removed.</p>
        <div class="compact-list">
          <?php foreach ($deletedRecords as $d): ?>
            <article>
              <strong><?= e((string) $d['target_table']) ?> #<?= e((string) ($d['target_key'] ?: $d['target_id'])) ?></strong>
              <span>Deleted by user #<?= (int) $d['deleted_by'] ?><?= (int) $d['delete_request_id'] > 0 ? ' | request #' . (int) $d['delete_request_id'] : '' ?></span>
              <small><?= e(date('M j, Y g:i A', strtotime((string) $d['created_at']))) ?></small>
              <form method="post" style="margin-top:8px">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="restore_deleted_record">
                <input type="hidden" name="record_id" value="<?= (int) $d['id'] ?>">
                <button type="submit" class="secondary" data-busy-text="Restoring...">Restore</button>
              </form>
            </article>
          <?php endforeach; ?>
          <?php if (!$deletedRecords): ?><p class="empty">No approved deletes recorded yet.</p><?php endif; ?>
        </div>
      </section>
      <section class="panel">
        <div class="section-head">
          <div>
            <h2>Disaster Recovery and Multisite</h2>
            <p>Define backup policy, register secondary sites, monitor sync events, and keep restore evidence in one Super Admin control plane.</p>
            <div class="notice warn"><strong>Operational restore rule:</strong> Super Admin can trigger backup and verify restore evidence. Actual production restore requires Super Admin approval plus hosting/database operator execution unless a dedicated restore database user is granted.</div>
          </div>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_backup_manifest">
            <input type="hidden" name="tab" value="evidence">
            <button type="submit" data-busy-text="Creating backup manifest...">Create Backup Manifest</button>
          </form>
        </div>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'policy' ? 'active' : '' ?>" data-panel="policy" role="tabpanel">
      <form method="post" class="panel">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_dr_settings">
        <input type="hidden" name="tab" value="policy">
        <h3>Recovery Policy</h3>
        <label>Site ID<input name="dr_site_id" value="<?= e($drSettings['dr_site_id']) ?>" required></label>
        <label>Site Role
          <select name="dr_site_role">
            <?php foreach (['primary' => 'Primary', 'replica' => 'Replica', 'standby' => 'Standby'] as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $drSettings['dr_site_role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Sync Enabled
          <select name="dr_sync_enabled">
            <option value="1" <?= $drSettings['dr_sync_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
            <option value="0" <?= $drSettings['dr_sync_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
          </select>
        </label>
        <label>Sync Mode
          <select name="dr_sync_mode">
            <?php foreach (['manual_review' => 'Manual review', 'near_realtime' => 'Near realtime', 'scheduled_batch' => 'Scheduled batch'] as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $drSettings['dr_sync_mode'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Backup Frequency
          <select name="dr_backup_frequency">
            <?php foreach (['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $drSettings['dr_backup_frequency'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Retention Days<input type="number" name="dr_backup_retention_days" min="1" max="3650" value="<?= e($drSettings['dr_backup_retention_days']) ?>"></label>
        <label>Private Backup Path<input name="dr_backup_storage_path" value="<?= e($drSettings['dr_backup_storage_path']) ?>"></label>
        <label>Recovery Contact<input name="dr_recovery_contact" value="<?= e($drSettings['dr_recovery_contact']) ?>"></label>
        <label>Last Restore Test<input name="dr_last_restore_test_at" value="<?= e($drSettings['dr_last_restore_test_at']) ?>" placeholder="YYYY-MM-DD"></label>
        <label>Automated Backup Token
          <input type="password" name="dr_auto_backup_token" autocomplete="new-password" placeholder="<?= trim((string) ($drSettings['dr_auto_backup_token'] ?? '')) !== '' ? 'Configured — enter a new token to rotate' : 'Not configured (used by cron backup runner)' ?>">
        </label>
        <small class="meta">Used by the bearer-token backup runner. Leave blank to keep the current token.</small>
        <button type="submit" data-busy-text="Saving recovery policy...">Save Recovery Policy</button>
      </form>
    </div>

    <div class="tab-panel <?= $activeTab === 'nodes' ? 'active' : '' ?>" data-panel="nodes" role="tabpanel">
      <section class="console-grid">
        <form method="post" class="panel">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_site_node">
          <input type="hidden" name="tab" value="nodes">
          <h3>Add or Rotate Site Node</h3>
          <label>Node Key<input name="node_key" placeholder="lagos-replica" required></label>
          <label>Display Name<input name="name" placeholder="Lagos Standby Site" required></label>
          <label>Base URL<input name="base_url" placeholder="https://replica.example.com/CDC" required></label>
          <label>Node Role
            <select name="node_role">
              <option value="replica">Replica</option>
              <option value="standby">Standby</option>
              <option value="reporting">Reporting</option>
              <option value="primary">Primary</option>
            </select>
          </label>
          <button type="submit" data-busy-text="Saving node...">Save Node and Generate Token</button>
          <p class="meta">The sync token is shown once after save. Store it in the other site's secure environment/config.</p>
        </form>

        <section class="panel">
          <h3>Registered Sites</h3>
          <div class="compact-list">
            <?php foreach ($siteNodes as $node): ?>
              <article>
                <strong><?= e($node['name']) ?></strong>
                <span><?= e($node['node_key']) ?> | <?= e($node['node_role']) ?> | <?= e($node['status']) ?></span>
                <small><?= e($node['base_url']) ?><?= $node['last_seen_at'] ? ' | Last seen ' . e(date('M j, g:i A', strtotime((string) $node['last_seen_at']))) : '' ?></small>
                <?php if ($node['last_error']): ?><small class="danger-text"><?= e($node['last_error']) ?></small><?php endif; ?>
                <form method="post" class="node-actions">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="update_site_node">
                  <input type="hidden" name="node_id" value="<?= (int) $node['id'] ?>">
                  <input type="hidden" name="tab" value="nodes">
                  <select name="status"><option value="active" <?= $node['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="paused" <?= $node['status'] === 'paused' ? 'selected' : '' ?>>Paused</option><option value="disabled" <?= $node['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
                  <label><input type="checkbox" name="sync_enabled" value="1" <?= (int) $node['sync_enabled'] === 1 ? 'checked' : '' ?>> Sync</label>
                  <button type="submit" class="secondary" data-busy-text="Updating node...">Update</button>
                </form>
                <form method="post" class="node-actions">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="queue_sync_ping">
                  <input type="hidden" name="target_node" value="<?= e($node['node_key']) ?>">
                  <input type="hidden" name="tab" value="nodes">
                  <button type="submit" class="secondary" data-busy-text="Queueing ping...">Queue Ping</button>
                </form>
              </article>
            <?php endforeach; ?>
            <?php if (!$siteNodes): ?><p class="empty">No replica or standby sites registered yet.</p><?php endif; ?>
          </div>
        </section>
      </section>
    </div>

    <div class="tab-panel <?= $activeTab === 'evidence' ? 'active' : '' ?>" data-panel="evidence" role="tabpanel">
      <section class="console-grid">
        <section class="panel">
          <h3>Backup Evidence</h3>
          <div class="compact-list">
            <?php foreach ($backups as $backup): ?>
              <article>
                <strong><?= e($backup['backup_ref']) ?></strong>
                <span><?= e($backup['status']) ?> | <?= number_format((int) $backup['file_size']) ?> bytes</span>
                <small><?= e((string) $backup['storage_path']) ?></small>
              </article>
            <?php endforeach; ?>
            <?php if (!$backups): ?><p class="empty">No backup manifests recorded yet.</p><?php endif; ?>
          </div>
        </section>
        <section class="panel">
          <h3>Recent Sync Events</h3>
          <div class="compact-list">
            <?php foreach ($syncEvents as $event): ?>
              <article>
                <strong><?= e($event['event_type']) ?></strong>
                <span><?= e($event['direction']) ?> | <?= e($event['status']) ?> | <?= e($event['event_uuid']) ?></span>
                <small><?= e((string) ($event['source_node'] ?: 'local')) ?> to <?= e((string) ($event['target_node'] ?: 'all')) ?> | <?= e(date('M j, g:i A', strtotime((string) $event['created_at']))) ?></small>
                <?php if ($event['error_message']): ?><small class="danger-text"><?= e($event['error_message']) ?></small><?php endif; ?>
              </article>
            <?php endforeach; ?>
            <?php if (!$syncEvents): ?><p class="empty">No multisite sync events yet.</p><?php endif; ?>
          </div>
        </section>
      </section>
    </div>

  </div>
</div>
