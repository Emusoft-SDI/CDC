<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/_market.php';

function seller_query_context(PDO $pdo, array $user, bool $handlePost = false): array
{
    $userId = (int) $user['id'];
    $sellerTypes = marketplace_seller_types();
    $listingTypes = marketplace_listing_types();
    $categories = marketplace_categories($pdo);
    $seller = marketplace_current_seller($pdo, $userId);
    $message = '';
    $error = '';

    if ($handlePost && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            $error = 'Invalid security token.';
        } else {
            try {
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'save_seller') {
                    $storeName = trim((string) ($_POST['store_name'] ?? ''));
                    $sellerType = (string) ($_POST['seller_type'] ?? marketplace_user_default_seller_type($user));
                    if (!isset($sellerTypes[$sellerType])) {
                        $sellerType = 'other';
                    }
                    if ($storeName === '') {
                        throw new RuntimeException('Store name is required.');
                    }
                    if ($seller) {
                        $slug = marketplace_unique_slug($pdo, 'marketplace_sellers', $storeName, (int) $seller['id']);
                        $stmt = $pdo->prepare("
                            UPDATE marketplace_sellers
                            SET seller_type=?, store_name=?, slug=?, description=?, contact_person=?, email=?, phone=?, whatsapp=?,
                                location_label=?, coverage_area=?, fulfillment_options=?, approval_status=IF(approval_status='approved','approved','pending')
                            WHERE id=? AND user_id=?
                        ");
                        $stmt->execute([$sellerType, $storeName, $slug, trim((string) ($_POST['description'] ?? '')), trim((string) ($_POST['contact_person'] ?? '')), trim((string) ($_POST['email'] ?? '')), trim((string) ($_POST['phone'] ?? '')), trim((string) ($_POST['whatsapp'] ?? '')), trim((string) ($_POST['location_label'] ?? '')), trim((string) ($_POST['coverage_area'] ?? '')), trim((string) ($_POST['fulfillment_options'] ?? '')), (int) $seller['id'], $userId]);
                        $message = 'Seller profile updated.';
                    } else {
                        $slug = marketplace_unique_slug($pdo, 'marketplace_sellers', $storeName);
                        $stmt = $pdo->prepare("
                            INSERT INTO marketplace_sellers
                                (user_id, seller_type, store_name, slug, description, contact_person, email, phone, whatsapp, location_label, coverage_area, fulfillment_options)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$userId, $sellerType, $storeName, $slug, trim((string) ($_POST['description'] ?? '')), trim((string) ($_POST['contact_person'] ?? '')), trim((string) ($_POST['email'] ?? '')), trim((string) ($_POST['phone'] ?? '')), trim((string) ($_POST['whatsapp'] ?? '')), trim((string) ($_POST['location_label'] ?? '')), trim((string) ($_POST['coverage_area'] ?? '')), trim((string) ($_POST['fulfillment_options'] ?? ''))]);
                        $message = 'Seller profile submitted for review.';
                    }
                    $seller = marketplace_current_seller($pdo, $userId);
                }

                if ($action === 'save_listing' && $seller) {
                    $listingId = (int) ($_POST['listing_id'] ?? 0);
                    $title = trim((string) ($_POST['title'] ?? ''));
                    if ($title === '') {
                        throw new RuntimeException('Listing title is required.');
                    }
                    $listingType = (string) ($_POST['listing_type'] ?? 'product');
                    if (!isset($listingTypes[$listingType])) {
                        $listingType = 'product';
                    }
                    $uploadedImagePath = market_upload_listing_image('listing_image');
                    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: marketplace_category_id_for_type($pdo, $listingType);
                    $availability = (string) ($_POST['availability_status'] ?? 'available');
                    if (!in_array($availability, ['available', 'limited', 'out_of_stock', 'paused'], true)) {
                        $availability = 'available';
                    }
                    $payload = [
                        $categoryId, $listingType, $title, trim((string) ($_POST['summary'] ?? '')), trim((string) ($_POST['description'] ?? '')),
                        max(0, (float) ($_POST['price'] ?? 0)), trim((string) ($_POST['price_unit'] ?? '')), $_POST['quantity_available'] === '' ? null : max(0, (float) $_POST['quantity_available']),
                        trim((string) ($_POST['unit'] ?? '')), $_POST['min_order_quantity'] === '' ? null : max(0, (float) $_POST['min_order_quantity']),
                        trim((string) ($_POST['location_label'] ?? '')), trim((string) ($_POST['fulfillment_method'] ?? '')), $availability,
                        trim((string) ($_POST['mpn'] ?? '')), trim((string) ($_POST['gtin'] ?? '')), trim((string) ($_POST['gtin_type'] ?? '')),
                        trim((string) ($_POST['origin_country'] ?? '')), trim((string) ($_POST['manufacturer'] ?? '')), trim((string) ($_POST['brand'] ?? '')),
                        trim((string) ($_POST['model_number'] ?? '')), trim((string) ($_POST['tags'] ?? '')), isset($_POST['requires_shipping']) ? 1 : 0, isset($_POST['downloadable']) ? 1 : 0,
                    ];
                    if ($listingId > 0) {
                        $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title, $listingId);
                        $stmt = $pdo->prepare("
                            UPDATE marketplace_listings
                            SET category_id=?, listing_type=?, title=?, summary=?, description=?, price=?, price_unit=?, quantity_available=?,
                                unit=?, min_order_quantity=?, location_label=?, fulfillment_method=?, availability_status=?, approval_status='pending',
                                mpn=?, gtin=?, gtin_type=?, origin_country=?, manufacturer=?, brand=?, model_number=?, tags=?, requires_shipping=?, downloadable=?, slug=?
                            WHERE id=? AND seller_id=?
                        ");
                        $stmt->execute([...$payload, $slug, $listingId, (int) $seller['id']]);
                        if ($uploadedImagePath !== null) {
                            $pdo->prepare("UPDATE marketplace_listings SET image_path = ? WHERE id = ? AND seller_id = ?")->execute([$uploadedImagePath, $listingId, (int) $seller['id']]);
                        }
                        $message = 'Listing updated and sent for approval.';
                    } else {
                        $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title);
                        $stmt = $pdo->prepare("
                            INSERT INTO marketplace_listings
                                (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, min_order_quantity, location_label, fulfillment_method, availability_status, approval_status, mpn, gtin, gtin_type, origin_country, manufacturer, brand, model_number, tags, requires_shipping, downloadable)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([(int) $seller['id'], $categoryId, $listingType, $title, $slug, ...array_slice($payload, 3)]);
                        if ($uploadedImagePath !== null) {
                            $pdo->prepare("UPDATE marketplace_listings SET image_path = ? WHERE id = ? AND seller_id = ?")->execute([$uploadedImagePath, (int) $pdo->lastInsertId(), (int) $seller['id']]);
                        }
                        $message = 'Listing created and sent for approval.';
                    }
                }

                if ($action === 'delete_listing' && $seller) {
                    $listingId = (int) ($_POST['listing_id'] ?? 0);
                    if ($listingId <= 0) {
                        throw new RuntimeException('Listing ID is required.');
                    }
                    $stmt = $pdo->prepare("SELECT id, title FROM marketplace_listings WHERE id = ? AND seller_id = ? LIMIT 1");
                    $stmt->execute([$listingId, (int) $seller['id']]);
                    $listing = $stmt->fetch();
                    if ($listing) {
                        admin_queue_verified_delete_request($pdo, 'marketplace_listings', $listingId, (string) $listing['title'], 'Marketplace seller requested listing deletion.');
                        $message = 'Listing delete request sent to Super Admin for approval.';
                    } else {
                        $message = 'Listing not found for this seller.';
                    }
                }

                if ($action === 'reply_inquiry' && $seller) {
                    $inquiryId = (int) ($_POST['inquiry_id'] ?? 0);
                    $quotedAmount = $_POST['quoted_amount'] === '' ? null : max(0, (float) $_POST['quoted_amount']);
                    $status = (string) ($_POST['status'] ?? 'responded');
                    if (!in_array($status, ['responded', 'quoted', 'accepted', 'closed', 'cancelled'], true)) {
                        $status = 'responded';
                    }
                    $stmt = $pdo->prepare("UPDATE marketplace_inquiries SET seller_reply=?, quoted_amount=?, quoted_at=IF(? IS NULL, quoted_at, NOW()), status=? WHERE id=? AND seller_id=?");
                    $stmt->execute([trim((string) ($_POST['seller_reply'] ?? '')), $quotedAmount, $quotedAmount, $status, $inquiryId, (int) $seller['id']]);
                    $message = 'Buyer message updated.';
                }

                if ($action === 'create_order' && $seller) {
                    $inquiryId = (int) ($_POST['inquiry_id'] ?? 0);
                    $stmt = $pdo->prepare("SELECT i.*, l.price FROM marketplace_inquiries i JOIN marketplace_listings l ON l.id=i.listing_id WHERE i.id=? AND i.seller_id=? LIMIT 1");
                    $stmt->execute([$inquiryId, (int) $seller['id']]);
                    $inq = $stmt->fetch();
                    if ($inq) {
                        $quantity = max(1, (float) ($inq['quantity'] ?: 1));
                        $unitPrice = (float) ($inq['quoted_amount'] ?: $inq['price']);
                        $stmt = $pdo->prepare("
                            INSERT INTO marketplace_orders
                                (order_ref, inquiry_id, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status, fulfillment_note)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'quoted', ?)
                        ");
                        $stmt->execute([marketplace_order_ref(), (int) $inq['id'], (int) $inq['listing_id'], (int) $seller['id'], $inq['buyer_user_id'] ?: null, $inq['buyer_name'], $inq['buyer_email'], $inq['buyer_phone'], $quantity, $unitPrice, $quantity * $unitPrice, trim((string) ($_POST['fulfillment_note'] ?? ''))]);
                        $pdo->prepare("UPDATE marketplace_inquiries SET status='quoted' WHERE id=?")->execute([(int) $inq['id']]);
                        $message = 'Order created from buyer request.';
                    }
                }

                if ($action === 'update_order' && $seller) {
                    $status = (string) ($_POST['status'] ?? 'preparing');
                    if (!in_array($status, ['quoted', 'accepted', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'cancelled', 'disputed'], true)) {
                        $status = 'preparing';
                    }
                    $deliveryStatus = (string) ($_POST['delivery_status'] ?? 'awaiting_seller');
                    if (!in_array($deliveryStatus, ['not_started', 'awaiting_seller', 'packing', 'ready_for_pickup', 'scheduled', 'in_transit', 'delivered', 'failed', 'returned'], true)) {
                        $deliveryStatus = 'awaiting_seller';
                    }
                    $stmt = $pdo->prepare("UPDATE marketplace_orders SET status=?, delivery_status=?, fulfillment_note=? WHERE id=? AND seller_id=?");
                    $stmt->execute([$status, $deliveryStatus, trim((string) ($_POST['fulfillment_note'] ?? '')), (int) ($_POST['order_id'] ?? 0), (int) $seller['id']]);
                    $message = 'Order status updated.';
                }
                $seller = marketplace_current_seller($pdo, $userId);
            } catch (Throwable $e) {
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to complete seller action.';
            }
        }
    }

    $listings = $inquiries = $orders = [];
    $editListing = null;
    if ($seller) {
        $stmt = $pdo->prepare("SELECT l.*, c.name category_name FROM marketplace_listings l LEFT JOIN marketplace_categories c ON c.id=l.category_id WHERE l.seller_id=? ORDER BY l.created_at DESC");
        $stmt->execute([(int) $seller['id']]);
        $listings = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT i.*, l.title listing_title FROM marketplace_inquiries i JOIN marketplace_listings l ON l.id=i.listing_id WHERE i.seller_id=? ORDER BY i.created_at DESC LIMIT 80");
        $stmt->execute([(int) $seller['id']]);
        $inquiries = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT o.*, l.title listing_title FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id WHERE o.seller_id=? ORDER BY o.created_at DESC LIMIT 80");
        $stmt->execute([(int) $seller['id']]);
        $orders = $stmt->fetchAll();
        if ((int) ($_GET['edit_listing'] ?? 0) > 0) {
            foreach ($listings as $row) {
                if ((int) $row['id'] === (int) $_GET['edit_listing']) {
                    $editListing = $row;
                    break;
                }
            }
        }
    }

    $sales = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], array_filter($orders, static fn(array $row): bool => !in_array((string) $row['status'], ['cancelled'], true))));
    $openOrders = count(array_filter($orders, static fn(array $row): bool => !in_array((string) $row['status'], ['completed', 'cancelled'], true)));
    $pendingPayout = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], array_filter($orders, static fn(array $row): bool => (string) ($row['payment_status'] ?? '') === 'paid' && empty($row['settled_at']))));
    return compact('sellerTypes', 'listingTypes', 'categories', 'seller', 'message', 'error', 'listings', 'inquiries', 'orders', 'editListing', 'sales', 'openOrders', 'pendingPayout');
}

function seller_access_or_message(PDO $pdo, array $user): void
{
    if (market_user_can_sell($pdo, $user)) {
        return;
    }
    seller_header('Marketplace Access Required', 'overview', $user, null);
    ?>
    <section class="sc-card sc-panel">
      <h2>Marketplace Access Required</h2>
      <p>Your current profile can browse the marketplace, but it is not enabled to operate a seller storefront.</p>
      <div class="sc-actions"><a class="sc-btn" href="index.php">Open Marketplace</a><a class="sc-btn secondary" href="../dashboard/index.php">Back to Dashboard</a></div>
    </section>
    <?php
    seller_footer();
    exit;
}

function seller_avatar(?array $seller, array $user): string
{
    if ($seller) {
        foreach (market_listing_query(db(), ['q' => (string) $seller['store_name']], 1) as $item) {
            return market_listing_image_url($item);
        }
    }
    $pic = trim((string) ($user['profile_picture'] ?? ''));
    return $pic !== '' ? '../' . ltrim($pic, '/') : '../assets/market/dwarf-coconut-seedlings.png';
}

function seller_header(string $title, string $active, array $user, ?array $seller): void
{
    $menu = [
        'overview' => ['Overview', 'seller-central.php', 'home'],
        'storefront' => ['Storefront', $seller ? 'store.php?seller=' . rawurlencode((string) $seller['slug']) : 'storefront.php', 'external-link'],
        'products' => ['Products', 'seller-products.php', 'package'],
        'add' => ['Add Product', 'seller-add-product.php', 'plus-square'],
        'inventory' => ['Inventory', 'seller-inventory.php', 'boxes'],
        'orders' => ['Orders', 'seller-orders.php', 'shopping-bag'],
        'buyers' => ['Buyers', 'seller-buyers.php', 'users'],
        'messages' => ['Messages', 'seller-messages.php', 'mail'],
        'promotions' => ['Promotions', 'seller-promotions.php', 'megaphone'],
        'payouts' => ['Payouts', 'seller-payouts.php', 'wallet'],
        'disputes' => ['Disputes', 'seller-disputes.php', 'badge-alert'],
        'reports' => ['Reports', 'seller-reports.php', 'bar-chart-3'],
        'academy' => ['Seller Academy', '../academy/my-learning.php', 'graduation-cap'],
        'settings' => ['Settings', 'seller-settings.php', 'settings'],
    ];
    $avatar = seller_avatar($seller, $user);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - Seller Central</title>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root{--green:#007a3d;--green2:#0f8f4b;--deep:#003f25;--ink:#07162f;--muted:#64748b;--line:#e2e8f0;--soft:#f7fbf8;--gold:#d49400;--orange:#f97316;--blue:#2563eb;--red:#dc2626;--shadow:0 18px 48px rgba(15,23,42,.08)}*{box-sizing:border-box}body{margin:0;background:#f8fbfa;color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif}.sc-shell{display:grid;grid-template-columns:278px minmax(0,1fr);min-height:100vh}.sc-side{height:100vh;position:sticky;top:0;overflow:auto;background:linear-gradient(155deg,#006b36,#00381f 72%);color:#fff;padding:22px 18px}.sc-brand{display:flex;gap:12px;align-items:center;color:#fff;text-decoration:none;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.15)}.sc-brand img{width:54px;height:54px;border-radius:50%;object-fit:contain;background:#fff}.sc-brand strong{font-size:1.45rem;display:block}.sc-brand span span{display:block;font-size:.78rem;line-height:1.25;opacity:.9}.sc-store{display:flex;gap:12px;align-items:center;margin:24px 0;padding:14px;border:1px solid rgba(255,255,255,.18);border-radius:14px;background:rgba(255,255,255,.08)}.sc-store img{width:58px;height:58px;border-radius:50%;object-fit:cover}.sc-store b,.sc-store span{display:block}.sc-store span{font-size:.82rem;color:#c8f5d9}.sc-menu{display:grid;gap:7px}.sc-menu a{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none;font-weight:850;padding:11px 13px;border-radius:10px}.sc-menu a.active,.sc-menu a:hover{background:linear-gradient(90deg,#0f9f55,#087140);box-shadow:0 10px 22px rgba(0,0,0,.18)}.sc-upgrade{margin-top:30px;border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:16px;background:rgba(255,255,255,.06)}.sc-main{min-width:0}.sc-top{height:72px;border-bottom:1px solid var(--line);background:#fff;display:flex;align-items:center;gap:18px;padding:0 28px;position:sticky;top:0;z-index:5}.sc-search{flex:1;max-width:610px;display:flex;align-items:center;gap:9px;background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:11px 14px}.sc-search input{border:0;background:transparent;outline:0;width:100%;font:inherit}.sc-chip{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:10px;padding:9px 11px;background:#fff;font-weight:850;color:var(--ink);text-decoration:none}.sc-content{padding:28px}.sc-title{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}.sc-title h1{margin:0 0 6px;font-size:2rem;line-height:1.1}.sc-title p{margin:0;color:var(--muted)}.sc-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;margin-bottom:18px}.sc-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow)}.sc-kpi{padding:18px;display:flex;align-items:center;gap:13px}.sc-icon{width:48px;height:48px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eaf7ef;color:var(--green);flex:none}.sc-icon.blue{background:#eaf1ff;color:var(--blue)}.sc-icon.orange{background:#fff2e6;color:var(--orange)}.sc-icon.gold{background:#fff6dc;color:var(--gold)}.sc-icon.red{background:#fee2e2;color:var(--red)}.sc-icon.purple{background:#f2e8ff;color:#7c3aed}.sc-kpi small{display:block;color:var(--muted);font-weight:850}.sc-kpi b{display:block;font-size:1.35rem}.sc-kpi span{font-size:.82rem;color:var(--green);font-weight:850}.sc-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.sc-panel{padding:16px}.sc-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid #edf2f7;padding-bottom:10px}.sc-panel-head h2{font-size:1rem;margin:0}.sc-link{color:var(--green);font-weight:950;text-decoration:none}.sc-list{display:grid;gap:10px}.sc-row{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #edf2f7}.sc-row:last-child{border-bottom:0}.thumb{width:56px;height:56px;border-radius:8px;object-fit:cover;background:#eaf7ef}.muted{color:var(--muted)}.badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 9px;font-size:.75rem;font-weight:950;background:#eef2f7;color:#334155}.badge.good{background:#e8f8ee;color:#087140}.badge.warn{background:#fff4dc;color:#a16207}.badge.danger{background:#fee2e2;color:#b91c1c}.badge.blue{background:#eaf1ff;color:#1d4ed8}.sc-table{width:100%;border-collapse:collapse}.sc-table th,.sc-table td{text-align:left;border-bottom:1px solid #edf2f7;padding:11px}.sc-table th{font-size:.78rem;text-transform:uppercase;color:#64748b;background:#f8fafc}.sc-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:950;cursor:pointer}.sc-btn.secondary{background:#fff;color:var(--green)}.sc-btn.soft{background:#eef8f1;color:var(--green);border-color:#d9efe1}.sc-actions{display:flex;gap:10px;flex-wrap:wrap}.sc-form{display:grid;gap:12px}.sc-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sc-form-grid .wide{grid-column:1/-1}.sc-form input,.sc-form select,.sc-form textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:8px;font:inherit}.sc-form textarea{min-height:100px}.quick-actions{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.quick-actions a{display:grid;place-items:center;text-align:center;gap:7px;min-height:94px;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--ink);font-weight:950;background:#fff}.quick-actions small{display:block;color:var(--muted);font-weight:500}.alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:850}.alert.ok{background:#eaf8ef;color:#11602f;border:1px solid #bce6c8}.alert.err{background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3}.empty{padding:22px;border:1px dashed var(--line);border-radius:12px;color:var(--muted);background:#fbfdfc}.footer{display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:.9rem;padding:20px 28px;border-top:1px solid var(--line);background:#fff;margin-top:12px}@media(max-width:1250px){.sc-kpis{grid-template-columns:repeat(3,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}.quick-actions{grid-template-columns:repeat(3,1fr)}}@media(max-width:860px){.sc-shell{grid-template-columns:1fr}.sc-side{position:relative;height:auto}.sc-top{position:relative;height:auto;flex-wrap:wrap;padding:14px}.sc-content{padding:18px}.sc-kpis,.sc-form-grid{grid-template-columns:1fr}.quick-actions{grid-template-columns:1fr}.footer{display:block}.sc-title{display:block}}
  </style>
</head>
<body>
<div class="sc-shell">
  <aside class="sc-side">
    <a class="sc-brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><span>National Coconut Development &<br>Propagation Initiative</span></span></a>
    <div class="sc-store"><img src="<?= e($avatar) ?>" alt=""><div><b><?= e((string) ($seller['store_name'] ?? $user['name'] ?? 'Seller')) ?></b><span><?= $seller ? e(marketplace_status_label((string) $seller['approval_status'])) : 'Store pending setup' ?></span><span>Seller ID: <?= $seller ? 'NAT-SL-' . str_pad((string) $seller['id'], 5, '0', STR_PAD_LEFT) : 'Not created' ?></span></div></div>
    <nav class="sc-menu">
      <?php foreach ($menu as $key => [$label, $href, $icon]): ?>
        <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i data-lucide="<?= e($icon) ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sc-upgrade"><h3>Grow your business with NATCODEV</h3><p>Reach buyers, manage products, receive orders, and build trust.</p><a class="sc-btn secondary" href="../provider/index.php">Upgrade Account</a></div>
  </aside>
  <section class="sc-main">
    <header class="sc-top">
      <a class="sc-chip" href="seller-central.php"><i data-lucide="menu"></i></a>
      <form class="sc-search" action="seller-search.php" method="get"><i data-lucide="search"></i><input name="q" placeholder="Search products, orders, buyers..."><span class="badge good">Ctrl + K</span></form>
      <a class="sc-chip" href="../dashboard/index.php"><i data-lucide="layout-dashboard"></i> My Dashboard</a>
      <a class="sc-chip" href="index.php"><i data-lucide="store"></i> Marketplace</a>
      <a class="sc-chip" href="../academy/my-learning.php"><i data-lucide="graduation-cap"></i> Academy</a>
      <a class="sc-chip" href="../dashboard/wallet.php"><i data-lucide="wallet"></i> Wallet</a>
      <a class="sc-chip" href="../dashboard/logout.php"><i data-lucide="log-out"></i></a>
    </header>
    <main class="sc-content">
      <div class="sc-title"><div><h1><?= e($title) ?></h1><p>Manage your store, products, orders, and growth on NATCODEV Marketplace.</p></div><span class="sc-chip">Store Health <b style="color:var(--green)">Excellent</b></span></div>
<?php
}

function seller_footer(): void
{
    ?>
    </main>
    <footer class="footer"><span>&copy; 2026 NATCODEV. All rights reserved.</span><span>Grow more. Earn more. Empowering coconut businesses.</span><span>Seller Console v2.1.0</span></footer>
  </section>
</div>
<script src="../lib/location-picker.js"></script>
<script>if(window.lucide){lucide.createIcons();}</script>
</body>
</html>
<?php
}

function seller_kpis(array $ctx): void
{
    $listings = $ctx['listings'];
    $orders = $ctx['orders'];
    $sales = (float) $ctx['sales'];
    $openOrders = (int) $ctx['openOrders'];
    $lowStock = count(array_filter($listings, static fn(array $row): bool => (float) ($row['quantity_available'] ?? 99) > 0 && (float) ($row['quantity_available'] ?? 99) <= 10));
    $pendingPayout = (float) $ctx['pendingPayout'];
    ?>
    <section class="sc-kpis">
      <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="wallet"></i></span><div><small>Today’s Sales</small><b><?= e(marketplace_money($sales)) ?></b><span>+ active revenue</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="shopping-bag"></i></span><div><small>Open Orders</small><b><?= $openOrders ?></b><span>Needs fulfillment</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon blue"><i data-lucide="package"></i></span><div><small>Active Products</small><b><?= count($listings) ?></b><span><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['approval_status'] === 'approved')) ?> approved</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="triangle-alert"></i></span><div><small>Low Stock Items</small><b><?= $lowStock ?></b><span>Review inventory</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon gold"><i data-lucide="coins"></i></span><div><small>Pending Payout</small><b><?= e(marketplace_money($pendingPayout)) ?></b><span>Wallet settlement</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="star"></i></span><div><small>Store Rating</small><b>4.7</b><span>Verified reviews</span></div></div>
    </section>
<?php
}
