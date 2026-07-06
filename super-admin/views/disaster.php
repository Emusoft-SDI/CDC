<?php defined('NATCODEV_SUPER_ADMIN') || exit; ?>
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
      <button type="submit" data-busy-text="Creating backup manifest...">Create Backup Manifest</button>
    </form>
  </div>
  <div class="dr-grid">
    <form method="post" class="dr-card">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_dr_settings">
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
      <button type="submit" data-busy-text="Saving recovery policy...">Save Recovery Policy</button>
    </form>

    <form method="post" class="dr-card">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_site_node">
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
  </div>

  <div class="dr-grid">
    <section class="dr-card">
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
              <select name="status"><option value="active" <?= $node['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="paused" <?= $node['status'] === 'paused' ? 'selected' : '' ?>>Paused</option><option value="disabled" <?= $node['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
              <label><input type="checkbox" name="sync_enabled" value="1" <?= (int) $node['sync_enabled'] === 1 ? 'checked' : '' ?>> Sync</label>
              <button type="submit" class="secondary" data-busy-text="Updating node...">Update</button>
            </form>
            <form method="post" class="node-actions">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="queue_sync_ping">
              <input type="hidden" name="target_node" value="<?= e($node['node_key']) ?>">
              <button type="submit" class="secondary" data-busy-text="Queueing ping...">Queue Ping</button>
            </form>
          </article>
        <?php endforeach; ?>
        <?php if (!$siteNodes): ?><p class="empty">No replica or standby sites registered yet.</p><?php endif; ?>
      </div>
    </section>

    <section class="dr-card">
      <h3>Backup and Sync Evidence</h3>
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
  </div>
</section>
