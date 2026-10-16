<?php
declare(strict_types=1);

function academy_sponsorships_admin_view(PDO $pdo, ?int $adminId = null): void
{
    $sponsors = (new class{}); // placeholder pattern; just echo
    include __DIR__ . '/../super-admin/modules/academy-sponsorships.php';
}

function academy_sponsorships_operator_view(PDO $pdo, ?int $operatorId = null): void
{
    include __DIR__ . '/../admin/modules/academy-sponsorships.php';
}
