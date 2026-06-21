<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$updated = 0;
foreach (App\Models\User::where('address', 'like', '%membership_certificate_status%')->get() as $user) {
    $address = (array) $user->address;
    if (($address['membership_certificate_status'] ?? null) === 'uploaded') {
        $address['membership_certificate_status'] = 'pending';
        $user->address = $address;
        $user->save();
        $updated++;
    }
}

echo "NORMALIZED_CERTIFICATE_STATUSES=$updated\n";
