<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/_cooperative.php';
require_once __DIR__ . '/../lib/academy/sponsorships.php';

$pdo = coop_pdo();
$user = coop_require($pdo);
$cooperative = cooperative_current($pdo);
$cooperativeId = (int) ($cooperative['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'recommend' && !empty($_POST['webinar_id'])) {
        academy_recommend_course($pdo, [
            'recommender_type' => 'cooperative',
            'recommender_user_id' => (int) $user['id'],
            'recommender_cooperative_id' => $cooperativeId,
            'webinar_id' => (int) $_POST['webinar_id'],
            'recipient_scope_type' => 'cooperative',
            'recipient_cooperative_id' => $cooperativeId,
            'message' => trim((string) ($_POST['message'] ?? '')),
        ]);
    } elseif ($_POST['action'] === 'sponsor' && !empty($_POST['webinar_id'])) {
        academy_sponsorship_create($pdo, [
            'sponsor_type' => 'cooperative',
            'sponsor_cooperative_id' => $cooperativeId,
            'sponsor_user_id' => (int) $user['id'],
            'webinar_id' => (int) $_POST['webinar_id'],
            'sponsor_scope_type' => 'cooperative',
            'target_cooperative_id' => $cooperativeId,
            'note' => trim((string) ($_POST['note'] ?? '')),
            'slots' => (int) ($_POST['slots'] ?? 10),
            'created_by' => (int) $user['id'],
        ]);
    }
}

$courses = academy_course_list($pdo, ['status' => 'active']);
coop_header('Academy Sponsorships', 'Recommend or sponsor learning for your cooperative.', $user, 'academy');
?>
<section class="panel">
    <h3>Sponsor Training for Cooperative</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="sponsor">
        <label class="full">
            Course/Webinar
            <select name="webinar_id" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Slots Available
            <input type="number" name="slots" min="1" value="10">
        </label>
        <label class="full">
            Notes
            <textarea name="note" rows="3"></textarea>
        </label>
        <button type="submit" class="btn primary">Create Sponsorship</button>
    </form>
</section>

<section class="panel">
    <h3>Recommend Training to Members</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="recommend">
        <label class="full">
            Course/Webinar
            <select name="webinar_id" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="full">
            Message
            <textarea name="message" rows="3"></textarea>
        </label>
        <button type="submit" class="btn primary">Submit Recommendation</button>
    </form>
</section>
<?php coop_footer(); ?>
