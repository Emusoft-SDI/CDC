<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/sponsorships.php';

$pdo = db();
admin_require($pdo, 'training');

$view = $_GET['view'] ?? 'list';
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

$sponsors = $pdo->query("SELECT * FROM academy_sponsorships ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$recs = $pdo->query("SELECT * FROM academy_sponsorship_recommendations ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel">
    <h3>Academy Sponsorships & Recommendations</h3>
    <p class="muted">Manage coordinator/cooperative learning sponsorships and recommendations.</p>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
</section>

<section class="panel">
    <h3>Sponsorships</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Sponsor</th><th>Course</th><th>Scope</th><th>Status</th><th>Slots</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($sponsors as $s): ?>
            <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= e($s['sponsor_type']) ?> (<?= (int) $s['sponsor_user_id'] ?>)</td>
                <td><?= (int) $s['webinar_id'] ?></td>
                <td><?= e($s['sponsor_scope_type']) ?></td>
                <td><?= e($s['status']) ?></td>
                <td><?= (int) $s['slots'] ?></td>
                <td><?= e($s['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h3>Recommendations</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Recommender</th><th>Course</th><th>Recipient</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach ($recs as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= e($r['recommender_type']) ?>/<?= (int) $r['recommender_user_id'] ?></td>
                <td><?= (int) $r['webinar_id'] ?></td>
                <td><?= e($r['recipient_scope_type']) ?></td>
                <td><?= e($r['status']) ?></td>
                <td><?= e(substr((string) $r['message'], 0, 60)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
