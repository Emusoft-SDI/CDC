-- NATCODEV compatibility migration for the official natcodevcom_data schema.
-- Apply the official phpMyAdmin dump first, then apply this file.
-- Target: MariaDB 10.11+ / MySQL compatible syntax.

ALTER TABLE `certificates`
  ADD COLUMN IF NOT EXISTS `certificate_ref` VARCHAR(80) NULL AFTER `id`,
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(30) NOT NULL DEFAULT 'issued' AFTER `certificate_path`,
  ADD COLUMN IF NOT EXISTS `revoked_at` DATETIME NULL AFTER `verification_url`,
  ADD COLUMN IF NOT EXISTS `revoked_reason` TEXT NULL AFTER `revoked_at`;

CREATE INDEX IF NOT EXISTS `idx_certificates_status` ON `certificates` (`status`);
CREATE INDEX IF NOT EXISTS `idx_certificates_ref` ON `certificates` (`certificate_ref`);

ALTER TABLE `messages`
  ADD COLUMN IF NOT EXISTS `ticket_id` VARCHAR(50) NULL AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `category` VARCHAR(50) DEFAULT 'general' AFTER `ticket_id`,
  ADD COLUMN IF NOT EXISTS `priority` ENUM('low','medium','high') DEFAULT 'medium' AFTER `category`,
  ADD COLUMN IF NOT EXISTS `status` ENUM('open','in_progress','resolved','closed') DEFAULT 'open' AFTER `priority`;

CREATE INDEX IF NOT EXISTS `idx_messages_read` ON `messages` (`is_read`);

ALTER TABLE `document_requirements`
  ADD COLUMN IF NOT EXISTS `verified` TINYINT(1) DEFAULT 0 AFTER `file_path`,
  ADD COLUMN IF NOT EXISTS `api_validation_status` VARCHAR(30) DEFAULT 'pending' AFTER `verified_at`,
  ADD COLUMN IF NOT EXISTS `api_validation_response` TEXT NULL AFTER `api_validation_status`,
  ADD COLUMN IF NOT EXISTS `api_validation_timestamp` TIMESTAMP NULL DEFAULT NULL AFTER `api_validation_response`,
  ADD COLUMN IF NOT EXISTS `retry_count` INT NOT NULL DEFAULT 0 AFTER `api_validation_timestamp`,
  ADD COLUMN IF NOT EXISTS `last_retry_at` TIMESTAMP NULL DEFAULT NULL AFTER `retry_count`;

CREATE INDEX IF NOT EXISTS `idx_document_status` ON `document_requirements` (`verification_status`);

UPDATE `certificates`
SET `certificate_ref` = COALESCE(`certificate_ref`, `qr_code_hash`, CONCAT('CERT-', `application_id`, '-', `id`)),
    `qr_code_hash` = COALESCE(`qr_code_hash`, `certificate_ref`, CONCAT('CERT-', `application_id`, '-', `id`)),
    `status` = COALESCE(`status`, 'issued')
WHERE `certificate_ref` IS NULL OR `qr_code_hash` IS NULL OR `status` IS NULL;
