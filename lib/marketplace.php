<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function marketplace_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
    return $slug !== '' ? $slug : 'marketplace';
}

function marketplace_unique_slug(PDO $pdo, string $table, string $base, int $ignoreId = 0): string
{
    $base = marketplace_slug($base);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId > 0) {
            $sql .= " AND id <> ?";
            $params[] = $ignoreId;
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function marketplace_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function marketplace_seller_types(): array
{
    return [
        'grower' => 'Grower / Farm Owner',
        'cooperative' => 'Cooperative',
        'farm_hand' => 'Farm Hand / Practical Worker',
        'agronomist' => 'Agronomist',
        'extensionist' => 'Agricultural Extensionist',
        'input_provider' => 'Input Provider',
        'service_provider' => 'Service Provider',
        'processor' => 'Processor / Aggregator',
        'logistics' => 'Logistics Provider',
        'equipment_owner' => 'Equipment Owner',
        'investor_offtaker' => 'Investor / Offtaker',
        'natcodev' => 'NATCODEV Official',
        'other' => 'Other Marketplace Seller',
    ];
}

function marketplace_listing_types(): array
{
    return [
        'product' => 'Product',
        'service' => 'Service',
        'equipment' => 'Equipment',
        'labor' => 'Farm Labor',
        'procurement' => 'Procurement Request',
    ];
}

function marketplace_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function marketplace_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done || app_schema_flag_is_set($pdo, 'marketplace_schema_ready', '20260617-v1')) {
        $done = true;
        return;
    }

    try {
        $existing = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('marketplace_categories','marketplace_sellers','marketplace_listings','marketplace_orders')
        ")->fetchColumn();
        if ((int) $existing === 4) {
            app_schema_flag_set($pdo, 'marketplace_schema_ready', '20260617-v1');
            $done = true;
            return;
        }
    } catch (Throwable $e) {
        // Fall through to the full schema creation path.
    }

    app_ensure_farmer_engagement_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL UNIQUE,
            listing_type VARCHAR(40) NOT NULL DEFAULT 'product',
            description TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_marketplace_categories_type (listing_type, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_categories');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_sellers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            seller_type VARCHAR(60) NOT NULL DEFAULT 'grower',
            store_name VARCHAR(180) NOT NULL,
            slug VARCHAR(200) NOT NULL UNIQUE,
            description TEXT NULL,
            contact_person VARCHAR(160) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(60) NULL,
            whatsapp VARCHAR(60) NULL,
            location_label VARCHAR(255) NULL,
            coverage_area TEXT NULL,
            fulfillment_options TEXT NULL,
            approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            admin_notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_sellers_user (user_id),
            INDEX idx_marketplace_sellers_status (approval_status, verification_status, is_featured)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_sellers');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_listings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            category_id INT NULL,
            source_item_id INT NULL,
            listing_type VARCHAR(40) NOT NULL DEFAULT 'product',
            title VARCHAR(220) NOT NULL,
            slug VARCHAR(240) NOT NULL UNIQUE,
            summary VARCHAR(500) NULL,
            description TEXT NULL,
            price DECIMAL(14,2) NOT NULL DEFAULT 0,
            price_unit VARCHAR(60) NULL,
            quantity_available DECIMAL(14,2) NULL,
            unit VARCHAR(60) NULL,
            min_order_quantity DECIMAL(14,2) NULL,
            location_label VARCHAR(255) NULL,
            fulfillment_method VARCHAR(180) NULL,
            availability_status VARCHAR(40) NOT NULL DEFAULT 'available',
            approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            image_path VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_listings_seller (seller_id, approval_status, availability_status),
            INDEX idx_marketplace_listings_category (category_id, listing_type),
            INDEX idx_marketplace_listings_source (source_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_listings');
    foreach ([
        'mpn' => "VARCHAR(120) NULL",
        'gtin' => "VARCHAR(120) NULL",
        'gtin_type' => "VARCHAR(40) NULL",
        'origin_country' => "VARCHAR(120) NULL",
        'manufacturer' => "VARCHAR(180) NULL",
        'brand' => "VARCHAR(180) NULL",
        'model_number' => "VARCHAR(180) NULL",
        'tags' => "VARCHAR(500) NULL",
        'requires_shipping' => "TINYINT(1) NOT NULL DEFAULT 1",
        'downloadable' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'marketplace_listings', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_inquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inquiry_ref VARCHAR(80) NOT NULL UNIQUE,
            listing_id INT NOT NULL,
            seller_id INT NOT NULL,
            buyer_user_id INT NULL,
            buyer_name VARCHAR(180) NOT NULL,
            buyer_email VARCHAR(255) NULL,
            buyer_phone VARCHAR(80) NULL,
            quantity DECIMAL(14,2) NULL,
            preferred_date DATE NULL,
            message TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'new',
            seller_reply TEXT NULL,
            quoted_amount DECIMAL(14,2) NULL,
            quoted_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_inquiries_seller (seller_id, status, created_at),
            INDEX idx_marketplace_inquiries_buyer (buyer_user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_inquiries');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_ref VARCHAR(80) NOT NULL UNIQUE,
            inquiry_id INT NULL,
            listing_id INT NOT NULL,
            seller_id INT NOT NULL,
            buyer_user_id INT NULL,
            buyer_name VARCHAR(180) NOT NULL,
            buyer_email VARCHAR(255) NULL,
            buyer_phone VARCHAR(80) NULL,
            quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
            total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'requested',
            fulfillment_note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_orders_seller (seller_id, status, created_at),
            INDEX idx_marketplace_orders_buyer (buyer_user_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_orders');
    foreach ([
        'checkout_ref' => "VARCHAR(100) NULL",
        'payment_status' => "VARCHAR(40) NOT NULL DEFAULT 'unpaid'",
        'payment_method' => "VARCHAR(40) NULL",
        'delivery_status' => "VARCHAR(40) NOT NULL DEFAULT 'not_started'",
        'delivery_address' => "TEXT NULL",
        'delivery_contact' => "VARCHAR(160) NULL",
        'tracking_ref' => "VARCHAR(100) NULL",
        'payment_reference' => "VARCHAR(120) NULL",
        'payment_provider_reference' => "VARCHAR(120) NULL",
        'payment_provider_payload' => "LONGTEXT NULL",
        'delivery_fee' => "DECIMAL(14,2) NOT NULL DEFAULT 0",
        'service_fee' => "DECIMAL(14,2) NOT NULL DEFAULT 0",
        'checkout_total' => "DECIMAL(14,2) NOT NULL DEFAULT 0",
        'paid_at' => "DATETIME NULL",
        'settled_at' => "DATETIME NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'marketplace_orders', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_favorites (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            listing_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_marketplace_favorite (user_id, listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_favorites');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            listing_id INT NOT NULL,
            seller_id INT NOT NULL,
            user_id INT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            review_text TEXT NULL,
            approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_marketplace_reviews_listing (listing_id, approval_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_reviews');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_promotions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            promo_ref VARCHAR(80) NOT NULL UNIQUE,
            seller_id INT NULL,
            listing_id INT NULL,
            category_id INT NULL,
            title VARCHAR(180) NOT NULL,
            subtitle VARCHAR(255) NULL,
            placement VARCHAR(60) NOT NULL DEFAULT 'homepage_banner',
            image_path VARCHAR(255) NULL,
            target_url VARCHAR(255) NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            duration_days INT NOT NULL DEFAULT 30,
            payment_method VARCHAR(40) NULL,
            payment_reference VARCHAR(120) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            impressions INT NOT NULL DEFAULT 0,
            clicks INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_promotions_active (placement, status, starts_at, ends_at),
            INDEX idx_marketplace_promotions_seller (seller_id, status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_promotions');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_saved_carts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            listing_id INT NOT NULL,
            quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_marketplace_saved_cart (user_id, listing_id),
            INDEX idx_marketplace_saved_cart_user (user_id),
            INDEX idx_marketplace_saved_cart_listing (listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_saved_carts');

    marketplace_seed_categories($pdo);
    marketplace_seed_business_listings($pdo);
    marketplace_seed_featured_promotions($pdo);
    marketplace_migrate_legacy_items($pdo);
    app_schema_flag_set($pdo, 'marketplace_schema_ready', '20260617-v1');
    $done = true;
}

function marketplace_seed_categories(PDO $pdo): void
{
    $categories = [
        ['Certified Coconut Seedlings', 'product', 'Improved seedlings, nursery stock, and planting material.'],
        ['Fertilizer and Soil Inputs', 'product', 'Organic and inorganic inputs for coconut farms.'],
        ['Farm Tools and Equipment', 'equipment', 'Tools, machinery, and rentable equipment.'],
        ['Labor and Farm Hands', 'labor', 'Practical farm workers for planting, clearing, harvesting, spraying, and nursery work.'],
        ['Agronomy and Advisory', 'service', 'Professional agronomy, extension, training, and soil advisory services.'],
        ['Logistics and Haulage', 'service', 'Transport, aggregation, cold chain, and produce movement.'],
        ['Processing and Value Addition', 'service', 'Processors, aggregators, packaging, and coconut value-chain services.'],
        ['Offtake and Procurement', 'procurement', 'Buyer demand, tenders, offtake requests, and bulk procurement.'],
        ['Finance and Insurance', 'service', 'Credit, investment, insurance, and financial services.'],
        ['Training and Certification', 'service', 'Paid and free learning, certification, and practical capacity-building.'],
    ];
    $stmt = $pdo->prepare("
        INSERT INTO marketplace_categories (name, slug, listing_type, description, sort_order, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE name = VALUES(name), listing_type = VALUES(listing_type), description = VALUES(description), is_active = 1
    ");
    foreach ($categories as $index => $category) {
        $stmt->execute([$category[0], marketplace_slug($category[0]), $category[1], $category[2], ($index + 1) * 10]);
    }
}

function marketplace_migrate_legacy_items(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'marketplace_items')) {
        return;
    }

    $sellerId = marketplace_official_seller_id($pdo);
    $stmt = $pdo->query("SELECT * FROM marketplace_items ORDER BY id");
    $insert = $pdo->prepare("
        INSERT INTO marketplace_listings
            (seller_id, category_id, source_item_id, listing_type, title, slug, summary, description, price, price_unit, availability_status, approval_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'fixed', ?, ?)
    ");
    foreach ($stmt->fetchAll() as $item) {
        $exists = $pdo->prepare("SELECT id FROM marketplace_listings WHERE source_item_id = ? LIMIT 1");
        $exists->execute([(int) $item['id']]);
        if ($exists->fetch()) {
            continue;
        }
        $type = in_array((string) ($item['category'] ?? ''), ['service', 'training'], true) ? 'service' : (((string) ($item['category'] ?? '') === 'equipment') ? 'equipment' : 'product');
        $categoryId = marketplace_category_id_for_type($pdo, $type);
        $title = (string) ($item['title'] ?? 'Marketplace Item');
        $insert->execute([
            $sellerId,
            $categoryId,
            (int) $item['id'],
            $type,
            $title,
            marketplace_unique_slug($pdo, 'marketplace_listings', $title),
            mb_substr(trim((string) ($item['description'] ?? '')), 0, 480),
            (string) ($item['description'] ?? ''),
            (float) ($item['price'] ?? 0),
            (int) ($item['is_active'] ?? 1) === 1 ? 'available' : 'paused',
            (int) ($item['is_active'] ?? 1) === 1 ? 'approved' : 'pending',
        ]);
    }
}

function marketplace_official_seller_id(PDO $pdo): int
{
    $stmt = $pdo->prepare("SELECT id FROM marketplace_sellers WHERE seller_type = 'natcodev' ORDER BY id LIMIT 1");
    $stmt->execute();
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO marketplace_sellers
            (user_id, seller_type, store_name, slug, description, contact_person, email, approval_status, verification_status, is_featured)
        VALUES (NULL, 'natcodev', 'NATCODEV Official Marketplace', 'natcodev-official-marketplace',
            'Official NATCODEV marketplace offers, programs, training, inputs, and approved opportunities.', 'NATCODEV Marketplace Team', ?, 'approved', 'verified', 1)
    ");
    $stmt->execute([app_env('MAIL_FROM_ADDRESS', 'info@natcodev.com.ng')]);
    return (int) $pdo->lastInsertId();
}

function marketplace_seed_business_listings(PDO $pdo): void
{
    $sellerId = marketplace_official_seller_id($pdo);
    $rows = [
        'Certified Coconut Seedlings' => [
            ['Dwarf Coconut Seedlings - 6 Month Nursery Batch', 'Certified dwarf seedlings for fast establishment and early farm planning.', 1500, 'seedling', 'Minimum 25 seedlings. Nursery evidence and variety notes available.'],
            ['Hybrid Coconut Seedlings - Coastal Adapted', 'Improved hybrid planting material for humid coastal production zones.', 1800, 'seedling', 'Packed in polybags with transplanting guidance.'],
            ['Mother Palm Selected Seed Nuts', 'Selected nuts for nurseries and propagation programs.', 900, 'nut', 'Source selection details supplied at pickup.'],
            ['Nursery Starter Pack for Growers', 'Seedlings, nursery bags, labeling tags, and establishment guide.', 85000, 'pack', 'Designed for smallholder nursery setup.'],
            ['Replacement Seedling Bundle', 'Approved replacement seedlings for gap filling and survival recovery.', 12500, 'bundle', 'Bundle of 10, best used within rainy season.'],
        ],
        'Fertilizer and Soil Inputs' => [
            ['Organic Compost - Coconut Farm Grade', 'Compost blend for soil structure and young palm vigor.', 9500, 'bag', '25kg bag, suitable for field establishment.'],
            ['NPK Coconut Establishment Blend', 'Balanced nutrient input for young coconut stands.', 18500, 'bag', 'Use according to agronomy recommendation.'],
            ['Dolomite Lime Soil Conditioner', 'Soil pH support for acidic coconut plots.', 7800, 'bag', 'Includes application advisory note.'],
            ['Coconut Husk Mulch', 'Moisture retention and weed suppression material.', 3500, 'bag', '50kg compressed bag for field mulching.'],
            ['Biochar Soil Improvement Pack', 'Carbon-rich amendment for sandy and tired soils.', 16000, 'pack', 'Recommended with compost and field monitoring.'],
        ],
        'Farm Tools and Equipment' => [
            ['Motorized Knapsack Sprayer Rental', 'Daily sprayer rental for farm protection operations.', 12000, 'day', 'Operator optional. Fuel excluded unless agreed.'],
            ['Coconut Climbing Harness Kit', 'Safety kit for palm climbing and harvest support.', 45000, 'kit', 'Includes harness, belt, rope, and safety checklist.'],
            ['Brush Cutter Rental with Operator', 'Field clearing equipment and operator service.', 35000, 'day', 'Best for undergrowth control before planting.'],
            ['Irrigation Starter Kit - 1 Acre', 'Basic irrigation set for nursery or young coconut field.', 220000, 'kit', 'Includes hoses, connectors, and setup guidance.'],
            ['Farm Tools Bundle', 'Cutlass, hoe, rake, measuring tape, tags, and field notebook.', 28000, 'bundle', 'Useful for farm hands and smallholder operations.'],
        ],
        'Labor and Farm Hands' => [
            ['Planting Crew - Coconut Establishment', 'Practical farm hands for pegging, digging, planting, and mulching.', 60000, 'day', 'Crew of 5 with supervisor.'],
            ['Weeding Team for Young Coconut Fields', 'Manual weeding and field cleanup workers.', 45000, 'day', 'Suitable where herbicide use is restricted.'],
            ['Nursery Worker - Part Time', 'Skilled nursery hand for watering, sorting, and seedling care.', 15000, 'day', 'Can be assigned to grower farms.'],
            ['Harvest Support Crew', 'Farm hands for mature palm harvest and collection.', 70000, 'day', 'Includes loading support and safety routine.'],
            ['Spraying Assistant Team', 'Trained assistants for supervised farm spraying activity.', 38000, 'day', 'Requires approved chemical and PPE.'],
        ],
        'Agronomy and Advisory' => [
            ['Farm Layout and Spacing Advisory', 'Agronomist review for coconut spacing and intercrop layout.', 50000, 'visit', 'Includes field sketch and recommendations.'],
            ['Soil Sampling and Interpretation', 'Sampling support plus soil report interpretation.', 65000, 'service', 'Laboratory fees may be separate.'],
            ['Pest and Disease Diagnostic Visit', 'On-farm diagnosis for coconut and intercrop issues.', 45000, 'visit', 'Photo evidence and action plan included.'],
            ['Intercrop Planning Session', 'Advice on income bridge crops before coconut yield starts.', 30000, 'session', 'Covers livestock/intercrop compatibility.'],
            ['Farm Operations Monthly Advisory', 'Monthly agronomy support for active growers.', 120000, 'month', 'Includes call support and visit schedule.'],
        ],
        'Logistics and Haulage' => [
            ['Seedling Delivery - Lagos/Ogun Axis', 'Safe delivery service for seedlings and nursery inputs.', 35000, 'trip', 'Vehicle size depends on quantity.'],
            ['Farm Input Haulage - Light Truck', 'Transport service for fertilizer, tools, and materials.', 55000, 'trip', 'Local routes within approved coverage.'],
            ['Produce Aggregation Pickup', 'Pickup service for coconut/intercrop produce.', 40000, 'trip', 'Buyer/seller loading terms confirmed at order.'],
            ['Cold Chain Partner Request', 'Temperature-sensitive produce movement coordination.', 120000, 'trip', 'Subject to availability and route.'],
            ['Motorcycle Dispatch for Samples/Documents', 'Fast delivery for samples, documents, and small packages.', 8000, 'trip', 'Urban and peri-urban routes.'],
        ],
        'Processing and Value Addition' => [
            ['Coconut Dehusking Service', 'Processing support for harvested coconuts.', 300, 'nut', 'Bulk pricing available.'],
            ['Coconut Oil Small Batch Processing', 'Processing service for growers and cooperatives.', 95000, 'batch', 'Packaging can be added separately.'],
            ['Desiccated Coconut Processing Slot', 'Value-add processing for food-grade coconut output.', 180000, 'slot', 'Quality and hygiene requirements apply.'],
            ['Coconut Water Bottling Support', 'Pilot bottling service for approved producers.', 250000, 'batch', 'Includes basic labeling support.'],
            ['Packaging and Label Design Service', 'Branding support for coconut value-chain products.', 75000, 'service', 'Includes print-ready label files.'],
        ],
        'Offtake and Procurement' => [
            ['Bulk Coconut Procurement Request', 'Buyer demand for mature coconuts from verified growers.', 500, 'nut', 'Quantity and delivery terms confirmed by quote.'],
            ['Intercrop Produce Offtake - Cassava/Maize', 'Procurement opportunity for bridge-crop growers.', 250000, 'lot', 'Lot price varies by quality and volume.'],
            ['Seedling Bulk Buyer Tender', 'Procurement request for verified nurseries.', 1000000, 'tender', 'Seller must provide nursery evidence.'],
            ['Coconut Husk Supply Contract', 'Recurring demand for husk and fiber material.', 180000, 'month', 'Monthly supply agreement available.'],
            ['Processor Aggregation Contract', 'Offtake arrangement for processors and cooperatives.', 500000, 'contract', 'Requires verified supply capacity.'],
        ],
        'Finance and Insurance' => [
            ['Farm Input Credit Readiness Review', 'Assessment service before input credit or partner funding.', 25000, 'review', 'Checks farm profile, wallet, and verification status.'],
            ['Coconut Farm Insurance Advisory', 'Guidance on risk coverage options for farms.', 15000, 'session', 'Policy purchase handled with partner provider.'],
            ['Cooperative Finance Documentation Pack', 'Document readiness support for cooperative funding.', 70000, 'pack', 'Includes checklist and review.'],
            ['Wallet Settlement Reconciliation Service', 'Support for marketplace and wallet transaction records.', 20000, 'case', 'Useful for sellers and cooperatives.'],
            ['Investor Due Diligence Desk', 'Commercial review support for marketplace buyers/investors.', 150000, 'desk', 'Includes document and field evidence summary.'],
        ],
        'Training and Certification' => [
            ['Marketplace Seller Readiness Clinic', 'Practical training on storefront setup, listing quality, and buyer trust.', 25000, 'seat', 'Includes checklist and certificate eligibility.'],
            ['Farm Hand Safety Orientation', 'Safety basics for practical farm workers.', 10000, 'seat', 'Recommended before field assignment.'],
            ['Provider Accreditation Preparation', 'Course support for input/service provider readiness.', 30000, 'seat', 'Covers documents, coverage, and product listing.'],
            ['Grower Digital Onboarding Support', 'Assisted onboarding for grower dashboard and marketplace use.', 0, 'seat', 'Free orientation for registered growers.'],
            ['Field Evidence and Reporting Course', 'Training on field documentation and proof collection.', 20000, 'seat', 'Useful for agents, growers, and providers.'],
        ],
    ];

    $categoryStmt = $pdo->prepare("SELECT id, listing_type FROM marketplace_categories WHERE name = ? LIMIT 1");
    $exists = $pdo->prepare("SELECT id FROM marketplace_listings WHERE slug = ? LIMIT 1");
    $insert = $pdo->prepare("
        INSERT INTO marketplace_listings
            (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, min_order_quantity, location_label, fulfillment_method, availability_status, approval_status, is_featured, tags)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'fixed', ?, ?, 1, 'Nigeria coverage', 'Marketplace checkout, seller confirmation, delivery tracking', 'available', 'approved', ?, ?)
    ");
    foreach ($rows as $categoryName => $items) {
        $categoryStmt->execute([$categoryName]);
        $category = $categoryStmt->fetch();
        if (!$category) {
            continue;
        }
        foreach ($items as $index => $item) {
            [$title, $summary, $price, $unit, $description] = $item;
            $slug = marketplace_slug($title);
            $exists->execute([$slug]);
            if ($exists->fetch()) {
                continue;
            }
            $insert->execute([
                $sellerId,
                (int) $category['id'],
                (string) $category['listing_type'],
                $title,
                $slug,
                $summary,
                $description,
                (float) $price,
                100,
                $unit,
                $index === 0 ? 1 : 0,
                $categoryName . ', NATCODEV, verified',
            ]);
        }
    }
}

function marketplace_promo_ref(): string
{
    return 'MKT-AD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function marketplace_seed_featured_promotions(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'marketplace_promotions')) {
        return;
    }
    $sellerId = marketplace_official_seller_id($pdo);
    $listingStmt = $pdo->query("SELECT id, title FROM marketplace_listings WHERE approval_status = 'approved' ORDER BY is_featured DESC, id LIMIT 10");
    $listings = $listingStmt->fetchAll();
    $placements = [
        ['Premium Dwarf Coconut Seedlings', 'High survival seedlings for serious growers.', 'homepage_banner', 'assets/market/featured/vendor-ad-01.png'],
        ['Organic Coconut Compost', 'Build soil health before coconut yield begins.', 'homepage_banner', 'assets/market/featured/vendor-ad-02.png'],
        ['Farm Tools & Pruning Equipment', 'Quality tools for clean field operations.', 'homepage_banner', 'assets/market/featured/vendor-ad-03.png'],
        ['Planting Crew Services', 'Verified farm hands for establishment season.', 'homepage_banner', 'assets/market/featured/vendor-ad-04.png'],
        ['Nursery Trust Pack', 'Seedlings, advice, and delivery support.', 'homepage_ad', 'assets/market/featured/vendor-ad-05.png'],
        ['Coconut Farm Logistics', 'Move inputs and seedlings safely.', 'homepage_ad', 'assets/market/featured/vendor-ad-06.png'],
        ['Verified Compost Suppliers', 'Nutrient support for intercropping and stands.', 'homepage_ad', 'assets/market/featured/vendor-ad-07.png'],
        ['Seedling Delivery Deals', 'Bulk bundles with tracked delivery.', 'homepage_ad', 'assets/market/featured/vendor-ad-08.png'],
        ['Input Provider Spotlight', 'Certified farm inputs from approved sellers.', 'homepage_ad', 'assets/market/featured/vendor-ad-09.png'],
        ['Soil & Mulch Marketplace', 'Prepare your farm before year-three yield.', 'homepage_ad', 'assets/market/featured/vendor-ad-10.png'],
    ];
    $exists = $pdo->prepare("SELECT id FROM marketplace_promotions WHERE title = ? LIMIT 1");
    $insert = $pdo->prepare("
        INSERT INTO marketplace_promotions
            (promo_ref, seller_id, listing_id, category_id, title, subtitle, placement, image_path, target_url, amount, duration_days, payment_method, status, starts_at, ends_at, approved_at)
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 0, 365, 'seeded', 'active', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 365 DAY), NOW())
    ");
    foreach ($placements as $index => [$title, $subtitle, $placement, $image]) {
        $exists->execute([$title]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $listing = $listings[$index % max(1, count($listings))] ?? null;
        $listingId = $listing ? (int) $listing['id'] : null;
        $target = $listingId ? 'product.php?id=' . $listingId : 'index.php';
        $insert->execute([marketplace_promo_ref(), $sellerId, $listingId, $title, $subtitle, $placement, $image, $target]);
    }
}

function marketplace_active_promotions(PDO $pdo, string $placement, int $limit = 10): array
{
    if (!app_table_exists($pdo, 'marketplace_promotions')) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT p.*, s.store_name, s.slug seller_slug, l.title listing_title
        FROM marketplace_promotions p
        LEFT JOIN marketplace_sellers s ON s.id = p.seller_id
        LEFT JOIN marketplace_listings l ON l.id = p.listing_id
        WHERE p.placement = ?
          AND p.status = 'active'
          AND (p.starts_at IS NULL OR p.starts_at <= NOW())
          AND (p.ends_at IS NULL OR p.ends_at >= NOW())
        ORDER BY p.amount DESC, p.created_at DESC
        LIMIT " . max(1, min(20, $limit)) . "
    ");
    $stmt->execute([$placement]);
    return $stmt->fetchAll();
}

function marketplace_category_id_for_type(PDO $pdo, string $type): ?int
{
    $stmt = $pdo->prepare("SELECT id FROM marketplace_categories WHERE listing_type = ? AND is_active = 1 ORDER BY sort_order, id LIMIT 1");
    $stmt->execute([$type]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function marketplace_categories(PDO $pdo, ?string $type = null): array
{
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM marketplace_categories WHERE is_active = 1 AND listing_type = ? ORDER BY sort_order, name");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
    return $pdo->query("SELECT * FROM marketplace_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
}

function marketplace_current_seller(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM marketplace_sellers WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $seller = $stmt->fetch();
    return $seller ?: null;
}

function marketplace_user_default_seller_type(array $user): string
{
    $role = (string) ($user['platform_role'] ?? $user['role'] ?? 'grower');
    return match ($role) {
        'agronomist' => 'agronomist',
        'agric_extensionist', 'extensionist' => 'extensionist',
        'investor' => 'investor_offtaker',
        'provider' => 'service_provider',
        default => 'grower',
    };
}

function marketplace_inquiry_ref(): string
{
    return 'INQ-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function marketplace_order_ref(): string
{
    return 'MKT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function marketplace_public_css(): string
{
    return '
    :root{--green:#245317;--leaf:#13834b;--gold:#c49a1d;--ink:#172211;--muted:#66715f;--line:#dfe8d8;--bg:#f6f8f3;--panel:#fff;--danger:#a32020;--shadow:0 14px 34px rgba(24,43,18,.08)}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif} a{color:var(--leaf);font-weight:800;text-decoration:none} a:hover{text-decoration:underline}
    .topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid rgba(24,43,18,.1);box-shadow:0 8px 24px rgba(24,43,18,.06)} .bar{max-width:1280px;margin:0 auto;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
    .brand{display:flex;align-items:center;gap:11px;color:var(--green)} .brand img{width:46px;height:46px;border-radius:50%;object-fit:contain;border:1px solid var(--line);background:#fff}.brand strong{display:block}.brand span{display:block;color:var(--muted);font-size:.86rem;font-weight:650}
    .nav{display:flex;gap:10px;flex-wrap:wrap}.nav a{padding:9px 11px;border-radius:6px;color:#33412d}.nav a.active,.nav a:hover{background:#edf6e8;text-decoration:none;color:var(--green)}
    main{max-width:1280px;margin:0 auto;padding:26px 22px 42px}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}.page-head h1{margin:0;color:var(--green);font-size:clamp(2rem,4vw,3.1rem);line-height:1.04}.page-head p{color:var(--muted);max-width:760px;line-height:1.6}
    .panel,.card,.stat{background:#fff;border:1px solid rgba(24,43,18,.09);border-radius:8px;box-shadow:var(--shadow);padding:18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.layout{display:grid;grid-template-columns:330px 1fr;gap:18px;align-items:start}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:16px 0}
    .metric{font-size:2rem;font-weight:900;color:var(--green);line-height:1}.listing-card{display:grid;gap:9px}.listing-card h2,.card h2,.panel h2{margin:0;color:var(--green)}.store-card{border-left:4px solid var(--gold)}.price{font-size:1.25rem;font-weight:900;color:#203a13}.muted,.meta,small{color:var(--muted)}.badge{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef7f1;color:var(--green);font-size:.8rem;font-weight:850}.badge.pending{background:#fff7df;color:#8a5a00}.badge.rejected,.badge.suspended{background:#fff3f3;color:var(--danger)}
    label{display:block;font-weight:800;margin:10px 0 6px}input,select,textarea{width:100%;border:1px solid #cbd8c4;border-radius:6px;padding:11px 12px;font:inherit}textarea{min-height:115px}input:focus,select:focus,textarea:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(20,115,58,.14);outline:none}
    button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--green);color:#fff;border:0;border-radius:6px;padding:11px 14px;font-weight:850;cursor:pointer;text-decoration:none;box-shadow:0 10px 24px rgba(45,80,22,.18)}button:hover,.button:hover{background:var(--leaf);color:#fff;text-decoration:none}.button.secondary,button.secondary{background:#edf6e8;color:var(--green);border:1px solid var(--line);box-shadow:none}.actions,.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:12px}.notice{padding:13px 15px;border-radius:8px;margin:16px 0}.ok{background:#eaf8f0;color:#0f6b3c;border:1px solid #bfe8cf}.error{background:#fff3f3;color:var(--danger);border:1px solid #ffd2d2}.empty{border:1px dashed var(--line);border-radius:8px;padding:18px;color:var(--muted);background:#fff}
    table{width:100%;border-collapse:collapse;background:#fff;border:1px solid rgba(24,43,18,.09);border-radius:8px;overflow:hidden}th,td{padding:11px;border-bottom:1px solid #edf1ea;text-align:left;vertical-align:top}th{background:#eef6e9;color:#243b1d}
    @media(max-width:900px){.page-head,.layout{grid-template-columns:1fr;display:grid}.bar{align-items:flex-start}.nav{width:100%}}';
}

function marketplace_public_header(string $title, string $active = 'marketplace'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $logo = app_primary_logo_url();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Marketplace</title>
  <style><?= marketplace_public_css() ?></style>
</head>
<body>
  <header class="topbar">
    <div class="bar">
      <a class="brand" href="../index.php">
        <img src="<?= e($logo) ?>" alt="NATCODEV">
        <span><strong>NATCODEV Marketplace</strong><span>Public agricultural trade desk</span></span>
      </a>
      <nav class="nav" aria-label="Marketplace navigation">
        <a class="<?= $active === 'marketplace' ? 'active' : '' ?>" href="index.php">Marketplace</a>
        <a class="<?= $active === 'stores' ? 'active' : '' ?>" href="index.php?view=stores">Seller Directory</a>
        <a href="../market/seller-central.php">Seller Central</a>
        <a href="../market/orders.php">Buyer Orders</a>
      </nav>
    </div>
  </header>
  <main>
<?php
}

function marketplace_public_footer(): void
{
    ?>
  </main>
</body>
</html>
<?php
}
