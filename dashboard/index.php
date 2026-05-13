<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

session_start();
$pdo = db();
app_ensure_farmer_engagement_schema($pdo);
app_ensure_certificate_schema($pdo);
fm_ensure_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT u.id user_id, u.name user_name, u.email user_email, u.role,
               a.id application_id, a.app_ref, a.location, a.farm_size, a.commitments,
               a.confirmed, a.created_at, a.confirmed_at
        FROM users u
        LEFT JOIN applications a ON a.id = u.application_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        session_destroy();
        redirect_to('login.php');
    }

    $pdo->prepare("INSERT IGNORE INTO wallets (user_id) VALUES (?)")->execute([$userId]);

    $docStmt = $pdo->prepare("
        SELECT verification_status, COUNT(*) total
        FROM document_requirements
        WHERE user_id = ?
        GROUP BY verification_status
    ");
    $docStmt->execute([$userId]);
    $docCounts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
    foreach ($docStmt->fetchAll() as $row) {
        $docCounts[$row['verification_status']] = (int) $row['total'];
    }
    $docTotal = array_sum($docCounts);

    $msgStmt = $pdo->prepare("
        SELECT *
        FROM messages
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 4
    ");
    $msgStmt->execute([$userId]);
    $messages = $msgStmt->fetchAll();

    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_from_admin = 1 AND is_read = 0");
    $unreadStmt->execute([$userId]);
    $unreadMessages = (int) $unreadStmt->fetchColumn();

    $walletStmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $walletBalance = (float) $walletStmt->fetchColumn();

    fm_seed_missing_verifications($pdo);

    $farmStmt = $pdo->prepare("
        SELECT gf.*, ns.state_name, nl.lga_name, fv.status verification_status,
               fv.system_confidence_score, fv.system_notes
        FROM grower_farms gf
        LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
        LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
        LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
        WHERE gf.user_id = ?
        ORDER BY gf.is_primary DESC, gf.updated_at DESC, gf.created_at DESC
        LIMIT 6
    ");
    $farmStmt->execute([$userId]);
    $farms = $farmStmt->fetchAll();
    $primaryFarm = $farms[0] ?? null;
    $farmWeather = null;
    if ($primaryFarm) {
        $farmWeather = fm_weather_estimate(
            $pdo,
            (int) $primaryFarm['id'],
            $primaryFarm['latitude'] !== null ? (float) $primaryFarm['latitude'] : null,
            $primaryFarm['longitude'] !== null ? (float) $primaryFarm['longitude'] : null
        );
    }

    $fieldTaskStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM field_tasks ft
        JOIN grower_farms gf ON gf.id = ft.farm_id
        WHERE gf.user_id = ? AND ft.status NOT IN ('completed','cancelled')
    ");
    $fieldTaskStmt->execute([$userId]);
    $openFieldTasks = (int) $fieldTaskStmt->fetchColumn();

    $agronomyCases = 0;
    if (app_table_exists($pdo, 'agronomy_cases')) {
        $agronomyStmt = $pdo->prepare("SELECT COUNT(*) FROM agronomy_cases WHERE grower_id = ? AND status NOT IN ('resolved','closed')");
        $agronomyStmt->execute([$userId]);
        $agronomyCases = (int) $agronomyStmt->fetchColumn();
    }

    $certificate = null;
    if (!empty($profile['application_id'])) {
        $certStmt = $pdo->prepare("
            SELECT c.*, COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref
            FROM certificates c
            JOIN applications a ON a.id = c.application_id
            WHERE c.application_id = ? AND COALESCE(c.status, 'issued') = 'issued'
            ORDER BY c.issued_at DESC
            LIMIT 1
        ");
        $certStmt->execute([(int) $profile['application_id']]);
        $certificate = $certStmt->fetch();
    }

    $resources = [];
    if (app_table_exists($pdo, 'resources')) {
        $resources = $pdo->query("SELECT title, category, description, file_path FROM resources ORDER BY created_at DESC LIMIT 4")->fetchAll();
    }

    $webinars = [];
    if (app_table_exists($pdo, 'webinars')) {
        $webinars = $pdo->query("SELECT title, start_time, is_free, price FROM webinars WHERE start_time > NOW() ORDER BY start_time ASC LIMIT 3")->fetchAll();
    }
} catch (Throwable $e) {
    error_log('Farmer dashboard error: ' . $e->getMessage());
    http_response_code(500);
    exit('Dashboard temporarily unavailable.');
}

$confirmed = (int) ($profile['confirmed'] ?? 0) === 1;
$documentsComplete = $docTotal > 0 && $docCounts['rejected'] === 0 && $docCounts['pending'] === 0;
$certificateReady = (bool) $certificate;
$certificateVerifyUrl = '';
$certificateFileUrl = '';
$certificatePdfUrl = '';
$certificateStoredPdfUrl = '';

if ($certificateReady) {
    $certificateRef = (string) $certificate['display_ref'];
    $certificateVerifyUrl = app_base_url() . '/verify-certificate.php?ref=' . urlencode($certificateRef);
    $certificatePdfUrl = 'download-certificate.php?ref=' . urlencode($certificateRef);
    $certificatePath = ltrim((string) ($certificate['certificate_path'] ?? ''), '/');

    if ($certificatePath !== '' && !str_starts_with($certificatePath, 'certificates/')) {
        $certificatePath = 'certificates/' . $certificatePath;
    }

    if ($certificatePath !== '') {
        $certificateFileUrl = app_base_url() . '/' . $certificatePath;
    }

    $certificatePdfPath = ltrim((string) ($certificate['certificate_pdf_path'] ?? ''), '/');
    if ($certificatePdfPath !== '' && !str_starts_with($certificatePdfPath, 'certificates/')) {
        $certificatePdfPath = 'certificates/' . $certificatePdfPath;
    }
    if ($certificatePdfPath !== '') {
        $certificateStoredPdfUrl = app_base_url() . '/' . $certificatePdfPath;
    }
}

$steps = [
    ['label' => 'Application submitted', 'done' => !empty($profile['app_ref']), 'href' => '#application'],
    ['label' => 'Email confirmed', 'done' => $confirmed, 'href' => '#application'],
    ['label' => 'Documents verified', 'done' => $documentsComplete, 'href' => 'documents.php'],
    ['label' => 'Certificate issued', 'done' => $certificateReady, 'href' => '#certificate'],
];
$completed = count(array_filter($steps, fn($step) => $step['done']));
$progress = (int) round(($completed / count($steps)) * 100);
?>
<?php dashboard_page_start('Dashboard Overview', [
    'active' => 'index.php',
    'description' => 'Track farm health, verification, support, training, marketplace access, wallet activity, and certificate status from one place.',
    'wide' => true,
    'css' => '
      .farm-health-card { background:linear-gradient(135deg,#ffffff 0%,#f3faf1 100%); }
      .farm-health-head { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; margin-bottom:14px; }
      .farm-health-metrics { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:12px; margin:14px 0; }
      .farm-health-metrics div { border:1px solid var(--line); border-radius:8px; padding:12px; background:#fff; }
      .farm-list { display:grid; gap:10px; margin-top:12px; }
      .farm-row { display:grid; grid-template-columns:minmax(0,1.6fr) minmax(120px,.6fr) minmax(120px,.6fr); gap:12px; align-items:center; border:1px solid var(--line); border-radius:8px; padding:12px; background:#fff; }
      @media (max-width:760px) { .farm-health-head, .farm-row { grid-template-columns:1fr; display:grid; } .farm-health-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    ',
]); ?>

    <section class="hero">
      <div class="card">
        <h2>Your Growth Path</h2>
        <p class="muted">Track what matters most: confirmation, verification, support, training, and certification.</p>
        <div class="progress"><div style="width:<?= $progress ?>%;"></div></div>
        <strong><?= $progress ?>% complete</strong>
        <div class="steps" style="margin-top:14px;">
          <?php foreach ($steps as $step): ?>
            <a class="step" href="<?= e($step['href']) ?>" style="text-decoration:none;color:inherit;">
              <span><?= e($step['label']) ?></span>
              <span class="<?= $step['done'] ? 'done' : 'todo' ?>"><?= $step['done'] ? 'Done' : 'Action needed' ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="actions">
          <a class="button" href="documents.php">Complete Verification</a>
          <a class="button secondary" href="inbox.php">Ask for Support</a>
          <?php if ($certificateReady): ?><a class="button secondary" href="<?= e($certificateVerifyUrl) ?>">Verify Certificate</a><?php endif; ?>
        </div>
      </div>

      <div class="card">
        <h2>Engagement Snapshot</h2>
        <p><span class="metric"><?= $docCounts['verified'] ?></span><br><span class="muted">Verified documents</span></p>
        <p><span class="metric"><?= $unreadMessages ?></span><br><span class="muted">Unread support replies</span></p>
        <p><span class="metric">NGN <?= e(number_format($walletBalance, 2)) ?></span><br><span class="muted">Wallet balance</span></p>
      </div>
    </section>

    <section class="grid">
      <div class="card span-8 farm-health-card">
        <div class="farm-health-head">
          <div>
            <h2>Farm Health</h2>
            <p class="muted">Your farms, verification status, field work, weather, and agronomy support in one place.</p>
          </div>
          <div class="actions" style="margin-top:0;">
            <a class="button" href="farm-health.php">Open Farm Health</a>
            <a class="button secondary" href="fields.php">Manage Fields</a>
            <a class="button secondary" href="agronomist.php">Ask Agronomist</a>
          </div>
        </div>

        <div class="farm-health-metrics">
          <div><span class="metric"><?= count($farms) ?></span><br><span class="muted">Registered farms</span></div>
          <div><span class="metric"><?= (int) $openFieldTasks ?></span><br><span class="muted">Open field tasks</span></div>
          <div><span class="metric"><?= (int) $agronomyCases ?></span><br><span class="muted">Agronomy cases</span></div>
          <div>
            <span class="metric"><?= $farmWeather ? e((string) $farmWeather['temperature_c']) . '&deg;C' : '-' ?></span><br>
            <span class="muted"><?= $farmWeather ? 'Rain ' . e((string) $farmWeather['rainfall_mm']) . 'mm' : 'Weather pending' ?></span>
          </div>
        </div>

        <?php if ($primaryFarm): ?>
          <p><strong><?= e($primaryFarm['farm_name']) ?></strong> is your primary farm<?= $primaryFarm['street_address'] ? ' at ' . e($primaryFarm['street_address']) : '' ?>.</p>
          <?php if ($farmWeather): ?><p class="muted"><?= e((string) $farmWeather['summary']) ?> / Humidity <?= e((string) $farmWeather['humidity_percent']) ?>%</p><?php endif; ?>
        <?php else: ?>
          <p class="muted">Add your farm location and coordinates so NATCODEV can support verification, weather checks, and agronomy recommendations.</p>
        <?php endif; ?>

        <div class="farm-list">
          <?php foreach (array_slice($farms, 0, 3) as $farm): ?>
            <?php $status = (string) ($farm['verification_status'] ?? 'pending'); ?>
            <div class="farm-row">
              <div>
                <strong><?= e($farm['farm_name']) ?><?= (int) $farm['is_primary'] === 1 ? ' / Primary' : '' ?></strong><br>
                <span class="muted"><?= e($farm['state_name'] ?? 'State pending') ?><?= $farm['lga_name'] ? ' / ' . e($farm['lga_name']) : '' ?></span>
              </div>
              <span class="badge <?= e($status) ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
              <span class="muted"><?= $farm['system_confidence_score'] !== null ? e((string) $farm['system_confidence_score']) . '% confidence' : 'Awaiting score' ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (!$farms): ?><div class="empty">No farm record has been added yet. Start from your profile farm location.</div><?php endif; ?>
        </div>
      </div>

      <div class="card span-4">
        <h2>Farm Actions</h2>
        <p class="muted">Keep your farm record current before requesting inspection or agronomy help.</p>
        <div class="steps">
          <a class="step" href="profile.php#farms"><span>Update farm profile</span><span class="todo">Open</span></a>
          <a class="step" href="fields.php"><span>Review field status</span><span class="todo">Open</span></a>
          <a class="step" href="farm-health.php"><span>Request farm review</span><span class="todo">Open</span></a>
          <a class="step" href="agronomist.php"><span>Get agronomy advice</span><span class="todo">Open</span></a>
        </div>
      </div>

      <div class="card span-8" id="application">
        <h2>Application Summary</h2>
        <table>
          <tr><td>Reference</td><td><?= e($profile['app_ref'] ?? 'Not linked') ?></td></tr>
          <tr><td>Status</td><td><span class="status <?= $confirmed ? 'confirmed' : 'pending' ?>"><?= $confirmed ? 'Confirmed' : 'Pending email confirmation' ?></span></td></tr>
          <tr><td>Farm Size</td><td><?= e((string) ($profile['farm_size'] ?? '')) ?> hectares</td></tr>
          <tr><td>Location</td><td><?= e($profile['location'] ?? '') ?></td></tr>
          <tr><td>Commitments</td><td><?= e($profile['commitments'] ?? '') ?></td></tr>
        </table>
      </div>

      <div class="card span-4" id="certificate">
        <h2>Certificate</h2>
        <?php if ($certificateReady): ?>
          <p class="done">Certificate issued</p>
          <p><?= e($certificate['display_ref']) ?></p>
          <?php if ($certificateFileUrl): ?><a class="button" href="<?= e($certificateFileUrl) ?>" target="_blank">Open HTML</a><?php endif; ?>
          <a class="button" href="<?= e($certificatePdfUrl) ?>">Download PDF</a>
          <?php if ($certificateStoredPdfUrl): ?><a class="button secondary" href="<?= e($certificateStoredPdfUrl) ?>" target="_blank">Open PDF</a><?php endif; ?>
          <a class="button secondary" href="<?= e($certificateVerifyUrl) ?>" target="_blank">Verify Certificate</a>
        <?php elseif ($documentsComplete && $confirmed): ?>
          <p class="muted">Your certificate is being prepared by the team.</p>
        <?php else: ?>
          <p class="muted">Complete email confirmation and document verification to unlock your certificate.</p>
        <?php endif; ?>
      </div>

      <div class="card span-6">
        <h2>Support Desk</h2>
        <?php foreach ($messages as $msg): ?>
          <div class="message <?= (int) $msg['is_from_admin'] === 1 ? 'from-admin' : '' ?>">
            <strong><?= (int) $msg['is_from_admin'] === 1 ? 'NATCODEV Team' : 'You' ?></strong>
            <div><?= e($msg['message']) ?></div>
            <small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small>
          </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?><p class="muted">No support messages yet. Start a conversation when you need help.</p><?php endif; ?>
        <a class="button secondary" href="inbox.php">Open Support</a>
      </div>

      <div class="card span-6">
        <h2>Training & Resources</h2>
        <?php if ($webinars): ?>
          <?php foreach ($webinars as $webinar): ?>
            <p><strong><?= e($webinar['title']) ?></strong><br><span class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $webinar['start_time']))) ?> / <?= (int) $webinar['is_free'] === 1 ? 'Free' : 'NGN ' . e(number_format((float) $webinar['price'], 2)) ?></span></p>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php foreach ($resources as $resource): ?>
          <p><strong><?= e($resource['title']) ?></strong><br><span class="muted"><?= e($resource['category']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$webinars && !$resources): ?><p class="muted">Training sessions and resources will appear here when published.</p><?php endif; ?>
      </div>

      <div class="card span-4">
        <h2>Marketplace</h2>
        <p class="muted">Access inputs, services, and premium grower opportunities.</p>
        <a class="button secondary" href="marketplace.php">Open Marketplace</a>
      </div>

      <div class="card span-4">
        <h2>Payments</h2>
        <p class="muted">Use your wallet for training, premium services, and marketplace purchases.</p>
        <a class="button secondary" href="wallet.php">Manage Wallet</a>
      </div>
    </section>
  <?php dashboard_page_end(); ?>
