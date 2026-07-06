<?php
$fieldRows = report_rows($pdo, "
    SELECT u.name agent, COUNT(fv.id) visits,
           SUM(CASE WHEN ft.status IN ('pending','assigned','in_progress') THEN 1 ELSE 0 END) open_tasks
    FROM users u
    LEFT JOIN farm_visits fv ON fv.agent_id = u.id AND fv.visited_at BETWEEN ? AND ?
    LEFT JOIN field_tasks ft ON ft.assigned_to = u.id
    WHERE u.role = 'field_agent' OR u.is_agronomist = 1 OR u.is_extensionist = 1
    GROUP BY u.id, u.name
    ORDER BY visits DESC, open_tasks DESC
    LIMIT 12
", $dateParams);
$exportRows = $fieldRows;
function render_module_table() {
    global $fieldRows;
    ?>
    <table><thead><tr><th>Agent</th><th>Visits In Period</th><th>Open Tasks</th></tr></thead><tbody>
      <?php foreach ($fieldRows as $row): ?><tr><td><strong><?= e($row['agent']) ?></strong></td><td><?= (int) $row['visits'] ?></td><td><?= (int) $row['open_tasks'] ?></td></tr><?php endforeach; ?>
      <?php if (!$fieldRows): ?><tr><td colspan="3">No field team activity yet.</td></tr><?php endif; ?>
    </tbody></table>
    <?php
}
