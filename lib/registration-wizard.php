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

// Seller subscription plans live in the revenue module: the wizard offers a
// plan at sign-up and records the choice (plus its subscription row) at
// finalize, so the definitions are needed here.
require_once __DIR__ . '/platform-revenue.php';

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
                            ['name' => 'district', 'label' => 'District / Ward', 'type' => 'text', 'placeholder' => 'e.g. District 1, Ward A'],
                            ['name' => 'village', 'label' => 'Community / Village', 'type' => 'text', 'placeholder' => 'e.g. Ago Iwoye Village'],
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
                            [
                                'name' => 'subscription_plan',
                                'label' => 'Subscription Plan',
                                'type' => 'select',
                                'required' => true,
                                'source' => 'seller_plans',
                                'default' => 'seller_starter',
                                'hint' => 'Starter is free. Growth and Enterprise are billed annually from your seller wallet.',
                            ],
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

            'coordinator' => [
                'label' => 'Coordinator Enrollment',
                'short' => 'Coordinator',
                'entry' => 'apply.php?type=coordinator',
                'dashboard' => 'coordination/',
                'role' => 'admin',
                'platform_role' => 'state_coordinator',
                'steps' => [
                    [
                        'key' => 'personal',
                        'title' => 'Your Details',
                        'intro' => 'Who you are and how we reach you.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                            ['name' => 'alternate_phone', 'label' => 'Alternate Phone', 'type' => 'tel'],
                            ['name' => 'alternate_email', 'label' => 'Alternate Email', 'type' => 'email'],
                        ],
                    ],
                    [
                        'key' => 'enclave',
                        'title' => 'Your Coordination Enclave',
                        'intro' => 'The tier you head, and the exact enclave you will coordinate. Operations reviews and approves every application before your account is activated.',
                        'fields' => [
                            ['name' => 'coordinator_tier', 'label' => 'Coordinator Tier', 'type' => 'select', 'required' => true,
                                'options' => [
                                    'village_coordinator' => 'Village Head (Village Coordinator)',
                                    'district_coordinator' => 'District / Ward Head (District Coordinator)',
                                    'lga_coordinator' => 'LGA Head (LGA Coordinator)',
                                    'state_coordinator' => 'State Coordinator',
                                    'regional_coordinator' => 'Regional Coordinator',
                                ]],
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'lga', 'label' => 'LGA', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Ijebu North'],
                            ['name' => 'district', 'label' => 'District / Ward', 'type' => 'text', 'placeholder' => 'e.g. District 1'],
                            ['name' => 'village', 'label' => 'Community / Village', 'type' => 'text', 'placeholder' => 'e.g. Ago Iwoye'],
                            ['name' => 'reason', 'label' => 'Why do you want to coordinate this enclave?', 'type' => 'textarea'],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your application goes to operations for approval.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I confirm the information given is accurate and I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
                        ],
                    ],
                ],
            ],

            'cooperative' => [
                'label' => 'Cooperative Partnership',
                'short' => 'Cooperative',
                'entry' => 'apply.php?type=cooperative',
                'dashboard' => 'cooperatives/',
                'role' => 'grower',
                'platform_role' => 'cooperative_admin',
                'steps' => [
                    [
                        'key' => 'personal',
                        'title' => 'Contact Person',
                        'intro' => 'The person who will administer the cooperative on NATCODEV.',
                        'fields' => [
                            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => true],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp Number', 'type' => 'tel'],
                            ['name' => 'alternate_phone', 'label' => 'Alternate Phone', 'type' => 'tel'],
                            ['name' => 'alternate_email', 'label' => 'Alternate Email', 'type' => 'email'],
                        ],
                    ],
                    [
                        'key' => 'cooperative',
                        'title' => 'Your Cooperative',
                        'intro' => 'The registered cooperative that will partner with NATCODEV. Operations reviews and approves every partnership before the account is activated.',
                        'fields' => [
                            ['name' => 'cooperative_name', 'label' => 'Cooperative Name', 'type' => 'text', 'required' => true],
                            ['name' => 'cooperative_registration_number', 'label' => 'Cooperative Registration Number (CAC)', 'type' => 'text', 'required' => true],
                            ['name' => 'cooperative_members_count', 'label' => 'Number of Members', 'type' => 'number', 'required' => true, 'min' => '1'],
                            ['name' => 'state', 'label' => 'State', 'type' => 'select', 'required' => true, 'source' => 'states'],
                            ['name' => 'lga', 'label' => 'LGA', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. Ijebu North'],
                            ['name' => 'address', 'label' => 'Cooperative Address', 'type' => 'text'],
                        ],
                    ],
                    [
                        'key' => 'confirm',
                        'title' => 'Review & Submit',
                        'intro' => 'Confirm and your partnership application goes to operations for approval.',
                        'review' => true,
                        'fields' => [
                            ['name' => 'terms_accepted', 'label' => 'I confirm the information given is accurate and I accept the Terms of Service and Privacy Policy.', 'type' => 'checkbox', 'required' => true],
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
                if (!isset($field['options']) && (string) ($field['source'] ?? '') === 'seller_plans' && !array_key_exists($raw, registration_wizard_seller_plan_options())) {
                    $errors[] = $label . ' has an invalid selection.';
                }
            }

            $values[$name] = $raw;
        }

        return ['values' => $values, 'errors' => $errors];
    }
}

/* ---------------------------------------------------------------- seller plans */

if (!function_exists('registration_wizard_seller_plan_options')) {
    /**
     * Plan choices for the sign-up selector, keyed by plan key.
     *
     * @return array<string, string> plan key => option label
     */
    function registration_wizard_seller_plan_options(): array
    {
        $options = [];
        foreach (revenue_default_seller_plans() as $plan) {
            $options[(string) $plan['plan_key']] = registration_wizard_seller_plan_label($plan);
        }

        return $options;
    }
}

if (!function_exists('registration_wizard_seller_plan_label')) {
    /** A one-line description of a plan for the selector and the review step. */
    function registration_wizard_seller_plan_label(array $plan): string
    {
        $monthly = (float) ($plan['monthly_fee'] ?? 0);
        $annual = (float) ($plan['annual_fee'] ?? 0);
        $fee = ($annual <= 0 && $monthly <= 0)
            ? 'Free'
            : 'NGN ' . number_format($monthly, 0) . '/mo or NGN ' . number_format($annual, 0) . '/yr';
        $limit = (int) ($plan['product_limit'] ?? 0);

        return sprintf('%s — %s · %s listings', (string) ($plan['title'] ?? ''), $fee, number_format($limit));
    }
}

if (!function_exists('registration_wizard_seller_plan_title')) {
    /** The display title for a plan key, or the key itself when unknown. */
    function registration_wizard_seller_plan_title(string $planKey): string
    {
        foreach (revenue_default_seller_plans() as $plan) {
            if ((string) $plan['plan_key'] === $planKey) {
                return (string) $plan['title'];
            }
        }

        return $planKey;
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
            if ($value === '' && array_key_exists('default', $field)) {
                // Preselect the field default (the starter plan) on a fresh draft.
                $value = (string) $field['default'];
            }
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

            if (!empty($field['hint'])) {
                $html .= '<span class="rw-hint">' . e((string) $field['hint']) . '</span>';
            }

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
                } elseif ($source === 'seller_plans') {
                    // Subscription plans come from the revenue module so the seeded
                    // plan list stays the single source of truth. Always offers a
                    // value (the default is preselected), so no empty placeholder.
                    foreach (registration_wizard_seller_plan_options() as $planValue => $planLabel) {
                        $selected = (string) $planValue === $value ? ' selected' : '';
                        $html .= '<option value="' . e((string) $planValue) . '"' . $selected . '>' . e($planLabel) . '</option>';
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
                if ((string) ($field['source'] ?? '') === 'seller_plans') {
                    $value = registration_wizard_seller_plan_title($value);
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

        // Seller registration also writes a subscription record, so the revenue
        // tables, the wallet, and the marketplace_sellers.subscription_plan column
        // must all exist before the transaction starts, for the same DDL reason.
        $sellerSubscription = [];
        if ($boardSlug === 'seller') {
            if (!function_exists('wallet_ensure_schema')) {
                require_once __DIR__ . '/wallet.php';
            }
            if (!app_column_exists($pdo, 'marketplace_sellers', 'subscription_plan')) {
                app_add_column_if_missing($pdo, 'marketplace_sellers', 'subscription_plan', "VARCHAR(40) NOT NULL DEFAULT 'seller_starter'");
            }
            revenue_ensure_schema($pdo);
            wallet_ensure_schema($pdo);
        }

        // Enrollment boards write enrollment applications;
        // grower rows can carry the self-declared
        // district/village enclave columns. Both must
        // exist before the transaction starts, for the
        // same DDL reason.
        if (in_array($boardSlug, ['coordinator', 'cooperative'], true)) {
            enrollment_ensure_schema($pdo);
        }
        if (!app_column_exists($pdo, 'applications', 'district')) {
            app_add_column_if_missing($pdo, 'applications', 'district', 'VARCHAR(160) NULL');
        }
        if (!app_column_exists($pdo, 'applications', 'village')) {
            app_add_column_if_missing($pdo, 'applications', 'village', 'VARCHAR(160) NULL');
        }

        // One transaction: activating the account and writing the board-specific row
        // must succeed or fail together. Otherwise a failure here would leave someone
        // with an active account and a half-built application.
        $ownsTransaction = !$pdo->inTransaction();

        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            // Enrollment boards (coordinator, cooperative)
            // stay unactivated until operations approves
            // the application; enrollment_application_approve()
            // activates the account, assigns the scoped
            // role, and emails the claim link.
            $enrollmentBoard = in_array($boardSlug, ['coordinator', 'cooperative'], true);
            if ($enrollmentBoard) {
                $pdo->prepare("UPDATE users SET name = COALESCE(NULLIF(?, ''), name), phone = COALESCE(NULLIF(?, ''), phone),
                        role = ?, platform_role = ?, email_verified_at = NOW(), phone_verified = 1,
                        terms_accepted = 1, terms_accepted_at = NOW()
                    WHERE id = ?")
                    ->execute([
                        (string) ($values['name'] ?? ''),
                        (string) ($values['phone'] ?? ''),
                        (string) $board['role'],
                        (string) ($values['coordinator_tier'] ?: $board['platform_role']),
                        $userId,
                    ]);
            } else {
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
            }

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
                $sellerSubscription = registration_wizard_finalize_seller($pdo, $values, $userId, $email, $extraColumns);
            } elseif ($boardSlug === 'provider') {
                registration_wizard_finalize_provider($pdo, $values, $userId, $email, $extraColumns);
            } elseif ($boardSlug === 'buyer') {
                registration_wizard_finalize_buyer($pdo, $userId);
            } elseif ($boardSlug === 'coordinator' || $boardSlug === 'cooperative') {
                registration_wizard_finalize_enrollment($pdo, $boardSlug, $values, $userId, $email);
            }

            $pdo->prepare("UPDATE registration_drafts SET status = 'submitted', otp_verified = 1, submitted_at = NOW(),
                    updated_at = NOW(), current_step = ? WHERE id = ?")
                ->execute([registration_wizard_steps_total($board), (int) $draft['id']]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            $result = ['ok' => true, 'message' => 'Registration complete.'];
            if (!empty($sellerSubscription['fund_wallet'])) {
                // A paid plan the wallet could not cover: the subscription is
                // recorded as pending_payment and the redirect carries a
                // fund-wallet flag so Seller Central can show the funding CTA.
                $result['fund_wallet'] = true;
                $result['message'] = 'Registration complete. Your '
                    . (string) ($sellerSubscription['plan_title'] ?? 'seller') . ' subscription is awaiting payment.';
            }

            return $result;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Registration finalize failed (' . $boardSlug . '): ' . $e->getMessage());

            return ['ok' => false, 'message' => 'We could not finish your registration. Please try again or contact support.'];
        }
    }
}

if (!function_exists('registration_wizard_finalize_buyer')) {
    /**
     * Give a wizard-completed buyer their buyer_profiles row.
     *
     * The admin buyer lists read buyer_profiles, so without one a
     * wizard-registered buyer is invisible there until they save a
     * profile. This creates the same minimal row (user_id plus the
     * default buyer_type) that buyer_activate_access() creates for the
     * old self-activation flow; the unique key on user_id makes it a
     * no-op touch when a row already exists.
     */
    function registration_wizard_finalize_buyer(PDO $pdo, int $userId): void
    {
        if (!app_table_exists($pdo, 'buyer_profiles')) {
            return;
        }
        $pdo->prepare("INSERT INTO buyer_profiles (user_id, buyer_type) VALUES (?, 'individual')
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP")
            ->execute([$userId]);
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
            'district', 'village',
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

if (!function_exists('registration_wizard_finalize_enrollment')) {
    /**
     * Commit a coordinator or cooperative enrollment application.
     *
     * When `enrollment_auto_approve` is set in settings, applications are
     * immediately activated (user gets role assignment + claim link) and
     * ops does not need to review them. Otherwise the app lands as
     * `pending` for operations approval in the admin console.
     */
    function registration_wizard_finalize_enrollment(PDO $pdo, string $boardSlug, array $values, int $userId, string $email): void
    {
        enrollment_ensure_schema($pdo);
        $applicantType = $boardSlug === 'coordinator' ? 'coordinator' : 'cooperative';
        $autoApprove = trim((string) admin_setting($pdo, 'enrollment_auto_approve', '0')) === '1';

        // Re-submitting an application updates the
        // pending/approved row instead of duplicating it.
        $existing = $pdo->prepare("SELECT id, status FROM enrollment_applications WHERE email = ? AND applicant_type = ? ORDER BY id DESC LIMIT 1");
        $existing->execute([$email, $applicantType]);
        $existingRow = $existing->fetch();
        $existingId = (int) ($existingRow['id'] ?: 0);
        $existingStatus = (string) ($existingRow['status'] ?? '');

        if ($applicantType === 'coordinator') {
            $tier = strtolower((string) ($values['coordinator_tier'] ?? 'state_coordinator'));
            $validTiers = ['regional_coordinator', 'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator'];
            if (!in_array($tier, $validTiers, true)) {
                $tier = 'state_coordinator';
            }
            $columns = 'applicant_type, platform_role, name, email, phone, state, lga, district, village, reason, user_id';
            $params = [$applicantType, $tier, (string) ($values['name'] ?? ''), $email, (string) ($values['phone'] ?? '') ?: null, (string) ($values['state'] ?? '') ?: null, (string) ($values['lga'] ?? '') ?: null, (string) ($values['district'] ?? '') ?: null, (string) ($values['village'] ?? '') ?: null, (string) ($values['reason'] ?? '') ?: null, $userId];
        } else {
            $columns = 'applicant_type, name, email, phone, state, lga, cooperative_name, cooperative_registration_number, cooperative_members_count, user_id';
            $params = [$applicantType, (string) ($values['name'] ?? ''), $email, (string) ($values['phone'] ?? '') ?: null, (string) ($values['state'] ?? '') ?: null, (string) ($values['lga'] ?? '') ?: null, (string) ($values['cooperative_name'] ?? '') ?: null, (string) ($values['cooperative_registration_number'] ?? '') ?: null, is_numeric((string) ($values['cooperative_members_count'] ?? '')) ? (int) $values['cooperative_members_count'] : null, $userId];
        }

        // Determine final status based on auto-approve setting.
        $finalStatus = $autoApprove ? 'approved' : 'pending';

        if ($existingId > 0 && ($existingStatus !== 'approved' || !$autoApprove)) {
            // Only update if the previous status was not already approved via auto-approve.
            $sets = [];
            foreach (explode(', ', $columns) as $column) {
                if (in_array($column, ['applicant_type', 'user_id'], true)) {
                    continue;
                }
                $sets[] = "{$column} = ?";
            }
            $updateParams = [];
            foreach (explode(', ', $columns) as $index => $column) {
                if (in_array($column, ['applicant_type', 'user_id'], true)) {
                    continue;
                }
                $updateParams[] = $params[$index];
            }
            $updateParams[] = $existingId;
            $pdo->prepare("UPDATE enrollment_applications SET " . implode(', ', $sets) . ", status = ? WHERE id = ?")->execute([...$updateParams, $finalStatus, $existingId]);
            $applicationRow = [
                'id' => $existingId,
                'applicant_type' => $params[0],
                'email' => $params[3] ?? '',
                'platform_role' => $params[1] ?? '',
                'state' => $params[5] ?? '',
                'lga' => $params[6] ?? '',
                'district' => $params[7] ?? '',
                'village' => $params[8] ?? '',
                'cooperative_name' => isset($params[7]) ? $params[7] : '',
                'cooperative_registration_number' => isset($params[8]) ? $params[8] : '',
            ];
        } elseif (!$autoApprove || $existingStatus === 'approved') {
            // For new apps under auto-approve, insert and activate directly.
            // For re-submits where auto-approve was just toggled ON and old status is 'approved', skip re-insert.
            if ($autoApprove && $existingStatus === 'approved') {
                return; // Already auto-approved; nothing to do.
            }
            $paramsWithStatus = [...$params, $finalStatus];
            $pdo->prepare("INSERT INTO enrollment_applications ({$columns}, status) VALUES (" . implode(', ', array_fill(0, count($paramsWithStatus), '?')) . ")")->execute($paramsWithStatus);
            $insertedId = (int) $pdo->lastInsertId();

            $applicationRow = [
                'id' => $insertedId,
                'applicant_type' => $params[0],
                'email' => $params[3] ?? '',
                'platform_role' => $params[1] ?? '',
                'state' => $params[5] ?? '',
                'lga' => $params[6] ?? '',
                'district' => $params[7] ?? '',
                'village' => $params[8] ?? '',
                'cooperative_name' => isset($params[7]) ? $params[7] : '',
                'cooperative_registration_number' => isset($params[8]) ? $params[8] : '',
            ];
        } else {
            // Existing pending row but auto-approve just turned ON — promote to approved & activate.
            $pdo->prepare("UPDATE enrollment_applications SET status = ? WHERE id = ?")->execute(['approved', $existingId]);
            $applicationRow = [
                'id' => $existingId,
                'applicant_type' => $existingRow['applicant_type'] ?? '',
                'email' => $email,
                'platform_role' => $params[1] ?? '',
                'state' => $params[5] ?? '',
                'lga' => $params[6] ?? '',
                'district' => $params[7] ?? '',
                'village' => $params[8] ?? '',
                'cooperative_name' => isset($params[7]) ? $params[7] : '',
                'cooperative_registration_number' => isset($params[8]) ? $params[8] : '',
            ];
            // Activate the previously-pending application.
            if ($autoApprove && function_exists('enrollment_activate_user')) {
                $result = enrollment_activate_user($pdo, $applicationRow);
                error_log('Enrollment auto-activation: ' . $result['message']);
            }
        }

        // Auto-approve path: immediately activate the user.
        if ($autoApprove && !empty($applicationRow['id'])) {
            if (function_exists('enrollment_activate_user')) {
                $result = enrollment_activate_user($pdo, $applicationRow);
                error_log('Enrollment auto-activation: ' . $result['message']);
            }
        }

        if (function_exists('admin_audit_event')) {
            $action = $autoApprove ? 'enrollment_application_auto_approved' : 'enrollment_application_submitted';
            $subject = $applicationRow['id'] ?: (int) $pdo->lastInsertId();
            admin_audit_event($pdo, $action, 'enrollment_application', $subject, null, ['applicant_type' => $applicantType, 'user_id' => $userId, 'auto_approve' => $autoApprove], 'ok', $applicantType . ' enrollment ' . ($autoApprove ? 'auto-approved' : 'submitted') . ' by ' . $email);
        }
    }
}

if (!function_exists('registration_wizard_finalize_seller')) {
    /**
     * Commit the seller store row and its subscription record.
     *
     * The chosen plan is stored on the seller row, and a seller_subscriptions
     * row is created with a one-year term. The Starter plan is free and active
     * immediately; paid plans are debited from the seller's wallet when it
     * covers the annual fee, otherwise they are recorded as pending_payment and
     * the caller is told to send the seller to fund their wallet. Renewal is
     * manual — there is no recurring billing.
     *
     * @return array{plan_key: string, plan_title: string, amount_due: float, fund_wallet: bool}
     */
    function registration_wizard_finalize_seller(PDO $pdo, array $values, int $userId, string $email, array $extraColumns = []): array
    {
        $result = [
            'plan_key' => 'seller_starter',
            'plan_title' => 'Seller Starter',
            'amount_due' => 0.0,
            'fund_wallet' => false,
        ];
        if (!app_table_exists($pdo, 'marketplace_sellers')) {
            return $result;
        }
        // Lazy migration: the plan column is created on first use, guarded the
        // same way as every other column this module introduces. In the wizard
        // flow it already exists (created before the transaction); this keeps
        // direct callers of this function working too.
        if (!app_column_exists($pdo, 'marketplace_sellers', 'subscription_plan')) {
            app_add_column_if_missing($pdo, 'marketplace_sellers', 'subscription_plan', "VARCHAR(40) NOT NULL DEFAULT 'seller_starter'");
        }
        $storeName = (string) ($values['store_name'] ?? '');
        if ($storeName === '') {
            return $result;
        }

        // Resolve the choice against the seeded plan list, falling back to the
        // free Starter plan for anything unrecognised.
        $chosenPlan = strtolower(trim((string) ($values['subscription_plan'] ?? '')));
        $plan = null;
        foreach (revenue_default_seller_plans() as $candidate) {
            if ((string) $candidate['plan_key'] === $chosenPlan) {
                $plan = $candidate;
                break;
            }
        }
        if ($plan === null) {
            $plan = revenue_default_seller_plans()[0];
        }
        $result['plan_key'] = (string) $plan['plan_key'];
        $result['plan_title'] = (string) $plan['title'];

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
            'subscription_plan' => (string) $plan['plan_key'],
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

            registration_wizard_finalize_seller_subscription($pdo, $existing, $userId, $plan, $result);

            return $result;
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

        registration_wizard_finalize_seller_subscription($pdo, (int) $pdo->lastInsertId(), $userId, $plan, $result);

        return $result;
    }
}

if (!function_exists('registration_wizard_finalize_seller_subscription')) {
    /**
     * Create (or refresh) the seller's one-year subscription record.
     *
     * Runs inside the finalize transaction, so the wallet debit and the
     * subscription row commit or roll back together. A free plan is active
     * at once; a paid plan is active only when the wallet covered the annual
     * fee (SELECT ... FOR UPDATE balance check, then a wallet_transactions
     * debit with provider='seller_subscription'), and pending_payment
     * otherwise so the seller can fund the wallet and complete payment.
     */
    function registration_wizard_finalize_seller_subscription(PDO $pdo, int $sellerId, int $userId, array $plan, array &$result): void
    {
        revenue_ensure_schema($pdo);
        $annualFee = round((float) ($plan['annual_fee'] ?? 0), 2);

        $planStmt = $pdo->prepare('SELECT id FROM seller_subscription_plans WHERE plan_key = ? LIMIT 1');
        $planStmt->execute([(string) $plan['plan_key']]);
        $planId = (int) $planStmt->fetchColumn();
        if ($planId <= 0) {
            // The seeded plan row is missing: point the subscription at the
            // starter plan so it still resolves to a workable limit.
            $planStmt->execute(['seller_starter']);
            $planId = (int) $planStmt->fetchColumn();
        }
        if ($planId <= 0) {
            return;
        }

        // Idempotent re-finalize: keep one row per seller/plan and refresh it
        // instead of stacking duplicates.
        $subStmt = $pdo->prepare("
            SELECT id, status FROM seller_subscriptions
            WHERE seller_id = ? AND plan_id = ? AND status IN ('active', 'pending_payment')
            ORDER BY id DESC LIMIT 1
        ");
        $subStmt->execute([$sellerId, $planId]);
        $existingSub = $subStmt->fetch();
        if ($existingSub && (string) $existingSub['status'] === 'active') {
            return; // Already paid for this term: nothing to charge or fund.
        }

        $reference = 'SUB-' . $sellerId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $charged = $annualFee <= 0;
        if (!$charged) {
            if (!function_exists('wallet_get_or_create')) {
                require_once __DIR__ . '/wallet.php';
            }
            $wallet = wallet_get_or_create($pdo, $userId);
            // Lock the wallet row so two concurrent finalizes cannot spend the
            // same balance twice.
            $lock = $pdo->prepare('SELECT balance FROM wallets WHERE id = ? FOR UPDATE');
            $lock->execute([(int) $wallet['id']]);
            $balance = (float) ($lock->fetchColumn() ?? 0);

            if ($balance >= $annualFee) {
                $before = $balance;
                $after = round($balance - $annualFee, 2);
                $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?')
                    ->execute([$after, (int) $wallet['id']]);
                $pdo->prepare("
                    INSERT INTO wallet_transactions
                        (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                    VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'seller_subscription', 'completed', ?, ?, NOW())
                ")->execute([
                    (int) $wallet['id'],
                    $userId,
                    $annualFee,
                    'Seller subscription: ' . (string) $plan['title'] . ' (annual)',
                    $reference,
                    $before,
                    $after,
                ]);
                $charged = true;
            }
        }

        $status = $charged ? 'active' : 'pending_payment';
        $amountPaid = $charged ? $annualFee : 0.0;

        if ($existingSub) {
            $pdo->prepare("
                UPDATE seller_subscriptions
                SET status = ?, billing_cycle = 'annual', amount_paid = ?, started_at = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL 1 YEAR), payment_reference = ?
                WHERE id = ?
            ")->execute([$status, $amountPaid, $reference, (int) $existingSub['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO seller_subscriptions
                    (seller_id, user_id, plan_id, status, billing_cycle, amount_paid, started_at, expires_at, payment_reference)
                VALUES (?, ?, ?, ?, 'annual', ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), ?)
            ")->execute([$sellerId, $userId, $planId, $status, $amountPaid, $reference]);
        }

        if (!$charged) {
            // The wallet does not cover the fee yet: the subscription is
            // recorded as pending_payment and the finalize redirect carries a
            // fund-wallet flag so Seller Central shows the funding CTA.
            $result['amount_due'] = $annualFee;
            $result['fund_wallet'] = true;
        }
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

/* -------------------------------------------------------------------- begin */

if (!function_exists('registration_wizard_begin')) {
    /**
     * Begin a registration from a board's entry form.
     *
     * Shared by register-wizard.php and every board's own registration
     * page, so all public sign-ups validate, rate-limit, and store data
     * the same way: the account is created (or an earlier unverified
     * attempt with the same email is reused) as needs_confirmation, the
     * first wizard step is seeded in registration_drafts, and the email
     * OTP is sent. The caller redirects the user to verify-otp.php.
     *
     * @param array<string,mixed> $input the posted entry-form values
     * @return array{ok: bool, userId: int, errors: array<int,string>, message: string}
     */
    function registration_wizard_begin(PDO $pdo, string $boardSlug, array $input): array
    {
        $errors = [];
        $userId = 0;

        $boardSlug = strtolower((string) preg_replace('/[^a-z0-9_]/i', '', $boardSlug));
        $board = registration_wizard_board($boardSlug);
        if (!$board) {
            return ['ok' => false, 'userId' => $userId, 'errors' => ['Unknown registration board.'], 'message' => 'Unknown registration board.'];
        }
        $steps = registration_wizard_steps($board);

        $name = trim((string) ($input['name'] ?? ''));
        $email = (string) filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        $digits = (string) preg_replace('/\D+/', '', $phone);
        if ($name === '') {
            $errors[] = 'Enter your full name.';
        }
        if ($email === '') {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            $errors[] = 'Enter a valid phone number.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Choose a password of at least 8 characters.';
        }
        if (!app_check_rate_limit('registration_start', 6, 900)) {
            $errors[] = 'Too many registration attempts. Please try again in a few minutes.';
        }

        if ($errors) {
            return ['ok' => false, 'userId' => $userId, 'errors' => $errors, 'message' => (string) ($errors[0] ?? '')];
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch() ?: null;

        if ($user && (string) $user['account_status'] === 'active' && !empty($user['email_verified_at'])) {
            $errors[] = 'That email is already registered. Please sign in instead, or use a different email.';
        } elseif (($phoneOwner = registration_wizard_phone_owner($pdo, $phone, $user ? (int) $user['id'] : 0)) !== null) {
            // Phone is not unique in the users table, so without this two
            // accounts could share a number and SMS about either one would go
            // to a single handset.
            $errors[] = registration_wizard_phone_conflict_message($phoneOwner);
        } else {
            if ($user) {
                // Unverified account from an earlier attempt: reuse it rather
                // than creating a duplicate the unique index would reject.
                $userId = (int) $user['id'];
                $pdo->prepare('UPDATE users SET name = ?, phone = ?, password = ?, role = ?, platform_role = ?, account_status = \'needs_confirmation\' WHERE id = ?')
                    ->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), (string) $board['role'], (string) $board['platform_role'], $userId]);
            } else {
                $pdo->prepare('INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, (string) $board['role'], (string) $board['platform_role'], 'needs_confirmation']);
                $userId = (int) $pdo->lastInsertId();
            }

            $existing = registration_wizard_find_by_email($pdo, $boardSlug, $email);
            if ($existing) {
                $pdo->prepare('UPDATE registration_drafts SET user_id = ?, phone = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$userId, $phone, (int) $existing['id']]);
            } else {
                // Seed the first step with what the entry form already
                // collected, plus the application type when the entry page
                // passed one (apply.php?type=cooperative, for example).
                $seed = ['name' => $name, 'phone' => $phone];
                $memberType = strtolower(trim((string) ($input['member_type'] ?? '')));
                if (in_array($memberType, ['individual', 'corporate', 'cooperative'], true)) {
                    $seed['member_type'] = $memberType;
                }
                $pdo->prepare('INSERT INTO registration_drafts (board, user_id, email, phone, payload, current_step, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())')
                    ->execute([$boardSlug, $userId, $email, $phone, json_encode([(string) $steps[0]['key'] => $seed])]);
            }

            // Pass the full user row (with phone) so the OTP can also be
            // delivered over WhatsApp/SMS, not just by email.
            $otpUserStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $otpUserStmt->execute([$userId]);
            $otpUser = $otpUserStmt->fetch() ?: ['id' => $userId, 'email' => $email, 'phone' => $phone];
            $otp = registration_wizard_otp_begin($pdo, $boardSlug, $otpUser);
            if (!empty($otp['ok'])) {
                return ['ok' => true, 'userId' => $userId, 'errors' => [], 'message' => 'Verification code sent.'];
            }
            $errors[] = (string) $otp['message'];
        }

        return ['ok' => false, 'userId' => $userId, 'errors' => $errors, 'message' => (string) ($errors[0] ?? '')];
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
