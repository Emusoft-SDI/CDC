<?php
declare(strict_types=1);

/**
 * Capability façade over the existing feature catalog (Phase 1, additive).
 *
 * Capabilities map 1:1 to admin features today. The indirection lets finer-grained
 * capabilities be introduced later without touching every call site, and it gives
 * new endpoints a stable, self-documenting permission vocabulary.
 */

function admin_capability_catalog(): array
{
    return [
        'admin.access' => 'dashboard',
        'registry.view' => 'applications',
        'registry.documents.review' => 'documents',
        'registry.certificates.manage' => 'certificates',
        'registry.field.manage' => 'field_network',
        'marketplace.manage' => 'marketplace',
        'providers.manage' => 'providers',
        'resources.allocate' => 'resource_allocation',
        'communications.manage' => 'communications',
        'wallet.manage' => 'wallet',
        'revenue.manage' => 'revenue',
        'academy.manage' => 'training',
        'support.manage' => 'support',
        'reports.view' => 'reports',
        'settings.manage' => 'settings',
        'backups.manage' => 'backups',
        'users.manage' => 'user_management',
        'imports.manage' => 'imports',
        'governance.manage' => 'governance',
        'monitoring.view' => 'monitoring',
    ];
}

function admin_capability_feature(string $capability): ?string
{
    return admin_capability_catalog()[$capability] ?? null;
}

function admin_can(PDO $pdo, string $capability): bool
{
    $feature = admin_capability_feature($capability);
    if ($feature === null) {
        return false;
    }
    return function_exists('admin_feature_is_allowed') && admin_feature_is_allowed($pdo, $feature);
}

function admin_require_capability(PDO $pdo, string $capability): void
{
    if (!admin_can($pdo, $capability)) {
        http_response_code(403);
        exit('Forbidden: missing capability ' . $capability . '.');
    }
}
