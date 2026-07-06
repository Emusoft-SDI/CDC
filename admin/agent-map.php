<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);
support_ensure_schema($pdo);
admin_require($pdo, 'field_network');

function fmap_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Field map query failed: ' . $e->getMessage());
        return [];
    }
}

function fmap_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!app_table_exists($pdo, $table)) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$farmPoints = app_table_exists($pdo, 'grower_farms') ? fmap_rows($pdo, "
    SELECT gf.id, gf.farm_name, gf.farm_size, gf.latitude, gf.longitude, gf.street_address,
           u.name grower_name, ns.state_name, nl.lga_name, COALESCE(fv.status, 'pending') verification_status
    FROM grower_farms gf
    LEFT JOIN users u ON u.id = gf.user_id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.latitude IS NOT NULL AND gf.longitude IS NOT NULL
    ORDER BY gf.updated_at DESC, gf.id DESC
    LIMIT 500
") : [];

$visitPoints = app_table_exists($pdo, 'farm_visits') ? fmap_rows($pdo, "
    SELECT fv.id, fv.visit_latitude latitude, fv.visit_longitude longitude, fv.result, fv.visited_at,
           gf.farm_name, u.name agent_name
    FROM farm_visits fv
    LEFT JOIN grower_farms gf ON gf.id = fv.farm_id
    LEFT JOIN users u ON u.id = fv.agent_id
    WHERE fv.visit_latitude IS NOT NULL AND fv.visit_longitude IS NOT NULL
    ORDER BY fv.visited_at DESC
    LIMIT 300
") : [];

if (!$visitPoints && app_table_exists($pdo, 'field_visits')) {
    $visitPoints = fmap_rows($pdo, "
        SELECT fv.id, fv.latitude, fv.longitude, fv.status result, fv.visited_at, u.name agent_name, g.name grower_name
        FROM field_visits fv
        LEFT JOIN users u ON u.id = fv.agent_id
        LEFT JOIN users g ON g.id = fv.grower_id
        WHERE fv.latitude IS NOT NULL AND fv.longitude IS NOT NULL
        ORDER BY fv.visited_at DESC
        LIMIT 300
    ");
}

$fieldTickets = app_table_exists($pdo, 'support_tickets') ? fmap_rows($pdo, "
    SELECT t.id, t.ticket_ref, t.subject, t.description, t.priority, t.status, t.requester_name, t.created_at,
           t.linked_record_type, t.linked_record_ref, gf.latitude, gf.longitude, gf.farm_name
    FROM support_tickets t
    LEFT JOIN grower_farms gf ON t.linked_record_type IN ('farm','grower_farm','farm_id') AND CAST(t.linked_record_ref AS UNSIGNED) = gf.id
    WHERE t.category = 'field'
    ORDER BY FIELD(t.status,'open','in_progress','waiting_on_user','escalated','resolved','closed'), t.created_at DESC
    LIMIT 200
") : [];

$ticketPoints = array_values(array_filter($fieldTickets, static fn(array $row): bool => $row['latitude'] !== null && $row['longitude'] !== null));
$activeAgents = fmap_count($pdo, 'agent_locations', 'timestamp > NOW() - INTERVAL 5 MINUTE');
$openFieldTickets = fmap_count($pdo, 'support_tickets', "category='field' AND status NOT IN ('resolved','closed','rejected')");
?>
<?php admin_page_start('Field Operations Map', [
    'active' => 'agent-map.php',
    'description' => 'Map live agents, farms, field visits, and field support issues with clear operational layers.',
    'wide' => true,
    'css' => '#fieldMap{height:68vh;min-height:520px;border-radius:8px;border:1px solid var(--line)}.map-shell{display:grid;grid-template-columns:minmax(0,1fr)360px;gap:14px}.map-card{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:var(--shadow);padding:14px}.map-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}.map-kpi{background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px;box-shadow:var(--shadow)}.map-kpi small{display:block;color:var(--muted);font-weight:900;text-transform:uppercase;font-size:.72rem}.map-kpi strong{display:block;font-size:1.45rem;color:#102033;margin-top:5px}.layer-controls{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.layer-controls label{border:1px solid var(--line);border-radius:999px;padding:7px 10px;background:#fff;font-weight:850}.issue-list{display:grid;gap:9px;max-height:58vh;overflow:auto}.issue{border:1px solid #edf1f4;border-radius:8px;padding:10px;background:#fbfdfc}.issue strong{color:#102033}.badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.72rem;font-weight:900;background:#eef2f7;color:#344054}.badge.high,.badge.urgent{background:#fee4e2;color:#b42318}.badge.medium{background:#fff6dc;color:#b54708}.badge.low{background:#dcfae6;color:#067647}.muted{color:var(--muted)}@media(max-width:1100px){.map-shell{grid-template-columns:1fr}.map-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}',
]); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<div class="map-kpis">
  <div class="map-kpi"><small>Live Agents</small><strong id="agentCount"><?= number_format($activeAgents) ?></strong></div>
  <div class="map-kpi"><small>Mapped Farms</small><strong><?= number_format(count($farmPoints)) ?></strong></div>
  <div class="map-kpi"><small>Recent Visits</small><strong><?= number_format(count($visitPoints)) ?></strong></div>
  <div class="map-kpi"><small>Open Field Issues</small><strong><?= number_format($openFieldTickets) ?></strong></div>
</div>

<div class="map-shell">
  <section class="map-card">
    <div class="layer-controls">
      <label><input type="checkbox" data-layer="agents" checked> Live agents</label>
      <label><input type="checkbox" data-layer="farms" checked> Farms</label>
      <label><input type="checkbox" data-layer="visits" checked> Visits</label>
      <label><input type="checkbox" data-layer="issues" checked> Field issues</label>
      <a class="btn secondary" href="support.php?category=field&page=1">Open Field Tickets</a>
      <a class="btn secondary" href="registry/field.php">Field Agent Directory</a>
    </div>
    <div id="fieldMap"></div>
    <p class="muted" style="margin:10px 0 0">Field tickets without linked farm coordinates are listed in the queue instead of disappearing from the map.</p>
  </section>
  <aside class="map-card">
    <h3 style="margin-top:0">Field Issue Queue</h3>
    <div class="issue-list">
      <?php foreach ($fieldTickets as $ticket): ?>
        <div class="issue">
          <strong><?= e((string) $ticket['subject']) ?></strong><br>
          <span class="badge <?= e((string) $ticket['priority']) ?>"><?= e((string) $ticket['priority']) ?></span>
          <span class="badge"><?= e((string) $ticket['status']) ?></span><br>
          <small class="muted"><?= e((string) $ticket['ticket_ref']) ?> / <?= e((string) ($ticket['requester_name'] ?: 'Requester')) ?></small>
          <?php if (!empty($ticket['farm_name'])): ?><br><small><?= e((string) $ticket['farm_name']) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$fieldTickets): ?><div class="issue muted">No field support tickets yet.</div><?php endif; ?>
    </div>
  </aside>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('fieldMap').setView([9.0820, 8.6753], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
const groups = {agents:L.layerGroup().addTo(map),farms:L.layerGroup().addTo(map),visits:L.layerGroup().addTo(map),issues:L.layerGroup().addTo(map)};
const bounds = [];
const farmPoints = <?= json_encode($farmPoints, JSON_UNESCAPED_SLASHES) ?>;
const visitPoints = <?= json_encode($visitPoints, JSON_UNESCAPED_SLASHES) ?>;
const issuePoints = <?= json_encode($ticketPoints, JSON_UNESCAPED_SLASHES) ?>;
function h(value){return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}
function addCircle(group, lat, lng, color, popup, radius=8){const nlat=Number(lat), nlng=Number(lng); if(!Number.isFinite(nlat)||!Number.isFinite(nlng)) return; L.circleMarker([nlat,nlng],{radius,color,fillColor:color,fillOpacity:.78,weight:2}).addTo(group).bindPopup(popup); bounds.push([nlat,nlng]);}
farmPoints.forEach(f => addCircle(groups.farms, f.latitude, f.longitude, '#087443', `<strong>${h(f.farm_name)}</strong><br>${h(f.grower_name)}<br>${h(f.lga_name)} ${h(f.state_name)}<br>Status: ${h(f.verification_status)}`, 7));
visitPoints.forEach(v => addCircle(groups.visits, v.latitude, v.longitude, '#175cd3', `<strong>Field visit</strong><br>${h(v.farm_name || v.grower_name || 'Farm visit')}<br>Agent: ${h(v.agent_name)}<br>${h(v.visited_at)}`, 6));
issuePoints.forEach(t => addCircle(groups.issues, t.latitude, t.longitude, '#d92d20', `<strong>${h(t.subject)}</strong><br>${h(t.ticket_ref)} / ${h(t.priority)}<br>${h(t.farm_name || '')}`, 9));
async function loadAgents(){try{const res=await fetch('../api/live-agents.php',{credentials:'same-origin'});const payload=await res.json();const agents=payload.items||[];groups.agents.clearLayers();agents.forEach(agent=>addCircle(groups.agents,agent.latitude,agent.longitude,Number(agent.battery_level||100)<20?'#b42318':'#0f9f55',`<strong>${h(agent.name)}</strong><br>Battery: ${h(agent.battery_level||'?')}%<br>Last seen: ${h(agent.timestamp)}`,10));document.getElementById('agentCount').textContent=agents.length;if(bounds.length){map.fitBounds(bounds,{padding:[24,24],maxZoom:11});}}catch(error){console.error('Failed to load live agents',error);}}
document.querySelectorAll('[data-layer]').forEach(input=>input.addEventListener('change',()=>{const layer=groups[input.dataset.layer]; if(!layer) return; input.checked ? layer.addTo(map) : map.removeLayer(layer);}));
if(bounds.length){map.fitBounds(bounds,{padding:[24,24],maxZoom:11});}
loadAgents();
setInterval(loadAgents,30000);
</script>
<?php admin_page_end(); ?>