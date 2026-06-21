<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo();
$user = fa_require_user($pdo);
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            fm_record_task_visit($pdo, $user, $_POST + ['sync_source' => 'online_form']);
            $message = 'Visit submitted successfully.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$tasks = fa_task_rows($pdo, $user);
fa_header('My Assignments', 'Review assigned farms, start visits, capture GPS, and submit field outcomes.', $user, 'assignments');
?>
<?php if ($message): ?><div class="fa-card fa-panel" style="border-color:#bfe8cf;color:#0f6b3c"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="fa-card fa-panel" style="border-color:#ffd2d2;color:#a32020"><?= e($error) ?></div><?php endif; ?>
<section class="fa-grid">
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Assigned Farm Work</h2><span class="badge good"><?= count($tasks) ?> active</span></div>
    <div class="fa-grid">
      <?php foreach ($tasks as $task): ?>
        <div class="fa-card fa-panel span-6" style="box-shadow:none">
          <div class="fa-panel-head"><h2><?= e((string) $task['farm_name']) ?></h2><span class="badge <?= e(fa_priority_class((string) $task['priority'])) ?>"><?= e((string) $task['priority']) ?></span></div>
          <p><strong><?= e((string) $task['grower_name']) ?></strong><?= $task['grower_phone'] ? ' / ' . e((string) $task['grower_phone']) : '' ?><br><span class="muted"><?= e(trim((string) (($task['street_address'] ?? '') . ' ' . ($task['lga_name'] ?? '') . ' ' . ($task['state_name'] ?? '')))) ?></span></p>
          <form method="post" class="field-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
            <div class="field-grid">
              <input name="visit_latitude" id="task_<?= (int) $task['id'] ?>_lat" inputmode="decimal" placeholder="Visit latitude">
              <input name="visit_longitude" id="task_<?= (int) $task['id'] ?>_lng" inputmode="decimal" placeholder="Visit longitude">
            </div>
            <button type="button" class="btn secondary" onclick="fillTaskLocation(<?= (int) $task['id'] ?>)"><i data-lucide="navigation"></i> Use Current GPS</button>
            <select name="result"><option value="verified">Verified on site</option><option value="needs_review">Needs admin review</option><option value="rejected">Reject location</option><option value="submitted">Submit evidence only</option></select>
            <textarea name="notes" placeholder="Visit notes, crop condition, boundary evidence, access notes"></textarea>
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
            <button class="btn" type="submit"><i data-lucide="send"></i> Submit Visit</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><div class="empty span-12">No active field task assigned yet.</div><?php endif; ?>
    </div>
  </article>
</section>
<script>
function applyTaskLocation(taskId, latitude, longitude){document.getElementById(`task_${taskId}_lat`).value=Number(latitude).toFixed(7);document.getElementById(`task_${taskId}_lng`).value=Number(longitude).toFixed(7);}
function fillTaskLocation(taskId){if(!navigator.geolocation){alert('GPS is not available on this device.');return;}navigator.geolocation.getCurrentPosition(p=>applyTaskLocation(taskId,p.coords.latitude,p.coords.longitude),()=>alert('Unable to get current GPS. You can type the coordinates manually.'),{enableHighAccuracy:true,timeout:20000,maximumAge:60000});}
if(window.lucide){lucide.createIcons();}
</script>
<?php fa_footer(); ?>
