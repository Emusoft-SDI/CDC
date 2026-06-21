<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::orderBy('id')->firstOrFail();
auth('admin')->login($admin);

function get_admin_backup_page($kernel, $uri)
{
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);
    $response = $kernel->handle($request);
    $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $content];
}

[$backupStatus, $backupHtml] = get_admin_backup_page($kernel, '/admin/backups');
[$dashboardStatus, $dashboardHtml] = get_admin_backup_page($kernel, '/admin/dashboard');

echo 'backup_status=' . $backupStatus . PHP_EOL;
echo 'backup_has_create=' . (str_contains($backupHtml, 'Create Backup') && str_contains($backupHtml, 'Run Backup Now') ? 'yes' : 'no') . PHP_EOL;
echo 'backup_has_targets=' . (str_contains($backupHtml, 'Google Drive') && str_contains($backupHtml, 'Bitbucket') && str_contains($backupHtml, 'S3 Compatible') ? 'yes' : 'no') . PHP_EOL;
echo 'dashboard_status=' . $dashboardStatus . PHP_EOL;
echo 'sidebar_has_backup=' . (str_contains($dashboardHtml, 'Platform Backup') && str_contains($dashboardHtml, '/admin/backups') ? 'yes' : 'no') . PHP_EOL;
