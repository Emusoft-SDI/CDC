<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$address = 'Suite T11, 3rd Floor, Febson Mall, 24/25 Herbert Macaulay Way, Wuse Zone 4, Abuja, FCT';
$phoneDisplay = '+234 703 337 7202';
$phoneTel = '+2347033377202';

function replace_in_file($path, array $replacements)
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        return false;
    }

    foreach ($replacements as $from => $to) {
        $contents = str_replace($from, $to, $contents);
    }

    return file_put_contents($path, $contents) !== false;
}

$files = [
    $root . '\\resources\\views\\templates\\crystal_sky\\contact.blade.php',
    $root . '\\resources\\views\\templates\\crystal_sky\\partials\\footer.blade.php',
    $root . '\\resources\\views\\templates\\crystal_sky\\partials\\footer.blade copy.php',
    $root . '\\resources\\views\\templates\\crystal_sky\\partials\\header_top.blade.php',
    $root . '\\resources\\views\\templates\\indigo_fusion\\contact.blade.php',
    $root . '\\resources\\views\\templates\\indigo_fusion\\partials\\footer.blade.php',
];

$replacements = [
    'NATCODEV Coconut Farmers Cooperative, Local Demo Branch' => $address,
    'Local Demo Branch' => $address,
    '+234 000 000 0000' => $phoneDisplay,
    '+234 000 000 000' => $phoneDisplay,
    '+(234) 000 000 0000' => $phoneDisplay,
    '+(234) 0703 337 7202' => $phoneDisplay,
    '0703 337 7202' => $phoneDisplay,
    'info@natcodevcoop.local' => 'info@natcodev.com.ng',
];

foreach ($files as $file) {
    if (is_file($file)) {
        replace_in_file($file, $replacements);
    }
}

chdir($root);
require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Update global settings where the script can safely discover matching columns.
$general = function_exists('gs') ? gs() : null;
if ($general) {
    foreach (['address', 'contact_address', 'office_address'] as $column) {
        if (isset($general->{$column})) {
            $general->{$column} = $address;
        }
    }
    foreach (['phone', 'mobile', 'contact_number', 'phone_number'] as $column) {
        if (isset($general->{$column})) {
            $general->{$column} = $phoneDisplay;
        }
    }
    $general->save();
}

// Update frontend contact content and contact elements.
$frontends = App\Models\Frontend::where('data_keys', 'like', 'contact_us.%')->get();
foreach ($frontends as $frontend) {
    $values = (array) $frontend->data_values;

    foreach ($values as $key => $value) {
        $lower = strtolower($key);
        if (str_contains($lower, 'address')) {
            $values[$key] = $address;
        }
        if (str_contains($lower, 'number') || str_contains($lower, 'phone') || str_contains($lower, 'mobile')) {
            $values[$key] = $phoneDisplay;
        }
        if (is_string($value)) {
            $value = str_replace(array_keys($GLOBALS['replacements']), array_values($GLOBALS['replacements']), $value);
            $values[$key] = $value;
        }
    }

    if (($frontend->data_keys ?? '') === 'contact_us.element') {
        $type = strtolower((string)($values['address_type'] ?? ''));
        if (str_contains($type, 'address') || str_contains($type, 'office')) {
            $values['address'] = $address;
        }
        if (str_contains($type, 'phone') || str_contains($type, 'call') || str_contains($type, 'mobile')) {
            $values['address'] = $phoneDisplay;
        }
    }

    $frontend->data_values = $values;
    $frontend->save();
}

// Update branch records if present.
if (class_exists(App\Models\Branch::class)) {
    App\Models\Branch::query()->update([
        'address' => $address,
        'mobile' => $phoneDisplay,
    ]);
}

echo "UPDATED_CONTACT_DETAILS\n";
