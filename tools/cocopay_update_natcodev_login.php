<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/crystal_sky/user/auth/login.blade.php';

$blade = <<<'BLADE'
@extends($activeTemplate . 'layouts.app')
@section('app')
    @php
        $assetBase = asset('assets/images/frontend/natcodev');
    @endphp

    <style>
        :root {
            --nat-green-950: #062c1f;
            --nat-green-900: #083a28;
            --nat-green-800: #0c5136;
            --nat-green-700: #0f6f45;
            --nat-gold: #d8a846;
            --nat-gold-2: #f1cb73;
            --nat-cream: #fff9ed;
            --nat-ink: #17231d;
            --nat-muted: #657268;
            --nat-line: rgba(8, 58, 40, .14);
        }

        body {
            background: #fbfbf6;
        }

        .nat-login {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(420px, .98fr) minmax(420px, 1.02fr);
            color: var(--nat-ink);
            background: #fbfbf6;
        }

        .nat-login-media {
            position: relative;
            min-height: 100vh;
            padding: 42px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            color: #fff;
            background-image: linear-gradient(180deg, rgba(6, 44, 31, .45), rgba(6, 44, 31, .92)), url('{{ $assetBase }}/grower-registration.png');
            background-size: cover;
            background-position: center;
        }

        .nat-login-media::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 8px;
            background: linear-gradient(90deg, var(--nat-gold), #fff1b6, var(--nat-green-700));
        }

        .nat-login-brand {
            position: relative;
            z-index: 1;
            width: 118px;
            height: 118px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .94);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 20px 44px rgba(0, 0, 0, .18);
            overflow: hidden;
        }

        .nat-login-brand img {
            width: 94px;
            height: 94px;
            object-fit: contain;
            border-radius: 999px;
        }

        .nat-login-story {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }

        .nat-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 8px 13px;
            border: 1px solid rgba(241, 203, 115, .52);
            border-radius: 999px;
            color: #ffe7a5;
            background: rgba(255, 255, 255, .08);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0;
        }

        .nat-login-story h1 {
            margin: 20px 0 16px;
            color: #fff;
            font-size: clamp(38px, 5vw, 64px);
            line-height: 1;
            letter-spacing: 0;
        }

        .nat-login-story p {
            max-width: 560px;
            margin: 0;
            color: rgba(255, 255, 255, .82);
            font-size: 17px;
            line-height: 1.75;
        }

        .nat-login-points {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 28px;
        }

        .nat-login-point {
            padding: 16px;
            border: 1px solid rgba(255, 255, 255, .17);
            border-radius: 12px;
            background: rgba(255, 255, 255, .1);
            backdrop-filter: blur(12px);
        }

        .nat-login-point i {
            color: var(--nat-gold-2);
            font-size: 22px;
        }

        .nat-login-point span {
            display: block;
            margin-top: 10px;
            color: rgba(255, 255, 255, .86);
            font-size: 13px;
            line-height: 1.45;
            font-weight: 700;
        }

        .nat-login-panel {
            min-height: 100vh;
            padding: 42px min(7vw, 84px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nat-login-card {
            width: min(100%, 520px);
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 18px;
            padding: 34px;
            box-shadow: 0 28px 80px rgba(6, 44, 31, .1);
        }

        .nat-login-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 26px;
        }

        .nat-login-logo img {
            max-width: 150px;
            max-height: 74px;
            object-fit: contain;
        }

        .nat-home-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--nat-green-800);
            font-weight: 800;
            font-size: 13px;
        }

        .nat-login-card h2 {
            margin: 0 0 10px;
            color: var(--nat-green-950);
            font-size: 34px;
            line-height: 1.12;
            letter-spacing: 0;
        }

        .nat-login-card .lead {
            color: var(--nat-muted);
            line-height: 1.7;
            margin-bottom: 26px;
        }

        .nat-login-card .form--label {
            color: var(--nat-green-950);
            font-weight: 800;
            margin-bottom: 9px;
        }

        .nat-login-card .form--control {
            min-height: 52px;
            border-radius: 8px;
            border: 1px solid var(--nat-line);
            background: #fbfbf6;
            color: var(--nat-ink);
        }

        .nat-login-card .form--control:focus {
            border-color: var(--nat-gold);
            box-shadow: 0 0 0 4px rgba(216, 168, 70, .16);
            background: #fff;
        }

        .nat-login-card .btn--base {
            min-height: 52px;
            border-radius: 8px;
            border: 0;
            color: var(--nat-green-950);
            background: linear-gradient(135deg, var(--nat-gold-2), var(--nat-gold)) !important;
            font-weight: 900;
            box-shadow: 0 18px 36px rgba(216, 168, 70, .24);
        }

        .nat-login-help {
            display: grid;
            gap: 12px;
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid var(--nat-line);
            color: var(--nat-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .nat-login-help a,
        .nat-login-card a {
            color: var(--nat-green-700);
            font-weight: 800;
        }

        .nat-register-box {
            margin-top: 18px;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid rgba(216, 168, 70, .28);
            background: var(--nat-cream);
            color: var(--nat-green-950);
        }

        .nat-register-box strong {
            display: block;
            margin-bottom: 4px;
        }

        @media (max-width: 991px) {
            .nat-login {
                grid-template-columns: 1fr;
            }

            .nat-login-media {
                min-height: 520px;
            }

            .nat-login-panel {
                min-height: auto;
                padding: 28px 18px 48px;
            }
        }

        @media (max-width: 640px) {
            .nat-login-media {
                padding: 26px;
            }

            .nat-login-points {
                grid-template-columns: 1fr;
            }

            .nat-login-card {
                padding: 24px;
            }

            .nat-login-top {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <main class="nat-login">
        <section class="nat-login-media">
            <a class="nat-login-brand" href="{{ route('home') }}" aria-label="@lang('NATCODEV home')">
                <img src="{{ siteLogo('dark') }}" alt="@lang('NATCODEV Cooperative Society')">
            </a>

            <div class="nat-login-story">
                <span class="nat-eyebrow"><i class="las la-seedling"></i>@lang('NATCODEV member workspace')</span>
                <h1>@lang('Welcome back to your coconut cooperative account.')</h1>
                <p>@lang('Sign in to manage your wallet, savings, farm credit, certificate, transactions, and support tickets from one trusted member dashboard.')</p>
                <div class="nat-login-points">
                    <div class="nat-login-point"><i class="las la-wallet"></i><span>@lang('Wallet and cooperative savings records')</span></div>
                    <div class="nat-login-point"><i class="las la-certificate"></i><span>@lang('NATCODEV growers certificate profile')</span></div>
                    <div class="nat-login-point"><i class="las la-hand-holding-usd"></i><span>@lang('Farm credit and loan tracking')</span></div>
                </div>
            </div>
        </section>

        <section class="nat-login-panel">
            <div class="nat-login-card">
                <div class="nat-login-top">
                    <a class="nat-login-logo" href="{{ route('home') }}"><img src="{{ siteLogo('dark') }}" alt="@lang('NATCODEV logo')"></a>
                    <a class="nat-home-link" href="{{ route('home') }}"><i class="las la-arrow-left"></i>@lang('Back to site')</a>
                </div>

                <h2>@lang('Member Login')</h2>
                <p class="lead">@lang('Access your NATCODEV Coconut Farmers Cooperative wallet, deposits, loans, certificate, and support desk.')</p>

                <form method="POST" action="{{ route('user.login') }}" class="verify-gcaptcha">
                    @csrf
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="username" class="form--label">@lang('Username or Email')</label>
                                <input type="text" name="username" class="form--control" id="username" value="{{ old('username') }}" autocomplete="username" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="your-password" class="form--label">@lang('Password')</label>
                                <input id="your-password" type="password" class="form--control" name="password" autocomplete="current-password" required>
                            </div>
                        </div>

                        <x-captcha />

                        <div class="col-12">
                            <div class="d-flex form-group flex-wrap justify-content-between gap-2">
                                <div class="form--check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">@lang('Remember me')</label>
                                </div>
                                <a href="{{ route('user.password.request') }}" class="forgot-password">@lang('Forgot password?')</a>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <button type="submit" id="recaptcha" class="btn btn--base w-100">@lang('Sign in to member workspace')</button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="nat-register-box">
                    <strong>@lang('New cooperative member?')</strong>
                    <span>@lang('Register and upload your NATCODEV growers certificate or cooperative-issued certificate for member review.')</span>
                    <div style="margin-top:10px;">
                        <a href="{{ route('user.register') }}"><i class="las la-user-plus"></i> @lang('Create member account')</a>
                    </div>
                </div>

                <div class="nat-login-help">
                    <span>@lang('Need help accessing your account?') <a href="{{ route('contact') }}">@lang('Contact cooperative support')</a>.</span>
                    <span>@lang('For payment questions, include your Paystack or Monnify transaction reference when contacting support.')</span>
                </div>
            </div>
        </section>
    </main>
@endsection
BLADE;

file_put_contents($file, $blade);
echo "NATCODEV login page updated\n";
