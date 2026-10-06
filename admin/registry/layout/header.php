<?php
/**
 * Registry workspace header.
 *
 * The registry now renders inside the shared NATCODEV Workspace Hub shell
 * (lib/layout/html_layout.php -> admin_page_start) so it matches admin/index.php.
 * Registry component styles are kept; only the old sidebar/topbar shell CSS was removed.
 */
$registryNavFiles = [
    'overview' => 'registry/index.php',
    'users' => 'registry/users.php',
    'growers' => 'registry/growers.php',
    'applications' => 'registry/applications.php',
    'documents' => 'registry/documents.php',
    'certificates' => 'registry/certificates.php',
    'field' => 'registry/field.php',
    'import' => 'registry/import.php',
    'profile' => 'registry/profile.php',
];
$registryActive = $registryNavFiles[(string) ($activeNav ?? 'overview')] ?? 'registry/index.php';

$registryCss = <<<'CSS'
*{margin:0;padding:0;box-sizing:border-box}
:root{--green-900:#0f2e1f;--green-800:#1a4731;--green-700:#235c3f;--green-600:#2d7a52;--green-500:#3a9d6a;--green-400:#4fc48a;--green-100:#e8f5ee;--green-50:#f0faf4;--text:#1a1a1a;--text-secondary:#6b7280;--border:#e5e7eb;--warning:#f59e0b;--info:#3b82f6;--success:#10b981}
.registry-content{padding:0}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700}
.page-subtitle{font-size:13px;color:var(--muted);margin-top:2px}
.btn{padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;text-decoration:none}
.btn-primary{background:var(--green-700);color:#fff}
.btn-primary:hover{background:var(--green-800)}
.btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--green-50)}
.btn-danger{background:var(--red);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-icon{padding:6px;background:none;border:1px solid var(--border);border-radius:6px;cursor:pointer}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:#fff;padding:20px;border-radius:12px;border:1px solid var(--border)}
.stat-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.stat-card-label{font-size:12px;color:var(--text-secondary)}
.stat-card-value{font-size:26px;font-weight:700;margin-top:4px}
.card{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:20px;box-shadow:none;padding:0}
.card-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:22px}
.card-body.p0{padding:0}
table{width:100%;border-collapse:collapse}
th,td{padding:12px 22px;text-align:left;font-size:13px}
th{background:var(--green-50);font-weight:600;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
td{border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--green-50)}
.status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.status-verified,.status-approved,.status-active{background:#dcfce7;color:#166534}
.status-pending-review,.status-under-review{background:#fef3c7;color:#92400e}
.status-rejected,.status-revoked{background:#fee2e2;color:#991b1b}
.form-group{margin-bottom:16px}
.form-label{display:block;font-size:12px;font-weight:600;margin-bottom:6px;color:var(--text-secondary)}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:#fff;border-radius:12px;width:90%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-overlay.active > .modal{display:block;position:relative;inset:auto}
.avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--green-100);color:var(--green-700);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:12px}
.avatar-row{display:flex;align-items:center;gap:10px}
.toast{position:fixed;bottom:24px;right:24px;background:var(--green-800);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:300;display:none;animation:registrySlideIn .3s}
@keyframes registrySlideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
@media(max-width:900px){.grid-2,.grid-3,.grid-4{grid-template-columns:1fr}}
CSS;

$registryRailLabels = [
    'overview' => 'Overview', 'users' => 'Users', 'growers' => 'Growers',
    'applications' => 'Applications', 'documents' => 'Documents',
    'certificates' => 'Certificates', 'field' => 'Field Network',
    'import' => 'Batch Import', 'profile' => 'Profile',
];
$registryActiveKey = (string) ($activeNav ?? 'overview');
$registryCrumb = $registryRailLabels[$registryActiveKey] ?? 'Overview';

$registryRailCss = <<<'CSS'
.registry-rail-brand{display:flex;align-items:center;gap:10px;padding-bottom:14px;margin-bottom:12px;border-bottom:1px solid rgba(255,255,255,.14);color:#fff;text-decoration:none}
.registry-rail-brand img{width:44px;height:44px;border-radius:50%;background:#fff;object-fit:contain;padding:4px}
.registry-rail-brand strong{display:block;line-height:1.1}
.registry-rail-brand span{display:block;margin-top:3px;color:#dff5e8;font-size:.72rem;font-weight:750}
.registry-rail-label{margin:14px 4px 7px;color:#aee4c4;font-size:.7rem;font-weight:950;text-transform:uppercase;letter-spacing:.03em}
.registry-rail nav{display:grid;gap:5px}
CSS;

admin_page_start(
    (string) ($pageTitle ?? 'NATCODEV Registry'),
    [
        'active' => $registryActive,
        'description' => 'National coconut registry workspace: users, growers, applications, verification and field data.',
        'wide' => true,
        'topbar_only' => true,
        'breadcrumbs' => [['label' => 'Registry Operations'], ['label' => $registryCrumb]],
        'head_html' => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">'
            . '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">'
            . '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>',
        'css' => $registryCss . $registryRailCss,
    ]
);
?>
<div class="registry-shell">
  <aside class="registry-rail" aria-label="Registry workspace navigation">
    <a class="registry-rail-brand" href="index.php">
      <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
      <strong>NATCODEV<span>National Coconut Registry</span></strong>
    </a>
    <div class="registry-rail-label">Operations</div>
    <nav aria-label="Registry operations">
      <a class="<?= $registryActiveKey === 'overview' ? 'active' : '' ?>" href="index.php"><span><i class="fas fa-table-columns"></i> Overview</span></a>
      <a class="<?= $registryActiveKey === 'users' ? 'active' : '' ?>" href="users.php"><span><i class="fas fa-users"></i> Users</span></a>
      <a class="<?= $registryActiveKey === 'growers' ? 'active' : '' ?>" href="growers.php"><span><i class="fas fa-seedling"></i> Growers</span></a>
      <a class="<?= $registryActiveKey === 'applications' ? 'active' : '' ?>" href="applications.php"><span><i class="fas fa-file-lines"></i> Applications</span></a>
    </nav>
    <div class="registry-rail-label">Verification</div>
    <nav aria-label="Registry verification">
      <a class="<?= $registryActiveKey === 'documents' ? 'active' : '' ?>" href="documents.php"><span><i class="fas fa-file-shield"></i> Documents</span></a>
      <a class="<?= $registryActiveKey === 'certificates' ? 'active' : '' ?>" href="certificates.php"><span><i class="fas fa-certificate"></i> Certificates</span></a>
    </nav>
    <div class="registry-rail-label">Field &amp; Data</div>
    <nav aria-label="Registry field and data">
      <a class="<?= $registryActiveKey === 'field' ? 'active' : '' ?>" href="field.php"><span><i class="fas fa-map-location-dot"></i> Field Network</span></a>
      <a class="<?= $registryActiveKey === 'import' ? 'active' : '' ?>" href="import.php"><span><i class="fas fa-file-import"></i> Batch Import</span></a>
    </nav>
    <div class="registry-rail-label">Account</div>
    <nav aria-label="Registry account">
      <a class="<?= $registryActiveKey === 'profile' ? 'active' : '' ?>" href="profile.php"><span><i class="fas fa-user-gear"></i> Profile</span></a>
      <a href="../index.php"><span><i class="fas fa-gauge-high"></i> Workspace Hub</span></a>
      <a href="logout.php"><span><i class="fas fa-right-from-bracket"></i> Logout</span></a>
    </nav>
  </aside>
  <div class="registry-content">
