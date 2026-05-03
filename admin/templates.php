<!-- admin/templates.php -->
<h2>Notification Template Management</h2>

<!-- Template Editor -->
<form method="POST" id="templateForm">
  <div class="form-group">
    <label>Template Name</label>
    <select name="template_name" required onchange="loadTemplate(this.value)">
      <option value="">Select Template</option>
      <option value="phone_verification">Phone Verification</option>
      <option value="validation_success">Validation Success</option>
      <option value="validation_failure">Validation Failure</option>
      <option value="certificate_issued">Certificate Issued</option>
      <option value="document_rejected">Document Rejected</option>
    </select>
  </div>
  
  <div class="form-group">
    <label>SMS Template</label>
    <textarea name="sms_template" rows="4" placeholder="SMS message template..." required></textarea>
    <small>Available variables: {code}, {timeout}, {document_type}, {name}</small>
  </div>
  
  <div class="form-group">
    <label>WhatsApp Template</label>
    <textarea name="whatsapp_template" rows="6" placeholder="WhatsApp message template..." required></textarea>
    <small>Supports WhatsApp formatting (*bold*, _italic_)</small>
  </div>
  
  <div class="form-group">
    <label>
      <input type="checkbox" name="is_active" checked> Active
    </label>
  </div>
  
  <button type="submit" name="save_template">Save Template</button>
</form>

<!-- Template List -->
<h3>Current Templates</h3>
<table>
  <thead>
    <tr>
      <th>Template Name</th>
      <th>SMS Active</th>
      <th>WhatsApp Active</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $templates = $pdo->query("
        SELECT template_name, 
               MAX(CASE WHEN template_type = 'sms' THEN is_active END) as sms_active,
               MAX(CASE WHEN template_type = 'whatsapp' THEN is_active END) as whatsapp_active
        FROM notification_templates 
        GROUP BY template_name
    ")->fetchAll();
    
    foreach ($templates as $template):
    ?>
    <tr>
      <td><?= htmlspecialchars($template['template_name']) ?></td>
      <td><?= $template['sms_active'] ? '✅' : '❌' ?></td>
      <td><?= $template['whatsapp_active'] ? '✅' : '❌' ?></td>
      <td>
        <button onclick="editTemplate('<?= $template['template_name'] ?>')">Edit</button>
        <button onclick="deleteTemplate('<?= $template['template_name'] ?>')" style="background:#c62828;">Delete</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<script>
// Load template for editing
async function loadTemplate(templateName) {
  if (!templateName) return;
  
  const response = await fetch(`/api/get-template.php?name=${templateName}`);
  const template = await response.json();
  
  if (template.sms) {
    document.querySelector('[name="sms_template"]').value = template.sms.message_template;
    document.querySelector('[name="is_active"]').checked = template.sms.is_active;
  }
  if (template.whatsapp) {
    document.querySelector('[name="whatsapp_template"]').value = template.whatsapp.message_template;
  }
}

// Edit template
function editTemplate(templateName) {
  document.querySelector('[name="template_name"]').value = templateName;
  loadTemplate(templateName);
}

// Delete template
async function deleteTemplate(templateName) {
  if (confirm('Delete this template?')) {
    await fetch(`/api/delete-template.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ template_name: templateName })
    });
    location.reload();
  }
}
</script>