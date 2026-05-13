<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$agents = $pdo->query("
    SELECT u.id, u.name, u.email, COALESCE(sp.staff_type, 'field_agent') staff_type
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE u.role = 'field_agent'
    ORDER BY sp.staff_type, u.name
")->fetchAll();

admin_page_start('Agent Reports', [
    'active' => 'reports.php',
    'description' => 'Export field-agent location and visit activity by reporting period.',
]);
?>
<form class="panel" id="reportForm">
  <label>Field Agent</label>
  <select name="agent_id" required>
    <option value="">Select Agent</option>
    <?php foreach ($agents as $agent): ?>
      <option value="<?= (int) $agent['id'] ?>"><?= e($agent['name']) ?> - <?= e(ucfirst(str_replace('_', ' ', (string) $agent['staff_type']))) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="grid">
    <div>
      <label>Start Date</label>
      <input type="date" name="start_date" value="<?= e(date('Y-m-d', strtotime('-30 days'))) ?>" required>
    </div>
    <div>
      <label>End Date</label>
      <input type="date" name="end_date" value="<?= e(date('Y-m-d')) ?>" required>
    </div>
  </div>
  <div class="actions">
    <button type="button" onclick="viewReport()">View Online</button>
    <button type="button" class="secondary" onclick="exportReport('csv')">Export CSV</button>
    <button type="button" class="secondary" onclick="exportReport('pdf')">Export PDF</button>
  </div>
</form>

<section class="panel" style="margin-top:18px;">
  <h2>Activity Log</h2>
  <div id="reportView" class="empty">Choose an agent and view a report.</div>
</section>

<script>
function reportParams(format) {
  const form = document.getElementById('reportForm');
  const params = new URLSearchParams(new FormData(form));
  if (format) params.set('format', format);
  return params;
}

function exportReport(format) {
  const params = reportParams(format);
  if (!params.get('agent_id')) {
    alert('Select a field agent first.');
    return;
  }
  window.open(`../api/agent-report.php?${params.toString()}`, '_blank');
}

async function viewReport() {
  const params = reportParams();
  if (!params.get('agent_id')) {
    alert('Select a field agent first.');
    return;
  }
  const target = document.getElementById('reportView');
  target.textContent = 'Loading report...';
  const res = await fetch(`../api/agent-report.php?${params.toString()}`);
  const payload = await res.json();
  const rows = payload.items || [];
  if (!rows.length) {
    target.className = 'empty';
    target.textContent = 'No activity found for this period.';
    return;
  }
  target.className = '';
  target.innerHTML = `<table><thead><tr><th>Timestamp</th><th>Location</th><th>Battery</th><th>Activity</th></tr></thead><tbody>${rows.map(row => {
    const location = row.latitude ? `${row.latitude}, ${row.longitude}` : 'N/A';
    const activity = [row.visit_notes ? `Visit: ${String(row.visit_notes).slice(0, 80)}` : '', row.geofence_event ? `Geofence: ${row.geofence_event} ${row.zone_name || ''}` : ''].filter(Boolean).join('; ') || 'Location ping';
    return `<tr><td>${escapeHtml(row.timestamp)}</td><td>${escapeHtml(location)}</td><td>${escapeHtml(row.battery_level || '')}%</td><td>${escapeHtml(activity)}</td></tr>`;
  }).join('')}</tbody></table>`;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}
</script>
<?php admin_page_end(); ?>
