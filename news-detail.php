<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/news.php';

$pdo = db();
news_ensure_schema($pdo);

$logo = app_primary_logo_url();
$year = date('Y');
$office = app_contact_office();

$slug = trim((string) ($_GET['slug'] ?? ''));
$currentUser = current_user($pdo);
$isMember = !empty($currentUser['id']);
$isAdmin = admin_session_is_authenticated($pdo);

$post = news_get_by_slug($pdo, $slug, $isAdmin, $isMember);

$feedbackError = '';
$feedbackSuccess = '';

// Handle Interactive Feedback (Comments & Poll Voting)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $post) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $feedbackError = 'Security token invalid. Please refresh the page.';
    } elseif (!$isMember) {
        $feedbackError = 'Please sign in to your NATCODEV account to participate in discussion and polls.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $userId = (int) $currentUser['id'];

        if ($action === 'submit_comment') {
            $commentText = trim((string) ($_POST['comment_text'] ?? ''));
            if ($commentText === '') {
                $feedbackError = 'Comment cannot be empty.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, created_at)
                        VALUES (?, ?, 'comment', ?, NOW())
                    ");
                    $stmt->execute([$post['id'], $userId, $commentText]);
                    $feedbackSuccess = 'Your comment has been published.';
                } catch (Throwable $e) {
                    $feedbackError = 'Failed to submit comment: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'submit_poll') {
            $pollOption = trim((string) ($_POST['poll_option'] ?? ''));
            if ($pollOption === '') {
                $feedbackError = 'Please select an option before voting.';
            } else {
                try {
                    $check = $pdo->prepare("SELECT id FROM coop_news_feedback WHERE news_id = ? AND user_id = ? AND feedback_type = 'poll_vote'");
                    $check->execute([$post['id'], $userId]);
                    if ($check->fetch()) {
                        $feedbackError = 'You have already voted on this announcement.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO coop_news_feedback (news_id, user_id, feedback_type, content, created_at)
                            VALUES (?, ?, 'poll_vote', ?, NOW())
                        ");
                        $stmt->execute([$post['id'], $userId, $pollOption]);
                        $feedbackSuccess = 'Thank you for your vote!';
                    }
                } catch (Throwable $e) {
                    $feedbackError = 'Failed to record vote: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Comments & Poll Statistics
$comments = [];
$hasVotedPoll = false;
$pollResults = [];
$totalVotes = 0;
$relatedPosts = [];

if ($post) {
    try {
        // Comments
        $cStmt = $pdo->prepare("
            SELECT f.*, u.name, u.email, u.platform_role 
            FROM coop_news_feedback f
            JOIN users u ON f.user_id = u.id
            WHERE f.news_id = ? AND f.feedback_type = 'comment' AND f.is_hidden = 0
            ORDER BY f.created_at DESC
        ");
        $cStmt->execute([$post['id']]);
        $comments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

        // Check user poll status
        if ($isMember) {
            $pollCheck = $pdo->prepare("SELECT id FROM coop_news_feedback WHERE news_id = ? AND user_id = ? AND feedback_type = 'poll_vote' LIMIT 1");
            $pollCheck->execute([$post['id'], (int)$currentUser['id']]);
            $hasVotedPoll = (bool) $pollCheck->fetch();
        }

        // Aggregate Poll Votes
        $pStmt = $pdo->prepare("
            SELECT content AS option_name, COUNT(*) AS vote_count 
            FROM coop_news_feedback 
            WHERE news_id = ? AND feedback_type = 'poll_vote' 
            GROUP BY content
        ");
        $pStmt->execute([$post['id']]);
        $votesRaw = $pStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($votesRaw as $v) {
            $totalVotes += (int) $v['vote_count'];
        }
        foreach ($votesRaw as $v) {
            $pollResults[$v['option_name']] = [
                'count' => (int) $v['vote_count'],
                'percent' => $totalVotes > 0 ? round(((int)$v['vote_count'] / $totalVotes) * 100, 1) : 0,
            ];
        }

        // Related Posts
        $rStmt = $pdo->prepare("
            SELECT * FROM coop_news 
            WHERE id != ? AND status = 'published' AND visibility IN ('public', 'both')
            ORDER BY created_at DESC LIMIT 3
        ");
        $rStmt->execute([$post['id']]);
        $relatedPosts = $rStmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-increment analytics view counter
        $pdo->prepare("
            INSERT INTO coop_news_analytics (news_id, views_count)
            VALUES (?, 1)
            ON DUPLICATE KEY UPDATE views_count = views_count + 1
        ")->execute([$post['id']]);
    } catch (Throwable $e) {
        error_log("News detail error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $post ? e($post['title']) . ' - NATCODEV News & Insights' : 'Announcement Not Found - NATCODEV' ?></title>
  <meta name="description" content="<?= $post ? e(mb_strimwidth((string)$post['summary'], 0, 160, '...')) : 'NATCODEV Announcement' ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--teal:#0e7e7d;--blue:#2f72d8;--purple:#5b3ba6;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--shadow:0 18px 42px rgba(16,24,40,.1);--max:1760px}
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f7faf5}a{text-decoration:none;color:inherit}button,input{font:inherit}
    .top-strip{background:linear-gradient(90deg,#063f20,#085d2b);color:#fff;padding:11px 30px;font-weight:800}.top-inner{max-width:var(--max);margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:20px}.top-links{display:flex;gap:18px;align-items:center}.top-links a{color:#fff}.social{display:flex;gap:14px}
    .header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:40}.nav-wrap{max-width:var(--max);margin:0 auto;min-height:86px;padding:10px 24px;display:grid;grid-template-columns:minmax(260px,330px) minmax(0,1fr) auto;gap:18px;align-items:center}.brand{display:flex;gap:12px;align-items:center;min-width:0}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain;flex:0 0 auto}.brand strong{display:block;font-size:1.85rem;line-height:.95;color:var(--green);letter-spacing:.02em}.brand span{display:block;font-size:.68rem;font-weight:900;color:#111;letter-spacing:.03em}.nav{display:flex;justify-content:center;gap:22px;align-items:center;font-weight:900;min-width:0}.nav a{color:#111827;white-space:nowrap}.nav a:hover{color:var(--green2)}.actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;min-width:0}.btn{border-radius:10px;border:1px solid var(--green);min-height:48px;padding:12px 22px;font-weight:950;display:inline-flex;gap:9px;align-items:center;justify-content:center;white-space:nowrap}.btn.primary{background:var(--green2);color:#fff;border-color:var(--green2)}.btn.light{background:#fff;color:var(--green)}
    
    .article-wrap{max-width:1160px;margin:36px auto;padding:0 24px;display:grid;grid-template-columns:1fr 340px;gap:36px}
    .main-article{background:#fff;border-radius:18px;border:1px solid var(--line);box-shadow:0 6px 20px rgba(16,24,40,.04);overflow:hidden}
    .article-hero-img{width:100%;height:380px;object-fit:cover;display:block;background:#e8f6ec}
    .article-content{padding:36px 40px}
    .article-header{margin-bottom:24px;border-bottom:1px solid var(--line);padding-bottom:20px}
    .cat-badge{background:rgba(8,117,58,0.12);color:var(--green2);font-weight:800;font-size:0.8rem;text-transform:uppercase;padding:6px 14px;border-radius:20px;display:inline-block;margin-bottom:14px}
    .urgent-badge{background:#fee2e2;color:#b91c1c;font-weight:800;font-size:0.8rem;text-transform:uppercase;padding:6px 14px;border-radius:20px;display:inline-flex;gap:6px;align-items:center;margin-bottom:14px;margin-left:8px}
    .article-header h1{font-size:clamp(1.8rem,3.2vw,2.5rem);line-height:1.25;color:var(--green);margin:0 0 16px;font-weight:900}
    .meta-line{display:flex;gap:20px;font-size:0.9rem;color:var(--muted);align-items:center;flex-wrap:wrap}
    .article-lead{font-size:1.18rem;line-height:1.65;color:#1e293b;font-weight:600;margin-bottom:24px;background:#f8faf6;padding:18px 22px;border-radius:12px;border-left:4px solid var(--green2)}
    .article-body{font-size:1.05rem;line-height:1.75;color:#334155}
    .article-body p{margin:0 0 20px}
    .article-body h2,.article-body h3{color:var(--green);margin:30px 0 14px}
    
    .sidebar-panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:0 4px 14px rgba(16,24,40,.04);margin-bottom:24px}
    .sidebar-panel h3{margin:0 0 16px;color:var(--green);font-size:1.2rem;display:flex;gap:8px;align-items:center}
    .poll-bar-container{background:#f1f5f9;border-radius:8px;height:24px;overflow:hidden;position:relative;margin:8px 0 14px}
    .poll-bar-fill{background:linear-gradient(90deg,var(--green2),var(--leaf));height:100%;border-radius:8px}
    .poll-bar-label{position:absolute;top:0;left:10px;right:10px;bottom:0;display:flex;justify-content:space-between;align-items:center;font-size:0.8rem;font-weight:700;color:#0f172a}
    
    .comment-item{border-bottom:1px solid var(--line);padding:16px 0}
    .comment-item:last-child{border-bottom:0}
    .comment-author{font-weight:800;color:var(--green);display:flex;justify-content:space-between;font-size:0.95rem;margin-bottom:4px}
    .comment-text{color:#475569;font-size:0.92rem;line-height:1.5;margin:0}
    
    .footer{background:#052915;color:#e8f6ec;margin-top:60px}.footer-inner{max-width:var(--max);margin:0 auto;padding:32px 24px 20px;display:grid;grid-template-columns:minmax(320px,1.55fr) repeat(4,minmax(0,1fr));gap:24px;align-items:start}.footer h3{margin:0 0 8px;color:#fff;font-size:1rem}.footer p,.footer a{color:#cfe5d3;line-height:1.45;font-size:.93rem}.footer a{display:block;margin:4px 0}.footer-brand{display:flex;gap:12px;align-items:center;margin-bottom:10px}.footer-brand img{width:54px;height:54px;background:#fff;border-radius:50%}.footer-bottom{border-top:1px solid rgba(255,255,255,.12);padding:14px 24px;text-align:center;color:#bdd5c1}
    @media(max-width:960px){.article-wrap{grid-template-columns:1fr}.nav-wrap{grid-template-columns:1fr}.footer-inner{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="top-strip">
    <div class="top-inner">
      <div><i class="fas fa-leaf"></i> Building productive coconut communities for Nigeria's future.</div>
      <div class="top-links">
        <a href="about.php">About Us</a><span>|</span><a href="news.php" style="color:#8ed17b;text-decoration:underline;">News & Updates</a><span>|</span><a href="contact.php">Contact Us</a>
        <span class="social"><i class="fab fa-facebook-f"></i><i class="fab fa-x-twitter"></i><i class="fab fa-youtube"></i><i class="fab fa-linkedin"></i></span>
      </div>
    </div>
  </div>

  <header class="header">
    <div class="nav-wrap">
      <a class="brand" href="index.php">
        <img src="<?= e($logo) ?>" alt="NATCODEV">
        <span><strong>NATCODEV</strong><span>NATIONAL COCONUT DEVELOPMENT & PROPAGATION INITIATIVE</span></span>
      </a>
      <nav class="nav" aria-label="Primary navigation">
        <a href="index.php">Home</a>
        <a href="registry/register.php">Registry</a>
        <a href="market/index.php">Marketplace</a>
        <a href="academy/index.php?screen=catalog">Academy</a>
        <a href="news.php" style="color:var(--green2);">News & Insights</a>
        <a href="verify-certificate.php">Verify</a>
        <a href="support/index.php">Support</a>
      </nav>
      <div class="actions">
        <?php if ($isMember): ?>
          <a class="btn light" href="dashboard/index.php">Dashboard</a>
        <?php else: ?>
          <a class="btn light" href="login.php">Login</a>
          <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="article-wrap">
    <?php if (!$post): ?>
      <section class="main-article" style="padding:60px 40px;text-align:center;grid-column:1/-1;">
        <i class="fas fa-triangle-exclamation" style="font-size:3.5rem;color:#e11d48;margin-bottom:16px;"></i>
        <h1 style="color:var(--green);">Announcement Not Found</h1>
        <p class="muted" style="font-size:1.1rem;margin:0 0 24px;">The article you are looking for may have been archived, rescheduled, or requires member login.</p>
        <a href="news.php" class="btn primary"><i class="fas fa-arrow-left"></i> Back to News Desk</a>
      </section>
    <?php else: 
      $img = !empty($post['image_url']) ? $post['image_url'] : 'assets/public/natcodev-community-impact.png';
    ?>
      <article class="main-article">
        <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" class="article-hero-img" onerror="this.src='assets/public/natcodev-community-impact.png'">

        <div class="article-content">
          <div class="article-header">
            <div>
              <span class="cat-badge"><?= e($post['category'] ?? 'General') ?></span>
              <?php if (($post['priority'] ?? '') === 'urgent'): ?>
                <span class="urgent-badge"><i class="fas fa-bolt"></i> High Priority</span>
              <?php endif; ?>
            </div>
            <h1><?= e($post['title']) ?></h1>
            <div class="meta-line">
              <span><i class="far fa-calendar-alt"></i> Published <?= date('F d, Y', strtotime($post['created_at'])) ?></span>
              <span><i class="far fa-eye"></i> <?= number_format((int)($post['views_count'] ?? 0)) ?> Views</span>
              <span><i class="far fa-comments"></i> <?= count($comments) ?> Comments</span>
            </div>
          </div>

          <?php if (!empty($post['summary'])): ?>
            <div class="article-lead"><?= e($post['summary']) ?></div>
          <?php endif; ?>

          <div class="article-body">
            <?= $post['content'] ?>
          </div>

          <!-- Comments Section -->
          <div style="border-top:1px solid var(--line);margin-top:40px;padding-top:30px;">
            <h3 style="color:var(--green);font-size:1.4rem;margin:0 0 20px;"><i class="fas fa-comments"></i> Stakeholder Discussion (<?= count($comments) ?>)</h3>

            <?php if ($feedbackSuccess): ?>
              <div style="background:#f0fdf4;color:#166534;padding:12px 16px;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:18px;font-weight:700;"><?= e($feedbackSuccess) ?></div>
            <?php endif; ?>
            <?php if ($feedbackError): ?>
              <div style="background:#fef2f2;color:#991b1b;padding:12px 16px;border-radius:8px;border:1px solid #fecaca;margin-bottom:18px;font-weight:700;"><?= e($feedbackError) ?></div>
            <?php endif; ?>

            <?php if ($isMember): ?>
              <form method="post" style="margin-bottom:28px;background:#f8faf6;padding:20px;border-radius:12px;border:1px solid var(--line);">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="submit_comment">
                <label style="display:block;font-weight:700;margin-bottom:6px;color:var(--green);">Add Your Comment / Question:</label>
                <textarea name="comment_text" rows="3" style="width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;margin-bottom:10px;" required placeholder="Share your insights or queries with the cooperative network..."></textarea>
                <button type="submit" class="btn primary" style="min-height:38px;padding:8px 18px;font-size:0.95rem;"><i class="fas fa-paper-plane"></i> Post Comment</button>
              </form>
            <?php else: ?>
              <div style="background:#f4faf2;padding:16px 20px;border-radius:12px;border:1px solid var(--line);margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <span>Sign in to participate in stakeholder discussions.</span>
                <a href="login.php" class="btn primary" style="min-height:36px;padding:6px 14px;font-size:0.9rem;">Sign In</a>
              </div>
            <?php endif; ?>

            <div class="comments-list">
              <?php foreach ($comments as $c): ?>
                <div class="comment-item">
                  <div class="comment-author">
                    <span><?= e($c['name'] ?: 'Member') ?> <small style="font-weight:normal;color:var(--muted);">(<?= e($c['platform_role'] ?: 'Grower') ?>)</small></span>
                    <small style="color:var(--muted);font-weight:normal;"><?= date('M d, Y H:i', strtotime($c['created_at'])) ?></small>
                  </div>
                  <p class="comment-text"><?= nl2br(e($c['content'])) ?></p>
                </div>
              <?php endforeach; ?>
              <?php if (empty($comments)): ?>
                <p class="muted" style="text-align:center;padding:20px 0;">No comments posted yet. Be the first to share your thoughts!</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </article>

      <!-- Sidebar -->
      <aside>
        <!-- Community Poll Widget -->
        <div class="sidebar-panel">
          <h3><i class="fas fa-square-poll-vertical"></i> Community Poll</h3>
          <p style="font-size:0.9rem;color:#475569;margin:0 0 14px;">Was this announcement helpful for your farming operations this season?</p>

          <?php if ($hasVotedPoll || !empty($pollResults)): ?>
            <div style="margin-bottom:14px;">
              <?php 
              $opts = ['Very Helpful', 'Moderately Helpful', 'Needs More Details'];
              foreach ($opts as $opt): 
                $res = $pollResults[$opt] ?? ['count' => 0, 'percent' => 0];
              ?>
                <div style="font-size:0.85rem;font-weight:700;display:flex;justify-content:space-between;">
                  <span><?= e($opt) ?></span>
                  <span><?= $res['percent'] ?>% (<?= $res['count'] ?>)</span>
                </div>
                <div class="poll-bar-container">
                  <div class="poll-bar-fill" style="width:<?= $res['percent'] ?>%;"></div>
                </div>
              <?php endforeach; ?>
              <small class="muted" style="display:block;text-align:right;">Total Votes: <?= $totalVotes ?></small>
            </div>
          <?php endif; ?>

          <?php if ($isMember && !$hasVotedPoll): ?>
            <form method="post" style="display:grid;gap:8px;">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="submit_poll">
              <label style="display:flex;gap:8px;align-items:center;font-size:0.9rem;cursor:pointer;">
                <input type="radio" name="poll_option" value="Very Helpful" required> Very Helpful
              </label>
              <label style="display:flex;gap:8px;align-items:center;font-size:0.9rem;cursor:pointer;">
                <input type="radio" name="poll_option" value="Moderately Helpful"> Moderately Helpful
              </label>
              <label style="display:flex;gap:8px;align-items:center;font-size:0.9rem;cursor:pointer;">
                <input type="radio" name="poll_option" value="Needs More Details"> Needs More Details
              </label>
              <button type="submit" class="btn primary" style="min-height:36px;padding:6px 14px;font-size:0.9rem;margin-top:6px;">Submit Vote</button>
            </form>
          <?php elseif (!$isMember): ?>
            <small class="muted"><a href="login.php" style="color:var(--green2);text-decoration:underline;">Sign in</a> to vote in community polls.</small>
          <?php endif; ?>
        </div>

        <!-- Related Updates -->
        <div class="sidebar-panel">
          <h3><i class="fas fa-newspaper"></i> Latest Releases</h3>
          <?php foreach ($relatedPosts as $r): ?>
            <div style="border-bottom:1px solid var(--line);padding:10px 0;">
              <a href="news-detail.php?slug=<?= urlencode((string)$r['slug']) ?>" style="font-weight:700;color:var(--green);font-size:0.92rem;display:block;line-height:1.4;margin-bottom:4px;">
                <?= e($r['title']) ?>
              </a>
              <small class="muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </aside>
    <?php endif; ?>
  </main>

  <footer id="contact" class="footer">
    <div class="footer-inner">
      <div>
        <div class="footer-brand"><img src="<?= e($logo) ?>" alt="NATCODEV"><strong>NATCODEV</strong></div>
        <p>National Coconut Development & Propagation Initiative. Building productive coconut communities and a sustainable coconut value chain.</p>
        <div style="margin-top:12px;display:grid;gap:8px;font-size:.92rem;line-height:1.45;max-width:360px">
          <div>
            <strong style="display:block;color:#fff;margin-bottom:2px">Head Office</strong>
            <span style="display:block"><?= e(implode(', ', $office['address_lines'])) ?></span>
          </div>
          <div>
            <strong style="display:block;color:#fff;margin-bottom:2px">Call Us</strong>
            <a href="tel:<?= e($office['phone_tel']) ?>"><?= e($office['phone_display']) ?></a>
          </div>
        </div>
      </div>
      <div><h3>Registry</h3><a href="apply.php?type=farmer">Grower Registration</a><a href="provider/index.php">Provider Registration</a><a href="field-agent/login.php">Field Network</a></div>
      <div><h3>Marketplace</h3><a href="market/index.php">Browse Marketplace</a><a href="market/stores.php">Seller Directory</a><a href="provider/login.php">Seller Central</a></div>
      <div><h3>Academy</h3><a href="academy/index.php#catalog">Course Catalog</a><a href="academy/">My Learning</a><a href="academy/register.php">Certificates</a></div>
      <div><h3>Support</h3><a href="verify-certificate.php">Verify Certificate</a><a href="login.php">Login</a><a href="support/index.php">Support Desk</a></div>
    </div>
    <div class="footer-bottom">&copy; <?= e($year) ?> NATCODEV. All rights reserved.</div>
  </footer>
</body>
</html>
