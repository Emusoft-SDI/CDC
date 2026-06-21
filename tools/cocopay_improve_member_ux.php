<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';

$dashboard = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    @if ($user->kv != Status::KYC_VERIFIED)
        @php
            $kyc = getContent('kyc_content.content', true);
        @endphp
        <div class="row justify-content-center mb-4">
            <div class="col-lg-12">
                @if ($user->kv == 0)
                    <div class="alert alert--danger natco-alert" role="alert">
                        <h4 class="text--base">@lang('Member Verification Required')</h4>
                        <hr>
                        <p class="mb-0">{{ __(@$kyc->data_values->unverified_content) }} <a href="{{ route('user.kyc.form') }}" class="text--base">@lang('Verify Cooperative Membership')</a></p>
                    </div>
                @elseif($user->kv == 2)
                    <div class="alert alert--warning natco-alert" role="alert">
                        <h4 class="text--base">@lang('Verification Under Review')</h4>
                        <hr>
                        <p class="mb-0">{{ __(@$kyc->data_values->pending_content) }} <a href="{{ route('user.kyc.data') }}" class="text--base">@lang('View Submitted Details')</a></p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="natco-member-hero">
        <div class="natco-member-hero__copy">
            <span class="natco-eyebrow">@lang('NATCODEV member workspace')</span>
            <h2>@lang('Welcome back'), {{ __($user->firstname ?? $user->username) }}</h2>
            <p>@lang('Track savings, request farm input loans, and follow cooperative transactions from one place.')</p>
        </div>
        <div class="natco-balance-panel">
            <span>@lang('Available Balance')</span>
            <strong>{{ $general->cur_sym }}{{ showAmount($user->balance) }}</strong>
            <a href="{{ route('user.transaction.history') }}" class="natco-panel-link">@lang('View statement')</a>
        </div>
    </div>

    <div class="natco-quick-actions">
        @if (@$general->modules->deposit)
            <a href="{{ route('user.deposit.index') }}" class="natco-action">
                <i class="las la-plus-circle"></i>
                <span>@lang('Add Savings')</span>
            </a>
        @endif
        @if ($general->modules->loan)
            <a href="{{ route('user.loan.plans') }}" class="natco-action natco-action--strong">
                <i class="las la-seedling"></i>
                <span>@lang('Apply Farm Loan')</span>
            </a>
        @endif
        @if ($general->modules->dps)
            <a href="{{ route('user.dps.plans') }}" class="natco-action">
                <i class="las la-calendar-check"></i>
                <span>@lang('Monthly Savings')</span>
            </a>
        @endif
        @if ($general->modules->fdr)
            <a href="{{ route('user.fdr.plans') }}" class="natco-action">
                <i class="las la-lock"></i>
                <span>@lang('Harvest Savings')</span>
            </a>
        @endif
        @if (@$general->modules->withdraw)
            <a href="{{ route('user.withdraw') }}" class="natco-action">
                <i class="las la-wallet"></i>
                <span>@lang('Withdraw')</span>
            </a>
        @endif
    </div>

    <div class="row gy-3">
        @if (@$general->modules->deposit)
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('user.deposit.history') }}?status={{ Status::PAYMENT_PENDING }}" class="natco-metric">
                    <span class="natco-metric__icon"><i class="las la-hourglass-half"></i></span>
                    <span class="natco-metric__label">@lang('Pending Deposits')</span>
                    <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_deposit']) }}</strong>
                </a>
            </div>
        @endif
        @if (@$general->modules->withdraw)
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('user.withdraw.history') }}?status={{ Status::PAYMENT_PENDING }}" class="natco-metric">
                    <span class="natco-metric__icon"><i class="las la-money-check"></i></span>
                    <span class="natco-metric__label">@lang('Pending Withdrawals')</span>
                    <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_withdraw']) }}</strong>
                </a>
            </div>
        @endif
        <div class="col-xl-3 col-md-6">
            <a href="{{ route('user.transaction.history') }}?today=1" class="natco-metric">
                <span class="natco-metric__icon"><i class="las la-exchange-alt"></i></span>
                <span class="natco-metric__label">@lang('Today Transactions')</span>
                <strong>{{ @$widget['total_trx'] }}</strong>
            </a>
        </div>
        @if ($general->modules->loan)
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('user.loan.list') }}?status={{ Status::LOAN_PENDING }}" class="natco-metric">
                    <span class="natco-metric__icon"><i class="las la-hand-holding-usd"></i></span>
                    <span class="natco-metric__label">@lang('Active Loan Requests')</span>
                    <strong>{{ @$widget['total_loan'] }}</strong>
                </a>
            </div>
        @endif
        @if ($general->modules->dps)
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('user.dps.list') }}?status={{ Status::FDR_RUNNING }}" class="natco-metric">
                    <span class="natco-metric__icon"><i class="las la-box-open"></i></span>
                    <span class="natco-metric__label">@lang('Monthly Savings Plans')</span>
                    <strong>{{ @$widget['total_dps'] }}</strong>
                </a>
            </div>
        @endif
        @if ($general->modules->fdr)
            <div class="col-xl-3 col-md-6">
                <a href="{{ route('user.fdr.list') }}?status={{ Status::FDR_RUNNING }}" class="natco-metric">
                    <span class="natco-metric__icon"><i class="las la-money-bill"></i></span>
                    <span class="natco-metric__label">@lang('Harvest Fixed Savings')</span>
                    <strong>{{ @$widget['total_fdr'] }}</strong>
                </a>
            </div>
        @endif
    </div>

    @if ($general->modules->referral_system)
        <div class="natco-referral">
            <div>
                <span class="natco-eyebrow">@lang('Invite another member')</span>
                <h5>@lang('My Referral Link')</h5>
                <p id="ref">{{ route('home') . '?reference=' . $user->username }}</p>
            </div>
            <button type="button" class="natco-copy copyBtn">
                <i class="icon-copy"></i>
                <span>@lang('Copy')</span>
            </button>
        </div>
    @endif

    <div class="pt-40">
        <div class="row gy-4 justify-content-center">
            <div class="col-xxl-6">
                <div class="dashboard-table natco-table">
                    <h5 class="dashboard-table__title card-header__title text-dark">@lang('Recent Money In')</h5>
                    <table class="table table--responsive--md">
                        <thead>
                            <tr>
                                <th>@lang('Date')</th>
                                <th>@lang('Reference')</th>
                                <th>@lang('Amount')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($credits as $credit)
                                <tr>
                                    <td>{{ showDateTime($credit->created_at, 'd M, Y h:i A') }}</td>
                                    <td>{{ __($credit->trx) }}</td>
                                    <td class="fw-bold text--success">{{ showAmount($credit->amount) }} {{ __($general->cur_text) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-xxl-6">
                <div class="dashboard-table natco-table">
                    <h5 class="dashboard-table__title card-header__title text-dark">@lang('Recent Money Out')</h5>
                    <table class="table table--responsive--md">
                        <thead>
                            <tr>
                                <th>@lang('Date')</th>
                                <th>@lang('Reference')</th>
                                <th>@lang('Amount')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($debits as $debit)
                                <tr>
                                    <td>{{ showDateTime($debit->created_at, 'd M, Y h:i A') }}</td>
                                    <td>{{ __($debit->trx) }}</td>
                                    <td class="fw-bold text--danger">{{ showAmount($debit->amount) }} {{ __($general->cur_text) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="100%" class="text-center">{{ __($emptyMessage) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";
        (function($) {
            $('.copyBtn').click(function() {
                const urlText = $('#ref').text();
                const tempTextArea = $('<textarea>');
                tempTextArea.val(urlText);
                $('body').append(tempTextArea);
                tempTextArea.select();
                document.execCommand('copy');
                tempTextArea.remove();
                notify('success', `Copied - ${urlText}`)
            });
        })(jQuery);
    </script>
@endpush
BLADE;

$loanForm = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    @php
        $perInstallment = $amount * $plan->per_installment / 100;
        $totalRepayment = $perInstallment * $plan->total_installment;
    @endphp
    <div class="natco-flow-heading">
        <span class="natco-eyebrow">@lang('Farm input loan')</span>
        <h3>@lang('Complete Your Cooperative Loan Request')</h3>
        <p>@lang('Share the farm details NATCODEV needs to review support for coconut production, dwarf coconut establishment, and harvest-linked repayment.')</p>
    </div>

    <div class="row gy-4 align-items-start">
        <div class="col-xl-4">
            <aside class="natco-loan-summary">
                <span class="natco-eyebrow">@lang('Request summary')</span>
                <h4>@lang($plan->name)</h4>
                <ul>
                    <li>
                        <span>@lang('Loan Amount')</span>
                        <strong>{{ $general->cur_sym . showAmount($amount) }}</strong>
                    </li>
                    <li>
                        <span>@lang('Installments')</span>
                        <strong>{{ $plan->total_installment }}</strong>
                    </li>
                    <li>
                        <span>@lang('Each Installment')</span>
                        <strong>{{ $general->cur_sym . showAmount($perInstallment) }}</strong>
                    </li>
                    <li>
                        <span>@lang('Total Repayment')</span>
                        <strong>{{ $general->cur_sym . showAmount($totalRepayment) }}</strong>
                    </li>
                </ul>
                @if ($plan->delay_value && getAmount($plan->delay_charge))
                    <p class="natco-summary-note">
                        @lang('Late installment charge applies after') {{ $plan->delay_value }} @lang('days.')
                    </p>
                @endif
            </aside>
        </div>
        <div class="col-xl-8">
            <div class="card custom--card natco-form-card">
                <div class="card-header">
                    <h5 class="card-title">@lang('Coconut Farm Details')</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('user.loan.apply.confirm') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @if ($plan->instruction)
                            <div class="natco-inline-note">
                                @php echo $plan->instruction @endphp
                            </div>
                        @endif
                        <x-viser-form identifier="id" identifierValue="{{ $plan->form_id }}" />
                        <button type="submit" class="btn btn--base w-100">@lang('Submit Loan Request')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('bottom-menu')
    <div class="col-12 order-lg-3 order-4">
        <div class="d-flex nav-buttons flex-align gap-md-3 gap-2">
            <a href="{{ route('user.loan.plans') }}" class="btn btn--base active">@lang('Loan Plans')</a>
            <a href="{{ route('user.loan.list') }}" class="btn btn-outline--base">@lang('My Loan List')</a>
        </div>
    </div>
@endpush
BLADE;

$css = <<<'CSS'

/* NATCODEV member experience */
.natco-member-hero {
    align-items: stretch;
    background: linear-gradient(135deg, rgba(19, 95, 52, .95), rgba(42, 71, 46, .92)), url('../../../images/frontend/banner/natcodev-africa-dwarf-coconut-hero.png') center/cover no-repeat;
    border-radius: 8px;
    color: #fff;
    display: grid;
    gap: 24px;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 340px);
    margin-bottom: 22px;
    overflow: hidden;
    padding: 28px;
}
.natco-member-hero__copy h2,
.natco-flow-heading h3 {
    color: inherit;
    font-size: 30px;
    line-height: 1.2;
    margin: 8px 0 10px;
}
.natco-member-hero__copy p,
.natco-flow-heading p {
    margin: 0;
    max-width: 680px;
}
.natco-eyebrow {
    display: block;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: uppercase;
}
.natco-balance-panel {
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .24);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 150px;
    padding: 20px;
}
.natco-balance-panel span {
    opacity: .86;
}
.natco-balance-panel strong {
    color: #fff;
    display: block;
    font-size: 34px;
    line-height: 1.15;
    margin: 8px 0 12px;
}
.natco-panel-link {
    color: #fff;
    font-weight: 700;
    text-decoration: underline;
}
.natco-quick-actions {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    margin-bottom: 22px;
}
.natco-action,
.natco-metric,
.natco-referral,
.natco-table,
.natco-loan-summary,
.natco-form-card {
    background: #fff;
    border: 1px solid #e6eadf;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(32, 47, 37, .06);
}
.natco-action {
    align-items: center;
    color: #26352b;
    display: flex;
    font-weight: 700;
    gap: 10px;
    min-height: 64px;
    padding: 14px;
}
.natco-action i {
    color: hsl(var(--base));
    font-size: 24px;
}
.natco-action--strong {
    background: #e9f8ed;
    border-color: rgba(28, 135, 69, .28);
}
.natco-metric {
    color: #26352b;
    display: grid;
    gap: 8px;
    min-height: 132px;
    padding: 18px;
}
.natco-metric__icon {
    align-items: center;
    background: #eff7e8;
    border-radius: 8px;
    color: hsl(var(--base));
    display: inline-flex;
    height: 40px;
    justify-content: center;
    width: 40px;
}
.natco-metric__icon i {
    font-size: 22px;
}
.natco-metric__label {
    color: #5c6a60;
    font-weight: 600;
}
.natco-metric strong {
    color: #1d2d22;
    font-size: 24px;
    line-height: 1.2;
}
.natco-referral {
    align-items: center;
    display: flex;
    gap: 18px;
    justify-content: space-between;
    margin-top: 22px;
    padding: 18px;
}
.natco-referral h5,
.natco-referral p {
    margin: 0;
}
.natco-referral p {
    color: #59685e;
    overflow-wrap: anywhere;
}
.natco-copy {
    align-items: center;
    background: hsl(var(--base));
    border: 0;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-weight: 700;
    gap: 8px;
    min-height: 44px;
    padding: 0 18px;
}
.natco-table {
    padding: 18px;
}
.natco-flow-heading {
    background: #f7fbf4;
    border-left: 4px solid hsl(var(--base));
    border-radius: 8px;
    color: #25352b;
    margin-bottom: 22px;
    padding: 22px;
}
.natco-loan-summary {
    padding: 22px;
    position: sticky;
    top: 92px;
}
.natco-loan-summary h4 {
    margin: 8px 0 18px;
}
.natco-loan-summary ul {
    display: grid;
    gap: 12px;
    margin: 0;
    padding: 0;
}
.natco-loan-summary li {
    align-items: center;
    border-bottom: 1px solid #edf0e8;
    display: flex;
    justify-content: space-between;
    padding-bottom: 12px;
}
.natco-loan-summary li span {
    color: #647066;
}
.natco-loan-summary li strong {
    color: #203426;
}
.natco-summary-note,
.natco-inline-note {
    background: #fff8e7;
    border: 1px solid #f0dfaa;
    border-radius: 8px;
    color: #6a5520;
    margin: 18px 0 0;
    padding: 12px;
}
.natco-inline-note {
    margin: 0 0 18px;
}
.natco-form-card .form-group {
    margin-bottom: 18px;
}
.natco-form-card .form-label {
    color: #26352b;
    font-weight: 700;
}
.natco-form-card .form--control {
    min-height: 48px;
}
.natco-alert {
    border-radius: 8px;
}
@media (max-width: 991px) {
    .natco-member-hero {
        grid-template-columns: 1fr;
    }
    .natco-quick-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .natco-loan-summary {
        position: static;
    }
}
@media (max-width: 575px) {
    .natco-member-hero {
        padding: 20px;
    }
    .natco-member-hero__copy h2,
    .natco-flow-heading h3 {
        font-size: 24px;
    }
    .natco-quick-actions {
        grid-template-columns: 1fr;
    }
    .natco-referral {
        align-items: stretch;
        flex-direction: column;
    }
}
CSS;

$files = [
    $base . '/core/resources/views/templates/crystal_sky/user/dashboard.blade.php' => $dashboard,
    $base . '/core/resources/views/templates/crystal_sky/user/loan/form.blade.php' => $loanForm,
    $base . '/core/resources/views/templates/indigo_fusion/user/dashboard.blade.php' => $dashboard,
    $base . '/core/resources/views/templates/indigo_fusion/user/loan/form.blade.php' => $loanForm,
];

foreach ($files as $file => $content) {
    if (!is_file($file)) {
        throw new RuntimeException("Missing file: {$file}");
    }
    file_put_contents($file, $content);
}

$marker = '/* NATCODEV member experience */';
$cssFiles = [
    $base . '/assets/templates/crystal_sky/css/custom.css',
    $base . '/assets/templates/indigo_fusion/css/custom.css',
];

foreach ($cssFiles as $customCss) {
    if (!is_file($customCss)) {
        throw new RuntimeException("Missing CSS file: {$customCss}");
    }
    $existing = file_get_contents($customCss);
    if (strpos($existing, $marker) === false) {
        file_put_contents($customCss, rtrim($existing) . PHP_EOL . $css . PHP_EOL);
    }
}

echo "Updated member dashboard, loan form, and NATCODEV UX styles for crystal_sky and indigo_fusion." . PHP_EOL;
