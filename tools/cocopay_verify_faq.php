<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$count = $pdo->query("SELECT COUNT(*) FROM frontends WHERE tempname='templates.indigo_fusion.' AND data_keys='faq.element'")->fetchColumn();
$content = $pdo->query("SELECT data_values FROM frontends WHERE tempname='templates.indigo_fusion.' AND data_keys='faq.content' LIMIT 1")->fetchColumn();
$view = file_get_contents('C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/indigo_fusion/sections/faq.blade.php');

echo "faq_rows={$count}\n";
echo 'content_has_heading=' . (str_contains($content, 'Frequently Asked Questions') ? 'yes' : 'no') . "\n";
echo 'view_has_search=' . (str_contains($view, 'natcodevFaqSearch') ? 'yes' : 'no') . "\n";
echo 'view_has_categories=' . (str_contains($view, 'data-faq-category') ? 'yes' : 'no') . "\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true,
    ],
]);

$page = @file_get_contents('http://localhost/cocopay/faq', false, $context);
echo 'http_contains_faq=' . ($page && str_contains($page, 'Frequently Asked Questions') ? 'yes' : 'no') . "\n";
echo 'http_contains_search=' . ($page && str_contains($page, 'natcodevFaqSearch') ? 'yes' : 'no') . "\n";
