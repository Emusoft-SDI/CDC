<?php

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$hash = password_hash('user123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'gracious'");
$stmt->execute([$hash]);

echo "Updated gracious password to user123." . PHP_EOL;

