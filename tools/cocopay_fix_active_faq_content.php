<?php

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=cocopay;charset=utf8mb4',
    'root',
    'root',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$now = date('Y-m-d H:i:s');
$activeTemplate = 'templates.indigo_fusion.';
$wrongTemplate = 'indigo_fusion';

$content = [
    'heading' => 'Frequently Asked Questions',
    'subheading' => 'Clear answers for NATCODEV Coconut Farmers Cooperative members, growers, savers, and value-chain partners.',
];

$faqs = [
    ['Membership', 'Who can join NATCODEV Coconut Farmers Cooperative?', 'Coconut growers, farm workers, processors, aggregators, input partners, and approved value-chain members can join once their identity and cooperative membership details are verified.'],
    ['Membership', 'Do I need a NATCODEV Growers Certificate to register?', 'Yes. New members must upload a NATCODEV Growers Certificate or another certificate issued by NATCODEV to their cooperative. This helps confirm that each account belongs to a genuine member or approved partner.'],
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

$pdo->beginTransaction();

$stmt = $pdo->prepare("SELECT id FROM frontends WHERE tempname = ? AND data_keys = 'faq.content' LIMIT 1");
$stmt->execute([$activeTemplate]);
$contentId = $stmt->fetchColumn();

if ($contentId) {
    $update = $pdo->prepare('UPDATE frontends SET data_values = ?, updated_at = ? WHERE id = ?');
    $update->execute([json_encode($content, JSON_UNESCAPED_SLASHES), $now, $contentId]);
} else {
    $insert = $pdo->prepare('INSERT INTO frontends (tempname, data_keys, data_values, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$activeTemplate, 'faq.content', json_encode($content, JSON_UNESCAPED_SLASHES), $now, $now]);
}

$pdo->prepare("DELETE FROM frontends WHERE tempname = ? AND data_keys = 'faq.element'")->execute([$activeTemplate]);
$insertFaq = $pdo->prepare('INSERT INTO frontends (tempname, data_keys, data_values, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');

foreach ($faqs as $faq) {
    $insertFaq->execute([
        $activeTemplate,
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

$pdo->prepare("UPDATE pages SET name = 'FAQ', secs = '[\"faq\"]', updated_at = ? WHERE tempname = ? AND slug = 'faq'")->execute([$now, $activeTemplate]);
$pdo->prepare("DELETE FROM pages WHERE tempname = ? AND slug = 'faq'")->execute([$wrongTemplate]);
$pdo->prepare("DELETE FROM frontends WHERE tempname = ? AND data_keys IN ('faq.content', 'faq.element')")->execute([$wrongTemplate]);

$pdo->commit();

echo "active_faq_rows=" . count($faqs) . "\n";
echo "active_template={$activeTemplate}\n";
echo "removed_wrong_template={$wrongTemplate}\n";

