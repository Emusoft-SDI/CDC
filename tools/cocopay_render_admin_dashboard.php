<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);
require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::first();
if (!$admin) {
    echo "NO_ADMIN_FOUND" . PHP_EOL;
    exit(1);
}

auth()->guard('admin')->login($admin);

$request = Illuminate\Http\Request::create('/admin/dashboard', 'GET');
$request->setLaravelSession(app('session')->driver());

$start = microtime(true);
$response = $kernel->handle($request);
$elapsed = round((microtime(true) - $start) * 1000, 2);
$content = method_exists($response, 'getContent') ? $response->getContent() : '';

echo 'STATUS=' . $response->getStatusCode() . PHP_EOL;
echo 'ELAPSED_MS=' . $elapsed . PHP_EOL;
echo 'CONTENT_LEN=' . strlen((string) $content) . PHP_EOL;
echo 'HAS_NATADMIN=' . (str_contains((string) $content, 'natadmin-hero') ? 'yes' : 'no') . PHP_EOL;

$kernel->terminate($request, $response);
