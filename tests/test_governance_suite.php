<?php
declare(strict_types=1);

/**
 * NATCODEV Super Admin Governance Test Suite
 * Covers the governance controls added to the console:
 * - Soft-delete (notification_templates, staff_profiles, farm_verifications, user_import_records, document_requirements, grower_farms, provider_registry, marketplace_sellers, marketplace_listings, certificates, academy_certificates) read isolation + reactivation
 * - Recycle-bin snapshot + restore
 * - Console-managed secrets (settings-first, .env fallback)
 * - Module mode kill-switch (paused/setup disables a module)
 * - Audit logging
 * - Session control (session epoch / force sign-out)
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/super-admin-console.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/admin-user-import.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/marketplace.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Start before any suite output so requiring this file cannot emit a warning.
    @session_start();
}

function run_governance_tests(): void
{
    TestHarness::start('Scenario 13: Super Admin Governance (soft-delete, secrets, module mode, audit, sessions)');
    $pdo = TestHarness::createTestDb();
    app_ensure_core_schema($pdo);
    admin_ensure_schema($pdo);
    admin_ensure_action_request_schema($pdo);
    fm_ensure_schema($pdo);
    super_admin_ensure_schema($pdo);
    admin_ensure_import_schema($pdo);

    // =====================================================================
    // 1. Soft-delete: notification_templates read isolation + reactivation
    // =====================================================================
    $templateName = 'gov_tpl_' . bin2hex(random_bytes(3));
    $pdo->prepare("INSERT INTO notification_templates (template_name, template_type, message_template, is_active) VALUES (?, 'sms', 'HELLO {name}', 1)")->execute([$templateName]);

    TestHarness::assertEqual('HELLO {name}', natcodev_template($pdo, $templateName, 'sms', 'FALLBACK'), 'Soft-delete: live template is used by the dispatcher');

    $pdo->prepare("UPDATE notification_templates SET deleted_at = NOW() WHERE template_name = ?")->execute([$templateName]);
    TestHarness::assertEqual('FALLBACK', natcodev_template($pdo, $templateName, 'sms', 'FALLBACK'), 'Soft-delete: deleted template is ignored by the dispatcher');

    $pdo->prepare("UPDATE notification_templates SET deleted_at = NULL WHERE template_name = ?")->execute([$templateName]);
    TestHarness::assertEqual('HELLO {name}', natcodev_template($pdo, $templateName, 'sms', 'FALLBACK'), 'Soft-delete: restored template is used again');

    // =====================================================================
    // 2. Soft-delete: staff_profiles + reactivation on role re-assignment
    // =====================================================================
    $email = 'gov_staff_' . bin2hex(random_bytes(3)) . '@example.test';
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role, account_status, created_at) VALUES ('Gov Staff', ?, 'x', 'field_agent', 'field_agent', 'active', NOW())")->execute([$email]);
    $staffUserId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO staff_profiles (user_id, staff_type, state, status) VALUES (?, 'field_agent', 'Lagos', 'active')")->execute([$staffUserId]);

    $liveStaff = (int) $pdo->query("SELECT COUNT(*) FROM staff_profiles WHERE user_id = {$staffUserId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveStaff, 'Soft-delete: live staff profile is visible to the filtered read');

    $pdo->prepare("UPDATE staff_profiles SET deleted_at = NOW() WHERE user_id = ?")->execute([$staffUserId]);
    $hiddenStaff = (int) $pdo->query("SELECT COUNT(*) FROM staff_profiles WHERE user_id = {$staffUserId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenStaff, 'Soft-delete: deleted staff profile is hidden from the filtered read');

    admin_upsert_staff_profile($pdo, $staffUserId, 'field_agent', ['state' => 'Lagos']);
    $reactivated = $pdo->query("SELECT deleted_at FROM staff_profiles WHERE user_id = {$staffUserId}")->fetchColumn();
    TestHarness::assert($reactivated === null, 'Soft-delete: re-assigning the staff role reactivates the profile');

    // =====================================================================
    // 3. Recycle bin: snapshot + restore of a deleted record
    // =====================================================================
    $pdo->prepare("UPDATE staff_profiles SET deleted_at = NOW() WHERE user_id = ?")->execute([$staffUserId]);
    $staffProfileId = (int) $pdo->query("SELECT id FROM staff_profiles WHERE user_id = {$staffUserId}")->fetchColumn();
    admin_snapshot_deleted_record($pdo, 'staff_profiles', $staffProfileId, null, null);
    $snapshotId = (int) $pdo->query("SELECT id FROM admin_deleted_records WHERE target_table = 'staff_profiles' AND target_id = {$staffProfileId} ORDER BY id DESC LIMIT 1")->fetchColumn();
    TestHarness::assert($snapshotId > 0, 'Recycle bin: deleted record is snapshotted for recovery');

    $pdo->prepare("DELETE FROM admin_deleted_records WHERE id = ?")->execute([$snapshotId]);
    TestHarness::assertEqual(0, (int) $pdo->query("SELECT COUNT(*) FROM admin_deleted_records WHERE id = {$snapshotId}")->fetchColumn(), 'Recycle bin: snapshot can be cleared after handling');

    // =====================================================================
    // 4. Console-managed secrets: settings-first, .env fallback
    // =====================================================================
    $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('paystack_secret_key', 'sk_test_GOVCHECK') ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
    TestHarness::assertEqual('sk_test_GOVCHECK', app_secret($pdo, 'paystack_secret_key', 'PAYSTACK_SECRET_KEY'), 'Secrets: console-stored value is preferred');

    $pdo->prepare("DELETE FROM settings WHERE key_name = 'paystack_secret_key'")->execute();
    $envBefore = getenv('PAYSTACK_SECRET_KEY');
    putenv('PAYSTACK_SECRET_KEY=sk_env_GOVCHECK');
    TestHarness::assertEqual('sk_env_GOVCHECK', app_secret($pdo, 'paystack_secret_key', 'PAYSTACK_SECRET_KEY'), 'Secrets: falls back to .env when no console value is set');
    if ($envBefore === false) {
        putenv('PAYSTACK_SECRET_KEY');
    } else {
        putenv('PAYSTACK_SECRET_KEY=' . $envBefore);
    }

    // =====================================================================
    // 5. Module mode kill-switch (paused / setup disables a module)
    // =====================================================================
    $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('module_marketplace_mode', 'paused') ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();
    TestHarness::assertEqual(false, admin_feature_is_globally_enabled($pdo, 'marketplace'), 'Module mode: a paused module is globally disabled');

    $pdo->prepare("UPDATE settings SET value = 'setup' WHERE key_name = 'module_marketplace_mode'")->execute();
    TestHarness::assertEqual(false, admin_feature_is_globally_enabled($pdo, 'marketplace'), 'Module mode: a setup-required module is globally disabled');

    $pdo->prepare("UPDATE settings SET value = 'active' WHERE key_name = 'module_marketplace_mode'")->execute();
    TestHarness::assertEqual(true, admin_feature_is_globally_enabled($pdo, 'marketplace'), 'Module mode: an active module is enabled');

    $pdo->prepare("DELETE FROM settings WHERE key_name = 'module_marketplace_mode'")->execute();

    // =====================================================================
    // 6. Audit logging
    // =====================================================================
    $auditBefore = (int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    admin_audit($pdo, 'gov_test_event', 'Governance test audit event.');
    $auditAfter = (int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    TestHarness::assert($auditAfter > $auditBefore, 'Audit: a privileged action writes an audit_log row');

    // =====================================================================
    // 7. Session control: epoch validation / force sign-out
    // =====================================================================
    $pdo->prepare("UPDATE users SET session_epoch = 0 WHERE id = ?")->execute([$staffUserId]);
    $_SESSION['user_id'] = $staffUserId;
    $_SESSION['session_epoch'] = 0;
    TestHarness::assert(current_user($pdo) !== null, 'Sessions: a valid epoch keeps the session alive');

    $pdo->prepare("UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?")->execute([$staffUserId]);
    TestHarness::assert(current_user($pdo) === null, 'Sessions: force sign-out (epoch bump) invalidates the session');
    unset($_SESSION['user_id'], $_SESSION['session_epoch'], $_SESSION['admin_authenticated'], $_SESSION['admin'], $_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);

    // =====================================================================
    // 8. Soft-delete: farm_verifications read isolation + reactivation
    // =====================================================================
    $pdo->prepare("INSERT INTO grower_farms (user_id, farm_name) VALUES (?, ?)")->execute([$staffUserId, 'gov_farm_' . bin2hex(random_bytes(3))]);
    $govFarmId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO farm_verifications (farm_id, requested_by, status) VALUES (?, ?, 'pending')")->execute([$govFarmId, $staffUserId]);
    $govVerificationId = (int) $pdo->lastInsertId();

    $liveVerification = (int) $pdo->query("SELECT COUNT(*) FROM farm_verifications WHERE id = {$govVerificationId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveVerification, 'Soft-delete: live farm verification is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'farm_verifications',
        'target_id' => $govVerificationId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenVerification = (int) $pdo->query("SELECT COUNT(*) FROM farm_verifications WHERE id = {$govVerificationId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenVerification, 'Soft-delete: approved delete hides the farm verification from the filtered read');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM farm_verifications WHERE id = {$govVerificationId}")->fetchColumn() !== false, 'Soft-delete: approved delete keeps the farm_verifications row and stamps deleted_at');

    $pdo->prepare("UPDATE farm_verifications SET deleted_at = NULL WHERE id = ?")->execute([$govVerificationId]);
    $restoredVerification = (int) $pdo->query("SELECT COUNT(*) FROM farm_verifications WHERE id = {$govVerificationId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredVerification, 'Soft-delete: restored farm verification is visible again');

    // =====================================================================
    // 9. Soft-delete: user_import_records read isolation + restore
    // =====================================================================
    $importBatchRef = 'GOV-' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO user_import_records (batch_ref, source_file, source_row, name, status) VALUES (?, 'gov_import.csv', 1, 'Gov Import', 'pending')")->execute([$importBatchRef]);
    $govImportId = (int) $pdo->lastInsertId();

    $liveImport = (int) $pdo->query("SELECT COUNT(*) FROM user_import_records WHERE id = {$govImportId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveImport, 'Soft-delete: live import record is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'user_import_records',
        'target_id' => $govImportId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenImport = (int) $pdo->query("SELECT COUNT(*) FROM user_import_records WHERE id = {$govImportId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenImport, 'Soft-delete: approved delete hides the import record from the filtered read');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM user_import_records WHERE id = {$govImportId}")->fetchColumn() !== false, 'Soft-delete: approved delete keeps the user_import_records row and stamps deleted_at');

    $pdo->prepare("UPDATE user_import_records SET deleted_at = NULL WHERE id = ?")->execute([$govImportId]);
    $restoredImport = (int) $pdo->query("SELECT COUNT(*) FROM user_import_records WHERE id = {$govImportId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredImport, 'Soft-delete: restored import record is visible again');

    // =====================================================================
    // 10. Soft-delete: document_requirements read isolation + restore
    // =====================================================================
    $docEmail = 'gov_doc_' . bin2hex(random_bytes(3)) . '@example.test';
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role, account_status, created_at) VALUES ('Gov Doc', ?, 'x', 'grower', 'grower', 'active', NOW())")->execute([$docEmail]);
    $govDocUserId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO document_requirements (user_id, document_type, document_number) VALUES (?, 'nin', ?)")->execute([$govDocUserId, 'GOVDOC' . bin2hex(random_bytes(3))]);
    $govDocId = (int) $pdo->lastInsertId();

    $liveDoc = (int) $pdo->query("SELECT COUNT(*) FROM document_requirements WHERE id = {$govDocId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveDoc, 'Soft-delete: live document requirement is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'document_requirements',
        'target_id' => $govDocId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenDoc = (int) $pdo->query("SELECT COUNT(*) FROM document_requirements WHERE id = {$govDocId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenDoc, 'Soft-delete: approved delete hides the document requirement from the filtered read');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM document_requirements WHERE id = {$govDocId}")->fetchColumn() !== false, 'Soft-delete: approved delete keeps the document_requirements row and stamps deleted_at');

    $pdo->prepare("UPDATE document_requirements SET deleted_at = NULL WHERE id = ?")->execute([$govDocId]);
    $restoredDoc = (int) $pdo->query("SELECT COUNT(*) FROM document_requirements WHERE id = {$govDocId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredDoc, 'Soft-delete: restored document requirement is visible again');

    // =====================================================================
    // 11. Soft-delete: grower_farms read isolation + restore
    // =====================================================================
    $pdo->prepare("INSERT INTO grower_farms (user_id, farm_name) VALUES (?, ?)")->execute([$staffUserId, 'gov_soft_farm_' . bin2hex(random_bytes(3))]);
    $govSoftFarmId = (int) $pdo->lastInsertId();

    $liveFarm = (int) $pdo->query("SELECT COUNT(*) FROM grower_farms WHERE id = {$govSoftFarmId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveFarm, 'Soft-delete: live grower farm is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the grower and their related records are never dropped.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'grower_farms',
        'target_id' => $govSoftFarmId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenFarm = (int) $pdo->query("SELECT COUNT(*) FROM grower_farms WHERE id = {$govSoftFarmId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenFarm, 'Soft-delete: approved delete hides the grower farm from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM grower_farms WHERE id = {$govSoftFarmId}")->fetchColumn(), 'Soft-delete: approved delete keeps the grower_farms row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM grower_farms WHERE id = {$govSoftFarmId}")->fetchColumn() !== false, 'Soft-delete: approved delete stamps deleted_at on grower_farms');

    $pdo->prepare("UPDATE grower_farms SET deleted_at = NULL WHERE id = ?")->execute([$govSoftFarmId]);
    $restoredFarm = (int) $pdo->query("SELECT COUNT(*) FROM grower_farms WHERE id = {$govSoftFarmId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredFarm, 'Soft-delete: restored grower farm is visible again');

    // =====================================================================
    // 12. Soft-delete: provider_registry read isolation + restore
    // =====================================================================
    pg_ensure_schema($pdo);
    $govCompany = 'gov_provider_' . bin2hex(random_bytes(3));
    $pdo->prepare("INSERT INTO provider_registry (company_name, provider_type, status) VALUES (?, 'service', 'pending_review')")->execute([$govCompany]);
    $govProviderId = (int) $pdo->lastInsertId();

    $liveProvider = (int) $pdo->query("SELECT COUNT(*) FROM provider_registry WHERE id = {$govProviderId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveProvider, 'Soft-delete: live provider is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the payment and certificate rows that reference the provider survive.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'provider_registry',
        'target_id' => $govProviderId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenProvider = (int) $pdo->query("SELECT COUNT(*) FROM provider_registry WHERE id = {$govProviderId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenProvider, 'Soft-delete: approved delete hides the provider from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM provider_registry WHERE id = {$govProviderId}")->fetchColumn(), 'Soft-delete: approved delete keeps the provider_registry row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM provider_registry WHERE id = {$govProviderId}")->fetchColumn() !== false, 'Soft-delete: approved delete stamps deleted_at on provider_registry');

    $pdo->prepare("UPDATE provider_registry SET deleted_at = NULL WHERE id = ?")->execute([$govProviderId]);
    $restoredProvider = (int) $pdo->query("SELECT COUNT(*) FROM provider_registry WHERE id = {$govProviderId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredProvider, 'Soft-delete: restored provider is visible again');

    // =====================================================================
    // 13. Soft-delete: marketplace_sellers read isolation + restore
    // =====================================================================
    marketplace_ensure_schema($pdo);
    $govSellerEmail = 'gov_seller_' . bin2hex(random_bytes(3)) . '@example.test';
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role, account_status, created_at) VALUES ('Gov Seller', ?, 'x', 'grower', 'seller', 'active', NOW())")->execute([$govSellerEmail]);
    $govSellerUserId = (int) $pdo->lastInsertId();
    $govSellerSlug = 'gov-seller-' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO marketplace_sellers (user_id, seller_type, store_name, slug, approval_status, verification_status) VALUES (?, 'grower', ?, ?, 'approved', 'verified')")
        ->execute([$govSellerUserId, 'Gov Seller Store', $govSellerSlug]);
    $govSellerId = (int) $pdo->lastInsertId();

    $liveSeller = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE id = {$govSellerId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveSeller, 'Soft-delete: live marketplace seller is visible to the filtered read');
    TestHarness::assert(marketplace_current_seller($pdo, $govSellerUserId) !== null, 'Soft-delete: live seller resolves for its owner');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the listings, orders and payouts that reference the seller survive.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'marketplace_sellers',
        'target_id' => $govSellerId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenSeller = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE id = {$govSellerId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenSeller, 'Soft-delete: approved delete hides the marketplace seller from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE id = {$govSellerId}")->fetchColumn(), 'Soft-delete: approved delete keeps the marketplace_sellers row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM marketplace_sellers WHERE id = {$govSellerId}")->fetchColumn() !== false, 'Soft-delete: approved delete stamps deleted_at on marketplace_sellers');
    TestHarness::assert(marketplace_current_seller($pdo, $govSellerUserId) === null, 'Soft-delete: deleted seller loses seller-central context');

    $pdo->prepare("UPDATE marketplace_sellers SET deleted_at = NULL WHERE id = ?")->execute([$govSellerId]);
    $restoredSeller = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE id = {$govSellerId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredSeller, 'Soft-delete: restored marketplace seller is visible again');

    // =====================================================================
    // 14. Soft-delete: certificates read isolation + restore
    // =====================================================================
    app_ensure_certificate_schema($pdo);
    $govCertAppRef = 'GOVCERT-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("INSERT INTO applications (app_ref, name, location, farm_size, phone, email, commitments, confirmed) VALUES (?, 'Gov Cert Grower', 'Lagos', 4.5, ?, ?, 'confirmed', 1)")
        ->execute([$govCertAppRef, '234' . random_int(1000000000, 9999999999), strtolower($govCertAppRef) . '@example.test']);
    $govCertAppId = (int) $pdo->lastInsertId();
    $govCertRef = 'CERT-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("INSERT INTO certificates (certificate_ref, application_id, user_id, certificate_path, status, qr_code_hash) VALUES (?, ?, ?, ?, 'issued', ?)")
        ->execute([$govCertRef, $govCertAppId, $staffUserId, 'certificates/gov-' . strtolower($govCertRef) . '.html', $govCertRef]);
    $govCertId = (int) $pdo->lastInsertId();

    $liveCert = (int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE id = {$govCertId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveCert, 'Soft-delete: live certificate is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the application, payments, and reports that reference it survive.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'certificates',
        'target_id' => $govCertId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenCert = (int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE id = {$govCertId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenCert, 'Soft-delete: approved delete hides the certificate from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE id = {$govCertId}")->fetchColumn(), 'Soft-delete: approved delete keeps the certificates row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM certificates WHERE id = {$govCertId}")->fetchColumn() !== false, 'Soft-delete: approved delete stamps deleted_at on certificates');

    $pdo->prepare("UPDATE certificates SET deleted_at = NULL WHERE id = ?")->execute([$govCertId]);
    $restoredCert = (int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE id = {$govCertId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredCert, 'Soft-delete: restored certificate is visible again');

    // =====================================================================
    // 15. Soft-delete: academy_certificates read isolation + restore
    // =====================================================================
    academy_ensure_schema($pdo);
    $pdo->prepare("INSERT INTO webinars (title, start_time) VALUES (?, NOW())")->execute(['Gov Academy ' . bin2hex(random_bytes(3))]);
    $govWebinarId = (int) $pdo->lastInsertId();
    $govAcadRef = 'NAT-ACAD-GOV-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("INSERT INTO academy_certificates (user_id, webinar_id, certificate_ref, status, issued_at) VALUES (?, ?, ?, 'issued', NOW())")
        ->execute([$staffUserId, $govWebinarId, $govAcadRef]);
    $govAcadId = (int) $pdo->lastInsertId();

    $liveAcad = (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE id = {$govAcadId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveAcad, 'Soft-delete: live academy certificate is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the registration and Academy history that reference it survive.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'academy_certificates',
        'target_id' => $govAcadId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenAcad = (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE id = {$govAcadId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenAcad, 'Soft-delete: approved delete hides the academy certificate from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE id = {$govAcadId}")->fetchColumn(), 'Soft-delete: approved delete keeps the academy_certificates row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM academy_certificates WHERE id = {$govAcadId}")->fetchColumn() !== false, 'Soft-delete: approved delete stamps deleted_at on academy_certificates');

    $pdo->prepare("UPDATE academy_certificates SET deleted_at = NULL WHERE id = ?")->execute([$govAcadId]);
    $restoredAcad = (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE id = {$govAcadId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredAcad, 'Soft-delete: restored academy certificate is visible again');

    // =====================================================================
    // 16. Soft-delete: marketplace_listings read isolation + restore
    // =====================================================================
    marketplace_ensure_schema($pdo);
    $govListingSlug = 'gov-listing-' . bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO marketplace_listings (seller_id, title, slug, approval_status, availability_status) VALUES (?, ?, ?, 'approved', 'available')")
        ->execute([$govSellerId, 'Gov Marketplace Listing', $govListingSlug]);
    $govListingId = (int) $pdo->lastInsertId();

    $liveListing = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE id = {$govListingId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $liveListing, 'Soft-delete: live marketplace listing is visible to the filtered read');

    // The approved-delete path must soft-delete (set deleted_at), never remove the row,
    // so the orders, inquiries, promotions and reports that reference the listing survive.
    admin_execute_approved_delete($pdo, [
        'id' => 0,
        'target_table' => 'marketplace_listings',
        'target_id' => $govListingId,
        'target_key' => null,
        'payload_json' => null,
    ]);
    $hiddenListing = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE id = {$govListingId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(0, $hiddenListing, 'Soft-delete: approved delete hides the listing from the filtered read');
    TestHarness::assertEqual(1, (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE id = {$govListingId}")->fetchColumn(), 'Soft-delete: approved delete keeps the marketplace_listings row');
    TestHarness::assert($pdo->query("SELECT deleted_at FROM marketplace_listings WHERE id = {$govListingId}")->fetchColumn() !== null, 'Soft-delete: approved delete stamps deleted_at on marketplace_listings');

    $pdo->prepare("UPDATE marketplace_listings SET deleted_at = NULL WHERE id = ?")->execute([$govListingId]);
    $restoredListing = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE id = {$govListingId} AND deleted_at IS NULL")->fetchColumn();
    TestHarness::assertEqual(1, $restoredListing, 'Soft-delete: restored marketplace listing is visible again');
}
