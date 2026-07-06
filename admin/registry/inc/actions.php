<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../../lib/notification-dispatch.php';
require_once __DIR__ . '/../../../lib/admin-user-import.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $redirectPage = (string) ($_POST['page'] ?? 'index.php');

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        header("Location: {$redirectPage}?error=" . urlencode('Invalid or expired security token. Refresh the page and try again.'));
        exit;
    }

    try {
        if ($action === 'review_application') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'under_review');
            if (!in_array($status, ['under_review', 'approved', 'rejected'], true)) {
                throw new RuntimeException('Invalid review status.');
            }
            
            if ($status === 'approved') {
                $stmt = $pdo->prepare("SELECT id, app_ref, name, email, phone FROM applications WHERE id = ? LIMIT 1");
                $stmt->execute([$applicationId]);
                $app = $stmt->fetch();
                if ($app) {
                    $temporaryPassword = bin2hex(random_bytes(4));
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = NOW(), review_status = 'active' WHERE id = ?")->execute([$applicationId]);
                    $pdo->prepare("INSERT INTO users (email, password, application_id, name, phone, role) VALUES (?, ?, ?, ?, ?, 'grower') ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), name = VALUES(name), phone = VALUES(phone)")
                        ->execute([$app['email'], password_hash($temporaryPassword, PASSWORD_DEFAULT), $applicationId, $app['name'], $app['phone']]);
                    $pdo->commit();
                    
                    $loginUrl = app_base_url() . '/login.php';
                    app_send_mail($app['email'], 'Your NATCODEV Dashboard Access', "Dear {$app['name']},\n\nYour application has been confirmed.\nDashboard: {$loginUrl}\nTemp Password: {$temporaryPassword}");
                }
            } else {
                $stmt = $pdo->prepare('UPDATE applications SET confirmed = 0, review_status = ? WHERE id = ?');
                $stmt->execute([$status === 'rejected' ? 'archived_no_response' : 'active', $applicationId]);
            }
            header("Location: {$redirectPage}?message=Application+updated");
            exit;
        }

        if ($action === 'resend_confirmation') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, app_ref, name, email, confirmation_token FROM applications WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if ($app) {
                $token = (string) ($app['confirmation_token'] ?: bin2hex(random_bytes(32)));
                if (!$app['confirmation_token']) $pdo->prepare("UPDATE applications SET confirmation_token = ? WHERE id = ?")->execute([$token, $id]);
                
                $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
                app_send_mail($app['email'], 'Confirm Your NATCODEV Application', "Please confirm your application: {$confirmUrl}");
                $pdo->prepare("UPDATE applications SET email_sent = 1 WHERE id = ?")->execute([$id]);
            }
            header("Location: {$redirectPage}?message=Confirmation+resent");
            exit;
        }

        if ($action === 'request_delete') {
            $id = (int) ($_POST['application_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? 'Admin request'));
            $pdo->prepare("INSERT INTO application_delete_requests (application_id, requested_by, reason) VALUES (?, ?, ?)")
                ->execute([$id, $registryUser['id'], $reason]);
            header("Location: {$redirectPage}?message=Delete+request+sent");
            exit;
        }

        if ($action === 'approve_delete' && admin_current_user_is_super_admin($pdo)) {
            $requestId = (int) ($_POST['request_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT application_id FROM application_delete_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $appId = (int) $stmt->fetchColumn();
            if ($appId > 0) {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE users SET application_id = NULL WHERE application_id = ?")->execute([$appId]);
                $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$appId]);
                $pdo->prepare("UPDATE application_delete_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([$registryUser['id'], $requestId]);
                $pdo->commit();
            }
            header("Location: {$redirectPage}?message=Application+deleted");
            exit;
        }

        if ($action === 'verify_document') {
            $documentId = (int) ($_POST['document_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'verified');
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if (!in_array($status, ['verified', 'rejected', 'pending'], true)) {
                throw new RuntimeException('Invalid document status.');
            }
            $stmt = $pdo->prepare('UPDATE document_requirements SET verification_status = ?, verified = ?, verification_notes = ?, verified_by = ?, verified_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $status === 'verified' ? 1 : 0, $notes !== '' ? $notes : null, (int) ($registryUser['id'] ?? 0), $documentId]);
            header("Location: {$redirectPage}?message=Document+review+saved");
            exit;
        }

        if ($action === 'bulk_verify_documents') {
            $documentIds = array_values(array_filter(array_map('intval', (array) ($_POST['doc_ids'] ?? []))));
            $status = (string) ($_POST['bulk_status'] ?? 'verified');
            if (!$documentIds || !in_array($status, ['verified', 'rejected'], true)) {
                throw new RuntimeException('Select at least one document and a valid decision.');
            }
            $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
            $params = array_merge([$status, $status === 'verified' ? 1 : 0, (int) ($registryUser['id'] ?? 0)], $documentIds);
            $stmt = $pdo->prepare("UPDATE document_requirements SET verification_status = ?, verified = ?, verified_by = ?, verified_at = NOW() WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            header("Location: {$redirectPage}?message=" . urlencode($stmt->rowCount() . ' document(s) updated.'));
            exit;
        }

        if ($action === 'batch_verify_certificates') {
            $refs = preg_split('/\R+/', trim((string) ($_POST['refs'] ?? ''))) ?: [];
            $refs = array_slice(array_values(array_unique(array_filter(array_map('trim', $refs)))), 0, 200);
            if (!$refs) {
                throw new RuntimeException('Enter at least one certificate reference.');
            }
            $valid = 0;
            $invalid = 0;
            $stmt = $pdo->prepare("SELECT id, status FROM certificates WHERE certificate_ref = ? OR qr_code_hash = ? LIMIT 1");
            $mark = $pdo->prepare('UPDATE certificates SET verified_at = NOW() WHERE id = ?');
            foreach ($refs as $ref) {
                $stmt->execute([$ref, $ref]);
                $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($certificate && ($certificate['status'] ?? '') === 'issued') {
                    $mark->execute([(int) $certificate['id']]);
                    $valid++;
                } else {
                    $invalid++;
                }
            }
            header("Location: {$redirectPage}?message=" . urlencode("Batch verified: {$valid} valid, {$invalid} invalid or unavailable."));
            exit;
        }

        if ($action === 'revoke_certificate') {
            $certificateId = (int) ($_POST['certificate_id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($certificateId <= 0 || $reason === '') {
                throw new RuntimeException('Certificate and revocation reason are required.');
            }
            $certStmt = $pdo->prepare("SELECT id, certificate_ref, status FROM certificates WHERE id = ? LIMIT 1");
            $certStmt->execute([$certificateId]);
            $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
            if (!$certificate || (string) ($certificate['status'] ?? '') !== 'issued') {
                throw new RuntimeException('Certificate was not found or is already inactive.');
            }

            if (!admin_current_user_is_super_admin($pdo)) {
                admin_ensure_action_request_schema($pdo);
                $pending = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = 'revoke_certificate' AND target_table = 'certificates' AND target_id = ? AND status = 'pending' LIMIT 1");
                $pending->execute([$certificateId]);
                if (!$pending->fetchColumn()) {
                    $request = $pdo->prepare("
                        INSERT INTO admin_action_requests
                            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
                        VALUES ('revoke_certificate', 'certificates', ?, ?, ?, ?, ?, ?)
                    ");
                    $request->execute([
                        $certificateId,
                        (string) ($certificate['certificate_ref'] ?? ''),
                        'Certificate ' . (string) ($certificate['certificate_ref'] ?? ('#' . $certificateId)),
                        admin_current_user_id($pdo),
                        $reason,
                        json_encode(['reason' => $reason, 'certificate_ref' => (string) ($certificate['certificate_ref'] ?? '')], JSON_UNESCAPED_SLASHES),
                    ]);
                }
                header("Location: {$redirectPage}?message=" . urlencode('Certificate revocation sent for Super Admin approval.'));
                exit;
            }

            $stmt = $pdo->prepare("UPDATE certificates SET status = 'revoked', revoked_at = NOW(), revoked_reason = ? WHERE id = ? AND status = 'issued'");
            $stmt->execute([$reason, $certificateId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Certificate was not found or is already inactive.');
            }
            header("Location: {$redirectPage}?message=Certificate+revoked");
            exit;
        }

        if ($action === 'restore_certificate') {
            $certificateId = (int) ($_POST['certificate_id'] ?? 0);
            if ($certificateId <= 0) {
                throw new RuntimeException('Certificate is required.');
            }
            $certStmt = $pdo->prepare("SELECT id, certificate_ref, status FROM certificates WHERE id = ? LIMIT 1");
            $certStmt->execute([$certificateId]);
            $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
            if (!$certificate || (string) ($certificate['status'] ?? '') !== 'revoked') {
                throw new RuntimeException('Certificate was not found or is not revoked.');
            }

            if (!admin_current_user_is_super_admin($pdo)) {
                admin_ensure_action_request_schema($pdo);
                $pending = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = 'restore_certificate' AND target_table = 'certificates' AND target_id = ? AND status = 'pending' LIMIT 1");
                $pending->execute([$certificateId]);
                if (!$pending->fetchColumn()) {
                    $request = $pdo->prepare("
                        INSERT INTO admin_action_requests
                            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
                        VALUES ('restore_certificate', 'certificates', ?, ?, ?, ?, ?, ?)
                    ");
                    $request->execute([
                        $certificateId,
                        (string) ($certificate['certificate_ref'] ?? ''),
                        'Certificate ' . (string) ($certificate['certificate_ref'] ?? ('#' . $certificateId)),
                        admin_current_user_id($pdo),
                        'Admin mistakenly revoked',
                        json_encode(['reason' => 'Restore mistakenly revoked certificate', 'certificate_ref' => (string) ($certificate['certificate_ref'] ?? '')], JSON_UNESCAPED_SLASHES),
                    ]);
                }
                header("Location: {$redirectPage}?message=" . urlencode('Certificate restoration sent for Super Admin approval.'));
                exit;
            }

            $stmt = $pdo->prepare("UPDATE certificates SET status = 'issued', revoked_at = NULL, revoked_reason = NULL WHERE id = ? AND status = 'revoked'");
            $stmt->execute([$certificateId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Certificate was not found or is not revoked.');
            }
            header("Location: {$redirectPage}?message=Certificate+restored");
            exit;
        }

        if ($action === 'issue_certificate') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $readiness = grower_certificate_readiness($userId, $pdo);
            $applicationId = (int) ($readiness['application_id'] ?? 0);
            if ($userId <= 0 || $applicationId <= 0) {
                throw new RuntimeException('Select a registered grower with an application.');
            }
            if (empty($readiness['ready'])) {
                $pending = array_map(
                    static fn(array $check): string => (string) $check['label'],
                    array_filter($readiness['checks'], static fn(array $check): bool => !$check['passed'])
                );
                throw new RuntimeException('Certificate blocked. Complete: ' . implode(', ', $pending) . '.');
            }
            generateCertificate($applicationId, $userId, $pdo);
            header("Location: {$redirectPage}?message=Certificate+issued");
            exit;
        }

        if ($action === 'send_activation') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            if ($applicationId <= 0) {
                throw new RuntimeException('No application is linked to this grower.');
            }
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE applications SET confirmation_token = ? WHERE id = ?')->execute([$token, $applicationId]);
            $sent = admin_import_send_confirmation($pdo, $applicationId, true, true, true);
            if (app_table_exists($pdo, 'user_import_records')) {
                $recordStmt = $pdo->prepare('SELECT * FROM user_import_records WHERE application_id = ? ORDER BY id DESC LIMIT 1');
                $recordStmt->execute([$applicationId]);
                $record = $recordStmt->fetch(PDO::FETCH_ASSOC);
                if ($record) {
                    $engagementToken = (string) ($record['engagement_token'] ?: $token);
                    $pdo->prepare("UPDATE user_import_records SET engagement_token = ?, engagement_deadline = DATE_ADD(NOW(), INTERVAL 14 DAY), status = CASE WHEN status = 'engagement_confirmed' THEN status ELSE 'pending_engagement' END, status_note = 'Activation reminder sent from Registry.' WHERE id = ?")
                        ->execute([$engagementToken, (int) $record['id']]);
                    $record['engagement_token'] = $engagementToken;
                    $sent = admin_import_send_phone_engagement($record, $engagementToken) || $sent;
                }
            }
            if (!$sent) {
                throw new RuntimeException('Activation could not be delivered. Check the grower email/phone and messaging configuration.');
            }
            header("Location: {$redirectPage}?message=Activation+sent");
            exit;
        }

        if ($action === 'send_import_activation') {
            $recordId = (int) ($_POST['import_record_id'] ?? 0);
            admin_ensure_import_schema($pdo);
            $stmt = $pdo->prepare('SELECT * FROM user_import_records WHERE id = ? LIMIT 1');
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                throw new RuntimeException('Imported grower record was not found.');
            }
            $token = (string) ($record['engagement_token'] ?: bin2hex(random_bytes(32)));
            $applicationId = (int) ($record['application_id'] ?? 0);
            if ($applicationId <= 0 && filter_var((string) ($record['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $applicationId = (int) (admin_import_insert_application($pdo, $record, $token) ?: 0);
            }
            $sent = $applicationId > 0 && admin_import_send_confirmation($pdo, $applicationId, true, true, true);
            $record['engagement_token'] = $token;
            $sent = admin_import_send_phone_engagement($record, $token) || $sent;
            $pdo->prepare("UPDATE user_import_records SET application_id = NULLIF(?, 0), engagement_token = ?, engagement_deadline = DATE_ADD(NOW(), INTERVAL 14 DAY), status = 'pending_engagement', status_note = 'Activation resent from Registry.' WHERE id = ?")
                ->execute([$applicationId, $token, $recordId]);
            if (!$sent) {
                throw new RuntimeException('Activation could not be delivered. Add a valid email or phone and verify messaging configuration.');
            }
            header("Location: {$redirectPage}?message=Imported+grower+activation+sent");
            exit;
        }

        if ($action === 'send_onboarding_reminder') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $grower = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$grower || !filter_var((string) $grower['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid grower email is required for an onboarding reminder.');
            }
            $onboardingUrl = app_base_url() . '/dashboard/onboarding.php';
            $sent = app_send_mail((string) $grower['email'], 'Complete Your NATCODEV Onboarding', "Dear {$grower['name']},\n\nSome account verification steps are still pending. Continue your profile, farm, phone, and document onboarding here:\n{$onboardingUrl}\n\nCertificates are issued only after all authenticity checks pass.");
            if (!$sent) {
                throw new RuntimeException('Onboarding reminder could not be delivered.');
            }
            header("Location: {$redirectPage}?message=Onboarding+reminder+sent");
            exit;
        }

        if ($action === 'send_password_reset') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            app_add_column_if_missing($pdo, 'users', 'password_reset_token', 'VARCHAR(64) NULL');
            app_add_column_if_missing($pdo, 'users', 'password_reset_expires', 'DATETIME NULL');
            $stmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $grower = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$grower || !filter_var((string) $grower['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid grower email is required for password reset.');
            }
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?')->execute([$token, $expires, $userId]);
            $resetUrl = app_base_url() . '/dashboard/reset-password.php?token=' . urlencode($token);
            if (!app_send_mail((string) $grower['email'], 'NATCODEV Password Reset', "Dear {$grower['name']},\n\nReset your dashboard password within one hour:\n{$resetUrl}\n\nIgnore this message if you did not request it.")) {
                throw new RuntimeException('Password reset email could not be delivered.');
            }
            header("Location: {$redirectPage}?message=Password+reset+sent");
            exit;
        }

        if ($action === 'create_grower') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'Individual');
            $stateName = (string) ($_POST['state'] ?? '');
            
            if ($name === '' || $email === '') {
                throw new RuntimeException('Name and email are required.');
            }
            
            $appRef = rx_ref('APP');
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare("INSERT INTO applications (app_ref, name, phone, email, commitments, confirmed, confirmation_token, email_sent, submission_source, location, farm_size) VALUES (?, ?, ?, ?, ?, 0, ?, 0, 'admin_registry', ?, 0)");
            $stmt->execute([$appRef, $name, $phone, $email, $type, $token, $stateName !== '' ? $stateName : 'Pending grower onboarding']);
            $applicationId = (int) $pdo->lastInsertId();
            $sent = admin_import_send_confirmation($pdo, $applicationId, true, $phone !== '', $phone !== '');
            header("Location: {$redirectPage}?message=" . urlencode($sent ? 'Grower saved pending activation; confirmation sent.' : 'Grower saved pending activation; delivery needs attention.'));
            exit;
        }

        if ($action === 'deploy_agent') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $location = trim((string) ($_POST['location'] ?? ''));
            
            if ($name === '' || $email === '') {
                throw new RuntimeException('Name and email are required.');
            }
            
            // Check if email already exists
            $existingUser = rx_scalar($pdo, "SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                 throw new RuntimeException('A user with this email already exists.');
            }

            $tempPassword = bin2hex(random_bytes(6));
            $password = password_hash($tempPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, location) VALUES (?, ?, ?, 'field_agent', ?)");
            $stmt->execute([$name, $email, $password, $location]);
            
            admin_notify_new_user($email, $name, $tempPassword, 'Field Agent');
            
            header("Location: {$redirectPage}?message=Field+Agent+deployed+and+notified");
            exit;
        }

        // Add more actions here (Phase 3 & 4)

    } catch (Throwable $e) {
        header("Location: {$redirectPage}?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    http_response_code(405);
    echo "Method Not Allowed. This endpoint only accepts POST requests.";
    exit;
}
