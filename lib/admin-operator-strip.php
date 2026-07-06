<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-layout.php';

function admin_workspace_operator_strip(PDO $pdo, array $options = []): string
{
    $user = current_user($pdo) ?: [];
    $name = (string) ($user['name'] ?? 'Operator');
    $role = function_exists('admin_current_platform_role') ? (admin_current_platform_role($pdo) ?? (string) ($user['role'] ?? 'admin')) : (string) ($user['role'] ?? 'admin');
    $avatar = trim((string) ($user['profile_picture'] ?? ''));
    $assetPrefix = (string) ($options['asset_prefix'] ?? '../');
    $profileHref = (string) ($options['profile_href'] ?? $assetPrefix . 'profile.php');
    $passwordHref = (string) ($options['password_href'] ?? $profileHref . '#password');
    $logoutAction = (string) ($options['logout_action'] ?? $assetPrefix . 'admin.php');
    $fallbackLogoutAction = (string) ($options['fallback_logout_action'] ?? $logoutAction);
    $searchAction = (string) ($options['search_action'] ?? '');
    $searchName = (string) ($options['search_name'] ?? 'search');
    $searchValue = (string) ($_GET[$searchName] ?? ($_GET['q'] ?? ''));
    $placeholder = (string) ($options['placeholder'] ?? 'Search this workspace');
    $title = (string) ($options['title'] ?? 'Workspace tools');
    $avatarUrl = $avatar !== '' ? (str_starts_with($avatar, 'http') ? $avatar : $assetPrefix . ltrim($avatar, '/')) : '';
    $workspaceProfileKey = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) ($options['profile_key'] ?? $title)), '_')) ?: 'workspace';
    $withWorkspaceProfile = static function (string $href) use ($workspaceProfileKey): string {
        if (!preg_match('/(^|\/)profile\.php($|[?#])/', $href) || str_contains($href, 'workspace=')) {
            return $href;
        }
        [$base, $fragment] = array_pad(explode('#', $href, 2), 2, '');
        $base .= (str_contains($base, '?') ? '&' : '?') . 'workspace=' . rawurlencode($workspaceProfileKey);
        return $fragment !== '' ? $base . '#' . $fragment : $base;
    };
    $profileHref = $withWorkspaceProfile($profileHref);
    $passwordHref = $withWorkspaceProfile($passwordHref);
    ob_start();
    ?>
    <style>
      .operator-strip{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid rgba(16,24,40,.09);border-radius:8px;box-shadow:0 10px 28px rgba(16,24,40,.06);padding:10px 12px;margin-bottom:16px}.operator-search{display:flex;align-items:center;gap:8px;flex:1;min-width:260px}.operator-search input{width:100%;border:1px solid #dce8e1;border-radius:8px;padding:10px 12px}.operator-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.operator-chip{display:flex;align-items:center;gap:9px;border:1px solid #dce8e1;border-radius:999px;padding:5px 10px;background:#fbfefd}.operator-avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:#075c34;font-weight:950;overflow:hidden}.operator-avatar img{width:100%;height:100%;object-fit:cover}.operator-chip small{display:block;color:#667085;font-size:.72rem;line-height:1}.operator-chip strong{display:block;font-size:.86rem;line-height:1.1}.operator-btn{border:1px solid #cfe2d6;background:#fff;color:#075c34;border-radius:8px;padding:8px 10px;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.operator-btn:hover{background:#e8f6ec;color:#06451f}.operator-btn.danger{border-color:#f3c1c1;color:#b42318}.operator-btn.danger:hover{background:#fff1f2;color:#912018}.operator-btn.danger.subtle{background:#fff8f8}.operator-title{font-weight:900;color:#075c34;margin-right:2px}@media(max-width:760px){.operator-search,.operator-actions{width:100%}.operator-strip{align-items:stretch}.operator-actions{justify-content:flex-start}}
    </style>
    <div class="operator-strip">
      <form class="operator-search" method="get" action="<?= e($searchAction) ?>">
        <span class="operator-title"><?= e($title) ?></span>
        <input name="<?= e($searchName) ?>" value="<?= e($searchValue) ?>" placeholder="<?= e($placeholder) ?>">
        <button class="operator-btn" type="submit">Search</button>
      </form>
      <div class="operator-actions">
        <a class="operator-btn" href="<?= e($profileHref) ?>">My Profile</a>
        <a class="operator-btn" href="<?= e($passwordHref) ?>">Password</a>
        <form method="post" action="<?= e($logoutAction) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button class="operator-btn danger" type="submit">Logout</button></form><form method="post" action="<?= e($fallbackLogoutAction) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button class="operator-btn danger subtle" type="submit">Secure Logout</button></form>
        <span class="operator-chip"><span class="operator-avatar"><?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($name, 0, 1))) ?><?php endif; ?></span><span><strong><?= e($name) ?></strong><small><?= e(status_label((string) $role)) ?></small></span></span>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
   
}