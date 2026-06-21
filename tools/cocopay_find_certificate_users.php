<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\User::orderBy('id')->get() as $user) {
    $address = (array) $user->address;
    if (!empty($address['membership_certificate_path']) || !empty($address['membership_certificate'])) {
        echo $user->id . '|' . $user->username . '|status=' . ($address['membership_certificate_status'] ?? 'none') . '|path=' . ($address['membership_certificate_path'] ?? '') . PHP_EOL;
    }
}
