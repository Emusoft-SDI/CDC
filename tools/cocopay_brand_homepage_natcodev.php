<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$core = $root . '/core';
$targetDir = $root . '/assets/images/frontend/natcodev';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$images = [
    'home-hero.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/public/natcodev-home-hero.png',
    'grower-registration.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/public/grower-registration-hero.png',
    'community-impact.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/public/natcodev-community-impact.png',
    'dwarf-seedlings.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/market/dwarf-coconut-seedlings.png',
    'seedling-market.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/market/marketplace-trust-seedlings.png',
    'planting-service.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/market/planting-crew-service.png',
    'academy-hero.png' => 'C:/Users/user/Downloads/UniServerZ/www/CDC/assets/academy/natcodev-academy-public-hero.png',
];

foreach ($images as $name => $source) {
    if (is_file($source) && filesize($source) > 0) {
        copy($source, $targetDir . '/' . $name);
    }
}

$home = <<<'BLADE'
@extends($activeTemplate . 'layouts.frontend')
@section('content')
    @php
        $isLoggedIn = auth()->check();
        $dashboardUrl = $isLoggedIn ? route('user.home') : route('user.login');
        $registerUrl = route('user.register');
        $assetBase = asset('assets/images/frontend/natcodev');
        $servicesUrl = url('/services');
        $faqUrl = url('/faq');
        $contactUrl = route('contact');
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

        .nat-home {
            background: #fbfbf6;
            color: var(--nat-ink);
            overflow: hidden;
        }

        .nat-container {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
        }

        .nat-hero {
            position: relative;
            min-height: 690px;
            display: grid;
            align-items: end;
            background-image: linear-gradient(90deg, rgba(6, 44, 31, .95) 0%, rgba(6, 44, 31, .72) 42%, rgba(6, 44, 31, .25) 100%), url('{{ $assetBase }}/home-hero.png');
            background-size: cover;
            background-position: center;
            color: #fff;
            padding: 120px 0 54px;
        }

        .nat-hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 8px;
            background: linear-gradient(90deg, var(--nat-gold), #fff1b6, var(--nat-green-700));
        }

        .nat-hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.04fr) minmax(320px, .56fr);
            gap: 42px;
            align-items: end;
        }

        .nat-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border: 1px solid rgba(241, 203, 115, .52);
            border-radius: 999px;
            color: #ffe7a5;
            background: rgba(255, 255, 255, .08);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0;
        }

        .nat-hero h1 {
            margin: 20px 0 18px;
            max-width: 850px;
            font-size: clamp(42px, 6vw, 78px);
            line-height: .98;
            letter-spacing: 0;
            color: #fff;
        }

        .nat-hero p {
            max-width: 710px;
            color: rgba(255, 255, 255, .86);
            font-size: 18px;
            line-height: 1.75;
        }

        .nat-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 30px;
        }

        .nat-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 52px;
            padding: 0 22px;
            border-radius: 8px;
            font-weight: 800;
            color: var(--nat-green-950);
            background: linear-gradient(135deg, var(--nat-gold-2), var(--nat-gold));
            box-shadow: 0 18px 36px rgba(216, 168, 70, .24);
        }

        .nat-btn:hover {
            color: var(--nat-green-950);
            transform: translateY(-1px);
        }

        .nat-btn.secondary {
            color: #fff;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .24);
            box-shadow: none;
        }

        .nat-proof {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .nat-proof-card {
            min-height: 124px;
            padding: 20px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .18);
            backdrop-filter: blur(14px);
        }

        .nat-proof-card strong {
            display: block;
            font-size: 30px;
            color: #fff;
            line-height: 1;
        }

        .nat-proof-card span {
            display: block;
            margin-top: 10px;
            color: rgba(255, 255, 255, .76);
            font-size: 13px;
            line-height: 1.45;
        }

        .nat-section {
            padding: 88px 0;
        }

        .nat-section.tight {
            padding-top: 54px;
        }

        .nat-section-head {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: end;
            margin-bottom: 32px;
        }

        .nat-section-head h2 {
            max-width: 720px;
            margin: 0;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            color: var(--nat-green-950);
            letter-spacing: 0;
        }

        .nat-section-head p {
            max-width: 420px;
            color: var(--nat-muted);
            line-height: 1.7;
            margin: 0;
        }

        .nat-workbench {
            margin-top: -42px;
            position: relative;
            z-index: 2;
        }

        .nat-workbench-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .nat-workbench-card {
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 22px 54px rgba(6, 44, 31, .08);
        }

        .nat-workbench-card i,
        .nat-service i {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: var(--nat-green-900);
            background: linear-gradient(135deg, rgba(241, 203, 115, .56), rgba(15, 111, 69, .12));
            font-size: 20px;
        }

        .nat-workbench-card h3,
        .nat-service h3 {
            margin: 16px 0 8px;
            font-size: 18px;
            color: var(--nat-green-950);
        }

        .nat-workbench-card p,
        .nat-service p {
            margin: 0;
            color: var(--nat-muted);
            line-height: 1.65;
            font-size: 14px;
        }

        .nat-story {
            display: grid;
            grid-template-columns: .95fr 1.05fr;
            gap: 42px;
            align-items: center;
        }

        .nat-story-media {
            display: grid;
            grid-template-columns: 1fr .78fr;
            gap: 16px;
            align-items: end;
        }

        .nat-story-media img {
            width: 100%;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, .7);
            box-shadow: 0 22px 52px rgba(6, 44, 31, .12);
        }

        .nat-story-media img:first-child {
            height: 460px;
        }

        .nat-story-media img:last-child {
            height: 320px;
        }

        .nat-story-copy h2 {
            font-size: clamp(32px, 4vw, 52px);
            line-height: 1.05;
            color: var(--nat-green-950);
            margin: 0 0 18px;
        }

        .nat-story-copy p {
            color: var(--nat-muted);
            line-height: 1.8;
            margin-bottom: 18px;
        }

        .nat-list {
            display: grid;
            gap: 12px;
            margin: 24px 0 0;
            padding: 0;
            list-style: none;
        }

        .nat-list li {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            color: var(--nat-green-950);
            font-weight: 700;
        }

        .nat-list i {
            color: var(--nat-gold);
            margin-top: 3px;
        }

        .nat-services {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .nat-service {
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 12px;
            padding: 26px;
            min-height: 250px;
            box-shadow: 0 16px 44px rgba(6, 44, 31, .06);
        }

        .nat-band {
            background: linear-gradient(135deg, var(--nat-green-950), var(--nat-green-700));
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .nat-band-grid {
            display: grid;
            grid-template-columns: .9fr 1.1fr;
            gap: 40px;
            align-items: center;
        }

        .nat-band h2 {
            color: #fff;
            font-size: clamp(32px, 4vw, 52px);
            line-height: 1.08;
            margin: 0 0 16px;
        }

        .nat-band p {
            color: rgba(255, 255, 255, .78);
            line-height: 1.75;
            margin: 0;
        }

        .nat-band img {
            width: 100%;
            height: 420px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, .18);
            box-shadow: 0 28px 70px rgba(0, 0, 0, .25);
        }

        .nat-steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            counter-reset: step;
        }

        .nat-step {
            counter-increment: step;
            background: var(--nat-cream);
            border: 1px solid rgba(216, 168, 70, .28);
            border-radius: 12px;
            padding: 24px;
        }

        .nat-step::before {
            content: counter(step, decimal-leading-zero);
            display: block;
            color: var(--nat-gold);
            font-weight: 900;
            margin-bottom: 18px;
        }

        .nat-step h3 {
            color: var(--nat-green-950);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .nat-step p {
            color: var(--nat-muted);
            line-height: 1.65;
            margin: 0;
        }

        .nat-market {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .nat-market-card {
            position: relative;
            min-height: 340px;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            align-items: end;
            padding: 24px;
            color: #fff;
            background: var(--nat-green-900);
        }

        .nat-market-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: .72;
            transition: transform .35s ease;
        }

        .nat-market-card:hover img {
            transform: scale(1.04);
        }

        .nat-market-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(6, 44, 31, .08), rgba(6, 44, 31, .9));
        }

        .nat-market-card div {
            position: relative;
            z-index: 1;
        }

        .nat-market-card h3 {
            color: #fff;
            margin-bottom: 8px;
            font-size: 22px;
        }

        .nat-market-card p {
            color: rgba(255, 255, 255, .78);
            line-height: 1.55;
            margin: 0;
        }

        .nat-cta {
            border-radius: 18px;
            padding: 42px;
            background: linear-gradient(135deg, #fff, var(--nat-cream));
            border: 1px solid rgba(216, 168, 70, .32);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 28px;
            align-items: center;
            box-shadow: 0 24px 70px rgba(6, 44, 31, .08);
        }

        .nat-cta h2 {
            margin: 0 0 10px;
            color: var(--nat-green-950);
            font-size: clamp(28px, 4vw, 44px);
        }

        .nat-cta p {
            margin: 0;
            color: var(--nat-muted);
            line-height: 1.7;
        }

        @media (max-width: 991px) {
            .nat-hero {
                min-height: auto;
                padding: 110px 0 70px;
            }

            .nat-hero-grid,
            .nat-story,
            .nat-band-grid,
            .nat-cta {
                grid-template-columns: 1fr;
            }

            .nat-proof,
            .nat-workbench-grid,
            .nat-services,
            .nat-steps,
            .nat-market {
                grid-template-columns: repeat(2, 1fr);
            }

            .nat-section-head {
                display: block;
            }

            .nat-section-head p {
                margin-top: 14px;
            }
        }

        @media (max-width: 640px) {
            .nat-container {
                width: min(100% - 24px, 1180px);
            }

            .nat-proof,
            .nat-workbench-grid,
            .nat-services,
            .nat-steps,
            .nat-market,
            .nat-story-media {
                grid-template-columns: 1fr;
            }

            .nat-story-media img:first-child,
            .nat-story-media img:last-child,
            .nat-band img {
                height: 300px;
            }

            .nat-cta {
                padding: 28px;
            }
        }
    </style>

    <main class="nat-home">
        <section class="nat-hero">
            <div class="nat-container nat-hero-grid">
                <div>
                    <span class="nat-eyebrow"><i class="las la-seedling"></i> NATCODEV Coconut Farmers Cooperative Society</span>
                    <h1>Organizing coconut farmers for finance, certification, and market access.</h1>
                    <p>NATCODEV connects growers, dwarf coconut seedling programs, savings, farm credit, harvest records, and cooperative payments in one trusted member platform.</p>
                    <div class="nat-actions">
                        <a class="nat-btn" href="{{ $dashboardUrl }}"><i class="las la-columns"></i>{{ $isLoggedIn ? __('Go to Dashboard') : __('Member Login') }}</a>
                        <a class="nat-btn secondary" href="{{ $registerUrl }}"><i class="las la-user-plus"></i>@lang('Join the Cooperative')</a>
                    </div>
                </div>
                <div class="nat-proof">
                    <div class="nat-proof-card"><strong>1K+</strong><span>@lang('Target member farmers across coconut communities')</span></div>
                    <div class="nat-proof-card"><strong>24/7</strong><span>@lang('Digital wallet, records, and support access')</span></div>
                    <div class="nat-proof-card"><strong>10+</strong><span>@lang('Cooperative programs for growers and partners')</span></div>
                    <div class="nat-proof-card"><strong>NGN</strong><span>@lang('Savings, deposits, loans, and payment tracking')</span></div>
                </div>
            </div>
        </section>

        <section class="nat-workbench">
            <div class="nat-container nat-workbench-grid">
                <article class="nat-workbench-card"><i class="las la-id-card"></i><h3>@lang('Grower Certification')</h3><p>@lang('Keep NATCODEV growers certificates and member status visible for cooperative verification.')</p></article>
                <article class="nat-workbench-card"><i class="las la-wallet"></i><h3>@lang('Member Wallet')</h3><p>@lang('Fund wallet, deposit savings, withdraw approved funds, and follow every ledger movement.')</p></article>
                <article class="nat-workbench-card"><i class="las la-hand-holding-usd"></i><h3>@lang('Farm Credit')</h3><p>@lang('Request structured loans for inputs, seedlings, production, and harvest operations.')</p></article>
                <article class="nat-workbench-card"><i class="las la-headset"></i><h3>@lang('Accessible Support')</h3><p>@lang('Reach support, browse FAQs, and resolve member requests without getting lost.')</p></article>
            </div>
        </section>

        <section class="nat-section">
            <div class="nat-container nat-story">
                <div class="nat-story-media">
                    <img src="{{ $assetBase }}/grower-registration.png" alt="@lang('NATCODEV grower registration')">
                    <img src="{{ $assetBase }}/dwarf-seedlings.png" alt="@lang('Dwarf coconut seedlings')">
                </div>
                <div class="nat-story-copy">
                    <span class="nat-eyebrow" style="color: var(--nat-green-800); border-color: rgba(216,168,70,.42); background: rgba(216,168,70,.12);">@lang('Built for coconut communities')</span>
                    <h2>@lang('From scattered records to one cooperative operating system.')</h2>
                    <p>@lang('NATCODEV gives coconut farmers a practical digital workspace for membership, savings, loans, verification, and market readiness. The platform supports African farming realities: field agents, local branches, farmer groups, and value-chain partners.')</p>
                    <ul class="nat-list">
                        <li><i class="las la-check-circle"></i>@lang('Dwarf coconut and nursery establishment support')</li>
                        <li><i class="las la-check-circle"></i>@lang('Transparent savings, deposits, withdrawals, and transactions')</li>
                        <li><i class="las la-check-circle"></i>@lang('Grower certificates and cooperative member documentation')</li>
                        <li><i class="las la-check-circle"></i>@lang('Input loans and harvest finance tracked from one ledger')</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="nat-section tight">
            <div class="nat-container">
                <div class="nat-section-head">
                    <h2>@lang('The cooperative tools members expect on day one.')</h2>
                    <p>@lang('A modern finance and support layer for growers, branch officers, and cooperative administrators.')</p>
                </div>
                <div class="nat-services">
                    <article class="nat-service"><i class="las la-money-check"></i><h3>@lang('Savings and Deposits')</h3><p>@lang('Members can add savings, review balances, and keep contributions traceable for cooperative planning.')</p></article>
                    <article class="nat-service"><i class="las la-leaf"></i><h3>@lang('Seedling and Input Finance')</h3><p>@lang('Loan requests can be tied to farm needs including dwarf coconut seedlings, fertilizer, tools, and labor.')</p></article>
                    <article class="nat-service"><i class="las la-exchange-alt"></i><h3>@lang('Transfers and Withdrawals')</h3><p>@lang('Move approved funds with clear transaction history and branch-level accountability.')</p></article>
                    <article class="nat-service"><i class="las la-mobile"></i><h3>@lang('Paystack and Monnify')</h3><p>@lang('Fund member accounts through Nigerian payment rails configured for cooperative deposits.')</p></article>
                    <article class="nat-service"><i class="las la-certificate"></i><h3>@lang('Membership Proof')</h3><p>@lang('Growers can keep their NATCODEV certificate attached to their profile for review and updates.')</p></article>
                    <article class="nat-service"><i class="las la-question-circle"></i><h3>@lang('FAQs and Knowledge Base')</h3><p>@lang('Members can find guidance on deposits, farm credit, security, profile records, and support tickets.')</p></article>
                </div>
            </div>
        </section>

        <section class="nat-section nat-band">
            <div class="nat-container nat-band-grid">
                <div>
                    <span class="nat-eyebrow"><i class="las la-chart-line"></i> @lang('Cooperative impact')</span>
                    <h2>@lang('Designed for African coconut farmers and value-chain partners.')</h2>
                    <p>@lang('The platform helps NATCODEV coordinate members, branches, processors, seedling programs, field agents, and finance workflows with records that are easier to trust.')</p>
                    <div class="nat-actions">
                        <a class="nat-btn" href="{{ $servicesUrl }}"><i class="las la-th-large"></i>@lang('Explore Services')</a>
                        <a class="nat-btn secondary" href="{{ $faqUrl }}"><i class="las la-book-open"></i>@lang('Read FAQs')</a>
                    </div>
                </div>
                <img src="{{ $assetBase }}/community-impact.png" alt="@lang('NATCODEV community impact')">
            </div>
        </section>

        <section class="nat-section">
            <div class="nat-container">
                <div class="nat-section-head">
                    <h2>@lang('How a farmer becomes an active cooperative member.')</h2>
                    <p>@lang('Clear steps help members know where they are and what to do next.')</p>
                </div>
                <div class="nat-steps">
                    <article class="nat-step"><h3>@lang('Register')</h3><p>@lang('Create a member profile with Nigeria as the default country and location details from the system.')</p></article>
                    <article class="nat-step"><h3>@lang('Upload Certificate')</h3><p>@lang('Attach the NATCODEV growers certificate or cooperative-issued document to confirm eligibility.')</p></article>
                    <article class="nat-step"><h3>@lang('Fund Wallet')</h3><p>@lang('Use Paystack or Monnify to deposit into the cooperative wallet and begin savings.')</p></article>
                    <article class="nat-step"><h3>@lang('Grow and Trade')</h3><p>@lang('Access farm credit, seedling programs, market readiness, harvest records, and support.')</p></article>
                </div>
            </div>
        </section>

        <section class="nat-section tight">
            <div class="nat-container">
                <div class="nat-section-head">
                    <h2>@lang('Coconut growth does not stop at finance.')</h2>
                    <p>@lang('NATCODEV links cooperative records with practical production and market pathways.')</p>
                </div>
                <div class="nat-market">
                    <article class="nat-market-card"><img src="{{ $assetBase }}/seedling-market.png" alt="@lang('Seedling market')"><div><h3>@lang('Verified Seedlings')</h3><p>@lang('Support for nursery programs and dwarf coconut species where needed.')</p></div></article>
                    <article class="nat-market-card"><img src="{{ $assetBase }}/planting-service.png" alt="@lang('Planting crew')"><div><h3>@lang('Farm Services')</h3><p>@lang('Connect production support, planting teams, and field operations to member growth.')</p></div></article>
                    <article class="nat-market-card"><img src="{{ $assetBase }}/academy-hero.png" alt="@lang('NATCODEV academy')"><div><h3>@lang('Training and Records')</h3><p>@lang('Support farmer learning, certificates, and operational readiness across the network.')</p></div></article>
                </div>
            </div>
        </section>

        <section class="nat-section">
            <div class="nat-container">
                <div class="nat-cta">
                    <div>
                        <h2>@lang('Ready to manage your cooperative account?')</h2>
                        <p>@lang('Log in to check your wallet, savings, loan requests, profile, certificate, transactions, and support tickets.')</p>
                    </div>
                    <div class="nat-actions" style="margin-top: 0;">
                        <a class="nat-btn" href="{{ $dashboardUrl }}"><i class="las la-columns"></i>{{ $isLoggedIn ? __('Open Dashboard') : __('Login') }}</a>
                        <a class="nat-btn secondary" style="color: var(--nat-green-950); border-color: rgba(8,58,40,.2);" href="{{ $contactUrl }}"><i class="las la-envelope"></i>@lang('Contact Support')</a>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
BLADE;

file_put_contents($core . '/resources/views/templates/indigo_fusion/home.blade.php', $home);

echo "NATCODEV homepage installed\n";
