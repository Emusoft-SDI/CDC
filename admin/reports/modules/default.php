<?php
$exportRows = report_rows($pdo, "
    SELECT u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status, COUNT(gf.id) farms
    FROM users u
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    WHERE u.role = 'grower'
    GROUP BY u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status
    ORDER BY u.created_at DESC
    LIMIT 500
");
function render_module_table() {
    global $accreditationRate, $verificationRate, $providersApproved, $providersPending, $sellerCount, $listingCount, $openSupport;
    ?>
    <table><thead><tr><th>Metric</th><th>Value</th><th>Meaning</th></tr></thead><tbody>
      <tr><td>Grower accreditation</td><td><?= $accreditationRate ?>%</td><td>Readiness for certification and formal participation.</td></tr>
      <tr><td>Farm verification</td><td><?= $verificationRate ?>%</td><td>Ground-truth confidence across captured farms.</td></tr>
      <tr><td>Provider coverage</td><td><?= number_format($providersApproved) ?> approved / <?= number_format($providersPending) ?> pending</td><td>Input and service ecosystem strength.</td></tr>
      <tr><td>Marketplace depth</td><td><?= number_format($sellerCount) ?> sellers / <?= number_format($listingCount) ?> listings</td><td>Seller supply available to buyers.</td></tr>
      <tr><td>Open support</td><td><?= number_format($openSupport) ?></td><td>Stakeholder unresolved communication load.</td></tr>
    </tbody></table>
    <?php
}
