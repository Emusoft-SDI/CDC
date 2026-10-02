<?php
declare(strict_types=1);

/**
 * Registration wizards, shared by every registration board.
 *
 * The problem this solves
 * -----------------------
 * apply.php asked for roughly eighteen fields on one page and drew a decorative
 * "Step 1 of 6" strip that did nothing. Meanwhile the other boards (buyer, seller,
 * learner, provider) collected a minimal set but had no way to resume: if the OTP
 * never arrived, the half-finished registration was simply lost, and re-registering
 * with the same email failed as "already registered".
 *
 * How it works now
 * ----------------
 * Every board has a minimal sign-up (name, email, phone, password). That creates the
 * account as needs_confirmation and a row in registration_drafts, then sends an OTP.
 * The wizard is only reachable once that OTP has been verified, and completing the
 * wizard marks the email verified and activates the account.
 *
 * A draft holds the collected data as JSON keyed by step, so a registration can be
 * abandoned at any step and resumed later: enter the email, receive a fresh OTP, and
 * continue at the first step that is not yet complete.
 *
 * Board-specific output (a grower application row, a provider registry row, a seller
 * store row) is written by registration_wizard_finalize() when the last step is saved.
 */

if (!function_exists('registration_wizard_boards')) {
    /**
     * Every registration board and the steps it walks through.
     *
     * Field keys are the column names they are written to where a column exists, which
     * keeps finalize() short and makes the payload self-describing.
     *
     * @return array<string, array<string, mixed>>
     */
    function registration_wizard_boards(): array
    {
        return [
            'grower' => [
                'label' => 'Coconut Grower Registration',
                'short' => 'Grower',
                'entry' => 'apply.php',
                'dashboard' => 'dashboard/index.php',
                'role' => 'grower',
                'platform_role' => 'grower',
                'steps' => [
                    [
                        'key' => 'personal',
                        'title' => 'Personal Details',
                        'intro' => 'Who you are and how we reach you.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'min_age' => 16, 'max_age' => 100],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                            ['name' => 'alternate_phone', 'label' => 'Alternate Phone', 'type' => 'tel'],
                            ['name' => 'alternate_email', 'label' => 'Alternate Email', 'type' => 'email'],
                            ['name' => 'member_type', 'label' => 'Registration Type', 'type' => 'select', 'required' => true,
                                'options' => ['individual' => 'Individual Farmer', 'corporate' => 'Corporate Farm / Company', 'cooperative' => 'Cooperative']],
                            ['name' => 'business_name', 'label' => 'Business / Farm Name', 'type' => 'text', 'required' => true, 'show_if' => ['member_type' => 'corporate']],
                            ['name' => 'business_registration_number', 'label' => 'Business Registration Number (CAC)', 'type' => 'text', 'required' => true, 'show_if' => ['member_type' => 'corporate']],
                            ['name' => 'representative_name', 'label' => 'Representative Name', 'type' => 'text', 'required' => true, 'show_if' => ['member_type' => 'corporate']],
                            ['name' => 'cooperative_name', 'label' => 'Cooperative Name', 'type' => 'text', 'required' => true, 'show_if' => ['member_type' => 'cooperative']],
                            ['name' => 'cooperative_registration_number', 'label' => 'Cooperative Registration Number', 'type' => 'text', 'required' => true, 'show_if' => ['member_type' => 'cooperative']],
                            ['name' => 'cooperative_members_count', 'label' => 'Number of Members', 'type' => 'number', 'min' => '1', 'required' => true, 'show_if' => ['member_type' => 'cooperative']],
                        ],
                    ],
                    [
                        'key' => 'location',
                        'title' => 'Farm Location',
                        'intro' => 'Where the farm is, so it can be mapped and verified.',
                        'fields' => [
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'lga_id', 'label' => 'LGA', 'type' => 'select', 'required' => true, 'source' => 'lgas'],
                            ['name' => 'street_address', 'label' => 'Nearest Landmark / Address', 'type' => 'text'],
                            ['name' => 'land_ownership_status', 'label' => 'Land Ownership', 'type' => 'select',
                                'options' => ['owned' => 'Owned', 'family' => 'Family Land', 'leased' => 'Leased / Rented', 'community' => 'Community Allocated', 'other' => 'Other']],
                                ['name' => 'land_ownership_other', 'label' => 'Please specify the land ownership', 'type' => 'text', 'required' => true, 'show_if' => ['land_ownership_status' => 'other']],
                            ['name' => 'land_title_details', 'label' => 'Title Details (if any)', 'type' => 'text'],
                            ['name' => 'climate_zone', 'label' => 'Climate Zone', 'type' => 'text', 'suggestions' => ['Rainforest', 'Derived savannah', 'Guinea savannah', 'Sudan savannah', 'Sahel', 'Mangrove / coastal', 'Montane / highland']],
                        ],
                    ],
                    [
                        'key' => 'stands',
                        'title' => 'Coconut Stands',
                        'intro' => 'What is planted on the farm today.',
                        'fields' => [
                            ['name' => 'farm_size', 'label' => 'Farm Size (hectares)', 'type' => 'number', 'required' => true, 'step_attr' => '0.1', 'min' => '0.1'],
                            ['name' => 'estimated_tree_count', 'label' => 'Number of Coconut Stands', 'type' => 'number', 'required' => true],
                            ['name' => 'coconut_variety', 'label' => 'Coconut Variety', 'type' => 'text', 'suggestions' => ['West African Tall', 'Malayan Dwarf', 'Hybrid (MYD x WAT)', 'Local / Native', 'Mixed varieties']],
                            ['name' => 'production_stage', 'label' => 'Production Stage', 'type' => 'select',
                                'options' => ['seedling' => 'Seedling / Nursery', 'young' => 'Young (1-4 years)', 'bearing' => 'Bearing (5+ years)', 'mixed' => 'Mixed Ages']],
                            ['name' => 'annual_yield_estimate', 'label' => 'Estimated Annual Yield', 'type' => 'text'],
                            ['name' => 'current_farm_activities', 'label' => 'Current Farm Activities', 'type' => 'textarea'],
                        ],
                    ],
                    [
                        'key' => 'intercrops',
                        'title' => 'Intercrops & Practices',
                        'intro' => 'What else grows on the farm, and how it is managed.',
                        'fields' => [
                            ['name' => 'intercrops', 'label' => 'Intercrops', 'type' => 'text', 'placeholder' => 'e.g. Cassava, Plantain, Cocoa'],
                            ['name' => 'livestock_integration', 'label' => 'Livestock Activities', 'type' => 'text', 'placeholder' => 'e.g. Poultry, Goats, Fish Farming'],
                            ['name' => 'farming_practices', 'label' => 'Farming Practices', 'type' => 'textarea'],
                            ['name' => 'soil_type', 'label' => 'Soil Type', 'type' => 'text', 'suggestions' => ['Sandy', 'Sandy loam', 'Loamy', 'Clay loam', 'Clay', 'Laterite', 'Peat / marshy']],
                            ['name' => 'water_source', 'label' => 'Water Source', 'type' => 'text', 'suggestions' => ['Rain-fed only', 'Borehole', 'Well', 'Stream / River', 'Dam / Reservoir', 'Irrigation scheme']],
                            ['name' => 'irrigation_method', 'label' => 'Irrigation Method', 'type' => 'text', 'suggestions' => ['None / Rain-fed', 'Manual watering', 'Drip', 'Sprinkler', 'Furrow', 'Flood']],
                        ],
                    ],
                    [
                        'key' => 'documents',
                        'title' => 'Documents & Challenges',
                        'intro' => 'Compliance details plus the support you need. Uploads are done from your dashboard.',
                        'fields' => [
                            ['name' => 'id_type', 'label' => 'Identification Type', 'type' => 'select',
                                'options' => ['nin' => 'National Identity Number (NIN)', 'voter' => "Voter's Card", 'drivers' => "Driver's Licence", 'passport' => 'International Passport']],
                            ['name' => 'id_number', 'label' => 'Identification Number', 'type' => 'text'],
                            ['name' => 'major_challenges', 'label' => 'Major Challenges', 'type' => 'textarea'],
                            ['name' => 'support_needs', 'label' => 'Support Needed', 'type' => 'textarea'],
                            ['name' => 'market_channels', 'label' => 'Current Market Channels', 'type' => 'text', 'suggestions' => ['Local market', 'Aggregators', 'Cooperatives', 'Direct buyers', 'Processors', 'Exporters', 'Online']],
                            ['name' => 'remarks', 'label' => 'Anything Else', 'type' => 'textarea'],
                        ],
                    ],
                    [
                        'key' => 'verification',
                        'title' => 'Review & Submit',
                        'intro' => 'Check your answers, accept the terms, and submit for review.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I confirm the information given is accurate and I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],

            'buyer' => [
                'label' => 'Buyer Registration',
                'short' => 'Buyer',
                'entry' => 'buyer/register.php',
                'dashboard' => 'buyer/index.php',
                'role' => 'grower',
                'platform_role' => 'buyer',
                'steps' => [
                    [
                        'key' => 'account',
                        'title' => 'Your Details',
                        'intro' => 'How sellers and our delivery partners reach you.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'min_age' => 16, 'max_age' => 100],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                        ],
                    ],
                    [
                        'key' => 'delivery',
                        'title' => 'Delivery',
                        'intro' => 'Where your orders usually go.',
                        'fields' => [
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'lga_id', 'label' => 'LGA', 'type' => 'select', 'required' => true, 'source' => 'lgas'],
                            ['name' => 'street_address', 'label' => 'Delivery Address', 'type' => 'text'],
                            ['name' => 'market_channels', 'label' => 'What do you buy most?', 'type' => 'text', 'placeholder' => 'e.g. Seedlings, Copra, Fresh Nuts'],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your buyer access is ready.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],

            'seller' => [
                'label' => 'Seller Registration',
                'short' => 'Seller',
                'entry' => 'market/seller-register.php',
                'dashboard' => 'market/seller-central.php',
                'role' => 'grower',
                'platform_role' => 'seller',
                'steps' => [
                    [
                        'key' => 'account',
                        'title' => 'Your Details',
                        'intro' => 'The person behind the store.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'min_age' => 16, 'max_age' => 100],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                        ],
                    ],
                    [
                        'key' => 'store',
                        'title' => 'Your Store',
                        'intro' => 'How buyers will see you on the marketplace.',
                        'fields' => [
                            ['name' => 'store_name', 'label' => 'Store Name', 'type' => 'text', 'required' => true],
                            ['name' => 'seller_type', 'label' => 'Seller Type', 'type' => 'select', 'required' => true,
                                'options' => ['grower' => 'Grower / Farmer', 'provider' => 'Service Provider', 'input_supplier' => 'Input Supplier', 'aggregator' => 'Aggregator / Offtaker', 'other' => 'Other']],
                                ['name' => 'seller_type_other', 'label' => 'Please specify your seller type', 'type' => 'text', 'required' => true, 'show_if' => ['seller_type' => 'other']],
                            ['name' => 'description', 'label' => 'What do you sell?', 'type' => 'textarea'],
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'lga_id', 'label' => 'LGA', 'type' => 'select', 'required' => true, 'source' => 'lgas'],
                            ['name' => 'location_label', 'label' => 'Location Description', 'type' => 'text'],
                        ],
                    ],
                    [
                        'key' => 'fulfilment',
                        'title' => 'Fulfilment',
                        'intro' => 'How you get orders to buyers.',
                        'fields' => [
                            ['name' => 'fulfillment_options', 'label' => 'Fulfilment Options', 'type' => 'text', 'placeholder' => 'e.g. Pickup, Local delivery, Nationwide shipping', 'suggestions' => ['Pickup', 'Local delivery', 'Nationwide shipping']],
                            ['name' => 'coverage_area', 'label' => 'Coverage Area', 'type' => 'text', 'placeholder' => 'e.g. Lagos and Ogun', 'suggestions' => ['Lagos and Ogun', 'South West', 'South East', 'South South', 'North Central', 'Nationwide']],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your store is created.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I accept the Terms of Service, Privacy Policy and marketplace rules.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],

            'learner' => [
                'label' => 'Academy Learner Registration',
                'short' => 'Learner',
                'entry' => 'academy/register.php',
                'dashboard' => 'academy/dashboard.php',
                'role' => 'grower',
                'platform_role' => 'learner',
                'steps' => [
                    [
                        'key' => 'account',
                        'title' => 'Your Details',
                        'intro' => 'So your certificate carries the right name.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'min_age' => 16, 'max_age' => 100],
                        ],
                    ],
                    [
                        'key' => 'learning',
                        'title' => 'Learning Profile',
                        'intro' => 'Helps us recommend the right courses.',
                        'fields' => [
                            ['name' => 'education_level', 'label' => 'Education Level', 'type' => 'select',
                                'options' => ['primary' => 'Primary', 'secondary' => 'Secondary', 'tertiary' => 'Tertiary', 'other' => 'Other']],
                                ['name' => 'education_level_other', 'label' => 'Please specify your education level', 'type' => 'text', 'required' => true, 'show_if' => ['education_level' => 'other']],
                            ['name' => 'farming_experience_years', 'label' => 'Years of Farming Experience', 'type' => 'number'],
                            ['name' => 'specialization', 'label' => 'Area of Interest', 'type' => 'text', 'placeholder' => 'e.g. Nursery management, Coconut processing', 'suggestions' => ['Nursery management', 'Coconut processing', 'Pest and disease control', 'Harvesting', 'Marketing', 'Farm records']],
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'source' => 'states'],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your Academy access is ready.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],

            'provider' => [
                'label' => 'Service Provider Registration',
                'short' => 'Provider',
                'entry' => 'provider/index.php',
                'dashboard' => 'provider/dashboard.php',
                'role' => 'provider',
                'platform_role' => 'provider',
                'steps' => [
                    [
                        'key' => 'account',
                        'title' => 'Contact Person',
                        'intro' => 'Who we deal with day to day.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'required' => true, 'min_age' => 16, 'max_age' => 100],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                        ],
                    ],
                    [
                        'key' => 'company',
                        'title' => 'Company',
                        'intro' => 'Your registered business details.',
                        'fields' => [
                            ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'required' => true],
                            ['name' => 'provider_type', 'label' => 'Provider Type', 'type' => 'select', 'required' => true,
                                'options' => ['input_supplier' => 'Input Supplier', 'equipment' => 'Equipment / Machinery', 'logistics' => 'Logistics', 'processing' => 'Processing', 'advisory' => 'Advisory / Extension', 'finance' => 'Finance', 'other' => 'Other']],
                                ['name' => 'provider_type_other', 'label' => 'Please specify your provider type', 'type' => 'text', 'required' => true, 'show_if' => ['provider_type' => 'other']],
                            ['name' => 'business_registration_number', 'label' => 'Registration Number (CAC)', 'type' => 'text'],
                            ['name' => 'tax_id', 'label' => 'Tax Identification Number', 'type' => 'text'],
                            ['name' => 'contact_person', 'label' => 'Contact Person Name', 'type' => 'text'],
                            ['name' => 'business_address', 'label' => 'Business Address', 'type' => 'text'],
                            ['name' => 'website', 'label' => 'Website', 'type' => 'text'],
                            ['name' => 'company_description', 'label' => 'What your company does', 'type' => 'textarea'],
                        ],
                    ],
                    [
                        'key' => 'coverage',
                        'title' => 'Coverage',
                        'intro' => 'Where you can deliver.',
                        'fields' => [
                            ['name' => 'coverage_scope', 'label' => 'Coverage Scope', 'type' => 'select', 'required' => true,
                                'options' => ['local' => 'Local / One state', 'regional' => 'Regional', 'national' => 'Nationwide']],
                            ['name' => 'state', 'label' => 'Primary State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'states_served', 'label' => 'Other States Served', 'type' => 'text', 'placeholder' => 'e.g. Ogun, Oyo, Osun', 'suggestions' => ['Ogun', 'Oyo', 'Osun', 'Ondo', 'Ekiti', 'Cross River', 'Akwa Ibom', 'Rivers']],
                            ['name' => 'years_in_business', 'label' => 'Years in Business', 'type' => 'number'],
                        ],
                    ],
                    [
                        'key' => 'capability',
                        'title' => 'Capacity',
                        'intro' => 'What you can supply and how.',
                        'fields' => [
                            ['name' => 'primary_category', 'label' => 'Primary Category', 'type' => 'text', 'suggestions' => ['Input supplies', 'Equipment', 'Logistics', 'Processing', 'Advisory', 'Finance', 'Other']],
                            ['name' => 'service_categories', 'label' => 'Service Categories', 'type' => 'text', 'suggestions' => ['Land preparation', 'Planting', 'Pruning', 'Harvesting', 'Transport', 'Storage', 'Training']],
                            ['name' => 'input_categories', 'label' => 'Input Categories', 'type' => 'text'],
                            ['name' => 'supply_capacity', 'label' => 'Supply Capacity', 'type' => 'text'],
                            ['name' => 'delivery_options', 'label' => 'Delivery Options', 'type' => 'text', 'suggestions' => ['Pickup only', 'Local delivery', 'Nationwide shipping', 'Buyer arranges']],
                            ['name' => 'quality_assurance', 'label' => 'Quality Assurance', 'type' => 'text'],
                            ['name' => 'certifications', 'label' => 'Certifications', 'type' => 'text'],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your provider profile is submitted for review.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('registration_wizard_board')) {
    /** @return array<string, mixed>|null */
    function registration_wizard_board(string $board): ?array
    {
        $boards = registration_wizard_boards();
        $board = strtolower(preg_replace('/[^a-z0-9_]/i', '', $board) ?? '');

        return $boards[$board] ?? null;
    }
}

if (!function_exists('registration_wizard_steps')) {
    /** @return array<int, array<string, mixed>> */
    function registration_wizard_steps(array $board): array
    {
        return $board['steps'] ?? [];
    }
}

if (!function_exists('registration_wizard_steps_total')) {
    function registration_wizard_steps_total(array $board): int
    {
        return count(registration_wizard_steps($board));
    }
}

if (!function_exists('registration_wizard_step_at')) {
    /** @return array<string, mixed>|null */
    function registration_wizard_step_at(array $board, int $index): ?array
    {
        $steps = registration_wizard_steps($board);

        return $steps[$index] ?? null;
    }
}

/* ---------------------------------------------------------------------- storage */

if (!function_exists('registration_wizard_ensure_schema')) {
    function registration_wizard_ensure_schema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_drafts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                board VARCHAR(40) NOT NULL,
                user_id INT NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(40) NULL,
                payload LONGTEXT NULL,
                current_step INT NOT NULL DEFAULT 1,
                otp_verified TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                submitted_at DATETIME NULL,
                UNIQUE KEY uniq_registration_board_email (board, email),
                KEY idx_registration_user (user_id),
                KEY idx_registration_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('registration_wizard_payload')) {
    /** @return array<string, array<string, mixed>> */
    function registration_wizard_payload(array $draft): array
    {
        $decoded = json_decode((string) ($draft['payload'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('registration_wizard_all_values')) {
    /** Flatten every step's values into one map keyed by field name. */
    function registration_wizard_all_values(array $payload): array
    {
        $values = [];
        foreach ($payload as $stepValues) {
            if (is_array($stepValues)) {
                foreach ($stepValues as $key => $value) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }
}

if (!function_exists('registration_wizard_find_by_email')) {
    /** @return array<string, mixed>|null */
    function registration_wizard_find_by_email(PDO $pdo, string $board, string $email): ?array
    {
        registration_wizard_ensure_schema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM registration_drafts WHERE board = ? AND LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$board, trim($email)]);

        return $stmt->fetch() ?: null;
    }
}

if (!function_exists('registration_wizard_find_by_user')) {
    /** @return array<string, mixed>|null */
    function registration_wizard_find_by_user(PDO $pdo, string $board, int $userId, bool $unfinishedOnly = false): ?array
    {
        registration_wizard_ensure_schema($pdo);
        $sql = 'SELECT * FROM registration_drafts WHERE board = ? AND user_id = ?';
        if ($unfinishedOnly) {
            $sql .= " AND status = 'draft'";
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$board, $userId]);

        return $stmt->fetch() ?: null;
    }
}

if (!function_exists('registration_wizard_find')) {
    /** @return array<string, mixed>|null */
    function registration_wizard_find(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM registration_drafts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }
}

if (!function_exists('registration_wizard_save_progress')) {
    function registration_wizard_save_progress(PDO $pdo, int $draftId, array $payload, int $currentStep): void
    {
        $pdo->prepare('UPDATE registration_drafts SET payload = ?, current_step = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($payload), $currentStep, $draftId]);
    }
}

if (!function_exists('registration_wizard_next_destination')) {
    /** Where verify-otp.php should send the user after a successful code check. */
    function registration_wizard_next_destination(string $board): string
    {
        return 'register-wizard.php?board=' . rawurlencode($board) . '&resume=1';
    }
}

if (!function_exists('registration_wizard_field_visible')) {
    function registration_wizard_field_visible(array $field, array $values): bool
    {
        $conditions = $field['show_if'] ?? null;
        if (!is_array($conditions) || !$conditions) {
            return true;
        }
        foreach ($conditions as $key => $expected) {
            if ((string) ($values[$key] ?? '') !== (string) $expected) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('registration_wizard_step_complete')) {
    /** A step counts as done once every visible required field has a value. */
    function registration_wizard_step_complete(array $step, array $payload): bool
    {
        $values = $payload[$step['key']] ?? [];
        foreach ($step['fields'] as $field) {
            if (empty($field['required'])) {
                continue;
            }
            if (!registration_wizard_field_visible($field, $values)) {
                continue;
            }
            $value = $values[$field['name']] ?? '';
            if (is_array($value) ? $value === [] : trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('registration_wizard_first_incomplete')) {
    /** Index of the first step still needing input, or the last step if all are done. */
    function registration_wizard_first_incomplete(array $board, array $payload): int
    {
        $steps = registration_wizard_steps($board);
        foreach ($steps as $index => $step) {
            if (!registration_wizard_step_complete($step, $payload)) {
                return $index;
            }
        }

        return max(0, count($steps) - 1);
    }
}

/* ------------------------------------------------------------------- validation */

if (!function_exists('registration_wizard_age_from_dob')) {
    /**
     * Whole years between a date of birth and today, or null when it is unusable.
     */
    function registration_wizard_age_from_dob(string $dob): ?int
    {
        $dob = trim($dob);
        if ($dob === '') {
            return null;
        }
        $born = DateTimeImmutable::createFromFormat('!Y-m-d', $dob);
        if (!$born || $born->format('Y-m-d') !== $dob) {
            return null;
        }
        $today = new DateTimeImmutable('today');
        if ($born > $today) {
            return null;
        }

        return $born->diff($today)->y;
    }
}

if (!function_exists('registration_wizard_dob_bounds')) {
    /**
     * The earliest and latest date of birth allowed by a field's age rules, so the date
     * picker itself prevents an impossible entry.
     *
     * @return array{min: string, max: string}
     */
    function registration_wizard_dob_bounds(array $field): array
    {
        $today = new DateTimeImmutable('today');
        $minAge = isset($field['min_age']) ? (int) $field['min_age'] : 16;
        $maxAge = isset($field['max_age']) ? (int) $field['max_age'] : 100;

        return [
            // to be at least $minAge today you were born on or before this date
            'max' => $today->modify('-' . $minAge . ' years')->format('Y-m-d'),
            // to be no older than $maxAge today you were born on or after this date
            'min' => $today->modify('-' . $maxAge . ' years')->modify('+1 day')->format('Y-m-d'),
        ];
    }
}

if (!function_exists('registration_wizard_validate_step')) {
    /**
     * @return array{values: array<string,mixed>, errors: array<int,string>}
     */
    function registration_wizard_validate_step(array $step, array $input, array $existing = []): array
    {
        $values = [];
        $errors = [];

        foreach ($step['fields'] as $field) {
            $name = (string) $field['name'];
            $type = (string) ($field['type'] ?? 'text');
            $label = (string) ($field['label'] ?? $name);

            if ($type === 'checkbox') {
                $values[$name] = !empty($input[$name]) ? 1 : 0;
                if (!empty($field['required']) && $values[$name] !== 1) {
                    $errors[] = 'Please tick: ' . $label;
                }
                continue;
            }

            $raw = trim((string) ($input[$name] ?? ''));
            if (!registration_wizard_field_visible($field, array_merge($existing, $input))) {
                $values[$name] = $raw;
                continue;
            }

            if (!empty($field['required']) && $raw === '') {
                $errors[] = $label . ' is required.';
                $values[$name] = '';
                continue;
            }

            if ($raw !== '') {
                if ($type === 'date') {
                    $age = registration_wizard_age_from_dob($raw);
                    if ($age === null) {
                        $errors[] = $label . ' must be a valid date of birth (YYYY-MM-DD) that is not in the future.';
                    } else {
                        $minAge = isset($field['min_age']) ? (int) $field['min_age'] : 16;
                        $maxAge = isset($field['max_age']) ? (int) $field['max_age'] : 100;
                        if ($age < $minAge) {
                            $errors[] = $label . ' means you are ' . $age . '. You must be at least ' . $minAge . ' years old.';
                        } elseif ($age > $maxAge) {
                            $errors[] = $label . ' does not look correct. Please check the year.';
                        }
                    }
                }
                if ($type === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = $label . ' must be a valid email address.';
                }
                if ($type === 'tel') {
                    $digits = preg_replace('/\D+/', '', $raw) ?? '';
                    if (strlen($digits) < 10 || strlen($digits) > 15) {
                        $errors[] = $label . ' must be a valid phone number.';
                    }
                }
                if ($type === 'number' && !is_numeric($raw)) {
                    $errors[] = $label . ' must be a number.';
                }
                if (isset($field['options']) && is_array($field['options']) && !array_key_exists($raw, $field['options'])) {
                    $errors[] = $label . ' has an invalid selection.';
                }
            }

            $values[$name] = $raw;
        }

        return ['values' => $values, 'errors' => $errors];
    }
}

/* -------------------------------------------------------------------- rendering */

if (!function_exists('registration_wizard_progress_html')) {
    function registration_wizard_progress_html(array $board, int $current, array $payload): string
    {
        $steps = registration_wizard_steps($board);
        $html = '<ol class="rw-progress" aria-label="Registration progress">';
        foreach ($steps as $index => $step) {
            $done = registration_wizard_step_complete($step, $payload);
            $state = $index === $current ? 'is-current' : ($done ? 'is-done' : '');
            $html .= '<li class="' . $state . '"' . ($index === $current ? ' aria-current="step"' : '') . '>'
                . '<span class="rw-progress__n">' . ($done && $index !== $current ? '&#10003;' : ($index + 1)) . '</span>'
                . '<span class="rw-progress__t">' . e((string) $step['title']) . '</span></li>';
        }

        return $html . '</ol>';
    }
}

if (!function_exists('registration_wizard_states')) {
    /** @return array<int, array<string, string>> */
    function registration_wizard_states(PDO $pdo): array
    {
        try {
            return $pdo->query('SELECT id, state_name FROM nigeria_states ORDER BY state_name')->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('registration_wizard_fields_html')) {
    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $options pdo, id_prefix
     */
    function registration_wizard_fields_html(array $step, array $values, array $options = []): string
    {
        $pdo = $options['pdo'] ?? null;
        $prefix = (string) ($options['id_prefix'] ?? 'rw');
        $html = '<div class="rw-grid">';

        foreach ($step['fields'] as $field) {
            $name = (string) $field['name'];
            $type = (string) ($field['type'] ?? 'text');
            $label = (string) ($field['label'] ?? $name);
            $value = (string) ($values[$name] ?? '');
            $required = !empty($field['required']);
            $id = $prefix . '-' . $name;
            $visible = registration_wizard_field_visible($field, $values);
            $wide = in_array($type, ['textarea', 'checkbox'], true) ? ' rw-wide' : '';
            $style = $visible ? '' : ' style="display:none"';

            // The conditions travel with the field so the page can reveal or hide it as
            // the user changes the controlling dropdown.
            $showIfAttr = '';
            if (!empty($field['show_if']) && is_array($field['show_if'])) {
                $showIfAttr = ' data-show-if="' . e((string) json_encode($field['show_if'], JSON_UNESCAPED_SLASHES)) . '"';
            }
            $html .= '<div class="rw-field' . $wide . '" data-field="' . e($name) . '"' . $showIfAttr . $style . '>';

            if ($type === 'checkbox') {
                $checked = $value === '1' ? ' checked' : '';
                $html .= '<label class="rw-check"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="1"'
                    . $checked . ($required ? ' required' : '') . '> <span>' . e($label) . '</span></label></div>';
                continue;
            }

            $html .= '<label for="' . e($id) . '">' . e($label) . ($required ? ' <span class="rw-req">*</span>' : '') . '</label>';

            if ($type === 'select') {
                $source = (string) ($field['source'] ?? '');
                $html .= '<select id="' . e($id) . '" name="' . e($name) . '"' . ($required ? ' required' : '');
                if ($source === 'states') {
                    $html .= ' data-rw-states';
                }
                if ($source === 'lgas') {
                    $html .= ' data-rw-lgas';
                }
                $html .= '>';

                if ($source === 'states') {
                    $html .= '<option value="">Select your state</option>';
                    if ($pdo instanceof PDO) {
                        foreach (registration_wizard_states($pdo) as $state) {
                            $selected = $value !== '' && $value === (string) $state['state_name'] ? ' selected' : '';
                            $html .= '<option value="' . e((string) $state['state_name']) . '" data-state-id="' . (int) $state['id'] . '"' . $selected . '>'
                                . e((string) $state['state_name']) . '</option>';
                        }
                    }
                } elseif ($source === 'lgas') {
                    // The option value is the numeric LGA id (applications.lga_id is an
                    // id, not a name). The list is refreshed by script when the state
                    // changes; the saved id is resolved here so it still renders
                    // correctly with scripting disabled.
                    $html .= '<option value="">Select your LGA</option>';
                    if ($value !== '' && $pdo instanceof PDO) {
                        try {
                            $stmt = $pdo->prepare('SELECT lga_name FROM nigeria_lgas WHERE id = ? LIMIT 1');
                            $stmt->execute([(int) $value]);
                            $lgaName = (string) $stmt->fetchColumn();
                        } catch (Throwable $e) {
                            $lgaName = '';
                        }
                        if ($lgaName !== '') {
                            $html .= '<option value="' . e($value) . '" selected>' . e($lgaName) . '</option>';
                        }
                    }
                } else {
                    $html .= '<option value="">Select&hellip;</option>';
                    foreach ((array) ($field['options'] ?? []) as $optValue => $optLabel) {
                        $selected = (string) $optValue === $value ? ' selected' : '';
                        $html .= '<option value="' . e((string) $optValue) . '"' . $selected . '>' . e((string) $optLabel) . '</option>';
                    }
                }
                $html .= '</select>';
            } elseif ($type === 'textarea') {
                $html .= '<textarea id="' . e($id) . '" name="' . e($name) . '" rows="3"' . ($required ? ' required' : '') . '>'
                    . e($value) . '</textarea>';
            } else {
                $attrs = '';
                if (isset($field['step_attr'])) {
                    $attrs .= ' step="' . e((string) $field['step_attr']) . '"';
                }
                if (isset($field['min'])) {
                    $attrs .= ' min="' . e((string) $field['min']) . '"';
                }
                if (!empty($field['placeholder'])) {
                    $attrs .= ' placeholder="' . e((string) $field['placeholder']) . '"';
                }
                if ($type === 'date') {
                    // Bounds make the native picker refuse an age outside the policy.
                    $bounds = registration_wizard_dob_bounds($field);
                    $attrs .= ' min="' . e($bounds['min']) . '" max="' . e($bounds['max']) . '"';
                }
                // A datalist offers common answers while still accepting anything typed,
                // which suits fields whose real answers vary (soil type, varieties, ...).
                $suggestions = is_array($field['suggestions'] ?? null) ? $field['suggestions'] : [];
                if ($suggestions) {
                    $attrs .= ' list="' . e($id) . '-options" autocomplete="off"';
                }
                $html .= '<input type="' . e($type) . '" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"'
                    . $attrs . ($required ? ' required' : '') . '>';
                if ($type === 'date') {
                    $ageNow = registration_wizard_age_from_dob($value);
                    // Filled in by script as the date changes.
                    $html .= '<span class="rw-hint" data-age-out="' . e($id) . '" data-age-min="'
                        . (int) ($field['min_age'] ?? 16) . '">'
                        . ($ageNow !== null ? 'Age: ' . $ageNow : 'Your age is calculated from this.') . '</span>';
                }
                if ($suggestions) {
                    $html .= '<datalist id="' . e($id) . '-options">';
                    foreach ($suggestions as $suggestion) {
                        $html .= '<option value="' . e((string) $suggestion) . '"></option>';
                    }
                    $html .= '</datalist>';
                }
            }

            $html .= '</div>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('registration_wizard_review_html')) {
    /** A read-back of everything captured so far, grouped by step. */
    function registration_wizard_review_html(array $board, array $payload, string $boardSlug): string
    {
        $html = '<div class="rw-review">';
        foreach (registration_wizard_steps($board) as $index => $step) {
            if (!empty($step['review'])) {
                continue;
            }
            $values = $payload[$step['key']] ?? [];
            $rows = '';
            foreach ($step['fields'] as $field) {
                if ((string) ($field['type'] ?? '') === 'checkbox') {
                    continue;
                }
                $value = (string) ($values[$field['name']] ?? '');
                if ($value === '') {
                    continue;
                }
                $rows .= '<div class="rw-review__row"><dt>' . e((string) $field['label']) . '</dt><dd>' . e($value) . '</dd></div>';
            }
            if ($rows === '') {
                continue;
            }
            $html .= '<section class="rw-review__group"><h3>' . e((string) $step['title']) . '</h3><dl>' . $rows . '</dl>'
                . '<a class="rw-review__edit" href="?board=' . e($boardSlug) . '&amp;step=' . (int) $index . '">Edit</a></section>';
        }

        return $html . '</div>';
    }
}

/* --------------------------------------------------------------------- finalize */

if (!function_exists('registration_wizard_column_ready')) {
    /**
     * Make sure a column exists and report it as usable.
     *
     * app_column_exists() caches per process, so a column created a moment ago still
     * reads as missing for the rest of the request — which silently dropped the
     * "Other" answers on the very request that created their column. This checks
     * information_schema directly after creating.
     */
    function registration_wizard_column_ready(PDO $pdo, string $table, string $column, string $definition = 'VARCHAR(180) NULL'): bool
    {
        if (app_column_exists($pdo, $table, $column)) {
            return true;
        }
        app_add_column_if_missing($pdo, $table, $column, $definition);
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

if (!function_exists('registration_wizard_prepare_other_columns')) {
    /**
     * Create any column the "Other" answers need, BEFORE a transaction starts.
     *
     * MySQL commits implicitly on DDL, so creating a column inside the finalize
     * transaction silently ends it and the row is left half written. This runs first and
     * reports which columns are actually usable.
     *
     * @return array<string, bool> column name => usable
     */
    function registration_wizard_prepare_other_columns(PDO $pdo, string $boardSlug, array $values): array
    {
        $plan = [
            'grower' => [['applications', 'land_ownership_other']],
            'learner' => [['users', 'education_level_other']],
            'seller' => [['marketplace_sellers', 'seller_type_other']],
            'provider' => [['provider_registry', 'provider_type_other']],
        ];

        $ready = [];
        foreach ($plan[$boardSlug] ?? [] as [$table, $column]) {
            if (($values[$column] ?? '') !== '') {
                $ready[$column] = registration_wizard_column_ready($pdo, $table, $column);
            }
        }

        return $ready;
    }
}

if (!function_exists('registration_wizard_finalize')) {
    /**
     * Commit a finished draft into the real tables and activate the account.
     *
     * @return array{ok: bool, message: string}
     */
    function registration_wizard_finalize(PDO $pdo, string $boardSlug, array $draft): array
    {
        $board = registration_wizard_board($boardSlug);
        if (!$board) {
            return ['ok' => false, 'message' => 'Unknown registration board.'];
        }

        $payload = registration_wizard_payload($draft);
        $values = registration_wizard_all_values($payload);
        $userId = (int) $draft['user_id'];
        $email = (string) $draft['email'];

        // Any column this feature introduces must be created before the transaction:
        // MySQL DDL commits implicitly and would otherwise end it mid-write.
        $extraColumns = registration_wizard_prepare_other_columns($pdo, $boardSlug, $values);

        // One transaction: activating the account and writing the board-specific row
        // must succeed or fail together. Otherwise a failure here would leave someone
        // with an active account and a half-built application.
        $ownsTransaction = !$pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            // The OTP proved control of the mailbox, so this is also the point where
            // the account stops being needs_confirmation.
            $pdo->prepare("UPDATE users SET name = COALESCE(NULLIF(?, ''), name), phone = COALESCE(NULLIF(?, ''), phone),
                    role = ?, platform_role = ?, account_status = 'active', email_verified_at = NOW(), phone_verified = 1,
                    terms_accepted = 1, terms_accepted_at = NOW()
                WHERE id = ?")
                ->execute([
                    (string) ($values['name'] ?? ''),
                    (string) ($values['phone'] ?? ''),
                    (string) $board['role'],
                    (string) $board['platform_role'],
                    $userId,
                ]);

            if (($values['state'] ?? '') !== '') {
                $pdo->prepare('UPDATE users SET location = ? WHERE id = ?')->execute([(string) $values['state'], $userId]);
            }

            // Date of birth, but only when it is a real, in-policy date.
            $dobValue = trim((string) ($values['dob'] ?? ''));
            if ($dobValue !== '' && app_column_exists($pdo, 'users', 'dob')) {
                $dobAge = registration_wizard_age_from_dob($dobValue);
                if ($dobAge !== null && $dobAge >= 16 && $dobAge <= 100) {
                    $pdo->prepare('UPDATE users SET dob = ? WHERE id = ?')->execute([$dobValue, $userId]);
                }
            }

            // Only the education "Other" answer belongs on the users table; the others
            // live with their own board's record.
            // Column prepared before the transaction; app_column_exists() would read a
            // stale cache here, so consult the prepared map instead.
            $educationOtherUsable = !empty($extraColumns['education_level_other']);

            foreach (['education_level', 'farming_experience_years', 'specialization', 'education_level_other'] as $column) {
                $userColumnUsable = $column === 'education_level_other'
                    ? $educationOtherUsable
                    : app_column_exists($pdo, 'users', $column);
                if (($values[$column] ?? '') !== '' && $userColumnUsable) {
                    $pdo->prepare("UPDATE users SET `{$column}` = ? WHERE id = ?")->execute([(string) $values[$column], $userId]);
                }
            }

            if ($boardSlug === 'grower') {
                registration_wizard_finalize_grower($pdo, $draft, $values, $userId, $extraColumns);
            } elseif ($boardSlug === 'seller') {
                registration_wizard_finalize_seller($pdo, $values, $userId, $email, $extraColumns);
            } elseif ($boardSlug === 'provider') {
                registration_wizard_finalize_provider($pdo, $values, $userId, $email, $extraColumns);
            }

            $pdo->prepare("UPDATE registration_drafts SET status = 'submitted', otp_verified = 1, submitted_at = NOW(),
                    updated_at = NOW(), current_step = ? WHERE id = ?")
                ->execute([registration_wizard_steps_total($board), (int) $draft['id']]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return ['ok' => true, 'message' => 'Registration complete.'];
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Registration finalize failed (' . $boardSlug . '): ' . $e->getMessage());

            return ['ok' => false, 'message' => 'We could not finish your registration. Please try again or contact support.'];
        }
    }
}

if (!function_exists('registration_wizard_finalize_grower')) {
    function registration_wizard_finalize_grower(PDO $pdo, array $draft, array $values, int $userId, array $extraColumns = []): void
    {
        $applicationId = 0;
        try {
            $stmt = $pdo->prepare('SELECT application_id FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $applicationId = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
        }

        // Created before the transaction by registration_wizard_prepare_other_columns().
        $otherColumnsForGrower = ['land_ownership_other'];

        $columns = [
            'land_ownership_other',
            'whatsapp', 'alternate_phone', 'alternate_email', 'member_type', 'business_name',
            'business_registration_number', 'representative_name', 'cooperative_name',
            'cooperative_registration_number', 'cooperative_members_count', 'state', 'state_id', 'lga_id',
            'street_address', 'land_ownership_status', 'land_title_details', 'climate_zone', 'farm_size',
            'estimated_tree_count', 'coconut_variety', 'production_stage', 'annual_yield_estimate',
            'current_farm_activities', 'intercrops', 'livestock_integration', 'farming_practices',
            'soil_type', 'water_source', 'irrigation_method', 'major_challenges', 'support_needs',
            'market_channels', 'remarks',
        ];

        if ($applicationId <= 0) {
            // A grower who signed up straight through the wizard has no applications
            // row yet. applications.phone is unique, so reuse any row already held for
            // this person (re-registration, or an earlier partial application) instead
            // of failing the insert.
            $stmt = $pdo->prepare('SELECT id FROM applications WHERE LOWER(email) = LOWER(?) OR phone = ? ORDER BY id ASC LIMIT 1');
            $stmt->execute([(string) $draft['email'], (string) ($values['phone'] ?? '')]);
            $applicationId = (int) $stmt->fetchColumn();

            if ($applicationId <= 0) {
                $appRef = 'NAT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo->prepare("INSERT INTO applications (app_ref, name, location, farm_size, phone, email, commitments, confirmed, review_status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, '', 0, 'pending', NOW(), NOW())")
                    ->execute([
                        $appRef,
                        (string) ($values['name'] ?? ''),
                        (string) ($values['state'] ?? ''),
                        is_numeric((string) ($values['farm_size'] ?? '')) ? (float) $values['farm_size'] : 0,
                        (string) ($values['phone'] ?? ''),
                        (string) $draft['email'],
                    ]);
                $applicationId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE users SET application_id = ? WHERE id = ?')->execute([$applicationId, $userId]);
        }

        // Always fill every mapped column from the payload, whether the row was just
        // created or already existed.
        $set = [];
        $params = [];
        foreach ($columns as $column) {
            $usable = in_array($column, $otherColumnsForGrower, true)
                ? !empty($extraColumns[$column])
                : app_column_exists($pdo, 'applications', $column);
            if (!$usable) {
                continue;
            }
            $value = $values[$column] ?? null;
            if (in_array($column, ['state_id', 'lga_id'], true) && (!is_numeric((string) $value) || (int) $value <= 0)) {
                continue;
            }
            $set[] = "`{$column}` = ?";
            $params[] = ($value === '' ? null : $value);
        }
        $set[] = "review_status = 'pending'";
        if (app_column_exists($pdo, 'applications', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        $params[] = $applicationId;
        $pdo->prepare('UPDATE applications SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
    }
}

if (!function_exists('registration_wizard_finalize_seller')) {
    function registration_wizard_finalize_seller(PDO $pdo, array $values, int $userId, string $email, array $extraColumns = []): void
    {
        if (!app_table_exists($pdo, 'marketplace_sellers')) {
            return;
        }
        $storeName = (string) ($values['store_name'] ?? '');
        if ($storeName === '') {
            return;
        }
        $sellerOtherUsable = !empty($extraColumns['seller_type_other']);
        $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $storeName), '-'));
        $slug = $slugBase !== '' ? $slugBase . '-' . substr(sha1((string) $userId), 0, 6) : 'store-' . $userId;

        $data = [
            'store_name' => $storeName,
            'slug' => $slug,
            'description' => (string) ($values['description'] ?? ''),
            'contact_person' => (string) ($values['name'] ?? ''),
            'email' => $email,
            'phone' => (string) ($values['phone'] ?? ''),
            'whatsapp' => (string) ($values['whatsapp'] ?? ''),
            'location_label' => (string) (($values['location_label'] ?? '') !== '' ? $values['location_label'] : ($values['state'] ?? '')),
            'coverage_area' => (string) ($values['coverage_area'] ?? ''),
            'fulfillment_options' => (string) ($values['fulfillment_options'] ?? ''),
            'seller_type' => (string) ($values['seller_type'] ?? 'grower'),
            'seller_type_other' => (string) ($values['seller_type_other'] ?? ''),
            'approval_status' => 'pending',
            'verification_status' => 'pending',
        ];

        $stmt = $pdo->prepare('SELECT id FROM marketplace_sellers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $existing = (int) $stmt->fetchColumn();

        if ($existing > 0) {
            $set = [];
            $params = [];
            foreach ($data as $column => $value) {
                $usable = $column === 'seller_type_other'
                    ? $sellerOtherUsable
                    : app_column_exists($pdo, 'marketplace_sellers', $column);
                if ($usable) {
                    $set[] = "`{$column}` = ?";
                    $params[] = $value;
                }
            }
            $params[] = $existing;
            $pdo->prepare('UPDATE marketplace_sellers SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ?')->execute($params);

            return;
        }

        $insertColumns = ['user_id'];
        $placeholders = ['?'];
        $params = [$userId];
        foreach ($data as $column => $value) {
            $usable = $column === 'seller_type_other'
                ? $sellerOtherUsable
                : app_column_exists($pdo, 'marketplace_sellers', $column);
            if ($usable) {
                $insertColumns[] = $column;
                $placeholders[] = '?';
                $params[] = $value;
            }
        }
        $pdo->prepare('INSERT INTO marketplace_sellers (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')')
            ->execute($params);
    }
}

if (!function_exists('registration_wizard_finalize_provider')) {
    function registration_wizard_finalize_provider(PDO $pdo, array $values, int $userId, string $email, array $extraColumns = []): void
    {
        if (!app_table_exists($pdo, 'provider_registry')) {
            return;
        }
        $providerOtherUsable = !empty($extraColumns['provider_type_other']);
        $data = [
            'provider_type' => (string) ($values['provider_type'] ?? 'other'),
            'provider_type_other' => (string) ($values['provider_type_other'] ?? ''),
            'company_name' => (string) ($values['company_name'] ?? ''),
            'company_description' => (string) ($values['company_description'] ?? ''),
            'contact_person' => (string) (($values['contact_person'] ?? '') !== '' ? $values['contact_person'] : ($values['name'] ?? '')),
            'email' => $email,
            'phone' => (string) ($values['phone'] ?? ''),
            'business_address' => (string) ($values['business_address'] ?? ''),
            'coverage_scope' => (string) ($values['coverage_scope'] ?? 'local'),
            'states_served' => (string) ($values['states_served'] ?? ''),
            'years_in_business' => is_numeric((string) ($values['years_in_business'] ?? '')) ? (int) $values['years_in_business'] : null,
            'certifications' => (string) ($values['certifications'] ?? ''),
            'website' => (string) ($values['website'] ?? ''),
            'business_registration_number' => (string) ($values['business_registration_number'] ?? ''),
            'tax_id' => (string) ($values['tax_id'] ?? ''),
            'primary_category' => (string) ($values['primary_category'] ?? ''),
            'service_categories' => (string) ($values['service_categories'] ?? ''),
            'input_categories' => (string) ($values['input_categories'] ?? ''),
            'supply_capacity' => (string) ($values['supply_capacity'] ?? ''),
            'delivery_options' => (string) ($values['delivery_options'] ?? ''),
            'quality_assurance' => (string) ($values['quality_assurance'] ?? ''),
        ];

        $stmt = $pdo->prepare('SELECT id FROM provider_registry WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $existing = (int) $stmt->fetchColumn();

        if ($existing > 0) {
            $set = [];
            $params = [];
            foreach ($data as $column => $value) {
                $usable = $column === 'provider_type_other'
                    ? $providerOtherUsable
                    : app_column_exists($pdo, 'provider_registry', $column);
                if ($usable) {
                    $set[] = "`{$column}` = ?";
                    $params[] = $value;
                }
            }
            $params[] = $existing;
            $pdo->prepare('UPDATE provider_registry SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ?')->execute($params);

            return;
        }

        $insertColumns = ['user_id'];
        $placeholders = ['?'];
        $params = [$userId];
        foreach ($data as $column => $value) {
            $usable = $column === 'provider_type_other'
                ? $providerOtherUsable
                : app_column_exists($pdo, 'provider_registry', $column);
            if ($usable) {
                $insertColumns[] = $column;
                $placeholders[] = '?';
                $params[] = $value;
            }
        }
        if (app_column_exists($pdo, 'provider_registry', 'status')) {
            $insertColumns[] = 'status';
            $placeholders[] = '?';
            $params[] = 'pending';
        }
        $pdo->prepare('INSERT INTO provider_registry (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')')
            ->execute($params);
    }
}

/* ------------------------------------------------------------------------- otp */

if (!function_exists('registration_wizard_otp_begin')) {
    /**
     * Send a registration OTP for a board and remember where to return.
     *
     * @return array{ok: bool, message: string}
     */
    function registration_wizard_otp_begin(PDO $pdo, string $boardSlug, array $user): array
    {
        $purpose = 'registration_' . (string) preg_replace('/[^a-z0-9_]/i', '', $boardSlug);
        $start = otp_begin_email_login_challenge($pdo, $user, registration_wizard_next_destination($boardSlug), $purpose);

        if (empty($start['ok'])) {
            return ['ok' => false, 'message' => (string) ($start['message'] ?? 'The verification code could not be sent.')];
        }

        return ['ok' => true, 'message' => 'Verification code sent.'];
    }
}

/* ------------------------------------------------------------------ phone ownership */

if (!function_exists('registration_wizard_normalise_phone')) {
    /**
     * Canonical form of a phone number, for comparing one against another.
     *
     * Nigerian numbers arrive as 07033399987, +2347033399987, 2347033399987 or with
     * spaces and dashes, so a straight string comparison misses duplicates. This reduces
     * them all to the local 0-prefixed form.
     */
    function registration_wizard_normalise_phone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        // 2347033399987 -> 07033399987
        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            $digits = '0' . substr($digits, 3);
        }
        // 7033399987 (10 digits, no trunk prefix) -> 07033399987
        if (strlen($digits) === 10 && !str_starts_with($digits, '0')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}

if (!function_exists('registration_wizard_phone_owner')) {
    /**
     * Find the account already holding a phone number, if any.
     *
     * Phone is not unique in the users table, so two accounts could share one number and
     * then SMS about either account (withdrawals, alerts, confirmations) would reach
     * whoever holds the phone. Registration refuses to create that situation.
     *
     * @return array<string,mixed>|null the owning user row
     */
    function registration_wizard_phone_owner(PDO $pdo, string $phone, int $exceptUserId = 0): ?array
    {
        $normalised = registration_wizard_normalise_phone($phone);
        if ($normalised === '') {
            return null;
        }
        // Match on the last ten digits so any stored formatting still lines up, then
        // confirm in PHP to rule out coincidental matches.
        $tail = substr($normalised, -10);
        try {
            $stmt = $pdo->prepare("
                SELECT id, name, email, phone
                FROM users
                WHERE phone IS NOT NULL AND phone <> ''
                  AND REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', '') LIKE ?
                ORDER BY id ASC
            ");
            $stmt->execute(['%' . $tail]);
        } catch (Throwable $e) {
            error_log('registration_wizard_phone_owner lookup failed: ' . $e->getMessage());

            return null;
        }

        foreach ($stmt->fetchAll() as $row) {
            if ($exceptUserId > 0 && (int) $row['id'] === $exceptUserId) {
                continue;
            }
            if (substr(registration_wizard_normalise_phone((string) $row['phone']), -10) === $tail) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('registration_wizard_phone_conflict_message')) {
    function registration_wizard_phone_conflict_message(array $owner): string
    {
        // Deliberately does not reveal the other account's details.
        return 'That phone number is already registered to another NATCODEV account. '
            . 'Each account needs its own number so that SMS alerts and withdrawal '
            . 'messages reach the right person. Please use a different number, or '
            . 'contact support if this number is yours.';
    }
}
