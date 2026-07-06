<?php
$supportRows = report_rows($pdo, "
    SELECT category, priority, status, COUNT(*) total
    FROM messages
    GROUP BY category, priority, status
    ORDER BY FIELD(priority, 'high','medium','low'), total DESC
    LIMIT 14
");
$exportRows = $supportRows;
function render_module_table() {
    global $supportRows;
    ?>
    <table><thead><tr><th>Category</th><th>Priority</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($supportRows as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['category']))) ?></td><td><?= e(ucwords((string) $row['priority'])) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?>
      <?php if (!$supportRows): ?><tr><td colspan="4">No support messages yet.</td></tr><?php endif; ?>
    </tbody></table>
    <?php
}
