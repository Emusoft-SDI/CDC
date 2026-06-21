<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Gateway/Monnify/ProcessController.php';
$contents = file_get_contents($file);

$contents = str_replace(
    <<<'PHP'
            $token = self::accessToken($credentials);
            $response = self::request('GET', $credentials->base_url . '/api/v2/merchant/transactions/query?paymentReference=' . urlencode($deposit->trx), [
PHP,
    <<<'PHP'
            $token = self::accessToken($credentials);
            $providerReference = $deposit->btc_wallet ?: $deposit->trx;
            $response = self::request('GET', $credentials->base_url . '/api/v2/transactions/' . urlencode($providerReference), [
PHP,
    $contents
);

file_put_contents($file, $contents);
echo "Monnify verification endpoint patched\n";
