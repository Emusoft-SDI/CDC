<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

/**
 * Resolve whether the parallel Admin UI (v2) shell is enabled.
 *
 * Default OFF so the legacy chrome is the production default until each area is
 * migrated and verified. Global switch = env ADMIN_UI_V2, else DB setting
 * `admin_ui_v2`. Optional per-area allow-list via env ADMIN_UI_V2_AREAS
 * (comma-separated area keys); empty means "all areas".
 */
function admin_ui_enabled(?string $area = null): bool
{
    $global = app_env_bool('ADMIN_UI_V2', false);
    if (!$global) {
        try {
            if (function_exists('admin_setting') && function_exists('db') && app_table_exists(db(), 'settings')) {
                $global = admin_setting(db(), 'admin_ui_v2', '0') === '1';
            }
        } catch (Throwable $e) {
            $global = false;
        }
    }
    if (!$global) {
        return false;
    }

    $raw = trim((string) app_env('ADMIN_UI_V2_AREAS', ''));
    if ($raw === '') {
        return true;
    }
    $areas = array_filter(array_map('trim', explode(',', $raw)), static fn (string $a): bool => $a !== '');
    return $area !== null && in_array($area, $areas, true);
}

function admin_ui_version(): string
{
    return '20261005-1';
}
