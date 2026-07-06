<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo, 'backups');
dr_ensure_schema($pdo);
$user = current_user($pdo) ?: [];
$message = '';
$error = '';
$bundle = null;
function backup_private_file_path(string $relativePath, string $absoluteRoot): ?string
{
    $projectRoot = dr_project_root();
    $candidate = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, "/\\"));
    $real = realpath($candidate);
    $base = realpath($absoluteRoot);
    if (!$real || !$base || !is_file($real)) {
        return null;
    }
    $realLower = strtolower($real);
    $baseLower = strtolower(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    return str_starts_with($realLower, $baseLower) ? $real : null;
}

function backup_read_manifest(array $backup, string $absoluteRoot): array
{
    $manifestPath = backup_private_file_path((string) ($backup['storage_path'] ?? ''), $absoluteRoot);
    if (!$manifestPath) {
        return [];
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    return is_array($manifest) ? $manifest : [];
}

function backup_artifact_label(string $type): string
{
    return match ($type) {
        'database_sql' => 'Database SQL',
        'site_archive' => 'Site ZIP',
        'manifest' => 'Manifest',
        default => ucwords(str_replace('_', ' ', $type)),
    };
}

[$relativeRoot, $absoluteRoot] = dr_backup_root($pdo);

if (isset($_GET['download'])) {
    $backupId = max(0, (int) $_GET['download']);
    $artifact = preg_replace('/[^a-z0-9_]/i', '', (string) ($_GET['artifact'] ?? 'manifest')) ?: 'manifest';
    $stmt = $pdo->prepare('SELECT * FROM dr_backups WHERE id = ? LIMIT 1');
    $stmt->execute([$backupId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$backup) {
        http_response_code(404);
        exit('Backup was not found.');
    }

    $bundleRelative = str_replace('\\', '/', dirname((string) $backup['storage_path']));
    $targetRelative = (string) $backup['storage_path'];
    if ($artifact !== 'manifest') {
        $manifest = backup_read_manifest($backup, $absoluteRoot);
        $targetRelative = '';
        foreach (($manifest['files'] ?? []) as $file) {
            if (($file['type'] ?? '') === $artifact && !empty($file['path'])) {
                $targetRelative = $bundleRelative . '/' . basename((string) $file['path']);
                break;
            }
        }
    }

    $downloadPath = $targetRelative !== '' ? backup_private_file_path($targetRelative, $absoluteRoot) : null;
    if (!$downloadPath) {
        http_response_code(404);
        exit('Backup artifact was not found.');
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($downloadPath) . '"');
    header('Content-Length: ' . filesize($downloadPath));
    header('X-Content-Type-Options: nosniff');
    readfile($downloadPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_backup_settings') {
                foreach (['dr_backup_storage_path', 'dr_google_drive_path', 'dr_external_drive_path', 'dr_git_remote', 'dr_backup_frequency', 'dr_backup_retention_days', 'dr_recovery_contact', 'dr_auto_backup_interval_seconds', 'dr_auto_backup_activity_window_seconds', 'dr_auto_backup_email_to', 'dr_auto_backup_critical_tables'] as $key) {
                    dr_save_setting($pdo, $key, trim((string) ($_POST[$key] ?? '')));
                }
                dr_save_setting($pdo, 'dr_auto_backup_enabled', !empty($_POST['dr_auto_backup_enabled']) ? '1' : '0');
                dr_save_setting($pdo, 'dr_auto_backup_copy_google_drive', !empty($_POST['dr_auto_backup_copy_google_drive']) ? '1' : '0');
                dr_save_setting($pdo, 'dr_auto_backup_copy_external_drive', !empty($_POST['dr_auto_backup_copy_external_drive']) ? '1' : '0');
                $message = 'Backup settings saved.';
            } elseif ($action === 'create_backup_bundle') {
                $bundle = dr_create_backup_bundle($pdo, (int) ($user['id'] ?? 0), [
                    'include_database' => !empty($_POST['include_database']),
                    'include_site' => !empty($_POST['include_site']),
                    'copy_google_drive' => !empty($_POST['copy_google_drive']),
                    'copy_external_drive' => !empty($_POST['copy_external_drive']),
                ]);
                $message = 'Backup bundle created: ' . $bundle['backup_ref'];
            } elseif ($action === 'run_integrity_backup') {
                $bundle = dr_run_integrity_backup($pdo, (int) ($user['id'] ?? 0), !empty($_POST['force_integrity_backup']));
                $message = ($bundle['status'] ?? '') === 'completed'
                    ? 'Data-integrity SQL backup created: ' . ($bundle['backup_ref'] ?? '')
                    : 'Data-integrity backup skipped: ' . ($bundle['reason'] ?? 'not due');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$settings = dr_settings($pdo);
$backups = app_table_exists($pdo, 'dr_backups') ? $pdo->query("SELECT * FROM dr_backups ORDER BY created_at DESC, id DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC) : [];
$zipReady = class_exists('ZipArchive');
$gitHead = trim((string) @shell_exec('git rev-parse --short HEAD 2>NUL'));
$gitBranch = trim((string) @shell_exec('git branch --show-current 2>NUL'));

admin_page_start('Backup & Disaster Recovery', [
    'active' => 'backups.php',
    'description' => 'Local full-site backups, SQL database dumps, Google Drive handoff, external-drive copies, Git evidence, and restore readiness.',
    'wide' => true,
    'css' => '
      :root{--primary:#0f5132;--green:#198754;--green-dark:#0f5132;--bg:#f6faf7;}
      .backup-hero{background:linear-gradient(135deg,#e8f5ed,#fff);border-left:5px solid #198754}
      .backup-grid{display:grid;grid-template-columns:380px minmax(0,1fr);gap:18px}.backup-target{border:1px solid #dfe7e2;border-radius:10px;padding:12px;background:#fff}.code-line{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#f3f6f4;border:1px solid #dce6df;border-radius:8px;padding:10px;overflow:auto}.status-pill{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:.78rem;font-weight:800;background:#e8f5ed;color:#0f5132}.status-pill.warn{background:#fff7df;color:#8a5a00}.status-pill.bad{background:#fff3f3;color:#9f1d1d}.artifact-links{display:flex;flex-wrap:wrap;gap:6px}.artifact-links a{border:1px solid #dfe7e2;border-radius:999px;padding:4px 8px;text-decoration:none;font-weight:800;color:#0f5132;background:#fff}.artifact-note{display:block;margin-top:6px;color:#8a5a00;font-size:.82rem}@media(max-width:980px){.backup-grid{grid-template-columns:1fr}}
    ',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel backup-hero">
  <h2>Backup Control Center</h2>
  <p class="muted">A go-live platform needs more than a manifest. Use this workspace to create local backup bundles, SQL dumps, optional full-site archives, and copy completed bundles to Google Drive or an external drive folder.</p>
  <div class="stats">
    <div class="stat"><div class="metric"><?= count($backups) ?></div><strong>Recent Backups</strong></div>
    <div class="stat"><div class="metric"><?= $zipReady ? 'On' : 'Off' ?></div><strong>Site Zip</strong></div>
    <div class="stat"><div class="metric"><?= e($settings['dr_backup_frequency'] ?? 'daily') ?></div><strong>Frequency</strong></div>
    <div class="stat"><div class="metric"><?= e((string) ($settings['dr_backup_retention_days'] ?? '30')) ?>d</div><strong>Retention</strong></div>
  </div>
</section>

<section class="backup-grid" id="create">
  <aside class="panel">
    <h2>Create Backup Bundle</h2>
    <p class="muted">Local backup is created first in <strong><?= e($relativeRoot) ?></strong>. Remote folders receive a copy only after the bundle completes.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_backup_bundle">
      <label class="check"><input type="checkbox" name="include_database" value="1" checked> Include SQL database dump</label>
      <label class="check"><input type="checkbox" name="include_site" value="1" checked> Include full site archive <?= $zipReady ? '' : '(Zip extension unavailable)' ?></label>
      <label class="check"><input type="checkbox" name="copy_google_drive" value="1"> Copy to Google Drive folder</label>
      <label class="check"><input type="checkbox" name="copy_external_drive" value="1"> Copy to external drive folder</label>
      <button type="submit">Create Backup Now</button>
    </form>

    <h2>Backup Destinations</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_backup_settings">
      <label>Local backup folder<input name="dr_backup_storage_path" value="<?= e((string) $settings['dr_backup_storage_path']) ?>"></label>
      <label>Google Drive local sync folder<input name="dr_google_drive_path" value="<?= e((string) ($settings['dr_google_drive_path'] ?? '')) ?>" placeholder="C:\Users\user\Google Drive\NATCODEV Backups"></label>
      <label>External drive folder<input name="dr_external_drive_path" value="<?= e((string) ($settings['dr_external_drive_path'] ?? '')) ?>" placeholder="E:\NATCODEV Backups"></label>
      <label>Git remote / repository note<input name="dr_git_remote" value="<?= e((string) ($settings['dr_git_remote'] ?? '')) ?>" placeholder="origin/main or private backup repo"></label>
      <label>Backup frequency<select name="dr_backup_frequency"><option <?= ($settings['dr_backup_frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>daily</option><option <?= ($settings['dr_backup_frequency'] ?? '') === 'hourly' ? 'selected' : '' ?>>hourly</option><option <?= ($settings['dr_backup_frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>weekly</option></select></label>
      <label>Retention days<input name="dr_backup_retention_days" inputmode="numeric" value="<?= e((string) $settings['dr_backup_retention_days']) ?>"></label>
      <label>Recovery contact<input name="dr_recovery_contact" value="<?= e((string) $settings['dr_recovery_contact']) ?>"></label>
      <button type="submit">Save Backup Settings</button>
    </form>
    <h2>Data Integrity Auto Backup</h2>
    <p class="muted">Creates SQL-only backups when critical activity is detected. A scheduler may call the secured runner every 30 seconds or hourly; the policy decides whether work is actually needed.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_backup_settings">
      <label class="check"><input type="checkbox" name="dr_auto_backup_enabled" value="1" <?= ($settings['dr_auto_backup_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Enable activity-aware SQL backups</label>
      <label>Minimum interval seconds<input name="dr_auto_backup_interval_seconds" inputmode="numeric" value="<?= e((string) ($settings['dr_auto_backup_interval_seconds'] ?? '3600')) ?>" placeholder="30 or 3600"></label>
      <label>Activity lookback window seconds<input name="dr_auto_backup_activity_window_seconds" inputmode="numeric" value="<?= e((string) ($settings['dr_auto_backup_activity_window_seconds'] ?? '3600')) ?>"></label>
      <label>Email backup notice to<input name="dr_auto_backup_email_to" value="<?= e((string) ($settings['dr_auto_backup_email_to'] ?? '')) ?>" placeholder="finance-or-admin@example.com"></label>
      <label>Critical tables<textarea name="dr_auto_backup_critical_tables" rows="4"><?= e((string) ($settings['dr_auto_backup_critical_tables'] ?? '')) ?></textarea></label>
      <label class="check"><input type="checkbox" name="dr_auto_backup_copy_google_drive" value="1" <?= ($settings['dr_auto_backup_copy_google_drive'] ?? '0') === '1' ? 'checked' : '' ?>> Copy SQL backup bundle to Google Drive folder</label>
      <label class="check"><input type="checkbox" name="dr_auto_backup_copy_external_drive" value="1" <?= ($settings['dr_auto_backup_copy_external_drive'] ?? '0') === '1' ? 'checked' : '' ?>> Copy SQL backup bundle to external drive folder</label>
      <button type="submit">Save Integrity Policy</button>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="run_integrity_backup">
      <label class="check"><input type="checkbox" name="force_integrity_backup" value="1"> Force now, even if no critical activity or interval is not due</label>
      <button type="submit">Run Integrity Check Now</button>
    </form>
    <div class="code-line">Runner URL: <?= e(app_base_url() . '/admin/backup-runner.php?token=' . ($settings['dr_auto_backup_token'] ?? '')) ?></div>
    <div class="code-line">Last result: <?= e((string) ($settings['dr_auto_backup_last_result'] ?? 'No run yet')) ?></div>
  </aside>

  <section>
    <?php if ($bundle): ?>
      <div class="panel">
        <h2>Created Bundle</h2>
        <p><span class="status-pill"><?= e((string) $bundle['status']) ?></span> <strong><?= e((string) $bundle['backup_ref']) ?></strong></p>
        <p class="muted">Manifest: <?= e((string) $bundle['manifest_path']) ?></p>
        <?php foreach (($bundle['targets'] ?? []) as $target => $result): ?>
          <div class="backup-target"><strong><?= e(ucwords(str_replace('_', ' ', (string) $target))) ?></strong><br><span class="muted"><?= e((string) ($result['status'] ?? 'unknown')) ?> <?= e((string) ($result['path'] ?? '')) ?></span><?php if (!empty($result['error'])): ?><br><span class="error"><?= e((string) $result['error']) ?></span><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="panel">
      <h2>Git and Offsite Evidence</h2>
      <p class="muted">Git is for code history, not database secrets. Use Git for committed code, then keep SQL/site bundles in local, Google Drive, and external-drive storage.</p>
      <div class="code-line">Branch: <?= e($gitBranch ?: 'unknown') ?> / HEAD: <?= e($gitHead ?: 'unknown') ?></div>
      <div class="code-line">Recommended: git status -> git add reviewed files -> git commit -m "Backup checkpoint" -> git push</div>
    </div>

    <div class="panel">
      <h2 id="evidence">Recent Backup Evidence</h2>
      <table>
        <thead><tr><th>Reference</th><th>Type</th><th>Status</th><th>Artifacts</th><th>Storage</th><th>Size</th><th>Created</th></tr></thead>
        <tbody>
          <?php foreach ($backups as $backup): ?>
            <?php $manifest = backup_read_manifest($backup, $absoluteRoot); ?>
            <tr>
              <td><strong><?= e((string) $backup['backup_ref']) ?></strong></td>
              <td><?= e((string) $backup['backup_type']) ?></td>
              <td><span class="status-pill <?= str_contains((string) $backup['status'], 'warning') ? 'warn' : '' ?>"><?= e((string) $backup['status']) ?></span></td>
              <td>
                <div class="artifact-links">
                  <a href="?download=<?= (int) $backup['id'] ?>&artifact=manifest">Manifest</a>
                  <?php foreach (($manifest['files'] ?? []) as $file): ?>
                    <?php if (!empty($file['type'])): ?>
                      <a href="?download=<?= (int) $backup['id'] ?>&artifact=<?= e((string) $file['type']) ?>"><?= e(backup_artifact_label((string) $file['type'])) ?></a>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <?php foreach (($manifest['notes'] ?? []) as $note): ?>
                  <span class="artifact-note"><?= e((string) $note) ?></span>
                <?php endforeach; ?>
                <?php if (!$manifest): ?><span class="artifact-note">Manifest file is not readable from backup storage.</span><?php endif; ?>
              </td>
              <td class="muted"><?= e((string) $backup['storage_path']) ?></td>
              <td><?= number_format((int) $backup['file_size']) ?> bytes</td>
              <td><?= e(date('M j, Y g:i A', strtotime((string) $backup['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$backups): ?><tr><td colspan="7" class="muted">No backup evidence yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="panel">
      <h2 id="restore-runbook">Restore Runbook</h2>
      <ol>
        <li>Copy the latest backup bundle from local, Google Drive, or external drive.</li>
        <li>Restore the site archive into the web root if a code/file rollback is needed.</li>
        <li>Import the SQL dump into the selected database.</li>
        <li>Restore `.env` values and verify APP_URL, email, SMS, Monnify, Paystack, and OAuth settings.</li>
        <li>Run Production Readiness and perform a test login, wallet deposit, withdrawal review, support ticket, and marketplace checkout.</li>
      </ol>
    </div>
  </section>
</section>
<?php admin_page_end(); ?>
