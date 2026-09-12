<?php
$stateRows = report_rows($pdo, "
    SELECT {$areaNameSql} area_name,
           COUNT(DISTINCT u.id) farmers,
           COUNT(DISTINCT gf.id) farms,
           COALESCE(SUM(gf.farm_size), 0) hectares,
           SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND {$locationFilterSql}
    GROUP BY {$areaNameSql}
    ORDER BY farmers DESC, area_name
    LIMIT 20
", $locationFilterParams);
$stateRows = is_array($stateRows ?? null) ? $stateRows : [];
$exportRows = $stateRows;
function render_module_table() {
    global $stateRows, $areaLabel;
    $rows = is_array($stateRows ?? null) ? $stateRows : [];
    ?>
    <table>
      <thead><tr><th><?= e($areaLabel) ?></th><th>Growers</th><th>Farms</th><th>Hectares</th><th>Accredited</th><th>Accreditation Rate</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr><td><strong><?= e($row['area_name']) ?></strong></td><td><?= (int) $row['farmers'] ?></td><td><?= (int) $row['farms'] ?></td><td><?= number_format((float) $row['hectares'], 1) ?></td><td><?= (int) $row['accredited'] ?></td><td><?= report_pct((float) $row['accredited'], (float) $row['farmers']) ?>%</td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6">No state intelligence available yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php
}
