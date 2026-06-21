<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Gateway/Monnify/ProcessController.php';
$contents = file_get_contents($file);

$contents = str_replace(
    <<<'PHP'
        return (object) [
            'api_key'       => trim($params->api_key ?? ''),
            'secret_key'    => trim($params->secret_key ?? ''),
            'contract_code' => trim($params->contract_code ?? ''),
            'base_url'      => rtrim(trim($params->base_url ?? 'https://sandbox.monnify.com'), '/'),
        ];
PHP,
    <<<'PHP'
        return (object) [
            'api_key'         => trim($params->api_key ?? ''),
            'secret_key'      => trim($params->secret_key ?? ''),
            'contract_code'   => trim($params->contract_code ?? ''),
            'base_url'        => rtrim(trim($params->base_url ?? 'https://sandbox.monnify.com'), '/'),
            'payment_methods' => array_values(array_filter(array_map('trim', explode(',', $params->payment_methods ?? 'CARD,ACCOUNT_TRANSFER,USSD')))),
            'ssl_verify'      => !in_array(strtolower(trim($params->ssl_verify ?? 'true')), ['0', 'false', 'no', 'off'], true),
        ];
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
PHP,
    <<<'PHP'
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, self::$sslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, self::$sslVerify ? 2 : 0);
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
class ProcessController extends Controller {
    protected static function credentials($deposit) {
PHP,
    <<<'PHP'
class ProcessController extends Controller {
    protected static bool $sslVerify = true;

    protected static function credentials($deposit) {
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
            $credentials = self::credentials($deposit);
PHP,
    <<<'PHP'
            $credentials = self::credentials($deposit);
            self::$sslVerify = $credentials->ssl_verify;
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
                'redirectUrl'        => route('ipn.' . $deposit->gateway->alias),
            ];
PHP,
    <<<'PHP'
                'redirectUrl'        => route('ipn.' . $deposit->gateway->alias),
                'paymentMethods'     => $credentials->payment_methods,
            ];
PHP,
    $contents
);

$contents = str_replace(
    <<<'PHP'
            $credentials = self::credentials($deposit);
            $token = self::accessToken($credentials);
            $response = self::request('GET', $credentials->base_url . '/api/v2/merchant/transactions/query?paymentReference=' . urlencode($deposit->trx), [
PHP,
    <<<'PHP'
            $credentials = self::credentials($deposit);
            self::$sslVerify = $credentials->ssl_verify;
            $token = self::accessToken($credentials);
            $providerReference = $deposit->btc_wallet ?: $deposit->trx;
            $response = self::request('GET', $credentials->base_url . '/api/v2/transactions/' . urlencode($providerReference), [
PHP,
    $contents
);

file_put_contents($file, $contents);
echo "Monnify controller aligned with CDC settings\n";
