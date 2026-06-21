<?php

$core = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$public = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay';

function save_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
    echo "UPDATED={$path}" . PHP_EOL;
}

$master = $core . '\\resources\\views\\admin\\layouts\\master.blade.php';
$masterContent = file_get_contents($master);
$needle = "<link rel=\"stylesheet\" href=\"{{ asset('assets/admin/css/app.css') }}\">";
$replacement = $needle . "\n    <link rel=\"stylesheet\" href=\"{{ asset('assets/admin/css/natcodev-admin.css') }}\">";
if (!str_contains($masterContent, 'natcodev-admin.css')) {
    $masterContent = str_replace($needle, $replacement, $masterContent);
    save_file($master, $masterContent);
}

$login = <<<'BLADE'
@extends('admin.layouts.master')
@section('content')
    <div class="natcodev-admin-login">
        <div class="natcodev-login-shell">
            <section class="natcodev-login-panel">
                <div class="natcodev-login-brand">
                    <img src="{{ siteLogo() }}" alt="{{ __($general->site_name) }}">
                    <span>@lang('NATCODEV Cooperative Operations')</span>
                </div>

                <div class="natcodev-login-copy">
                    <p class="eyebrow">@lang('Admin command centre')</p>
                    <h1>@lang('Manage coconut cooperative finance with confidence.')</h1>
                    <p>
                        @lang('Review members, certificates, wallet funding, withdrawals, loans, support tickets, and cooperative records from one secure workspace.')
                    </p>
                </div>

                <div class="natcodev-login-highlights">
                    <div>
                        <i class="las la-user-shield"></i>
                        <span>@lang('Member verification')</span>
                    </div>
                    <div>
                        <i class="las la-file-invoice-dollar"></i>
                        <span>@lang('Wallet and loan oversight')</span>
                    </div>
                    <div>
                        <i class="las la-seedling"></i>
                        <span>@lang('Grower certificate control')</span>
                    </div>
                </div>
            </section>

            <section class="natcodev-login-card">
                <div class="login-wrapper">
                    <div class="login-wrapper__top">
                        <p class="eyebrow">@lang('Secure access')</p>
                        <h3 class="title">@lang('Admin Login')</h3>
                        <p>@lang('Sign in to the NATCODEV Coconut Farmers Cooperative dashboard.')</p>
                    </div>
                    <div class="login-wrapper__body">
                        <form action="{{ route('admin.login') }}" method="POST" class="cmn-form verify-gcaptcha login-form">
                            @csrf
                            <div class="form-group">
                                <label>@lang('Username')</label>
                                <div class="natcodev-input">
                                    <i class="las la-user"></i>
                                    <input type="text" class="form-control" value="{{ old('username') }}" name="username" required autocomplete="username">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>@lang('Password')</label>
                                <div class="natcodev-input">
                                    <i class="las la-lock"></i>
                                    <input type="password" class="form-control" name="password" required autocomplete="current-password">
                                </div>
                            </div>
                            <x-captcha />
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="form-check me-3">
                                    <input class="form-check-input" name="remember" type="checkbox" id="remember">
                                    <label class="form-check-label" for="remember">@lang('Remember Me')</label>
                                </div>
                                <a href="{{ route('admin.password.reset') }}" class="forget-text">@lang('Forgot Password?')</a>
                            </div>
                            <button type="submit" class="btn cmn-btn w-100">
                                <i class="las la-sign-in-alt"></i>
                                @lang('Enter Dashboard')
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
BLADE;
save_file($core . '\\resources\\views\\admin\\auth\\login.blade.php', $login);

$css = <<<'CSS'
:root {
    --natcodev-forest: #063820;
    --natcodev-deep: #08281a;
    --natcodev-green: #08733f;
    --natcodev-leaf: #12a160;
    --natcodev-mint: #eaf7ef;
    --natcodev-gold: #d9a441;
    --natcodev-gold-soft: #fff3d7;
    --natcodev-ink: #17231c;
    --natcodev-muted: #6b776f;
}

body {
    background: #f5f8f4;
    color: var(--natcodev-ink);
}

.bg--dark,
.sidebar.bg--dark,
.navbar-wrapper.bg--dark {
    background: linear-gradient(180deg, var(--natcodev-deep) 0%, var(--natcodev-forest) 100%) !important;
}

.sidebar {
    box-shadow: 18px 0 42px rgba(6, 56, 32, .16);
}

.sidebar__logo {
    background: rgba(255, 255, 255, .06);
    border-bottom: 1px solid rgba(255, 255, 255, .09);
}

.sidebar__main-logo img {
    max-height: 58px;
    border-radius: 999px;
    background: #fff;
    padding: 4px;
}

.sidebar__menu .sidebar-menu-item > a {
    border-radius: 12px;
    margin: 3px 12px;
    color: rgba(255, 255, 255, .78);
}

.sidebar__menu .sidebar-menu-item.active > a,
.sidebar__menu .sidebar-menu-item > a:hover,
.sidebar__menu .sidebar-menu-item.open > a {
    background: linear-gradient(135deg, rgba(217, 164, 65, .22), rgba(18, 161, 96, .18));
    color: #fff;
    box-shadow: inset 3px 0 0 var(--natcodev-gold);
}

.sidebar__menu .menu-icon {
    color: var(--natcodev-gold);
}

.navbar-wrapper {
    border-bottom: 1px solid rgba(217, 164, 65, .2);
    box-shadow: 0 12px 30px rgba(6, 56, 32, .12);
}

.navbar-search-field {
    background: rgba(255, 255, 255, .1) !important;
    border: 1px solid rgba(255, 255, 255, .14) !important;
    color: #fff !important;
}

.navbar-search-field::placeholder {
    color: rgba(255, 255, 255, .68);
}

.navbar-search i,
.res-sidebar-open-btn,
.res-sidebar-close-btn,
.navbar-user__name,
.navbar-user .icon {
    color: #fff !important;
}

.primary--layer,
.navbar-user__thumb {
    background: rgba(255, 243, 215, .13) !important;
    border: 1px solid rgba(217, 164, 65, .34);
}

.text--primary,
.dropdown-menu__icon,
.forget-text {
    color: var(--natcodev-green) !important;
}

.btn--primary,
.cmn-btn,
.bg--primary {
    background: linear-gradient(135deg, var(--natcodev-green), var(--natcodev-forest)) !important;
    border-color: var(--natcodev-green) !important;
    color: #fff !important;
}

.btn--primary:hover,
.cmn-btn:hover {
    background: linear-gradient(135deg, var(--natcodev-forest), var(--natcodev-green)) !important;
    box-shadow: 0 12px 24px rgba(8, 115, 63, .22);
}

.btn-outline--primary {
    color: var(--natcodev-green) !important;
    border-color: rgba(8, 115, 63, .35) !important;
}

.btn-outline--primary:hover {
    background: var(--natcodev-green) !important;
    color: #fff !important;
}

.card,
.custom--card,
.table-responsive,
.dashboard-w1,
.dashboard-w2,
.dashboard-w3,
.dashboard-w4 {
    border: 1px solid rgba(8, 115, 63, .09) !important;
    border-radius: 16px !important;
    box-shadow: 0 16px 42px rgba(23, 35, 28, .06) !important;
}

.card-header {
    border-bottom-color: rgba(8, 115, 63, .1) !important;
}

.table thead th {
    background: #eef7f1 !important;
    color: var(--natcodev-forest) !important;
}

.badge.bg--success,
.menu-badge.bg--danger {
    box-shadow: 0 8px 18px rgba(217, 164, 65, .18);
}

.natcodev-admin-login {
    min-height: 100vh;
    padding: 32px 18px;
    display: flex;
    align-items: center;
    background:
        linear-gradient(120deg, rgba(6, 56, 32, .92), rgba(8, 115, 63, .76)),
        url('../images/login.jpg') center/cover no-repeat;
}

.natcodev-login-shell {
    width: min(1120px, 100%);
    margin: 0 auto;
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(360px, .8fr);
    gap: 28px;
    align-items: stretch;
}

.natcodev-login-panel,
.natcodev-login-card {
    border-radius: 26px;
    border: 1px solid rgba(255, 255, 255, .18);
    box-shadow: 0 24px 70px rgba(0, 0, 0, .28);
}

.natcodev-login-panel {
    color: #fff;
    padding: clamp(28px, 5vw, 56px);
    background:
        radial-gradient(circle at 18% 15%, rgba(217, 164, 65, .32), transparent 34%),
        linear-gradient(145deg, rgba(6, 56, 32, .82), rgba(9, 75, 43, .74));
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 620px;
}

.natcodev-login-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    font-weight: 700;
    letter-spacing: .02em;
}

.natcodev-login-brand img {
    width: 74px;
    height: 74px;
    object-fit: cover;
    border-radius: 999px;
    background: #fff;
    padding: 4px;
    box-shadow: 0 14px 35px rgba(0, 0, 0, .2);
}

.natcodev-login-copy .eyebrow,
.natcodev-login-card .eyebrow {
    margin: 0 0 10px;
    color: var(--natcodev-gold);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

.natcodev-login-copy h1 {
    max-width: 720px;
    margin: 0 0 18px;
    color: #fff;
    font-size: clamp(34px, 5vw, 58px);
    line-height: 1.03;
    font-weight: 800;
}

.natcodev-login-copy p {
    max-width: 660px;
    color: rgba(255, 255, 255, .82);
    font-size: 17px;
    line-height: 1.7;
}

.natcodev-login-highlights {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.natcodev-login-highlights div {
    min-height: 118px;
    padding: 18px;
    border-radius: 18px;
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .14);
}

.natcodev-login-highlights i {
    display: block;
    margin-bottom: 12px;
    color: var(--natcodev-gold);
    font-size: 28px;
}

.natcodev-login-highlights span {
    color: #fff;
    font-weight: 700;
}

.natcodev-login-card {
    padding: clamp(22px, 4vw, 42px);
    background: rgba(255, 255, 255, .96);
    align-self: center;
}

.natcodev-login-card .login-wrapper,
.natcodev-login-card .login-wrapper__body {
    background: transparent;
    box-shadow: none;
    padding: 0;
}

.natcodev-login-card .login-wrapper__top {
    padding: 0 0 24px;
    text-align: left;
}

.natcodev-login-card .title {
    color: var(--natcodev-forest);
    font-size: 30px;
    font-weight: 800;
}

.natcodev-login-card .login-wrapper__top p:not(.eyebrow) {
    color: var(--natcodev-muted);
}

.natcodev-login-card label {
    color: var(--natcodev-forest);
    font-weight: 700;
}

.natcodev-input {
    position: relative;
}

.natcodev-input i {
    position: absolute;
    top: 50%;
    left: 16px;
    transform: translateY(-50%);
    color: var(--natcodev-green);
    z-index: 2;
}

.natcodev-input .form-control {
    height: 52px;
    padding-left: 46px;
    border: 1px solid rgba(8, 115, 63, .18);
    border-radius: 14px;
    background: #f8fbf8;
}

.natcodev-login-card .cmn-btn {
    height: 54px;
    margin-top: 24px;
    border-radius: 14px;
    font-weight: 800;
}

@media (max-width: 991px) {
    .natcodev-login-shell {
        grid-template-columns: 1fr;
    }

    .natcodev-login-panel {
        min-height: auto;
    }
}

@media (max-width: 575px) {
    .natcodev-admin-login {
        padding: 14px;
    }

    .natcodev-login-highlights {
        grid-template-columns: 1fr;
    }
}
CSS;
save_file($public . '\\assets\\admin\\css\\natcodev-admin.css', $css);

echo 'NATCODEV_ADMIN_UI_UPDATED' . PHP_EOL;
