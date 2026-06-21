<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/crystal_sky';

$partialDir = $root . '/partials';
if (!is_dir($partialDir)) {
    mkdir($partialDir, 0777, true);
}

$styles = <<<'BLADE'
@once
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

        .nat-public { background: #fbfbf6; color: var(--nat-ink); }
        .nat-container { width: min(1180px, calc(100% - 36px)); margin: 0 auto; }
        .nat-page-hero {
            position: relative;
            min-height: 430px;
            display: grid;
            align-items: end;
            color: #fff;
            padding: 130px 0 58px;
            background-size: cover;
            background-position: center;
        }
        .nat-page-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(6,44,31,.94), rgba(6,44,31,.68), rgba(6,44,31,.18));
        }
        .nat-page-hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 7px;
            background: linear-gradient(90deg, var(--nat-gold), #fff1b6, var(--nat-green-700));
        }
        .nat-page-hero .nat-container { position: relative; z-index: 1; }
        .nat-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 8px 13px;
            border: 1px solid rgba(241,203,115,.52);
            border-radius: 999px;
            color: #ffe7a5;
            background: rgba(255,255,255,.08);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0;
        }
        .nat-page-hero h1 {
            max-width: 820px;
            margin: 18px 0 14px;
            color: #fff;
            font-size: clamp(38px, 5vw, 68px);
            line-height: 1;
            letter-spacing: 0;
        }
        .nat-page-hero p {
            max-width: 720px;
            margin: 0;
            color: rgba(255,255,255,.84);
            font-size: 18px;
            line-height: 1.7;
        }
        .nat-section { padding: 82px 0; }
        .nat-section.tight { padding-top: 48px; }
        .nat-section-head {
            display: flex;
            justify-content: space-between;
            gap: 28px;
            align-items: end;
            margin-bottom: 32px;
        }
        .nat-section-head h2 {
            max-width: 720px;
            margin: 0;
            color: var(--nat-green-950);
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: 0;
        }
        .nat-section-head p { max-width: 430px; margin: 0; color: var(--nat-muted); line-height: 1.7; }
        .nat-grid-2 { display: grid; grid-template-columns: .95fr 1.05fr; gap: 42px; align-items: center; }
        .nat-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
        .nat-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .nat-card {
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 12px;
            padding: 26px;
            box-shadow: 0 16px 44px rgba(6,44,31,.06);
        }
        .nat-card i {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: var(--nat-green-900);
            background: linear-gradient(135deg, rgba(241,203,115,.56), rgba(15,111,69,.12));
            font-size: 20px;
        }
        .nat-card h3 { margin: 16px 0 8px; color: var(--nat-green-950); font-size: 20px; }
        .nat-card p { margin: 0; color: var(--nat-muted); line-height: 1.65; }
        .nat-media {
            width: 100%;
            min-height: 420px;
            object-fit: cover;
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(6,44,31,.13);
            border: 1px solid rgba(255,255,255,.72);
        }
        .nat-copy h2 {
            margin: 0 0 16px;
            color: var(--nat-green-950);
            font-size: clamp(32px,4vw,52px);
            line-height: 1.06;
        }
        .nat-copy p { color: var(--nat-muted); line-height: 1.78; margin-bottom: 16px; }
        .nat-list { display: grid; gap: 12px; margin: 22px 0 0; padding: 0; list-style: none; }
        .nat-list li { display: flex; gap: 12px; align-items: flex-start; color: var(--nat-green-950); font-weight: 700; }
        .nat-list i { color: var(--nat-gold); margin-top: 3px; }
        .nat-band { background: linear-gradient(135deg, var(--nat-green-950), var(--nat-green-700)); color: #fff; }
        .nat-band h2, .nat-band h3 { color: #fff; }
        .nat-band p { color: rgba(255,255,255,.78); }
        .nat-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 50px;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 800;
            color: var(--nat-green-950);
            background: linear-gradient(135deg, var(--nat-gold-2), var(--nat-gold));
            box-shadow: 0 18px 36px rgba(216,168,70,.24);
        }
        .nat-btn:hover { color: var(--nat-green-950); transform: translateY(-1px); }
        .nat-btn.secondary { color: #fff; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); box-shadow: none; }
        .nat-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 26px; }
        .nat-faq { display: grid; gap: 14px; }
        .nat-faq details {
            background: #fff;
            border: 1px solid var(--nat-line);
            border-radius: 12px;
            padding: 18px 22px;
            box-shadow: 0 12px 34px rgba(6,44,31,.05);
        }
        .nat-faq summary {
            cursor: pointer;
            color: var(--nat-green-950);
            font-weight: 850;
            font-size: 17px;
        }
        .nat-faq p { color: var(--nat-muted); line-height: 1.7; margin: 12px 0 0; }
        .nat-contact-card {
            border-radius: 16px;
            background: linear-gradient(135deg, #fff, var(--nat-cream));
            border: 1px solid rgba(216,168,70,.28);
            padding: 28px;
        }
        .nat-form-wrap .contact-form,
        .nat-form-wrap .feature-overlay-section,
        .nat-form-wrap .features {
            margin: 0;
            box-shadow: none;
        }
        .nat-form-wrap .feature-overlay-section { display: none; }
        @media (max-width: 991px) {
            .nat-section-head { display: block; }
            .nat-section-head p { margin-top: 14px; }
            .nat-grid-2, .nat-grid-3, .nat-grid-4 { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .nat-container { width: min(100% - 24px, 1180px); }
            .nat-page-hero { min-height: 360px; padding-top: 110px; }
            .nat-grid-2, .nat-grid-3, .nat-grid-4 { grid-template-columns: 1fr; }
            .nat-media { min-height: 300px; }
        }
    </style>
@endonce
BLADE;

file_put_contents($partialDir . '/natcodev_public_styles.blade.php', $styles);

$pages = <<<'BLADE'
@extends($activeTemplate . 'layouts.frontend')
@section('content')
    @include($activeTemplate . 'partials.natcodev_public_styles')
    @php
        $pageKey = strtolower($pageTitle ?? '');
        $assetBase = asset('assets/images/frontend/natcodev');
        $dashboardUrl = auth()->check() ? route('user.home') : route('user.login');
    @endphp

    @if (str_contains($pageKey, 'about'))
        <main class="nat-public">
            <section class="nat-page-hero" style="background-image: url('{{ $assetBase }}/community-impact.png');">
                <div class="nat-container">
                    <span class="nat-eyebrow"><i class="las la-users"></i>@lang('About the cooperative')</span>
                    <h1>@lang('NATCODEV Coconut Farmers Cooperative Society')</h1>
                    <p>@lang('A farmer-first cooperative platform organizing coconut growers around trusted records, fair finance, certification, and market access.')</p>
                </div>
            </section>

            <section class="nat-section">
                <div class="nat-container nat-grid-2">
                    <img class="nat-media" src="{{ $assetBase }}/grower-registration.png" alt="@lang('NATCODEV grower registration')">
                    <div class="nat-copy">
                        <span class="nat-eyebrow" style="color:var(--nat-green-800);border-color:rgba(216,168,70,.42);background:rgba(216,168,70,.12);">@lang('Our purpose')</span>
                        <h2>@lang('Moving coconut farmers from scattered effort to shared prosperity.')</h2>
                        <p>@lang('NATCODEV helps members build traceable cooperative records, save consistently, request farm credit, upload growers certificates, and prepare for stronger participation in the coconut value chain.')</p>
                        <p>@lang('The platform is designed around African cooperative realities: field agents, local branches, member verification, local payments, harvest cycles, and practical support for dwarf coconut production.')</p>
                        <ul class="nat-list">
                            <li><i class="las la-check-circle"></i>@lang('Transparent member savings and wallet records')</li>
                            <li><i class="las la-check-circle"></i>@lang('Grower certification and cooperative eligibility')</li>
                            <li><i class="las la-check-circle"></i>@lang('Input, seedling, and harvest finance workflows')</li>
                            <li><i class="las la-check-circle"></i>@lang('Support for branches, farmers, and value-chain partners')</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="nat-section tight">
                <div class="nat-container">
                    <div class="nat-section-head">
                        <h2>@lang('What the cooperative stands for.')</h2>
                        <p>@lang('Every feature is tied to a practical farmer outcome: proof, finance, growth, support, and accountability.')</p>
                    </div>
                    <div class="nat-grid-4">
                        <article class="nat-card"><i class="las la-certificate"></i><h3>@lang('Proof')</h3><p>@lang('Member certificates and profile records help validate cooperative participation.')</p></article>
                        <article class="nat-card"><i class="las la-wallet"></i><h3>@lang('Finance')</h3><p>@lang('Savings, deposits, withdrawals, and credit sit in one traceable ledger.')</p></article>
                        <article class="nat-card"><i class="las la-seedling"></i><h3>@lang('Production')</h3><p>@lang('Dwarf coconut seedling and input needs can be linked to farm finance.')</p></article>
                        <article class="nat-card"><i class="las la-handshake"></i><h3>@lang('Trust')</h3><p>@lang('Clear records support branches, members, and cooperative administrators.')</p></article>
                    </div>
                </div>
            </section>
        </main>
    @elseif (str_contains($pageKey, 'service'))
        <main class="nat-public">
            <section class="nat-page-hero" style="background-image: url('{{ $assetBase }}/seedling-market.png');">
                <div class="nat-container">
                    <span class="nat-eyebrow"><i class="las la-layer-group"></i>@lang('Cooperative services')</span>
                    <h1>@lang('Services built around coconut farm growth.')</h1>
                    <p>@lang('Finance, certification, payments, support, and production coordination for NATCODEV cooperative members.')</p>
                </div>
            </section>

            <section class="nat-section">
                <div class="nat-container">
                    <div class="nat-section-head">
                        <h2>@lang('Member services in one reliable workspace.')</h2>
                        <p>@lang('The platform groups routine finance and support actions so members know where to go next.')</p>
                    </div>
                    <div class="nat-grid-3">
                        <article class="nat-card"><i class="las la-money-check"></i><h3>@lang('Savings and Wallet')</h3><p>@lang('Deposit, save, review balance, and follow every cooperative transaction from the member dashboard.')</p></article>
                        <article class="nat-card"><i class="las la-hand-holding-usd"></i><h3>@lang('Farm Credit')</h3><p>@lang('Request loans for seedlings, inputs, operations, and harvest support with clear review records.')</p></article>
                        <article class="nat-card"><i class="las la-mobile"></i><h3>@lang('Paystack and Monnify')</h3><p>@lang('Fund accounts with Nigerian payment rails configured for cooperative deposits.')</p></article>
                        <article class="nat-card"><i class="las la-certificate"></i><h3>@lang('Grower Certificate')</h3><p>@lang('Attach NATCODEV-issued certificates to support membership review and cooperative eligibility.')</p></article>
                        <article class="nat-card"><i class="las la-exchange-alt"></i><h3>@lang('Transfers and Withdrawals')</h3><p>@lang('Request withdrawals and move approved funds while keeping branch records clear.')</p></article>
                        <article class="nat-card"><i class="las la-headset"></i><h3>@lang('Support Desk')</h3><p>@lang('Open tickets, browse FAQs, and get guided help when member services need attention.')</p></article>
                    </div>
                </div>
            </section>

            <section class="nat-section nat-band">
                <div class="nat-container nat-grid-2">
                    <div class="nat-copy">
                        <h2>@lang('Production support meets financial accountability.')</h2>
                        <p>@lang('NATCODEV services connect the farm realities of coconut production with the records needed for savings, credit, member support, and cooperative reporting.')</p>
                        <div class="nat-actions">
                            <a class="nat-btn" href="{{ $dashboardUrl }}"><i class="las la-columns"></i>@lang('Open Member Workspace')</a>
                            <a class="nat-btn secondary" href="{{ route('contact') }}"><i class="las la-envelope"></i>@lang('Ask Support')</a>
                        </div>
                    </div>
                    <img class="nat-media" src="{{ $assetBase }}/planting-service.png" alt="@lang('Coconut planting service')">
                </div>
            </section>
        </main>
    @elseif (str_contains($pageKey, 'faq'))
        <main class="nat-public">
            <section class="nat-page-hero" style="background-image: url('{{ $assetBase }}/dwarf-seedlings.png');">
                <div class="nat-container">
                    <span class="nat-eyebrow"><i class="las la-question-circle"></i>@lang('Member FAQs')</span>
                    <h1>@lang('Answers for cooperative members.')</h1>
                    <p>@lang('Quick guidance for savings, farm credit, certificates, payments, support tickets, and profile records.')</p>
                </div>
            </section>

            <section class="nat-section">
                <div class="nat-container nat-grid-2" style="align-items:start;">
                    <div class="nat-copy">
                        <h2>@lang('Start with the common questions.')</h2>
                        <p>@lang('These answers explain the core NATCODEV member journey. For account-specific issues, open a support ticket after logging in.')</p>
                        <div class="nat-actions">
                            <a class="nat-btn" href="{{ $dashboardUrl }}"><i class="las la-life-ring"></i>@lang('Go to Support')</a>
                        </div>
                    </div>
                    <div class="nat-faq">
                        <details open><summary>@lang('What is NATCODEV Coconut Farmers Cooperative Society?')</summary><p>@lang('It is a cooperative platform for coconut growers to manage membership records, savings, deposits, farm loans, certificates, and support in one place.')</p></details>
                        <details><summary>@lang('Why do I need a NATCODEV growers certificate?')</summary><p>@lang('The certificate helps confirm cooperative participation and supports eligibility review for member services, farm credit, and records validation.')</p></details>
                        <details><summary>@lang('How do I fund my wallet?')</summary><p>@lang('Use the Deposit option in your dashboard. The platform is configured for Paystack and Monnify payment options.')</p></details>
                        <details><summary>@lang('What can farm loans support?')</summary><p>@lang('Farm loans may support inputs, dwarf coconut seedlings, production costs, labor, harvest needs, and other cooperative-approved farm operations.')</p></details>
                        <details><summary>@lang('Where do I find Airtime and Transfers?')</summary><p>@lang('Routine payment services are grouped under services or member dashboard menus to keep navigation cleaner and easier to scan.')</p></details>
                        <details><summary>@lang('How do I get help?')</summary><p>@lang('Use Contact for general enquiries, or log in and open a support ticket for account-specific wallet, loan, transaction, or profile issues.')</p></details>
                    </div>
                </div>
            </section>
        </main>
    @else
        @if ($sections != null)
            @foreach (json_decode($sections) as $sec)
                @include($activeTemplate . 'sections.' . $sec)
            @endforeach
        @endif
    @endif
@endsection
BLADE;

file_put_contents($root . '/pages.blade.php', $pages);

$contact = <<<'BLADE'
@extends($activeTemplate . 'layouts.frontend')
@php
    $contact = getContent('contact_us.content', true);
    $assetBase = asset('assets/images/frontend/natcodev');
@endphp
@section('content')
    @include($activeTemplate . 'partials.natcodev_public_styles')

    <main class="nat-public">
        <section class="nat-page-hero" style="background-image: url('{{ $assetBase }}/community-impact.png');">
            <div class="nat-container">
                <span class="nat-eyebrow"><i class="las la-headset"></i>@lang('Cooperative support')</span>
                <h1>@lang('Contact NATCODEV Coconut Farmers Cooperative.')</h1>
                <p>@lang('Reach the cooperative team for membership, certificates, wallet payments, farm credit, branch support, and general enquiries.')</p>
            </div>
        </section>

        <section class="nat-section">
            <div class="nat-container">
                <div class="nat-grid-3" style="margin-bottom:28px;">
                    <article class="nat-card"><i class="las la-map-marker-alt"></i><h3>@lang('Cooperative Office')</h3><p>{{ __(@$contact->data_values->contact_address ?: 'NATCODEV Coconut Farmers Cooperative, Local Demo Branch') }}</p></article>
                    <article class="nat-card"><i class="las la-phone"></i><h3>@lang('Phone')</h3><p><a href="tel:{{ @$contact->data_values->contact_number }}">{{ @$contact->data_values->contact_number ?: '+234 000 000 0000' }}</a></p></article>
                    <article class="nat-card"><i class="las la-envelope"></i><h3>@lang('Email')</h3><p><a href="mailto:{{ @$contact->data_values->email_address }}">{{ @$contact->data_values->email_address ?: 'info@natcodevcoop.local' }}</a></p></article>
                </div>

                <div class="nat-grid-2" style="align-items:start;">
                    <div class="nat-contact-card">
                        <div class="nat-copy">
                            <h2>@lang('Tell us what you need help with.')</h2>
                            <p>@lang('For account-specific issues, include your transaction reference, loan request, certificate detail, or profile email so the support team can trace it faster.')</p>
                        </div>
                        <img class="nat-media" src="{{ $assetBase }}/grower-registration.png" alt="@lang('NATCODEV cooperative support')" style="min-height:280px;margin-top:18px;">
                    </div>

                    <div class="nat-form-wrap">
                        <div class="contact-form">
                            <div class="section-heading">
                                <h6 class="section-heading__subtitle">@lang('Send a message')</h6>
                                <h2 class="section-heading__title">@lang('Member and partner enquiry')</h2>
                            </div>
                            <form action="" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form--group">
                                            <label class="form--label">@lang('Name')</label>
                                            <input name="name" type="text" class="form-control form--control" value="{{ old('name',@$user->fullname) }}" @if($user && $user->profile_complete) readonly @endif required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form--group">
                                            <label class="form--label">@lang('Email')</label>
                                            <input type="email" class="form--control" name="email" value="{{ old('email', @$user->email) }}" @if ($user) readonly @endif required />
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form--group">
                                            <label class="form--label">@lang('Subject')</label>
                                            <input type="text" name="subject" class="form--control" value="{{ old('subject') }}" placeholder="@lang('Wallet, certificate, loan, branch support...')" required />
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form--group">
                                            <label class="form--label">@lang('Message')</label>
                                            <textarea name="message" class="form--control" rows="5" cols="50">{{ old('message') }}</textarea>
                                        </div>
                                    </div>
                                    <x-captcha />
                                    <div class="col-12">
                                        <div class="form--group">
                                            <button class="btn btn--base" id="recaptcha" type="submit">@lang('Send Message')</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
BLADE;

file_put_contents($root . '/contact.blade.php', $contact);

echo "public cooperative pages updated\n";
