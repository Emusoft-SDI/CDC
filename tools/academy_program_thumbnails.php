<?php
declare(strict_types=1);

/**
 * Academy program thumbnails.
 *
 * Every Academy programme gets a matching thumbnail that is shown on its program
 * card and on every course card in the public catalog / product listing.
 *
 * Thumbnails are generated PNGs stored at:
 *   academy_uploads/thumbnails/<program-slug>.png
 *
 * Usage:
 *   php tools/academy_program_thumbnails.php            # status table (dry run)
 *   php tools/academy_program_thumbnails.php --prompts  # print the image prompt per program
 *   php tools/academy_program_thumbnails.php --register # store present thumbnails in the DB
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/seeds/programs.php';

$args = $argv ?? [];
$printPrompts = in_array('--prompts', $args, true);
$register = in_array('--register', $args, true);

$pdo = db();
academy_ensure_schema($pdo);

$out = static function (string $line): void {
    fwrite(STDOUT, $line . "\n");
};

/**
 * A short, program-specific scene used to build the thumbnail prompt.
 */
function academy_program_thumbnail_prompt(array $program): string
{
    $title = (string) ($program['title'] ?? 'Academy Programme');
    $scenes = [
        'Grower Onboarding Program' => 'a smiling Nigerian coconut grower standing in a young coconut plantation, holding a tablet and registering farm details',
        'Farm Hand Safety Program' => 'two farm workers in high-visibility vests, gloves and safety boots inspecting coconut palms with a supervisor',
        'Provider Accreditation Program' => 'an agricultural input and service provider presenting certified seedlings, fertilizer bags and compliance documents at a clean depot',
        'Field Agent Certification Program' => 'a field agent in branded uniform using a smartphone with GPS to verify a coconut farm boundary',
        'State Coordinator Operations Program' => 'a state coordinator reviewing a wall map and operations dashboard of farms and field agents',
        'Marketplace Seller Certification Program' => 'a marketplace seller grading and packaging fresh coconuts and coconut products for dispatch',
        'Grower & Farm Workforce Academy' => 'a mixed group of coconut growers and farm workers in a practical training session beside young palms',
        'Input & Service Provider Academy' => 'a well stocked agricultural input shop with coconut seedlings, fertilizer, tools and a service desk',
        'Field & Advisory Academy' => 'an agronomist advising a grower over a coconut palm, pointing at leaves with a clipboard',
        'Coordination & Governance Academy' => 'a professional coordination meeting with a large screen showing agricultural data charts and a Nigerian coconut value chain',
        'Investor & Marketplace Buyer Academy' => 'a buyer and investor inspecting premium coconuts and produce crates in a bright warehouse',
        'Nursery Establishment' => 'a tidy coconut nursery with rows of polybag seedlings under shade netting and a caretaker watering them',
        'Good Agricultural Practices' => 'sustainable coconut farming with mulching, intercropping and healthy palms on a well managed Nigerian farm',
    ];

    $scene = $scenes[$title] ?? ('a practical NATCODEV Academy training scene about ' . $title);

    return 'Flat vector illustration for a NATCODEV Academy programme thumbnail: ' . $scene
        . '. Coconut value-chain and Nigerian agriculture theme, deep forest green and harvest gold palette,'
        . ' clean modern educational style, soft natural daylight, wide 16:9 composition, no text, no words, no watermark, no logos.';
}

function academy_program_thumbnail_slug(array $program): string
{
    return academy_slug((string) ($program['title'] ?? ''));
}

$programs = $pdo->query("SELECT id, title, thumbnail FROM academy_programs WHERE COALESCE(status, 'active') = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();

if ($printPrompts) {
    foreach ($programs as $program) {
        $out(json_encode([
            'id' => (int) $program['id'],
            'title' => (string) $program['title'],
            'output' => 'academy_uploads/thumbnails/' . academy_program_thumbnail_slug($program) . '.png',
            'prompt' => academy_program_thumbnail_prompt($program),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
    exit(0);
}

$root = dirname(__DIR__);
$updates = 0;
$missing = 0;

printf("%-5s %-44s %-10s %s\n", 'ID', 'Programme', 'File', 'DB thumbnail');
foreach ($programs as $program) {
    $slug = academy_program_thumbnail_slug($program);
    $relative = 'academy_uploads/thumbnails/' . $slug . '.png';
    $exists = is_file($root . '/' . $relative);
    if (!$exists) {
        $missing++;
    }

    $dbThumb = trim((string) ($program['thumbnail'] ?? ''));
    if ($register && $exists && $dbThumb !== $relative) {
        $stmt = $pdo->prepare('UPDATE academy_programs SET thumbnail = ? WHERE id = ?');
        $stmt->execute([$relative, (int) $program['id']]);
        $updates++;
        $dbThumb = $relative;
    }

    printf(
        "%-5d %-44s %-10s %s\n",
        (int) $program['id'],
        substr((string) $program['title'], 0, 44),
        $exists ? 'present' : 'MISSING',
        $dbThumb === '' ? '-' : $dbThumb
    );
}

$out(str_repeat('-', 78));
if ($register) {
    $out("Registered {$updates} thumbnail(s) in academy_programs.thumbnail.");
} else {
    $out("{$missing} of " . count($programs) . ' thumbnail file(s) missing. Re-run with --register once generated.');
}
