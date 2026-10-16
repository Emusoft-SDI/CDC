<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/sponsorships.php';

$pdo = db();
admin_require($pdo, 'super_admin');

$view = $_GET['view'] ?? 'list';
$sponsorshipId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve_reco') {
            $rid = (int) $_POST['recommendation_id'];
            $stmt = $pdo->prepare("UPDATE academy_sponsorship_recommendations SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $stmt->execute([admin_current_id($pdo), $rid]);
            $message = 'Recommendation approved.';
        } elseif ($action === 'reject_reco') {
            $rid = (int) $_POST['recommendation_id'];
            $stmt = $pdo->prepare("UPDATE academy_sponsorship_recommendations SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
            $stmt->execute([admin_current_id($pdo), $rid]);
            $message = 'Recommendation rejected.';
        }
    }
}

$sponsors = $pdo->query("SELECT * FROM academy_sponsorships ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$recs = $pdo->query("SELECT * FROM academy_sponsorship_recommendations ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="panel">
    <h3>Academy Sponsorships & Recommendations (Super Admin)</h3>
    <p class="muted">View, approve, and manage coordinator/cooperative recommendations and sponsorships.</p>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
</section>

<section class="panel">
    <h3>Sponsorships</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Sponsor</th><th>Course</th><th>Scope</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($sponsors as $s): ?>
            <tr>
                <td><?= (int) $s['id'] ?></td>
                <td><?= e($s['sponsor_type']) ?></td>
                <td><?= e($s['webinar_id']) ?></td>
                <td><?= e($s['sponsor_scope_type']) ?></td>
                <td><?= e($s['status']) ?></td>
                <td><?= e($s['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h3>Recommendations</h3>
    <table class="table">
        <thead><tr><th>ID</th><th>Recommender</th><th>Course</th><th>Recipient</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($recs as $r): ?>
            <tr>
                <td><?= (int) $r['id'] ?></td>
                <td><?= e($r['recommender_type']) ?>/<?= (int) $r['recommender_user_id'] ?></td>
                <td><?= e($r['webinar_id']) ?></td>
                <td><?= e($r['recipient_scope_type']) ?></td>
                <td><?= e($r['status']) ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <form method="post" style="display:inline">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="approve_reco">
                        <input type="hidden" name="recommendation_id" value="<?= (int) $r['id'] ?>">
                        <button class="btn small" type="submit">Approve</button>
                    </form>
                    <form method="post" style="display:inline">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reject_reco">
                        <input type="hidden" name="recommendation_id" value="<?= (int) $r['id'] ?>">
                        <button class="btn small" type="submit">Reject</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
