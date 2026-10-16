<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/sponsorship-admin.php';

$pdo = db();
admin_require($pdo, 'super_admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        academy_admin_recommendation_update_status($pdo, (int) $_POST['id'], 'approved', admin_current_id($pdo));
        $message = 'Approved.';
    } elseif ($action === 'reject') {
        academy_admin_recommendation_update_status($pdo, (int) $_POST['id'], 'rejected', admin_current_id($pdo));
        $message = 'Rejected.';
    }
}

$sponsors = academy_admin_sponsorships_list($pdo, 200);
$recs = academy_admin_recommendations_list($pdo, 200);
?>
<section class="panel"><h3>Academy Sponsorships & Recommendations</h3><?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?></section>
<section class="panel"><h3>Sponsorships (CRUD Control)</h3><table class="table"><thead><tr><th>ID</th><th>Sponsor</th><th>Course</th><th>Scope</th><th>Status</th><th>Slots</th><th>Created</th></tr></thead><tbody><?php foreach ($sponsors as $s): ?><tr><td><?= (int)$s['id'] ?></td><td><?= e($s['sponsor_type']) ?></td><td><?= (int)$s['webinar_id'] ?></td><td><?= e($s['sponsor_scope_type']) ?></td><td><?= e($s['status']) ?></td><td><?= (int)$s['slots'] ?></td><td><?= e($s['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<section class="panel"><h3>Recommendations (Approve/Reject)</h3><table class="table"><thead><tr><th>ID</th><th>Recommender</th><th>Course</th><th>Recipient</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($recs as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e($r['recommender_type']) ?></td><td><?= (int)$r['webinar_id'] ?></td><td><?= e($r['recipient_scope_type']) ?></td><td><?= e($r['status']) ?></td><td><?php if ($r['status']==='pending'): ?><form method="post" style="display:inline"><?= csrf_field(); ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small">Approve</button></form> <form method="post" style="display:inline"><?= csrf_field(); ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn small">Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section>
