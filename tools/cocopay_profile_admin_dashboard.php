<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);
require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Constants\Status;
use App\Models\Deposit;
use App\Models\Dps;
use App\Models\Fdr;
use App\Models\Loan;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserLogin;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

function timed(string $name, callable $fn): void
{
    $start = microtime(true);
    try {
        $result = $fn();
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $summary = is_scalar($result) ? $result : (is_countable($result) ? count($result) : gettype($result));
        echo "{$name}|{$elapsed}ms|{$summary}" . PHP_EOL;
    } catch (Throwable $e) {
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        echo "{$name}|ERROR_AFTER_{$elapsed}ms|" . $e->getMessage() . PHP_EOL;
    }
}

timed('db_select_1', fn () => DB::select('select 1'));
timed('users_count', fn () => User::count());
timed('users_active', fn () => User::active()->count());
timed('certificate_uploaded', fn () => User::where('address', 'like', '%membership_certificate%')->count());
timed('loans_pending', fn () => Loan::pending()->count());
timed('loans_running', fn () => Loan::running()->count());
timed('dps_counts', fn () => [Dps::count(), Dps::running()->count(), Dps::matured()->count(), Dps::due()->count()]);
timed('fdr_counts', fn () => [Fdr::count(), Fdr::running()->count(), Fdr::closed()->count(), Fdr::due()->count()]);
timed('deposit_counts', fn () => [Deposit::pending()->count(), Deposit::rejected()->count()]);
timed('withdraw_counts', fn () => [Withdrawal::pending()->count(), Withdrawal::rejected()->count()]);
timed('support_attention', fn () => SupportTicket::whereIn('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count());
timed('transaction_money_in_30', fn () => Transaction::plus()->lastDays()->sum('amount'));
timed('transaction_money_out_30', fn () => Transaction::minus()->lastDays()->sum('amount'));
timed('user_logins_30', fn () => UserLogin::where('created_at', '>=', now()->subDay(30))->get(['browser', 'os', 'country']));
timed('plus_group_30', fn () => Transaction::plus()->sumAmount()->lastDays()->latest()->groupBy('date')->get());
timed('minus_group_30', fn () => Transaction::minus()->sumAmount()->lastDays()->latest()->groupBy('date')->get());
timed('deposit_month_365', fn () => Deposit::lastDays(365)->successful()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get());
timed('withdraw_month_365', fn () => Withdrawal::lastDays(365)->approved()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get());
timed('recent_members', fn () => User::latest()->take(5)->get(['id', 'firstname', 'lastname', 'username', 'email', 'mobile', 'address', 'balance', 'created_at']));
timed('recent_loans', fn () => Loan::with('user:id,username,firstname,lastname', 'plan:id,name')->latest()->take(5)->get());
timed('recent_tickets', fn () => SupportTicket::with('user:id,username,firstname,lastname')->latest()->take(5)->get());
timed('recent_transactions', fn () => Transaction::with('user:id,username,firstname,lastname')->latest()->take(6)->get());

echo 'DASHBOARD_PROFILE_DONE' . PHP_EOL;
