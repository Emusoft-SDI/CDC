<!-- dashboard/documents.php -->
<?php
// Get required documents based on user role
$role = $user['role'] ?? 'grower';
$requiredDocs = json_decode($pdo->query("SELECT value FROM settings WHERE key_name = 'required_documents_$role'")->fetchColumn(), true);
$docTypes = [
    'nin' => 'National ID Number (NIN)',
    'bvn' => 'Bank Verification Number (BVN)', 
    'voters_card' => "Voter's Card",
    'drivers_license' => "Driver's License",
    'international_passport' => 'International Passport'
];
?>
<div class="document-upload">
  <h2>Identity Verification</h2>
  <p>Upload your official identification documents to receive your certificate.</p>
  
  <?php foreach ($requiredDocs as $docType): ?>
    <div class="document-item">
      <h3><?= htmlspecialchars($docTypes[$docType]) ?> *</h3>
      
      <!-- Document number input -->
      <input type="text" 
             name="doc_number[<?= $docType ?>]" 
             placeholder="Enter <?= strtoupper($docType) ?> number"
             value="<?= htmlspecialchars(getDocumentNumber($pdo, $_SESSION['user_id'], $docType)) ?>">
      
      <!-- Document upload -->
      <input type="file" 
             name="doc_file[<?= $docType ?>]" 
             accept="image/jpeg,image/png,application/pdf"
             <?= isDocumentVerified($pdo, $_SESSION['user_id'], $docType) ? 'disabled' : '' ?>>
      
      <!-- Verification status -->
      <?php if ($status = getDocumentStatus($pdo, $_SESSION['user_id'], $docType)): ?>
        <div class="status-<?= $status ?>">
          <?= ucfirst($status) ?>
          <?php if ($status === 'rejected'): ?>
            <br><small><?= htmlspecialchars(getVerificationNotes($pdo, $_SESSION['user_id'], $docType)) ?></small>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  
  <button type="submit" name="save_documents">Save Documents</button>
</div>

<?php
function getDocumentNumber($pdo, $userId, $docType) {
    $stmt = $pdo->prepare("SELECT document_number FROM document_requirements WHERE user_id = ? AND document_type = ?");
    $stmt->execute([$userId, $docType]);
    return $stmt->fetchColumn();
}

function getDocumentStatus($pdo, $userId, $docType) {
    $stmt = $pdo->prepare("SELECT verification_status FROM document_requirements WHERE user_id = ? AND document_type = ?");
    $stmt->execute([$userId, $docType]);
    return $stmt->fetchColumn();
}

function getVerificationNotes($pdo, $userId, $docType) {
    $stmt = $pdo->prepare("SELECT verification_notes FROM document_requirements WHERE user_id = ? AND document_type = ?");
    $stmt->execute([$userId, $docType]);
    return $stmt->fetchColumn();
}

function isDocumentVerified($pdo, $userId, $docType) {
    return getDocumentStatus($pdo, $userId, $docType) === 'verified';
}
?>

// In documents.php - after saving documents
require_once '../lib/identity-validation.php';

if (!$error) {
    // Auto-validate BVN/NIN if enabled
    if ($pdo->query("SELECT value FROM settings WHERE key_name = 'validation_auto_enabled'")->fetchColumn() === '1') {
        $validator = new IdentityValidator($pdo);
        
        foreach ($requiredDocs as $docType) {
            if (in_array($docType, ['bvn', 'nin'])) {
                $number = trim($docNumbers[$docType] ?? '');
                if (!empty($number)) {
                    // Get the document ID
                    $stmt = $pdo->prepare("
                        SELECT id FROM document_requirements 
                        WHERE user_id = ? AND document_type = ?
                    ");
                    $stmt->execute([$userId, $docType]);
                    $docId = $stmt->fetchColumn();
                    
                    if ($docId) {
                        $validator->autoValidateDocument($docId, $docType, $number, $userId);
                    }
                }
            }
        }
    }
    
    $message = "Documents submitted successfully! Verification may take 1-2 business days.";
}

// In documents.php - handle document uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_documents'])) {
    $userId = $_SESSION['user_id'];
    $docNumbers = $_POST['doc_number'] ?? [];
    $docFiles = $_FILES['doc_file'] ?? [];
    
    foreach ($requiredDocs as $docType) {
        $number = trim($docNumbers[$docType] ?? '');
        $file = $docFiles[$docType] ?? null;
        
        // Validate document number
        if (empty($number)) {
            $error = "Please enter your {$docTypes[$docType]} number.";
            break;
        }
        
        // Handle file upload
        $filePath = null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/documents/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = "user_{$userId}_{$docType}_" . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                $error = "Failed to upload {$docTypes[$docType]} document.";
                break;
            }
            $filePath = "/documents/" . $fileName;
        }
        
        // Save or update document record
        $stmt = $pdo->prepare("
            INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status)
            VALUES (?, ?, ?, ?, 'pending')
            ON DUPLICATE KEY UPDATE 
                document_number = VALUES(document_number),
                file_path = VALUES(file_path),
                verification_status = 'pending'
        ");
        $stmt->execute([$userId, $docType, $number, $filePath]);
    }
    
    if (!$error) {
        $message = "Documents submitted successfully! Verification may take 1-2 business days.";
    }
}