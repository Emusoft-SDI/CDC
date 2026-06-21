<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Settlements</title>
<style>body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex;}.main{flex:1; padding:30px;}.card{background:#fff; padding:20px; border-radius:12px;}</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main"><h1>Settlements</h1>
    <div class="card">
        <p>Manage periodic financial settlements here.</p>
    </div>
</div>
</body>
</html>
