<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../provider/_provider.php';
require_once __DIR__ . '/../lib/admin-approvals.php';
if (is_file(__DIR__ . '/../lib/admin-audit-v2.php')) {
    require_once __DIR__ . '/../lib/admin-audit-v2.php';
}

$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    // CLI fallback: allow running from CLI as admin if PHP runs without session
    if (PHP_SAPI !== 'cli') {
        app_render_error_page(403, 'Sign in to manage certificates.');
    }
}


if (PHP_SAPI !== 'cli') { admin_require($pdo, 'certificates'); }
$ref = trim((string) ($_GET['ref'] ?? $argv[1] ?? ''));
$action = strtoupper(trim((string) ($_POST['action'] ?? $argv[2] ?? '')));
$reason = trim((string) ($_POST['reason'] ?? $argv[3] ?? ''));

if ($ref === '') {
    if (PHP_SAPI === 'cli') {
        echo "Usage: php admin/manage_certificate.php <ref> <action> [reason]\nActions: REVOKE, SUSPEND, REINSTATE\n";
        exit(1);
    }
    echo "<p>Missing certificate reference.</p>";
    exit;
}

$certificate = provider_accreditation_certificate_by_ref($pdo, $ref);
// load provider row for contact/email
$providerRow = $pdo->prepare('SELECT * FROM provider_registry WHERE id = ?' . (app_column_exists($pdo, 'provider_registry', 'deleted_at') ? ' AND deleted_at IS NULL' : '') . ' LIMIT 1');
$providerRow->execute([(int) ($certificate['provider_id'] ?? 0)]);
$providerRow = $providerRow->fetch() ?: null;
if (!$certificate) {
    if (PHP_SAPI === 'cli') {
        echo "Certificate not found: {$ref}\n";
        exit(1);
    }
    echo "<p>Certificate not found.</p>";
    exit;
}

if ($action !== '') {
    if (PHP_SAPI !== 'cli' && !verify_csrf($_POST['_csrf'] ?? null)) {
        app_render_error_page(403, 'Invalid security token.');
    }
    try {
        $isSuperAdmin = admin_current_user_is_super_admin($pdo);
        $providerId = (int) ($providerRow['id'] ?? $certificate['provider_id'] ?? 0);

        if ($action === 'REVOKE') {
            $requestType = 'revoke_provider_certification';
            $auditAction = 'certificate_revoked';
            $newStatus = 'revoked';
            if ($isSuperAdmin) {
                $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NOW(), revoked_reason = ? WHERE certificate_ref = ?');
                $stmt->execute(['revoked', $reason, $ref]);
            }
            $msg = "Certificate revoked.";
        } elseif ($action === 'SUSPEND' || $action === 'HOLD') {
            $requestType = 'suspend_provider_certification';
            $auditAction = 'certificate_suspended';
            $newStatus = 'suspended';
            if ($isSuperAdmin) {
                $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NULL, revoked_reason = ? WHERE certificate_ref = ?');
                $stmt->execute(['suspended', $reason, $ref]);
            }
            $msg = "Certificate suspended/held.";
        } elseif ($action === 'REINSTATE' || $action === 'RESTORE') {
            $requestType = 'reinstate_provider_certification';
            $auditAction = 'certificate_reinstated';
            $newStatus = 'issued';
            if ($isSuperAdmin) {
                $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NULL, revoked_reason = NULL WHERE certificate_ref = ?');
                $stmt->execute(['issued', $ref]);
            }
            $msg = "Certificate reinstated to issued.";
        } else {
            throw new RuntimeException('Unknown action');
        }

        if (!$isSuperAdmin) {
            // Non-super-admins cannot change provider certification directly: queue for Super Admin approval.
            admin_ensure_action_request_schema($pdo);
            $pending = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = ? AND target_table = 'provider_registry' AND target_id = ? AND status = 'pending' LIMIT 1");
            $pending->execute([$requestType, $providerId]);
            if (!$pending->fetchColumn()) {
                $request = $pdo->prepare("
                    INSERT INTO admin_action_requests
                        (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
                    VALUES (?, 'provider_registry', ?, ?, ?, ?, ?, ?)
                ");
                $request->execute([
                    $requestType,
                    $providerId,
                    $ref,
                    'Certificate ' . $ref,
                    admin_current_user_id($pdo),
                    $reason,
                    json_encode(['action' => $action, 'reason' => $reason, 'certificate_ref' => $ref], JSON_UNESCAPED_SLASHES),
                ]);
            }
            $msg = "Certificate action queued for Super Admin approval.";
        } else {
            // record structured audit event if available (include acting admin id/name if present)
            if (function_exists('admin_audit_event')) {
                admin_audit_event(
                    $pdo,
                    $auditAction,
                    'provider_registry',
                    $providerId,
                    [],
                    ['status' => $newStatus],
                    'success',
                    sprintf('%s: %s (ref=%s)%s', $auditAction, $reason ?: '-', $ref, PHP_SAPI === 'cli' ? ' (cli)' : '')
                );
            }
        }

        // notify provider by email if available (only when the change was applied)
        if ($isSuperAdmin && !empty($providerRow['email']) && function_exists('app_send_mail')) {
            $to = (string) $providerRow['email'];
            $subject = 'NATCODEV: Your accreditation certificate status changed';
            $plain = "Hello " . (($providerRow['contact_person'] ?? '') ?: '') . ",\n\nYour accreditation certificate ({$ref}) status has been changed to: " . ucfirst(strtolower($action)) . ".\n\nReason: " . ($reason ?: 'No reason provided') . "\n\nIf you have questions contact support.\n";
            $html = nl2br(htmlspecialchars($plain));
            // best-effort send
            try {
                app_send_mail($to, $subject, $plain, $html);
            } catch (Throwable $e) {
                // ignore email errors
            }
        }
    } catch (Throwable $e) {
        if (PHP_SAPI === 'cli') {
            echo "Action failed: " . $e->getMessage() . "\n";
            exit(1);
        }
        echo "<p>Action failed: " . e($e->getMessage()) . "</p>";
        exit;
    }

    if (PHP_SAPI === 'cli') {
        echo $msg . "\n";
        // show updated row
        $certificate = provider_accreditation_certificate_by_ref($pdo, $ref);
        print_r($certificate);
        exit(0);
    }
}

// Web view
?><?php require_once __DIR__ . '/../lib/admin-layout.php'; admin_page_start('Manage Provider Certificate', ['active' => '', 'wide' => true, 'head_html' => '', 'css' => '' . ' .nc-head{display:none}']); ?>
<h1>Manage Provider Certificate</h1>
<?php if (!empty($msg)): ?>
<p><strong><?= htmlspecialchars($msg) ?></strong></p>
<?php endif; ?>
<p><strong>Reference:</strong> <?= htmlspecialchars($ref) ?></p>
<p><strong>Provider:</strong> <?= htmlspecialchars((string) ($certificate['company_name'] ?? $certificate['contact_person'] ?? '')) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars((string) ($certificate['status'] ?? '')) ?></p>
<p><strong>Issued:</strong> <?= htmlspecialchars((string) ($certificate['issued_at'] ?? '')) ?></p>
<p><strong>Revoked At:</strong> <?= htmlspecialchars((string) ($certificate['revoked_at'] ?? '')) ?></p>
<p><strong>Revoked Reason:</strong> <?= nl2br(htmlspecialchars((string) ($certificate['revoked_reason'] ?? ''))) ?></p>

<form method="post">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
  <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
  <label>Action:
    <select name="action">
      <option value="">-- select --</option>
      <option value="REVOKE">Revoke</option>
      <option value="SUSPEND">Suspend / Hold</option>
      <option value="REINSTATE">Reinstate</option>
    </select>
  </label>
  <br>
  <label>Reason:<br>
    <textarea name="reason" rows="4" cols="60"></textarea>
  </label>
  <br>
  <button type="submit">Apply</button>
  <a href="verify-certificate.php?ref=<?= urlencode($ref) ?>" target="_blank">View verification page</a>
</form>
<?php admin_page_end(); ?><?php
