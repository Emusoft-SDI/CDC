<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$viewPath = $root . '/core/resources/views/templates/indigo_fusion/user/dashboard.blade.php';
$cssPath = $root . '/assets/templates/indigo_fusion/css/custom.css';

$view = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    @php
        $memberName = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
        $memberName = $memberName ?: ($user->username ?? __('Member'));
        $initials = strtoupper(substr($user->firstname ?: $user->username, 0, 1) . substr($user->lastname ?: '', 0, 1));
        $initials = $initials ?: 'M';
        $recentTransactions = $credits->merge($debits)->sortByDesc('created_at')->take(6);
        $memberLocation = trim((@$user->address->lga ? @$user->address->lga . ', ' : '') . (@$user->address->state ?: @$user->address->country ?: 'Nigeria'));
    @endphp

    <div class="container natfin">
        @if ($user->kv != Status::KYC_VERIFIED)
            @php $kyc = getContent('kyc_content.content', true); @endphp
            <div class="natfin-alert">
                <i class="las la-shield-alt"></i>
                @if ($user->kv == 0)
                    <div>
                        <strong>@lang('Complete member verification')</strong>
                        <span>{{ __(@$kyc->data_values->unverified_content) }}</span>
                    </div>
                    <a href="{{ route('user.kyc.form') }}">@lang('Verify now')</a>
                @elseif($user->kv == 2)
                    <div>
                        <strong>@lang('Verification under review')</strong>
                        <span>{{ __(@$kyc->data_values->pending_content) }}</span>
                    </div>
                    <a href="{{ route('user.kyc.data') }}">@lang('View details')</a>
                @endif
            </div>
        @endif

        <div class="natfin-grid">
            <aside class="natfin-rail">
                <div class="natfin-member-card">
                    <div class="natfin-avatar">{{ $initials }}</div>
                    <div>
                        <span>@lang('Welcome back')</span>
                        <h3>{{ __($memberName) }}</h3>
                    </div>
                    <a href="{{ route('user.profile.setting') }}" title="@lang('Profile')"><i class="las la-user-cog"></i></a>
                </div>

                <nav class="natfin-side-nav">
                    <a class="active" href="{{ route('user.home') }}"><i class="las la-th-large"></i><span>@lang('Overview')</span></a>
                    <a href="{{ route('user.transaction.history') }}"><i class="las la-receipt"></i><span>@lang('Ledger')</span></a>
                    @if ($general->modules->loan)
                        <a href="{{ route('user.loan.plans') }}"><i class="las la-hand-holding-usd"></i><span>@lang('Farm Credit')</span></a>
                    @endif
                    @if ($general->modules->dps)
                        <a href="{{ route('user.dps.plans') }}"><i class="las la-calendar-check"></i><span>@lang('Monthly Plan')</span></a>
                    @endif
                    @if ($general->modules->fdr)
                        <a href="{{ route('user.fdr.plans') }}"><i class="las la-lock"></i><span>@lang('Harvest Save')</span></a>
                    @endif
                </nav>

                <div class="natfin-mini-card">
                    <span>@lang('Member status')</span>
                    <strong>{{ $user->kv == Status::KYC_VERIFIED ? __('Verified') : __('Action needed') }}</strong>
                    <small>{{ __($memberLocation ?: 'Nigeria') }}</small>
                </div>
            </aside>

            <main class="natfin-main">
                <section class="natfin-wallet-row">
                    <div class="natfin-wallet-card">
                        <div class="natfin-wallet-top">
                            <div>
                                <span>@lang('Cooperative Wallet')</span>
                                <h2>{{ $general->cur_sym }}{{ showAmount($user->balance) }}</h2>
                            </div>
                            <i class="las la-leaf"></i>
                        </div>
                        <div class="natfin-wallet-meta">
                            <div>
                                <small>@lang('Account No.')</small>
                                <strong>{{ __($user->account_number ?: 'Pending') }}</strong>
                            </div>
                            <div>
                                <small>@lang('Currency')</small>
                                <strong>{{ __($general->cur_text) }}</strong>
                            </div>
                        </div>
                        <div class="natfin-wallet-actions">
                            @if (@$general->modules->deposit)
                                <a href="{{ route('user.deposit.index') }}"><i class="las la-plus"></i>@lang('Add Money')</a>
                            @endif
                            <a href="{{ route('user.transaction.history') }}"><i class="las la-file-invoice"></i>@lang('Statement')</a>
                        </div>
                    </div>

                    <div class="natfin-summary-stack">
                        <a href="{{ route('user.transaction.history') }}?today=1" class="natfin-summary-card">
                            <i class="las la-sync"></i>
                            <span>@lang('Today Activity')</span>
                            <strong>{{ @$widget['total_trx'] }}</strong>
                        </a>
                        @if ($general->modules->loan)
                            <a href="{{ route('user.loan.list') }}?status={{ Status::LOAN_PENDING }}" class="natfin-summary-card natfin-summary-card--blue">
                                <i class="las la-seedling"></i>
                                <span>@lang('Running Loans')</span>
                                <strong>{{ @$widget['total_loan'] }}</strong>
                            </a>
                        @endif
                    </div>
                </section>

                <section class="natfin-actions">
                    @if (@$general->modules->deposit)
                        <a href="{{ route('user.deposit.index') }}"><i class="las la-plus-circle"></i><span>@lang('Deposit')</span></a>
                    @endif
                    @if (@$general->modules->withdraw)
                        <a href="{{ route('user.withdraw') }}"><i class="las la-wallet"></i><span>@lang('Withdraw')</span></a>
                    @endif
                    @if ($general->modules->loan)
                        <a href="{{ route('user.loan.plans') }}"><i class="las la-hand-holding-usd"></i><span>@lang('Apply Loan')</span></a>
                    @endif
                    @if ($general->modules->own_bank)
                        <a href="{{ route('user.beneficiary.own') }}"><i class="las la-exchange-alt"></i><span>@lang('Transfer')</span></a>
                    @endif
                    @if (@$general->modules->airtime)
                        <a href="{{ route('user.airtime.form') }}"><i class="las la-mobile"></i><span>@lang('Airtime')</span></a>
                    @endif
                </section>

                <section class="natfin-metrics">
                    @if (@$general->modules->deposit)
                        <a href="{{ route('user.deposit.history') }}?status={{ Status::PAYMENT_PENDING }}">
                            <span>@lang('Pending Deposits')</span>
                            <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_deposit']) }}</strong>
                        </a>
                    @endif
                    @if (@$general->modules->withdraw)
                        <a href="{{ route('user.withdraw.history') }}?status={{ Status::PAYMENT_PENDING }}">
                            <span>@lang('Pending Withdrawals')</span>
                            <strong>{{ $general->cur_sym }}{{ showAmount(@$widget['total_withdraw']) }}</strong>
                        </a>
                    @endif
                    @if ($general->modules->dps)
                        <a href="{{ route('user.dps.list') }}?status={{ Status::FDR_RUNNING }}">
                            <span>@lang('Monthly Savings')</span>
                            <strong>{{ @$widget['total_dps'] }}</strong>
                        </a>
                    @endif
                    @if ($general->modules->fdr)
                        <a href="{{ route('user.fdr.list') }}?status={{ Status::FDR_RUNNING }}">
                            <span>@lang('Harvest Savings')</span>
                            <strong>{{ @$widget['total_fdr'] }}</strong>
                        </a>
                    @endif
                </section>

                <section class="natfin-panel">
                    <div class="natfin-panel-head">
                        <div>
                            <span>@lang('Recent movement')</span>
                            <h4>@lang('Transactions')</h4>
                        </div>
                        <a href="{{ route('user.transaction.history') }}">@lang('View all')</a>
                    </div>
                    <div class="natfin-activity-list">
                        @forelse($recentTransactions as $trx)
                            @php
                                $isCredit = $trx->trx_type == '+';
                                $label = $isCredit ? __('Money In') : __('Money Out');
                            @endphp
                            <a href="{{ route('user.transaction.history') }}?search={{ $trx->trx }}" class="natfin-activity">
                                <span class="natfin-activity-icon {{ $isCredit ? 'is-credit' : 'is-debit' }}"><i class="las {{ $isCredit ? 'la-arrow-down' : 'la-arrow-up' }}"></i></span>
                                <span>
                                    <strong>{{ __($trx->details ?: $label) }}</strong>
                                    <small>{{ __($trx->trx) }} · {{ diffForHumans($trx->created_at) }}</small>
                                </span>
                                <b class="{{ $isCredit ? 'is-credit' : 'is-debit' }}">{{ $isCredit ? '+' : '-' }} {{ showAmount($trx->amount) }} {{ __($general->cur_text) }}</b>
                            </a>
                        @empty
                            <div class="natfin-empty">@lang('No transaction activity yet')</div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="natfin-right">
                <div class="natfin-card">
                    <div class="natfin-card-head">
                        <span>@lang('Member profile')</span>
                        <a href="{{ route('user.profile.setting') }}"><i class="las la-edit"></i></a>
                    </div>
                    <div class="natfin-profile-line">
                        <small>@lang('Email')</small>
                        <strong>{{ __($user->email) }}</strong>
                    </div>
                    <div class="natfin-profile-line">
                        <small>@lang('Phone')</small>
                        <strong>{{ __($user->mobile ?: 'Not set') }}</strong>
                    </div>
                    <div class="natfin-profile-line">
                        <small>@lang('Location')</small>
                        <strong>{{ __($memberLocation ?: 'Nigeria') }}</strong>
                    </div>
                </div>

                @if ($general->modules->referral_system)
                    <div class="natfin-card natfin-referral">
                        <span>@lang('Invite growers')</span>
                        <h4>@lang('Share your cooperative link')</h4>
                        <p id="ref">{{ route('home') . '?reference=' . $user->username }}</p>
                        <button type="button" class="copyBtn"><i class="las la-copy"></i>@lang('Copy link')</button>
                    </div>
                @endif

                <div class="natfin-card natfin-insight">
                    <i class="las la-chart-line"></i>
                    <span>@lang('Farm finance focus')</span>
                    <strong>@lang('Keep savings, loans, and withdrawals traceable from one member ledger.')</strong>
                    <a href="{{ route('user.transaction.history') }}">@lang('Open ledger')</a>
                </div>
            </aside>
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

$css = <<<'CSS'

/* NATCODEV fintech dashboard redesign */
.natfin {
    color: #1d2f27;
}
.natfin a {
    text-decoration: none;
}
.natfin-alert {
    align-items: center;
    background: #fff8e8;
    border: 1px solid #f2d58d;
    border-radius: 8px;
    color: #4e3d16;
    display: flex;
    gap: 14px;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 14px 16px;
}
.natfin-alert i {
    color: #b77b00;
    font-size: 26px;
}
.natfin-alert strong,
.natfin-alert span {
    display: block;
}
.natfin-alert a {
    background: #17392a;
    border-radius: 8px;
    color: #fff;
    flex: 0 0 auto;
    font-weight: 800;
    padding: 9px 14px;
}
.natfin-grid {
    display: grid;
    gap: 18px;
    grid-template-columns: 220px minmax(0, 1fr) 300px;
}
.natfin-rail,
.natfin-card,
.natfin-panel,
.natfin-wallet-card,
.natfin-summary-card,
.natfin-metrics a {
    background: #fff;
    border: 1px solid #dfe8e2;
    border-radius: 8px;
    box-shadow: 0 16px 34px rgba(23, 57, 42, 0.08);
}
.natfin-rail {
    align-self: start;
    padding: 14px;
    position: sticky;
    top: 104px;
}
.natfin-member-card {
    align-items: center;
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
}
.natfin-avatar {
    align-items: center;
    background: #0f6b3d;
    border-radius: 8px;
    color: #fff;
    display: flex;
    flex: 0 0 44px;
    font-weight: 900;
    height: 44px;
    justify-content: center;
}
.natfin-member-card span,
.natfin-mini-card span,
.natfin-card-head span,
.natfin-referral span,
.natfin-insight span,
.natfin-panel-head span {
    color: #67776d;
    display: block;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}
.natfin-member-card h3 {
    color: #17392a;
    font-size: 15px;
    font-weight: 900;
    margin: 0;
}
.natfin-member-card a {
    align-items: center;
    background: #eef7f2;
    border-radius: 8px;
    color: #0f6b3d;
    display: flex;
    height: 36px;
    justify-content: center;
    margin-left: auto;
    width: 36px;
}
.natfin-side-nav {
    display: grid;
    gap: 7px;
}
.natfin-side-nav a {
    align-items: center;
    border-radius: 8px;
    color: #3b4f43;
    display: flex;
    font-weight: 800;
    gap: 9px;
    padding: 10px 11px;
}
.natfin-side-nav a i {
    color: #0f6b3d;
    font-size: 18px;
}
.natfin-side-nav a:hover,
.natfin-side-nav a.active {
    background: #eaf6ef;
    color: #0f6b3d;
}
.natfin-mini-card {
    background: #17392a;
    border-radius: 8px;
    color: #fff;
    margin-top: 16px;
    padding: 14px;
}
.natfin-mini-card span,
.natfin-mini-card small {
    color: rgba(255, 255, 255, 0.72);
}
.natfin-mini-card strong,
.natfin-mini-card small {
    display: block;
}
.natfin-mini-card strong {
    color: #fff;
    font-size: 18px;
    margin: 4px 0;
}
.natfin-main {
    display: grid;
    gap: 18px;
}
.natfin-wallet-row {
    display: grid;
    gap: 18px;
    grid-template-columns: minmax(0, 1fr) 190px;
}
.natfin-wallet-card {
    background: linear-gradient(135deg, #0f6b3d 0%, #17392a 62%, #274f84 100%);
    color: #fff;
    min-height: 250px;
    overflow: hidden;
    padding: 24px;
    position: relative;
}
.natfin-wallet-card::after {
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    content: "";
    height: 210px;
    position: absolute;
    right: -72px;
    top: -76px;
    width: 210px;
}
.natfin-wallet-top {
    align-items: flex-start;
    display: flex;
    justify-content: space-between;
    position: relative;
    z-index: 1;
}
.natfin-wallet-top span,
.natfin-wallet-meta small {
    color: rgba(255, 255, 255, 0.72);
    display: block;
    font-size: 13px;
    font-weight: 800;
}
.natfin-wallet-top h2 {
    color: #fff;
    font-size: 38px;
    font-weight: 900;
    line-height: 1.1;
    margin: 8px 0 0;
}
.natfin-wallet-top i {
    color: rgba(255, 255, 255, 0.85);
    font-size: 36px;
}
.natfin-wallet-meta {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 46px;
    position: relative;
    z-index: 1;
}
.natfin-wallet-meta div {
    background: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 8px;
    padding: 12px;
}
.natfin-wallet-meta strong {
    color: #fff;
    display: block;
    font-size: 15px;
    font-weight: 900;
    margin-top: 3px;
}
.natfin-wallet-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
    position: relative;
    z-index: 1;
}
.natfin-wallet-actions a {
    align-items: center;
    background: #fff;
    border-radius: 8px;
    color: #17392a;
    display: inline-flex;
    font-weight: 900;
    gap: 7px;
    min-height: 40px;
    padding: 9px 13px;
}
.natfin-summary-stack {
    display: grid;
    gap: 14px;
}
.natfin-summary-card {
    color: #17392a;
    display: grid;
    min-height: 118px;
    padding: 16px;
}
.natfin-summary-card i {
    align-items: center;
    background: #eaf6ef;
    border-radius: 8px;
    color: #0f6b3d;
    display: flex;
    font-size: 20px;
    height: 38px;
    justify-content: center;
    width: 38px;
}
.natfin-summary-card--blue i {
    background: #edf4ff;
    color: #274f84;
}
.natfin-summary-card span {
    color: #617268;
    font-weight: 800;
}
.natfin-summary-card strong {
    color: #17392a;
    font-size: 28px;
    font-weight: 900;
}
.natfin-actions {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.natfin-actions a {
    align-items: center;
    background: #fff;
    border: 1px solid #dfe8e2;
    border-radius: 8px;
    color: #263b30;
    display: flex;
    font-weight: 900;
    gap: 9px;
    min-height: 62px;
    padding: 12px;
}
.natfin-actions a:hover {
    border-color: #0f6b3d;
    color: #0f6b3d;
}
.natfin-actions i {
    align-items: center;
    background: #f0f7f3;
    border-radius: 8px;
    color: #0f6b3d;
    display: flex;
    flex: 0 0 34px;
    font-size: 18px;
    height: 34px;
    justify-content: center;
}
.natfin-metrics {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.natfin-metrics a {
    color: #17392a;
    padding: 15px;
}
.natfin-metrics span {
    color: #65766b;
    display: block;
    font-size: 12px;
    font-weight: 800;
}
.natfin-metrics strong {
    color: #17392a;
    display: block;
    font-size: 20px;
    font-weight: 900;
    margin-top: 6px;
}
.natfin-panel {
    padding: 18px;
}
.natfin-panel-head,
.natfin-card-head {
    align-items: center;
    display: flex;
    justify-content: space-between;
    gap: 12px;
}
.natfin-panel-head h4 {
    color: #17392a;
    font-size: 20px;
    font-weight: 900;
    margin: 2px 0 0;
}
.natfin-panel-head a,
.natfin-card-head a,
.natfin-insight a {
    color: #0f6b3d;
    font-weight: 900;
}
.natfin-activity-list {
    display: grid;
    gap: 10px;
    margin-top: 15px;
}
.natfin-activity {
    align-items: center;
    border: 1px solid #edf2ef;
    border-radius: 8px;
    color: #17392a;
    display: grid;
    gap: 12px;
    grid-template-columns: 42px minmax(0, 1fr) auto;
    padding: 12px;
}
.natfin-activity:hover {
    background: #f7faf8;
}
.natfin-activity-icon {
    align-items: center;
    border-radius: 8px;
    display: flex;
    height: 40px;
    justify-content: center;
    width: 40px;
}
.natfin-activity-icon.is-credit {
    background: #eaf7f0;
    color: #0f8a4f;
}
.natfin-activity-icon.is-debit {
    background: #fff0ed;
    color: #b33b24;
}
.natfin-activity strong,
.natfin-activity small,
.natfin-activity b {
    display: block;
}
.natfin-activity strong {
    color: #17392a;
    font-weight: 900;
}
.natfin-activity small {
    color: #6d7c72;
    font-weight: 700;
    margin-top: 2px;
}
.natfin-activity b {
    font-weight: 900;
    text-align: right;
    white-space: nowrap;
}
.natfin-activity .is-credit {
    color: #0f8a4f;
}
.natfin-activity .is-debit {
    color: #b33b24;
}
.natfin-empty {
    background: #f7faf8;
    border-radius: 8px;
    color: #617268;
    font-weight: 800;
    padding: 22px;
    text-align: center;
}
.natfin-right {
    display: grid;
    align-self: start;
    gap: 18px;
    position: sticky;
    top: 104px;
}
.natfin-card {
    padding: 18px;
}
.natfin-profile-line {
    border-top: 1px solid #edf2ef;
    padding: 12px 0 0;
    margin-top: 12px;
}
.natfin-profile-line small,
.natfin-profile-line strong {
    display: block;
}
.natfin-profile-line small {
    color: #65766b;
    font-size: 12px;
    font-weight: 800;
}
.natfin-profile-line strong {
    color: #17392a;
    font-weight: 900;
    overflow-wrap: anywhere;
}
.natfin-referral {
    background: #f7faf8;
}
.natfin-referral h4 {
    color: #17392a;
    font-size: 18px;
    font-weight: 900;
    margin: 4px 0 8px;
}
.natfin-referral p {
    background: #fff;
    border: 1px solid #dfe8e2;
    border-radius: 8px;
    color: #53645a;
    font-size: 12px;
    margin: 0 0 10px;
    overflow-wrap: anywhere;
    padding: 10px;
}
.natfin-referral button {
    background: #0f6b3d;
    border: 0;
    border-radius: 8px;
    color: #fff;
    font-weight: 900;
    padding: 10px 13px;
    width: 100%;
}
.natfin-insight {
    background: #17392a;
    color: #fff;
}
.natfin-insight i {
    color: #9bd7b2;
    font-size: 28px;
}
.natfin-insight span {
    color: rgba(255, 255, 255, 0.68);
    margin-top: 8px;
}
.natfin-insight strong {
    color: #fff;
    display: block;
    font-size: 17px;
    font-weight: 900;
    line-height: 1.35;
    margin: 8px 0 12px;
}
.natfin-insight a {
    color: #9bd7b2;
}
@media (max-width: 1199px) {
    .natfin-grid {
        grid-template-columns: 1fr;
    }
    .natfin-rail,
    .natfin-right {
        position: static;
    }
    .natfin-rail {
        display: grid;
        gap: 14px;
        grid-template-columns: 1fr;
    }
    .natfin-side-nav {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
    .natfin-side-nav a {
        justify-content: center;
    }
    .natfin-right {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 991px) {
    .natfin-wallet-row,
    .natfin-right {
        grid-template-columns: 1fr;
    }
    .natfin-actions,
    .natfin-metrics,
    .natfin-side-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 575px) {
    .natfin-alert,
    .natfin-member-card,
    .natfin-panel-head {
        align-items: flex-start;
        flex-direction: column;
    }
    .natfin-alert a,
    .natfin-member-card a {
        width: 100%;
    }
    .natfin-wallet-card {
        padding: 18px;
    }
    .natfin-wallet-top h2 {
        font-size: 30px;
    }
    .natfin-wallet-meta,
    .natfin-actions,
    .natfin-metrics,
    .natfin-side-nav {
        grid-template-columns: 1fr;
    }
    .natfin-activity {
        grid-template-columns: 38px minmax(0, 1fr);
    }
    .natfin-activity b {
        grid-column: 2;
        text-align: left;
    }
}
CSS;

if (!is_file($viewPath)) {
    throw new RuntimeException("Missing view: {$viewPath}");
}
if (!is_file($cssPath)) {
    throw new RuntimeException("Missing CSS: {$cssPath}");
}

file_put_contents($viewPath, $view);

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV fintech dashboard redesign */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Fintech dashboard redesign applied.\n";
