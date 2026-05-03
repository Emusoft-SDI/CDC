<?php
// lib/notifications.php
class NotificationSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Send notification based on user preference
    public function sendNotification($userId, $templateName, $variables = []) {
        // Get user notification preference
        $stmt = $pdo->prepare("SELECT phone, preferred_notification FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['phone']) {
            return false;
        }
        
        $phone = $this->formatNigerianNumber($user['phone']);
        $preference = $user['preferred_notification'] ?? 'sms';
        
        $results = [];
        
        // Send SMS if preferred or "both"
        if ($preference === 'sms' || $preference === 'both') {
            $smsTemplate = $this->getTemplate($templateName, 'sms');
            if ($smsTemplate) {
                $message = $this->renderTemplate($smsTemplate, $variables);
                $results['sms'] = $this->sendSMS($phone, $message);
            }
        }
        
        // Send WhatsApp if preferred or "both"
        if ($preference === 'whatsapp' || $preference === 'both') {
            $whatsappTemplate = $this->getTemplate($templateName, 'whatsapp');
            if ($whatsappTemplate) {
                $message = $this->renderTemplate($whatsappTemplate, $variables);
                $results['whatsapp'] = $this->sendWhatsApp($phone, $message);
            }
        }
        
        return $results;
    }
    
    // Get template from database
    private function getTemplate($templateName, $templateType) {
        $stmt = $this->pdo->prepare("
            SELECT message_template 
            FROM notification_templates 
            WHERE template_name = ? AND template_type = ? AND is_active = 1
        ");
        $stmt->execute([$templateName, $templateType]);
        return $stmt->fetchColumn();
    }
    
    // Render template with variables
    private function renderTemplate($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }
    
    // Format Nigerian phone number
    private function formatNigerianNumber($phone) {
        // Remove all non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different formats
        if (strlen($digits) === 11 && strpos($digits, '0') === 0) {
            // 08012345678 → 2348012345678
            return '234' . substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            // 8012345678 → 2348012345678
            return '234' . $digits;
        } elseif (strlen($digits) === 13 && strpos($digits, '234') === 0) {
            // Already formatted: 2348012345678
            return $digits;
        } else {
            // Return as-is if format is unknown
            return $digits;
        }
    }
    
    // Send SMS via Twilio
    private function sendSMS($phone, $message) {
        try {
            $client = new Twilio\Rest\Client($this->getTwilioSid(), $this->getTwilioToken());
            $client->messages->create(
                "+{$phone}",
                [
                    'from' => $this->getTwilioSmsNumber(),
                    'body' => $message
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("SMS failed for {$phone}: " . $e->getMessage());
            return false;
        }
    }
    
    // Send WhatsApp via Twilio
    private function sendWhatsApp($phone, $message) {
        try {
            $client = new Twilio\Rest\Client($this->getTwilioSid(), $this->getTwilioToken());
            $client->messages->create(
                "whatsapp:+{$phone}",
                [
                    'from' => $this->getTwilioWhatsAppNumber(),
                    'body' => $message
                ]
            );
            return true;
        } catch (Exception $e) {
            error_log("WhatsApp failed for {$phone}: " . $e->getMessage());
            return false;
        }
    }
    
    // Helper methods to get credentials
    private function getTwilioSid() {
        return $this->pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sid'")->fetchColumn();
    }
    
    private function getTwilioToken() {
        return $this->pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_token'")->fetchColumn();
    }
    
    private function getTwilioSmsNumber() {
        return $this->pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sms_number'")->fetchColumn();
    }
    
    private function getTwilioWhatsAppNumber() {
        return $this->pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_whatsapp_number'")->fetchColumn();
    }
}
?>