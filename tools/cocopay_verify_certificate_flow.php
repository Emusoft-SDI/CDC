<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

function handle_request($kernel, string $method, string $uri)
{
    $request = Illuminate\Http\Request::create($uri, $method, [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);
    $response = $kernel->handle($request);
    $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    $kernel->terminate($request, $response);
    return [$response->getStatusCode(), $content];
}

[$registerStatus, $registerHtml] = handle_request($kernel, 'GET', '/user/register');

$user = App\Models\User::orderBy('id')->first();
if ($user) {
    auth()->login($user);
}
[$profileStatus, $profileHtml] = handle_request($kernel, 'GET', '/user/profile-setting');

echo 'register_status=' . $registerStatus . PHP_EOL;
echo 'register_has_file_input=' . (str_contains($registerHtml, 'name="grower_certificate"') ? 'yes' : 'no') . PHP_EOL;
echo 'register_has_compulsory_notice=' . (str_contains($registerHtml, 'Compulsory document after registration') && str_contains($registerHtml, 'non-negotiable') ? 'yes' : 'no') . PHP_EOL;
echo 'register_broken=' . (str_contains($registerHtml, 'Symfony Exception') || str_contains($registerHtml, 'Internal Server Error') ? 'yes' : 'no') . PHP_EOL;

echo 'profile_status=' . $profileStatus . PHP_EOL;
echo 'profile_has_certificate_input=' . (str_contains($profileHtml, 'name="grower_certificate"') ? 'yes' : 'no') . PHP_EOL;
echo 'profile_has_required_copy=' . (str_contains($profileHtml, 'Compulsory NATCODEV Membership Document') && str_contains($profileHtml, 'non-negotiable') ? 'yes' : 'no') . PHP_EOL;
echo 'profile_broken=' . (str_contains($profileHtml, 'Symfony Exception') || str_contains($profileHtml, 'Internal Server Error') ? 'yes' : 'no') . PHP_EOL;
