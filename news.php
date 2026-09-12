<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/news.php';

$pdo = db();
news_ensure_schema($pdo);

$logo = app_primary_logo_url();
$year = date('Y');
$office = app_contact_office();

$category = trim((string) ($_GET['category'] ?? 'all'));
$search = trim((string) ($_GET['q'] ?? ''));

try {
    $sql = "
        SELECT n.*, COALESCE(a.views_count, 0) AS views_count, COALESCE(a.clicks_count, 0) AS clicks_count
        FROM coop_news n
        LEFT JOIN coop_news_analytics a ON n.id = a.news_id
        WHERE n.status = 'published' AND n.visibility IN ('public', 'both')
    ";
    $params = [];

    if ($category !== 'all' && $category !== '') {
        $sql .= " AND n.category = ?";
        $params[] = $category;
    }
    if ($search !== '') {
        $sql .= " AND (n.title LIKE ? OR n.summary LIKE ? OR n.content LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $sql .= " ORDER BY (n.priority = 'urgent') DESC, n.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $newsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get distinct categories
    $catStmt = $pdo->query("SELECT DISTINCT category FROM coop_news WHERE status = 'published' AND visibility IN ('public', 'both') AND category IS NOT NULL AND category != ''");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $newsList = [];
    $categories = [];
    error_log("Failed to load news: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>News & Insights - NATCODEV National Coconut Initiative</title>
  <meta name="description" content="Stay informed with the latest announcements, agricultural guidebooks, off-take briefings, and milestone reports from NATCODEV.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#063f20;--green2:#08753a;--leaf:#39a84d;--soft:#f4faf2;--gold:#c79010;--teal:#0e7e7d;--blue:#2f72d8;--purple:#5b3ba6;--ink:#111827;--muted:#667085;--line:#dfe8d8;--white:#fff;--shadow:0 18px 42px rgba(16,24,40,.1);--max:1760px}
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f7faf5}a{text-decoration:none;color:inherit}button,input{font:inherit}
    .top-strip{background:linear-gradient(90deg,#063f20,#085d2b);color:#fff;padding:11px 30px;font-weight:800}.top-inner{max-width:var(--max);margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:20px}.top-links{display:flex;gap:18px;align-items:center}.top-links a{color:#fff}.social{display:flex;gap:14px}
    .header{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:40}.nav-wrap{max-width:var(--max);margin:0 auto;min-height:86px;padding:10px 24px;display:grid;grid-template-columns:minmax(260px,330px) minmax(0,1fr) auto;gap:18px;align-items:center}.brand{display:flex;gap:12px;align-items:center;min-width:0}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain;flex:0 0 auto}.brand strong{display:block;font-size:1.85rem;line-height:.95;color:var(--green);letter-spacing:.02em}.brand span{display:block;font-size:.68rem;font-weight:900;color:#111;letter-spacing:.03em}.nav{display:flex;justify-content:center;gap:22px;align-items:center;font-weight:900;min-width:0}.nav a{color:#111827;white-space:nowrap}.nav a:hover{color:var(--green2)}.actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;min-width:0}.btn{border-radius:10px;border:1px solid var(--green);min-height:48px;padding:12px 22px;font-weight:950;display:inline-flex;gap:9px;align-items:center;justify-content:center;white-space:nowrap}.btn.primary{background:var(--green2);color:#fff;border-color:var(--green2)}.btn.light{background:#fff;color:var(--green)}
    
    .news-hero{background:linear-gradient(135deg,rgba(6,63,32,0.92) 0%,rgba(8,117,58,0.88) 100%),url("assets/public/natcodev-community-impact.png") center/cover no-repeat;color:#fff;padding:60px 24px;text-align:center}
    .news-hero h1{font-size:clamp(2.2rem,4.5vw,3.8rem);margin:0 0 12px;line-height:1.15;font-weight:900}
    .news-hero p{font-size:1.2rem;max-width:780px;margin:0 auto 24px;color:#e8f6ec;line-height:1.5}
    .breadcrumb{display:flex;justify-content:center;gap:8px;font-size:0.95rem;color:#ccebd3;font-weight:600}.breadcrumb a{color:#fff}
    
    .content-wrap{max-width:var(--max);margin:36px auto;padding:0 24px}
    .filters-bar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;background:#fff;padding:16px 20px;border-radius:14px;border:1px solid var(--line);box-shadow:0 4px 14px rgba(16,24,40,.04);margin-bottom:28px}
    .cat-pills{display:flex;gap:8px;flex-wrap:wrap}
    .pill{padding:8px 16px;border-radius:24px;background:#f4faf2;color:var(--green);font-size:0.9rem;font-weight:700;border:1px solid var(--line);transition:0.15s ease}
    .pill:hover,.pill.active{background:var(--green2);color:#fff;border-color:var(--green2)}
    .search-box{display:flex;gap:8px;min-width:280px}
    .search-box input{flex:1;padding:8px 14px;border:1px solid var(--line);border-radius:8px;outline:none}
    .search-box button{background:var(--green);color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:700}
    
    .news-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:24px}
    .news-card{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(16,24,40,.04);display:flex;flex-direction:column;transition:transform 0.18s ease,box-shadow 0.18s ease}
    .news-card:hover{transform:translateY(-4px);box-shadow:var(--shadow);border-color:#b9dfbd}
    .news-card-img{width:100%;height:210px;object-fit:cover;background:#e8f6ec;display:block}
    .news-card-body{padding:22px;display:flex;flex-direction:column;flex-grow:1}
    .news-meta-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .news-cat-tag{background:rgba(8,117,58,0.1);color:var(--green2);font-size:0.75rem;font-weight:800;text-transform:uppercase;padding:4px 10px;border-radius:14px;letter-spacing:0.04em}
    .news-urgent-tag{background:#fee2e2;color:#b91c1c;font-size:0.75rem;font-weight:800;text-transform:uppercase;padding:4px 10px;border-radius:14px;display:inline-flex;gap:4px;align-items:center}
    .news-card h3{margin:0 0 10px;font-size:1.25rem;color:var(--green);line-height:1.35;font-weight:800}
    .news-card p{margin:0 0 18px;color:#475569;font-size:0.95rem;line-height:1.55;flex-grow:1}
    .news-card-footer{display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--line);padding-top:14px;margin-top:auto;font-size:0.85rem;color:var(--muted)}
    .read-link{color:var(--green2);font-weight:800;font-size:0.92rem;display:inline-flex;align-items:center;gap:6px}
    
    .footer{background:#052915;color:#e8f6ec;margin-top:60px}.footer-inner{max-width:var(--max);margin:0 auto;padding:32px 24px 20px;display:grid;grid-template-columns:minmax(320px,1.55fr) repeat(4,minmax(0,1fr));gap:24px;align-items:start}.footer h3{margin:0 0 8px;color:#fff;font-size:1rem}.footer p,.footer a{color:#cfe5d3;line-height:1.45;font-size:.93rem}.footer a{display:block;margin:4px 0}.footer-brand{display:flex;gap:12px;align-items:center;margin-bottom:10px}.footer-brand img{width:54px;height:54px;background:#fff;border-radius:50%}.footer-bottom{border-top:1px solid rgba(255,255,255,.12);padding:14px 24px;text-align:center;color:#bdd5c1}
    @media(max-width:900px){.nav-wrap{grid-template-columns:1fr}.news-grid{grid-template-columns:1fr}.footer-inner{grid-template-columns:1fr}}
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
        <a class="btn light" href="login.php">Login</a>
        <a class="btn primary" href="apply.php?type=farmer"><i class="fas fa-user-plus"></i> Register</a>
      </div>
    </div>
  </header>

  <main>
    <section class="news-hero">
      <div class="breadcrumb">
        <a href="index.php">Home</a> &gt; <span>News & Insights</span>
      </div>
      <h1>News, Releases & Announcements</h1>
      <p>Stay informed with policy briefings, training notifications, market off-take reports, and operational updates from the national coconut desk.</p>
    </section>

    <div class="content-wrap">
      <!-- Search and Filter Bar -->
      <div class="filters-bar">
        <div class="cat-pills">
          <a class="pill <?= $category === 'all' ? 'active' : '' ?>" href="news.php">All Articles</a>
          <?php foreach ($categories as $cat): ?>
            <a class="pill <?= $category === $cat ? 'active' : '' ?>" href="news.php?category=<?= urlencode((string)$cat) ?>"><?= e($cat) ?></a>
          <?php endforeach; ?>
        </div>

        <form method="get" class="search-box">
          <?php if ($category !== 'all'): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
          <input type="text" name="q" placeholder="Search news & updates..." value="<?= e($search) ?>">
          <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>
      </div>

      <!-- Articles Grid -->
      <?php if (empty($newsList)): ?>
        <div style="text-align:center;padding:70px 20px;background:#fff;border-radius:16px;border:1px solid var(--line);">
          <i class="fas fa-bullhorn" style="font-size:3.5rem;color:var(--muted);margin-bottom:16px;display:block;"></i>
          <h2 style="color:var(--green);margin:0 0 8px;">No Announcements Found</h2>
          <p class="muted" style="margin:0 0 20px;">No published articles matched your current filter criteria.</p>
          <a href="news.php" class="btn primary">View All News</a>
        </div>
      <?php else: ?>
        <div class="news-grid">
          <?php foreach ($newsList as $post):
            $img = !empty($post['image_url']) ? $post['image_url'] : 'assets/public/natcodev-community-impact.png';
            $detailUrl = 'news-detail.php?slug=' . urlencode((string) $post['slug']);
          ?>
            <article class="news-card">
              <a href="<?= e($detailUrl) ?>" aria-label="<?= e($post['title']) ?>">
                <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" class="news-card-img" onerror="this.src='assets/public/natcodev-community-impact.png'">
              </a>
              <div class="news-card-body">
                <div class="news-meta-top">
                  <span class="news-cat-tag"><?= e($post['category'] ?? 'General') ?></span>
                  <?php if (($post['priority'] ?? '') === 'urgent'): ?>
                    <span class="news-urgent-tag"><i class="fas fa-bolt"></i> Urgent Notice</span>
                  <?php endif; ?>
                </div>

                <h3><a href="<?= e($detailUrl) ?>" style="color:inherit;"><?= e($post['title']) ?></a></h3>
                <p><?= e(mb_strimwidth((string)($post['summary'] ?? strip_tags($post['content'])), 0, 150, '...')) ?></p>

                <div class="news-card-footer">
                  <span><i class="far fa-calendar-alt"></i> <?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                  <a href="<?= e($detailUrl) ?>" class="read-link">Read Full Update <i class="fas fa-arrow-right"></i></a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
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
          <div>
            <strong style="display:block;color:#fff;margin-bottom:2px">Email</strong>
            <a href="mailto:<?= e($office['email']) ?>"><?= e($office['email']) ?></a>
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
