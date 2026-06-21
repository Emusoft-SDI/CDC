<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$now = date('Y-m-d H:i:s');
$template = 'indigo_fusion';
$slug = 'faq';
$sections = json_encode(['faq'], JSON_UNESCAPED_SLASHES);

$stmt = $pdo->prepare('SELECT id, secs FROM pages WHERE tempname = ? AND slug = ? LIMIT 1');
$stmt->execute([$template, $slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if ($page) {
    $update = $pdo->prepare('UPDATE pages SET name = ?, secs = ?, updated_at = ? WHERE id = ?');
    $update->execute(['FAQ', $sections, $now, $page['id']]);
    echo "updated_page_id={$page['id']}\n";
    echo "old_secs={$page['secs']}\n";
} else {
    $insert = $pdo->prepare('INSERT INTO pages (tempname, name, slug, secs, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([$template, 'FAQ', $slug, $sections, 0, $now, $now]);
    echo "inserted_page_id={$pdo->lastInsertId()}\n";
}

$count = $pdo->query("SELECT COUNT(*) FROM frontends WHERE tempname='indigo_fusion' AND data_keys='faq.element'")->fetchColumn();
echo "faq_rows={$count}\n";

