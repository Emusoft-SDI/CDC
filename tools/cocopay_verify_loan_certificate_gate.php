<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('username', 'gracious')->firstOrFail();
$originalAddress = (array) $user->address;

function set_certificate_status($user, $status)
{
    $address = (array) $user->fresh()->address;
    $address['membership_certificate_status'] = $status;
    Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
        'address' => json_encode($address),
    ]);
}

function get_as_user($kernel, $user, $uri)
{
    auth()->logout();
    auth()->login($user->fresh());
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);
    $response = $kernel->handle($request);
    $location = $response->headers->get('Location');
    $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $location, $content];
}

try {
    set_certificate_status($user, 'pending');
    [$pendingStatus, $pendingLocation] = get_as_user($kernel, $user, '/user/loan/plans');

    set_certificate_status($user, 'approved');
    [$approvedStatus, $approvedLocation, $approvedContent] = get_as_user($kernel, $user, '/user/loan/plans');

    echo 'pending_loan_status=' . $pendingStatus . PHP_EOL;
    echo 'pending_redirects_to_profile=' . (str_contains((string) $pendingLocation, '/user/profile-setting') ? 'yes' : 'no') . PHP_EOL;
    echo 'approved_loan_status=' . $approvedStatus . PHP_EOL;
    echo 'approved_shows_loan_plans=' . (str_contains($approvedContent, 'Loan Plans') ? 'yes' : 'no') . PHP_EOL;
} finally {
    $restoreAddress = $originalAddress;
    $restoreAddress['membership_certificate_status'] = 'pending';
    unset($restoreAddress['membership_certificate_rejection_reason'], $restoreAddress['membership_certificate_reviewed_at'], $restoreAddress['membership_certificate_reviewed_by']);
    Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
        'address' => json_encode($restoreAddress),
    ]);
}

echo 'restored_status=' . (((array) $user->fresh()->address)['membership_certificate_status'] ?? 'missing') . PHP_EOL;
