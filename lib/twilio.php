<?php
// lib/twilio.php - Enhanced with SMS fallback
require_once __DIR__ . '/../config.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function sendWhatsAppMessage($to, $message) {
    return sendMessage($to, $message, 'whatsapp');
}

function sendSMSMessage($to, $message) {
    return sendMessage($to, $message, 'sms');
}

function sendMessage($to, $message, $channel = 'whatsapp') {
    // Fetch credentials
    $pdo = db();
    if (!app_table_exists($pdo, 'settings')) {
        app_log_notification($channel, (string) $to, null, (string) $message, 'failed', 'twilio', null, 'Settings table is missing');
        return false;
    }

    $sid = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sid'")->fetchColumn();
    $token = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_token'")->fetchColumn();
    $whatsappFrom = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_whatsapp_number'")->fetchColumn();
    $smsFrom = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sms_number'")->fetchColumn();

    $transport = strtolower((string) app_env(strtoupper($channel) . '_TRANSPORT', app_is_production() ? 'twilio' : 'log'));
    $digits = preg_replace('/\D+/', '', (string) $to);
    if (str_starts_with($digits, '234')) {
        $number = '+' . $digits;
    } elseif (str_starts_with($digits, '0')) {
        $number = '+234' . substr($digits, 1);
    } else {
        $number = '+234' . $digits;
    }

    if ($transport === 'log') {
        app_log_notification($channel, $number, null, (string) $message, 'logged', $transport, 'Channel is in log mode');
        return true;
    }

    if (!$sid || !$token || !class_exists('Twilio\\Rest\\Client')) {
        $reason = !$sid || !$token ? 'Twilio credentials are missing' : 'Twilio SDK is not installed';
        app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, $reason);
        return false;
    }

    try {
        $client = new Twilio\Rest\Client($sid, $token);
        if ($channel === 'whatsapp') {
            $from = $whatsappFrom;
            $to = "whatsapp:" . $number;
        } else {
            $from = $smsFrom;
            $to = $number;
        }

        if (!$from) {
            app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, "Twilio {$channel} sender is missing");
            return false;
        }

        $result = $client->messages->create($to, [
            'from' => $from,
            'body' => $message
        ]);
        app_log_notification($channel, $number, null, (string) $message, 'sent', 'twilio', 'SID: ' . ($result->sid ?? 'accepted'));
        return true;
    } catch (Exception $e) {
        error_log("Twilio {$channel} Error: " . $e->getMessage());
        app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, $e->getMessage());
        return false;
    }
}
