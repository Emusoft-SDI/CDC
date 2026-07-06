<?php
require_once 'config.php';

try {
    $pdo = db();
    echo "Database connection successful\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT VERSION() as version");
    $result = $stmt->fetch();
    echo "Database version: " . $result['version'] . "\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>