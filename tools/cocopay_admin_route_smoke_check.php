<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::first();
if (!$admin) {
    fwrite(STDERR, "No admin account found\n");
    exit(1);
}

$routes = [
    '/admin/dashboard',
    '/admin/frontend/templates',
    '/admin/frontend/manage-seo',
    '/admin/frontend/frontend-sections/faq',
    '/admin/frontend/frontend-sections/service',
    '/admin/frontend/frontend-sections/footer',
    '/admin/setting/system-configuration',
    '/admin/setting/general',
    '/admin/setting/logo-icon',
    '/admin/gateway/automatic',
    '/admin/gateway/manual',
    '/admin/other-banks',
    '/admin/branch',
    '/admin/users',
    '/admin/deposit/history',
    '/admin/withdraw/pending',
    '/admin/loan/running',
    '/admin/ticket',
    '/admin/request-report',
];

foreach ($routes as $uri) {
    auth('admin')->login($admin);

    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);

    try {
        $response = $kernel->handle($request);
        $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        $status = $response->getStatusCode();
        $broken = $status >= 500
            || $status == 419
            || str_contains($content, 'Symfony Exception')
            || str_contains($content, 'Internal Server Error')
            || str_contains($content, 'Sorry your session has expired')
            || str_contains($content, 'Undefined variable')
            || str_contains($content, 'compact():');

        echo $uri . '|' . $status . '|broken=' . ($broken ? 'yes' : 'no');

        if ($broken && preg_match('/<title>(.*?)<\\/title>/is', $content, $match)) {
            echo '|title=' . trim(html_entity_decode(strip_tags($match[1])));
        }

        echo PHP_EOL;
        $kernel->terminate($request, $response);
    } catch (Throwable $exception) {
        echo $uri . '|EXCEPTION|' . get_class($exception) . '|' . $exception->getMessage() . PHP_EOL;
    }
}
