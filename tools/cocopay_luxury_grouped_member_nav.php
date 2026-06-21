<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$headerPath = $root . '/core/resources/views/templates/indigo_fusion/partials/header.blade.php';
$cssPath = $root . '/assets/templates/indigo_fusion/css/custom.css';

$header = <<<'BLADE'
@php
    $isMemberArea = auth()->check() && (request()->routeIs('user.*') || request()->routeIs('ticket*'));
@endphp

@if ($isMemberArea)
    @php
        $user = auth()->user();
        $displayName = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
        $displayName = $displayName ?: ($user->username ?? __('Member'));
        $initial = strtoupper(substr($displayName, 0, 1));
    @endphp

    <header class="nat-member-topbar nat-lux-topbar">
        <div class="container">
            <div class="nat-member-shell">
                <a class="nat-member-brand" href="{{ route('user.home') }}">
                    <img src="{{ siteLogo() }}" alt="@lang('NATCODEV logo')">
                </a>

                <button class="nat-member-toggle" data-bs-toggle="collapse" data-bs-target="#natMemberNavigation" type="button" aria-controls="natMemberNavigation" aria-expanded="false" aria-label="@lang('Toggle navigation')">
                    <i class="las la-bars"></i>
                </button>

                <div class="collapse nat-member-collapse" id="natMemberNavigation">
                    <ul class="nat-member-nav nat-lux-nav">
                        <li>
                            <a class="{{ menuActive('user.home') }}" href="{{ route('user.home') }}">
                                <i class="las la-th-large"></i><span>@lang('Dashboard')</span>
                            </a>
                        </li>

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.deposit*', 'user.withdraw*', 'user.transaction.history']) }}" href="#" id="walletMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-wallet"></i><span>@lang('Wallet')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="walletMenu">
                                @if ($general->modules->deposit)
                                    <li><a class="dropdown-item" href="{{ route('user.deposit.history') }}"><i class="las la-plus-circle"></i>@lang('Deposits')</a></li>
                                @endif
                                @if ($general->modules->withdraw)
                                    <li><a class="dropdown-item" href="{{ route('user.withdraw.history') }}"><i class="las la-money-check"></i>@lang('Withdrawals')</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('user.transaction.history') }}"><i class="las la-receipt"></i>@lang('Transactions')</a></li>
                            </ul>
                        </li>

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.fdr*', 'user.dps*', 'user.loan*']) }}" href="#" id="growthMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-seedling"></i><span>@lang('Growth')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="growthMenu">
                                @if ($general->modules->loan)
                                    <li><a class="dropdown-item" href="{{ route('user.loan.plans') }}"><i class="las la-hand-holding-usd"></i>@lang('Farm Loans')</a></li>
                                @endif
                                @if ($general->modules->dps)
                                    <li><a class="dropdown-item" href="{{ route('user.dps.plans') }}"><i class="las la-calendar-check"></i>@lang('Monthly Savings')</a></li>
                                @endif
                                @if ($general->modules->fdr)
                                    <li><a class="dropdown-item" href="{{ route('user.fdr.plans') }}"><i class="las la-lock"></i>@lang('Harvest Fixed Savings')</a></li>
                                @endif
                            </ul>
                        </li>

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.airtime*', 'user.transfer*', 'user.beneficiary.*']) }}" href="#" id="servicesMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-layer-group"></i><span>@lang('Services')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="servicesMenu">
                                @if ($general->modules->own_bank)
                                    <li><a class="dropdown-item" href="{{ route('user.beneficiary.own') }}"><i class="las la-exchange-alt"></i>@lang('Transfer')</a></li>
                                @elseif($general->modules->other_bank)
                                    <li><a class="dropdown-item" href="{{ route('user.beneficiary.other') }}"><i class="las la-exchange-alt"></i>@lang('Transfer')</a></li>
                                @elseif($general->modules->wire_transfer)
                                    <li><a class="dropdown-item" href="{{ route('user.transfer.wire.index') }}"><i class="las la-exchange-alt"></i>@lang('Transfer')</a></li>
                                @endif
                                @if (@$general->modules->airtime)
                                    <li><a class="dropdown-item" href="{{ route('user.airtime.form') }}"><i class="las la-mobile"></i>@lang('Airtime & Bills')</a></li>
                                @endif
                            </ul>
                        </li>

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.profile.setting', 'user.twofactor', 'user.change.password', 'user.referral.*', 'ticket', 'ticket.open', 'ticket.view']) }}" href="#" id="memberMoreMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-ellipsis-h"></i><span>@lang('More')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="memberMoreMenu">
                                <li><a class="dropdown-item" href="{{ route('user.profile.setting') }}"><i class="las la-user-circle"></i>@lang('Profile')</a></li>
                                <li><a class="dropdown-item" href="{{ route('user.referral.users') }}"><i class="las la-user-friends"></i>@lang('Referral')</a></li>
                                <li><a class="dropdown-item" href="{{ route('user.twofactor') }}"><i class="las la-shield-alt"></i>@lang('2FA Security')</a></li>
                                <li><a class="dropdown-item" href="{{ route('user.change.password') }}"><i class="las la-lock"></i>@lang('Password')</a></li>
                                <li><a class="dropdown-item" href="{{ route('ticket.index') }}"><i class="las la-headset"></i>@lang('Support')</a></li>
                            </ul>
                        </li>
                    </ul>

                    <div class="nat-member-actions nat-lux-actions">
                        @if ($general->multi_language)
                            @php $language = App\Models\Language::all(); @endphp
                            <select class="nat-language-select langSel">
                                @foreach ($language as $item)
                                    <option value="{{ $item->code }}" @if (session('lang') == $item->code) selected @endif>{{ __($item->name) }}</option>
                                @endforeach
                            </select>
                        @endif

                        <a class="nat-profile-chip" href="{{ route('user.profile.setting') }}">
                            <span>{{ $initial }}</span>
                            <small>{{ __($displayName) }}</small>
                        </a>

                        <a class="nat-logout-btn" href="{{ route('user.logout') }}">
                            <i class="las la-sign-out-alt"></i><span>@lang('Logout')</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
@else
    <header class="header">
        <div class="header__bottom">
            <div class="container">
                <nav class="navbar navbar-expand-lg align-items-center justify-content-between p-0">
                    <a class="site-logo site-title" href="{{ route('home') }}">
                        <img src="{{ siteLogo() }}" alt="logo">
                    </a>
                    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" type="button" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="menu-toggle"></span>
                    </button>
                    <div class="collapse navbar-collapse mt-xl-0 mt-3" id="navbarSupportedContent">
                        <ul class="navbar-nav main-menu m-auto" id="linkItem">
                            @if (!request()->routeIs('user.*') || !auth()->user())
                                @include($activeTemplate . 'partials.guest_header')
                            @else
                                @include($activeTemplate . 'partials.auth_header')
                            @endif
                        </ul>

                        <div class="nav-right">
                            @if ($general->multi_language)
                                @php $language = App\Models\Language::all(); @endphp
                                <select class="language-select me-3 langSel">
                                    @foreach ($language as $item)
                                        <option value="{{ $item->code }}" @if (session('lang') == $item->code) selected @endif>{{ __($item->name) }}</option>
                                    @endforeach
                                </select>
                            @endif

                            @guest
                                <a class="btn btn-sm header-base-button me-3 py-2" href="{{ route('user.login') }}">@lang('Sign In')</a>
                                <a class="btn btn-sm btn--base py-2 text-white" href="{{ route('user.register') }}">@lang('Sign Up')</a>
                            @else
                                <a class="btn btn-sm btn--base py-2 text-white logout-btn" href="{{ route('user.logout') }}">@lang('Logout')</a>
                            @endguest
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
@endif
BLADE;

$css = <<<'CSS'

/* NATCODEV luxury vivid member theme */
:root {
    --nat-lux-forest: #082c20;
    --nat-lux-emerald: #087a45;
    --nat-lux-green: #0f8f55;
    --nat-lux-mint: #e9f7ef;
    --nat-lux-gold: #c99a2e;
    --nat-lux-gold-soft: #f7e6b5;
    --nat-lux-ink: #14251d;
}
.nat-lux-topbar {
    background: rgba(255, 255, 255, 0.985);
    border-bottom: 1px solid rgba(201, 154, 46, 0.26);
    box-shadow: 0 18px 42px rgba(8, 44, 32, 0.12);
}
.nat-member-brand img {
    border-radius: 50%;
    height: 58px;
    max-width: 58px;
    object-fit: cover;
}
.nat-lux-nav {
    background: #f7fbf8;
    border: 1px solid #e0ebe3;
    border-radius: 8px;
    padding: 5px;
}
.nat-lux-nav > li > a {
    border-radius: 8px;
    color: var(--nat-lux-ink);
    min-height: 42px;
    padding: 9px 14px;
}
.nat-lux-nav > li > a i {
    color: var(--nat-lux-emerald);
}
.nat-lux-nav > li > a:hover,
.nat-lux-nav > li > a.active,
.nat-lux-nav > li > a.show {
    background: linear-gradient(135deg, #e9f7ef 0%, #fff8e4 100%);
    border-color: rgba(201, 154, 46, 0.35);
    color: var(--nat-lux-forest);
}
.nat-lux-nav > li > a.active i,
.nat-lux-nav > li > a.show i {
    color: var(--nat-lux-gold);
}
.nat-lux-dropdown {
    border: 1px solid rgba(201, 154, 46, 0.26);
    box-shadow: 0 22px 50px rgba(8, 44, 32, 0.18);
}
.nat-lux-dropdown .dropdown-item i {
    color: var(--nat-lux-gold);
}
.nat-lux-actions .nat-language-select,
.nat-lux-actions .nat-profile-chip {
    border-color: rgba(201, 154, 46, 0.22);
}
.nat-lux-actions .nat-profile-chip span {
    background: linear-gradient(135deg, var(--nat-lux-emerald), var(--nat-lux-forest));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.18);
}
.nat-lux-actions .nat-logout-btn {
    background: linear-gradient(135deg, var(--nat-lux-forest), #123f2e);
    border: 1px solid rgba(201, 154, 46, 0.35);
}
.nat-member-page-title {
    background:
        radial-gradient(circle at 82% 20%, rgba(201, 154, 46, 0.24), transparent 30%),
        linear-gradient(135deg, #082c20 0%, #087a45 58%, #0f8f55 100%) !important;
}
.nat-member-balance-pill,
.nat-member-next-actions a {
    border-color: rgba(247, 230, 181, 0.34) !important;
}
.nat-member-balance-pill strong {
    color: #fff7da !important;
}
.nat-member-next-actions a:hover,
.nat-member-next-actions a.active {
    color: #082c20 !important;
}
.natfin-wallet-card {
    background:
        radial-gradient(circle at 82% 12%, rgba(247, 230, 181, 0.25), transparent 30%),
        linear-gradient(135deg, #087a45 0%, #082c20 62%, #173b70 100%) !important;
    border: 1px solid rgba(201, 154, 46, 0.22);
}
.natfin-wallet-actions a:first-child,
.natfin-referral button,
.natfin-alert a {
    background: linear-gradient(135deg, #fff4cf, #c99a2e) !important;
    color: #10251b !important;
}
.natfin-side-nav a.active,
.natfin-side-nav a:hover,
.natfin-actions a:hover,
.natfin-panel-head a,
.natfin-card-head a,
.natfin-insight a,
.nat-tx-panel-head a {
    color: var(--nat-lux-emerald) !important;
}
.natfin-actions a:hover,
.natfin-metrics a:hover,
.natfin-summary-card:hover,
.natfin-panel:hover,
.natfin-card:hover {
    border-color: rgba(201, 154, 46, 0.36);
    box-shadow: 0 18px 44px rgba(8, 44, 32, 0.12);
}
.natfin-mini-card,
.natfin-insight,
.footer-area,
.footer,
footer {
    background: #082c20 !important;
}
@media (min-width: 1200px) {
    .nat-lux-nav {
        flex-wrap: nowrap;
    }
}
@media (max-width: 1199px) {
    .nat-lux-nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        width: 100%;
    }
    .nat-lux-nav > li > a {
        justify-content: center;
        width: 100%;
    }
}
CSS;

if (!is_file($headerPath)) {
    throw new RuntimeException("Missing header view: {$headerPath}");
}
if (!is_file($cssPath)) {
    throw new RuntimeException("Missing CSS: {$cssPath}");
}

file_put_contents($headerPath, $header);

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV luxury vivid member theme */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Luxury grouped member navigation applied.\n";
