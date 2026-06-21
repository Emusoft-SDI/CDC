<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';

$dashboard = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="container natdash">
        @if ($user->kv != Status::KYC_VERIFIED)
            @php $kyc = getContent('kyc_content.content', true); @endphp
            <div class="natdash-alert">
                @if ($user->kv == 0)
                    <div>
                        <strong>@lang('Member verification required')</strong>
                        <p>{{ __(@$kyc->data_values->unverified_content) }}</p>
                    </div>
                    <a href="{{ route('user.kyc.form') }}">@lang('Verify now')</a>
                @elseif($user->kv == 2)
                    <div>
                        <strong>@lang('Verification under review')</strong>
                        <p>{{ __(@$kyc->data_values->pending_content) }}</p>
                    </div>
                    <a href="{{ route('user.kyc.data') }}">@lang('View details')</a>
                @endif
            </div>
        @endif

        <div class="natdash-shell">
            <aside class="natdash-profile">
                <div class="natdash-avatar">
                    {{ strtoupper(substr($user->firstname ?: $user->username, 0, 1)) }}{{ strtoupper(substr($user->lastname ?: '', 0, 1)) }}
                </div>
                <h3>{{ __($user->fullname ?: $user->username) }}</h3>
                <p>{{ __($user->email) }}</p>
                <div class="natdash-status">
                    <span class="{{ $user->kv == Status::KYC_VERIFIED ? 'is-good' : 'is-warn' }}"></span>
                    {{ $user->kv == Status::KYC_VERIFIED ? __('Verified member') : __('Verification needed') }}
                </div>
                <div class="natdash-profile-list">
                    <div>
                        <span>@lang('Account No.')</span>
                        <strong>{{ __($user->account_number) }}</strong>
                    </div>
                    <div>
                        <span>@lang('Phone')</span>
                        <strong>{{ __($user->mobile) }}</strong>
                    </div>
                    <div>
                        <span>@lang('Location')</span>
                        <strong>{{ __(trim((@$user->address->lga ? @$user->address->lga . ', ' : '') . (@$user->address->state ?: @$user->address->country ?: 'Nigeria'))) }}</strong>
                    </div>
                </div>
                <a class="natdash-profile-link" href="{{ route('user.profile.setting') }}">
                    <i class="las la-user-cog"></i>
                    @lang('Manage profile')
                </a>
            </aside>

            <main class="natdash-main">
                <section class="natdash-hero">
                    <div>
                        <span class="natdash-kicker">@lang('NATCODEV member dashboard')</span>
                        <h2>@lang('Welcome back'), {{ __($user->firstname ?: $user->username) }}</h2>
                        <p>@lang('Manage cooperative savings, farm input loans, withdrawals, and member activity from one clean workspace.')</p>
                    </div>
                    <div class="natdash-balance">
                        <span>@lang('Available balance')</span>
                        <strong>{{ $general->cur_sym }}{{ showAmount($user->balance) }}</strong>
                        <a href="{{ route('user.transaction.history') }}">@lang('View statement')</a>
                    </div>
                </section>

                <section class="natdash-actions">
                    @if (@$general->modules->deposit)
                        <a href="{{ route('user.deposit.index') }}"><i class="las la-plus-circle"></i><span>@lang('Add Savings')</span></a>
                    @endif
                    @if ($general->modules->loan)
                        <a href="{{ route('user.loan.plans') }}"><i class="las la-seedling"></i><span>@lang('Farm Loan')</span></a>
                    @endif
                    @if ($general->modules->dps)
                        <a href="{{ route('user.dps.plans') }}"><i class="las la-calendar-check"></i><span>@lang('Monthly Plan')</span></a>
                    @endif
                    @if ($general->modules->fdr)
                        <a href="{{ route('user.fdr.plans') }}"><i class="las la-lock"></i><span>@lang('Harvest Save')</span></a>
                    @endif
                    @if (@$general->modules->withdraw)
                        <a href="{{ route('user.withdraw') }}"><i class="las la-wallet"></i><span>@lang('Withdraw')</span></a>
                    @endif
                </section>

                <section class="natdash-grid">
                    @if (@$general->modules->deposit)
                        <a href="{{ route('user.deposit.history') }}?status={{ Status::PAYMENT_PENDING }}" class="natdash-card">
                            <i class="las la-hourglass-half"></i>
                            <span>@lang('Pending Deposits')</span>
                            <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_deposit']) }}</strong>
                        </a>
                    @endif
                    @if (@$general->modules->withdraw)
                        <a href="{{ route('user.withdraw.history') }}?status={{ Status::PAYMENT_PENDING }}" class="natdash-card">
                            <i class="las la-money-check"></i>
                            <span>@lang('Pending Withdrawals')</span>
                            <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_withdraw']) }}</strong>
                        </a>
                    @endif
                    <a href="{{ route('user.transaction.history') }}?today=1" class="natdash-card">
                        <i class="las la-exchange-alt"></i>
                        <span>@lang('Today Transactions')</span>
                        <strong>{{ @$widget['total_trx'] }}</strong>
                    </a>
                    @if ($general->modules->loan)
                        <a href="{{ route('user.loan.list') }}?status={{ Status::LOAN_PENDING }}" class="natdash-card">
                            <i class="las la-hand-holding-usd"></i>
                            <span>@lang('Active Loan Requests')</span>
                            <strong>{{ @$widget['total_loan'] }}</strong>
                        </a>
                    @endif
                    @if ($general->modules->dps)
                        <a href="{{ route('user.dps.list') }}?status={{ Status::FDR_RUNNING }}" class="natdash-card">
                            <i class="las la-box-open"></i>
                            <span>@lang('Monthly Savings Plans')</span>
                            <strong>{{ @$widget['total_dps'] }}</strong>
                        </a>
                    @endif
                    @if ($general->modules->fdr)
                        <a href="{{ route('user.fdr.list') }}?status={{ Status::FDR_RUNNING }}" class="natdash-card">
                            <i class="las la-money-bill"></i>
                            <span>@lang('Harvest Fixed Savings')</span>
                            <strong>{{ @$widget['total_fdr'] }}</strong>
                        </a>
                    @endif
                </section>
            </main>
        </div>

        @if ($general->modules->referral_system)
            <section class="natdash-referral">
                <div>
                    <span class="natdash-kicker">@lang('Invite another cooperative member')</span>
                    <h4>@lang('Referral link')</h4>
                    <p id="ref">{{ route('home') . '?reference=' . $user->username }}</p>
                </div>
                <button type="button" class="copyBtn"><i class="las la-copy"></i>@lang('Copy')</button>
            </section>
        @endif

        <section class="natdash-tables">
            <div class="natdash-table">
                <div class="natdash-table-head">
                    <h4>@lang('Recent Money In')</h4>
                    <a href="{{ route('user.transaction.history') }}">@lang('All transactions')</a>
                </div>
                <table class="table">
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
                                <td class="text--success fw-bold">{{ showAmount($credit->amount) }} {{ __($general->cur_text) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">@lang('No credit activity yet')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="natdash-table">
                <div class="natdash-table-head">
                    <h4>@lang('Recent Money Out')</h4>
                    <a href="{{ route('user.transaction.history') }}">@lang('All transactions')</a>
                </div>
                <table class="table">
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
                                <td class="text--danger fw-bold">{{ showAmount($debit->amount) }} {{ __($general->cur_text) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">@lang('No debit activity yet')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
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

$css = <<<'CSS'

/* NATCODEV professional member dashboard */
.natdash {
    max-width: 1180px;
}
.natdash a {
    text-decoration: none;
}
.natdash-alert,
.natdash-profile,
.natdash-hero,
.natdash-actions a,
.natdash-card,
.natdash-referral,
.natdash-table {
    background: #fff;
    border: 1px solid #e6ece7;
    border-radius: 8px;
    box-shadow: 0 16px 40px rgba(20, 44, 31, .06);
}
.natdash-alert {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 16px 18px;
}
.natdash-alert p {
    margin: 4px 0 0;
}
.natdash-alert a {
    background: #0f6b3d;
    border-radius: 8px;
    color: #fff;
    font-weight: 700;
    padding: 10px 14px;
}
.natdash-shell {
    display: grid;
    gap: 18px;
    grid-template-columns: 280px minmax(0, 1fr);
}
.natdash-profile {
    align-self: start;
    padding: 22px;
    position: sticky;
    top: 92px;
}
.natdash-avatar {
    align-items: center;
    background: linear-gradient(135deg, #0f6b3d, #1f3a2d);
    border-radius: 8px;
    color: #fff;
    display: flex;
    font-size: 26px;
    font-weight: 800;
    height: 74px;
    justify-content: center;
    margin-bottom: 16px;
    width: 74px;
}
.natdash-profile h3 {
    color: #172c21;
    font-size: 22px;
    line-height: 1.2;
    margin: 0 0 5px;
}
.natdash-profile p {
    color: #667369;
    margin-bottom: 14px;
    overflow-wrap: anywhere;
}
.natdash-status {
    align-items: center;
    background: #f4f8f4;
    border-radius: 8px;
    color: #263c2f;
    display: flex;
    font-weight: 700;
    gap: 8px;
    padding: 10px 12px;
}
.natdash-status span {
    border-radius: 50%;
    display: inline-flex;
    height: 10px;
    width: 10px;
}
.natdash-status .is-good {
    background: #0f8a4b;
}
.natdash-status .is-warn {
    background: #b87912;
}
.natdash-profile-list {
    display: grid;
    gap: 12px;
    margin: 18px 0;
}
.natdash-profile-list div {
    border-bottom: 1px solid #edf1ed;
    padding-bottom: 10px;
}
.natdash-profile-list span,
.natdash-kicker,
.natdash-card span {
    color: #657267;
    display: block;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}
.natdash-profile-list strong {
    color: #1f3328;
    display: block;
    font-size: 14px;
    margin-top: 3px;
}
.natdash-profile-link {
    align-items: center;
    background: #10251c;
    border-radius: 8px;
    color: #fff;
    display: flex;
    font-weight: 800;
    gap: 8px;
    justify-content: center;
    min-height: 44px;
}
.natdash-profile-link:hover {
    color: #fff;
}
.natdash-main {
    display: grid;
    gap: 18px;
}
.natdash-hero {
    align-items: center;
    background: linear-gradient(135deg, rgba(15, 77, 48, .96), rgba(20, 43, 34, .92)), url('../../../images/frontend/banner/natcodev-africa-dwarf-coconut-hero.png') center/cover no-repeat;
    color: #fff;
    display: grid;
    gap: 20px;
    grid-template-columns: minmax(0, 1fr) 260px;
    min-height: 220px;
    overflow: hidden;
    padding: 28px;
    position: relative;
}
.natdash-hero::after {
    background: #0f6b3d;
    bottom: 0;
    content: "";
    height: 5px;
    left: 0;
    position: absolute;
    right: 0;
}
.natdash-hero h2 {
    color: #fff;
    font-size: 34px;
    line-height: 1.15;
    margin: 8px 0 10px;
}
.natdash-hero p {
    color: rgba(255, 255, 255, .88);
    margin: 0;
    max-width: 650px;
}
.natdash-hero .natdash-kicker {
    color: rgba(255, 255, 255, .78);
}
.natdash-balance {
    background: rgba(255, 255, 255, .11);
    border: 1px solid rgba(255, 255, 255, .22);
    border-radius: 8px;
    padding: 18px;
}
.natdash-balance span,
.natdash-balance a {
    color: rgba(255, 255, 255, .82);
    display: block;
    font-weight: 700;
}
.natdash-balance strong {
    color: #fff;
    display: block;
    font-size: 32px;
    line-height: 1.15;
    margin: 8px 0 12px;
}
.natdash-actions {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.natdash-actions a {
    align-items: center;
    color: #1d3327;
    display: flex;
    font-weight: 800;
    gap: 10px;
    min-height: 58px;
    padding: 14px;
}
.natdash-actions a:first-child {
    background: #f3faf5;
}
.natdash-actions i {
    color: #0f6b3d;
    font-size: 23px;
}
.natdash-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.natdash-card {
    color: #1c3025;
    display: grid;
    gap: 9px;
    min-height: 136px;
    padding: 18px;
}
.natdash-card i {
    align-items: center;
    background: #ecf6ee;
    border-radius: 8px;
    color: #0f6b3d;
    display: inline-flex;
    font-size: 22px;
    height: 42px;
    justify-content: center;
    width: 42px;
}
.natdash-card strong {
    color: #172c21;
    font-size: 25px;
    line-height: 1.1;
}
.natdash-referral {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-top: 18px;
    padding: 18px;
}
.natdash-referral h4,
.natdash-referral p {
    margin: 0;
}
.natdash-referral p {
    color: #657267;
    overflow-wrap: anywhere;
}
.natdash-referral button {
    align-items: center;
    background: #0f6b3d;
    border: 0;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-weight: 800;
    gap: 8px;
    min-height: 44px;
    padding: 0 18px;
}
.natdash-tables {
    display: grid;
    gap: 18px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 18px;
}
.natdash-table {
    overflow: hidden;
    padding: 0;
}
.natdash-table-head {
    align-items: center;
    border-bottom: 1px solid #e8eee9;
    display: flex;
    justify-content: space-between;
    padding: 16px 18px;
}
.natdash-table-head h4 {
    color: #172c21;
    font-size: 18px;
    margin: 0;
}
.natdash-table-head a {
    color: #0f6b3d;
    font-weight: 800;
}
.natdash-table .table {
    margin: 0;
}
.natdash-table .table th {
    color: #657267;
    font-size: 12px;
    text-transform: uppercase;
}
.natdash-table .table td,
.natdash-table .table th {
    padding: 14px 18px;
}

/* NATCODEV professional top navigation */
.header .container {
    max-width: 1180px;
}
.header__bottom {
    padding: 10px 0 !important;
}
.navbar {
    gap: 18px;
}
.site-logo {
    min-width: 0 !important;
    width: 184px;
}
.site-logo img {
    max-height: 58px !important;
    max-width: 184px !important;
}
.navbar-collapse {
    flex: 1;
    min-width: 0;
}
.main-menu {
    flex: 1;
    flex-wrap: nowrap !important;
    gap: 6px !important;
    justify-content: center !important;
    overflow-x: auto;
    scrollbar-width: none;
}
.main-menu::-webkit-scrollbar {
    display: none;
}
.main-menu li {
    flex: 0 0 auto;
}
.main-menu li a {
    border-radius: 8px;
    font-size: 13px;
    letter-spacing: 0;
    padding: 10px 12px !important;
    text-transform: uppercase;
    white-space: nowrap;
}
.main-menu li a.active,
.main-menu li a:hover {
    background: #edf6ef;
}
.nav-right {
    flex: 0 0 auto;
}
.language-select {
    min-width: 94px;
}
@media (max-width: 1199px) {
    .natdash-shell,
    .natdash-tables {
        grid-template-columns: 1fr;
    }
    .natdash-profile {
        position: static;
    }
    .natdash-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 991px) {
    .main-menu {
        align-items: stretch;
        flex-wrap: wrap !important;
        justify-content: flex-start !important;
        overflow: visible;
    }
    .natdash-hero {
        grid-template-columns: 1fr;
    }
    .natdash-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .natdash-grid,
    .natdash-actions {
        grid-template-columns: 1fr;
    }
    .natdash-hero {
        padding: 22px;
    }
    .natdash-hero h2 {
        font-size: 26px;
    }
    .natdash-alert,
    .natdash-referral {
        align-items: stretch;
        flex-direction: column;
        gap: 12px;
    }
}
CSS;

$dashboardFile = $base . '/core/resources/views/templates/indigo_fusion/user/dashboard.blade.php';
file_put_contents($dashboardFile, $dashboard);

$cssFile = $base . '/assets/templates/indigo_fusion/css/custom.css';
$existing = file_get_contents($cssFile);
$marker = '/* NATCODEV professional member dashboard */';
if (strpos($existing, $marker) === false) {
    file_put_contents($cssFile, rtrim($existing) . PHP_EOL . $css . PHP_EOL);
}

echo "Modern NATCODEV member dashboard applied." . PHP_EOL;

