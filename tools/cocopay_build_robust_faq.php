<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$core = $root . '/core';

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$now = date('Y-m-d H:i:s');
$template = 'indigo_fusion';

$content = [
    'heading' => 'Frequently Asked Questions',
    'subheading' => 'Clear answers for NATCODEV Coconut Farmers Cooperative members, growers, savers, and value-chain partners.',
];

$existing = $pdo->prepare("SELECT id FROM frontends WHERE tempname = ? AND data_keys = 'faq.content' LIMIT 1");
$existing->execute([$template]);
$contentId = $existing->fetchColumn();

if ($contentId) {
    $stmt = $pdo->prepare('UPDATE frontends SET data_values = ?, updated_at = ? WHERE id = ?');
    $stmt->execute([json_encode($content, JSON_UNESCAPED_SLASHES), $now, $contentId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO frontends (tempname, data_keys, data_values, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$template, 'faq.content', json_encode($content, JSON_UNESCAPED_SLASHES), $now, $now]);
}

$faqs = [
    ['Membership', 'Who can join NATCODEV Coconut Farmers Cooperative?', 'Coconut growers, farm workers, processors, aggregators, input partners, and approved value-chain members can join once their identity and cooperative membership details are verified.'],
    ['Membership', 'Do I need a NATCODEV Growers Certificate to register?', 'Yes. New members must upload a NATCODEV Growers Certificate or another certificate issued by NATCODEV to their cooperative. This helps the cooperative confirm that each account belongs to a genuine member or approved partner.'],
    ['Membership', 'What file can I upload as my certificate?', 'You can upload a clear PDF, JPG, JPEG, or PNG copy of your certificate. The name, cooperative details, and certificate number should be readable before submission.'],
    ['Profile', 'Can I update my phone number, location, or certificate later?', 'Yes. Members can update profile details from the member dashboard. If a certificate is missing, expired, or unclear, upload a replacement from the profile area or contact support for guidance.'],
    ['Savings', 'What savings options are available?', 'Members can make regular wallet deposits, monthly savings, and harvest fixed savings. These are designed for farm inputs, seasonal operations, and cooperative financial planning.'],
    ['Savings', 'How do Monthly Savings and Harvest Fixed Savings differ?', 'Monthly Savings supports recurring contributions, while Harvest Fixed Savings is better for setting money aside until a planned harvest or cooperative milestone.'],
    ['Deposits', 'How do I add money to my cooperative wallet?', 'Go to Deposit, choose an available payment method, enter the amount, and follow the instructions. Approved deposits appear in your wallet and transaction ledger.'],
    ['Withdrawals', 'How do withdrawals work?', 'Submit a withdrawal request from the dashboard. The cooperative reviews the request, verifies the balance and account details, then approves or declines it based on the current rules.'],
    ['Loans', 'How do farm input loans work?', 'Farm input loans help eligible members access funds for seedlings, dwarf coconut species, fertilizer, irrigation, labour, processing, and harvest coordination. Each loan follows the plan terms shown before application.'],
    ['Loans', 'Why do I need to confirm a loan application?', 'Confirmation lets you review the amount, repayment terms, charges, and any custom form details before the request reaches the cooperative for approval.'],
    ['Loans', 'Can I download my loan record?', 'Yes. Approved or submitted loan records can be downloaded where the download action is available. The document should include the member, loan, amount, and cooperative reference details.'],
    ['Transfers', 'Can I transfer money to another member?', 'Yes. Use Transfer to move funds to an approved beneficiary or member account. Every transfer is recorded so both the member and cooperative can trace the transaction.'],
    ['Transactions', 'What is the difference between Money In and Money Out?', 'Money In means funds entered your wallet, such as deposits, loan disbursements, or incoming transfers. Money Out means funds left your wallet, such as withdrawals, outgoing transfers, or charges.'],
    ['Security', 'How do I keep my account secure?', 'Use a strong password, enable 2FA where available, keep your email and phone number current, and contact support immediately if you notice a transaction you did not authorize.'],
    ['Support', 'Where can I get help?', 'Use the Support menu for the knowledge base, tickets, and direct contact options. Support can help with registration, certificate upload, deposits, withdrawals, loans, transfers, and profile issues.'],
    ['Support', 'What should I include in a support ticket?', 'Include your full name, username, transaction reference if available, screenshots where useful, and a clear explanation of what happened. This helps the team resolve the issue faster.'],
    ['Cooperative', 'Does NATCODEV replace the local cooperative office?', 'No. The platform supports the cooperative office by organizing member records, savings, loans, and support activity in one place. Local cooperative leadership still manages approvals and member administration.'],
    ['Cooperative', 'Can the platform support African coconut farming realities?', 'Yes. The workflow is designed around local member verification, farm input finance, harvest savings, processor relationships, cooperative reporting, and practical support for coconut growers.'],
];

$pdo->prepare("DELETE FROM frontends WHERE tempname = ? AND data_keys = 'faq.element'")->execute([$template]);
$insert = $pdo->prepare('INSERT INTO frontends (tempname, data_keys, data_values, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');

foreach ($faqs as $faq) {
    $insert->execute([
        $template,
        'faq.element',
        json_encode([
            'category' => $faq[0],
            'question' => $faq[1],
            'answer' => $faq[2],
        ], JSON_UNESCAPED_SLASHES),
        $now,
        $now,
    ]);
}

$faqView = <<<'BLADE'
@php
    $faq = getContent('faq.content', true);
    $faqs = getContent('faq.element', false, null, true);
    $categories = $faqs->map(function ($item) {
        return @$item->data_values->category;
    })->filter()->unique()->values();
@endphp

@if ($faq && $faqs->count())
    <section id="faq" class="pt-100 pb-100 natcodev-faq-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7 col-lg-9 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.2s">
                    <div class="section-header text-center natcodev-faq-header">
                        <span class="natcodev-faq-kicker">@lang('Member Help Center')</span>
                        <h2 class="section-title">{{ __(@$faq->data_values->heading) }}</h2>
                        <p class="mt-2">{{ __(@$faq->data_values->subheading) }}</p>
                    </div>
                </div>
            </div>

            <div class="natcodev-faq-tools">
                <div class="natcodev-faq-search">
                    <i class="las la-search"></i>
                    <input type="search" id="natcodevFaqSearch" placeholder="@lang('Search membership, savings, loans, certificate, support...')" aria-label="@lang('Search FAQs')">
                </div>
                <div class="natcodev-faq-categories" aria-label="@lang('FAQ categories')">
                    <button type="button" class="active" data-faq-category="all">@lang('All')</button>
                    @foreach ($categories as $category)
                        <button type="button" data-faq-category="{{ \Illuminate\Support\Str::slug($category) }}">{{ __($category) }}</button>
                    @endforeach
                </div>
            </div>

            <div class="accordion custom--accordion natcodev-faq-accordion" id="faqAccordion">
                <div class="row gy-4 justify-content-center" id="natcodevFaqList">
                    @foreach ($faqs as $element)
                        @php
                            $category = @$element->data_values->category ?? 'General';
                            $searchText = \Illuminate\Support\Str::lower(($category . ' ' . @$element->data_values->question . ' ' . @$element->data_values->answer));
                        @endphp
                        <div class="col-lg-6 natcodev-faq-item wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.2s" data-faq-category="{{ \Illuminate\Support\Str::slug($category) }}" data-faq-search="{{ e($searchText) }}">
                            <div class="accordion-item">
                                <span class="natcodev-faq-badge">{{ __($category) }}</span>
                                <h2 class="accordion-header" id="h-{{ $element->id }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-{{ @$element->id }}" aria-expanded="false" aria-controls="c-{{ @$element->id }}">
                                        {{ __(@$element->data_values->question) }}
                                    </button>
                                </h2>
                                <div id="c-{{ $element->id }}" class="accordion-collapse collapse" aria-labelledby="h-{{ $element->id }}" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <p>{{ __(@$element->data_values->answer) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="natcodev-faq-empty d-none" id="natcodevFaqEmpty">
                    <i class="las la-life-ring"></i>
                    <h5>@lang('No FAQ matched your search')</h5>
                    <p>@lang('Try another keyword or open a support ticket from your member dashboard.')</p>
                </div>
            </div>
        </div>
    </section>

    @push('script')
        <script>
            (function () {
                const search = document.getElementById('natcodevFaqSearch');
                const items = Array.from(document.querySelectorAll('.natcodev-faq-item'));
                const buttons = Array.from(document.querySelectorAll('[data-faq-category]'));
                const empty = document.getElementById('natcodevFaqEmpty');
                let activeCategory = 'all';

                function filterFaqs() {
                    const term = (search.value || '').trim().toLowerCase();
                    let visible = 0;

                    items.forEach((item) => {
                        const categoryMatch = activeCategory === 'all' || item.dataset.faqCategory === activeCategory;
                        const textMatch = !term || item.dataset.faqSearch.includes(term);
                        const show = categoryMatch && textMatch;
                        item.classList.toggle('d-none', !show);
                        if (show) visible += 1;
                    });

                    empty.classList.toggle('d-none', visible !== 0);
                }

                buttons.forEach((button) => {
                    button.addEventListener('click', () => {
                        activeCategory = button.dataset.faqCategory;
                        buttons.forEach((item) => item.classList.toggle('active', item === button));
                        filterFaqs();
                    });
                });

                search.addEventListener('input', filterFaqs);
            })();
        </script>
    @endpush
@endif
BLADE;

file_put_contents($core . '/resources/views/templates/indigo_fusion/sections/faq.blade.php', $faqView);

$cssFile = $root . '/assets/templates/indigo_fusion/css/custom.css';
$css = file_exists($cssFile) ? file_get_contents($cssFile) : '';
$marker = '/* NATCODEV robust FAQ */';
$block = <<<'CSS'
/* NATCODEV robust FAQ */
.natcodev-faq-section {
    background: linear-gradient(180deg, #fffaf0 0%, #f5faf4 52%, #ffffff 100%);
    position: relative;
}

.natcodev-faq-header .section-title {
    color: #102f24;
}

.natcodev-faq-kicker {
    color: #b88721;
    display: inline-flex;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: .08em;
    margin-bottom: 10px;
    text-transform: uppercase;
}

.natcodev-faq-tools {
    background: rgba(255, 255, 255, .92);
    border: 1px solid rgba(22, 101, 52, .13);
    border-radius: 18px;
    box-shadow: 0 22px 60px rgba(18, 48, 38, .1);
    margin: 0 auto 30px;
    max-width: 980px;
    padding: 18px;
}

.natcodev-faq-search {
    align-items: center;
    background: #f8fbf7;
    border: 1px solid rgba(22, 101, 52, .16);
    border-radius: 14px;
    display: flex;
    gap: 10px;
    padding: 0 16px;
}

.natcodev-faq-search i {
    color: #b88721;
    font-size: 1.35rem;
}

.natcodev-faq-search input {
    background: transparent;
    border: 0;
    color: #102f24;
    font-weight: 600;
    min-height: 50px;
    outline: 0;
    width: 100%;
}

.natcodev-faq-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}

.natcodev-faq-categories button {
    background: #ffffff;
    border: 1px solid rgba(22, 101, 52, .18);
    border-radius: 999px;
    color: #24463a;
    font-size: .84rem;
    font-weight: 800;
    padding: 8px 14px;
    transition: all .2s ease;
}

.natcodev-faq-categories button.active,
.natcodev-faq-categories button:hover {
    background: linear-gradient(135deg, #0f6b3d, #143d30);
    border-color: transparent;
    box-shadow: 0 10px 24px rgba(15, 107, 61, .18);
    color: #ffffff;
}

.natcodev-faq-accordion .accordion-item {
    background: rgba(255, 255, 255, .96);
    border: 1px solid rgba(22, 101, 52, .12);
    border-radius: 16px;
    box-shadow: 0 18px 44px rgba(18, 48, 38, .08);
    min-height: 100%;
    overflow: hidden;
    padding-top: 14px;
}

.natcodev-faq-accordion .accordion-button {
    color: #143d30;
    font-size: 1rem;
    font-weight: 800;
    padding: 10px 22px 18px;
}

.natcodev-faq-accordion .accordion-body {
    border-top: 1px solid rgba(22, 101, 52, .08);
    color: #496257;
    line-height: 1.7;
    padding: 18px 22px 22px;
}

.natcodev-faq-badge {
    background: rgba(184, 135, 33, .12);
    border-radius: 999px;
    color: #8b671d;
    display: inline-flex;
    font-size: .72rem;
    font-weight: 900;
    margin-left: 22px;
    padding: 5px 10px;
    text-transform: uppercase;
}

.natcodev-faq-empty {
    background: #ffffff;
    border: 1px dashed rgba(22, 101, 52, .24);
    border-radius: 16px;
    color: #496257;
    margin: 24px auto 0;
    max-width: 520px;
    padding: 34px;
    text-align: center;
}

.natcodev-faq-empty i {
    color: #b88721;
    font-size: 2rem;
}

@media (max-width: 575px) {
    .natcodev-faq-tools {
        border-radius: 12px;
        padding: 14px;
    }

    .natcodev-faq-categories button {
        flex: 1 1 auto;
    }
}
CSS;

if (strpos($css, $marker) === false) {
    file_put_contents($cssFile, rtrim($css) . "\n\n" . $block . "\n");
}

echo "FAQ content rows inserted: " . count($faqs) . "\n";
echo "FAQ view upgraded.\n";
