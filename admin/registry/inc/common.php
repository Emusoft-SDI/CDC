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

function rx_pagination_links(int $total, int $limit, int $currentPage, string $targetPage): string
{
    $totalPages = (int) ceil($total / $limit);
    if ($totalPages <= 1) return '';
    $links = '<div class="pagination" style="margin-top:16px;display:flex;gap:6px;justify-content:center;flex-wrap:wrap">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = ($i === $currentPage) ? 'btn-primary' : 'btn-secondary';
        $links .= '<a href="' . $targetPage . '?p=' . $i . '" class="btn btn-sm ' . $active . '">' . $i . '</a>';
    }
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
