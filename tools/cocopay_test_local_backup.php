<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = App\Models\Admin::orderBy('id')->firstOrFail();
auth('admin')->login($admin);

$request = Illuminate\Http\Request::create('/admin/backups/create', 'POST', ['target' => 'local']);
$controller = app(App\Http\Controllers\Admin\BackupController::class);
$before = glob(config('backup.local_path') . DIRECTORY_SEPARATOR . 'natcodev-backup-*.zip') ?: [];
$controller->create($request);
$after = glob(config('backup.local_path') . DIRECTORY_SEPARATOR . 'natcodev-backup-*.zip') ?: [];

echo 'zip_extension=' . (class_exists(ZipArchive::class) ? 'yes' : 'no') . PHP_EOL;
echo 'backup_created=' . (count($after) > count($before) ? 'yes' : 'no') . PHP_EOL;
if ($after) {
    usort($after, fn($a, $b) => filemtime($b) <=> filemtime($a));
    echo 'latest_backup=' . basename($after[0]) . PHP_EOL;
    echo 'latest_size=' . filesize($after[0]) . PHP_EOL;
}
