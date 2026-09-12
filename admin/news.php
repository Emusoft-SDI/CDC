<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/news.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
news_ensure_schema($pdo);

$message = '';
$error = '';
$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? 'list'));
$editPost = null;

// Handle CRUD & Broadcasting Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'General'));
        $status = trim((string) ($_POST['status'] ?? 'published'));
        $priority = trim((string) ($_POST['priority'] ?? 'normal'));
        $scheduled_at = !empty($_POST['scheduled_at']) ? trim((string) $_POST['scheduled_at']) : null;
        $content = (string) ($_POST['content'] ?? '');
        $image_url = trim((string) ($_POST['image_url'] ?? ''));
        $visibility = trim((string) ($_POST['visibility'] ?? 'both'));
        if (!in_array($visibility, ['public', 'internal', 'both'], true)) {
            $visibility = 'both';
        }
        if (!in_array($priority, ['normal', 'urgent'], true)) {
            $priority = 'normal';
        }

        // Auto-generate slug if empty
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }

        // Process uploaded image if present
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['cover_image'];
            $fileExt = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($fileExt, $allowedExts, true)) {
                $error = 'Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.';
            } elseif ((int) $file['size'] > 4 * 1024 * 1024) {
                $error = 'Image size must be less than 4MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? (string) finfo_file($finfo, (string)$file['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                if (!in_array($mime, $allowedMimes, true)) {
                    $error = 'Invalid image content.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/news/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0775, true);
                    }

                    $newFileName = 'news_' . bin2hex(random_bytes(10)) . '.' . $fileExt;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file((string)$file['tmp_name'], $destPath)) {
                        $image_url = 'uploads/news/' . $newFileName;
                    } else {
                        $error = 'Failed to save the uploaded image.';
                    }
                }
            }
        }

        if (empty($error)) {
            // 1. Create Article
            if ($action === 'create') {
                if (empty($title) || empty($content)) {
                    $error = 'Title and content are required.';
                } else {
                    try {
                        $check = $pdo->prepare("SELECT id FROM coop_news WHERE slug = ?");
                        $check->execute([$slug]);
                        if ($check->fetch()) {
                            $slug .= '-' . time();
                        }

                        $adminId = (int) ($_SESSION['admin_user']['id'] ?? 1);
                        $stmt = $pdo->prepare("
                            INSERT INTO coop_news (title, slug, summary, category, status, priority, scheduled_at, visibility, content, image_url, author_id, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$title, $slug, $summary, $category, $status, $priority, $scheduled_at, $visibility, $content, $image_url, $adminId]);
                        $newId = (int) $pdo->lastInsertId();

                        $pdo->prepare("INSERT INTO coop_news_analytics (news_id, views_count, clicks_count) VALUES (?, 0, 0)")->execute([$newId]);

                        $message = 'News article published successfully!';
                        $action = 'list';
                    } catch (Throwable $e) {
                        $error = 'Error publishing article: ' . $e->getMessage();
                    }
                }
            }

            // 2. Edit Article & Save Version Snapshot
            elseif ($action === 'edit') {
                $id = (int) ($_POST['id'] ?? 0);
                if (empty($title) || empty($content) || $id <= 0) {
                    $error = 'Title, content, and valid ID are required.';
                } else {
                    try {
                        // Check slug uniqueness
                        $check = $pdo->prepare("SELECT id FROM coop_news WHERE slug = ? AND id != ?");
                        $check->execute([$slug, $id]);
                        if ($check->fetch()) {
                            $slug .= '-' . time();
                        }

                        // Archive previous version snapshot
                        $oldStmt = $pdo->prepare("SELECT * FROM coop_news WHERE id = ?");
                        $oldStmt->execute([$id]);
                        $oldPost = $oldStmt->fetch(PDO::FETCH_ASSOC);

                        if ($oldPost) {
                            $verStmt = $pdo->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM coop_news_versions WHERE news_id = ?");
                            $verStmt->execute([$id]);
                            $nextVer = (int) $verStmt->fetchColumn();

                            $adminId = (int) ($_SESSION['admin_user']['id'] ?? 1);
                            $pdo->prepare("
                                INSERT INTO coop_news_versions (news_id, title_snapshot, summary_snapshot, content_snapshot, editor_id, version_number, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())
                            ")->execute([$id, $oldPost['title'], $oldPost['summary'], $oldPost['content'], $adminId, $nextVer]);
                        }

                        $stmt = $pdo->prepare("
                            UPDATE coop_news 
                            SET title = ?, slug = ?, summary = ?, category = ?, status = ?, priority = ?, scheduled_at = ?, visibility = ?, content = ?, image_url = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $slug, $summary, $category, $status, $priority, $scheduled_at, $visibility, $content, $image_url, $id]);

                        $message = 'News article updated successfully (version snapshot saved)!';
                        $action = 'list';
                    } catch (Throwable $e) {
                        $error = 'Error updating article: ' . $e->getMessage();
                    }
                }
            }

            // 3. Rollback to Prior Version
            elseif ($action === 'rollback') {
                $newsId = (int) ($_POST['news_id'] ?? 0);
                $verNum = (int) ($_POST['version'] ?? 0);
                if ($newsId > 0 && $verNum > 0) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM coop_news_versions WHERE news_id = ? AND version_number = ? LIMIT 1");
                        $stmt->execute([$newsId, $verNum]);
                        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($snapshot) {
                            $pdo->prepare("UPDATE coop_news SET title = ?, summary = ?, content = ? WHERE id = ?")->execute([
                                $snapshot['title_snapshot'],
                                $snapshot['summary_snapshot'],
                                $snapshot['content_snapshot'],
                                $newsId
                            ]);
                            $message = "Post successfully rolled back to Version #{$verNum}!";
                        } else {
                            $error = 'Snapshot version not found.';
                        }
                    } catch (Throwable $e) {
                        $error = 'Rollback failed: ' . $e->getMessage();
                    }
                }
            }

            // 4. Multi-Channel Broadcast Dispatch
            elseif ($action === 'execute_broadcast') {
                $id = (int) ($_POST['id'] ?? 0);
                $target = trim((string) ($_POST['audience_target'] ?? 'all'));
                $channels = (array) ($_POST['delivery_channels'] ?? []);
                $custom_subject = trim((string) ($_POST['custom_subject'] ?? ''));

                try {
                    $stmt = $pdo->prepare("SELECT * FROM coop_news WHERE id = ?");
                    $stmt->execute([$id]);
                    $article = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$article) {
                        throw new Exception('Article not found.');
                    }

                    $subject = !empty($custom_subject) ? $custom_subject : 'NATCODEV Notice: ' . $article['title'];

                    // Recipients query
                    $recipients = [];
                    if ($target === 'all' || $target === 'growers') {
                        $recipients = $pdo->query("SELECT id, email, phone, name FROM users WHERE account_status = 'active' OR account_status IS NULL")->fetchAll(PDO::FETCH_ASSOC);
                    } elseif ($target === 'field_agents') {
                        $recipients = $pdo->query("SELECT id, email, phone, name FROM users WHERE platform_role = 'field_agent'")->fetchAll(PDO::FETCH_ASSOC);
                    } elseif ($target === 'individual') {
                        $search = trim((string) ($_POST['individual_identifier'] ?? ''));
                        $iStmt = $pdo->prepare("SELECT id, email, phone, name FROM users WHERE email = ? OR phone = ? LIMIT 1");
                        $iStmt->execute([$search, $search]);
                        $recipients = $iStmt->fetchAll(PDO::FETCH_ASSOC);
                    }

                    $sentCount = 0;
                    $articleUrl = app_base_url() . '/news-detail.php?slug=' . urlencode((string) $article['slug']);

                    // Dispatch In-App Platform Broadcast
                    if (in_array('in_app', $channels, true) && app_table_exists($pdo, 'platform_broadcasts')) {
                        $pdo->prepare("
                            INSERT INTO platform_broadcasts (scope, audience, title, message, channel, priority, status, published_at)
                            VALUES ('national', ?, ?, ?, 'in_app', ?, 'published', NOW())
                        ")->execute([
                            $target,
                            $subject,
                            (string)($article['summary'] ?: strip_tags((string)$article['content'])),
                            $article['priority'] ?? 'normal'
                        ]);
                    }

                    // Dispatch Branded Email Announcements
                    if (in_array('email', $channels, true)) {
                        $emailHtml = "
                            <h2 style=\"color:#075f2a;margin:0 0 14px;font-size:20px;\">" . e($article['title']) . "</h2>
                            <p style=\"margin:0 0 16px;color:#475569;line-height:1.6;\">" . nl2br(e((string)($article['summary'] ?? ''))) . "</p>
                            <div style=\"margin:20px 0;\">" . $article['content'] . "</div>
                            <p style=\"margin:24px 0 0;\"><a href=\"" . e($articleUrl) . "\" style=\"display:inline-block;padding:12px 24px;background:#2d5016;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;\">Read Full Update Online</a></p>
                        ";
                        $brandedHtml = app_branded_email_html($subject, $emailHtml);

                        foreach ($recipients as $rec) {
                            if (!empty($rec['email']) && filter_var($rec['email'], FILTER_VALIDATE_EMAIL)) {
                                if (app_send_mail((string) $rec['email'], $subject, strip_tags($emailHtml), $brandedHtml)) {
                                    $sentCount++;
                                }
                            }
                        }
                    }

                    $message = "Broadcast completed! Sent to " . count($recipients) . " target recipients (" . $sentCount . " emails delivered).";
                    $action = 'list';
                } catch (Throwable $e) {
                    $error = 'Broadcast failed: ' . $e->getMessage();
                }
            }

            // 5. Delete Article
            elseif ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id > 0) {
                    try {
                        $pdo->prepare("DELETE FROM coop_news WHERE id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM coop_news_versions WHERE news_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM coop_news_feedback WHERE news_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM coop_news_analytics WHERE news_id = ?")->execute([$id]);
                        $message = 'Article deleted successfully.';
                        $action = 'list';
                    } catch (Throwable $e) {
                        $error = 'Delete failed: ' . $e->getMessage();
                    }
                }
            }

            // 6. Manual Cron Release Execution
            elseif ($action === 'run_scheduler') {
                require_once __DIR__ . '/news_cron.php';
                $stats = news_cron_process_matured_posts($pdo);
                if ($stats['published_count'] > 0) {
                    $message = "Scheduler executed: Auto-published {$stats['published_count']} scheduled post(s)!";
                } else {
                    $message = "Scheduler executed: No scheduled posts are currently due for release.";
                }
            }
        }
    }
}

// Fetch Article if editing or broadcasting view requested via GET
if (in_array($action, ['edit', 'broadcast'], true) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM coop_news WHERE id = ?");
    $stmt->execute([$id]);
    $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$editPost) {
        $error = 'Article not found.';
        $action = 'list';
    }
}

// Fetch All Articles & Analytics
$newsList = $pdo->query("
    SELECT n.*, COALESCE(a.views_count, 0) AS views_count, COALESCE(a.clicks_count, 0) AS clicks_count
    FROM coop_news n
    LEFT JOIN coop_news_analytics a ON n.id = a.news_id
    ORDER BY n.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('News & Communications Desk', [
    'active' => 'news.php',
    'description' => 'Author, schedule, and broadcast official announcements, guidebooks, off-take briefings, and policy updates across the cooperative network.',
    'wide' => true,
]);
?>

<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div style="display:flex;gap:10px;">
    <a href="news.php?action=create" class="button primary"><i class="fas fa-plus"></i> New Announcement</a>
    <form method="post" style="display:inline;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="run_scheduler">
      <button type="submit" class="button secondary"><i class="fas fa-clock"></i> Run Scheduler Check</button>
    </form>
  </div>
  <a href="../news.php" target="_blank" class="button secondary"><i class="fas fa-external-link"></i> View Public News Portal</a>
</div>

<?php if ($action === 'create' || ($action === 'edit' && $editPost)): ?>
  <!-- Create / Edit Form -->
  <section class="panel" style="margin-bottom:24px;">
    <h2><i class="fas fa-edit"></i> <?= $action === 'create' ? 'Create New Announcement' : 'Edit Announcement: ' . e($editPost['title']) ?></h2>

    <form method="post" enctype="multipart/form-data" style="display:grid;gap:16px;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'edit' ?>">
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int) $editPost['id'] ?>"><?php endif; ?>

      <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
        <label>
          <strong>Article Title *</strong>
          <input type="text" name="title" value="<?= e($editPost['title'] ?? '') ?>" required style="width:100%;padding:8px;margin-top:4px;">
        </label>
        <label>
          <strong>Category</strong>
          <select name="category" style="width:100%;padding:8px;margin-top:4px;">
            <?php foreach (['General', 'Training', 'Market', 'Registry', 'Agronomy', 'Finance', 'Policy'] as $cat): ?>
              <option value="<?= $cat ?>" <?= ($editPost['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
        <label>
          <strong>Status</strong>
          <select name="status" style="width:100%;padding:8px;margin-top:4px;">
            <option value="published" <?= ($editPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published (Live)</option>
            <option value="draft" <?= ($editPost['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="scheduled" <?= ($editPost['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
            <option value="archived" <?= ($editPost['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </label>
        <label>
          <strong>Priority</strong>
          <select name="priority" style="width:100%;padding:8px;margin-top:4px;">
            <option value="normal" <?= ($editPost['priority'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="urgent" <?= ($editPost['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent Notice</option>
          </select>
        </label>
        <label>
          <strong>Visibility</strong>
          <select name="visibility" style="width:100%;padding:8px;margin-top:4px;">
            <option value="both" <?= ($editPost['visibility'] ?? 'both') === 'both' ? 'selected' : '' ?>>Public & Members</option>
            <option value="public" <?= ($editPost['visibility'] ?? '') === 'public' ? 'selected' : '' ?>>Public Only</option>
            <option value="internal" <?= ($editPost['visibility'] ?? '') === 'internal' ? 'selected' : '' ?>>Members Only</option>
          </select>
        </label>
        <label>
          <strong>Schedule Date (if scheduled)</strong>
          <input type="datetime-local" name="scheduled_at" value="<?= !empty($editPost['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($editPost['scheduled_at'])) : '' ?>" style="width:100%;padding:8px;margin-top:4px;">
        </label>
      </div>

      <label>
        <strong>Short Summary / Excerpt</strong>
        <textarea name="summary" rows="2" style="width:100%;padding:8px;margin-top:4px;" placeholder="Brief 1-2 sentence lead for card previews and email headers"><?= e($editPost['summary'] ?? '') ?></textarea>
      </label>

      <label>
        <strong>Cover Image</strong>
        <input type="file" name="cover_image" accept="image/*" style="width:100%;padding:8px;margin-top:4px;">
        <?php if (!empty($editPost['image_url'])): ?>
          <input type="hidden" name="image_url" value="<?= e($editPost['image_url']) ?>">
          <small class="muted">Current image: <?= e($editPost['image_url']) ?></small>
        <?php endif; ?>
      </label>

      <label>
        <strong>Full Article Body (HTML Supported) *</strong>
        <textarea name="content" rows="10" required style="width:100%;padding:10px;margin-top:4px;font-family:monospace;"><?= e($editPost['content'] ?? '') ?></textarea>
      </label>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="button primary"><?= $action === 'create' ? 'Publish Announcement' : 'Save Changes & Create Version' ?></button>
        <a href="news.php" class="button secondary">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<?php if ($action === 'broadcast' && $editPost): ?>
  <!-- Broadcast Campaign Modal View -->
  <section class="panel" style="margin-bottom:24px;background:#f0fdf4;border-color:#86efac;">
    <h2><i class="fas fa-bullhorn"></i> Multi-Channel Broadcast Dispatch: <?= e($editPost['title']) ?></h2>
    <p class="muted">Blast this official announcement directly to stakeholders via In-App Feed and Corporate Branded Email notifications.</p>

    <form method="post" style="display:grid;gap:16px;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="execute_broadcast">
      <input type="hidden" name="id" value="<?= (int) $editPost['id'] ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <label>
          <strong>Target Audience</strong>
          <select name="audience_target" style="width:100%;padding:8px;margin-top:4px;">
            <option value="all">All Active Stakeholders & Outgrowers</option>
            <option value="growers">Registered Growers Only</option>
            <option value="field_agents">Field Agents & Extension Workers</option>
            <option value="individual">Individual Member (By Email or Phone)</option>
          </select>
        </label>
        <label>
          <strong>Individual Email/Phone (if targeting single user)</strong>
          <input type="text" name="individual_identifier" placeholder="e.g. farmer@example.com" style="width:100%;padding:8px;margin-top:4px;">
        </label>
      </div>

      <label>
        <strong>Custom Email Subject Override</strong>
        <input type="text" name="custom_subject" value="NATCODEV Urgent Notice: <?= e($editPost['title']) ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>

      <label>
        <strong>Delivery Channels</strong>
        <div style="display:flex;gap:20px;margin-top:6px;">
          <label style="display:flex;gap:6px;align-items:center;cursor:pointer;"><input type="checkbox" name="delivery_channels[]" value="in_app" checked> In-App Platform Broadcast</label>
          <label style="display:flex;gap:6px;align-items:center;cursor:pointer;"><input type="checkbox" name="delivery_channels[]" value="email" checked> Corporate Branded Email</label>
        </div>
      </label>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="button" style="background:#08753a;color:#fff;"><i class="fas fa-paper-plane"></i> Execute Broadcast Campaign</button>
        <a href="news.php" class="button secondary">Cancel</a>
      </div>
    </form>
  </section>
<?php endif; ?>

<!-- Articles Table Overview -->
<section class="panel">
  <h2><i class="fas fa-newspaper"></i> Published & Queued Communications Desk Articles</h2>

  <div style="overflow-x:auto;">
    <table class="data-table" style="width:100%;">
      <thead>
        <tr>
          <th>Title & Category</th>
          <th>Status</th>
          <th>Priority</th>
          <th>Visibility</th>
          <th>Views / Clicks</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($newsList as $item): ?>
          <tr>
            <td>
              <strong><?= e($item['title']) ?></strong>
              <br><small class="muted"><span class="tag"><?= e($item['category'] ?? 'General') ?></span> Slug: <code><?= e($item['slug']) ?></code></small>
            </td>
            <td><span class="tag <?= $item['status'] === 'published' ? 'green' : ($item['status'] === 'scheduled' ? 'blue' : 'amber') ?>"><?= strtoupper(e($item['status'])) ?></span></td>
            <td><span class="tag <?= $item['priority'] === 'urgent' ? 'red' : 'green' ?>"><?= strtoupper(e($item['priority'])) ?></span></td>
            <td><span class="tag"><?= strtoupper(e($item['visibility'])) ?></span></td>
            <td><small><i class="far fa-eye"></i> <?= number_format((int)$item['views_count']) ?> | <i class="fas fa-mouse-pointer"></i> <?= number_format((int)$item['clicks_count']) ?></small></td>
            <td><small><?= date('M d, Y', strtotime($item['created_at'])) ?></small></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="news.php?action=edit&id=<?= (int)$item['id'] ?>" class="button secondary" style="padding:4px 8px;font-size:0.8rem;"><i class="fas fa-edit"></i> Edit</a>
                <a href="news.php?action=broadcast&id=<?= (int)$item['id'] ?>" class="button secondary" style="padding:4px 8px;font-size:0.8rem;background:#f0fdf4;border-color:#86efac;color:#166534;"><i class="fas fa-bullhorn"></i> Broadcast</a>
                <a href="../news-detail.php?slug=<?= urlencode((string)$item['slug']) ?>" target="_blank" class="button secondary" style="padding:4px 8px;font-size:0.8rem;"><i class="fas fa-eye"></i> View</a>
                <form method="post" onsubmit="return confirm('Are you sure you want to delete this article?');" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                  <button type="submit" class="button secondary" style="padding:4px 8px;font-size:0.8rem;color:#e11d48;"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($newsList)): ?>
          <tr><td colspan="7" style="text-align:center;" class="muted">No news articles created yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php admin_page_end(); ?>
