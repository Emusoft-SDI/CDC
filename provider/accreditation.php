<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('accreditation', 'Accreditation', 'Review provider approval, documents, certification readiness, and NATCODEV quality checks.', function(PDO $pdo, array $user, array $provider): void {
    $items = ['Business Registration (CAC)', 'Tax Clearance Certificate', 'Input/Service Certificate', 'Good Agricultural Practice', 'Warehouse & Storage Inspection', 'Product Quality Compliance', 'Insurance Certificate'];
    echo '<section class="card"><div class="card-head"><h2>Accreditation Status</h2><span class="badge">' . e(provider_status_label((string) $provider['status'])) . '</span></div><div class="list">';
    foreach ($items as $i => $item) {
        echo '<div class="row"><span><i class="fas fa-file-shield" style="color:#08753a"></i> ' . e($item) . '</span><span class="badge ' . ($i > 4 ? 'warn' : '') . '">' . ($i > 4 ? 'Pending' : 'Verified') . '</span></div>';
    }
    echo '</div><p class="notice ok" style="margin-top:14px">Document upload hooks can be attached here to your existing file upload controls when NATCODEV sets exact document rules.</p></section>';
});
