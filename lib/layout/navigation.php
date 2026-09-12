<?php

function admin_pagination_offset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}


function admin_pagination_controls(int $total, int $page, int $perPage, array $extra = []): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    $base = array_merge($_GET, $extra);
    unset($base['page'], $base['per_page']);
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    $url = static function (int $targetPage, int $targetPerPage) use ($base): string {
        return '?' . http_build_query($base + ['page' => $targetPage, 'per_page' => $targetPerPage]);
    };

    ob_start();
    ?>
    <form class="pagination" method="get">
      <?php foreach ($base as $key => $value): ?>
        <?php if (is_scalar($value)): ?><input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div class="meta">Showing <?= (int) $from ?>-<?= (int) $to ?> of <?= (int) $total ?></div>
      <div class="pagination-links">
        <a class="button secondary" href="<?= e($url(max(1, $page - 1), $perPage)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
        <span class="meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <a class="button secondary" href="<?= e($url(min($pages, $page + 1), $perPage)) ?>" aria-disabled="<?= $page >= $pages ? 'true' : 'false' ?>">Next</a>
      </div>
      <label class="pagination-size">Rows
        <select name="per_page" onchange="this.form.page.value='1'; this.form.submit()">
          <?php foreach ([10, 25, 50, 100, 200, 500] as $size): ?>
            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="page" value="<?= (int) $page ?>">
    </form>
    <?php
    return (string) ob_get_clean();
}


function admin_nav_groups(): array
{
    return [
        'Dashboards' => [
            ['href' => 'index.php', 'label' => 'Workspace Hub', 'feature' => 'dashboard'],
            ['href' => 'operations/', 'label' => 'Role Dashboard', 'feature' => 'dashboard'],
            ['href' => 'operations/?page=state', 'label' => 'State Dashboard', 'feature' => 'state_dashboard'],
            ['href' => 'operations/?page=national', 'label' => 'National Dashboard', 'feature' => 'national_dashboard'],
        ],
        'Registry Operations' => [
            ['href' => 'registry/', 'label' => 'Registry Workspace', 'feature' => 'applications'],
            ['href' => 'admin.php', 'label' => 'Legacy Applications', 'feature' => 'applications', 'super_only' => true],
            ['href' => 'document-verification.php', 'label' => 'Documents', 'feature' => 'documents'],
            ['href' => 'bulk-verification.php', 'label' => 'Bulk Review', 'feature' => 'documents'],
            ['href' => 'identity_gateways.php', 'label' => 'Identity & KYC Gateways', 'feature' => 'documents', 'super_only' => true],
            ['href' => 'certificate-batch-verification.php', 'label' => 'Batch Certificate Verify', 'feature' => 'certificates'],
        ],
        'Support Desk' => [
            ['href' => 'support/', 'label' => 'Support Console', 'feature' => 'support'],
        ],
        'HR & People' => [
            ['href' => 'users.php', 'label' => 'Users & Roles', 'feature' => 'user_management', 'super_only' => true],
            ['href' => 'recruitment.php', 'label' => 'Recruitment', 'feature' => 'field_network'],
            ['href' => 'import-users.php', 'label' => 'Import & Engagement', 'feature' => 'imports', 'super_only' => true],
        ],
        'Field Network' => [
            ['href' => 'agent-map.php', 'label' => 'Agent Map', 'feature' => 'field_network'],
            ['href' => 'fields-management.php', 'label' => 'Fields Management', 'feature' => 'field_management'],
            ['href' => 'agronomy.php', 'label' => 'Agronomy Advisory', 'feature' => 'agronomy_advisory'],
            ['href' => 'assign-growers.php', 'label' => 'Assignments', 'feature' => 'field_network'],
        ],
        'Insights & Reports' => [
            ['href' => 'analytics.php', 'label' => 'Analytics', 'feature' => 'analytics'],
            ['href' => 'reports/', 'label' => 'Reporting Intelligence', 'feature' => 'reports'],
            ['href' => 'demographics.php', 'label' => 'Demographics', 'feature' => 'analytics'],
            ['href' => 'validation-stats.php', 'label' => 'Validation Stats', 'feature' => 'analytics'],
        ],
        'Marketplace & Providers' => [
            ['href' => 'marketplace/', 'label' => 'Marketplace', 'feature' => 'marketplace'],
            ['href' => 'providers.php', 'label' => 'Input & Service Providers', 'feature' => 'providers'],
            ['href' => 'resource-allocation.php', 'label' => 'Resource Allocation', 'feature' => 'resource_allocation'],
        ],
        'Wallet & Payments' => [
            ['href' => 'wallet/', 'label' => 'Wallet Workspace', 'feature' => 'wallet'],
            ['href' => 'revenue/', 'label' => 'Revenue & Monetization', 'feature' => 'revenue'],
            ['href' => 'reports.php?report=finance', 'label' => 'Finance Reports', 'feature' => 'reports'],
        ],
        'Communication & Content' => [
            ['href' => 'communications.php', 'label' => 'Communication Hub', 'feature' => 'communications'],
            ['href' => 'news.php', 'label' => 'News & Desk Releases', 'feature' => 'communications'],
            ['href' => 'sms_gateways.php', 'label' => 'SMS & WhatsApp Gateways', 'feature' => 'communications', 'super_only' => true],
            ['href' => 'notifications.php', 'label' => 'Notification Log', 'feature' => 'notifications'],
        ],
        'Learning & Training' => [
            ['href' => 'resources.php', 'label' => 'Learning Resources', 'feature' => 'resources'],
            ['href' => 'academy/', 'label' => 'NATCODEV Academy', 'feature' => 'training'],
            ['href' => '../super-admin/index.php?view=controls', 'label' => 'Training Governance Policy', 'feature' => 'training', 'super_only' => true],
        ],
        'Governance & Compliance' => [
            ['href' => 'governance.php', 'label' => 'Policies & Governance', 'feature' => 'governance'],
            ['href' => 'production-readiness.php', 'label' => 'Production Readiness', 'feature' => 'production_readiness'],
            ['href' => 'monitoring.php', 'label' => 'System Health', 'feature' => 'monitoring'],
            ['href' => 'backups.php', 'label' => 'Backup & Recovery', 'feature' => 'backups'],
        ],
        'System Settings' => [
            ['href' => 'settings/', 'label' => 'Operational Settings', 'feature' => 'settings'],
            ['href' => 'templates.php', 'label' => 'Message Templates', 'feature' => 'templates'],
            ['href' => '../super-admin/index.php?view=controls', 'label' => 'Module Setup', 'feature' => 'integrations', 'super_only' => true],
        ],
    ];
}


function admin_allowed_nav_groups(PDO $pdo): array
{
    $groups = [];
    foreach (admin_nav_groups() as $groupLabel => $items) {
        $allowedItems = array_values(array_filter($items, static function (array $item) use ($pdo): bool {
            if (!empty($item['super_only']) && !admin_current_user_is_super_admin($pdo)) {
                return false;
            }
            return admin_feature_is_allowed($pdo, (string) ($item['feature'] ?? 'dashboard'));
        }));
        if ($allowedItems) {
            $groups[$groupLabel] = $allowedItems;
        }
    }

    return $groups;
}


function admin_footer_nav_items(PDO $pdo): array
{
    $items = [
        ['href' => 'admin.php', 'label' => 'Legacy Applications', 'feature' => 'applications', 'super_only' => true],
        ['href' => 'document-verification.php', 'label' => 'Documents', 'feature' => 'documents'],
        ['href' => 'support/', 'label' => 'Support Desk', 'feature' => 'support'],
        ['href' => 'reports/', 'label' => 'Reports', 'feature' => 'reports'],
        ['href' => 'settings/', 'label' => 'Settings', 'feature' => 'settings'],

    ];

    return array_values(array_filter($items, static function (array $item) use ($pdo): bool {
        if (!empty($item['super_only']) && !admin_current_user_is_super_admin($pdo)) {
            return false;
        }

        return admin_feature_is_allowed($pdo, (string) ($item['feature'] ?? 'dashboard'));
    }));
}
