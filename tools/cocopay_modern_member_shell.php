<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$headerPath = $root . '/resources/views/templates/indigo_fusion/partials/header.blade.php';
$breadcrumbPath = $root . '/resources/views/templates/indigo_fusion/partials/breadcrumb.blade.php';
$cssPath = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/templates/indigo_fusion/css/custom.css';

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

    <header class="nat-member-topbar">
        <div class="container">
            <div class="nat-member-shell">
                <a class="nat-member-brand" href="{{ route('user.home') }}">
                    <img src="{{ siteLogo() }}" alt="@lang('NATCODEV logo')">
                    <span>
                        <strong>@lang('NATCODEV')</strong>
                        <small>@lang('Coconut Farmers Co-op')</small>
                    </span>
                </a>

                <button class="nat-member-toggle" data-bs-toggle="collapse" data-bs-target="#natMemberNavigation" type="button" aria-controls="natMemberNavigation" aria-expanded="false" aria-label="@lang('Toggle navigation')">
                    <i class="las la-bars"></i>
                </button>

                <div class="collapse nat-member-collapse" id="natMemberNavigation">
                    <ul class="nat-member-nav">
                        <li>
                            <a class="{{ menuActive('user.home') }}" href="{{ route('user.home') }}">
                                <i class="las la-th-large"></i><span>@lang('Dashboard')</span>
                            </a>
                        </li>

                        @if ($general->modules->deposit)
                            <li>
                                <a class="{{ menuActive('user.deposit*') }}" href="{{ route('user.deposit.history') }}">
                                    <i class="las la-plus-circle"></i><span>@lang('Deposit')</span>
                                </a>
                            </li>
                        @endif

                        @if ($general->modules->withdraw)
                            <li>
                                <a class="{{ menuActive('user.withdraw*') }}" href="{{ route('user.withdraw.history') }}">
                                    <i class="las la-wallet"></i><span>@lang('Withdraw')</span>
                                </a>
                            </li>
                        @endif

                        @if ($general->modules->fdr)
                            <li>
                                <a class="{{ menuActive('user.fdr*') }}" href="{{ route('user.fdr.plans') }}">
                                    <i class="las la-seedling"></i><span>@lang('FDR')</span>
                                </a>
                            </li>
                        @endif

                        @if ($general->modules->dps)
                            <li>
                                <a class="{{ menuActive('user.dps*') }}" href="{{ route('user.dps.plans') }}">
                                    <i class="las la-calendar-check"></i><span>@lang('DPS')</span>
                                </a>
                            </li>
                        @endif

                        @if ($general->modules->loan)
                            <li>
                                <a class="{{ menuActive('user.loan*') }}" href="{{ route('user.loan.plans') }}">
                                    <i class="las la-hand-holding-usd"></i><span>@lang('Loan')</span>
                                </a>
                            </li>
                        @endif

                        @if (@$general->modules->airtime)
                            <li>
                                <a class="{{ menuActive('user.airtime*') }}" href="{{ route('user.airtime.form') }}">
                                    <i class="las la-mobile"></i><span>@lang('Airtime')</span>
                                </a>
                            </li>
                        @endif

                        @if ($general->modules->own_bank || $general->modules->other_bank || $general->modules->wire_transfer)
                            <li>
                                @if ($general->modules->own_bank)
                                    <a class="{{ menuActive(['user.transfer*', 'user.beneficiary.*']) }}" href="{{ route('user.beneficiary.own') }}">
                                        <i class="las la-exchange-alt"></i><span>@lang('Transfer')</span>
                                    </a>
                                @elseif($general->modules->other_bank)
                                    <a class="{{ menuActive(['user.transfer*', 'user.beneficiary.*']) }}" href="{{ route('user.beneficiary.other') }}">
                                        <i class="las la-exchange-alt"></i><span>@lang('Transfer')</span>
                                    </a>
                                @else
                                    <a class="{{ menuActive(['user.transfer*']) }}" href="{{ route('user.transfer.wire.index') }}">
                                        <i class="las la-exchange-alt"></i><span>@lang('Transfer')</span>
                                    </a>
                                @endif
                            </li>
                        @endif

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.profile.setting', 'user.twofactor', 'user.change.password', 'user.transaction.history', 'ticket', 'ticket.open', 'ticket.view']) }}" href="#" id="memberMoreMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-ellipsis-h"></i><span>@lang('More')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown" aria-labelledby="memberMoreMenu">
                                <li><a class="dropdown-item" href="{{ route('user.profile.setting') }}"><i class="las la-user-circle"></i>@lang('Profile')</a></li>
                                <li><a class="dropdown-item" href="{{ route('user.transaction.history') }}"><i class="las la-receipt"></i>@lang('Transactions')</a></li>
                                <li><a class="dropdown-item" href="{{ route('user.change.password') }}"><i class="las la-lock"></i>@lang('Password')</a></li>
                                <li><a class="dropdown-item" href="{{ route('ticket.index') }}"><i class="las la-headset"></i>@lang('Support')</a></li>
                            </ul>
                        </li>
                    </ul>

                    <div class="nat-member-actions">
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
                            @if (auth()->user() && request()->routeIs('ticket*'))
                                @include($activeTemplate . 'partials.auth_header')
                            @elseif (!request()->routeIs('user.*') || !auth()->user())
                                @include($activeTemplate . 'partials.guest_header')
                            @else
                                @include($activeTemplate . 'partials.auth_header')
                            @endif
                        </ul>

                        <div class="nav-right">
                            @if ($general->multi_language)
                                @php
                                    $language = App\Models\Language::all();
                                @endphp
                                <select class="language-select me-3 langSel">
                                    @foreach ($language as $item)
                                        <option value="{{ $item->code }}" @if (session('lang') == $item->code) selected @endif>{{ __($item->name) }}</option>
                                    @endforeach
                                </select>
                            @endif

                            @if (auth()->user() && !request()->routeIs('user.*'))
                                <a class="btn btn-sm header-base-button me-3 py-2" href="{{ route('user.home') }}">@lang('Dashboard')</a>
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
                <div class="nat-member-page-title__inner">
                    <div>
                        <span>@lang('NATCODEV member workspace')</span>
                        <h1>{{ __($pageTitle) }}</h1>
                    </div>
                    <div class="nat-member-balance-pill">
                        <small>@lang('Available Balance')</small>
                        <strong>{{ showAmount($member->balance) }} {{ __($general->cur_text) }}</strong>
                    </div>
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

/* NATCODEV modern member shell */
.nat-member-topbar {
    background: rgba(255, 255, 255, 0.96);
    border-bottom: 1px solid #e1e9e3;
    box-shadow: 0 12px 28px rgba(23, 57, 42, 0.08);
    position: sticky;
    top: 0;
    z-index: 999;
}
.nat-member-shell {
    align-items: center;
    display: flex;
    gap: 18px;
    min-height: 82px;
}
.nat-member-brand {
    align-items: center;
    color: #17392a;
    display: inline-flex;
    flex: 0 0 auto;
    gap: 10px;
}
.nat-member-brand:hover {
    color: #0f6b3d;
}
.nat-member-brand img {
    height: 52px;
    max-width: 178px;
    object-fit: contain;
}
.nat-member-brand span {
    display: none;
    line-height: 1.05;
}
.nat-member-brand strong,
.nat-member-brand small {
    display: block;
}
.nat-member-brand strong {
    font-size: 16px;
    font-weight: 900;
}
.nat-member-brand small {
    color: #6a7b70;
    font-size: 11px;
    font-weight: 700;
}
.nat-member-toggle {
    align-items: center;
    background: #f0f7f3;
    border: 1px solid #dbe8df;
    border-radius: 8px;
    color: #0f6b3d;
    display: none;
    font-size: 24px;
    height: 44px;
    justify-content: center;
    margin-left: auto;
    width: 48px;
}
.nat-member-collapse {
    align-items: center;
    display: flex;
    flex: 1 1 auto;
    gap: 16px;
    justify-content: space-between;
}
.nat-member-nav {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
    list-style: none;
    margin: 0;
    padding: 0;
}
.nat-member-nav > li > a {
    align-items: center;
    border: 1px solid transparent;
    border-radius: 8px;
    color: #344b3d;
    display: inline-flex;
    font-size: 13px;
    font-weight: 800;
    gap: 7px;
    min-height: 40px;
    padding: 9px 10px;
    white-space: nowrap;
}
.nat-member-nav > li > a i {
    color: #0f6b3d;
    font-size: 18px;
}
.nat-member-nav > li > a:hover,
.nat-member-nav > li > a.active {
    background: #eaf6ef;
    border-color: #cfe6d8;
    color: #0f6b3d;
}
.nat-member-dropdown {
    border: 1px solid #dfe9e3;
    border-radius: 8px;
    box-shadow: 0 16px 40px rgba(23, 57, 42, 0.14);
    min-width: 210px;
    padding: 8px;
}
.nat-member-dropdown .dropdown-item {
    align-items: center;
    border-radius: 8px;
    color: #314b3b;
    display: flex;
    font-weight: 700;
    gap: 9px;
    padding: 10px 12px;
}
.nat-member-dropdown .dropdown-item:hover {
    background: #eff8f2;
    color: #0f6b3d;
}
.nat-member-actions {
    align-items: center;
    display: flex;
    flex: 0 0 auto;
    gap: 10px;
}
.nat-language-select {
    background: #fff;
    border: 1px solid #dce7e0;
    border-radius: 8px;
    color: #314b3b;
    font-size: 13px;
    font-weight: 800;
    min-height: 40px;
    padding: 0 10px;
}
.nat-profile-chip {
    align-items: center;
    background: #f7faf8;
    border: 1px solid #dfe9e3;
    border-radius: 8px;
    color: #17392a;
    display: inline-flex;
    gap: 8px;
    min-height: 42px;
    padding: 6px 10px 6px 7px;
}
.nat-profile-chip span {
    align-items: center;
    background: #0f6b3d;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-size: 14px;
    font-weight: 900;
    height: 30px;
    justify-content: center;
    width: 30px;
}
.nat-profile-chip small {
    color: #17392a;
    font-size: 12px;
    font-weight: 900;
    max-width: 92px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.nat-logout-btn {
    align-items: center;
    background: #17392a;
    border-radius: 8px;
    color: #fff;
    display: inline-flex;
    font-size: 13px;
    font-weight: 900;
    gap: 7px;
    min-height: 42px;
    padding: 9px 13px;
}
.nat-logout-btn:hover {
    background: #0f6b3d;
    color: #fff;
}
.nat-member-page-title {
    background: linear-gradient(135deg, #123829 0%, #0f6b3d 72%);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    color: #fff;
    padding: 28px 0;
}
.nat-member-page-title__inner {
    align-items: center;
    display: flex;
    justify-content: space-between;
    gap: 18px;
}
.nat-member-page-title span {
    color: rgba(255, 255, 255, 0.78);
    display: block;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.nat-member-page-title h1 {
    color: #fff;
    font-size: 30px;
    font-weight: 900;
    line-height: 1.15;
    margin: 0;
}
.nat-member-balance-pill {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    min-width: 190px;
    padding: 12px 14px;
}
.nat-member-balance-pill small,
.nat-member-balance-pill strong {
    color: #fff;
    display: block;
}
.nat-member-balance-pill small {
    font-size: 12px;
    font-weight: 700;
    opacity: 0.78;
}
.nat-member-balance-pill strong {
    font-size: 20px;
    font-weight: 900;
}
.nat-member-topbar + .main-wrapper section.pt-100 {
    padding-top: 44px !important;
}
.nat-member-topbar + .main-wrapper .bottom-menu-section {
    background: #f7faf8 !important;
    border-bottom: 1px solid #e1e9e3;
    border-top: 1px solid #e1e9e3;
    padding: 10px 0 !important;
}
.nat-member-topbar + .main-wrapper .bottom-menu ul {
    gap: 8px;
}
.nat-member-topbar + .main-wrapper .bottom-menu ul li a {
    background: #fff;
    border: 1px solid #dfe9e3;
    border-radius: 8px;
    color: #314b3b !important;
    font-weight: 800;
    padding: 9px 14px !important;
}
.nat-member-topbar + .main-wrapper .bottom-menu ul li a:hover,
.nat-member-topbar + .main-wrapper .bottom-menu ul li a.active {
    background: #eaf6ef;
    border-color: #cfe6d8;
    color: #0f6b3d !important;
}
@media (max-width: 1199px) {
    .nat-member-shell {
        flex-wrap: wrap;
        min-height: 74px;
        padding: 10px 0;
    }
    .nat-member-toggle {
        display: inline-flex;
    }
    .nat-member-collapse {
        align-items: stretch;
        background: #fff;
        border-top: 1px solid #e5eee8;
        flex-basis: 100%;
        flex-direction: column;
        gap: 12px;
        padding: 14px 0 6px;
    }
    .nat-member-collapse:not(.show) {
        display: none;
    }
    .nat-member-nav {
        align-items: stretch;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .nat-member-nav > li > a {
        justify-content: center;
        width: 100%;
    }
    .nat-member-actions {
        flex-wrap: wrap;
        justify-content: space-between;
    }
}
@media (max-width: 767px) {
    .nat-member-brand img {
        height: 46px;
        max-width: 156px;
    }
    .nat-member-nav {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .nat-member-actions > * {
        flex: 1 1 auto;
    }
    .nat-profile-chip {
        justify-content: center;
    }
    .nat-logout-btn {
        justify-content: center;
    }
    .nat-member-page-title {
        padding: 22px 0;
    }
    .nat-member-page-title__inner {
        align-items: flex-start;
        flex-direction: column;
    }
    .nat-member-page-title h1 {
        font-size: 25px;
    }
    .nat-member-balance-pill {
        width: 100%;
    }
}
CSS;

foreach ([$headerPath, $breadcrumbPath, $cssPath] as $path) {
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }
}

file_put_contents($headerPath, $header);
file_put_contents($breadcrumbPath, $breadcrumb);

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV modern member shell */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Modern member shell applied.\n";
