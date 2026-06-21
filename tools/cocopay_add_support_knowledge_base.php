<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$routesPath = $root . '/core/routes/web.php';
$headerPath = $root . '/core/resources/views/templates/indigo_fusion/partials/header.blade.php';
$viewPath = $root . '/core/resources/views/templates/indigo_fusion/user/support/knowledge_base.blade.php';
$cssPath = $root . '/assets/templates/indigo_fusion/css/custom.css';

foreach ([$routesPath, $headerPath, $cssPath] as $path) {
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }
}

$routes = file_get_contents($routesPath);
if (strpos($routes, "user/support/knowledge-base") === false) {
    $insert = <<<'PHP'

Route::middleware('auth')->get('user/support/knowledge-base', function () {
    $pageTitle = 'Knowledge Base';
    return view(activeTemplate() . 'user.support.knowledge_base', compact('pageTitle'));
})->name('user.support.knowledge');

PHP;
    $needle = "Route::get('app/deposit/confirm/{hash}', 'Gateway\\PaymentController@appDepositConfirm')->name('deposit.app.confirm');\n";
    if (strpos($routes, $needle) === false) {
        throw new RuntimeException('Route insertion point not found.');
    }
    $routes = str_replace($needle, $needle . $insert, $routes);
    file_put_contents($routesPath, $routes);
}

$header = file_get_contents($headerPath);
$oldServices = <<<'BLADE'
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
BLADE;

$newServices = <<<'BLADE'
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
                            <a class="dropdown-toggle {{ menuActive(['ticket*', 'user.support.knowledge']) }}" href="#" id="supportMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="las la-headset"></i><span>@lang('Support')</span>
                            </a>
                            <ul class="dropdown-menu nat-member-dropdown nat-lux-dropdown" aria-labelledby="supportMenu">
                                <li><a class="dropdown-item" href="{{ route('user.support.knowledge') }}"><i class="las la-book-open"></i>@lang('Knowledge Base')</a></li>
                                <li><a class="dropdown-item" href="{{ route('ticket.index') }}"><i class="las la-ticket-alt"></i>@lang('Support Tickets')</a></li>
                                <li><a class="dropdown-item" href="{{ route('ticket.open') }}"><i class="las la-plus-circle"></i>@lang('Open New Ticket')</a></li>
                                <li><a class="dropdown-item" href="{{ route('contact') }}"><i class="las la-envelope"></i>@lang('Contact Office')</a></li>
                            </ul>
                        </li>

                        <li class="dropdown">
                            <a class="dropdown-toggle {{ menuActive(['user.profile.setting', 'user.twofactor', 'user.change.password', 'user.referral.*']) }}" href="#" id="memberMoreMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
BLADE;

if (strpos($header, $oldServices) === false) {
    throw new RuntimeException('Expected member services/more block not found.');
}
$header = str_replace($oldServices, $newServices, $header);
$header = str_replace(
    "                                <li><a class=\"dropdown-item\" href=\"{{ route('ticket.index') }}\"><i class=\"las la-headset\"></i>@lang('Support')</a></li>\n",
    '',
    $header
);
$header = str_replace(
    "<li><a class=\"dropdown-item\" href=\"{{ route('user.profile.setting') }}\"><i class=\"las la-user-circle\"></i>@lang('Profile')</a></li>\n                                            <li><a class=\"dropdown-item\" href=\"{{ route('user.logout') }}\"><i class=\"las la-sign-out-alt\"></i>@lang('Logout')</a></li>",
    "<li><a class=\"dropdown-item\" href=\"{{ route('user.profile.setting') }}\"><i class=\"las la-user-circle\"></i>@lang('Profile')</a></li>\n                                            <li><a class=\"dropdown-item\" href=\"{{ route('user.support.knowledge') }}\"><i class=\"las la-book-open\"></i>@lang('Knowledge Base')</a></li>\n                                            <li><a class=\"dropdown-item\" href=\"{{ route('ticket.index') }}\"><i class=\"las la-headset\"></i>@lang('Support')</a></li>\n                                            <li><a class=\"dropdown-item\" href=\"{{ route('user.logout') }}\"><i class=\"las la-sign-out-alt\"></i>@lang('Logout')</a></li>",
    $header
);
file_put_contents($headerPath, $header);

$view = <<<'BLADE'
@extends($activeTemplate . 'layouts.master')
@section('content')
    <div class="container natkb">
        <section class="natkb-hero">
            <div>
                <span>@lang('Member Help Center')</span>
                <h2>@lang('Knowledge Base & FAQs')</h2>
                <p>@lang('Find quick answers for wallet activity, savings, loans, transfers, airtime, security, and support tickets.')</p>
            </div>
            <div class="natkb-hero-actions">
                <a href="{{ route('ticket.open') }}"><i class="las la-plus-circle"></i>@lang('Open Ticket')</a>
                <a href="{{ route('ticket.index') }}"><i class="las la-ticket-alt"></i>@lang('My Tickets')</a>
            </div>
        </section>

        <section class="natkb-grid">
            <a href="#wallet"><i class="las la-wallet"></i><span>@lang('Wallet & Transactions')</span></a>
            <a href="#savings"><i class="las la-seedling"></i><span>@lang('Savings & Growth')</span></a>
            <a href="#loans"><i class="las la-hand-holding-usd"></i><span>@lang('Farm Loans')</span></a>
            <a href="#services"><i class="las la-layer-group"></i><span>@lang('Transfers & Bills')</span></a>
            <a href="#security"><i class="las la-shield-alt"></i><span>@lang('Profile & Security')</span></a>
            <a href="#tickets"><i class="las la-headset"></i><span>@lang('Support Tickets')</span></a>
        </section>

        <section class="natkb-layout">
            <main class="natkb-main">
                @php
                    $sections = [
                        'wallet' => [
                            'title' => 'Wallet & Transactions',
                            'items' => [
                                ['What is my cooperative wallet?', 'Your wallet shows your available member balance and keeps a traceable record of deposits, withdrawals, transfers, savings activity, loan disbursement, and charges.'],
                                ['Why do transactions show Money In and Money Out?', 'Money In means funds were credited to your wallet. Money Out means funds were debited from your wallet. Open Transactions to filter by reference, category, or direction.'],
                                ['Where can I find my statement?', 'Go to Wallet > Transactions or use the Statement button on the dashboard wallet card.'],
                            ],
                        ],
                        'savings' => [
                            'title' => 'Savings & Growth',
                            'items' => [
                                ['What is Monthly Savings?', 'Monthly Savings helps members build regular cooperative contributions for farm needs and future planning.'],
                                ['What is Harvest Fixed Savings?', 'Harvest Fixed Savings is for funds you want to keep locked for a defined harvest or maturity period.'],
                                ['Can I track pending deposits?', 'Yes. Open Wallet > Deposits to see pending and completed deposit records.'],
                            ],
                        ],
                        'loans' => [
                            'title' => 'Farm Loans',
                            'items' => [
                                ['How do I apply for a farm loan?', 'Open Growth > Farm Loans, choose a plan, complete the form, and submit the request for review.'],
                                ['Why is loan documentation important?', 'The cooperative needs farm details to review support for coconut production, dwarf coconut establishment, harvest plans, and repayment capacity.'],
                                ['Where do I track my loan?', 'Use Growth > Farm Loans or the Running Loans card on your dashboard.'],
                            ],
                        ],
                        'services' => [
                            'title' => 'Transfers & Bills',
                            'items' => [
                                ['Where is Airtime?', 'Airtime and bill services are grouped under Services > Airtime & Bills.'],
                                ['Where is Transfer?', 'Transfer is under Services. It covers member or bank transfer paths enabled for your account.'],
                                ['Why group services?', 'Grouping keeps the top menu clean and helps members find routine payment services faster.'],
                            ],
                        ],
                        'security' => [
                            'title' => 'Profile & Security',
                            'items' => [
                                ['How do I update my profile?', 'Open More > Profile or click your profile chip in the top menu.'],
                                ['Where is password change?', 'Open More > Password to change your login password.'],
                                ['Should I enable 2FA?', 'Yes. Use More > 2FA Security to protect your account with a second verification step.'],
                            ],
                        ],
                        'tickets' => [
                            'title' => 'Support Tickets',
                            'items' => [
                                ['When should I open a ticket?', 'Open a ticket when you cannot resolve an issue from the dashboard, transaction page, deposit, withdrawal, loan, or profile screens.'],
                                ['How do I see my tickets?', 'Open Support > Support Tickets to see all your submitted tickets and replies.'],
                                ['What should I include in a ticket?', 'Include the transaction reference, affected service, screenshots if available, and a short explanation of what you expected to happen.'],
                            ],
                        ],
                    ];
                @endphp

                @foreach ($sections as $id => $section)
                    <article class="natkb-section" id="{{ $id }}">
                        <div class="natkb-section-head">
                            <span>@lang('Help Topic')</span>
                            <h3>{{ __($section['title']) }}</h3>
                        </div>
                        <div class="accordion natkb-accordion" id="kb-{{ $id }}">
                            @foreach ($section['items'] as $index => $item)
                                <div class="accordion-item">
                                    <h4 class="accordion-header" id="kb-{{ $id }}-h-{{ $index }}">
                                        <button class="accordion-button @if ($index) collapsed @endif" type="button" data-bs-toggle="collapse" data-bs-target="#kb-{{ $id }}-c-{{ $index }}" aria-expanded="{{ $index ? 'false' : 'true' }}" aria-controls="kb-{{ $id }}-c-{{ $index }}">
                                            {{ __($item[0]) }}
                                        </button>
                                    </h4>
                                    <div id="kb-{{ $id }}-c-{{ $index }}" class="accordion-collapse collapse @if (!$index) show @endif" aria-labelledby="kb-{{ $id }}-h-{{ $index }}" data-bs-parent="#kb-{{ $id }}">
                                        <div class="accordion-body">{{ __($item[1]) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </main>

            <aside class="natkb-aside">
                <div class="natkb-card">
                    <i class="las la-headset"></i>
                    <h4>@lang('Still need help?')</h4>
                    <p>@lang('Send a support ticket and the cooperative support team can reply with a clear record of the issue.')</p>
                    <a href="{{ route('ticket.open') }}">@lang('Open Support Ticket')</a>
                </div>
                <div class="natkb-card natkb-card--gold">
                    <i class="las la-lightbulb"></i>
                    <h4>@lang('Faster support tip')</h4>
                    <p>@lang('Always include the transaction reference or loan request detail when asking about money movement.')</p>
                </div>
            </aside>
        </section>
    </div>
@endsection
BLADE;
file_put_contents($viewPath, $view);

$css = <<<'CSS'

/* NATCODEV member knowledge base */
.natkb {
    color: #14251d;
}
.natkb a {
    text-decoration: none;
}
.natkb-hero,
.natkb-section,
.natkb-card,
.natkb-grid a {
    background: #fff;
    border: 1px solid #dfe9e3;
    border-radius: 8px;
    box-shadow: 0 16px 34px rgba(8, 44, 32, 0.08);
}
.natkb-hero {
    align-items: center;
    display: flex;
    gap: 18px;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 24px;
}
.natkb-hero span,
.natkb-section-head span {
    color: #087a45;
    display: block;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}
.natkb-hero h2 {
    color: #082c20;
    font-size: 32px;
    font-weight: 900;
    margin: 4px 0 6px;
}
.natkb-hero p,
.natkb-card p {
    color: #607269;
    margin: 0;
}
.natkb-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.natkb-hero-actions a,
.natkb-card a {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-weight: 900;
    gap: 7px;
    min-height: 42px;
    padding: 10px 14px;
}
.natkb-hero-actions a:first-child,
.natkb-card a {
    background: linear-gradient(135deg, #fff4cf, #c99a2e);
    color: #10251b;
}
.natkb-hero-actions a:last-child {
    background: #e9f7ef;
    color: #087a45;
}
.natkb-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    margin-bottom: 18px;
}
.natkb-grid a {
    align-items: center;
    color: #14251d;
    display: flex;
    flex-direction: column;
    font-weight: 900;
    gap: 8px;
    min-height: 104px;
    justify-content: center;
    padding: 14px;
    text-align: center;
}
.natkb-grid a:hover {
    border-color: rgba(201, 154, 46, 0.46);
    color: #087a45;
}
.natkb-grid i {
    align-items: center;
    background: #e9f7ef;
    border-radius: 8px;
    color: #087a45;
    display: flex;
    font-size: 22px;
    height: 42px;
    justify-content: center;
    width: 42px;
}
.natkb-layout {
    display: grid;
    gap: 18px;
    grid-template-columns: minmax(0, 1fr) 300px;
}
.natkb-main {
    display: grid;
    gap: 18px;
}
.natkb-section {
    padding: 20px;
}
.natkb-section-head h3 {
    color: #082c20;
    font-size: 22px;
    font-weight: 900;
    margin: 4px 0 16px;
}
.natkb-accordion .accordion-item {
    border-color: #edf2ef;
}
.natkb-accordion .accordion-button {
    color: #14251d;
    font-weight: 900;
}
.natkb-accordion .accordion-button:not(.collapsed) {
    background: #e9f7ef;
    color: #087a45;
}
.natkb-accordion .accordion-body {
    color: #53645a;
    font-weight: 700;
}
.natkb-aside {
    align-self: start;
    display: grid;
    gap: 18px;
    position: sticky;
    top: 104px;
}
.natkb-card {
    padding: 20px;
}
.natkb-card i {
    color: #c99a2e;
    font-size: 30px;
}
.natkb-card h4 {
    color: #082c20;
    font-size: 20px;
    font-weight: 900;
    margin: 8px 0;
}
.natkb-card a {
    margin-top: 14px;
}
.natkb-card--gold {
    background: #fff8e4;
    border-color: rgba(201, 154, 46, 0.35);
}
@media (max-width: 1199px) {
    .natkb-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .natkb-layout {
        grid-template-columns: 1fr;
    }
    .natkb-aside {
        position: static;
    }
}
@media (max-width: 767px) {
    .natkb-hero {
        align-items: flex-start;
        flex-direction: column;
    }
    .natkb-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .natkb-hero-actions,
    .natkb-hero-actions a {
        width: 100%;
    }
    .natkb-hero-actions a {
        justify-content: center;
    }
}
CSS;

$existingCss = file_get_contents($cssPath);
if (strpos($existingCss, '/* NATCODEV member knowledge base */') === false) {
    file_put_contents($cssPath, rtrim($existingCss) . $css . PHP_EOL);
}

echo "Support menu and knowledge base added.\n";
