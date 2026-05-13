<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);
fm_ensure_schema($pdo);
agronomy_ensure_schema($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $taskStmt = $pdo->prepare("
                SELECT ft.*, gf.latitude submitted_latitude, gf.longitude submitted_longitude
                FROM field_tasks ft
                JOIN grower_farms gf ON gf.id = ft.farm_id
                WHERE ft.id = ? AND (ft.assigned_to = ? OR ? = 'admin')
                LIMIT 1
            ");
            $taskStmt->execute([$taskId, (int) $user['id'], (string) $user['role']]);
            $task = $taskStmt->fetch();
            if (!$task) {
                throw new RuntimeException('Assigned task not found.');
            }
            $lat = ($_POST['visit_latitude'] ?? '') === '' ? null : (float) $_POST['visit_latitude'];
            $lng = ($_POST['visit_longitude'] ?? '') === '' ? null : (float) $_POST['visit_longitude'];
            if ($lat !== null && ($lat < -90 || $lat > 90)) {
                throw new RuntimeException('Latitude must be between -90 and 90.');
            }
            if ($lng !== null && ($lng < -180 || $lng > 180)) {
                throw new RuntimeException('Longitude must be between -180 and 180.');
            }
            $distance = null;
            if ($lat !== null && $lng !== null && $task['submitted_latitude'] !== null && $task['submitted_longitude'] !== null) {
                $distance = fm_haversine_m((float) $task['submitted_latitude'], (float) $task['submitted_longitude'], $lat, $lng);
            }
            $result = in_array((string) ($_POST['result'] ?? 'submitted'), ['verified', 'needs_review', 'rejected', 'submitted'], true)
                ? (string) $_POST['result']
                : 'submitted';
            $pdo->prepare("
                INSERT INTO farm_visits
                    (farm_id, task_id, agent_id, visit_latitude, visit_longitude, distance_from_submitted_location_m, notes, result)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                (int) $task['farm_id'],
                $taskId,
                (int) $user['id'],
                $lat,
                $lng,
                $distance,
                trim((string) ($_POST['notes'] ?? '')),
                $result,
            ]);
            $visitId = (int) $pdo->lastInsertId();
            if (trim((string) ($_POST['crop_symptoms'] ?? '')) !== '' || trim((string) ($_POST['pest_signs'] ?? '')) !== '') {
                $pdo->prepare("
                    INSERT INTO agronomy_field_checklists
                        (farm_id, visit_id, agent_id, crop_symptoms, pest_signs, weed_pressure, water_stress, soil_condition, farmer_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    (int) $task['farm_id'],
                    $visitId,
                    (int) $user['id'],
                    trim((string) ($_POST['crop_symptoms'] ?? '')),
                    trim((string) ($_POST['pest_signs'] ?? '')),
                    trim((string) ($_POST['weed_pressure'] ?? '')),
                    trim((string) ($_POST['water_stress'] ?? '')),
                    trim((string) ($_POST['soil_condition'] ?? '')),
                    trim((string) ($_POST['farmer_notes'] ?? '')),
                ]);
                $pdo->prepare("
                    INSERT INTO agronomy_cases
                        (case_ref, grower_id, farm_id, assigned_to, source, category, priority, status, title, description, symptoms, created_by)
                    SELECT ?, gf.user_id, gf.id, NULL, 'field_agent', 'crop_management', 'normal', 'open',
                           CONCAT('Field observation: ', gf.farm_name), ?, ?, ?
                    FROM grower_farms gf
                    WHERE gf.id = ?
                ")->execute([
                    agronomy_case_ref(),
                    trim((string) ($_POST['farmer_notes'] ?? 'Field agent submitted agronomy observations.')),
                    trim((string) ($_POST['crop_symptoms'] ?? '')) . "\n" . trim((string) ($_POST['pest_signs'] ?? '')),
                    (int) $user['id'],
                    (int) $task['farm_id'],
                ]);
            }
            $pdo->prepare("UPDATE field_tasks SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$taskId]);
            $verificationStatus = $result === 'verified' && ($distance === null || $distance <= 500) ? 'verified' : 'needs_review';
            $notes = $distance === null ? 'Field visit submitted without comparable GPS distance.' : 'Field visit GPS distance from submitted point: ' . number_format($distance, 1) . 'm.';
            $pdo->prepare("
                INSERT INTO farm_verifications (farm_id, requested_by, status, system_notes, reviewed_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), system_notes = VALUES(system_notes), reviewed_at = NOW()
            ")->execute([(int) $task['farm_id'], (int) $user['id'], $verificationStatus, $notes]);
            $message = 'Visit submitted successfully.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$tasksStmt = $pdo->prepare("
    SELECT ft.*, gf.farm_name, gf.street_address, gf.latitude, gf.longitude, u.name grower_name, u.phone grower_phone,
           s.state_name, l.lga_name
    FROM field_tasks ft
    JOIN grower_farms gf ON gf.id = ft.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN nigeria_states s ON s.id = gf.state_id
    LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
    WHERE (ft.assigned_to = ? OR ? = 'admin') AND ft.status IN ('pending','assigned','in_progress')
    ORDER BY FIELD(ft.priority, 'urgent','high','normal','low'), ft.due_date IS NULL, ft.due_date, ft.created_at
");
$tasksStmt->execute([(int) $user['id'], (string) $user['role']]);
$fieldTasks = $tasksStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Field Agent</title>
  <link rel="manifest" href="../manifest.json">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --line:#d8e2dc; }
    * { box-sizing:border-box; }
    body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin:0; background:#f5f8f6; color:var(--ink); }
    header, main { max-width:1120px; margin:0 auto; padding:22px; }
    header { background:#fff; color:var(--ink); max-width:none; border-bottom:1px solid rgba(16,24,40,.08); box-shadow:0 8px 24px rgba(16,24,40,.06); }
    header .inner { max-width:1120px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; gap:16px; }
    h1 { color:var(--primary); margin:0 0 6px; line-height:1.15; }
    h2 { color:var(--primary); margin-top:0; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:18px; }
    .card { background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; padding:18px; box-shadow:0 14px 34px rgba(16,24,40,.09); }
    #map { height:360px; border-radius:8px; border:1px solid var(--line); }
    input, button { padding:12px; border-radius:5px; border:1px solid var(--line); font-size:1rem; }
    input:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
    button { background:var(--green); color:#fff; border:0; cursor:pointer; font-weight:800; }
    button:hover { background:var(--green-dark); }
    #offlineBadge { background:#a32020; color:white; padding:6px 10px; display:none; }
    .ok { color:#14733a; font-weight:bold; }
    .bad { color:#a32020; font-weight:bold; }
    header a { color:var(--green-dark); font-weight:800; text-decoration:none; }
    .notice{padding:12px;border-radius:8px;margin:12px 0}.success{background:#eaf8f0;color:#0f6b3c}.error{background:#fff3f3;color:#a32020}.task-card{border:1px solid var(--line);border-radius:8px;padding:12px;margin-bottom:12px;background:#fff}.task-form{display:grid;gap:8px}.task-form textarea{min-height:80px;padding:12px;border:1px solid var(--line);border-radius:5px}
    @media (max-width:640px) { header .inner { align-items:flex-start; flex-direction:column; } main { padding:16px; } }
  </style>
</head>
<body>
  <div id="offlineBadge">Offline Mode</div>
  <header>
    <div class="inner">
      <div>
        <h1>Field Agent Operations</h1>
        <div><?= e($user['name']) ?> / <?= e($user['role']) ?></div>
      </div>
      <div>
        <a href="../dashboard/profile.php">Profile</a> /
        <a href="../dashboard/logout.php">Logout</a>
      </div>
    </div>
  </header>

  <main class="grid">
    <?php if ($message): ?><p class="notice success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="notice error"><?= e($error) ?></p><?php endif; ?>
    <section class="card">
      <h2>Assigned Field Tasks</h2>
      <?php foreach ($fieldTasks as $task): ?>
        <article class="task-card">
          <h3><?= e($task['farm_name']) ?></h3>
          <p><strong><?= e($task['grower_name']) ?></strong><?= $task['grower_phone'] ? ' / ' . e($task['grower_phone']) : '' ?><br><?= e((string) $task['street_address']) ?> <?= e((string) $task['lga_name']) ?> <?= e((string) $task['state_name']) ?></p>
          <p>GPS: <?= e((string) ($task['latitude'] ?? 'missing')) ?>, <?= e((string) ($task['longitude'] ?? 'missing')) ?> / Priority: <?= e($task['priority']) ?></p>
          <form method="post" class="task-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            <input name="visit_latitude" id="task_<?= (int) $task['id'] ?>_lat" inputmode="decimal" placeholder="Visit latitude">
            <input name="visit_longitude" id="task_<?= (int) $task['id'] ?>_lng" inputmode="decimal" placeholder="Visit longitude">
            <button type="button" onclick="fillTaskLocation(<?= (int) $task['id'] ?>)">Use Current GPS</button>
            <select name="result"><option value="verified">Verified on site</option><option value="needs_review">Needs admin review</option><option value="rejected">Reject location</option><option value="submitted">Submit evidence only</option></select>
            <textarea name="notes" placeholder="Visit notes, crop condition, boundary evidence, access notes"></textarea>
            <textarea name="crop_symptoms" placeholder="Agronomy: crop symptoms observed"></textarea>
            <textarea name="pest_signs" placeholder="Agronomy: pest, disease, or weed signs"></textarea>
            <select name="weed_pressure"><option value="">Weed pressure</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select>
            <select name="water_stress"><option value="">Water stress</option><option value="none">None</option><option value="mild">Mild</option><option value="severe">Severe</option></select>
            <textarea name="soil_condition" placeholder="Soil condition, moisture, erosion, salinity notes"></textarea>
            <textarea name="farmer_notes" placeholder="Farmer interview notes"></textarea>
            <button type="submit">Submit Visit</button>
          </form>
        </article>
      <?php endforeach; ?>
      <?php if (!$fieldTasks): ?><p>No open field tasks assigned.</p><?php endif; ?>
    </section>

    <section class="card">
      <h2>Visit Map</h2>
      <div id="map"></div>
    </section>

    <section class="card">
      <h2>Certificate Verification</h2>
      <input type="text" id="certRef" placeholder="Certificate or application reference" style="width:100%; box-sizing:border-box;">
      <p><button onclick="verifyCertificate()">Verify Certificate</button></p>
      <div id="verificationResult"></div>
    </section>

    <section class="card">
      <h2>Offline Resources</h2>
      <div id="offlineResources">Loading resources...</div>
      <p><button onclick="downloadAllResources()">Download All</button></p>
    </section>
  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('../sw.js');

    window.addEventListener('online', () => {
      document.getElementById('offlineBadge').style.display = 'none';
      loadResources();
      loadVisits();
      loadCertificatesForOffline();
    });
    window.addEventListener('offline', () => {
      document.getElementById('offlineBadge').style.display = 'block';
    });

    function escapeHtml(value) {
      return String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    }

    async function loadResources() {
      const container = document.getElementById('offlineResources');
      try {
        const res = await fetch('../api/resources.php?offline=1');
        const payload = await res.json();
        const resources = payload.items || [];
        container.innerHTML = resources.length ? resources.map(r => `
          <div>
            <strong>${escapeHtml(r.title)}</strong> (${escapeHtml(r.category)})<br>
            <a href="../resources/${encodeURIComponent(r.file_path)}" download>Download</a>
          </div>
        `).join('') : '<p>No offline resources published yet.</p>';
      } catch (err) {
        container.innerHTML = '<p>Offline mode: use previously cached resources.</p>';
      }
    }

    async function downloadAllResources() {
      const res = await fetch('../api/resources.php?offline=1');
      const payload = await res.json();
      for (const r of (payload.items || [])) {
        try { await fetch(`../resources/${encodeURIComponent(r.file_path)}`); } catch (err) {}
      }
      alert('Resource download attempted.');
    }

    async function verifyCertificate() {
      const ref = document.getElementById('certRef').value.trim();
      const result = document.getElementById('verificationResult');
      if (!ref) return;
      result.textContent = 'Verifying...';

      if (navigator.onLine) {
        try {
          const res = await fetch(`../verify-certificate.php?format=json&ref=${encodeURIComponent(ref)}`);
          const payload = await res.json();
          if (payload.valid) {
            result.innerHTML = `<div class="ok">VALID CERTIFICATE</div><div>${escapeHtml(payload.certificate.name)}<br>${escapeHtml(payload.certificate.certificate_ref)}</div>`;
          } else {
            result.innerHTML = '<div class="bad">Invalid Certificate</div>';
          }
          return;
        } catch (err) {}
      }

      const offline = await verifyCertificateOffline(ref);
      result.innerHTML = offline.valid
        ? `<div class="ok">VALID CERTIFICATE (Offline)</div><div>${escapeHtml(offline.name)}</div>`
        : `<div class="bad">${escapeHtml(offline.error || 'Invalid Certificate')}</div>`;
    }

    async function initOfflineVerification() {
      if (!('indexedDB' in window)) return;
      const request = indexedDB.open('NATCODEVCerts', 1);
      request.onupgradeneeded = event => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('certificates')) db.createObjectStore('certificates', { keyPath: 'certificate_ref' });
      };
      request.onsuccess = event => {
        window.certDB = event.target.result;
        loadCertificatesForOffline();
      };
    }

    async function loadCertificatesForOffline() {
      if (!window.certDB || !navigator.onLine) return;
      try {
        const res = await fetch('../api/certificates.php');
        const payload = await res.json();
        const tx = window.certDB.transaction(['certificates'], 'readwrite');
        const store = tx.objectStore('certificates');
        (payload.items || []).forEach(cert => store.put(cert));
      } catch (err) {}
    }

    function verifyCertificateOffline(ref) {
      return new Promise(resolve => {
        if (!window.certDB) return resolve({ valid:false, error:'Offline verification not ready' });
        const tx = window.certDB.transaction(['certificates'], 'readonly');
        const request = tx.objectStore('certificates').get(ref);
        request.onsuccess = () => resolve(request.result ? { valid:true, ...request.result } : { valid:false, error:'Certificate not cached' });
        request.onerror = () => resolve({ valid:false, error:'Offline lookup failed' });
      });
    }

    const map = L.map('map').setView([9.0820, 8.6753], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

    async function loadVisits() {
      try {
        const res = await fetch('../api/visits.php');
        const payload = await res.json();
        (payload.items || []).forEach(visit => {
          if (visit.latitude && visit.longitude) {
            L.marker([visit.latitude, visit.longitude]).addTo(map).bindPopup(`<strong>${escapeHtml(visit.grower_name)}</strong><br>${escapeHtml(visit.location)}`);
          }
        });
      } catch (err) {}
    }

    async function sendLocation() {
      if (!navigator.geolocation || !navigator.onLine) return;
      navigator.geolocation.getCurrentPosition(async position => {
        await fetch('../api/track-location.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy
          })
        });
      });
    }

    function fillTaskLocation(taskId) {
      if (!navigator.geolocation) {
        alert('GPS is not available on this device.');
        return;
      }
      navigator.geolocation.getCurrentPosition(position => {
        document.getElementById(`task_${taskId}_lat`).value = position.coords.latitude.toFixed(7);
        document.getElementById(`task_${taskId}_lng`).value = position.coords.longitude.toFixed(7);
      }, () => alert('Unable to get current GPS.'));
    }

    loadResources();
    loadVisits();
    initOfflineVerification();
    sendLocation();
    setInterval(sendLocation, 120000);
  </script>
</body>
</html>
