<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/../../lib/workspace-account.php';

$pageTitle = 'Registry Profile - NATCODEV';
$activeNav = 'profile';

$user = current_user($pdo);
$msg = '';
$error = '';
$profileNotice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Refresh and try again.';
    } elseif (!$user) {
        $error = 'Profile management is available only for user-backed workspace sessions.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'account_profile');
            if ($action === 'send_password_change_otp') {
                $result = workspace_account_send_password_change_otp($pdo, (int) $user['id']);
                if ($result['ok'] ?? false) {
                    $msg = $result['message'];
                } else {
                    $error = $result['message'] ?? 'Unable to send password change OTP.';
                }
            } elseif ($action === 'account_password') {
                workspace_account_change_password_with_otp(
                    $pdo,
                    (int) $user['id'],
                    trim((string) ($_POST['otp_code'] ?? '')),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['confirm_password'] ?? '')
                );
                $msg = 'Password changed successfully.';
            } else {
                workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                $msg = 'Profile updated successfully.';
            }
            $user = current_user($pdo) ?: $user;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (!$user) {
    $user = ['name' => 'Administrator', 'email' => '', 'profile_picture' => ''];
    $profileNotice = 'Profile editing is available only when signed in with a user-backed account. A general admin session can still use workspace navigation and safe logout.';
}

$accountStatus = trim((string) ($user['account_status'] ?? 'active'));
$userRole = (string) (($user['platform_role'] ?? '') ?: ($user['role'] ?? 'admin'));
$emailVerified = trim((string) ($user['email_verified_at'] ?? '')) !== '';

require __DIR__ . '/layout/header.php';
?>
<style>
  .page-header { margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
  .page-title { font-size:1.6rem; margin:0; }
  .page-subtitle { margin:6px 0 0; color:#475569; font-size:.95rem; max-width:640px; }
  .profile-grid { display:grid; grid-template-columns:1fr minmax(280px, 340px); gap:20px; margin-bottom:24px; }
  .profile-card { border:1px solid rgba(15,23,42,.08); border-radius:16px; background:#fff; overflow:hidden; }
  .profile-card header { padding:20px 22px; border-bottom:1px solid rgba(15,23,42,.08); background:#f8fafc; }
  .profile-card header h2 { margin:0; font-size:1.05rem; }
  .profile-card header p { margin:8px 0 0; color:#475569; font-size:.92rem; line-height:1.5; }
  .profile-card .card-body { padding:20px; }
  .profile-summary { display:grid; gap:12px; margin-bottom:18px; }
  .summary-item { display:flex; justify-content:space-between; border:1px solid #e2e8f0; border-radius:12px; padding:12px 14px; background:#f8fafc; }
  .summary-item strong { color:#0f172a; }
  .status-pill { display:inline-flex; align-items:center; justify-content:center; padding:6px 12px; border-radius:999px; font-size:.82rem; font-weight:700; }
  .status-pill.verified { background:#dcfce7; color:#166534; }
  .status-pill.unverified { background:#fef3c7; color:#92400e; }
  .profile-actions { display:grid; gap:12px; margin-top:16px; }
  .profile-actions a { display:inline-flex; align-items:center; justify-content:center; padding:12px 14px; border-radius:12px; text-decoration:none; border:1px solid #d1d5db; color:#0f172a; background:#f8fafc; font-size:.95rem; }
  .profile-actions a.primary { background:#0f766e; color:#fff; border-color:#0f766e; }
  .profile-notice { margin-bottom:18px; padding:16px; border-radius:14px; background:#f8fafc; border:1px solid #d1d5db; color:#334155; }
  .profile-hero { display:grid; gap:14px; margin-bottom:20px; }
  .hero-card { display:grid; grid-template-columns:60px 1fr; gap:14px; align-items:center; padding:18px; border:1px solid #e2e8f0; border-radius:16px; background:#fff; }
  .hero-avatar { width:60px; height:60px; border-radius:18px; background:#0f766e; color:#fff; display:grid; place-items:center; font-size:1.3rem; font-weight:800; }
  .hero-details strong { display:block; font-size:1.05rem; margin-bottom:6px; }
  .hero-details small { display:block; color:#475569; font-size:.92rem; }
  @media(max-width:1024px){ .profile-grid{grid-template-columns:1fr} }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">Registry Profile</h1>
      <p class="page-subtitle">Manage your registry workspace identity, security settings, and account actions for public users, internal operators, and registry workflows.</p>
<div class="profile-grid">
  <section class="profile-card">
    <header>
      <h2>Registry Profile</h2>
      <p>Keep your registry user identity, account security, and workspace exit flows aligned with the registry workspace truth.</p>
    </header>
    <div class="card-body">
      <?php if ($profileNotice): ?><div class="profile-notice"><?= rx_e($profileNotice) ?></div><?php endif; ?>
      <?php if ($msg): ?><div class="alert alert-success"><?= rx_e($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= rx_e($error) ?></div><?php endif; ?>
      <div class="profile-hero">
        <div class="hero-card">
          <div class="hero-avatar"><?= rx_e(strtoupper(substr(trim((string)$user['name']), 0, 1))) ?></div>
          <div class="hero-details">
            <strong><?= rx_e((string) $user['name']) ?></strong>
            <small><?= rx_e((string) $user['email']) ?></small>
            <small><?= rx_e(ucwords(str_replace('_', ' ', $userRole))) ?></small>
          </div>
        </div>
        <div class="profile-summary">
          <div class="summary-item"><span>Account status</span><strong><?= rx_e(ucfirst($accountStatus)) ?></strong></div>
          <div class="summary-item"><span>Email verification</span><strong class="status-pill <?= $emailVerified ? 'verified' : 'unverified' ?>"><?= $emailVerified ? 'Verified' : 'Unverified' ?></strong></div>
          <div class="summary-item"><span>Workspace truth</span><strong>Registry user session</strong></div>
        </div>
      </div>

      <?php if ($user && isset($user['id'])): ?>
        <?php workspace_account_render_profile_forms($user, 'registry', 'Registry Account Profile', 'Change Password', '../', 'otp') ?>
      <?php else: ?>
        <div class="card-body">
          <p>The current session is not associated with a registered user account. Profile and password settings cannot be updated in this mode.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <aside class="profile-card">
    <header>
      <h2>Workspace Actions</h2>
      <p>Quick registry navigation and safe exit controls.</p>
    </header>
    <div class="card-body">
      <div class="profile-actions">
        <a class="primary" href="index.php">Dashboard</a>
        <a href="users.php">Users</a>
        <a href="growers.php">Growers</a>
        <a href="applications.php">Applications</a>
        <a href="documents.php">Documents</a>
        <a href="logout.php" style="color:#b91c1c; border-color:#fecaca; background:#fef2f2;">Logout</a>
      </div>
    </div>
  </aside>
</div>

<?php
require __DIR__ . '/layout/footer.php';
