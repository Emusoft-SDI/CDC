<?php
// lib/webhook-dispatcher.php
class WebhookDispatcher {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function dispatchValidationEvent($eventType, $documentData) {
        // Get active webhooks for this event type
        $stmt = $pdo->prepare("
            SELECT id, name, url, secret_key, event_types
            FROM webhook_endpoints 
            WHERE active = 1
        ");
        $stmt->execute();
        $webhooks = $stmt->fetchAll();
        
        foreach ($webhooks as $webhook) {
            $eventTypes = json_decode($webhook['event_types'], true);
            if (!in_array($eventType, $eventTypes)) {
                continue;
            }
            
            $payload = [
                'event' => $eventType,
                'timestamp' => date('c'),
                'document' => $documentData,
                'platform' => 'NATCODEV'
            ];
            
            $this->sendWebhook($webhook['url'], $payload, $webhook['secret_key']);
        }
    }
    
    private function sendWebhook($url, $payload, $secretKey = null) {
        $jsonPayload = json_encode($payload);
        $signature = $secretKey ? hash_hmac('sha256', $jsonPayload, $secretKey) : null;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter([
            'Content-Type: application/json',
            'User-Agent: NATCODEV-Webhook/1.0',
            $signature ? "X-NATCODEV-Signature: {$signature}" : null
        ]));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log webhook delivery
        error_log("Webhook to {$url}: HTTP {$httpCode}");
    }
}
?>