<?php defined('NATCODEV_SUPPORT_WORKSPACE_VIEW') || exit; ?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<style>
  html, body, .admin-shell { width:100%; max-width:none; }
  body { overflow-x:hidden; }
  main.admin-main { max-width:none !important; width:100vw !important; margin:0 !important; padding:18px !important; }
  .sd-workspace { grid-template-columns:248px minmax(0,1fr) !important; width:100%; max-width:none; margin:0; }
  .sd-content, .sd-shell, .sd-panel { min-width:0; }
  .sd-kpis { grid-template-columns:repeat(6,minmax(120px,1fr)) !important; }
  .sd-kpi { min-height:130px !important; padding:12px !important; }
  .sd-kpi strong { font-size:clamp(1.05rem,1.6vw,1.45rem) !important; overflow-wrap:anywhere; }
  .sd-kpi span { font-size:.74rem !important; }
  .sd-grid { grid-template-columns:minmax(0,1.35fr) minmax(260px,.75fr) !important; }
  .sd-grid > .sd-panel:first-child { grid-column:1; grid-row:1 / span 2; }
  .sd-lower { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)) !important; }
  .sd-filter { grid-template-columns:repeat(auto-fit,minmax(128px,1fr)) !important; align-items:end; }
  .sd-table { table-layout:auto; }
  .sd-table th, .sd-table td { overflow-wrap:anywhere; }
  .sd-priorities { grid-template-columns:1fr !important; }
  .support-work { grid-template-columns:minmax(260px,420px) minmax(0,1fr) !important; }
  .support-topbar { position:sticky; top:0; z-index:30; display:flex; align-items:center; justify-content:space-between; gap:14px; margin:-18px -18px 18px; padding:12px 18px; background:rgba(255,255,255,.96); border-bottom:1px solid rgba(16,24,40,.1); box-shadow:0 10px 28px rgba(16,24,40,.08); backdrop-filter:blur(12px); }
  .support-topbrand { display:flex; align-items:center; gap:11px; min-width:240px; color:#063f24; text-decoration:none; font-weight:950; }
  .support-topbrand img { width:42px; height:42px; border-radius:50%; border:1px solid var(--line); object-fit:contain; background:#fff; padding:3px; }
  .support-topbrand span { display:block; color:#5f6f63; font-size:.76rem; font-weight:800; margin-top:2px; }
  .support-topnav { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
  .support-toplink, .support-menu summary { display:inline-flex; align-items:center; gap:7px; min-height:38px; padding:9px 11px; border-radius:8px; border:1px solid var(--line); background:#fff; color:#102033; font-weight:900; text-decoration:none; box-shadow:none; cursor:pointer; }
  .support-toplink.primary { background:#067647; color:#fff; border-color:#067647; }
  .support-toplink:hover, .support-menu summary:hover { background:#eef8f0; color:#063f24; text-decoration:none; }
  .support-toplink.primary:hover { background:#005b32; color:#fff; }
  .support-menu { position:relative; }
  .support-menu summary { list-style:none; }
  .support-menu summary::-webkit-details-marker { display:none; }
  .support-menu summary:before { content:""; width:15px; height:11px; background:linear-gradient(#102033,#102033) 0 0/100% 2px no-repeat,linear-gradient(#102033,#102033) 0 50%/100% 2px no-repeat,linear-gradient(#102033,#102033) 0 100%/100% 2px no-repeat; }
  .support-menu[open] summary { background:#eef8f0; color:#063f24; }
  .support-menu-panel { position:absolute; left:0; top:calc(100% + 8px); width:min(320px,calc(100vw - 28px)); display:grid; gap:5px; padding:10px; border:1px solid rgba(16,24,40,.12); border-radius:8px; background:#fff; box-shadow:0 22px 42px rgba(16,24,40,.18); }
  .support-menu-panel a { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 11px; border-radius:7px; color:#102033; text-decoration:none; font-weight:850; }
  .support-menu-panel a:hover { background:#f1faf5; color:#063f24; }
  .support-menu-panel small { color:var(--muted); font-weight:750; }
  .support-layout { display:grid; grid-template-columns:260px minmax(0,1fr); gap:18px; align-items:start; }
  .support-rail { position:sticky; top:74px; min-height:calc(100vh - 96px); border-radius:8px; background:linear-gradient(180deg,#063f24,#005b32); color:#fff; padding:16px; box-shadow:0 18px 42px rgba(6,63,36,.22); }
  .support-rail-brand { display:flex; align-items:center; gap:10px; padding-bottom:14px; margin-bottom:12px; border-bottom:1px solid rgba(255,255,255,.14); color:#fff; text-decoration:none; }
  .support-rail-brand img { width:44px; height:44px; border-radius:50%; background:#fff; object-fit:contain; padding:4px; }
  .support-rail-brand strong { display:block; line-height:1.1; }
  .support-rail-brand span { display:block; margin-top:3px; color:#dff5e8; font-size:.72rem; font-weight:750; }
  .support-rail-section { margin-top:14px; }
  .support-rail-label { margin:0 4px 7px; color:#aee4c4; font-size:.7rem; font-weight:950; text-transform:uppercase; letter-spacing:.03em; }
  .support-rail-nav { display:grid; gap:5px; }
  .support-rail-nav a { display:flex; align-items:center; justify-content:space-between; gap:10px; color:#f8fffb; padding:10px 11px; border-radius:8px; text-decoration:none; font-weight:850; }
  .support-rail-nav a span:first-child { display:inline-flex; align-items:center; gap:9px; min-width:0; }
  .support-rail-nav a:hover { background:rgba(255,255,255,.12); color:#fff; text-decoration:none; }
  .support-rail-nav a.active { background:#fff; color:#06451f; box-shadow:0 10px 22px rgba(0,0,0,.12); }
  .support-rail-count { min-width:24px; padding:2px 7px; border-radius:999px; background:#0ea765; color:#fff; font-size:.74rem; text-align:center; font-weight:950; }
  .support-rail-count.warn { background:#f79009; }
  .support-rail-count.bad { background:#d92d20; }
  .support-rail-nav a.active .support-rail-count { background:#e8f5ed; color:#06451f; }
  .support-rail-foot { margin-top:16px; padding-top:12px; border-top:1px solid rgba(255,255,255,.14); display:grid; gap:7px; }
  .support-rail-user { margin-top:14px; border:1px solid rgba(255,255,255,.16); border-radius:8px; padding:11px; display:flex; gap:10px; align-items:center; background:rgba(255,255,255,.07); }
  .support-rail-user small { display:block; color:#dff5e8; font-weight:700; }
  .support-main { min-width:0; }
  .support-nav { display:none !important; }
  .support-control-center > .card { border-radius:8px; }
  @media (max-width:1180px) { .support-layout { grid-template-columns:1fr; } .support-rail { position:relative; top:auto; min-height:auto; } .support-rail-nav { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  @media (max-width:980px) {
    main.admin-main { padding:12px !important; width:100% !important; }
    .support-topbar { margin:-12px -12px 12px; align-items:flex-start; flex-direction:column; }
    .support-topbrand { min-width:0; }
    .support-topnav { width:100%; justify-content:flex-start; }
    .sd-kpis { grid-template-columns:repeat(auto-fit,minmax(150px,1fr)) !important; }
    .sd-workspace, .sd-grid, .support-work, .support-rail-nav { grid-template-columns:1fr !important; }
    .sd-grid > .sd-panel:first-child { grid-column:auto; grid-row:auto; }
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('main.admin-main');
    if (main) {
      main.style.maxWidth = 'none';
      main.style.width = '100vw';
      main.style.margin = '0';
    }
  });
</script>

<header class="support-topbar" aria-label="Support workspace top bar">
  <a class="support-topbrand" href="index.php">
    <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
    <strong>NATCODEV Support<span>Admin support workspace</span></strong>
  </a>
  <nav class="support-topnav" aria-label="Support quick navigation"><a class="support-toplink primary" href="<?= e(sd_url(['view' => 'overview', 'status' => 'active', 'scope' => 'all'])) ?>">Workspace</a>
    <a class="support-toplink" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'assigned'])) ?>">My Queue</a>
    <a class="support-toplink" href="<?= e(sd_url(['view' => 'teams'])) ?>">Teams</a>
    <a class="support-toplink" href="logout.php">Logout</a>
  </nav>
</header>

<div class="support-layout">
  <aside class="support-rail" aria-label="Support workspace side menu">
    <a class="support-rail-brand" href="<?= e(sd_url(['view' => 'overview', 'status' => 'active', 'scope' => 'all'])) ?>">
      <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
      <strong>NATCODEV Support<span>Control center</span></strong>
    </a>

    <div class="support-rail-section">
      <div class="support-rail-label">Ticket Queues</div>
      <nav class="support-rail-nav" aria-label="Ticket queues">
        <a class="<?= $workspaceView === 'overview' && $filterScope === 'all' && $filterStatus === 'active' && $filterCategory === '' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'overview', 'status' => 'active', 'scope' => 'all'])) ?>"><span><i class="fa-solid fa-table-columns"></i> Overview</span></a>
        <a class="<?= $filterScope === 'all' && $filterStatus === 'active' && $filterCategory === '' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active', 'scope' => 'all'])) ?>"><span><i class="fa-solid fa-ticket"></i> All Active Tickets</span><b class="support-rail-count"><?= (int) ($stats['open_count'] ?? 0) ?></b></a>
        <a class="<?= $filterScope === 'unassigned' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'unassigned'])) ?>"><span><i class="fa-solid fa-user-plus"></i> Unassigned</span><b class="support-rail-count warn"><?= $unassignedCount ?></b></a>
        <a class="<?= $filterScope === 'assigned' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'assigned'])) ?>"><span><i class="fa-solid fa-user-check"></i> Assigned To Me</span><b class="support-rail-count"><?= $myAssignedCount ?></b></a>
        <a class="<?= $filterStatus === 'escalated' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'escalations', 'status' => 'escalated', 'scope' => 'all'])) ?>"><span><i class="fa-solid fa-triangle-exclamation"></i> Escalations</span><b class="support-rail-count bad"><?= (int) ($stats['overdue_count'] ?? 0) ?></b></a>
      </nav>
    </div>

    <div class="support-rail-section">
      <div class="support-rail-label">Work Areas</div>
      <nav class="support-rail-nav" aria-label="Support work areas">
        <a class="<?= $workspaceView === 'messages' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'messages'])) ?>"><span><i class="fa-regular fa-message"></i> Messages</span><b class="support-rail-count"><?= count($timelineRows) ?></b></a>
        <a class="<?= $filterCategory === 'general' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'complaints', 'category' => 'general', 'status' => 'active'])) ?>"><span><i class="fa-solid fa-bullhorn"></i> Complaints</span><b class="support-rail-count warn"><?= (int) ($stats['complaint_count'] ?? 0) ?></b></a>
        <a class="<?= $filterCategory === 'field' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'field', 'category' => 'field', 'status' => 'active'])) ?>"><span><i class="fa-solid fa-location-dot"></i> Field Issues</span><b class="support-rail-count"><?= (int) ($stats['field_count'] ?? 0) ?></b></a>
        <a href="<?= e(sd_url(['view' => 'knowledge'])) ?>"><span><i class="fa-solid fa-book-open"></i> Knowledge Base</span></a>
      </nav>
    </div>

    <div class="support-rail-section">
      <div class="support-rail-label">Administration</div>
      <nav class="support-rail-nav" aria-label="Support administration">
        <a class="<?= $workspaceView === 'teams' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'teams'])) ?>"><span><i class="fa-solid fa-people-group"></i> Support Teams</span><b class="support-rail-count"><?= count($customTeamRows) ?></b></a>
        <a href="<?= e(sd_url(['view' => 'sla'])) ?>"><span><i class="fa-solid fa-chart-line"></i> SLA Reports</span></a>
        <a href="<?= e(sd_url(['view' => 'settings'])) ?>"><span><i class="fa-solid fa-gear"></i> Settings</span></a>
      </nav>
    </div>

    <div class="support-rail-foot">
      <nav class="support-rail-nav" aria-label="Support exits">
        <a href="<?= e(sd_url(['view' => 'overview', 'status' => 'active', 'scope' => 'all'])) ?>"><span><i class="fa-solid fa-house"></i> Support Home</span></a>
        <a href="<?= e(sd_url(['view' => 'public-entry'])) ?>"><span><i class="fa-solid fa-life-ring"></i> Public Entry</span></a>
        <a href="logout.php"><span><i class="fa-solid fa-right-from-bracket"></i> Logout</span></a>
      </nav>
      <div class="support-rail-user">
        <div class="sd-avatar"><?= e(strtoupper(substr((string) ($admin['name'] ?? 'A'), 0, 1))) ?></div>
        <div><strong><?= e((string) (($admin['name'] ?? '') ?: 'Support Admin')) ?></strong><small><?= e((string) (($admin['platform_role'] ?? '') ?: ($admin['role'] ?? 'admin'))) ?></small></div>
      </div>
    </div>
  </aside>

  <main class="support-main">
<div class="support-control-center">
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
          <div class="text-uppercase small fw-bold text-success">NATCODEV Platform Operations</div>
          <h1 class="h3 mb-1">Support Desk Control Center</h1>
          <p class="text-muted mb-0">Operate tickets, teams, SLA, complaints, field issues, messages, and support intelligence from one admin workspace.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-success" href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active'])) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Work Tickets</a>
          <a class="btn btn-outline-success" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'unassigned'])) ?>"><i class="fa-solid fa-user-plus me-1"></i> Assign Agent</a>
          <a class="btn btn-outline-danger" href="<?= e(sd_url(['view' => 'escalations', 'status' => 'escalated', 'scope' => 'all'])) ?>"><i class="fa-solid fa-arrow-up me-1"></i> Escalations</a>
          <a class="btn btn-outline-secondary" href="<?= e(sd_url(['view' => 'sla'])) ?>"><i class="fa-solid fa-chart-line me-1"></i> SLA Snapshot</a>
        </div>
      </div>
      <form class="row g-2 mt-3" method="get" action="<?= e(sd_url([])) ?>">
        <div class="col-lg-6"><input class="form-control" type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Search tickets, users, topics, or references"></div>
        <div class="col-sm-6 col-lg-2"><select class="form-select" name="status"><option value="active">All Active</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-6 col-lg-2"><select class="form-select" name="scope"><option value="all" <?= $filterScope === 'all' ? 'selected' : '' ?>>All Ownership</option><option value="unassigned" <?= $filterScope === 'unassigned' ? 'selected' : '' ?>>Unassigned</option><option value="groups" <?= $filterScope === 'groups' ? 'selected' : '' ?>>Assigned Teams</option><option value="assigned" <?= $filterScope === 'assigned' ? 'selected' : '' ?>>Assigned To Me</option></select></div>
        <div class="col-lg-2"><button class="btn btn-dark w-100" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i> Search</button></div>
      </form>
    </div>
  </div>

  <?php
  $supportDedicatedViews = ['knowledge', 'sla', 'settings', 'public-entry', 'field', 'messages', 'complaints'];
  if (in_array($workspaceView, $supportDedicatedViews, true)):
  ?>
  <div class="sd-shell">
    <?php if ($workspaceView === 'knowledge'): ?>
      <section class="sd-panel" id="knowledge-suggestions"><div class="sd-head"><h3>Knowledge Base Suggestions</h3><a href="<?= e(sd_url(['view' => 'overview'])) ?>">Back to Overview</a></div><div class="sd-list"><?php foreach ($knowledgeRows as $row): $cat = $categories[(string) $row['category']] ?? $categories['general']; ?><div class="sd-list-row"><div><strong><?= e((string) $cat['label']) ?></strong><small>Last support activity <?= e(sd_short_date((string) $row['last_seen'])) ?></small></div><strong><?= (int) $row['total'] ?></strong></div><?php endforeach; ?><?php if (!$knowledgeRows): ?><p class="empty">No knowledge base candidates yet.</p><?php endif; ?></div></section>
    <?php elseif ($workspaceView === 'sla'): ?>
      <section class="sd-panel" id="support-sla"><div class="sd-head"><h3>SLA Reports Snapshot</h3><a href="<?= e(sd_url(['view' => 'escalations', 'status' => 'escalated'])) ?>">Escalations</a></div><div class="sd-kpis"><div class="sd-kpi"><div><small>Open Tickets</small><strong><?= (int) ($stats['open_count'] ?? 0) ?></strong><span>Active load</span></div><div class="sd-icon"><i class="fa-solid fa-headset"></i></div></div><div class="sd-kpi"><div><small>Overdue SLA</small><strong><?= (int) ($stats['overdue_count'] ?? 0) ?></strong><span>Needs attention</span></div><div class="sd-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div></div><div class="sd-kpi"><div><small>Avg Response</small><strong><?= e(sd_minutes_label($stats['avg_response_minutes'] !== null ? (int) $stats['avg_response_minutes'] : null)) ?></strong><span>First response</span></div><div class="sd-icon"><i class="fa-solid fa-stopwatch"></i></div></div></div></section>
    <?php elseif ($workspaceView === 'settings'): ?>
      <section class="sd-panel" id="support-settings"><div class="sd-head"><h3>Support Workspace Settings</h3><a href="<?= e(sd_url(['view' => 'teams'])) ?>">Teams</a></div><div class="sd-list"><div class="sd-list-row"><div><strong>Routing</strong><small>Queue, assignment, escalation, messages, complaints, and field issues are administered inside this workspace.</small></div><span class="sd-badge ok">Contained</span></div><div class="sd-list-row"><div><strong>SLA policy</strong><small>Use the SLA page and escalation queue before changing global policy elsewhere.</small></div><span class="sd-badge warn">Operator review</span></div></div></section>
    <?php elseif ($workspaceView === 'public-entry'): ?>
      <section class="sd-panel" id="support-public-entry"><div class="sd-head"><h3>Public Entry Governance</h3><a href="<?= e(sd_url(['view' => 'tickets'])) ?>">Tickets</a></div><div class="sd-list"><div class="sd-list-row"><div><strong>Public support portal</strong><small>Users create, track, reply to, and rate tickets from the public support page. Admins resolve them here.</small></div><span class="sd-badge info">Public-facing</span></div><div class="sd-list-row"><div><strong>Ticket access rule</strong><small>Anonymous visitors must know ticket reference and requester email. Logged-in users only see their own tickets.</small></div><span class="sd-badge ok">Protected</span></div></div></section>
    <?php elseif ($workspaceView === 'field'): ?>
      <section class="sd-panel" id="field-issues-overview"><div class="sd-head"><h3>Field Issues Overview</h3><a href="<?= e(sd_url(['view' => 'tickets', 'category' => 'field', 'status' => 'active'])) ?>">Field Tickets</a></div><div class="sd-map"><span>Nigeria Field Support Map</span></div><div class="sd-list-row"><div><strong>Total Open Field Issues</strong><small>Farm visits, evidence, and field operations</small></div><strong><?= (int) ($stats['field_count'] ?? 0) ?></strong></div><?php foreach ($fieldRows as $row): ?><div class="sd-list-row"><span><?= e((string) ($row['linked_record_ref'] ?: 'Unlinked field request')) ?></span><strong><?= (int) $row['total'] ?></strong></div><?php endforeach; ?></section>
    <?php elseif ($workspaceView === 'messages'): ?>
      <section class="sd-panel"><div class="sd-head"><h3>Communication Timeline</h3><a href="<?= e(sd_url(['view' => 'tickets'])) ?>">Tickets</a></div><div class="sd-timeline"><?php foreach ($timelineRows as $row): ?><div class="sd-timeline-row"><div class="sd-dot"><i class="fa-solid fa-message"></i></div><div><strong><?= e((string) $row['author_name']) ?></strong> <span class="meta"><?= e(sd_when((string) $row['created_at'])) ?></span><small class="meta"><?= e((string) $row['ticket_ref']) ?> - <?= e(mb_substr((string) $row['message'], 0, 120)) ?></small></div></div><?php endforeach; ?><?php if (!$timelineRows): ?><p class="empty">No recent support messages.</p><?php endif; ?></div></section>
    <?php elseif ($workspaceView === 'complaints'): ?>
      <section class="sd-panel"><div class="sd-head"><h3>Complaints Queue</h3><a href="<?= e(sd_url(['view' => 'overview'])) ?>">Overview</a></div><table class="sd-table"><thead><tr><th>Ticket</th><th>Requester</th><th>Subject</th><th>Status</th></tr></thead><tbody><?php foreach ($tickets as $ticket): ?><tr><td><a href="<?= e(sd_ticket_url((string) $ticket['ticket_ref'])) ?>"><?= e((string) $ticket['ticket_ref']) ?></a></td><td><?= e((string) $ticket['requester_name']) ?></td><td><?= e((string) $ticket['subject']) ?></td><td><span class="sd-badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="4">No complaint tickets found.</td></tr><?php endif; ?></tbody></table></section>
    <?php endif; ?>
  </div>
</div>
  </main>
</div>
<?php admin_page_end(); return; ?>
<?php endif; ?>
  <div class="support-body">
<div class="sd-shell">
<style>.sd-shell>.sd-top{order:1}.sd-shell>.sd-kpis{order:2}.sd-shell>#support-teams{order:3}.sd-shell>#ticket-workbench{order:6}.sd-shell>#ticket-workbench.is-focused{order:4;border-color:#087443;box-shadow:0 18px 48px rgba(8,116,67,.16)}.sd-shell>.sd-grid{order:5}.sd-shell>.sd-lower{order:6}.sd-shell>.sd-actions{order:7}#ticket-workbench.is-focused .sd-head h3:after{content:"Focused resolution";margin-left:10px;font-size:.72rem;color:#087443;background:#e8f5ed;border-radius:999px;padding:4px 8px;vertical-align:middle}</style>
  <div class="sd-top">
    <div class="sd-title">
      <h2>NATCODEV Support Desk</h2>
      <p>Deliver fast, empathetic, and effective support across all user groups and channels.</p>
    </div>
    <div class="sd-date"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></div>
  </div>

  <section class="sd-kpis">
    <div class="sd-kpi"><div><small>Open Tickets</small><strong><?= (int) ($stats['open_count'] ?? 0) ?></strong><span>Active support load</span></div><div class="sd-icon"><i class="fa-solid fa-headset"></i></div></div>
    <div class="sd-kpi"><div><small>Overdue SLA</small><strong><?= (int) ($stats['overdue_count'] ?? 0) ?></strong><span>Needs immediate action</span></div><div class="sd-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
    <div class="sd-kpi"><div><small>Resolved Today</small><strong><?= (int) ($stats['resolved_today'] ?? 0) ?></strong><span>Closed with outcome</span></div><div class="sd-icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="sd-kpi"><div><small>Field Issues</small><strong><?= (int) ($stats['field_count'] ?? 0) ?></strong><span>Farm and visit support</span></div><div class="sd-icon blue"><i class="fa-solid fa-location-dot"></i></div></div>
    <div class="sd-kpi"><div><small>Complaints</small><strong><?= (int) ($stats['complaint_count'] ?? 0) ?></strong><span>Public and user concerns</span></div><div class="sd-icon orange"><i class="fa-solid fa-bullhorn"></i></div></div>
    <div class="sd-kpi"><div><small>Avg Response Time</small><strong><?= e(sd_minutes_label($stats['avg_response_minutes'] !== null ? (int) $stats['avg_response_minutes'] : null)) ?></strong><span>First agent response</span></div><div class="sd-icon"><i class="fa-solid fa-stopwatch"></i></div></div>
  </section>

  <?php if ($workspaceView === 'teams'): ?>
  <section class="sd-panel" id="support-teams">
    <div class="sd-head"><h3>Customer Support Teams</h3><a href="<?= e(sd_url(['view' => 'overview'])) ?>">Back to Desk</a></div>
    <div class="support-work">
      <form method="post" class="sd-reply">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_team">
        <div class="form-grid">
          <div class="span2"><label>Team Name</label><input name="team_name" placeholder="Marketplace Resolution Team" required></div>
          <div><label>Module</label><select name="module"><?php foreach (array_unique(array_map(static fn(array $row): string => (string) $row['module'], $categories)) as $module): ?><option value="<?= e($module) ?>"><?= e(ucwords(str_replace('_', ' ', $module))) ?></option><?php endforeach; ?></select></div>
          <div><label>Status</label><select name="status"><option value="active">Active</option><option value="paused">Paused</option></select></div>
          <div class="span2"><label>Lead / Supervisor</label><select name="lead_admin_id"><option value="">Unassigned</option><?php foreach ($supportAdmins as $agent): ?><option value="<?= (int) $agent['id'] ?>"><?= e((string) ($agent['name'] ?: $agent['email'])) ?> - <?= e((string) ($agent['platform_role'] ?: $agent['role'])) ?></option><?php endforeach; ?></select></div>
          <div class="span2"><label>Description</label><input name="description" placeholder="Handles marketplace buyer/seller tickets, refunds, delivery disputes..."></div>
        </div>
        <button class="button" style="margin-top:10px">Save Support Team</button>
      </form>
      <div>
        <table class="sd-table"><thead><tr><th>Team</th><th>Module</th><th>Lead</th><th>Status</th><th>Open Tickets</th></tr></thead><tbody>
          <?php foreach ($customTeamRows as $teamRow): $teamOpen = sd_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_team=? AND status NOT IN ('resolved','closed','rejected')", [(string) $teamRow['team_name']]); ?><tr><td><strong><?= e((string) $teamRow['team_name']) ?></strong><br><small><?= e((string) $teamRow['description']) ?></small></td><td><?= e((string) $teamRow['module']) ?></td><td><?= e((string) ($teamRow['lead_name'] ?: 'Unassigned')) ?></td><td><span class="sd-badge <?= (string)$teamRow['status']==='active'?'ok':'neutral' ?>"><?= e((string) $teamRow['status']) ?></span></td><td><?= (int) $teamOpen ?></td></tr><?php endforeach; ?>
          <?php if (!$customTeamRows): ?><tr><td colspan="5">No custom support team yet. Create teams for Marketplace, Academy, Wallet, Field, Provider, and Technical support.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </div>
  </section>
  <?php endif; ?>
  <?php if ($selected): ?>
  <section class="sd-panel<?= $selected ? ' is-focused' : '' ?>" id="ticket-workbench">
    <div class="sd-head"><h3>Ticket Workbench</h3><a href="<?= e(sd_url(['view' => 'public-entry'])) ?>">Public Entry Info</a></div>
    <div class="support-work">
      <aside>
        <?php foreach ($tickets as $ticket): ?>
          <a class="sd-action" href="<?= e(sd_ticket_url((string) $ticket['ticket_ref'])) ?>">
            <i class="fa-solid fa-ticket"></i>
            <span><strong><?= e((string) $ticket['ticket_ref']) ?></strong><small><?= e((string) $ticket['subject']) ?> / <?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></small></span>
          </a>
        <?php endforeach; ?>
      </aside>
      <section>
        <?php if ($selected): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div><h2><?= e((string) $selected['subject']) ?></h2><p class="meta"><?= e((string) $selected['ticket_ref']) ?> / <?= e($categories[(string) $selected['category']]['label'] ?? (string) $selected['category']) ?> / <?= e((string) $selected['module']) ?></p></div>
            <a class="btn light" href="<?= e(sd_url(['view' => 'public-entry'])) ?>">Public Entry</a>
          </div>
          <div class="detail-grid">
            <div class="detail"><strong>Requester</strong><br><?= e((string) $selected['requester_name']) ?><br><span class="meta"><?= e((string) $selected['requester_email']) ?> / <?= e(support_role_label((string) $selected['requester_role'])) ?></span></div>
            <div class="detail"><strong>Linked Record</strong><br><?= e((string) ($selected['linked_record_type'] ?: 'None')) ?><br><span class="meta"><?= e((string) ($selected['linked_record_ref'] ?: 'No reference')) ?></span></div>
            <div class="detail"><strong>Team / Agent / SLA</strong><br><?= e((string) ($selected['assigned_team'] ?: 'Unassigned')) ?><br><span class="meta"><?= e(sd_agent_label($supportAdmins, ((int) ($selected['assigned_admin_id'] ?? 0)) ?: null)) ?> / <?= e(sd_short_date((string) ($selected['sla_due_at'] ?? ''))) ?></span></div>
          </div>

          <div class="conversation">
            <?php foreach ($conversation as $msg): ?><div class="msg <?= $msg['visibility'] === 'internal' ? 'internal' : ($msg['admin_id'] ? 'agent' : '') ?>"><strong><?= e((string) $msg['author_name']) ?></strong> <span class="meta"><?= e((string) $msg['author_role']) ?> / <?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></span><p><?= nl2br(e((string) $msg['message'])) ?></p></div><?php endforeach; ?>
          </div>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>">
            <div class="form-grid">
              <div><label>Status</label><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Assigned Team</label><select name="assigned_team"><?php foreach ($teams as $team): ?><option value="<?= e($team) ?>" <?= (string) $selected['assigned_team'] === $team ? 'selected' : '' ?>><?= e($team) ?></option><?php endforeach; ?></select></div><div class="span2"><label>Assigned Agent</label><select name="assigned_admin_id"><option value="">Unassigned</option><?php foreach ($supportAdmins as $agent): ?><option value="<?= (int) $agent['id'] ?>" <?= (int) ($selected['assigned_admin_id'] ?? 0) === (int) $agent['id'] ? 'selected' : '' ?>><?= e((string) ($agent['name'] ?: $agent['email'])) ?> - <?= e((string) ($agent['platform_role'] ?: $agent['role'])) ?></option><?php endforeach; ?></select></div>
              <div><label>Outcome</label><select name="outcome"><option value="">No final outcome</option><?php foreach ($outcomes as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) ($selected['outcome'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div class="span2"><label>Reply to requester</label><textarea name="reply" rows="5" placeholder="Visible to the requester."></textarea></div>
              <div class="span2"><label>Internal note</label><textarea name="internal_note" rows="5" placeholder="Visible only to platform admins/support agents."></textarea></div>
              <div class="span4"><button class="btn" type="submit">Update Ticket</button></div>
            </div>
          </form>
        <?php else: ?>
          <p class="empty">Select a ticket to manage.</p>
        <?php endif; ?>
      </section>
    </div>
  </section>

  <?php endif; ?>

  <section class="sd-grid">
    <div class="sd-panel" id="ticket-queue">
      <div class="sd-head"><h3>Ticket Queue</h3><a href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active', 'scope' => 'all'])) ?>">View All</a></div>
      <form method="get" class="sd-filter">
        <select name="status"><option value="active">All Active</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="category"><option value="">All Categories</option><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option><?php endforeach; ?></select>
        <select name="priority"><option value="">All Priorities</option><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterPriority === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="scope">
          <option value="all" <?= $filterScope === 'all' ? 'selected' : '' ?>>All Ownership</option>
          <option value="unassigned" <?= $filterScope === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
          <option value="groups" <?= $filterScope === 'groups' ? 'selected' : '' ?>>Assigned Teams</option>
          <option value="assigned" <?= $filterScope === 'assigned' ? 'selected' : '' ?>>Assigned To Me</option>
        </select>
        <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Search">
        <button class="btn" type="submit">Filter</button>
      </form>
      <div class="sd-tabs">
        <a class="<?= $filterScope === 'all' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active', 'scope' => 'all'])) ?>">All Tickets <span class="sd-badge ok"><?= (int) ($stats['open_count'] ?? 0) ?></span></a>
        <a class="<?= $filterScope === 'unassigned' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'unassigned'])) ?>">Unassigned <span class="sd-badge neutral"><?= $unassignedCount ?></span></a>
        <a class="<?= $filterScope === 'groups' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'groups'])) ?>">My Groups <span class="sd-badge info"><?= $myGroupsCount ?></span></a>
        <a class="<?= $filterScope === 'assigned' ? 'active' : '' ?>" href="<?= e(sd_url(['view' => 'assigned', 'status' => 'active', 'scope' => 'assigned'])) ?>">Assigned To Me <span class="sd-badge ok"><?= $myAssignedCount ?></span></a>
      </div>
      <table class="sd-table">
        <thead><tr><th>ID</th><th>Subject</th><th>Requester</th><th>Role</th><th>Category</th><th>Priority</th><th>Status</th><th>SLA</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><a href="<?= e(sd_ticket_url((string) $ticket['ticket_ref'])) ?>"><?= e((string) $ticket['ticket_ref']) ?></a></td>
            <td><strong><?= e((string) $ticket['subject']) ?></strong></td>
            <td><?= e((string) $ticket['requester_name']) ?></td>
            <td><?= e(support_role_label((string) $ticket['requester_role'])) ?></td>
            <td><?= e($categories[(string) $ticket['category']]['label'] ?? (string) $ticket['category']) ?></td>
            <td><span class="sd-badge <?= e(support_badge_class((string) $ticket['priority'])) ?>"><?= e($priorities[(string) $ticket['priority']] ?? (string) $ticket['priority']) ?></span></td>
            <td><span class="sd-badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></td>
            <td><?= e(sd_short_date((string) ($ticket['sla_due_at'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$tickets): ?><p class="empty">No tickets match these filters.</p><?php endif; ?>
      <?= sd_pagination_controls($totalTickets, $page, $perPage, ['status' => $filterStatus, 'category' => $filterCategory, 'priority' => $filterPriority, 'scope' => $filterScope, 'q' => $filterQ]) ?>
    </div>

    <div class="sd-panel">
      <div class="sd-head"><h3>Priority Triage Board</h3><a href="<?= e(sd_url(['status' => 'active'])) ?>">Refresh</a></div>
      <div class="sd-priorities">
        <?php foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $priorityKey => $priorityLabel): ?>
          <div class="sd-priority <?= e($priorityKey) ?>">
            <h4><?= e($priorityLabel) ?> (<?= (int) ($stats[$priorityKey . '_count'] ?? 0) ?>)</h4>
            <?php foreach ($priorityBoards[$priorityKey] as $row): ?>
              <div class="sd-mini-ticket">
                <strong><?= e((string) $row['ticket_ref']) ?></strong>
                <?= e((string) $row['subject']) ?>
                <span><?= e((string) $row['requester_name']) ?> / SLA <?= e(sd_short_date((string) ($row['sla_due_at'] ?? ''))) ?></span>
              </div>
            <?php endforeach; ?>
            <?php if (!$priorityBoards[$priorityKey]): ?><div class="sd-mini-ticket"><span>No active <?= e(strtolower($priorityLabel)) ?> priority tickets.</span></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sd-panel">
      <div class="sd-head"><h3>Conversation Preview</h3><a href="<?= $selected ? e(sd_ticket_url((string) $selected['ticket_ref'])) : e(sd_url(['status' => 'active', 'scope' => 'all'])) ?>"><?= $selected ? 'Open Workbench' : 'Choose Ticket' ?></a></div>
      <?php if ($selected): ?>
        <p><strong><?= e((string) $selected['ticket_ref']) ?></strong> - <?= e((string) $selected['subject']) ?> <span class="sd-badge <?= e(support_badge_class((string) $selected['status'])) ?>"><?= e($statuses[(string) $selected['status']] ?? (string) $selected['status']) ?></span></p>
        <div class="sd-chat">
          <?php foreach (array_slice($conversation, -3) as $msg): ?>
            <div class="sd-chat-row">
              <div class="sd-avatar"><?= e(strtoupper(substr((string) $msg['author_name'], 0, 1))) ?></div>
              <div><strong><?= e((string) $msg['author_name']) ?></strong> <span class="meta"><?= e(sd_when((string) $msg['created_at'])) ?></span><p><?= e(mb_substr((string) $msg['message'], 0, 140)) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty">Select a ticket to preview the conversation.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="sd-lower">
    <div class="sd-panel">
      <div class="sd-head" id="knowledge-suggestions"><h3>Knowledge Base Suggestions</h3><a href="<?= e(sd_url(['view' => 'public-entry'])) ?>">Public Entry</a></div>
      <div class="sd-list">
        <?php foreach ($knowledgeRows as $row): $cat = $categories[(string) $row['category']] ?? $categories['general']; ?>
          <div class="sd-list-row"><div><strong><?= e((string) $cat['label']) ?></strong><small>Last support activity <?= e(sd_short_date((string) $row['last_seen'])) ?></small></div><strong><?= (int) $row['total'] ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head" id="field-issues-overview"><h3>Field Issues Overview</h3><a href="<?= e(sd_url(['view' => 'field', 'category' => 'field', 'status' => 'active'])) ?>">Workspace View</a></div>
      <div class="sd-map"><span>Nigeria Field Support Map</span></div>
      <div class="sd-list-row"><div><strong>Total Open Field Issues</strong><small>Farm visits, evidence, and field operations</small></div><strong><?= (int) ($stats['field_count'] ?? 0) ?></strong></div>
      <?php foreach ($fieldRows as $row): ?><div class="sd-list-row"><span><?= e((string) ($row['linked_record_ref'] ?: 'Unlinked field request')) ?></span><strong><?= (int) $row['total'] ?></strong></div><?php endforeach; ?>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Escalation Queue</h3><a href="<?= e(sd_url(['view' => 'escalations', 'status' => 'escalated', 'scope' => 'all'])) ?>">View All</a></div>
      <div class="sd-list">
        <?php foreach ($escalationRows as $row): ?>
          <div class="sd-list-row"><div><strong><?= e((string) $row['ticket_ref']) ?></strong><small><?= e((string) $row['subject']) ?> / <?= e((string) $row['requester_name']) ?></small></div><span class="sd-badge <?= e(support_badge_class((string) $row['priority'])) ?>"><?= e(ucfirst((string) $row['priority'])) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$escalationRows): ?><p class="empty">No escalated or overdue tickets right now.</p><?php endif; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Communication Timeline</h3><a href="<?= $selected ? e(sd_ticket_url((string) $selected['ticket_ref'])) : e(sd_url(['view' => 'messages'])) ?>">View</a></div>
      <div class="sd-timeline">
        <?php foreach ($timelineRows as $row): ?>
          <div class="sd-timeline-row"><div class="sd-dot"><i class="fa-solid fa-message"></i></div><div><strong><?= e((string) $row['author_name']) ?></strong> <span class="meta"><?= e(sd_when((string) $row['created_at'])) ?></span><small class="meta"><?= e((string) $row['ticket_ref']) ?> - <?= e(mb_substr((string) $row['message'], 0, 80)) ?></small></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head" id="support-sla"><h3>Customer Satisfaction</h3><a href="<?= e(sd_url(['view' => 'settings'])) ?>">Policy</a></div>
      <div class="sd-score"><?= number_format($satisfactionScore, 1) ?></div>
      <div class="sd-bars">
        <div class="sd-bar"><span>5 Star</span><div class="sd-track"><div class="sd-fill" style="width:68%"></div></div><span>68%</span></div>
        <div class="sd-bar"><span>4 Star</span><div class="sd-track"><div class="sd-fill" style="width:22%"></div></div><span>22%</span></div>
        <div class="sd-bar"><span>3 Star</span><div class="sd-track"><div class="sd-fill orange" style="width:7%"></div></div><span>7%</span></div>
        <div class="sd-bar"><span>1-2</span><div class="sd-track"><div class="sd-fill red" style="width:3%"></div></div><span>3%</span></div>
      </div>
      <p><span class="sd-badge ok">Quick Response</span> <span class="sd-badge ok">Resolved</span> <span class="sd-badge warn">Wait Time</span></p>
    </div>
  </section>

  <?php if (!$selected): ?>
  <section class="sd-panel<?= $selected ? ' is-focused' : '' ?>" id="ticket-workbench">
    <div class="sd-head"><h3>Ticket Workbench</h3><a href="<?= e(sd_url(['view' => 'public-entry'])) ?>">Public Entry Info</a></div>
    <div class="support-work">
      <aside>
        <?php foreach ($tickets as $ticket): ?>
          <a class="sd-action" href="<?= e(sd_ticket_url((string) $ticket['ticket_ref'])) ?>">
            <i class="fa-solid fa-ticket"></i>
            <span><strong><?= e((string) $ticket['ticket_ref']) ?></strong><small><?= e((string) $ticket['subject']) ?> / <?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></small></span>
          </a>
        <?php endforeach; ?>
      </aside>
      <section>
        <?php if ($selected): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div><h2><?= e((string) $selected['subject']) ?></h2><p class="meta"><?= e((string) $selected['ticket_ref']) ?> / <?= e($categories[(string) $selected['category']]['label'] ?? (string) $selected['category']) ?> / <?= e((string) $selected['module']) ?></p></div>
            <a class="btn light" href="<?= e(sd_url(['view' => 'public-entry'])) ?>">Public Entry</a>
          </div>
          <div class="detail-grid">
            <div class="detail"><strong>Requester</strong><br><?= e((string) $selected['requester_name']) ?><br><span class="meta"><?= e((string) $selected['requester_email']) ?> / <?= e(support_role_label((string) $selected['requester_role'])) ?></span></div>
            <div class="detail"><strong>Linked Record</strong><br><?= e((string) ($selected['linked_record_type'] ?: 'None')) ?><br><span class="meta"><?= e((string) ($selected['linked_record_ref'] ?: 'No reference')) ?></span></div>
            <div class="detail"><strong>Team / Agent / SLA</strong><br><?= e((string) ($selected['assigned_team'] ?: 'Unassigned')) ?><br><span class="meta"><?= e(sd_agent_label($supportAdmins, ((int) ($selected['assigned_admin_id'] ?? 0)) ?: null)) ?> / <?= e(sd_short_date((string) ($selected['sla_due_at'] ?? ''))) ?></span></div>
          </div>

          <div class="conversation">
            <?php foreach ($conversation as $msg): ?><div class="msg <?= $msg['visibility'] === 'internal' ? 'internal' : ($msg['admin_id'] ? 'agent' : '') ?>"><strong><?= e((string) $msg['author_name']) ?></strong> <span class="meta"><?= e((string) $msg['author_role']) ?> / <?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></span><p><?= nl2br(e((string) $msg['message'])) ?></p></div><?php endforeach; ?>
          </div>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>">
            <div class="form-grid">
              <div><label>Status</label><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Assigned Team</label><select name="assigned_team"><?php foreach ($teams as $team): ?><option value="<?= e($team) ?>" <?= (string) $selected['assigned_team'] === $team ? 'selected' : '' ?>><?= e($team) ?></option><?php endforeach; ?></select></div><div class="span2"><label>Assigned Agent</label><select name="assigned_admin_id"><option value="">Unassigned</option><?php foreach ($supportAdmins as $agent): ?><option value="<?= (int) $agent['id'] ?>" <?= (int) ($selected['assigned_admin_id'] ?? 0) === (int) $agent['id'] ? 'selected' : '' ?>><?= e((string) ($agent['name'] ?: $agent['email'])) ?> - <?= e((string) ($agent['platform_role'] ?: $agent['role'])) ?></option><?php endforeach; ?></select></div>
              <div><label>Outcome</label><select name="outcome"><option value="">No final outcome</option><?php foreach ($outcomes as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) ($selected['outcome'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div class="span2"><label>Reply to requester</label><textarea name="reply" rows="5" placeholder="Visible to the requester."></textarea></div>
              <div class="span2"><label>Internal note</label><textarea name="internal_note" rows="5" placeholder="Visible only to platform admins/support agents."></textarea></div>
              <div class="span4"><button class="btn" type="submit">Update Ticket</button></div>
            </div>
          </form>
        <?php else: ?>
          <p class="empty">Select a ticket to manage.</p>
        <?php endif; ?>
      </section>
    </div>
  </section>

  <?php endif; ?>

  <section class="sd-lower" id="support-settings">
    <div class="sd-panel" id="support-public-entry">
      <div class="sd-head"><h3>Public Entry Governance</h3><a href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active'])) ?>">Back to Tickets</a></div>
      <div class="sd-list">
        <div class="sd-list-row"><div><strong>Public support portal</strong><small>Users create, track, reply to, and rate tickets from the public support page. Admins stay here to resolve them.</small></div><span class="sd-badge info">Public-facing</span></div>
        <div class="sd-list-row"><div><strong>Ticket access rule</strong><small>Anonymous visitors must know both ticket reference and requester email. Logged-in users only see their own tickets.</small></div><span class="sd-badge ok">Protected</span></div>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Support Workspace Settings</h3><a href="<?= e(sd_url(['view' => 'sla'])) ?>">SLA</a></div>
      <div class="sd-list">
        <div class="sd-list-row"><div><strong>Routing</strong><small>Queue, team assignment, escalation, messages, complaints, and field issues are administered inside this workspace.</small></div><span class="sd-badge ok">Contained</span></div>
        <div class="sd-list-row"><div><strong>SLA policy</strong><small>Use the SLA snapshot and escalation queue here before exporting or changing global policy elsewhere.</small></div><span class="sd-badge warn">Operator review</span></div>
      </div>
    </div>
  </section>
  <section class="sd-actions">
    <a class="sd-action" href="<?= e(sd_url(['view' => 'tickets', 'status' => 'active'])) ?>"><i class="fa-solid fa-circle-plus"></i><span><strong>Work Tickets</strong><small>Open the support workbench</small></span></a>
    <a class="sd-action" href="<?= $selected ? e(sd_ticket_url((string) $selected['ticket_ref'])) : e(sd_url(['status' => 'active', 'scope' => 'all'])) ?>"><i class="fa-solid fa-user-check"></i><span><strong>Assign Agent</strong><small><?= $selected ? 'Assign the selected ticket' : 'Choose a ticket first' ?></small></span></a>
    <a class="sd-action" href="<?= e(sd_url(['view' => 'escalations', 'status' => 'escalated', 'scope' => 'all'])) ?>"><i class="fa-solid fa-arrow-up"></i><span><strong>Escalate Ticket</strong><small>Move ticket to escalation</small></span></a>
    <a class="sd-action" href="<?= e(sd_url(['view' => 'knowledge'])) ?>"><i class="fa-solid fa-book-open"></i><span><strong>Knowledge Base</strong><small>Review FAQ suggestions</small></span></a>
    <a class="sd-action" href="<?= e(sd_url(['view' => 'sla'])) ?>"><i class="fa-solid fa-chart-line"></i><span><strong>SLA Snapshot</strong><small>Review support analytics</small></span></a>
  </section>
</div>
  </div>
</div>
  </main>
</div>
<?php admin_page_end(); ?>
