<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$viewPath = $root . '/resources/views/templates/indigo_fusion/user/transactions.blade.php';
$cssPath = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/templates/indigo_fusion/css/custom.css';

$view = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="container nat-transactions-page">
        <div class="nat-tx-header">
            <div>
                <span class="nat-kicker">@lang('Member Ledger')</span>
                <h3>@lang('Transactions')</h3>
                <p>@lang('Review money in, money out, balances, and transaction references from one place.')</p>
            </div>
            <a class="nat-tx-reset" href="{{ route('user.transaction.history') }}">
                <i class="las la-redo-alt"></i> @lang('Reset')
            </a>
        </div>

        <div class="nat-tx-help">
            <strong>@lang('What changed?')</strong>
            <span>@lang('Money In (Credit) means funds were added to your wallet. Money Out (Debit) means funds were removed from your wallet.')</span>
        </div>

        <div class="nat-tx-filter">
            <form action="{{ route('user.transaction.history') }}" method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label>@lang('Reference Number')</label>
                        <input class="form-control form--control" name="search" type="text" value="{{ request()->search }}" placeholder="@lang('Enter transaction reference')">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label>@lang('Direction')</label>
                        <select class="form-select form--control" name="trx_type">
                            <option value="">@lang('All Directions')</option>
                            <option value="+" @selected(request()->trx_type == '+')>@lang('Money In (Credit)')</option>
                            <option value="-" @selected(request()->trx_type == '-')>@lang('Money Out (Debit)')</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label>@lang('Category')</label>
                        <select class="form-select form--control" name="remark">
                            <option value="">@lang('All Categories')</option>
                            @foreach ($remarks as $remark)
                                <option value="{{ $remark->remark }}" @selected(request()->remark == $remark->remark)>{{ __(keyToTitle($remark->remark)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <button class="btn nat-tx-filter-btn w-100" type="submit">
                            <i class="las la-filter"></i> @lang('Filter')
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="nat-tx-table-card">
            <div class="table-responsive--md">
                <table class="custom--table table nat-tx-table">
                    <thead>
                        <tr>
                            <th>@lang('S.N.')</th>
                            <th>@lang('Reference')</th>
                            <th>@lang('Date')</th>
                            <th>@lang('Direction')</th>
                            <th>@lang('Amount')</th>
                            <th>@lang('Post Balance')</th>
                            <th>@lang('Category')</th>
                            <th>@lang('Action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $trx)
                            @php
                                $isCredit = $trx->trx_type == '+';
                                $direction = $isCredit ? __('Money In') : __('Money Out');
                                $directionLong = $isCredit ? __('Money In (Credit)') : __('Money Out (Debit)');
                                $remarkLabel = __(keyToTitle($trx->remark ?? 'general'));
                                $signedAmount = ($isCredit ? '+ ' : '- ') . showAmount($trx->amount) . ' ' . __($general->cur_text);
                            @endphp
                            <tr>
                                <td data-label="@lang('S.N.')">{{ __($loop->index + $transactions->firstItem()) }}</td>
                                <td data-label="@lang('Reference')">
                                    <span class="nat-tx-ref">{{ $trx->trx }}</span>
                                    <span class="nat-tx-sub">{{ __($trx->details) }}</span>
                                </td>
                                <td data-label="@lang('Date')">
                                    <span class="nat-tx-date">{{ showDateTime($trx->created_at) }}</span>
                                    <span class="nat-tx-sub">{{ diffForHumans($trx->created_at) }}</span>
                                </td>
                                <td data-label="@lang('Direction')">
                                    <span class="nat-tx-badge {{ $isCredit ? 'nat-tx-badge--credit' : 'nat-tx-badge--debit' }}">{{ $direction }}</span>
                                </td>
                                <td data-label="@lang('Amount')">
                                    <span class="nat-tx-amount {{ $isCredit ? 'nat-tx-amount--credit' : 'nat-tx-amount--debit' }}">{{ $signedAmount }}</span>
                                </td>
                                <td data-label="@lang('Post Balance')">{{ showAmount($trx->post_balance) }} {{ __($general->cur_text) }}</td>
                                <td data-label="@lang('Category')">{{ $remarkLabel }}</td>
                                <td data-label="@lang('Action')">
                                    <button class="nat-tx-view" type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#transactionDetailModal"
                                        data-reference="{{ $trx->trx }}"
                                        data-date="{{ showDateTime($trx->created_at) }}"
                                        data-direction="{{ $directionLong }}"
                                        data-amount="{{ $signedAmount }}"
                                        data-charge="{{ showAmount($trx->charge) }} {{ __($general->cur_text) }}"
                                        data-balance="{{ showAmount($trx->post_balance) }} {{ __($general->cur_text) }}"
                                        data-category="{{ $remarkLabel }}"
                                        data-details="{{ __($trx->details) }}">
                                        @lang('View')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($transactions->hasPages())
                <div class="nat-tx-pagination">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="transactionDetailModal" tabindex="-1" aria-labelledby="transactionDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nat-tx-modal">
                <div class="modal-header">
                    <div>
                        <span class="nat-kicker">@lang('Transaction Detail')</span>
                        <h5 class="modal-title" id="transactionDetailModalLabel">@lang('Ledger Entry')</h5>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="@lang('Close')"></button>
                </div>
                <div class="modal-body">
                    <div class="nat-tx-detail-grid">
                        <div><span>@lang('Reference')</span><strong data-tx-reference></strong></div>
                        <div><span>@lang('Date')</span><strong data-tx-date></strong></div>
                        <div><span>@lang('Direction')</span><strong data-tx-direction></strong></div>
                        <div><span>@lang('Amount')</span><strong data-tx-amount></strong></div>
                        <div><span>@lang('Charge')</span><strong data-tx-charge></strong></div>
                        <div><span>@lang('Post Balance')</span><strong data-tx-balance></strong></div>
                        <div><span>@lang('Category')</span><strong data-tx-category></strong></div>
                        <div><span>@lang('Details')</span><strong data-tx-details></strong></div>
                    </div>
                    <p class="nat-tx-note">@lang('Credit adds money to the wallet. Debit removes money from the wallet. The post balance is the wallet balance after this transaction was recorded.')</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function($) {
            "use strict";
            $('.nat-tx-view').on('click', function() {
                const data = this.dataset;
                const modal = $('#transactionDetailModal');

                modal.find('[data-tx-reference]').text(data.reference || '-');
                modal.find('[data-tx-date]').text(data.date || '-');
                modal.find('[data-tx-direction]').text(data.direction || '-');
                modal.find('[data-tx-amount]').text(data.amount || '-');
                modal.find('[data-tx-charge]').text(data.charge || '-');
                modal.find('[data-tx-balance]').text(data.balance || '-');
                modal.find('[data-tx-category]').text(data.category || '-');
                modal.find('[data-tx-details]').text(data.details || '-');
            });
        })(jQuery);
    </script>
@endpush

@push('bottom-menu')
    <li><a href="{{ route('user.profile.setting') }}">@lang('Profile')</a></li>
    <li><a href="{{ route('user.referral.users') }}">@lang('Referral')</a></li>
    <li><a href="{{ route('user.twofactor') }}">@lang('2FA Security')</a></li>
    <li><a href="{{ route('user.change.password') }}">@lang('Change Password')</a></li>
    <li><a class="active" href="{{ route('user.transaction.history') }}">@lang('Transactions')</a></li>
    <li><a class="{{ menuActive(['ticket.*']) }}" href="{{ route('ticket.index') }}">@lang('Support Tickets')</a></li>
@endpush
BLADE;

$css = <<<'CSS'

/* NATCODEV transaction workspace */
.nat-transactions-page {
    padding-bottom: 40px;
}
.nat-tx-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}
.nat-tx-header h3 {
    color: #17392a;
    font-size: 30px;
    font-weight: 800;
    line-height: 1.15;
    margin: 4px 0 6px;
}
.nat-tx-header p,
.nat-tx-help span {
    color: #5b6b62;
    margin: 0;
}
.nat-kicker {
    color: #0f6b3d;
    display: inline-block;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0;
    text-transform: uppercase;
}
.nat-tx-reset,
.nat-tx-filter-btn,
.nat-tx-view {
    align-items: center;
    background: #0f6b3d;
    border: 0;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-weight: 800;
    gap: 7px;
    justify-content: center;
    min-height: 44px;
    padding: 10px 16px;
}
.nat-tx-reset:hover,
.nat-tx-filter-btn:hover,
.nat-tx-view:hover {
    background: #0b5530;
    color: #fff;
}
.nat-tx-help,
.nat-tx-filter,
.nat-tx-table-card {
    background: #fff;
    border: 1px solid #e0e8e2;
    border-radius: 8px;
    box-shadow: 0 14px 34px rgba(23, 57, 42, 0.08);
}
.nat-tx-help {
    align-items: center;
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    padding: 14px 16px;
}
.nat-tx-help strong {
    color: #17392a;
    flex: 0 0 auto;
}
.nat-tx-filter {
    margin-bottom: 20px;
    padding: 18px;
}
.nat-tx-filter label {
    color: #314b3b;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 8px;
}
.nat-tx-table-card {
    overflow: hidden;
}
.nat-tx-table {
    margin-bottom: 0;
}
.nat-tx-table thead th {
    background: #17392a;
    color: #fff;
    font-size: 12px;
    text-transform: uppercase;
}
.nat-tx-ref,
.nat-tx-date,
.nat-tx-amount {
    color: #17392a;
    display: block;
    font-weight: 800;
}
.nat-tx-sub {
    color: #6d7b73;
    display: block;
    font-size: 12px;
    margin-top: 3px;
}
.nat-tx-badge {
    border-radius: 999px;
    display: inline-flex;
    font-size: 12px;
    font-weight: 800;
    padding: 6px 10px;
}
.nat-tx-badge--credit {
    background: #e6f6ef;
    color: #0f6b3d;
}
.nat-tx-badge--debit {
    background: #fff0ed;
    color: #b33b24;
}
.nat-tx-amount--credit {
    color: #0f8a4f;
}
.nat-tx-amount--debit {
    color: #b33b24;
}
.nat-tx-view {
    min-height: 36px;
    padding: 8px 14px;
}
.nat-tx-pagination {
    border-top: 1px solid #e8eee9;
    padding: 14px 18px;
}
.nat-tx-modal {
    border: 0;
    border-radius: 8px;
}
.nat-tx-modal .modal-header {
    border-bottom-color: #e5eee8;
}
.nat-tx-detail-grid {
    display: grid;
    gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.nat-tx-detail-grid div {
    background: #f7faf8;
    border: 1px solid #e0e8e2;
    border-radius: 8px;
    padding: 12px;
}
.nat-tx-detail-grid span {
    color: #68786e;
    display: block;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 4px;
}
.nat-tx-detail-grid strong {
    color: #17392a;
    display: block;
    font-size: 14px;
    overflow-wrap: anywhere;
}
.nat-tx-note {
    background: #f0f7f3;
    border-left: 4px solid #0f6b3d;
    color: #425548;
    margin: 14px 0 0;
    padding: 12px;
}
@media (max-width: 767px) {
    .nat-tx-header,
    .nat-tx-help {
        align-items: flex-start;
        flex-direction: column;
    }
    .nat-tx-reset {
        width: 100%;
    }
    .nat-tx-detail-grid {
        grid-template-columns: 1fr;
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
if (strpos($existingCss, '/* NATCODEV transaction workspace */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Transactions page improved.\n";
