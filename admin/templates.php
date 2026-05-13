<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

function admin_template_library(): array
{
    return [
        'phone_verification' => [
            'label' => 'Phone Verification',
            'category' => 'Account',
            'sms' => 'NATCODEV: Your verification code is {code}. It expires in {timeout} minutes.',
            'whatsapp' => '*NATCODEV Phone Verification*' . "\n\n" . 'Your verification code is *{code}*.' . "\n" . 'It expires in {timeout} minutes.',
        ],
        'otp_login' => [
            'label' => 'Dashboard OTP Login',
            'category' => 'Account',
            'sms' => 'NATCODEV: Use {code} to sign in to your dashboard. This code expires in {timeout} minutes.',
            'whatsapp' => '*NATCODEV Login Code*' . "\n\n" . 'Use *{code}* to sign in. This code expires in {timeout} minutes.',
        ],
        'password_reset' => [
            'label' => 'Password Reset',
            'category' => 'Account',
            'sms' => 'NATCODEV: Reset your dashboard password here: {reset_url}. Ignore this if you did not request it.',
            'whatsapp' => '*NATCODEV Password Reset*' . "\n\n" . 'Reset your dashboard password: {reset_url}' . "\n\n" . 'Ignore this message if you did not request it.',
        ],
        'password_changed' => [
            'label' => 'Password Changed',
            'category' => 'Account',
            'sms' => 'NATCODEV: Your dashboard password was changed. Contact support immediately if this was not you.',
            'whatsapp' => '*NATCODEV Security Notice*' . "\n\n" . 'Your dashboard password was changed. Contact support immediately if this was not you.',
        ],
        'bulk_user_onboarded' => [
            'label' => 'Bulk User Onboarded',
            'category' => 'Account',
            'sms' => 'NATCODEV: Your {role} account has been created. Login: {login_url}. Temporary password: {temporary_password}',
            'whatsapp' => '*NATCODEV Account Created*' . "\n\n" . 'Role: {role}' . "\n" . 'Login: {login_url}' . "\n" . 'Temporary password: {temporary_password}',
            'email' => 'Hello {name}, your NATCODEV {role} account has been created.' . "\n\n" . 'Login: {login_url}' . "\n" . 'Temporary password: {temporary_password}' . "\n\n" . 'Please sign in and update your profile and password.',
        ],
        'application_received' => [
            'label' => 'Application Received',
            'category' => 'Application',
            'sms' => 'NATCODEV: Thank you {name}. Your application {app_ref} has been received and is pending review.',
            'whatsapp' => '*Application Received*' . "\n\n" . 'Thank you {name}. Your NATCODEV application reference is *{app_ref}* and is pending review.',
        ],
        'application_confirmed' => [
            'label' => 'Application Confirmed',
            'category' => 'Application',
            'sms' => 'NATCODEV: Your application {app_ref} has been confirmed. Login: {login_url}. Temporary password: {temporary_password}',
            'whatsapp' => '*Application Confirmed*' . "\n\n" . 'Your application *{app_ref}* has been confirmed.' . "\n" . 'Login: {login_url}' . "\n" . 'Temporary password: {temporary_password}',
        ],
        'application_rejected' => [
            'label' => 'Application Needs Attention',
            'category' => 'Application',
            'sms' => 'NATCODEV: Your application {app_ref} needs attention. Reason: {reason}. Login to update your details.',
            'whatsapp' => '*Application Update Required*' . "\n\n" . 'Your application *{app_ref}* needs attention.' . "\n" . 'Reason: {reason}' . "\n" . 'Please login to update your details.',
        ],
        'document_uploaded' => [
            'label' => 'Document Uploaded',
            'category' => 'Documents',
            'sms' => 'NATCODEV: Your {document_type} document was uploaded and is awaiting verification.',
            'whatsapp' => '*Document Uploaded*' . "\n\n" . 'Your {document_type} document was uploaded and is awaiting verification.',
        ],
        'document_verified' => [
            'label' => 'Document Verified',
            'category' => 'Documents',
            'sms' => 'NATCODEV: Your {document_type} document has been verified successfully.',
            'whatsapp' => '*Document Verified*' . "\n\n" . 'Your {document_type} document has been verified successfully.',
        ],
        'document_rejected' => [
            'label' => 'Document Rejected',
            'category' => 'Documents',
            'sms' => 'NATCODEV: Your {document_type} document was rejected. Reason: {reason}. Please upload a corrected copy.',
            'whatsapp' => '*Document Rejected*' . "\n\n" . 'Your {document_type} document was rejected.' . "\n" . 'Reason: {reason}' . "\n" . 'Please upload a corrected copy.',
        ],
        'validation_success' => [
            'label' => 'Validation Success',
            'category' => 'Documents',
            'sms' => 'NATCODEV: Your {document_type} validation was successful. Certificate processing can continue.',
            'whatsapp' => '*Validation Successful*' . "\n\n" . 'Your {document_type} validation was successful. Certificate processing can continue.',
        ],
        'validation_failure' => [
            'label' => 'Validation Failure',
            'category' => 'Documents',
            'sms' => 'NATCODEV: Your {document_type} validation failed. Please check your details and resubmit.',
            'whatsapp' => '*Validation Failed*' . "\n\n" . 'Your {document_type} validation failed. Please check your details and resubmit.',
        ],
        'certificate_ready' => [
            'label' => 'Certificate Ready',
            'category' => 'Certificate',
            'sms' => 'NATCODEV: Your certificate {certificate_ref} is ready. Download: {certificate_url}',
            'whatsapp' => '*Certificate Ready*' . "\n\n" . 'Your certificate *{certificate_ref}* is ready.' . "\n" . 'Download: {certificate_url}',
        ],
        'certificate_issued' => [
            'label' => 'Certificate Issued',
            'category' => 'Certificate',
            'sms' => 'NATCODEV: Certificate {certificate_ref} has been issued to {name}. Verify: {verification_url}',
            'whatsapp' => '*Certificate Issued*' . "\n\n" . 'Certificate *{certificate_ref}* has been issued to {name}.' . "\n" . 'Verify: {verification_url}',
        ],
        'certificate_revoked' => [
            'label' => 'Certificate Revoked',
            'category' => 'Certificate',
            'sms' => 'NATCODEV: Certificate {certificate_ref} has been revoked. Reason: {reason}. Contact support for help.',
            'whatsapp' => '*Certificate Revoked*' . "\n\n" . 'Certificate *{certificate_ref}* has been revoked.' . "\n" . 'Reason: {reason}' . "\n" . 'Contact support for help.',
        ],
        'support_ticket_opened' => [
            'label' => 'Support Ticket Opened',
            'category' => 'Support',
            'sms' => 'NATCODEV: Support ticket {ticket_id} has been opened. Category: {category}.',
            'whatsapp' => '*Support Ticket Opened*' . "\n\n" . 'Ticket: *{ticket_id}*' . "\n" . 'Category: {category}',
        ],
        'support_reply' => [
            'label' => 'Support Reply',
            'category' => 'Support',
            'sms' => 'NATCODEV: You have a new reply on ticket {ticket_id}. Login to view and respond.',
            'whatsapp' => '*New Support Reply*' . "\n\n" . 'You have a new reply on ticket *{ticket_id}*. Login to view and respond.',
        ],
        'support_ticket_closed' => [
            'label' => 'Support Ticket Closed',
            'category' => 'Support',
            'sms' => 'NATCODEV: Ticket {ticket_id} has been marked {status}. Thank you.',
            'whatsapp' => '*Support Ticket Updated*' . "\n\n" . 'Ticket *{ticket_id}* has been marked *{status}*.',
        ],
        'wallet_funded' => [
            'label' => 'Wallet Funded',
            'category' => 'Wallet',
            'sms' => 'NATCODEV: Your wallet was credited with NGN {amount}. Balance: NGN {balance}. Ref: {reference}',
            'whatsapp' => '*Wallet Credited*' . "\n\n" . 'Amount: NGN {amount}' . "\n" . 'Balance: NGN {balance}' . "\n" . 'Reference: {reference}',
        ],
        'wallet_debited' => [
            'label' => 'Wallet Debited',
            'category' => 'Wallet',
            'sms' => 'NATCODEV: NGN {amount} was debited from your wallet for {description}. Balance: NGN {balance}.',
            'whatsapp' => '*Wallet Debited*' . "\n\n" . 'Amount: NGN {amount}' . "\n" . 'Purpose: {description}' . "\n" . 'Balance: NGN {balance}',
        ],
        'payment_pending' => [
            'label' => 'Payment Pending',
            'category' => 'Wallet',
            'sms' => 'NATCODEV: Payment {reference} for NGN {amount} is pending. Complete payment to fund your wallet.',
            'whatsapp' => '*Payment Pending*' . "\n\n" . 'Reference: {reference}' . "\n" . 'Amount: NGN {amount}' . "\n" . 'Complete payment to fund your wallet.',
        ],
        'marketplace_order' => [
            'label' => 'Marketplace Order',
            'category' => 'Marketplace',
            'sms' => 'NATCODEV: Your marketplace order {order_ref} for {item_title} has been received.',
            'whatsapp' => '*Marketplace Order Received*' . "\n\n" . 'Order: {order_ref}' . "\n" . 'Item: {item_title}',
        ],
        'marketplace_order_status' => [
            'label' => 'Marketplace Order Status',
            'category' => 'Marketplace',
            'sms' => 'NATCODEV: Order {order_ref} is now {status}.',
            'whatsapp' => '*Marketplace Order Update*' . "\n\n" . 'Order *{order_ref}* is now *{status}*.',
        ],
        'webinar_registered' => [
            'label' => 'Webinar Registered',
            'category' => 'Training',
            'sms' => 'NATCODEV: You are registered for {webinar_title} on {webinar_date}. Link: {webinar_url}',
            'whatsapp' => '*Training Registration Confirmed*' . "\n\n" . 'Session: {webinar_title}' . "\n" . 'Date: {webinar_date}' . "\n" . 'Link: {webinar_url}',
        ],
        'webinar_reminder' => [
            'label' => 'Webinar Reminder',
            'category' => 'Training',
            'sms' => 'NATCODEV Reminder: {webinar_title} starts at {start_time}. Join: {webinar_url}',
            'whatsapp' => '*Training Reminder*' . "\n\n" . '{webinar_title} starts at {start_time}.' . "\n" . 'Join: {webinar_url}',
        ],
        'field_visit_scheduled' => [
            'label' => 'Field Visit Scheduled',
            'category' => 'Field',
            'sms' => 'NATCODEV: A field visit is scheduled for {visit_date}. Agent: {agent_name}, {agent_phone}.',
            'whatsapp' => '*Field Visit Scheduled*' . "\n\n" . 'Date: {visit_date}' . "\n" . 'Agent: {agent_name}' . "\n" . 'Phone: {agent_phone}',
        ],
        'field_visit_completed' => [
            'label' => 'Field Visit Completed',
            'category' => 'Field',
            'sms' => 'NATCODEV: Field visit for {name} was completed on {visit_date}. Notes: {notes}',
            'whatsapp' => '*Field Visit Completed*' . "\n\n" . 'Grower: {name}' . "\n" . 'Date: {visit_date}' . "\n" . 'Notes: {notes}',
        ],
        'recruitment_received' => [
            'label' => 'Recruitment Application Received',
            'category' => 'Recruitment',
            'sms' => 'NATCODEV: Your {role} application {app_ref} has been received and is pending review.',
            'whatsapp' => '*Recruitment Application Received*' . "\n\n" . 'Role: {role}' . "\n" . 'Reference: {app_ref}' . "\n" . 'Status: Pending review.',
        ],
        'recruitment_shortlisted' => [
            'label' => 'Recruitment Shortlisted',
            'category' => 'Recruitment',
            'sms' => 'NATCODEV: Your {role} application {app_ref} has been shortlisted. Next step: {next_step}',
            'whatsapp' => '*Recruitment Shortlisted*' . "\n\n" . 'Your {role} application *{app_ref}* has been shortlisted.' . "\n" . 'Next step: {next_step}',
        ],
        'recruitment_approved' => [
            'label' => 'Recruitment Approved',
            'category' => 'Recruitment',
            'sms' => 'NATCODEV: Your {role} application {app_ref} has been approved. Login: {login_url}. Temporary password: {temporary_password}',
            'whatsapp' => '*Recruitment Approved*' . "\n\n" . 'Your {role} application *{app_ref}* has been approved.' . "\n" . 'Login: {login_url}' . "\n" . 'Temporary password: {temporary_password}',
        ],
        'recruitment_rejected' => [
            'label' => 'Recruitment Not Approved',
            'category' => 'Recruitment',
            'sms' => 'NATCODEV: Your {role} application {app_ref} was not approved at this time. Reason: {reason}',
            'whatsapp' => '*Recruitment Update*' . "\n\n" . 'Your {role} application *{app_ref}* was not approved at this time.' . "\n" . 'Reason: {reason}',
        ],
        'farm_health_request' => [
            'label' => 'Farm Health Request',
            'category' => 'Field',
            'sms' => 'NATCODEV: Farm health request {ticket_id} has been received. Our team will review it.',
            'whatsapp' => '*Farm Health Request Received*' . "\n\n" . 'Request: {ticket_id}' . "\n" . 'Our team will review it.',
        ],
        'healthcare_request' => [
            'label' => 'Healthcare Request',
            'category' => 'Services',
            'sms' => 'NATCODEV: Healthcare service request {request_ref} has been received and is under review.',
            'whatsapp' => '*Healthcare Request Received*' . "\n\n" . 'Request: {request_ref}' . "\n" . 'Status: Under review',
        ],
        'admin_alert' => [
            'label' => 'Admin Alert',
            'category' => 'Admin',
            'sms' => 'NATCODEV Admin: {event} requires attention. Ref: {reference}',
            'whatsapp' => '*NATCODEV Admin Alert*' . "\n\n" . 'Event: {event}' . "\n" . 'Reference: {reference}',
        ],
        'security_alert' => [
            'label' => 'Security Alert',
            'category' => 'Admin',
            'sms' => 'NATCODEV Security: {event} detected for {account}. If this was not authorized, contact support.',
            'whatsapp' => '*NATCODEV Security Alert*' . "\n\n" . 'Event: {event}' . "\n" . 'Account: {account}' . "\n" . 'Contact support if this was not authorized.',
        ],
    ];
}

function admin_template_options(array $library): array
{
    $options = [];
    foreach ($library as $key => $template) {
        $options[$key] = $template['label'];
    }
    return $options;
}

function admin_seed_templates(PDO $pdo, array $library, bool $overwrite = false): int
{
    $inserted = 0;
    $sql = $overwrite
        ? "INSERT INTO notification_templates (template_name, template_type, message_template, is_active)
           VALUES (?, ?, ?, 1)
           ON DUPLICATE KEY UPDATE message_template = VALUES(message_template), is_active = 1"
        : "INSERT IGNORE INTO notification_templates (template_name, template_type, message_template, is_active)
           VALUES (?, ?, ?, 1)";
    $stmt = $pdo->prepare($sql);

    foreach ($library as $name => $template) {
        foreach (['sms', 'whatsapp', 'email'] as $type) {
            $text = $template[$type] ?? $template['whatsapp'] ?? $template['sms'];
            $stmt->execute([$name, $type, $text]);
            $inserted += $stmt->rowCount();
        }
    }

    return $inserted;
}

$library = admin_template_library();
$names = admin_template_options($library);
$message = '';
$error = '';
$existingRecords = (int) $pdo->query("SELECT COUNT(*) FROM notification_templates")->fetchColumn();
if ($existingRecords < count($library) * 3 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $created = admin_seed_templates($pdo, $library, false);
    if ($created > 0) {
        $message = "{$created} missing default template records generated.";
    }
}

if ((string) ($_GET['seed'] ?? '') === '1') {
    $created = admin_seed_templates($pdo, $library, false);
    $message = $created > 0
        ? "{$created} template records generated."
        : 'All default templates already exist.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');

        if ($action === 'seed_missing') {
            $created = admin_seed_templates($pdo, $library, false);
            $message = $created > 0 ? "{$created} missing template records generated." : 'All default templates already exist.';
        } elseif ($action === 'reset_defaults') {
            $created = admin_seed_templates($pdo, $library, true);
            $message = "Default library refreshed across {$created} template records.";
        } else {
            $templateName = (string) ($_POST['template_name'] ?? '');
            $sms = trim((string) ($_POST['sms_template'] ?? ''));
            $whatsapp = trim((string) ($_POST['whatsapp_template'] ?? ''));
            $active = isset($_POST['is_active']) ? 1 : 0;

            if (!isset($names[$templateName]) || $sms === '' || $whatsapp === '') {
                $error = 'Choose a template and provide both SMS and WhatsApp text.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO notification_templates (template_name, template_type, message_template, is_active)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE message_template = VALUES(message_template), is_active = VALUES(is_active)
                ");
                $stmt->execute([$templateName, 'sms', $sms, $active]);
                $stmt->execute([$templateName, 'whatsapp', $whatsapp, $active]);
                $message = 'Template saved.';
            }
        }
    }
}

$templates = $pdo->query("
    SELECT template_name,
           MAX(CASE WHEN template_type = 'sms' THEN is_active ELSE 0 END) sms_active,
           MAX(CASE WHEN template_type = 'whatsapp' THEN is_active ELSE 0 END) whatsapp_active
    FROM notification_templates
    GROUP BY template_name
    ORDER BY template_name
")->fetchAll();

$templateCount = count($templates);
$expectedCount = count($library);

admin_page_start('Notification Templates', [
    'active' => 'templates.php',
    'description' => 'Generate and manage reusable SMS and WhatsApp messages across account, application, verification, certificate, wallet, support, field, and service workflows.',
    'wide' => true,
    'action_html' => '<a class="button secondary" href="templates.php?seed=1">Generate Missing Defaults</a>',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><span>Template Workflows</span><div class="metric"><?= $templateCount ?></div><p class="meta">Generated out of <?= $expectedCount ?> defaults.</p></div>
  <div class="stat"><span>Channels</span><div class="metric">3</div><p class="meta">Email, SMS, and WhatsApp.</p></div>
  <div class="stat"><span>Total Records</span><div class="metric"><?= $templateCount * 3 ?></div><p class="meta">One record per workflow/channel.</p></div>
</section>

<section class="panel">
  <form method="post" class="toolbar">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" name="action" value="seed_missing">Generate Missing Templates</button>
    <button type="submit" class="secondary" name="action" value="reset_defaults" onclick="return confirm('Reset all default template text to the generated library? Custom edits to default templates will be overwritten.')">Reset Default Text</button>
  </form>
</section>

<section class="layout" style="margin-top:18px;">
  <form class="panel" method="post" id="templateForm">
    <h2>Template Editor</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <label>Template Name</label>
    <select name="template_name" required onchange="loadTemplate(this.value)">
      <option value="">Select Template</option>
      <?php foreach ($library as $value => $template): ?>
        <option value="<?= e($value) ?>"><?= e($template['category'] . ' - ' . $template['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <label>SMS Template</label>
    <textarea name="sms_template" rows="5" required></textarea>
    <small>Common variables: {name}, {code}, {timeout}, {app_ref}, {document_type}, {reason}, {certificate_ref}, {ticket_id}, {amount}, {balance}, {reference}, {login_url}.</small>
    <label>WhatsApp Template</label>
    <textarea name="whatsapp_template" rows="8" required></textarea>
    <label><input type="checkbox" name="is_active" checked> Active</label>
    <div class="actions"><button type="submit">Save Template</button></div>
  </form>

  <section>
    <table>
      <thead><tr><th>Template</th><th>Category</th><th>SMS</th><th>WhatsApp</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($templates as $template): ?>
          <?php $meta = $library[$template['template_name']] ?? ['label' => $template['template_name'], 'category' => 'Custom']; ?>
          <tr>
            <td><?= e($meta['label']) ?><br><small><?= e((string) $template['template_name']) ?></small></td>
            <td><?= e($meta['category']) ?></td>
            <td><span class="badge <?= (int) $template['sms_active'] === 1 ? 'verified' : 'closed' ?>"><?= (int) $template['sms_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <td><span class="badge <?= (int) $template['whatsapp_active'] === 1 ? 'verified' : 'closed' ?>"><?= (int) $template['whatsapp_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
            <td><button type="button" class="secondary" onclick="editTemplate('<?= e((string) $template['template_name']) ?>')">Edit</button></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$templates): ?><tr><td colspan="5">No templates saved yet. Use Generate Missing Templates.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</section>

<script>
async function loadTemplate(templateName) {
  if (!templateName) return;
  const response = await fetch(`../api/get-template.php?name=${encodeURIComponent(templateName)}`);
  const template = await response.json();
  document.querySelector('[name="sms_template"]').value = template.sms ? (template.sms.message_template || '') : '';
  document.querySelector('[name="whatsapp_template"]').value = template.whatsapp ? (template.whatsapp.message_template || '') : '';
  document.querySelector('[name="is_active"]').checked = template.sms ? Number(template.sms.is_active || 0) === 1 : true;
}

function editTemplate(templateName) {
  document.querySelector('[name="template_name"]').value = templateName;
  loadTemplate(templateName);
  document.getElementById('templateForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
<?php admin_page_end(); ?>
