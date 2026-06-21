<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$address = 'Suite T11, 3rd Floor, Febson Mall, 24/25 Herbert Macaulay Way, Wuse Zone 4, Abuja, FCT';
$phone = '+234 703 337 7202';
$routes = ['/', '/contact', '/services', '/about', '/faq'];

foreach ($routes as $uri) {
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);
    $response = $kernel->handle($request);
    $html = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    echo $uri . '|status=' . $response->getStatusCode();
    echo '|has_address=' . (str_contains($html, $address) ? 'yes' : 'no');
    echo '|has_phone=' . (str_contains($html, $phone) ? 'yes' : 'no');
    echo '|old_placeholder=' . (str_contains($html, 'Local Demo Branch') || str_contains($html, '+234 000') || str_contains($html, '000 000 0000') ? 'yes' : 'no');
    echo PHP_EOL;
    $kernel->terminate($request, $response);
}

$contactRecords = App\Models\Frontend::where('data_keys', 'like', 'contact_us.%')->get();
echo 'contact_records=' . $contactRecords->count() . PHP_EOL;
echo 'db_has_address=' . ($contactRecords->contains(fn($row) => str_contains(json_encode($row->data_values), $address)) ? 'yes' : 'no') . PHP_EOL;
echo 'db_has_phone=' . ($contactRecords->contains(fn($row) => str_contains(json_encode($row->data_values), $phone)) ? 'yes' : 'no') . PHP_EOL;
