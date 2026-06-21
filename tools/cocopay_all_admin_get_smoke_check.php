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

$routes = [];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $uri = $route->uri();
    $methods = $route->methods();
    if (!in_array('GET', $methods, true)) {
        continue;
    }
    if (!str_starts_with($uri, 'admin')) {
        continue;
    }
    if (str_contains($uri, '{')) {
        continue;
    }
    $routes[] = '/' . ltrim($uri, '/');
}

$routes = array_values(array_unique($routes));
sort($routes);

$failures = 0;
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

        if ($broken) {
            $failures++;
            echo 'FAIL|' . $uri . '|' . $status;
            if (preg_match('/<title>(.*?)<\\/title>/is', $content, $match)) {
                echo '|' . trim(html_entity_decode(strip_tags($match[1])));
            }
            echo PHP_EOL;
        }

        $kernel->terminate($request, $response);
    } catch (Throwable $exception) {
        $failures++;
        echo 'EXCEPTION|' . $uri . '|' . get_class($exception) . '|' . $exception->getMessage() . PHP_EOL;
    }
}

echo 'checked=' . count($routes) . PHP_EOL;
echo 'failures=' . $failures . PHP_EOL;
