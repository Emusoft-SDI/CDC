<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$address = 'Suite T11, 3rd Floor, Febson Mall, 24/25 Herbert Macaulay Way, Wuse Zone 4, Abuja, FCT';
$phone = '+234 703 337 7202';
$email = 'info@natcodev.com.ng';

foreach (App\Models\Frontend::where('data_keys', 'like', 'contact_us.%')->get() as $row) {
    $values = (array) $row->data_values;

    if ($row->data_keys === 'contact_us.content') {
        if (array_key_exists('contact_address', $values)) {
            $values['contact_address'] = $address;
        }
        if (array_key_exists('contact_number', $values)) {
            $values['contact_number'] = $phone;
        }
        if (array_key_exists('email_address', $values)) {
            $values['email_address'] = $email;
        }
    }

    if ($row->data_keys === 'contact_us.element') {
        $type = strtolower((string)($values['address_type'] ?? ''));
        if (str_contains($type, 'mobile') || str_contains($type, 'phone') || str_contains($type, 'call')) {
            $values['address'] = $phone;
        } elseif (str_contains($type, 'email')) {
            $values['address'] = $email;
        } elseif (str_contains($type, 'office') || str_contains($type, 'address')) {
            $values['address'] = $address;
        }
    }

    $row->data_values = $values;
    $row->save();
}

if (class_exists(App\Models\Branch::class)) {
    App\Models\Branch::query()->update([
        'address' => $address,
        'mobile' => $phone,
        'email' => $email,
    ]);
}

echo "NORMALIZED_CONTACT_DB\n";
