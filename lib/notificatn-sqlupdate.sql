-- Add notification preferences
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS preferred_notification ENUM('sms', 'whatsapp', 'both') DEFAULT 'sms';

-- Add template management table
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) UNIQUE NOT NULL,
    template_type ENUM('sms', 'whatsapp') NOT NULL,
    message_template TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default templates
INSERT IGNORE INTO notification_templates (template_name, template_type, message_template) VALUES
('phone_verification', 'sms', 'Your NATCODEV verification code is: {code}. Valid for {timeout} minutes.'),
('phone_verification', 'whatsapp', '🔐 *NATCODEV Phone Verification*\n\nYour verification code is: *{code}*\nValid for {timeout} minutes.\n\nDo not share this code with anyone.'),
('validation_success', 'sms', '✅ NATCODEV: Your {document_type} validation was successful! Certificate processing has begun.'),
('validation_success', 'whatsapp', '✅ *NATCODEV Validation Success*\n\nYour {document_type} validation was successful!\n\nCertificate processing has begun.\n\nThank you for your participation in Nigeria\'s agricultural revolution!'),
('validation_failure', 'sms', '❌ NATCODEV: Your {document_type} validation failed. Please check your details and resubmit.'),
('validation_failure', 'whatsapp', '❌ *NATCODEV Validation Failed*\n\nYour {document_type} validation failed.\n\nPlease check your details and resubmit.\n\nContact support: 0703-COCONUT');