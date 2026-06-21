<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::orderBy('id')->firstOrFail();
auth('admin')->login($admin);

function get_admin_page($kernel, $uri)
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

[$queueStatus, $queueHtml] = get_admin_page($kernel, '/admin/users/certificates');
[$approvedStatus, $approvedHtml] = get_admin_page($kernel, '/admin/users/certificates?status=approved');
[$allStatus, $allHtml] = get_admin_page($kernel, '/admin/users/certificates?status=all');
[$dashboardStatus, $dashboardHtml] = get_admin_page($kernel, '/admin/dashboard');

echo 'queue_status=' . $queueStatus . PHP_EOL;
echo 'queue_has_title=' . (str_contains($queueHtml, 'NATCODEV Membership Certificate Queue') ? 'yes' : 'no') . PHP_EOL;
echo 'queue_has_actions=' . (str_contains($queueHtml, 'Approve') && str_contains($queueHtml, 'View') ? 'yes' : 'no') . PHP_EOL;
echo 'pending_has_gracious=' . (str_contains($queueHtml, 'gracious') ? 'yes' : 'no') . PHP_EOL;
echo 'approved_status=' . $approvedStatus . PHP_EOL;
echo 'approved_has_gracious=' . (str_contains($approvedHtml, 'gracious') ? 'yes' : 'no') . PHP_EOL;
echo 'all_status=' . $allStatus . PHP_EOL;
echo 'all_has_gracious=' . (str_contains($allHtml, 'gracious') ? 'yes' : 'no') . PHP_EOL;
echo 'dashboard_status=' . $dashboardStatus . PHP_EOL;
echo 'sidebar_has_certificate_menu=' . (str_contains($dashboardHtml, 'Certificate Validation') && str_contains($dashboardHtml, '/admin/users/certificates') ? 'yes' : 'no') . PHP_EOL;
