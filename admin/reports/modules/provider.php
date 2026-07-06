<?php
$providerRows = report_rows($pdo, "
    SELECT provider_type, status, COUNT(*) total
    FROM provider_registry
    GROUP BY provider_type, status
    ORDER BY provider_type, status
");
$exportRows = $providerRows;
function render_module_table() {
    global $providerRows;
    ?>
    <table><thead><tr><th>Provider Type</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($providerRows as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['provider_type']))) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?>
      <?php if (!$providerRows): ?><tr><td colspan="3">No provider records yet.</td></tr><?php endif; ?>
    </tbody></table>
    <?php
}
