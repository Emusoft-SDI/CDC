<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "pages matching faq:\n";
foreach ($pdo->query("SELECT id, tempname, name, slug, secs FROM pages WHERE slug='faq' ORDER BY id") as $row) {
    echo "{$row['id']} | {$row['tempname']} | {$row['name']} | {$row['slug']} | {$row['secs']}\n";
}

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'ignore_errors' => true,
        'header' => "Cache-Control: no-cache\r\n",
    ],
]);

$url = 'http://localhost/cocopay/faq?codex_review=' . time();
$body = @file_get_contents($url, false, $context);
$headers = $http_response_header ?? [];

echo "\nhttp headers:\n";
foreach ($headers as $header) {
    echo $header . "\n";
}

echo "\nhttp length=" . strlen((string) $body) . "\n";
echo "has_heading=" . ($body && str_contains($body, 'Frequently Asked Questions') ? 'yes' : 'no') . "\n";
echo "has_search=" . ($body && str_contains($body, 'natcodevFaqSearch') ? 'yes' : 'no') . "\n";

if ($body) {
    $text = strip_tags($body);
    $text = preg_replace('/\s+/', ' ', $text);
    echo "sample=" . mb_substr($text, 0, 500) . "\n";
}

