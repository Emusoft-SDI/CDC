<?php

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=cocopay;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function updateFrontend(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare('UPDATE frontends SET data_values = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([
        json_encode($data, JSON_UNESCAPED_SLASHES),
        $id,
    ]);
}

function updateByKey(PDO $pdo, string $key, array $data): void
{
    $stmt = $pdo->prepare('UPDATE frontends SET data_values = ?, updated_at = NOW() WHERE data_keys = ?');
    $stmt->execute([
        json_encode($data, JSON_UNESCAPED_SLASHES),
        $key,
    ]);
}

function keepImage(PDO $pdo, int $id, array $data): array
{
    $stmt = $pdo->prepare('SELECT data_values FROM frontends WHERE id = ?');
    $stmt->execute([$id]);
    $existing = json_decode((string) $stmt->fetchColumn(), true) ?: [];

    foreach (['image', 'seo_image', 'blog_image_1', 'blog_image_2'] as $field) {
        if (array_key_exists($field, $existing) && !array_key_exists($field, $data)) {
            $data[$field] = $existing[$field];
        }
    }

    return $data;
}

$pdo->beginTransaction();

try {
    $pdo->exec("UPDATE general_settings SET site_name='NATCODEV Coconut Farmers Cooperative', email_from='info@natcodevcoop.local', cur_text='NGN', cur_sym='N', updated_at=NOW() WHERE id=1");

    updateFrontend($pdo, 1, [
        'seo_image' => '1',
        'keywords' => ['NATCODEV', 'coconut farmers cooperative', 'farmer finance', 'coconut value chain', 'cooperative banking', 'farm inputs', 'harvest savings'],
        'description' => 'NATCODEV Coconut Farmers Cooperative helps coconut growers save, access transparent credit, manage farm input financing, and coordinate harvest payments through a secure member platform.',
        'social_title' => 'NATCODEV Coconut Farmers Cooperative',
        'social_description' => 'Member finance, cooperative savings, farm input support, and coconut value-chain payments for organized growers.',
    ]);

    updateFrontend($pdo, 39, keepImage($pdo, 39, [
        'has_image' => '1',
        'heading' => 'Finance, Savings, and Growth for Coconut Farmers',
        'subheading' => 'NATCODEV Coconut Farmers Cooperative gives members a secure place to manage savings, request input loans, track cooperative payments, and build stronger coconut value chains.',
        'button_text' => 'Join the Cooperative',
        'button_link' => 'user/register',
    ]));

    updateFrontend($pdo, 24, keepImage($pdo, 24, [
        'has_image' => '1',
        'title' => 'About Us',
        'heading' => 'Built for coconut farmers, processors, and cooperative members.',
        'video_link' => 'https://www.youtube.com/embed/WOb4cj7izpE',
        'subheading' => 'NATCODEV Coconut Farmers Cooperative organizes farmers around transparent savings, fair access to credit, input support, harvest coordination, and reliable member records. Our goal is to help coconut growers move from scattered effort to shared prosperity.',
    ]));

    updateFrontend($pdo, 36, [
        'title' => 'Our Services',
        'heading' => 'Practical cooperative services for farm growth.',
    ]);

    updateFrontend($pdo, 35, [
        'heading' => 'Withdraw Member Funds',
        'subheading' => 'Members can request withdrawals from their cooperative balance through controlled approval workflows, keeping records clear and traceable.',
        'icon' => '<i class="fas fa-money-check-alt"></i>',
    ]);

    updateFrontend($pdo, 48, [
        'heading' => 'Member Savings',
        'subheading' => 'Members can deposit savings and cooperative contributions so funds are recorded, trackable, and ready for farm needs.',
        'icon' => '<i class="fas fa-credit-card"></i>',
    ]);

    updateFrontend($pdo, 49, [
        'heading' => 'Cooperative Transfers',
        'subheading' => 'Move funds between members, branches, partner banks, and approved beneficiaries with structured records for every transaction.',
        'icon' => '<i class="las la-exchange-alt"></i>',
    ]);

    updateFrontend($pdo, 33, [
        'heading' => 'Member Benefits',
        'subheading' => 'Tools that help coconut farmers save, borrow, transfer, and participate in cooperative programs.',
    ]);

    updateFrontend($pdo, 41, [
        'heading' => 'Produce Payment Tracking',
        'subheading' => 'Record cooperative payouts, member balances, and payment history tied to coconut harvest and value-chain activity.',
        'icon' => '<i class="fas fa-exchange-alt"></i>',
    ]);

    updateFrontend($pdo, 42, [
        'heading' => 'Farm Savings Plans',
        'subheading' => 'Use recurring savings and fixed savings plans to prepare for seedlings, fertilizer, labor, processing, and household goals.',
        'icon' => '<i class="fas fa-wallet"></i>',
    ]);

    updateFrontend($pdo, 43, [
        'heading' => 'Input and Harvest Loans',
        'subheading' => 'Members can apply for structured cooperative loans for farm inputs, equipment, processing, and seasonal harvest operations.',
        'icon' => '<i class="fas fa-coins"></i>',
    ]);

    updateFrontend($pdo, 44, [
        'heading' => 'Digital Records',
        'subheading' => 'Keep member transactions, approvals, balances, KYC, and support requests in one cooperative system.',
        'icon' => '<i class="fas fa-file-invoice-dollar"></i>',
    ]);

    updateFrontend($pdo, 45, [
        'heading' => 'Our Goal',
        'subheading' => 'To improve income, organization, and financial access for coconut farmers through a trusted cooperative platform.',
        'icon' => '<i class="las la-bullseye"></i>',
    ]);

    updateFrontend($pdo, 46, [
        'heading' => 'Our Vision',
        'subheading' => 'A stronger coconut economy where farmers, processors, and communities share in transparent cooperative growth.',
        'icon' => '<i class="far fa-eye"></i>',
    ]);

    updateFrontend($pdo, 47, [
        'heading' => 'Our Mission',
        'subheading' => 'We connect coconut farmers with savings, credit, training, market coordination, and reliable financial records.',
        'icon' => '<i class="las la-hourglass-start"></i>',
    ]);

    updateFrontend($pdo, 50, keepImage($pdo, 50, [
        'has_image' => '1',
        'title' => 'Why Choose Us?',
        'heading' => 'A cooperative system designed around farmers, not paperwork.',
        'btn_text' => 'Become a Member',
        'btn_link' => 'user/register',
    ]));

    updateFrontend($pdo, 51, [
        'heading' => 'Transparent Records',
        'subheading' => 'Member deposits, transfers, loan requests, and withdrawals are tracked clearly so the cooperative can account for every transaction.',
        'icon' => '<i class="fas fa-file-invoice-dollar"></i>',
    ]);

    updateFrontend($pdo, 52, [
        'heading' => 'Member Protection',
        'subheading' => 'Verification, approval steps, and account controls help protect cooperative funds and member information.',
        'icon' => '<i class="las la-user-shield"></i>',
    ]);

    updateFrontend($pdo, 53, [
        'title' => 'How It Works',
        'heading' => 'Simple steps for cooperative participation.',
    ]);

    updateFrontend($pdo, 54, [
        'heading' => 'Register as a Member',
        'subheading' => 'Create your cooperative account and provide basic member details.',
    ]);

    updateFrontend($pdo, 55, [
        'heading' => 'Complete Verification',
        'subheading' => 'Submit the required KYC and branch information so your account can be approved.',
    ]);

    updateFrontend($pdo, 56, [
        'heading' => 'Save or Deposit Funds',
        'subheading' => 'Add member savings, dues, or harvest proceeds to your cooperative account.',
    ]);

    updateFrontend($pdo, 57, [
        'heading' => 'Access Services',
        'subheading' => 'Apply for farm loans, savings plans, transfers, withdrawals, and cooperative support.',
    ]);

    updateFrontend($pdo, 58, [
        'heading' => 'Savings plans for farm inputs, harvest cycles, and family goals.',
    ]);

    updateFrontend($pdo, 59, [
        'title' => 'Cooperative Loan Schemes',
        'heading' => 'Input, harvest, and farm-growth loans for members.',
    ]);

    updateFrontend($pdo, 60, [
        'heading' => 'Serving organized coconut farmers and value-chain partners.',
        'subheading' => 'NATCODEV Coconut Farmers Cooperative supports member savings, farm credit, branch operations, transfers, and transparent financial reporting for the coconut sector.',
    ]);

    updateFrontend($pdo, 61, [
        'heading' => '1K+',
        'subheading' => 'Target Member Farmers',
        'icon' => '<i class="las la-user-circle"></i>',
    ]);

    updateFrontend($pdo, 62, [
        'heading' => '24/7',
        'subheading' => 'Member Record Access',
        'icon' => '<i class="las la-coins"></i>',
    ]);

    updateFrontend($pdo, 63, [
        'heading' => '10+',
        'subheading' => 'Cooperative Programs',
        'icon' => '<i class="las la-project-diagram"></i>',
    ]);

    updateFrontend($pdo, 92, [
        'heading' => 'Local',
        'subheading' => 'Farmer Communities Served',
        'icon' => '<i class="las la-globe-africa"></i>',
    ]);

    updateFrontend($pdo, 70, [
        'heading' => 'Frequently Asked Questions',
        'subheading' => 'Answers for farmers, members, branch staff, and cooperative partners.',
    ]);

    updateFrontend($pdo, 71, [
        'question' => 'Is cooperative account registration free?',
        'answer' => 'Yes. Members can create an account for local testing and cooperative onboarding. Official dues or contribution rules can be set by NATCODEV management.',
    ]);

    updateFrontend($pdo, 72, [
        'question' => 'Can members transfer funds?',
        'answer' => 'Yes. The platform supports own-cooperative transfers, other-bank beneficiaries, and wire transfer workflows when enabled and configured.',
    ]);

    updateFrontend($pdo, 88, [
        'question' => 'How does a farmer join?',
        'answer' => 'Use the registration page, provide the required member details, and complete verification through the cooperative branch or admin team.',
    ]);

    updateFrontend($pdo, 89, [
        'question' => 'Does NATCODEV share member information?',
        'answer' => 'Member information should be used only for cooperative operations, verification, reporting, and approved support services.',
    ]);

    updateFrontend($pdo, 115, [
        'question' => 'How can a member apply for a farm loan?',
        'answer' => 'After account verification, a member can choose an available loan plan, enter the requested amount, and submit the application for cooperative review.',
    ]);

    updateFrontend($pdo, 122, [
        'question' => 'How do fixed savings plans work?',
        'answer' => 'Members select an available fixed savings plan, deposit eligible funds, and track profit or maturity details from their account dashboard.',
    ]);

    updateFrontend($pdo, 73, keepImage($pdo, 73, [
        'has_image' => '1',
        'heading' => 'Subscribe for cooperative updates, training notices, and market information.',
    ]));

    updateFrontend($pdo, 74, [
        'text' => 'Copyright (c) 2026 NATCODEV Coconut Farmers Cooperative. All Rights Reserved.',
    ]);

    updateFrontend($pdo, 79, [
        'address_type' => 'Mobile Number',
        'address' => '+234 000 000 0000',
        'icon' => '<i class="fas fa-phone"></i>',
    ]);

    updateFrontend($pdo, 80, [
        'address_type' => 'Email Address',
        'address' => 'info@natcodevcoop.local',
        'icon' => '<i class="fas fa-envelope"></i>',
    ]);

    updateFrontend($pdo, 81, [
        'address_type' => 'Cooperative Office',
        'address' => 'NATCODEV Coconut Farmers Cooperative, Local Demo Branch',
        'icon' => '<i class="fas fa-map-marked"></i>',
    ]);

    updateFrontend($pdo, 94, [
        'heading' => 'Fixed Savings Scheme',
        'subheading' => 'Keep funds working while preparing for the next farm season.',
    ]);

    updateFrontend($pdo, 95, [
        'heading' => 'Monthly Cooperative Savings',
        'subheading' => 'Build discipline and capital for farm inputs, processing, and household needs.',
    ]);

    updateFrontend($pdo, 96, [
        'heading' => 'Our Cooperative Partners',
    ]);

    updateFrontend($pdo, 112, keepImage($pdo, 112, [
        'has_image' => '1',
        'heading' => 'Welcome Back, Member',
        'subheading' => 'Log in to manage your cooperative savings, transfers, loans, and member records.',
    ]));

    updateFrontend($pdo, 113, keepImage($pdo, 113, [
        'has_image' => '1',
        'heading' => 'Create Your Member Account',
        'subheading' => 'Provide accurate information so NATCODEV can verify and support your cooperative membership.',
    ]));

    updateFrontend($pdo, 114, keepImage($pdo, 114, [
        'has_image' => '1',
        'heading' => 'Reset Your Password',
        'subheading' => 'Recover access to your NATCODEV member account using your registered email or verification process.',
    ]));

    updateFrontend($pdo, 120, [
        'unverified_content' => 'Dear member, NATCODEV needs your KYC details to protect cooperative accounts and keep records accurate. Please submit the requested information through your dashboard.',
        'pending_content' => 'Dear member, your KYC information is currently under cooperative review. We will update your account once verification is complete.',
    ]);

    updateFrontend($pdo, 121, [
        'heading' => 'Registration Disabled',
        'subheading' => 'Member registration is currently closed. Please contact the nearest NATCODEV cooperative office.',
        'button_text' => 'Browse Home Page',
        'button_link' => '/',
    ]);

    updateFrontend($pdo, 123, [
        'heading' => 'THE COOPERATIVE PORTAL IS UNDER MAINTENANCE',
        'description' => '<h2 style="text-align:center;"><font size="6">We are improving the member portal.</font></h2><p>Please check back shortly. NATCODEV is updating services for coconut farmers and cooperative members.</p>',
    ]);

    updateFrontend($pdo, 124, keepImage($pdo, 124, [
        'has_image' => '1',
        'heading' => 'Account Restricted',
    ]));

    updateFrontend($pdo, 107, [
        'name' => 'Amina Okoro',
        'designation' => 'Coconut Farmer',
        'quote' => 'The cooperative record system helps me see my savings and plan for farm inputs before the next season.',
        'rating' => '5',
    ]);

    updateFrontend($pdo, 108, [
        'name' => 'Joseph Adewale',
        'designation' => 'Cooperative Member',
        'quote' => 'NATCODEV makes member transactions easier to follow, especially savings, transfers, and loan requests.',
        'rating' => '5',
    ]);

    updateFrontend($pdo, 109, [
        'name' => 'Mariam Bello',
        'designation' => 'Processor',
        'quote' => 'A shared platform gives farmers and processors a clearer view of payments, records, and cooperative programs.',
        'rating' => '5',
    ]);

    updateFrontend($pdo, 110, [
        'name' => 'Emeka Nwosu',
        'designation' => 'Branch Coordinator',
        'quote' => 'Branch staff can support members faster when records, deposits, withdrawals, and account details are organized.',
        'rating' => '5',
    ]);

    updateFrontend($pdo, 117, [
        'title' => 'Privacy Policy',
        'content' => '<div class="mb-5"><h3>Member Information</h3><p>NATCODEV Coconut Farmers Cooperative collects member information for account creation, verification, savings, loan processing, transfers, support, and cooperative reporting.</p></div><div class="mb-5"><h3>How We Use Data</h3><p>Information is used to operate member services, protect cooperative funds, process approved transactions, and communicate important updates.</p></div><div class="mb-5"><h3>Protection</h3><p>Members should keep their login details private. The cooperative should limit access to authorized officers and administrators.</p></div>',
    ]);

    updateFrontend($pdo, 118, [
        'title' => 'Terms of Service',
        'content' => '<div class="mb-5"><h3>Membership Use</h3><p>This portal supports NATCODEV Coconut Farmers Cooperative members with savings, transfers, farm finance requests, and account records.</p></div><div class="mb-5"><h3>Accurate Information</h3><p>Members are responsible for providing accurate registration, KYC, beneficiary, and transaction information.</p></div><div class="mb-5"><h3>Approval Workflows</h3><p>Loans, withdrawals, transfers, and cooperative services may require verification or administrator approval before completion.</p></div>',
    ]);

    $pdo->exec("UPDATE branches SET name='NATCODEV Main Cooperative Office', email='branch@natcodevcoop.local', address='NATCODEV Coconut Farmers Cooperative Main Office', updated_at=NOW() WHERE code='MAIN'");
    $pdo->exec("UPDATE loan_plans SET name='Coconut Farm Input Loan', instruction='Supports seedlings, fertilizer, labor, tools, and seasonal coconut farm operations.', updated_at=NOW() WHERE name='Demo Personal Loan'");
    $pdo->exec("UPDATE dps_plans SET name='Monthly Produce Savings', updated_at=NOW() WHERE name='Demo Monthly Savings'");
    $pdo->exec("UPDATE fdr_plans SET name='Harvest Fixed Savings', updated_at=NOW() WHERE name='Demo Fixed Deposit'");
    $pdo->exec("UPDATE other_banks SET name='Partner Cooperative Bank', instruction='Demo transfer route for approved partner bank beneficiaries.', updated_at=NOW() WHERE name='Demo External Bank'");
    $pdo->exec("UPDATE withdraw_methods SET name='Member Bank Withdrawal', description='Withdrawal method for NATCODEV cooperative member bank payouts.', updated_at=NOW() WHERE name='Demo Bank Withdrawal'");
    $pdo->exec("UPDATE gateways SET name='Cooperative Bank Deposit', alias='cooperative_bank_deposit', description='Manual deposit option for cooperative savings, dues, and farm program contributions.', updated_at=NOW() WHERE alias='manual_bank_deposit'");
    $pdo->exec("UPDATE gateway_currencies SET name='Cooperative Bank Deposit', gateway_alias='cooperative_bank_deposit', currency='NGN', symbol='N', updated_at=NOW() WHERE method_code=1000");

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "NATCODEV_CONTENT_REWRITE_OK\n";
