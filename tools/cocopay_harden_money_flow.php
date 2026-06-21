<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$payment = $root . '\\app\\Http\\Controllers\\Gateway\\PaymentController.php';
$code = file_get_contents($payment);
$old = <<<'PHP'
    public static function userDataUpdate($deposit, $isManual = null)
    {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user = User::find($deposit->user_id);
            $user->balance += $deposit->amount;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $deposit->user_id;
            $transaction->amount       = $deposit->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge       = $deposit->charge;
            $transaction->trx_type     = '+';
            $transaction->details      = 'Deposit Via ' . $deposit->gatewayCurrency()->name;
            $transaction->trx          = $deposit->trx;
            $transaction->remark       = 'deposit';
            $transaction->save();

            if (!$isManual) {
                $adminNotification            = new AdminNotification();
                $adminNotification->user_id   = $user->id;
                $adminNotification->title     = 'Deposit successful via ' . $deposit->gatewayCurrency()->name;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            ReferralCommission::levelCommission($user, $deposit->amount, $deposit->trx);

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name'     => $deposit->gatewayCurrency()->name,
                'method_currency' => $deposit->method_currency, 
                'method_amount'   => showAmount($deposit->final_amount),
                'amount'          => showAmount($deposit->amount),
                'charge'          => showAmount($deposit->charge),
                'rate'            => showAmount($deposit->rate),
                'trx'             => $deposit->trx,
                'post_balance'    => showAmount($user->balance),
            ]);
        }
    }
PHP;

$new = <<<'PHP'
    public static function userDataUpdate($deposit, $isManual = null)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($deposit, $isManual) {
            $deposit = Deposit::where('id', $deposit->id)->lockForUpdate()->first();

            if (!$deposit || !in_array($deposit->status, [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING])) {
                return;
            }

            $gatewayName = $deposit->gatewayCurrency()->name;

            $user = User::where('id', $deposit->user_id)->lockForUpdate()->firstOrFail();

            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user->balance += $deposit->amount;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $deposit->user_id;
            $transaction->amount       = $deposit->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge       = $deposit->charge;
            $transaction->trx_type     = '+';
            $transaction->details      = 'Deposit Via ' . $gatewayName;
            $transaction->trx          = $deposit->trx;
            $transaction->remark       = 'deposit';
            $transaction->save();

            if (!$isManual) {
                $adminNotification            = new AdminNotification();
                $adminNotification->user_id   = $user->id;
                $adminNotification->title     = 'Deposit successful via ' . $gatewayName;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            ReferralCommission::levelCommission($user, $deposit->amount, $deposit->trx);

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name'     => $gatewayName,
                'method_currency' => $deposit->method_currency,
                'method_amount'   => showAmount($deposit->final_amount),
                'amount'          => showAmount($deposit->amount),
                'charge'          => showAmount($deposit->charge),
                'rate'            => showAmount($deposit->rate),
                'trx'             => $deposit->trx,
                'post_balance'    => showAmount($user->balance),
            ]);
        });
    }
PHP;

$code = str_replace($old, $new, $code);
file_put_contents($payment, $code);

$withdraw = $root . '\\app\\Http\\Controllers\\User\\WithdrawController.php';
$code = file_get_contents($withdraw);
$code = str_replace(
    "        if (\$request->amount > auth()->user()->balance) {\n            \$notify[] = ['error', 'Insufficient balance'];\n            return back()->withNotify(\$notify);\n        }",
    "        if (\$request->amount > auth()->user()->balance) {\n            throw ValidationException::withMessages(['amount' => 'Insufficient balance']);\n        }",
    $code
);
$old = <<<'PHP'
        if ($withdraw->amount > $user->balance) {
            $notify[] = ['error', 'Insufficient balance'];
            return back()->withNotify($notify);
        }

        $withdraw->status               = Status::PAYMENT_PENDING;
        $withdraw->withdraw_information = $userData;
        $withdraw->save();
        $user->balance -= $withdraw->amount;
        $user->save();

        $transaction               = new Transaction();
        $transaction->user_id      = $withdraw->user_id;
        $transaction->amount       = $withdraw->amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = $withdraw->charge;
        $transaction->trx_type     = '-';
        $transaction->details      = showAmount($withdraw->final_amount) . ' ' . $withdraw->currency . ' Withdraw Via ' . $withdraw->method->name;
        $transaction->trx          = $withdraw->trx;
        $transaction->remark       = 'withdraw';
        $transaction->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $user->id;
        $adminNotification->title     = 'New withdraw request from ' . $user->username;
        $adminNotification->click_url = urlPath('admin.withdraw.details', $withdraw->id);
        $adminNotification->save();

        notify($user, 'WITHDRAW_REQUEST', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount),
            'amount'          => showAmount($withdraw->amount),
            'charge'          => showAmount($withdraw->charge),
            'rate'            => showAmount($withdraw->rate),
            'trx'             => $withdraw->trx,
            'post_balance'    => showAmount($user->balance),
        ]);
PHP;
$new = <<<'PHP'
        \Illuminate\Support\Facades\DB::transaction(function () use ($withdraw, $userData) {
            $withdraw = Withdrawal::where('id', $withdraw->id)->where('status', Status::PAYMENT_INITIATE)->lockForUpdate()->firstOrFail();
            $user = auth()->user()->newQuery()->where('id', auth()->id())->lockForUpdate()->firstOrFail();

            if ($withdraw->amount > $user->balance) {
                throw ValidationException::withMessages(['amount' => 'Insufficient balance']);
            }

            $withdraw->status               = Status::PAYMENT_PENDING;
            $withdraw->withdraw_information = $userData;
            $withdraw->save();

            $user->balance -= $withdraw->amount;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $withdraw->user_id;
            $transaction->amount       = $withdraw->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge       = $withdraw->charge;
            $transaction->trx_type     = '-';
            $transaction->details      = showAmount($withdraw->final_amount) . ' ' . $withdraw->currency . ' Withdraw Via ' . $withdraw->method->name;
            $transaction->trx          = $withdraw->trx;
            $transaction->remark       = 'withdraw';
            $transaction->save();

            $adminNotification            = new AdminNotification();
            $adminNotification->user_id   = $user->id;
            $adminNotification->title     = 'New withdraw request from ' . $user->username;
            $adminNotification->click_url = urlPath('admin.withdraw.details', $withdraw->id);
            $adminNotification->save();

            notify($user, 'WITHDRAW_REQUEST', [
                'method_name'     => $withdraw->method->name,
                'method_currency' => $withdraw->currency,
                'method_amount'   => showAmount($withdraw->final_amount),
                'amount'          => showAmount($withdraw->amount),
                'charge'          => showAmount($withdraw->charge),
                'rate'            => showAmount($withdraw->rate),
                'trx'             => $withdraw->trx,
                'post_balance'    => showAmount($user->balance),
            ]);
        });
PHP;
$code = str_replace($old, $new, $code);
file_put_contents($withdraw, $code);

$adminWithdraw = $root . '\\app\\Http\\Controllers\\Admin\\WithdrawalController.php';
$code = file_get_contents($adminWithdraw);
$old = <<<'PHP'
        $withdraw                 = Withdrawal::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->with('user')->firstOrFail();
        $withdraw->status         = Status::PAYMENT_SUCCESS;
        $withdraw->admin_feedback = $request->details;
        $withdraw->save();

        notify($withdraw->user, 'WITHDRAW_APPROVE', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount),
            'amount'          => showAmount($withdraw->amount),
            'charge'          => showAmount($withdraw->charge),
            'rate'            => showAmount($withdraw->rate),
            'trx'             => $withdraw->trx,
            'admin_details'   => $request->details,
        ]);
PHP;
$new = <<<'PHP'
        $withdraw = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $withdraw = Withdrawal::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->with('user', 'method')->lockForUpdate()->firstOrFail();
            $withdraw->status         = Status::PAYMENT_SUCCESS;
            $withdraw->admin_feedback = $request->details;
            $withdraw->save();

            return $withdraw;
        });

        notify($withdraw->user, 'WITHDRAW_APPROVE', [
            'method_name'     => $withdraw->method->name,
            'method_currency' => $withdraw->currency,
            'method_amount'   => showAmount($withdraw->final_amount),
            'amount'          => showAmount($withdraw->amount),
            'charge'          => showAmount($withdraw->charge),
            'rate'            => showAmount($withdraw->rate),
            'trx'             => $withdraw->trx,
            'admin_details'   => $request->details,
        ]);
PHP;
$code = str_replace($old, $new, $code);

$old = <<<'PHP'
        $withdraw = Withdrawal::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->with('user')->firstOrFail();

        $withdraw->status         = Status::PAYMENT_REJECT;
        $withdraw->admin_feedback = $request->details;
        $withdraw->save();

        $user = $withdraw->user;
        $user->balance += $withdraw->amount;
        $user->save();

        $transaction               = new Transaction();
        $transaction->user_id      = $withdraw->user_id;
        $transaction->amount       = $withdraw->amount;
        $transaction->post_balance = $user->balance;
        $transaction->charge       = 0;
        $transaction->trx_type     = '+';
        $transaction->remark       = 'withdraw_reject';
        $transaction->details      = showAmount($withdraw->amount) . ' ' . gs('cur_text') . ' Refunded from withdrawal rejection';
        $transaction->trx          = $withdraw->trx;
        $transaction->save();
PHP;
$new = <<<'PHP'
        [$withdraw, $user] = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $withdraw = Withdrawal::where('id', $request->id)->where('status', Status::PAYMENT_PENDING)->with('user', 'method')->lockForUpdate()->firstOrFail();
            $user = $withdraw->user()->lockForUpdate()->firstOrFail();

            $withdraw->status         = Status::PAYMENT_REJECT;
            $withdraw->admin_feedback = $request->details;
            $withdraw->save();

            $user->balance += $withdraw->amount;
            $user->save();

            $transaction               = new Transaction();
            $transaction->user_id      = $withdraw->user_id;
            $transaction->amount       = $withdraw->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->remark       = 'withdraw_reject';
            $transaction->details      = showAmount($withdraw->amount) . ' ' . gs('cur_text') . ' Refunded from withdrawal rejection';
            $transaction->trx          = $withdraw->trx;
            $transaction->save();

            return [$withdraw, $user];
        });
PHP;
$code = str_replace($old, $new, $code);
file_put_contents($adminWithdraw, $code);

$ipn = $root . '\\routes\\ipn.php';
file_put_contents($ipn, <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::post('paystack', 'Paystack\ProcessController@ipn')->name('Paystack');
Route::any('monnify', 'Monnify\ProcessController@ipn')->name('Monnify');
PHP);

echo "MONEY_FLOW_HARDENED\n";
