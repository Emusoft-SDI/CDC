<?php
require_once __DIR__ . '/../config.php';
app_require_cli('admin maintenance script');
$pdo = db();
$rows = $pdo->query("SELECT action, description, ip_address, created_at FROM audit_log WHERE action LIKE 'certificate_%' ORDER BY created_at DESC LIMIT 10")->fetchAll();
print_r($rows);
