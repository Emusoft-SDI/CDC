<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $redirectPage = (string) ($_POST['page'] ?? 'index.php');

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
            if (!in_array($status, ['verified', 'rejected', 'pending'], true)) {
                throw new RuntimeException('Invalid document status.');
            }
            $stmt = $pdo->prepare('UPDATE document_requirements SET verification_status = ?, verified = ?, verified_by = ?, verified_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $status === 'verified' ? 1 : 0, (int) ($registryUser['id'] ?? 0), $documentId]);
            header("Location: {$redirectPage}?message=Document+review+saved");
            exit;
        }

if ($action === 'issue_certificate')
 {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $applicationId = rx_scalar($pdo, 'SELECT application_id FROM users WHERE id = ?', [$userId]);
    if ($userId <= 0 || $applicationId <= 0) {
        throw new RuntimeException('Select a registered grower with an application.');
    }
    // Ensure grower is confirmed before issuing certificate
    $stmt = $pdo->prepare('SELECT confirmed FROM applications WHERE id = ?');
    $stmt->execute([$applicationId]);
    $confirmed = (int) $stmt->fetchColumn();
    if ($confirmed !== 1) {
        header("Location: {$redirectPage}?error=not_confirmed");
        exit;
    }
    generateCertificate($applicationId, $userId, $pdo);
    header("Location: {$redirectPage}?message=Certificate+issued");
    exit;
}


        // Admin manual confirmation of pending grower
        if ($action === 'admin_confirm_grower') {
            $applicationId = (int) ($_POST['application_id'] ?? 0);
            if ($applicationId > 0) {
                $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?")->execute([$applicationId]);
                // Notify the grower via email
                $stmt = $pdo->prepare("SELECT email, name FROM applications WHERE id = ? LIMIT 1");
                $stmt->execute([$applicationId]);
                $appInfo = $stmt->fetch();
                if ($appInfo && $appInfo['email']) {
                    $loginUrl = app_base_url() . '/login.php';
                    $subject = 'Your NATCODEV Grower Account Confirmed';
                    $message = "Dear {$appInfo['name']},\n\nYour grower account has been confirmed by an administrator. You may now log in to the dashboard using the link below and your existing credentials.\n\nLogin: {$loginUrl}\n\nThank you for joining NATCODEV!";
                    app_send_mail($appInfo['email'], $subject, $message);
                }
            }
            header("Location: {$redirectPage}?message=Grower+confirmed+by+admin");
            exit;
        }


        if ($action === 'create_grower') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'Individual');
            $stateName = (string) ($_POST['state'] ?? '');
            $farmSize = max(0.0, (float) ($_POST['farm_size'] ?? 0.0));
            
            if ($name === '' || $email === '') {
                throw new RuntimeException('Name and email are required.');
            }
            
            try {
                $pdo->beginTransaction();
                $appRef = rx_ref('APP');
                $stmt = $pdo->prepare("INSERT INTO applications (app_ref, name, phone, email, commitments, confirmed, submission_source, location, farm_size) VALUES (?, ?, ?, ?, ?, 1, 'admin_registry', ?, ?)");
                $stmt->execute([$appRef, $name, $phone, $email, $type, $stateName, $farmSize]);
                $applicationId = (int) $pdo->lastInsertId();
                
                $tempPassword = bin2hex(random_bytes(8));
                $password = password_hash($tempPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password, application_id, name, role, phone, location) VALUES (?, ?, ?, ?, 'grower', ?, ?)");
                $stmt->execute([$email, $password, $applicationId, $name, $phone, $stateName]);
                
                $pdo->commit();
                admin_notify_new_user($email, $name, $tempPassword, 'Grower');
                
                header("Location: {$redirectPage}?message=Grower+registered+and+notified");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->errorInfo && $e->errorInfo[1] === 1062) {
                    $stmt = $pdo->prepare("SELECT id, confirmed FROM applications WHERE email = ? OR phone = ? LIMIT 1");
                    $stmt->execute([$email, $phone]);
                    $existingApp = $stmt->fetch();
                    
                    if (!$existingApp) {
                        $stmt = $pdo->prepare("SELECT application_id FROM users WHERE email = ? OR phone = ? LIMIT 1");
                        $stmt->execute([$email, $phone]);
                        $userIdx = $stmt->fetch();
                        if ($userIdx && $userIdx['application_id']) {
                            $stmt = $pdo->prepare("SELECT id, confirmed FROM applications WHERE id = ? LIMIT 1");
                            $stmt->execute([$userIdx['application_id']]);
                            $existingApp = $stmt->fetch();
                        }
                    }
                    
                    if ($existingApp) {
                        if ((int) $existingApp['confirmed'] === 0) {
                            header("Location: {$redirectPage}?error_code=duplicate_unconfirmed&app_id=" . $existingApp['id']);
                        } else {
                            header("Location: {$redirectPage}?error_code=duplicate_confirmed&app_id=" . $existingApp['id']);
                        }
                        exit;
                    } else {
                        header("Location: {$redirectPage}?error_code=duplicate_confirmed&app_id=" . $existingApp['id']);
                        exit;
                    }
                }
                throw $e;
            }
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

        // New actions for viewing and editing a grower from duplicate banner
        if ($action === 'view_grower') {
            $appId = (int) ($_POST['application_id'] ?? 0);
            header("Location: view_grower.php?app_id={$appId}");
            exit;
        }
        if ($action === 'edit_grower') {
            $appId = (int) ($_POST['application_id'] ?? 0);
            header("Location: edit_grower.php?app_id={$appId}");
            exit;
        }
        // End of new actions here (Phase 3 & 4)

        // Update grower details (edit page)
        if ($action === 'update_grower') {
            $appId = (int) ($_POST['application_id'] ?? 0);
            if ($appId <= 0) {
                throw new RuntimeException('Invalid application ID.');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $type = (string) ($_POST['type'] ?? 'Individual');
            $stateName = (string) ($_POST['state'] ?? '');
            $farmSize = max(0.0, (float) ($_POST['farm_size'] ?? 0.0));
            if ($name === '' || $email === '') {
                throw new RuntimeException('Name and email are required.');
            }
            try {
                $pdo->beginTransaction();
                // Update applications table
                $stmt = $pdo->prepare("UPDATE applications SET name = ?, phone = ?, email = ?, commitments = ?, location = ?, farm_size = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $type, $stateName, $farmSize, $appId]);
                // Update linked user if exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE application_id = ? LIMIT 1");
                $stmt->execute([$appId]);
                $userId = $stmt->fetchColumn();
                if ($userId) {
                    $stmt = $pdo->prepare("UPDATE users SET email = ?, name = ?, phone = ?, location = ? WHERE id = ?");
                    $stmt->execute([$email, $name, $phone, $stateName, $userId]);
                }
                $pdo->commit();
                header("Location: {$redirectPage}?message=Grower+updated");
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }


    } catch (Throwable $e) {
        header("Location: {$redirectPage}?error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    http_response_code(405);
    echo "Method Not Allowed. This endpoint only accepts POST requests.";
    exit;
}
