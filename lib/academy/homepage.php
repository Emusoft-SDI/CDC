<?php
declare(strict_types=1);

/**
 * Public Academy homepage presentation settings.
 *
 * Stores the editable homepage (hero image, hero copy, featured course,
 * trust badges, pathways, CTA) as a single JSON row in the settings table
 * so admins can restyle the campus without touching code.
 */

function academy_homepage_defaults(): array
{
    return [
        'hero_image' => '',
        'eyebrow' => 'Coconut value-chain learning campus',
        'headline' => 'Grow the <em>tree of life</em> — train today, thrive for generations.',
        'lede' => 'NATCODEV Academy is the education entry point for growers, learners, field teams, sellers, providers, and coordinators. Explore structured programs, enroll in practical courses, complete assessments, and earn certificates that prepare you for approved platform roles.',
        'featured_course_id' => 0,
        'trust' => ['Free courses available', 'Verifiable certificates', 'Approved role pathways'],
        'pathways' => [
            ['icon' => 'fa-seedling', 'title' => 'Grower Foundation', 'text' => 'Farm records, profile readiness, field evidence, wallet basics, and certificate preparation.'],
            ['icon' => 'fa-store', 'title' => 'Market Readiness', 'text' => 'Product listing, fulfillment discipline, customer support, refunds, and seller trust standards.'],
            ['icon' => 'fa-handshake-angle', 'title' => 'Provider Accreditation', 'text' => 'Service capacity, compliance evidence, delivery quality, and provider onboarding.'],
            ['icon' => 'fa-map-location-dot', 'title' => 'Field & Advisory', 'text' => 'Verification visits, GPS evidence, safety routines, advisory reporting, and escalation.'],
        ],
        'cta_heading' => 'Your next role starts in the classroom.',
        'cta_copy' => 'Join the Academy campus as a learner, complete the pathway that matches your ambition, and carry a verifiable certificate into your platform role request.',
    ];
}

function academy_homepage_settings(PDO $pdo): array
{
    static $cached = [];
    if ($cached !== []) {
        return $cached;
    }

    $settings = academy_homepage_defaults();
    if (app_table_exists($pdo, 'settings')) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'academy_homepage' LIMIT 1");
        $stmt->execute();
        $raw = (string) $stmt->fetchColumn();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $settings = array_merge($settings, $decoded);
            }
        }
    }

    $settings['hero_image'] = (string) $settings['hero_image'];
    $settings['eyebrow'] = (string) $settings['eyebrow'];
    $settings['headline'] = (string) $settings['headline'];
    $settings['lede'] = (string) $settings['lede'];
    $settings['featured_course_id'] = (int) $settings['featured_course_id'];
    $settings['cta_heading'] = (string) $settings['cta_heading'];
    $settings['cta_copy'] = (string) $settings['cta_copy'];

    $trust = [];
    foreach (array_slice(is_array($settings['trust'] ?? null) ? $settings['trust'] : [], 0, 3) as $label) {
        $trust[] = (string) $label;
    }
    $settings['trust'] = $trust !== [] ? $trust : academy_homepage_defaults()['trust'];

    $pathways = [];
    foreach (array_slice(is_array($settings['pathways'] ?? null) ? $settings['pathways'] : [], 0, 4) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $pathways[] = [
            'icon' => (string) ($row['icon'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'text' => (string) ($row['text'] ?? ''),
        ];
    }
    $settings['pathways'] = $pathways !== [] ? $pathways : academy_homepage_defaults()['pathways'];

    $cached = $settings;
    return $settings;
}

function academy_homepage_save(PDO $pdo, array $data): void
{
    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('academy_homepage', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $stmt->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

/**
 * Validated image upload into academy_uploads/<subdir>/. Returns a web-root
 * relative path (e.g. academy_uploads/hero/academy_hero_..._png.png) or an
 * empty string when no file was selected.
 */
function academy_upload_homepage_image(string $field, string $subdir): string
{
    if (empty($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    $upload = app_uploaded_file_info((array) $_FILES[$field], ['jpg', 'jpeg', 'png', 'webp'], 3 * 1024 * 1024, 'Academy image');
    $dir = dirname(__DIR__, 2) . '/academy_uploads/' . $subdir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fileName = app_safe_upload_name('academy_' . $subdir, $upload['name'], $upload['extension']);
    if (!move_uploaded_file($upload['tmp_name'], $dir . '/' . $fileName)) {
        throw new RuntimeException('Unable to upload the Academy image. Check upload folder permissions.');
    }
    return 'academy_uploads/' . $subdir . '/' . $fileName;
}

/**
 * Resolve a stored homepage image path to a public URL. Stored paths are
 * re-validated against a strict allowlist and checked on disk so a tampered
 * settings row can never point outside academy_uploads; anything invalid
 * falls back to the built-in hero artwork.
 */
function academy_homepage_image_url(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path !== '' && preg_match('#^academy_uploads/(hero|thumbnails)/[\w.-]+\.(jpe?g|png|webp)$#i', $path) === 1 && is_file(dirname(__DIR__, 2) . '/' . $path)) {
        return '../' . $path;
    }
    return '../assets/academy/natcodev-academy-public-hero.png';
}

function academy_homepage_html(string $value): string
{
    return trim(strip_tags($value, '<em><strong>'));
}
