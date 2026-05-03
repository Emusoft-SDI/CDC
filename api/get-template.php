// api/get-template.php
<?php
header('Content-Type: application/json');

$templateName = $_GET['name'] ?? '';
if (!$templateName) {
    http_response_code(400);
    exit(json_encode(['error' => 'Template name required']));
}

$stmt = $pdo->prepare("
    SELECT template_type, message_template, is_active
    FROM notification_templates 
    WHERE template_name = ?
");
$stmt->execute([$templateName]);
$templates = $stmt->fetchAll();

$result = [];
foreach ($templates as $template) {
    $result[$template['template_type']] = $template;
}

echo json_encode($result);
?>

// api/delete-template.php
<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$templateName = $input['template_name'] ?? '';

if (!$templateName) {
    http_response_code(400);
    exit(json_encode(['error' => 'Template name required']));
}

$pdo->prepare("DELETE FROM notification_templates WHERE template_name = ?")
     ->execute([$templateName]);

echo json_encode(['success' => true]);
?>