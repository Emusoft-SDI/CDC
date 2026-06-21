<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::orderBy('id')->first();
$admin = App\Models\Admin::orderBy('id')->first();

function request_page($kernel, $uri, $guard = null, $actor = null)
{
    auth()->logout();
    auth('admin')->logout();

    if ($guard === 'user' && $actor) {
        auth()->login($actor);
    }
    if ($guard === 'admin' && $actor) {
        auth('admin')->login($actor);
    }

    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);

    $response = $kernel->handle($request);
    $html = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $html];
}

[$profileStatus, $profileHtml] = request_page($kernel, '/user/profile-setting', 'user', $user);
[$adminStatus, $adminHtml] = request_page($kernel, '/admin/users/detail/' . ($user->id ?? 1), 'admin', $admin);

echo 'user_profile_status=' . $profileStatus . PHP_EOL;
echo 'user_profile_has_view_route=' . (str_contains($profileHtml, 'certificate-view') ? 'yes' : 'no') . PHP_EOL;
echo 'user_profile_has_status=' . (str_contains($profileHtml, 'Certificate required') || str_contains($profileHtml, 'Pending') || str_contains($profileHtml, 'Approved') || str_contains($profileHtml, 'Rejected') ? 'yes' : 'no') . PHP_EOL;
echo 'user_profile_broken=' . (str_contains($profileHtml, 'Symfony Exception') || str_contains($profileHtml, 'Internal Server Error') ? 'yes' : 'no') . PHP_EOL;

echo 'admin_detail_status=' . $adminStatus . PHP_EOL;
echo 'admin_detail_has_review_card=' . (str_contains($adminHtml, 'NATCODEV Membership Certificate Review') ? 'yes' : 'no') . PHP_EOL;
echo 'admin_detail_has_actions=' . (str_contains($adminHtml, 'certificate-approve') || str_contains($adminHtml, 'certificate-reject') || str_contains($adminHtml, 'No NATCODEV membership certificate') ? 'yes' : 'no') . PHP_EOL;
echo 'admin_detail_broken=' . (str_contains($adminHtml, 'Symfony Exception') || str_contains($adminHtml, 'Internal Server Error') ? 'yes' : 'no') . PHP_EOL;

foreach ([
    'user.profile.certificate.view',
    'admin.users.certificate.view',
    'admin.users.certificate.approve',
    'admin.users.certificate.reject',
] as $routeName) {
    echo 'route_' . $routeName . '=' . (Route::has($routeName) ? 'yes' : 'no') . PHP_EOL;
}
