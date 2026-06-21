<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::orderBy('id')->firstOrFail();
auth('admin')->login($admin);

function get_admin_system_page($kernel, $uri)
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

foreach ([
    'system_update' => '/admin/system/system-update',
    'system_info' => '/admin/system/info',
    'server_info' => '/admin/system/server-info',
    'optimize' => '/admin/system/optimize',
] as $name => $uri) {
    [$status, $html] = get_admin_system_page($kernel, $uri);
    echo $name . '_status=' . $status . PHP_EOL;
    echo $name . '_broken=' . (str_contains($html, 'ParseError') || str_contains($html, 'Fatal error') ? 'yes' : 'no') . PHP_EOL;
}
