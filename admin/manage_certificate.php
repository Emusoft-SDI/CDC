<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../provider/_provider.php';

$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    // CLI fallback: allow running from CLI as admin if PHP runs without session
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Forbidden');
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
$providerRow = $pdo->prepare('SELECT * FROM provider_registry WHERE id = ? LIMIT 1');
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
    try {
        if ($action === 'REVOKE') {
            $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NOW(), revoked_reason = ? WHERE certificate_ref = ?');
            $stmt->execute(['revoked', $reason, $ref]);
            $msg = "Certificate revoked.";
            $auditAction = 'certificate_revoked';
        } elseif ($action === 'SUSPEND' || $action === 'HOLD') {
            $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NULL, revoked_reason = ? WHERE certificate_ref = ?');
            $stmt->execute(['suspended', $reason, $ref]);
            $msg = "Certificate suspended/held.";
            $auditAction = 'certificate_suspended';
        } elseif ($action === 'REINSTATE' || $action === 'RESTORE') {
            $stmt = $pdo->prepare('UPDATE provider_accreditation_certificates SET status = ?, revoked_at = NULL, revoked_reason = NULL WHERE certificate_ref = ?');
            $stmt->execute(['issued', $ref]);
            $msg = "Certificate reinstated to issued.";
            $auditAction = 'certificate_reinstated';
        } else {
            throw new RuntimeException('Unknown action');
        }

        // record audit_log if available (include acting admin id/name if present)
        if (app_table_exists($pdo, 'audit_log')) {
            $actorId = null;
            $actorName = null;
            try {
                $actorId = admin_current_user_id($pdo);
                $actorName = admin_current_user_name($pdo);
            } catch (Throwable $e) {
                // ignore
            }
            $desc = sprintf('%s: %s by admin%s (ref=%s)%s', $auditAction, $reason ?: '-', $actorName ? ' ' . $actorName : '', $ref, PHP_SAPI === 'cli' ? ' (cli)' : '');
            $stmt = $pdo->prepare('INSERT INTO audit_log (action, description, ip_address, actor_id, actor_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$auditAction, $desc, $_SERVER['REMOTE_ADDR'] ?? null, $actorId, $actorName]);
        }

        // notify provider by email if available
        if (!empty($providerRow['email']) && function_exists('app_send_mail')) {
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
?><!doctype html>
<html>
<head><meta charset="utf-8"><title>Manage Certificate <?= htmlspecialchars($ref) ?></title></head>
<body>
<h1>Manage Provider Certificate</h1>
<p><strong>Reference:</strong> <?= htmlspecialchars($ref) ?></p>
<p><strong>Provider:</strong> <?= htmlspecialchars((string) ($certificate['company_name'] ?? $certificate['contact_person'] ?? '')) ?></p>
<p><strong>Status:</strong> <?= htmlspecialchars((string) ($certificate['status'] ?? '')) ?></p>
<p><strong>Issued:</strong> <?= htmlspecialchars((string) ($certificate['issued_at'] ?? '')) ?></p>
<p><strong>Revoked At:</strong> <?= htmlspecialchars((string) ($certificate['revoked_at'] ?? '')) ?></p>
<p><strong>Revoked Reason:</strong> <?= nl2br(htmlspecialchars((string) ($certificate['revoked_reason'] ?? ''))) ?></p>

<form method="post">
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
</body>
</html>

<?php
