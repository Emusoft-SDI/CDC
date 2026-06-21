<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function send_request($kernel, $method, $uri, array $parameters = [], array $server = [])
{
    $request = Illuminate\Http\Request::create($uri, $method, $parameters, [], [], array_merge([
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ], $server));

    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

$admin = App\Models\Admin::first();
if (!$admin) {
    fwrite(STDERR, "No admin account found\n");
    exit(1);
}

auth('admin')->login($admin);

$getResponse = send_request($kernel, 'GET', '/admin/frontend/templates');
$getBody = $getResponse->getContent();

$postResponse = send_request($kernel, 'POST', '/admin/frontend/templates', ['name' => 'crystal_sky']);

$postStatus = method_exists($postResponse, 'getStatusCode') ? $postResponse->getStatusCode() : 'unknown';
$postLocation = method_exists($postResponse, 'headers') ? $postResponse->headers->get('Location') : null;

echo 'templates_get_status=' . $getResponse->getStatusCode() . PHP_EOL;
echo 'templates_get_has_page=' . (str_contains($getBody, 'Crystal Sky') || str_contains($getBody, 'crystal_sky') ? 'yes' : 'no') . PHP_EOL;
echo 'templates_get_broken=' . (str_contains($getBody, 'Internal Server Error') || str_contains($getBody, 'Sorry your session has expired') ? 'yes' : 'no') . PHP_EOL;
if ($getResponse->getStatusCode() >= 500) {
    if (preg_match('/<title>(.*?)<\\/title>/is', $getBody, $match)) {
        echo 'templates_get_title=' . trim(html_entity_decode(strip_tags($match[1]))) . PHP_EOL;
    }
    if (preg_match('/<h1[^>]*>(.*?)<\\/h1>/is', $getBody, $match)) {
        echo 'templates_get_h1=' . trim(html_entity_decode(strip_tags($match[1]))) . PHP_EOL;
    }
}
echo 'expired_post_status=' . $postStatus . PHP_EOL;
echo 'expired_post_location=' . ($postLocation ?: 'none') . PHP_EOL;
echo 'expired_post_is_419=' . ($postStatus == 419 ? 'yes' : 'no') . PHP_EOL;
