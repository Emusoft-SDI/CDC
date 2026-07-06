<?php
declare(strict_types=1);

function rx_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Registry scalar failed: ' . $e->getMessage());
        return 0;
    }
}

function rx_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Registry rows failed: ' . $e->getMessage());
        return [];
    }
}

function rx_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rx_status_class(string $status): string
{
    $status = strtolower($status);
    return match ($status) {
        'active', 'approved', 'confirmed', 'verified', 'valid', 'issued', 'completed' => 'status-verified',
        'under_review', 'under review', 'processing', 'in_progress' => 'status-under-review',
        'rejected', 'revoked', 'expired', 'failed', 'inactive' => 'status-rejected',
        default => 'status-pending-review',
    };
}

function rx_user_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($words as $word) {
        if ($word !== '') {
            $letters .= strtoupper(substr($word, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : 'NA';
}

function rx_ref(string $prefix): string
{
    return $prefix . '-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function rx_per_page(int $default = 50): int
{
    $value = (int) ($_GET['per_page'] ?? $default);
    return in_array($value, [10, 25, 50, 100, 200, 500], true) ? $value : $default;
}

function rx_pagination_links(int $total, int $limit, int $currentPage, string $targetPage, string $pageParam = 'p', string $sizeParam = 'per_page'): string
{
    $totalPages = max(1, (int) ceil($total / max(1, $limit)));
    $currentPage = min(max(1, $currentPage), $totalPages);
    $base = $_GET;
    unset($base[$pageParam], $base[$sizeParam]);
    $url = static fn(int $page, int $size): string => $targetPage . '?' . http_build_query($base + [$pageParam => $page, $sizeParam => $size]);
    $links = '<div class="pagination" style="margin-top:16px;display:flex;gap:8px;justify-content:center;align-items:center;flex-wrap:wrap">';
    $links .= '<span class="muted">' . number_format($total) . ' result(s)</span>';
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    if ($start > 1) $links .= '<a href="' . rx_e($url(1, $limit)) . '" class="btn btn-sm btn-secondary">1</a>';
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $currentPage) ? 'btn-primary' : 'btn-secondary';
        $links .= '<a href="' . rx_e($url($i, $limit)) . '" class="btn btn-sm ' . $active . '">' . $i . '</a>';
    }
    if ($end < $totalPages) $links .= '<a href="' . rx_e($url($totalPages, $limit)) . '" class="btn btn-sm btn-secondary">' . $totalPages . '</a>';
    $links .= '<form method="get" action="' . rx_e($targetPage) . '" style="display:flex;align-items:center;gap:6px">';
    foreach ($base as $key => $value) if (is_scalar($value)) $links .= '<input type="hidden" name="' . rx_e((string)$key) . '" value="' . rx_e((string)$value) . '">';
    $links .= '<input type="hidden" name="' . rx_e($pageParam) . '" value="1"><label class="muted">Rows <select name="' . rx_e($sizeParam) . '" class="form-select" onchange="this.form.submit()" style="width:auto;display:inline-block">';
    foreach ([10,25,50,100,200,500] as $size) $links .= '<option value="' . $size . '"' . ($limit === $size ? ' selected' : '') . '>' . $size . '</option>';
    $links .= '</select></label></form>';
    $links .= '</div>';
    return $links;
}

function admin_notify_new_user(string $email, string $name, string $tempPassword, string $roleLabel): bool
{
    $loginUrl = app_base_url() . '/login.php';
    return app_send_mail(
        $email,
        'NATCODEV Account Created',
        "Dear {$name},\n\nYour NATCODEV {$roleLabel} account has been created.\nDashboard: {$loginUrl}\nYour temporary password: {$tempPassword}\n\nPlease change your password upon your first login."
    );
}
