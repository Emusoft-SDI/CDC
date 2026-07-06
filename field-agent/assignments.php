<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo();
$user = fa_require_user($pdo);
$message = '';
$error = '';

function fa_collect_evidence_uploads(array $files, int $taskId, int $userId): array
{
    if (empty($files['name'])) {
        return [];
    }
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $evidence = [];
    $maxFiles = 6;
    $uploadDir = dirname(__DIR__) . '/field_uploads/evidence';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0750, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Evidence storage is unavailable.');
    }
    foreach ($names as $index => $unused) {
        if (count($evidence) >= $maxFiles) {
            throw new RuntimeException('Upload a maximum of ' . $maxFiles . ' evidence files per visit.');
        }
        $file = [
            'name' => is_array($files['name']) ? (string) $files['name'][$index] : (string) $files['name'],
            'type' => is_array($files['type']) ? (string) $files['type'][$index] : (string) ($files['type'] ?? ''),
            'tmp_name' => is_array($files['tmp_name']) ? (string) $files['tmp_name'][$index] : (string) ($files['tmp_name'] ?? ''),
            'error' => is_array($files['error']) ? (int) $files['error'][$index] : (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => is_array($files['size']) ? (int) $files['size'][$index] : (int) ($files['size'] ?? 0),
        ];
        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') {
            continue;
        }
        $info = app_uploaded_file_info($file, ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'], 12 * 1024 * 1024, 'Field evidence file', ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        $safe = app_safe_upload_name('field_' . $userId . '_task_' . $taskId, (string) $info['name'], (string) $info['extension']);
        if (!move_uploaded_file((string) $info['tmp_name'], $uploadDir . '/' . $safe)) {
            throw new RuntimeException('Unable to save evidence file.');
        }
        $evidence[] = [
            'path' => 'field_uploads/evidence/' . $safe,
            'name' => (string) $info['name'],
            'mime' => (string) $info['type'],
            'size' => (int) $info['size'],
            'kind' => str_starts_with((string) $info['type'], 'image/') ? 'photo' : 'document',
        ];
    }
    return $evidence;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $evidence = fa_collect_evidence_uploads((array) ($_FILES['evidence_files'] ?? []), $taskId, (int) $user['id']);
            fm_record_task_visit($pdo, $user, $_POST + ['sync_source' => 'online_form', 'evidence_files' => $evidence]);
            $message = 'Visit evidence submitted successfully.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$tasks = fa_task_rows($pdo, $user);
fa_header('My Assignments', 'Review assigned farms, start visits, capture GPS, open-source map evidence, camera photos, documents, and field outcomes.', $user, 'assignments');
?>
<?php if ($message): ?><div class="fa-card fa-panel" style="border-color:#bfe8cf;color:#0f6b3c"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="fa-card fa-panel" style="border-color:#ffd2d2;color:#a32020"><?= e($error) ?></div><?php endif; ?>
<section class="fa-grid">
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Assigned Farm Work</h2><span class="badge good"><?= count($tasks) ?> active</span></div>
    <div class="fa-grid">
      <?php foreach ($tasks as $task): $taskId = (int) $task['id']; $assignedLat = $task['latitude'] === null ? '' : (string) $task['latitude']; $assignedLng = $task['longitude'] === null ? '' : (string) $task['longitude']; ?>
        <div class="fa-card fa-panel span-6" style="box-shadow:none">
          <div class="fa-panel-head"><h2><?= e((string) $task['farm_name']) ?></h2><span class="badge <?= e(fa_priority_class((string) $task['priority'])) ?>"><?= e((string) $task['priority']) ?></span></div>
          <p><strong><?= e((string) $task['grower_name']) ?></strong><?= $task['grower_phone'] ? ' / ' . e((string) $task['grower_phone']) : '' ?><br><span class="muted"><?= e(trim((string) (($task['street_address'] ?? '') . ' ' . ($task['lga_name'] ?? '') . ' ' . ($task['state_name'] ?? '')))) ?></span></p>
          <form method="post" class="field-form" enctype="multipart/form-data" data-task-form="<?= $taskId ?>" data-assigned-lat="<?= e($assignedLat) ?>" data-assigned-lng="<?= e($assignedLng) ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="task_id" value="<?= $taskId ?>">
            <div class="field-grid">
              <input name="visit_latitude" id="task_<?= $taskId ?>_lat" inputmode="decimal" placeholder="Visit latitude">
              <input name="visit_longitude" id="task_<?= $taskId ?>_lng" inputmode="decimal" placeholder="Visit longitude">
            </div>
            <div class="field-grid">
              <button type="button" class="btn secondary" onclick="useAssignedFarmGps(<?= $taskId ?>)"><i data-lucide="map-pin"></i> Use Assignment GPS</button>
              <button type="button" class="btn secondary" onclick="fillTaskLocation(<?= $taskId ?>)"><i data-lucide="navigation"></i> Use Current Farm GPS</button>
            </div>
            <div id="task_<?= $taskId ?>_map" class="task-map" data-map-task="<?= $taskId ?>" data-assigned-lat="<?= e($assignedLat) ?>" data-assigned-lng="<?= e($assignedLng) ?>" style="height:240px;border:1px solid #dfe7e2;border-radius:12px;overflow:hidden;background:#eef8ef"></div>
            <small id="task_<?= $taskId ?>_gps_status" class="muted">Map shows the assigned farm point and the captured farm visit point when available.</small>
            <select name="result"><option value="verified">Verified on site</option><option value="needs_review">Needs admin review</option><option value="rejected">Reject location</option><option value="submitted">Submit evidence only</option></select>
            <textarea name="notes" placeholder="Visit notes, crop condition, boundary evidence, access notes"></textarea>
            <label style="font-weight:900">Camera capture</label>
            <input type="file" name="evidence_files[]" accept="image/*" capture="environment" data-camera-input="<?= $taskId ?>">
            <small id="task_<?= $taskId ?>_camera_status" class="muted">Use the device camera to snap farm, boundary, stand, or access-road evidence. The image uploads to protected storage when you submit the visit.</small>
            <label style="font-weight:900">Additional photos/documents</label>
            <input type="file" name="evidence_files[]" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx" multiple data-evidence-input="<?= $taskId ?>">
            <small id="task_<?= $taskId ?>_file_status" class="muted">Upload up to 6 files total: photos, PDF, DOC, DOCX. Max 12MB each.</small>
            <div class="field-grid">
              <textarea name="crop_symptoms" placeholder="Crop symptoms observed"></textarea>
              <textarea name="pest_signs" placeholder="Pest, disease, or weed signs"></textarea>
            </div>
            <div class="field-grid">
              <select name="weed_pressure"><option value="">Weed pressure</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select>
              <select name="water_stress"><option value="">Water stress</option><option value="none">None</option><option value="mild">Mild</option><option value="severe">Severe</option></select>
            </div>
            <textarea name="soil_condition" placeholder="Soil condition, moisture, erosion, salinity notes"></textarea>
            <textarea name="farmer_notes" placeholder="Farmer interview notes"></textarea>
            <button class="btn" type="submit"><i data-lucide="send"></i> Submit Visit Evidence</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><div class="empty span-12">No active field task assigned yet.</div><?php endif; ?>
    </div>
  </article>
</section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const taskMaps = {};
function numOrNull(value){const n=Number(value);return Number.isFinite(n)?n:null;}
function taskForm(taskId){return document.querySelector(`[data-task-form="${taskId}"]`);}
function applyTaskLocation(taskId, latitude, longitude){
  const lat=Number(latitude); const lng=Number(longitude);
  document.getElementById(`task_${taskId}_lat`).value=lat.toFixed(7);
  document.getElementById(`task_${taskId}_lng`).value=lng.toFixed(7);
  updateTaskVisitMarker(taskId, lat, lng);
}
function initTaskMap(el){
  if(!window.L || !el) return;
  const taskId=el.getAttribute('data-map-task');
  const assignedLat=numOrNull(el.getAttribute('data-assigned-lat'));
  const assignedLng=numOrNull(el.getAttribute('data-assigned-lng'));
  const center=(assignedLat!==null && assignedLng!==null)?[assignedLat,assignedLng]:[9.0820,8.6753];
  const map=L.map(el).setView(center,(assignedLat!==null && assignedLng!==null)?15:6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);
  const state={map, assigned:null, visit:null};
  if(assignedLat!==null && assignedLng!==null){state.assigned=L.marker([assignedLat,assignedLng]).addTo(map).bindPopup('Assigned farm GPS');}
  taskMaps[taskId]=state;
  setTimeout(()=>map.invalidateSize(),200);
}
function updateTaskVisitMarker(taskId, lat, lng){
  const state=taskMaps[String(taskId)];
  if(!state || !window.L) return;
  if(state.visit){state.visit.setLatLng([lat,lng]);}else{state.visit=L.marker([lat,lng]).addTo(state.map).bindPopup('Captured farm visit GPS');}
  const markers=[];
  if(state.assigned) markers.push(state.assigned.getLatLng());
  markers.push(state.visit.getLatLng());
  state.map.fitBounds(L.latLngBounds(markers),{padding:[24,24],maxZoom:17});
}
function useAssignedFarmGps(taskId){
  const form=taskForm(taskId); if(!form) return;
  const lat=numOrNull(form.getAttribute('data-assigned-lat'));
  const lng=numOrNull(form.getAttribute('data-assigned-lng'));
  if(lat===null || lng===null){alert('No registered farm GPS is available for this assignment. Capture current farm GPS instead.');return;}
  applyTaskLocation(taskId,lat,lng);
  const status=document.getElementById(`task_${taskId}_gps_status`); if(status) status.textContent='Using registered assignment farm GPS.';
}
function fillTaskLocation(taskId){
  if(!navigator.geolocation){alert('GPS is not available on this device.');return;}
  const status=document.getElementById(`task_${taskId}_gps_status`); if(status) status.textContent='Requesting current farm GPS...';
  navigator.geolocation.getCurrentPosition(p=>{applyTaskLocation(taskId,p.coords.latitude,p.coords.longitude); if(status) status.textContent=`Captured current GPS with about ${Math.round(p.coords.accuracy || 0)}m accuracy.`;},()=>{if(status) status.textContent='Unable to get current GPS. You can type coordinates manually.'; alert('Unable to get current GPS. You can type the coordinates manually.');},{enableHighAccuracy:true,timeout:20000,maximumAge:60000});
}
function evidenceCount(input){return input && input.files ? input.files.length : 0;}
document.querySelectorAll('[data-map-task]').forEach(initTaskMap);
document.querySelectorAll('[data-camera-input]').forEach(input=>input.addEventListener('change',()=>{const id=input.getAttribute('data-camera-input');const status=document.getElementById(`task_${id}_camera_status`);if(status)status.textContent=evidenceCount(input)>0?'Camera photo attached. Submit visit evidence to upload into protected storage.':'No camera photo attached.';}));
document.querySelectorAll('[data-evidence-input]').forEach(input=>input.addEventListener('change',()=>{const id=input.getAttribute('data-evidence-input');const status=document.getElementById(`task_${id}_file_status`);if(status)status.textContent=`${evidenceCount(input)} additional file(s) attached. Submit visit evidence to upload.`;}));
if(window.lucide){lucide.createIcons();}
</script>
<?php fa_footer(); ?>