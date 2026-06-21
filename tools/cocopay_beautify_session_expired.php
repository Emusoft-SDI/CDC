<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\errors\\session_expired.blade.php';

$view = <<<'BLADE'
@php
    $targetRoute = route('home');
    $targetLabel = 'Return to Cooperative Home';
    $supportRoute = route('contact');

    if (request()->is('admin') || request()->is('admin/*')) {
        $targetRoute = route('admin.login');
        $targetLabel = 'Return to Admin Login';
    } elseif (request()->is('user') || request()->is('user/*')) {
        $targetRoute = route('user.login');
        $targetLabel = 'Return to Member Login';
    } elseif (request()->is('staff') || request()->is('staff/*')) {
        $targetRoute = route('staff.login');
        $targetLabel = 'Return to Branch Staff Login';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $general->siteName($pageTitle ?? 'Session Expired | NATCODEV Cooperative') }}</title>
    <link rel="shortcut icon" type="image/png" href="{{ siteLogo() }}">
    <link rel="stylesheet" href="{{ asset('assets/global/css/bootstrap.min.css') }}">
    <style>
        :root {
            --natcodev-green: #075f38;
            --natcodev-deep: #062c20;
            --natcodev-gold: #c8942f;
            --natcodev-mint: #eef8f0;
            --natcodev-ink: #182720;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--natcodev-ink);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 12% 18%, rgba(200, 148, 47, 0.22), transparent 28%),
                linear-gradient(135deg, #f7fbf5 0%, #edf6ee 48%, #ffffff 100%);
        }

        .session-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 40px 16px;
            position: relative;
            overflow: hidden;
        }

        .session-page::before,
        .session-page::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .session-page::before {
            width: 420px;
            height: 420px;
            right: -160px;
            top: -170px;
            border: 60px solid rgba(7, 95, 56, 0.08);
        }

        .session-page::after {
            width: 540px;
            height: 540px;
            left: -240px;
            bottom: -260px;
            background: rgba(7, 95, 56, 0.06);
        }

        .session-card {
            width: min(100%, 980px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 0.95fr 1.25fr;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(7, 95, 56, 0.12);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 28px 80px rgba(6, 44, 32, 0.16);
            position: relative;
            z-index: 1;
        }

        .session-brand {
            background:
                linear-gradient(160deg, rgba(6, 44, 32, 0.96), rgba(7, 95, 56, 0.92)),
                url("{{ asset('assets/images/frontend/natcodev/home-hero.png') }}");
            background-size: cover;
            background-position: center;
            color: #ffffff;
            padding: 44px 36px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 520px;
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-lockup img {
            width: 62px;
            height: 62px;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.92);
            padding: 6px;
        }

        .brand-lockup span {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.72);
            font-weight: 700;
        }

        .brand-lockup strong {
            display: block;
            font-size: 18px;
            line-height: 1.25;
        }

        .session-note {
            border-left: 3px solid var(--natcodev-gold);
            padding-left: 18px;
        }

        .session-note p {
            margin: 0;
            max-width: 320px;
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.7;
            font-size: 15px;
        }

        .session-content {
            padding: 58px 54px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .status-pill {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(200, 148, 47, 0.32);
            background: #fff8e8;
            color: #71521d;
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--natcodev-gold);
            box-shadow: 0 0 0 5px rgba(200, 148, 47, 0.16);
        }

        h1 {
            margin: 24px 0 16px;
            color: var(--natcodev-deep);
            font-size: clamp(34px, 5vw, 54px);
            line-height: 1.02;
            font-weight: 900;
            letter-spacing: 0;
        }

        .lead {
            max-width: 580px;
            margin: 0;
            color: #55645d;
            font-size: 17px;
            line-height: 1.8;
        }

        .session-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 34px;
        }

        .primary-action,
        .secondary-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 0 22px;
            border-radius: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .primary-action {
            background: linear-gradient(135deg, var(--natcodev-green), #0a7a48);
            color: #ffffff;
            box-shadow: 0 14px 28px rgba(7, 95, 56, 0.22);
        }

        .secondary-action {
            color: var(--natcodev-green);
            background: #f2f8f2;
            border: 1px solid rgba(7, 95, 56, 0.12);
        }

        .primary-action:hover,
        .secondary-action:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }

        .primary-action:hover {
            color: #ffffff;
            box-shadow: 0 18px 34px rgba(7, 95, 56, 0.28);
        }

        .secondary-action:hover {
            color: var(--natcodev-green);
            background: #e8f3e9;
        }

        .safe-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 34px;
        }

        .safe-item {
            background: var(--natcodev-mint);
            border: 1px solid rgba(7, 95, 56, 0.1);
            border-radius: 16px;
            padding: 15px;
            min-height: 92px;
        }

        .safe-item strong {
            display: block;
            color: var(--natcodev-deep);
            font-size: 14px;
            margin-bottom: 6px;
        }

        .safe-item span {
            display: block;
            color: #607269;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 860px) {
            .session-card {
                grid-template-columns: 1fr;
                border-radius: 22px;
            }

            .session-brand {
                min-height: 250px;
                padding: 30px 24px;
            }

            .session-content {
                padding: 36px 24px;
            }

            .safe-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="session-page">
        <section class="session-card" aria-labelledby="session-title">
            <aside class="session-brand">
                <div class="brand-lockup">
                    <img src="{{ siteLogo() }}" alt="NATCODEV">
                    <div>
                        <span>NATCODEV</span>
                        <strong>Coconut Farmers Cooperative</strong>
                    </div>
                </div>
                <div class="session-note">
                    <p>Your account is protected when a page sits idle for too long. Sign in again to continue your cooperative work securely.</p>
                </div>
            </aside>

            <div class="session-content">
                <div class="status-pill"><span class="status-dot"></span> Secure pause</div>
                <h1 id="session-title">@lang('Session Expired')</h1>
                <p class="lead">
                    @lang('For your safety, this workspace paused because the form or page was left idle. Please sign in again and continue from your dashboard.')
                </p>

                <div class="session-actions">
                    <a href="{{ $targetRoute }}" class="primary-action">@lang($targetLabel)</a>
                    <a href="{{ $supportRoute }}" class="secondary-action">@lang('Contact Support')</a>
                </div>

                <div class="safe-list">
                    <div class="safe-item">
                        <strong>@lang('Funds protected')</strong>
                        <span>@lang('No transaction is completed from an expired screen.')</span>
                    </div>
                    <div class="safe-item">
                        <strong>@lang('Data secured')</strong>
                        <span>@lang('Old forms are blocked before they can submit.')</span>
                    </div>
                    <div class="safe-item">
                        <strong>@lang('Easy recovery')</strong>
                        <span>@lang('Return to the right login area and continue.')</span>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
BLADE;

file_put_contents($path, $view);

echo "BEAUTIFIED_SESSION_EXPIRED_VIEW\n";
