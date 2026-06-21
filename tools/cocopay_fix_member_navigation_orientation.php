<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$breadcrumbPath = $root . '/core/resources/views/templates/indigo_fusion/partials/breadcrumb.blade.php';
$cssPath = $root . '/assets/templates/indigo_fusion/css/custom.css';

$breadcrumb = <<<'BLADE'
@if (!request()->routeIs('home'))
    @if (auth()->check() && (request()->routeIs('user.*') || request()->routeIs('ticket*')))
        @php
            $member = auth()->user();
            $memberName = trim(($member->firstname ?? '') . ' ' . ($member->lastname ?? ''));
            $memberName = $memberName ?: ($member->username ?? __('Member'));
        @endphp
        <section class="nat-member-page-title">
            <div class="container">
                <div class="nat-member-crumbs">
                    <a href="{{ route('user.home') }}"><i class="las la-home"></i>@lang('Dashboard')</a>
                    <i class="las la-angle-right"></i>
                    <span>{{ __($pageTitle) }}</span>
                </div>

                <div class="nat-member-page-title__inner">
                    <div>
                        <span>@lang('You are in the member workspace')</span>
                        <h1>{{ __($pageTitle) }}</h1>
                    </div>
                    <div class="nat-member-balance-pill">
                        <small>@lang('Available Balance')</small>
                        <strong>{{ showAmount($member->balance) }} {{ __($general->cur_text) }}</strong>
                    </div>
                </div>

                <div class="nat-member-next-actions">
                    <a href="{{ route('user.home') }}"><i class="las la-th-large"></i>@lang('Overview')</a>
                    <a href="{{ route('user.transaction.history') }}"><i class="las la-receipt"></i>@lang('Transactions')</a>
                    @if (@$general->modules->deposit)
                        <a class="{{ menuActive('user.deposit*') }}" href="{{ route('user.deposit.history') }}"><i class="las la-plus-circle"></i>@lang('Deposits')</a>
                    @endif
                    @if (@$general->modules->withdraw)
                        <a class="{{ menuActive('user.withdraw*') }}" href="{{ route('user.withdraw.history') }}"><i class="las la-wallet"></i>@lang('Withdrawals')</a>
                    @endif
                    @if (@$general->modules->loan)
                        <a class="{{ menuActive('user.loan*') }}" href="{{ route('user.loan.plans') }}"><i class="las la-hand-holding-usd"></i>@lang('Loans')</a>
                    @endif
                </div>
            </div>
        </section>
    @else
        @php $breadCumImage = getContent('breadcrumb.content', true); @endphp

        <section class="inner-hero bg_img overlay--one" style="background-image: url('{{ getImage('assets/images/frontend/breadcrumb/' . @$breadCumImage->data_values->image, '1920x1280') }}');">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-6 text-center">
                        <h2 class="page-title text-white">{{ __($pageTitle) }}</h2>
                    </div>
                </div>
            </div>
        </section>
    @endif
@endif
BLADE;

$css = <<<'CSS'

/* NATCODEV member navigation orientation fix */
@media (min-width: 1200px) {
    .nat-member-topbar .nat-member-collapse.collapse:not(.show) {
        display: flex !important;
    }
}
.nat-member-crumbs {
    align-items: center;
    color: rgba(255, 255, 255, 0.72);
    display: flex;
    flex-wrap: wrap;
    font-size: 13px;
    font-weight: 800;
    gap: 8px;
    margin-bottom: 14px;
}
.nat-member-crumbs a,
.nat-member-crumbs span,
.nat-member-crumbs i {
    color: rgba(255, 255, 255, 0.86);
}
.nat-member-crumbs a {
    align-items: center;
    display: inline-flex;
    gap: 6px;
}
.nat-member-crumbs a:hover {
    color: #fff;
}
.nat-member-next-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 18px;
}
.nat-member-next-actions a {
    align-items: center;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-size: 13px;
    font-weight: 900;
    gap: 7px;
    min-height: 38px;
    padding: 8px 12px;
}
.nat-member-next-actions a:hover,
.nat-member-next-actions a.active {
    background: #fff;
    color: #0f6b3d;
}
.nat-member-next-actions a i {
    font-size: 17px;
}
@media (max-width: 1199px) {
    .nat-member-topbar .nat-member-collapse.collapse:not(.show) {
        display: none !important;
    }
    .nat-member-topbar .nat-member-collapse.collapse.show {
        display: flex !important;
    }
}
@media (max-width: 575px) {
    .nat-member-next-actions a {
        flex: 1 1 calc(50% - 8px);
        justify-content: center;
    }
}
CSS;

if (!is_file($breadcrumbPath)) {
    throw new RuntimeException("Missing breadcrumb view: {$breadcrumbPath}");
}
if (!is_file($cssPath)) {
    throw new RuntimeException("Missing stylesheet: {$cssPath}");
}

file_put_contents($breadcrumbPath, $breadcrumb);

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV member navigation orientation fix */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Member navigation/orientation fix applied.\n";
