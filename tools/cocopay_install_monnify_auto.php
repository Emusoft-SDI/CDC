<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';

$controllerDir = $root . '/app/Http/Controllers/Gateway/Monnify';
if (!is_dir($controllerDir)) {
    mkdir($controllerDir, 0777, true);
}

$controller = <<<'PHP'
<?php

namespace App\Http\Controllers\Gateway\Monnify;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController;
use App\Models\Deposit;
use Illuminate\Http\Request;

class ProcessController extends Controller {
    protected static function credentials($deposit) {
        $params = json_decode($deposit->gatewayCurrency()->gateway_parameter);

        return (object) [
            'api_key'       => trim($params->api_key ?? ''),
            'secret_key'    => trim($params->secret_key ?? ''),
            'contract_code' => trim($params->contract_code ?? ''),
            'base_url'      => rtrim(trim($params->base_url ?? 'https://sandbox.monnify.com'), '/'),
        ];
    }

    protected static function request($method, $url, $headers = [], $payload = null) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception($error);
        }

        return json_decode($response);
    }

    protected static function accessToken($credentials) {
        if (!$credentials->api_key || !$credentials->secret_key) {
            throw new \Exception('Monnify API key and secret key are required.');
        }

        $auth = base64_encode($credentials->api_key . ':' . $credentials->secret_key);
        $response = self::request('POST', $credentials->base_url . '/api/v1/auth/login', [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
        ]);

        $token = $response->responseBody->accessToken ?? null;
        if (!$token) {
            throw new \Exception($response->responseMessage ?? 'Unable to authenticate Monnify credentials.');
        }

        return $token;
    }

    public static function process($deposit) {
        try {
            $credentials = self::credentials($deposit);

            if (!$credentials->contract_code) {
                throw new \Exception('Monnify contract code is required.');
            }

            $token = self::accessToken($credentials);
            $user = auth()->user();
            $payload = [
                'amount'             => round($deposit->final_amount, 2),
                'customerName'       => trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: $user->username,
                'customerEmail'      => $user->email,
                'paymentReference'   => $deposit->trx,
                'paymentDescription' => 'NATCODEV Cooperative deposit ' . $deposit->trx,
                'currencyCode'       => $deposit->method_currency,
                'contractCode'       => $credentials->contract_code,
                'redirectUrl'        => route('ipn.' . $deposit->gateway->alias),
            ];

            $response = self::request('POST', $credentials->base_url . '/api/v1/merchant/transactions/init-transaction', [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ], $payload);

            $checkoutUrl = $response->responseBody->checkoutUrl ?? null;
            if (!$checkoutUrl) {
                throw new \Exception($response->responseMessage ?? 'Unable to initialize Monnify checkout.');
            }

            $deposit->btc_wallet = $response->responseBody->transactionReference ?? $deposit->trx;
            $deposit->save();

            return json_encode([
                'redirect'     => true,
                'redirect_url' => $checkoutUrl,
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function ipn(Request $request) {
        $track = $request->paymentReference ?? $request->payment_reference ?? $request->reference ?? session('Track');
        $deposit = Deposit::where('trx', $track)->orderBy('id', 'DESC')->first();

        if (!$deposit) {
            $notify[] = ['error', 'Invalid Monnify payment reference.'];
            return to_route(gatewayRedirectUrl())->withNotify($notify);
        }

        try {
            $credentials = self::credentials($deposit);
            $token = self::accessToken($credentials);
            $response = self::request('GET', $credentials->base_url . '/api/v2/merchant/transactions/query?paymentReference=' . urlencode($deposit->trx), [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ]);

            $body = $response->responseBody ?? null;
            $deposit->detail = $body ?: $response;
            $deposit->save();

            $amountPaid = (float) ($body->amountPaid ?? $body->amount ?? 0);
            $currency = $body->currencyCode ?? $deposit->method_currency;
            $status = strtoupper($body->paymentStatus ?? '');

            if ($status === 'PAID' && round($amountPaid, 2) >= round($deposit->final_amount, 2) && $currency === $deposit->method_currency && $deposit->status == Status::PAYMENT_INITIATE) {
                PaymentController::userDataUpdate($deposit);
                $notify[] = ['success', 'Monnify payment captured successfully.'];
                return to_route(gatewayRedirectUrl(true))->withNotify($notify);
            }

            $notify[] = ['error', 'Monnify payment is not completed yet.'];
        } catch (\Exception $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return to_route(gatewayRedirectUrl())->withNotify($notify);
    }
}
PHP;

file_put_contents($controllerDir . '/ProcessController.php', $controller);

$ipn = $root . '/routes/ipn.php';
$ipnContents = file_get_contents($ipn);
if (!str_contains($ipnContents, "name('Monnify')")) {
    $ipnContents .= "\nRoute::any('monnify', 'Monnify\\ProcessController@ipn')->name('Monnify');\n";
    file_put_contents($ipn, $ipnContents);
}

$autoController = $root . '/app/Http/Controllers/Admin/AutomaticGatewayController.php';
$contents = file_get_contents($autoController);
$contents = str_replace("whereIn('alias', ['Paystack'])", "whereIn('alias', ['Paystack', 'Monnify'])", $contents);
file_put_contents($autoController, $contents);

echo "Monnify automatic files installed\n";
