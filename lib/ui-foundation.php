<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function ntv_status_class(?string $status): string
{
    $status = strtolower(trim((string) $status));
    $status = preg_replace('/[^a-z0-9_ -]/', '', $status) ?: 'pending';
    return str_replace(' ', '-', str_replace('_', '-', $status));
}

function ntv_status_label(?string $status): string
{
    $status = trim((string) $status);
    return $status === '' ? 'Pending' : ucwords(str_replace(['_', '-'], ' ', $status));
}

function ntv_badge(?string $status, ?string $label = null): string
{
    return '<span class="ntv-badge ntv-status-' . e(ntv_status_class($status)) . '">' . e($label ?? ntv_status_label($status)) . '</span>';
}

function ntv_metric_card(string $label, string $value, string $description = '', string $tone = 'green'): string
{
    return '<article class="ntv-metric ntv-tone-' . e($tone) . '">'
        . '<span class="ntv-metric-label">' . e($label) . '</span>'
        . '<strong>' . e($value) . '</strong>'
        . ($description !== '' ? '<small>' . e($description) . '</small>' : '')
        . '</article>';
}

function ntv_action_card(string $title, string $description, string $href, string $action = 'Open', string $status = ''): string
{
    return '<article class="ntv-action-card">'
        . '<div><h3>' . e($title) . '</h3><p>' . e($description) . '</p></div>'
        . ($status !== '' ? ntv_badge($status) : '')
        . '<a class="button secondary" href="' . e($href) . '">' . e($action) . '</a>'
        . '</article>';
}

function ntv_timeline(array $steps): string
{
    $html = '<ol class="ntv-timeline">';
    foreach ($steps as $step) {
        $done = !empty($step['done']);
        $status = $done ? 'done' : (string) ($step['status'] ?? 'pending');
        $html .= '<li class="' . e(ntv_status_class($status)) . '">'
            . '<span aria-hidden="true"></span>'
            . '<div><strong>' . e((string) ($step['label'] ?? 'Step')) . '</strong>';
        if (!empty($step['description'])) {
            $html .= '<small>' . e((string) $step['description']) . '</small>';
        }
        $html .= '</div></li>';
    }
    $html .= '</ol>';
    return $html;
}

function ntv_external_url_is_safe(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (str_starts_with($url, '/')) {
        return true;
    }
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true);
}
