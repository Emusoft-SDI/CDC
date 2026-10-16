<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/sponsorship-admin.php';

$pdo = db();
admin_require($pdo, 'training');

$view = $_GET['view'] ?? 'list';
$sponsors = academy_admin_sponsorships_list($pdo, 200);
$recs = academy_admin_recommendations_list($pdo, 200);
?>
<section class="panel">
    <h3>Academy Sponsorships & Recommendations</h3>
    <p class="muted">CRUD: View all sponsorships and recommendations with full administrative visibility and control.</p>
</section>

<section class="panel">
    <h3>Sponsorships (Full Control)</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Sponsor</th><th>Course</th><th>Scope</th><th>Status</th><th>Slots</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($sponsors as $s): ?>
            <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= e($s['sponsor_type']) ?></td>
                <td><?= (int) $s['webinar_id'] ?></td>
                <td><?= e($s['sponsor_scope_type']) ?></td>
                <td><?= e($s['status']) ?></td>
                <td><?= (int) $s['slots'] ?></td>
                <td><?= e($s['created_at']) ?></td>
                <td>Edit/Update/Delete (via API/Module controls)</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h3>Recommendations (Full Control)</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Recommender</th><th>Course</th><th>Recipient</th><th>Status</th><th>Reviewed</th></tr></thead>
        <tbody>
        <?php foreach ($recs as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= e($r['recommender_type']) ?></td>
                <td><?= (int) $r['webinar_id'] ?></td>
                <td><?= e($r['recipient_scope_type']) ?></td>
                <td><?= e($r['status']) ?></td>
                <td><?= e($r['reviewed_at'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
