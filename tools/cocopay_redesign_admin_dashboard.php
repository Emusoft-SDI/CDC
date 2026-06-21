<?php

$core = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$controllerFile = $core . '/app/Http/Controllers/Admin/AdminController.php';
$dashboardFile = $core . '/resources/views/admin/dashboard.blade.php';

$controller = file_get_contents($controllerFile);

if (strpos($controller, 'use App\Models\SupportTicket;') === false) {
    $controller = str_replace("use App\Models\SupportMessage;\n", "use App\Models\SupportMessage;\nuse App\Models\SupportTicket;\n", str_replace("use App\Models\Subscriber;\n", "use App\Models\Subscriber;\nuse App\Models\SupportMessage;\n", $controller));
}

if (strpos($controller, 'use App\Models\SupportMessage;') === false) {
    $controller = str_replace("use App\Models\SupportTicket;\n", "use App\Models\SupportMessage;\nuse App\Models\SupportTicket;\n", $controller);
}

$oldWidget = <<<'PHP'
    private function widgetData() {
        $widget['total_users']             = User::count();
        $widget['verified_users']          = User::active()->count();
        $widget['email_unverified_users']  = User::emailUnverified()->count();
        $widget['mobile_unverified_users'] = User::mobileUnverified()->count();

        $widget['total_pending_loan'] = Loan::pending()->count();
        $widget['total_due_loan']     = Loan::due()->count();
        $widget['total_running_loan'] = Loan::running()->count();
        $widget['total_paid_loan']    = Loan::paid()->count();

        $widget['total_dps']         = Dps::count();
        $widget['total_running_dps'] = Dps::running()->count();
        $widget['total_matured_dps'] = Dps::matured()->count();
        $widget['total_due_dps']     = Dps::due()->count();

        $widget['total_fdr']         = Fdr::count();
        $widget['total_running_fdr'] = Fdr::running()->count();
        $widget['total_closed_fdr']  = Fdr::closed()->count();
        $widget['total_due_fdr']     = Fdr::due()->count();

        $widget['total_deposit_pending']  = Deposit::pending()->count();
        $widget['total_deposit_rejected'] = Deposit::rejected()->count();

        $widget['total_withdraw_pending']  = Withdrawal::pending()->count();
        $widget['total_withdraw_rejected'] = Withdrawal::rejected()->count();

        return $widget;
    }
PHP;

$newWidget = <<<'PHP'
    private function widgetData() {
        $totalUsers = User::count();
        $certificateUploaded = User::where('address', 'like', '%membership_certificate%')->count();

        $widget['total_users']             = $totalUsers;
        $widget['verified_users']          = User::active()->count();
        $widget['email_unverified_users']  = User::emailUnverified()->count();
        $widget['mobile_unverified_users'] = User::mobileUnverified()->count();
        $widget['certificate_uploaded']    = $certificateUploaded;
        $widget['certificate_missing']     = max($totalUsers - $certificateUploaded, 0);
        $widget['kyc_pending_users']       = User::kycPending()->count();
        $widget['total_wallet_balance']    = User::sum('balance');

        $widget['total_pending_loan'] = Loan::pending()->count();
        $widget['total_due_loan']     = Loan::due()->count();
        $widget['total_running_loan'] = Loan::running()->count();
        $widget['total_paid_loan']    = Loan::paid()->count();

        $widget['total_dps']         = Dps::count();
        $widget['total_running_dps'] = Dps::running()->count();
        $widget['total_matured_dps'] = Dps::matured()->count();
        $widget['total_due_dps']     = Dps::due()->count();

        $widget['total_fdr']         = Fdr::count();
        $widget['total_running_fdr'] = Fdr::running()->count();
        $widget['total_closed_fdr']  = Fdr::closed()->count();
        $widget['total_due_fdr']     = Fdr::due()->count();

        $widget['total_deposit_pending']  = Deposit::pending()->count();
        $widget['total_deposit_rejected'] = Deposit::rejected()->count();

        $widget['total_withdraw_pending']  = Withdrawal::pending()->count();
        $widget['total_withdraw_rejected'] = Withdrawal::rejected()->count();

        $widget['support_attention'] = SupportTicket::whereIn('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count();
        $widget['money_in_30']       = Transaction::plus()->lastDays()->sum('amount');
        $widget['money_out_30']      = Transaction::minus()->lastDays()->sum('amount');

        return $widget;
    }
PHP;

if (strpos($controller, $oldWidget) === false) {
    throw new RuntimeException('Could not find widgetData block.');
}
$controller = str_replace($oldWidget, $newWidget, $controller);

$oldDashboard = <<<'PHP'
    public function dashboard() {
        $pageTitle = 'Dashboard';
        $widget    = $this->widgetData();
        $chartData = $this->piChartData();
        $plusTrx   = Transaction::plus()->sumAmount()->lastDays()->latest()->groupBy('date')->get();
        $minusTrx  = Transaction::minus()->sumAmount()->lastDays()->latest()->groupBy('date')->get();

        $trxReport['date'] = collect([]);

        $plusTrx->map(function ($trxData) use ($trxReport) {
            $trxReport['date']->push($trxData->date);
        });

        $minusTrx->map(function ($trxData) use ($trxReport) {
            $trxReport['date']->push($trxData->date);
        });

        $trxReport['date'] = dateSorting($trxReport['date']->unique()->toArray());

        $depositsMonth = Deposit::lastDays(365)->successful()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get();

        $withdrawalMonth = Withdrawal::lastDays(365)->approved()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get();

        // Monthly Deposit & Withdraw Report Graph
        $report['months']                = collect([]);
        $report['deposit_month_amount']  = collect([]);
        $report['withdraw_month_amount'] = collect([]);

        $depositsMonth->map(function ($depositData) use ($report) {
            $report['months']->push($depositData->months);
            $report['deposit_month_amount']->push(getAmount($depositData->depositAmount));
        });

        $withdrawalMonth->map(function ($withdrawData) use ($report) {
            if (!in_array($withdrawData->months, $report['months']->toArray())) {
                $report['months']->push($withdrawData->months);
            }
            $report['withdraw_month_amount']->push(getAmount($withdrawData->withdrawAmount));
        });

        $months = $this->makeMonthArray($report['months']);

        foreach ($months as $month) {
            $chartData['deposits'][]    = getAmount(@$depositsMonth->where('months', $month)->first()->depositAmount);
            $chartData['withdrawals'][] = getAmount(@$withdrawalMonth->where('months', $month)->first()->withdrawAmount);
        }

        foreach ($trxReport['date'] as $trxDate) {
            $chartData['plus_trx'][]  = $plusTrx->where('date', $trxDate)->first()->amount ?? 0;
            $chartData['minus_trx'][] = $minusTrx->where('date', $trxDate)->first()->amount ?? 0;
        }

        $chartData['trx_dates'] = $trxReport['date'];
        return view('admin.dashboard', compact('pageTitle', 'widget', 'chartData', 'months'));
    }
PHP;

$newDashboard = <<<'PHP'
    public function dashboard() {
        $pageTitle = 'NATCODEV Operations Dashboard';
        $widget    = $this->widgetData();
        $chartData = $this->piChartData();
        $plusTrx   = Transaction::plus()->sumAmount()->lastDays()->latest()->groupBy('date')->get();
        $minusTrx  = Transaction::minus()->sumAmount()->lastDays()->latest()->groupBy('date')->get();

        $trxReport['date'] = collect([]);

        $plusTrx->map(function ($trxData) use ($trxReport) {
            $trxReport['date']->push($trxData->date);
        });

        $minusTrx->map(function ($trxData) use ($trxReport) {
            $trxReport['date']->push($trxData->date);
        });

        $trxReport['date'] = dateSorting($trxReport['date']->unique()->toArray());

        $depositsMonth = Deposit::lastDays(365)->successful()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get();

        $withdrawalMonth = Withdrawal::lastDays(365)->approved()->sumAmount()->selectRaw("DATE_FORMAT(created_at,'%M-%Y') as months")->latest()->groupBy('months')->get();

        $report['months']                = collect([]);
        $report['deposit_month_amount']  = collect([]);
        $report['withdraw_month_amount'] = collect([]);

        $depositsMonth->map(function ($depositData) use ($report) {
            $report['months']->push($depositData->months);
            $report['deposit_month_amount']->push(getAmount($depositData->depositAmount));
        });

        $withdrawalMonth->map(function ($withdrawData) use ($report) {
            if (!in_array($withdrawData->months, $report['months']->toArray())) {
                $report['months']->push($withdrawData->months);
            }
            $report['withdraw_month_amount']->push(getAmount($withdrawData->withdrawAmount));
        });

        $months = $this->makeMonthArray($report['months']);

        foreach ($months as $month) {
            $chartData['deposits'][]    = getAmount(@$depositsMonth->where('months', $month)->first()->depositAmount);
            $chartData['withdrawals'][] = getAmount(@$withdrawalMonth->where('months', $month)->first()->withdrawAmount);
        }

        foreach ($trxReport['date'] as $trxDate) {
            $chartData['money_in'][]  = $plusTrx->where('date', $trxDate)->first()->amount ?? 0;
            $chartData['money_out'][] = $minusTrx->where('date', $trxDate)->first()->amount ?? 0;
        }

        $chartData['trx_dates'] = $trxReport['date'];

        $recentMembers = User::latest()->take(5)->get(['id', 'firstname', 'lastname', 'username', 'email', 'mobile', 'address', 'balance', 'created_at']);
        $recentLoans = Loan::with('user:id,username,firstname,lastname', 'plan:id,name')->latest()->take(5)->get();
        $recentTickets = SupportTicket::with('user:id,username,firstname,lastname')->latest()->take(5)->get();
        $recentTransactions = Transaction::with('user:id,username,firstname,lastname')->latest()->take(6)->get();

        return view('admin.dashboard', compact('pageTitle', 'widget', 'chartData', 'months', 'recentMembers', 'recentLoans', 'recentTickets', 'recentTransactions'));
    }
PHP;

if (strpos($controller, $oldDashboard) === false) {
    throw new RuntimeException('Could not find dashboard method block.');
}
$controller = str_replace($oldDashboard, $newDashboard, $controller);

$oldReport = <<<'PHP'
    public function requestReport() {
        $pageTitle            = 'Your Listed Report & Request';
        $response             = CurlRequest::curlContent($url);
        $response             = json_decode($response);
        if ($response->status == 'error') {
            return to_route('admin.dashboard')->withErrors($response->message);
        }
        $reports = $response->message[0];
        return view('admin.reports', compact('reports', 'pageTitle'));
    }
PHP;

$newReport = <<<'PHP'
    public function requestReport() {
        $notify[] = ['info', 'Vendor report requests are disabled on this local NATCODEV install. Use the local support ticket queue instead.'];
        return to_route('admin.ticket.index')->withNotify($notify);
    }

    public function reportSubmit(Request $request) {
        $request->validate([
            'type'    => 'required|string|max:40',
            'message' => 'required|string|max:2000',
        ]);

        $admin = auth('admin')->user();

        $ticket             = new SupportTicket();
        $ticket->ticket     = getNumber();
        $ticket->name       = $admin->name ?? 'NATCODEV Admin';
        $ticket->email      = $admin->email ?? gs('email_from');
        $ticket->subject    = 'Admin ' . ucfirst($request->type);
        $ticket->last_reply = now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->priority   = Status::PRIORITY_MEDIUM;
        $ticket->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->admin_id          = $admin->id ?? 0;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Local support ticket created successfully.'];
        return to_route('admin.ticket.view', $ticket->id)->withNotify($notify);
    }
PHP;

if (strpos($controller, $oldReport) === false) {
    throw new RuntimeException('Could not find requestReport method block.');
}
$controller = str_replace($oldReport, $newReport, $controller);

file_put_contents($controllerFile, $controller);

$dashboard = <<<'BLADE'
@extends('admin.layouts.app')
@section('panel')
    @php
        $certificateRate = $widget['total_users'] ? round(($widget['certificate_uploaded'] / $widget['total_users']) * 100) : 0;
        $attentionTotal = $widget['total_pending_loan'] + $widget['total_deposit_pending'] + $widget['total_withdraw_pending'] + $widget['support_attention'] + $widget['certificate_missing'];
    @endphp

    <style>
        .natadmin-hero,
        .natadmin-card,
        .natadmin-panel,
        .natadmin-action {
            border: 1px solid #e2ebe5;
            border-radius: 12px;
            box-shadow: 0 18px 42px rgba(8, 44, 32, .08);
        }
        .natadmin-hero {
            align-items: center;
            background:
                radial-gradient(circle at 88% 12%, rgba(201,154,46,.32), transparent 28%),
                linear-gradient(135deg, #082c20 0%, #087a45 62%, #102f24 100%);
            color: #fff;
            display: grid;
            gap: 20px;
            grid-template-columns: minmax(0, 1fr) 280px;
            margin-bottom: 22px;
            overflow: hidden;
            padding: 26px;
        }
        .natadmin-hero span,
        .natadmin-kicker {
            color: rgba(255,255,255,.72);
            display: block;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .natadmin-hero h2 {
            color: #fff;
            font-size: 32px;
            font-weight: 900;
            margin: 6px 0 8px;
        }
        .natadmin-hero p {
            color: rgba(255,255,255,.82);
            margin: 0;
        }
        .natadmin-hero-metric {
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 10px;
            padding: 18px;
        }
        .natadmin-hero-metric strong {
            color: #fff4cf;
            display: block;
            font-size: 34px;
            line-height: 1.1;
            margin-top: 6px;
        }
        .natadmin-actions {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            margin-bottom: 22px;
        }
        .natadmin-action {
            align-items: center;
            background: #fff;
            color: #163829;
            display: flex;
            font-weight: 900;
            gap: 10px;
            min-height: 62px;
            padding: 14px;
        }
        .natadmin-action:hover {
            border-color: rgba(201,154,46,.38);
            color: #087a45;
        }
        .natadmin-action i {
            align-items: center;
            background: #e9f7ef;
            border-radius: 8px;
            color: #087a45;
            display: inline-flex;
            font-size: 20px;
            height: 36px;
            justify-content: center;
            width: 36px;
        }
        .natadmin-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 22px;
        }
        .natadmin-card {
            background: #fff;
            min-height: 146px;
            padding: 18px;
        }
        .natadmin-card i {
            color: #c99a2e;
            font-size: 24px;
        }
        .natadmin-card span {
            color: #62766b;
            display: block;
            font-size: 12px;
            font-weight: 900;
            margin-top: 12px;
            text-transform: uppercase;
        }
        .natadmin-card strong {
            color: #102f24;
            display: block;
            font-size: 28px;
            font-weight: 900;
            line-height: 1.15;
            margin-top: 6px;
        }
        .natadmin-card small {
            color: #6a7b70;
            display: block;
            font-weight: 700;
            margin-top: 8px;
        }
        .natadmin-layout {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.45fr) minmax(320px, .75fr);
        }
        .natadmin-panel {
            background: #fff;
            margin-bottom: 18px;
            overflow: hidden;
        }
        .natadmin-panel-head {
            align-items: center;
            border-bottom: 1px solid #e8efe9;
            display: flex;
            justify-content: space-between;
            padding: 16px 18px;
        }
        .natadmin-panel-head h5 {
            color: #102f24;
            font-weight: 900;
            margin: 0;
        }
        .natadmin-panel-head a {
            color: #087a45;
            font-weight: 900;
        }
        .natadmin-list {
            display: grid;
            gap: 0;
            padding: 8px 18px 18px;
        }
        .natadmin-row {
            align-items: center;
            border-bottom: 1px solid #edf2ef;
            display: grid;
            gap: 12px;
            grid-template-columns: minmax(0, 1fr) auto;
            padding: 13px 0;
        }
        .natadmin-row:last-child {
            border-bottom: 0;
        }
        .natadmin-row strong,
        .natadmin-row span,
        .natadmin-row small {
            display: block;
        }
        .natadmin-row strong {
            color: #17392a;
            font-weight: 900;
        }
        .natadmin-row span,
        .natadmin-row small {
            color: #66776d;
            font-weight: 700;
        }
        .natadmin-money-in {
            color: #087a45;
            font-weight: 900;
        }
        .natadmin-money-out {
            color: #b64028;
            font-weight: 900;
        }
        .natadmin-empty {
            color: #6a7b70;
            font-weight: 800;
            padding: 24px 18px;
            text-align: center;
        }
        @media (max-width: 1199px) {
            .natadmin-grid,
            .natadmin-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .natadmin-layout,
            .natadmin-hero {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 575px) {
            .natadmin-grid,
            .natadmin-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="natadmin-hero">
        <div>
            <span>@lang('NATCODEV cooperative control room')</span>
            <h2>@lang('Operations Dashboard')</h2>
            <p>@lang('Track member onboarding, certificate readiness, savings movement, farm credit approvals, withdrawals, and support from one place.')</p>
        </div>
        <div class="natadmin-hero-metric">
            <span>@lang('Items needing attention')</span>
            <strong>{{ $attentionTotal }}</strong>
            <small>@lang('Certificates, loans, deposits, withdrawals, support')</small>
        </div>
    </div>

    <div class="natadmin-actions">
        <a class="natadmin-action" href="{{ route('admin.users.all') }}"><i class="las la-users"></i>@lang('Members')</a>
        <a class="natadmin-action" href="{{ route('admin.loan.pending') }}"><i class="las la-seedling"></i>@lang('Farm Credit')</a>
        <a class="natadmin-action" href="{{ route('admin.deposit.pending') }}"><i class="las la-wallet"></i>@lang('Pending Deposits')</a>
        <a class="natadmin-action" href="{{ route('admin.withdraw.pending') }}"><i class="las la-money-check-alt"></i>@lang('Withdrawals')</a>
        <a class="natadmin-action" href="{{ route('admin.ticket.pending') }}"><i class="las la-headset"></i>@lang('Support')</a>
    </div>

    <div class="natadmin-grid">
        <div class="natadmin-card">
            <i class="las la-user-friends"></i>
            <span>@lang('Cooperative members')</span>
            <strong>{{ $widget['total_users'] }}</strong>
            <small>{{ $widget['verified_users'] }} @lang('active and verified')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-certificate"></i>
            <span>@lang('Growers certificates')</span>
            <strong>{{ $certificateRate }}%</strong>
            <small>{{ $widget['certificate_uploaded'] }} @lang('uploaded') / {{ $widget['certificate_missing'] }} @lang('missing')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-hand-holding-usd"></i>
            <span>@lang('Pending farm loans')</span>
            <strong>{{ $widget['total_pending_loan'] }}</strong>
            <small>{{ $widget['total_running_loan'] }} @lang('running loans')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-life-ring"></i>
            <span>@lang('Support attention')</span>
            <strong>{{ $widget['support_attention'] }}</strong>
            <small>@lang('Open member conversations')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-piggy-bank"></i>
            <span>@lang('Wallet balance')</span>
            <strong>{{ $general->cur_sym }}{{ showAmount($widget['total_wallet_balance']) }}</strong>
            <small>@lang('Across member wallets')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-arrow-down"></i>
            <span>@lang('Money in, 30 days')</span>
            <strong>{{ $general->cur_sym }}{{ showAmount($widget['money_in_30']) }}</strong>
            <small>@lang('Deposits, disbursements, credits')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-arrow-up"></i>
            <span>@lang('Money out, 30 days')</span>
            <strong>{{ $general->cur_sym }}{{ showAmount($widget['money_out_30']) }}</strong>
            <small>@lang('Withdrawals, transfers, charges')</small>
        </div>
        <div class="natadmin-card">
            <i class="las la-tasks"></i>
            <span>@lang('Pending approvals')</span>
            <strong>{{ $widget['total_deposit_pending'] + $widget['total_withdraw_pending'] }}</strong>
            <small>{{ $widget['total_deposit_pending'] }} @lang('deposits') / {{ $widget['total_withdraw_pending'] }} @lang('withdrawals')</small>
        </div>
    </div>

    <div class="natadmin-layout">
        <div>
            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Cooperative Cash Movement')</h5>
                    <a href="{{ route('admin.report.transaction') }}">@lang('View ledger')</a>
                </div>
                <div class="card-body">
                    <div id="monthly-dw-report"></div>
                </div>
            </div>

            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Money In vs Money Out')</h5>
                    <a href="{{ route('admin.report.transaction') }}">@lang('Transactions')</a>
                </div>
                <div class="card-body">
                    <div id="transaction-report"></div>
                </div>
            </div>

            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Recent Member Ledger')</h5>
                    <a href="{{ route('admin.report.transaction') }}">@lang('Open report')</a>
                </div>
                <div class="natadmin-list">
                    @forelse ($recentTransactions as $transaction)
                        <div class="natadmin-row">
                            <div>
                                <strong>{{ __($transaction->details ?? ucfirst(str_replace('_', ' ', $transaction->remark))) }}</strong>
                                <span>{{ @$transaction->user->username ?? __('System') }} · {{ $transaction->trx }} · {{ showDateTime($transaction->created_at, 'd M, Y h:i A') }}</span>
                            </div>
                            <div class="{{ $transaction->trx_type == '+' ? 'natadmin-money-in' : 'natadmin-money-out' }}">
                                {{ $transaction->trx_type == '+' ? '+' : '-' }} {{ $general->cur_sym }}{{ showAmount($transaction->amount) }}
                            </div>
                        </div>
                    @empty
                        <div class="natadmin-empty">@lang('No recent transactions yet.')</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Newest Members')</h5>
                    <a href="{{ route('admin.users.all') }}">@lang('All members')</a>
                </div>
                <div class="natadmin-list">
                    @forelse ($recentMembers as $member)
                        @php
                            $hasCertificate = !blank(@$member->address->membership_certificate);
                        @endphp
                        <div class="natadmin-row">
                            <div>
                                <strong>{{ $member->fullname ?: $member->username }}</strong>
                                <span>{{ '@' . $member->username }} · {{ showDateTime($member->created_at, 'd M, Y') }}</span>
                            </div>
                            <small class="{{ $hasCertificate ? 'text--success' : 'text--warning' }}">
                                {{ $hasCertificate ? __('Certified') : __('Needs certificate') }}
                            </small>
                        </div>
                    @empty
                        <div class="natadmin-empty">@lang('No members yet.')</div>
                    @endforelse
                </div>
            </div>

            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Farm Credit Queue')</h5>
                    <a href="{{ route('admin.loan.pending') }}">@lang('Pending loans')</a>
                </div>
                <div class="natadmin-list">
                    @forelse ($recentLoans as $loan)
                        <div class="natadmin-row">
                            <div>
                                <strong>{{ $loan->loan_number }}</strong>
                                <span>{{ @$loan->user->username }} · {{ @$loan->plan->name }} · {{ showDateTime($loan->created_at, 'd M, Y') }}</span>
                            </div>
                            <small>{{ $general->cur_sym }}{{ showAmount($loan->amount) }}</small>
                        </div>
                    @empty
                        <div class="natadmin-empty">@lang('No loan records yet.')</div>
                    @endforelse
                </div>
            </div>

            <div class="natadmin-panel">
                <div class="natadmin-panel-head">
                    <h5>@lang('Support Desk')</h5>
                    <a href="{{ route('admin.ticket.index') }}">@lang('All tickets')</a>
                </div>
                <div class="natadmin-list">
                    @forelse ($recentTickets as $ticket)
                        <div class="natadmin-row">
                            <div>
                                <strong>{{ strLimit($ticket->subject, 36) }}</strong>
                                <span>#{{ $ticket->ticket }} · {{ $ticket->name }} · {{ diffForHumans($ticket->last_reply) }}</span>
                            </div>
                            <small>@php echo $ticket->statusBadge; @endphp</small>
                        </div>
                    @empty
                        <div class="natadmin-empty">@lang('No support tickets yet.')</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script-lib')
    <script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/vendor/chart.js.2.8.0.js') }}"></script>
    <script src="{{ asset('assets/admin/js/charts.js') }}"></script>
@endpush

@push('script')
    <script>
        "use strict";
        barChart(
            document.querySelector("#monthly-dw-report"),
            `{{ __($general->cur_text) }}`,
            [{
                    name: 'Member Deposits',
                    data: @json(@$chartData['deposits'])
                },
                {
                    name: 'Approved Withdrawals',
                    data: @json(@$chartData['withdrawals'])
                }
            ],
            @json($months)
        );

        lineChart(
            document.querySelector("#transaction-report"),
            [{
                    name: "Money In",
                    data: @json(@$chartData['money_in'])
                },
                {
                    name: "Money Out",
                    data: @json(@$chartData['money_out'])
                }
            ],
            @json(@$chartData['trx_dates'])
        );
    </script>
@endpush
BLADE;

file_put_contents($dashboardFile, $dashboard);

echo "Admin dashboard redesigned and request report fixed.\n";

