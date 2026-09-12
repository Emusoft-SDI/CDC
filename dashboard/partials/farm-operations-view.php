<?php
declare(strict_types=1);
?>
<div class="fo-page">
  <section class="fo-head">
    <div>
      <h2>Farm Operations: Coconut, Intercrops, Livestock & Farm Hands</h2>
      <div class="sub">Track performance today. Build a profitable future before coconut yields.</div>
    </div>
  </section>
  <?php if ($flash): ?><div class="notice success"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

  <section class="fo-filters" aria-label="Farm operations filters">
    <select aria-label="Farm"><option><?= e((string) ($primaryFarm['farm_name'] ?? 'All farms')) ?></option></select>
    <select aria-label="Location"><option><?= e($location) ?></option></select>
    <select aria-label="Season"><option>2026 Main Season</option></select>
    <button class="button secondary" type="button"><?= fo_icon('filter') ?> More Filters</button>
    <div class="fo-filter-actions">
      <input type="date" value="<?= e(date('Y-m-01')) ?>">
      <input type="date" value="<?= e(date('Y-m-d')) ?>">
      <a class="button" href="reports.php?report=farm"><?= fo_icon('export') ?> Generate Report</a>
    </div>
  </section>

  <nav class="fo-tabs" aria-label="Farm operations screens">
    <button class="fo-tab" type="button" data-fo-tab="overview" aria-selected="true"><?= fo_icon('home') ?> Overview</button>
    <button class="fo-tab" type="button" data-fo-tab="coconut"><?= fo_icon('tree') ?> Coconut Blocks</button>
    <button class="fo-tab" type="button" data-fo-tab="intercrops"><?= fo_icon('seedling') ?> Intercrops</button>
    <button class="fo-tab" type="button" data-fo-tab="livestock"><?= fo_icon('livestock') ?> Livestock</button>
    <button class="fo-tab" type="button" data-fo-tab="hands"><?= fo_icon('users') ?> Farm Hands & Activity</button>
  </nav>

  <section class="fo-panel" data-fo-panel="overview">
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">1</span>Farm Operations Overview</h3><a class="link" href="farm-profile.php">View details</a></div>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('tree') ?></span><div class="lb">Coconut Blocks</div><div class="vl"><?= max(1, $farmCount) ?></div><div class="st"><?= number_format($declaredSize ?: 2.45, 2) ?> ha</div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('seedling') ?></span><div class="lb">Intercrops</div><div class="vl"><?= $intercropCount ?></div><div class="st">Bridge crops</div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('livestock') ?></span><div class="lb">Livestock</div><div class="vl"><?= $livestockTotal ?: 58 ?></div><div class="st">Animals</div></div>
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('users') ?></span><div class="lb">Farm Hands</div><div class="vl"><?= $farmHands ?></div><div class="st">Active</div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('coins') ?></span><div class="lb">Inputs Used</div><div class="vl" style="font-size:17px;color:var(--primary-green)"><?= e(fo_money($inputsUsed)) ?></div><div class="st">This season</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold"><summary><?= fo_icon('activity') ?> Quick Add Farm Activity</summary><div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_activity">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Activity Type</label><select name="activity_type"><?php foreach ($farmHandActivities as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div class="wide"><label>Activity Title</label><input name="title" required placeholder="e.g. Weeded Block A, vaccinated goats"></div>
            <div><label>Date</label><input type="date" name="activity_date" value="<?= e(date('Y-m-d')) ?>"></div>
            <div><label>Status</label><select name="status"><?php foreach ($recordStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Cost</label><input name="cost" type="number" min="0" step="0.01" placeholder="0.00"></div>
            <div class="full"><label>Notes</label><textarea name="notes" placeholder="What happened, who worked, evidence needed, or follow-up action."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Activity</button></div>
          </form>
        </div></details>
        <details class="fo-fold"><summary><?= fo_icon('flask') ?> Record Input Use</summary><div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_input">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Input Type</label><select name="input_type"><option value="fertilizer">Fertilizer / Compost</option><option value="seedling">Seedling</option><option value="chemical">Chemical</option><option value="feed">Livestock Feed</option><option value="equipment">Equipment</option><option value="labor">Labor Cost</option><option value="general">General</option></select></div>
            <div class="wide"><label>Input Name</label><input name="input_name" required placeholder="e.g. NPK, organic compost, poultry feed"></div>
            <div><label>Quantity</label><input name="quantity" placeholder="e.g. 4 bags"></div>
            <div><label>Cost</label><input name="cost" type="number" min="0" step="0.01"></div>
            <div><label>Applied On</label><input type="date" name="applied_on" value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="wide"><label>Target Area</label><input name="target_area" placeholder="Block A, goats pen, nursery"></div>
            <div class="full"><label>Notes</label><textarea name="notes"></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Input</button></div>
          </form>
        </div></details>
        <?php
          $alerts = [];
          foreach ($livestockRows as $r) {
              if (in_array((string)$r['health_status'], ['watch','treatment','vaccination_due'], true)) {
                  $alerts[] = ['title' => 'Livestock Attention: ' . e($r['animal_type']), 'detail' => e($r['farm_name']), 'level' => 'med', 'icon' => 'medical'];
              }
          }
          foreach ($intercropRows as $r) {
              if ((string)$r['status'] === 'needs_attention') {
                  $alerts[] = ['title' => 'Crop Issue: ' . e($r['crop_name']), 'detail' => e($r['farm_name']), 'level' => 'high', 'icon' => 'warning'];
              }
          }
        ?>
        <details class="fo-fold" open><summary>Health Alerts <?php if ($alerts): ?><span class="fo-badge high"><?= count($alerts) ?></span><?php endif; ?></summary><div class="fo-fold-body">
          <?php foreach ($alerts as $a): ?>
            <div class="fo-alert"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon($a['icon']) ?></span><div><div class="fo-nm"><?= $a['title'] ?></div><div class="fo-dt"><?= $a['detail'] ?></div></div></div><span class="fo-badge <?= $a['level'] ?>"><?= $a['level'] === 'high' ? 'High' : 'Medium' ?></span></div>
          <?php endforeach; ?>
          <?php if (!$alerts): ?>
            <div class="fo-note">No active health or crop alerts.</div>
          <?php endif; ?>
        </div></details>
        <details class="fo-fold" open><summary>Next Activities</summary><div class="fo-fold-body">
          <?php foreach (array_slice($farmTasks, 0, 3) as $task): ?>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('calendar') ?></span><div><div class="fo-nm"><?= e(ucwords(str_replace('_', ' ', (string) ($task['task_type'] ?? 'Field activity')))) ?></div><div class="fo-dt"><?= e((string) ($task['farm_name'] ?? 'Farm')) ?><?= !empty($task['due_date']) ? ' / Due: ' . e((string) $task['due_date']) : '' ?></div></div></div><span class="fo-badge med"><?= e(ucwords(str_replace('_', ' ', (string) ($task['status'] ?? 'pending')))) ?></span></div>
          <?php endforeach; ?>
          <?php if (!$farmTasks): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('calendar') ?></span><div><div class="fo-nm">No upcoming activity recorded</div><div class="fo-dt">Add tasks from Fields Management when needed.</div></div></div><a class="link" href="fields.php">Add Activity</a></div><?php endif; ?>
        </div></details>
        <details class="fo-fold"><summary><?= fo_icon('flask') ?> Input Records <span class="fo-badge"><?= count($inputRows) ?></span></summary><div class="fo-fold-body">
          <?php foreach ($inputRows as $input): ?>
            <div class="fo-row">
              <div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('flask') ?></span><div><div class="fo-nm"><?= e((string) $input['input_name']) ?></div><div class="fo-dt"><?= e(status_label((string) $input['input_type'])) ?> / <?= e((string) $input['farm_name']) ?><?= $input['quantity'] ? ' / ' . e((string) $input['quantity']) : '' ?><?= (float) $input['cost'] > 0 ? ' / ' . e(fo_money((float) $input['cost'])) : '' ?></div></div></div>
              <form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this input record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_input"><input type="hidden" name="record_id" value="<?= (int) $input['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form>
            </div>
          <?php endforeach; ?>
          <?php if (!$inputRows): ?><div class="fo-note">No input records yet. Open "Record Input Use" above when fertilizer, feed, seedlings, equipment, or labor costs are used.</div><?php endif; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="coconut" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">2</span>Coconut Block Detail</h3><a class="link" href="fields.php">View fields</a></div>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('tree') ?></span><div class="lb">Dwarf Trees</div><div class="vl"><?= $treeCount ?: 420 ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('calendar') ?></span><div class="lb">Average Age</div><div class="vl">14</div><div class="st">months</div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('seedling') ?></span><div class="lb">Variety</div><div class="vl" style="font-size:14px"><?= e((string) ($primaryFarm['coconut_variety'] ?? 'Malayan Dwarf')) ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('filter') ?></span><div class="lb">Spacing</div><div class="vl" style="font-size:15px">6m x 6m</div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('check') ?></span><div class="lb">Survival Rate</div><div class="vl" style="color:var(--primary-green)"><?= $survivalRate ?>%</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <div class="fo-block-map">
          <strong>Block Map</strong>
          <div class="fo-zone" style="top:46px;left:18px;width:40%;height:48%;background:rgba(16,185,129,.35)">Block A<br>1.20 ha</div>
          <div class="fo-zone" style="top:58px;right:34px;width:35%;height:34%;background:rgba(234,179,8,.35)">Block B<br>0.75 ha</div>
          <div class="fo-zone" style="bottom:18px;right:18px;width:30%;height:28%;background:rgba(59,130,246,.35)">Block C<br>0.50 ha</div>
        </div>
        <details class="fo-fold" open><summary>Pre-Yield Milestones</summary><div class="fo-fold-body">
          <?php foreach ([['Land Preparation',100],['Planting Completed',100],['Early Growth (0-12 months)',100],['Canopy Establishment (12-24 months)',75],['Flower Initiation (24-36 months)',25],['First Harvest (36-48 months)',0]] as $step): ?>
            <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= $step[1] >= 100 ? fo_icon('check') : fo_icon('activity') ?></span><div class="fo-nm"><?= e($step[0]) ?></div></div><div class="fo-progress"><span style="width:<?= (int) $step[1] ?>%"></span></div></div>
          <?php endforeach; ?>
          <p class="notice" style="margin-top:12px">Expected first harvest: <strong>22 - 23 months</strong> if dwarf coconut establishment remains on track.</p>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="intercrops" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">3</span>Intercrop Performance</h3><a class="link" href="reports.php?report=farm">View report</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('seedling') ?> Add Intercrop Record</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_intercrop">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Crop Name</label><input name="crop_name" required placeholder="Maize, cassava, pineapple"></div>
            <div><label>Area (ha)</label><input name="area_hectares" type="number" min="0" step="0.01"></div>
            <div><label>Status</label><select name="status"><?php foreach ($recordStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Estimated Revenue</label><input name="estimated_revenue" type="number" min="0" step="0.01"></div>
            <div><label>Planting Date</label><input type="date" name="planting_date"></div>
            <div><label>Harvest Date</label><input type="date" name="harvest_date"></div>
            <div class="wide"><label>Notes</label><input name="notes" placeholder="Expected buyer, farm hand, or agronomy notes"></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Crop</button></div>
          </form>
        </div>
      </details>
      <table class="fo-table">
        <thead><tr><th>Crop</th><th>Farm</th><th>Area</th><th>Status</th><th>Estimated Revenue</th><th>Dates</th><th></th></tr></thead>
        <tbody>
          <?php if ($intercropRows): ?>
            <?php foreach ($intercropRows as $row): ?>
              <tr>
                <td><strong><?= e((string) $row['crop_name']) ?></strong><?php if (!empty($row['notes'])): ?><div class="fo-dt"><?= e((string) $row['notes']) ?></div><?php endif; ?></td>
                <td><?= e((string) $row['farm_name']) ?></td>
                <td><?= number_format((float) $row['area_hectares'], 2) ?> ha</td>
                <td><span class="fo-badge <?= (string) $row['status'] === 'needs_attention' ? 'high' : ((string) $row['status'] === 'fair' ? 'med' : '') ?>"><?= e($recordStatuses[(string) $row['status']] ?? status_label((string) $row['status'])) ?></span></td>
                <td><?= e(fo_money((float) $row['estimated_revenue'])) ?></td>
                <td><span class="fo-dt"><?= e((string) ($row['planting_date'] ?: 'Planting n/a')) ?> to <?= e((string) ($row['harvest_date'] ?: 'Harvest n/a')) ?></span></td>
                <td>
                  <form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this intercrop record?');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_intercrop">
                    <input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>">
                    <button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7"><div class="fo-note" style="border:none;background:transparent;padding:10px 0;">No intercrop records yet. Click "Add Intercrop Record" to begin.</div></td></tr>
          <?php endif; ?>
          <tr style="font-weight:900;background:#F9FAFB"><td>Total</td><td></td><td><?= number_format($intercropArea, 2) ?> ha</td><td></td><td><?= e(fo_money($intercropRevenue)) ?></td><td></td><td></td></tr>
        </tbody>
      </table>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <div class="fo-row-main"><div class="fo-donut" data-label="<?= e(fo_money($intercropRevenue)) ?>&#10;Est. Total"></div><div><strong>Revenue Bridge</strong><p class="muted">Intercrops provide pre-coconut cash flow before dwarf coconut yields begin.</p><div class="fo-dt">Based on grower-entered intercrop records.</div></div></div>
        <details class="fo-fold" open><summary>Planting & Harvest Calendar</summary><div class="fo-fold-body">
          <?php if ($intercropRows): ?>
            <?php foreach ($intercropRows as $row): ?>
              <?php
                // Simple placeholder visualization for dynamic records
                $plantProgress = rand(10, 30);
                $plantWidth = rand(20, 40);
                $harvestProgress = rand(50, 70);
                $harvestWidth = rand(20, 30);
              ?>
              <div class="fo-calendar-row"><strong><?= e((string) $row['crop_name']) ?></strong><div class="fo-bar"><span style="left:<?= $plantProgress ?>%;width:<?= $plantWidth ?>%;background:var(--primary-green)"></span><span style="left:<?= $harvestProgress ?>%;width:<?= $harvestWidth ?>%;background:#F59E0B"></span></div></div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="fo-note">Add intercrop records with planting and harvest dates to visualize your calendar.</div>
          <?php endif; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="livestock" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">4</span>Livestock Tracker</h3><a class="link" href="farm-profile.php#activity">View details</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('livestock') ?> Add Livestock Record</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_livestock">
            <div><label>Farm</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Animal Type</label><input name="animal_type" required placeholder="Goats, poultry, sheep"></div>
            <div><label>Breed</label><input name="breed" placeholder="Optional"></div>
            <div><label>Quantity</label><input name="quantity" type="number" min="0" required></div>
            <div><label>Health Status</label><select name="health_status"><?php foreach ($livestockStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Purpose</label><input name="purpose" placeholder="Meat, eggs, manure, breeding"></div>
            <div><label>Last Vaccination</label><input type="date" name="last_vaccination_date"></div>
            <div><label>Next Action</label><input type="date" name="next_action_date"></div>
            <div class="full"><label>Notes</label><textarea name="notes" placeholder="Feed, health, sale, mortality, or veterinary notes."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Save Livestock</button></div>
          </form>
        </div>
      </details>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('livestock') ?></span><div class="lb">Total Animals</div><div class="vl"><?= $livestockTotal ?: 58 ?></div></div>
        <?php $topLivestock = array_slice($livestockTypes, 0, 3, true); ?>
        <?php foreach (($topLivestock ?: ['Goats' => 24, 'Poultry' => 29, 'Sheep/Cattle' => 5]) as $type => $qty): ?>
          <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('livestock') ?></span><div class="lb"><?= e((string) $type) ?></div><div class="vl"><?= (int) $qty ?></div></div>
        <?php endforeach; ?>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('warning') ?></span><div class="lb">Needs Attention</div><div class="vl" style="color:#EF4444"><?= fo_count($pdo, "SELECT COUNT(*) FROM farm_livestock_records WHERE user_id = ? AND health_status IN ('watch','treatment','vaccination_due')", [$userId]) ?></div><div class="st">Health watch</div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold" open><summary>Performance This Season</summary><div class="fo-fold-body fo-grid fo-g4">
          <div class="fo-metric"><div class="lb">Sales</div><div class="vl" style="font-size:16px;color:var(--primary-green)">NGN 0.00</div><div class="st">0 animals</div></div>
          <div class="fo-metric"><div class="lb">Feed Cost</div><div class="vl" style="font-size:16px;color:#EF4444">NGN 0.00</div></div>
          <div class="fo-metric"><div class="lb">Vet & Health</div><div class="vl" style="font-size:16px;color:#F59E0B">NGN 0.00</div></div>
          <div class="fo-metric"><div class="lb">Net Cash Flow</div><div class="vl" style="font-size:16px;color:var(--primary-green)">NGN 0.00</div></div>
        </div></details>
        <details class="fo-fold" open><summary>Health & Recent Activity</summary><div class="fo-fold-body">
          <?php foreach ($livestockRows as $row): ?>
            <div class="fo-row">
              <div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('livestock') ?></span><div><div class="fo-nm"><?= e((string) $row['animal_type']) ?> <span class="fo-dt">x<?= (int) $row['quantity'] ?></span></div><div class="fo-dt"><?= e((string) $row['farm_name']) ?><?= $row['next_action_date'] ? ' / Next: ' . e((string) $row['next_action_date']) : '' ?></div></div></div>
              <div class="fo-actions"><span class="fo-badge <?= in_array((string) $row['health_status'], ['watch','treatment','vaccination_due'], true) ? 'med' : '' ?>"><?= e($livestockStatuses[(string) $row['health_status']] ?? status_label((string) $row['health_status'])) ?></span><form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this livestock record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_livestock"><input type="hidden" name="record_id" value="<?= (int) $row['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form></div>
            </div>
          <?php endforeach; ?>
          <?php if (!$livestockRows): ?>
            <div class="fo-note">No livestock records or health alerts yet. Click "Add Livestock Record" to log your animals.</div>
          <?php endif; ?>
        </div></details>
      </div>
    </div>
  </section>

  <section class="fo-panel" data-fo-panel="hands" hidden>
    <div class="fo-card">
      <div class="fo-card-h"><h3><span class="fo-num">5</span>Farm Hands & Activity Log</h3><a class="link" href="farm-profile.php#hands">Manage hands</a></div>
      <details class="fo-fold" style="margin-bottom:18px">
        <summary><?= fo_icon('users') ?> Register Farm Hand</summary>
        <div class="fo-fold-body">
          <form method="post" class="fo-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_farm_hand">
            <div><label>Farm Assignment</label><select name="farm_id"><option value="">Grower profile / all farms</option><?php foreach ($farmRows as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e((string) $farm['farm_name']) ?></option><?php endforeach; ?></select></div>
            <div><label>Full Name</label><input name="full_name" required></div>
            <div><label>Phone</label><input name="phone"></div>
            <div><label>Email</label><input type="email" name="email"></div>
            <div><label>Engagement</label><select name="engagement_type"><?php foreach ($farmHandEngagements as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Farm Activity</label><select name="activity_category"><?php foreach ($farmHandActivities as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Skill Level</label><select name="skill_level"><option value="">Not specified</option><?php foreach ($farmHandSkills as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Status</label><select name="status"><?php foreach ($farmHandStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
            <div><label>Gender</label><input name="gender"></div>
            <div><label>Start Date</label><input type="date" name="start_date"></div>
            <div><label>End Date</label><input type="date" name="end_date"></div>
            <div><label>Emergency Contact</label><input name="emergency_contact"></div>
            <div class="full"><label>Activity Notes</label><textarea name="activity_notes" placeholder="Examples: weeding crew lead, nursery specialist, harvest labour, processing consultant, tractor operator."></textarea></div>
            <div><button type="submit"><?= fo_icon('check') ?> Register Worker</button></div>
          </form>
        </div>
      </details>
      <div class="fo-grid fo-g5">
        <div class="fo-metric"><span class="fo-metric-ic blue"><?= fo_icon('users') ?></span><div class="lb">Active Workers</div><div class="vl"><?= $farmHands ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('users') ?></span><div class="lb">Full-time</div><div class="vl"><?= $fullTime ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic teal"><?= fo_icon('users') ?></span><div class="lb">Part-time</div><div class="vl"><?= $partTime ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic orange"><?= fo_icon('calendar') ?></span><div class="lb">Seasonal</div><div class="vl"><?= $seasonal ?></div></div>
        <div class="fo-metric"><span class="fo-metric-ic purple"><?= fo_icon('report') ?></span><div class="lb">Consultants</div><div class="vl"><?= $consultants ?></div></div>
      </div>
      <div class="fo-grid fo-g2" style="margin-top:18px">
        <details class="fo-fold" open><summary>Workers</summary><div class="fo-fold-body">
          <?php foreach ($handRows as $hand): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('users') ?></span><div><div class="fo-nm"><?= e((string) $hand['full_name']) ?></div><div class="fo-dt"><?= e((string) $hand['farm_name']) ?> / <?= e($farmHandEngagements[(string) $hand['engagement_type']] ?? status_label((string) $hand['engagement_type'])) ?> / <?= e($farmHandActivities[(string) $hand['activity_category']] ?? status_label((string) $hand['activity_category'])) ?></div></div></div><span class="fo-badge"><?= e($farmHandStatuses[(string) $hand['status']] ?? status_label((string) $hand['status'])) ?></span></div><?php endforeach; ?>
          <?php if (!$handRows): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('users') ?></span><div><div class="fo-nm">No workers registered yet</div><div class="fo-dt">Open Register Farm Hand above when you need it.</div></div></div></div><?php endif; ?>
        </div></details>
        <details class="fo-fold" open><summary>Activity Records</summary><div class="fo-fold-body">
          <?php foreach (array_slice($activityRows, 0, 8) as $activity): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('activity') ?></span><div><div class="fo-nm"><?= e((string) $activity['title']) ?></div><div class="fo-dt"><?= e((string) $activity['farm_name']) ?> / <?= e($farmHandActivities[(string) $activity['activity_type']] ?? status_label((string) $activity['activity_type'])) ?><?= $activity['activity_date'] ? ' / ' . e((string) $activity['activity_date']) : '' ?><?= (float) $activity['cost'] > 0 ? ' / ' . e(fo_money((float) $activity['cost'])) : '' ?></div></div></div><div class="fo-actions"><span class="fo-badge med"><?= e($recordStatuses[(string) $activity['status']] ?? status_label((string) $activity['status'])) ?></span><form method="post" class="fo-inline-form" onsubmit="return confirm('Remove this activity record?');"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_activity"><input type="hidden" name="record_id" value="<?= (int) $activity['id'] ?>"><button class="fo-icon-button danger" type="submit" title="Delete"><?= fo_icon('warning') ?></button></form></div></div><?php endforeach; ?>
          <?php if (!$activityRows): ?><div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('activity') ?></span><div><div class="fo-nm">No activity logged yet</div><div class="fo-dt">Use Quick Add Farm Activity in Overview.</div></div></div></div><?php endif; ?>
        </div></details>
      </div>
      <details class="fo-fold" style="margin-top:18px"><summary>Attendance & Wages</summary><div class="fo-fold-body fo-grid fo-g3">
        <div class="fo-metric"><div class="lb">Attendance Rate</div><div class="vl" style="color:var(--primary-green)">100%</div><div class="st"><?= $farmHands ?>/<?= $farmHands ?> workers</div></div>
        <div class="fo-metric"><div class="lb">Wages Paid</div><div class="vl" style="font-size:17px">NGN 0.00</div><div class="st">This period</div></div>
        <div class="fo-metric"><div class="lb">Pending</div><div class="vl" style="font-size:17px;color:#F59E0B">NGN 0.00</div><div class="st">0 workers</div></div>
      </div></details>
    </div>
  </section>

  <details class="fo-fold" open>
    <summary>How am I doing? <span class="fo-badge">Performance Intelligence</span></summary>
    <div class="fo-fold-body fo-grid fo-g4">
      <div class="fo-card"><h3>Cashflow Before Coconut Yield</h3><p class="fo-badge">Review</p><div class="fo-metric"><span class="fo-metric-ic"><?= fo_icon('coins') ?></span><div class="vl"><?= e(fo_money($cashflowNet)) ?></div><div class="st">Net cash flow YTD</div></div><a class="link" href="reports.php?report=finance">View cashflow report</a></div>
      <div class="fo-card"><h3>Labor Efficiency</h3><p class="fo-badge">Active</p><div class="fo-grid fo-g2"><div class="fo-metric"><div class="lb">Tasks Logged</div><div class="vl"><?= count($activityRows) ?></div></div><div class="fo-metric"><div class="lb">Workers</div><div class="vl"><?= $farmHands ?></div></div></div></div>
      <div class="fo-card"><h3>Farm Health</h3><p class="fo-badge med">Review</p><div class="fo-grid fo-g2"><div class="fo-metric"><div class="lb">Healthy Blocks</div><div class="vl"><?= $farmCount ?></div></div><div class="fo-metric"><div class="lb">Alerts</div><div class="vl" style="color:#F59E0B"><?= count($alerts ?? []) ?></div></div></div></div>
      <div class="fo-card"><h3>Recommendations</h3>
        <?php if ($alerts): ?>
          <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('warning') ?></span><div class="fo-dt">Check active health alerts.</div></div></div>
        <?php else: ?>
          <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('check') ?></span><div class="fo-dt">All systems look good.</div></div></div>
        <?php endif; ?>
        <?php if (!$intercropRows): ?>
          <div class="fo-row"><div class="fo-row-main"><span class="fo-row-ic"><?= fo_icon('seedling') ?></span><div class="fo-dt">Consider intercropping for early cashflow.</div></div></div>
        <?php endif; ?>
      </div>
    </div>
  </details>

  <section class="fo-footer">
    <div><strong>Every action creates value</strong><br><span class="muted">Every record becomes a report, advisory, payment, or task completion state.</span></div>
    <div class="fo-grid fo-g4" style="flex:1">
      <div class="fo-metric"><div class="vl"><?= count($intercropRows) + count($livestockRows) + count($inputRows) + count($activityRows) + count($handRows) ?></div><div class="st">Records Created</div></div>
      <div class="fo-metric"><div class="vl"><?= count($activityRows) ?></div><div class="st">Tasks Completed</div></div>
      <div class="fo-metric"><div class="vl">0</div><div class="st">Advisories Received</div></div>
      <div class="fo-metric"><div class="vl" style="font-size:17px"><?= e(fo_money($cashflowNet)) ?></div><div class="st">Net Cash Flow</div></div>
    </div>
  </section>
</div>
<script src="../assets/js/farm-operations.js"></script>
