<?php
declare(strict_types=1);

/**
 * NATCODEV Academy Multimodal Content Formatter.
 *
 * Transforms plain or annotated lesson content into rich visual cards,
 * callout blocks (Takeaways, Pro-Tips, Warnings, Step Timelines),
 * and embeds interactive vector infographics.
 */

function academy_infographics_directory(): string
{
    return dirname(__DIR__, 2) . '/assets/academy/infographics';
}

function academy_lesson_infographic_svg(string $slug): ?string
{
    $slug = preg_replace('/[^a-z0-9_-]/i', '', $slug);
    $path = academy_infographics_directory() . '/' . $slug . '.svg';
    if (!is_file($path)) {
        return null;
    }
    return (string) file_get_contents($path);
}

function academy_render_infographic_block(string $slug, string $title = 'Course Visual Guide'): string
{
    $svg = academy_lesson_infographic_svg($slug);
    if (!$svg) {
        return '';
    }

    $downloadUrl = '../assets/academy/infographics/' . rawurlencode($slug) . '.svg';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div class="infographic-card" id="infographic-{$slug}">
    <div class="infographic-toolbar">
        <div class="infographic-title">
            <i class="fas fa-chart-pie" style="color:var(--green-600)"></i>
            <span>{$safeTitle}</span>
            <span class="infographic-badge">Interactive Vector</span>
        </div>
        <div class="infographic-actions">
            <button type="button" class="infographic-btn" data-action="inspect-infographic" data-target="{$slug}">
                <i class="fas fa-expand"></i> Inspect &amp; Zoom
            </button>
            <a class="infographic-btn" href="{$downloadUrl}" download="{$slug}-infographic.svg" target="_blank" rel="noopener">
                <i class="fas fa-download"></i> Download SVG
            </a>
        </div>
    </div>
    <div class="infographic-viewport">
        {$svg}
    </div>
</div>
HTML;
}

function academy_render_lesson_content(string $content, array $lesson = []): string
{
    if (trim($content) === '') {
        return '';
    }

    // 1. Process [INFOGRAPHIC:slug|Title]
    $content = preg_replace_callback('/\[INFOGRAPHIC:([a-z0-9_-]+)(?:\|([^\]]+))?\]/i', static function (array $matches): string {
        $slug = $matches[1];
        $title = !empty($matches[2]) ? trim($matches[2]) : 'Lesson Visual Guide';
        return academy_render_infographic_block($slug, $title);
    }, $content);

    // 2. Process [TAKEAWAY]...[/TAKEAWAY]
    $content = preg_replace_callback('/\[TAKEAWAY\](.*?)\[\/TAKEAWAY\]/is', static function (array $matches): string {
        $inner = nl2br(trim($matches[1]));
        return <<<HTML
<div class="lesson-callout callout-takeaway">
    <i class="fas fa-bookmark callout-icon"></i>
    <h4>Key Takeaway</h4>
    <div>{$inner}</div>
</div>
HTML;
    }, $content);

    // 3. Process [PROTIP]...[/PROTIP]
    $content = preg_replace_callback('/\[PROTIP\](.*?)\[\/PROTIP\]/is', static function (array $matches): string {
        $inner = nl2br(trim($matches[1]));
        return <<<HTML
<div class="lesson-callout callout-protip">
    <i class="fas fa-lightbulb callout-icon"></i>
    <h4>Field Pro-Tip</h4>
    <div>{$inner}</div>
</div>
HTML;
    }, $content);

    // 4. Process [WARNING]...[/WARNING]
    $content = preg_replace_callback('/\[WARNING\](.*?)\[\/WARNING\]/is', static function (array $matches): string {
        $inner = nl2br(trim($matches[1]));
        return <<<HTML
<div class="lesson-callout callout-warning">
    <i class="fas fa-triangle-exclamation callout-icon"></i>
    <h4>Important Operational Warning</h4>
    <div>{$inner}</div>
</div>
HTML;
    }, $content);

    // 5. Process [STEPS]...[/STEPS]
    $content = preg_replace_callback('/\[STEPS\](.*?)\[\/STEPS\]/is', static function (array $matches): string {
        $lines = array_filter(array_map('trim', explode("\n", trim($matches[1]))));
        $html = '<div class="steps-timeline">';
        $index = 1;
        foreach ($lines as $line) {
            $lineClean = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            $parts = explode(':', $lineClean, 2);
            $head = htmlspecialchars($parts[0], ENT_QUOTES, 'UTF-8');
            $desc = !empty($parts[1]) ? htmlspecialchars(trim($parts[1]), ENT_QUOTES, 'UTF-8') : '';
            $html .= '<div class="step-item"><div class="step-number">' . $index . '</div><div>';
            if ($desc !== '') {
                $html .= '<strong>' . $head . '</strong><p>' . $desc . '</p>';
            } else {
                $html .= '<p style="margin:4px 0 0;font-weight:700">' . $head . '</p>';
            }
            $html .= '</div></div>';
            $index++;
        }
        $html .= '</div>';
        return $html;
    }, $content);

    // 6. Process [CHECKLIST]...[/CHECKLIST]
    $content = preg_replace_callback('/\[CHECKLIST\](.*?)\[\/CHECKLIST\]/is', static function (array $matches): string {
        $lines = array_filter(array_map('trim', explode("\n", trim($matches[1]))));
        $html = '<div class="checklist-card"><h4><i class="fas fa-list-check" style="color:var(--green-600);margin-right:6px"></i> Practical Verification Checklist</h4><div class="checklist-grid">';
        foreach ($lines as $line) {
            $lineClean = htmlspecialchars(ltrim($line, '-*• '), ENT_QUOTES, 'UTF-8');
            $html .= '<div class="checklist-row"><i class="fas fa-circle-check checklist-check"></i><span>' . $lineClean . '</span></div>';
        }
        $html .= '</div></div>';
        return $html;
    }, $content);

    // Split into chunks by double newline and wrap plain text paragraphs in semantic <p> tags
    $blocks = preg_split('/\n{2,}/', trim($content)) ?: [];
    $renderedBlocks = [];
    foreach ($blocks as $block) {
        $trimmed = trim($block);
        if ($trimmed === '') {
            continue;
        }
        if (str_starts_with($trimmed, '<div') || str_starts_with($trimmed, '<table') || str_starts_with($trimmed, '<ul') || str_starts_with($trimmed, '<ol') || str_starts_with($trimmed, '<h')) {
            $renderedBlocks[] = $trimmed;
        } else {
            $renderedBlocks[] = '<p style="margin-bottom:16px;line-height:1.75;font-size:15px;color:var(--slate-700,#334155)">' . nl2br($trimmed) . '</p>';
        }
    }

    return implode("\n\n", $renderedBlocks);
}
