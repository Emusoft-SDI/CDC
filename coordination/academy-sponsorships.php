<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/coordination.php';
require_once __DIR__ . '/../lib/academy/sponsorships.php';

pdo_connect();

$pdo = pdo_conn();
coord_require($pdo, 'coordination');

$user = coord_current_user($pdo);
$assignments = admin_active_role_assignments($pdo, (int) $user['id']);
if (!$assignments) {
    header('Location: ../dashboard.php?error=' . urlencode('Access denied.'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'recommend' && !empty($_POST['webinar_id'])) {
        academy_recommend_course($pdo, [
            'recommender_type' => 'coordinator',
            'recommender_user_id' => (int) $user['id'],
            'webinar_id' => (int) $_POST['webinar_id'],
            'recipient_scope_type' => 'state',
            'recipient_state_id' => (int) ($_POST['state_id'] ?? 0),
            'recipient_lga' => trim((string) ($_POST['lga'] ?? '')),
            'recipient_district' => trim((string) ($_POST['district'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ]);
    }
}

$courses = academy_course_list($pdo, ['status' => 'active']);
$states = app_states($pdo);

coord_header('Academy Sponsorships & Recommendations', 'Recommend or sponsor training for your enclaves.', $user, 'academy');
?>
<section class="panel">
    <h3>Recommend Training</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="recommend">
        <label>
            Course/Webinar
            <select name="webinar_id" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= e($course['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            State
            <select name="state_id">
                <option value="">Select state (or keep broad)</option>
                <?php foreach ($states as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            LGA
            <input type="text" name="lga" placeholder="LGA (optional)">
        </label>
        <label>
            District/Village
            <input type="text" name="district" placeholder="District/Village (optional)">
        </label>
        <label class="full">
            Message to recipients
            <textarea name="message" rows="3" placeholder="Reason for recommendation..."></textarea>
        </label>
        <button type="submit" class="btn primary">Submit Recommendation</button>
    </form>
</section>
<?php coord_footer(); ?>
