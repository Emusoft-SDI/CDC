<!-- admin/reports.php -->
<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

$agents = $pdo->query("SELECT id, name FROM users WHERE role = 'field_agent'")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Agent Activity Reports - NATCODEV</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; }
    select, input { padding: 8px; width: 200px; }
    .export-buttons { margin: 20px 0; }
    button { background: #2d5016; color: white; padding: 10px 15px; border: none; margin-right: 10px; }
  </style>
</head>
<body>
  <h1>Agent Activity Reports</h1>
  
  <form id="reportForm">
    <div class="form-group">
      <label>Field Agent</label>
      <select name="agent_id" required>
        <option value="">Select Agent</option>
        <?php foreach ($agents as $agent): ?>
        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="form-group">
      <label>Start Date</label>
      <input type="date" name="start_date" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" required>
    </div>
    
    <div class="form-group">
      <label>End Date</label>
      <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" required>
    </div>
    
    <div class="export-buttons">
      <button type="button" onclick="exportReport('csv')">📥 Export CSV</button>
      <button type="button" onclick="exportReport('pdf')">📄 Export PDF</button>
      <button type="button" onclick="viewReport()">👁️ View Online</button>
    </div>
  </form>

  <div id="reportView" style="margin-top: 30px;"></div>

  <script>
    function exportReport(format) {
      const form = document.getElementById('reportForm');
      const data = new FormData(form);
      data.append('format', format);
      
      // Build URL
      const params = new URLSearchParams();
      for (let [key, value] of data.entries()) {
        params.append(key, value);
      }
      
      window.open(`/api/agent-report.php?${params.toString()}`, '_blank');
    }
    
    function viewReport() {
      const form = document.getElementById('reportForm');
      const data = new FormData(form);
      const params = new URLSearchParams();
      for (let [key, value] of data.entries()) {
        params.append(key, value);
      }
      
      fetch(`/api/agent-report.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
          let html = '<h2>Activity Log</h2><table border="1" style="width:100%; border-collapse:collapse;">';
          html += '<tr><th>Timestamp</th><th>Location</th><th>Battery</th><th>Activity</th></tr>';
          
          data.forEach(row => {
            const location = row.latitude ? `${row.latitude}, ${row.longitude}` : 'N/A';
            let activity = [];
            if (row.visit_notes) activity.push(`Visit: ${row.visit_notes.substring(0, 50)}...`);
            if (row.geofence_event) activity.push(`Geofence: ${row.geofence_event} ${row.zone_name || ''}`);
            const activityStr = activity.join('; ') || 'Location ping';
            
            html += `<tr>
              <td>${row.timestamp}</td>
              <td>${location}</td>
              <td>${row.battery_level}%</td>
              <td>${activityStr}</td>
            </tr>`;
          });
          
          html += '</table>';
          document.getElementById('reportView').innerHTML = html;
        });
    }
  </script>
</body>
</html>