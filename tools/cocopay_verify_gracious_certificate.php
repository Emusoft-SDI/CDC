<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('username', 'gracious')->firstOrFail();
$admin = App\Models\Admin::orderBy('id')->first();

function render_as($kernel, $uri, $guard, $actor)
{
    auth()->logout();
    auth('admin')->logout();
    if ($guard === 'user') {
        auth()->login($actor);
    } else {
        auth('admin')->login($actor);
    }
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => $uri]);
    $response = $kernel->handle($request);
    $html = $response instanceof Symfony\Component\HttpFoundation\BinaryFileResponse
        ? ''
        : (method_exists($response, 'getContent') ? (string) $response->getContent() : '');
    $kernel->terminate($request, $response);
    return [$response->getStatusCode(), $html];
}

[$profileStatus, $profileHtml] = render_as($kernel, '/user/profile-setting', 'user', $user);
[$adminStatus, $adminHtml] = render_as($kernel, '/admin/users/detail/' . $user->id, 'admin', $admin);
[$userCertificateStatus] = render_as($kernel, '/user/certificate-view', 'user', $user);
[$adminCertificateStatus] = render_as($kernel, '/admin/users/certificate-view/' . $user->id, 'admin', $admin);

$address = (array) $user->fresh()->address;

echo 'certificate_status=' . ($address['membership_certificate_status'] ?? 'missing') . PHP_EOL;
echo 'certificate_file_exists=' . (!empty($address['membership_certificate_path']) && is_file(base_path('../' . $address['membership_certificate_path'])) ? 'yes' : 'no') . PHP_EOL;
echo 'profile_status=' . $profileStatus . PHP_EOL;
echo 'profile_has_view_certificate=' . (str_contains($profileHtml, 'View Current Certificate') && str_contains($profileHtml, 'certificate-view') ? 'yes' : 'no') . PHP_EOL;
echo 'profile_has_pending=' . (str_contains($profileHtml, 'Pending') ? 'yes' : 'no') . PHP_EOL;
echo 'admin_status=' . $adminStatus . PHP_EOL;
echo 'admin_has_view_certificate=' . (str_contains($adminHtml, 'View Certificate') && str_contains($adminHtml, 'certificate-view') ? 'yes' : 'no') . PHP_EOL;
echo 'admin_has_approve_reject=' . (str_contains($adminHtml, 'Approve Certificate') && str_contains($adminHtml, 'certificate-reject') ? 'yes' : 'no') . PHP_EOL;
echo 'user_certificate_route_status=' . $userCertificateStatus . PHP_EOL;
echo 'admin_certificate_route_status=' . $adminCertificateStatus . PHP_EOL;
