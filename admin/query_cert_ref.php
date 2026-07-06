<?php
require_once __DIR__ . '/../config.php';
app_require_cli('admin maintenance script');
$pdo = db();
$stmt = $pdo->prepare('SELECT certificate_ref, status FROM provider_accreditation_certificates WHERE provider_id = ? LIMIT 1');
$stmt->execute([1]);
$r = $stmt->fetch();
var_export($r);
