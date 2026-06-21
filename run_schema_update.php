<?php
require_once __DIR__ . '/config.php';

$pdo = db();

$sql = "
-- 1. Update Wallets Table
ALTER TABLE `wallets` ADD COLUMN IF NOT EXISTS `status` VARCHAR(40) NOT NULL DEFAULT 'active';
ALTER TABLE `wallets` ADD COLUMN IF NOT EXISTS `hold_balance` DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE `wallets` ADD COLUMN IF NOT EXISTS `wallet_type` VARCHAR(60) NULL;
ALTER TABLE `wallets` ADD COLUMN IF NOT EXISTS `last_activity_at` DATETIME NULL;
ALTER TABLE `wallets` ADD COLUMN IF NOT EXISTS `reserved_account_number` VARCHAR(80) NULL;

-- 2. Update Users Table (for platform_role)
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `platform_role` VARCHAR(50) NULL AFTER `role`;

-- 3. Create Missing Wallet/Marketplace/Academy Tables if they don't exist
CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `charge` DECIMAL(12,2) DEFAULT 0,
    `final_amount` DECIMAL(12,2) NOT NULL,
    `bank_name` VARCHAR(100),
    `account_number` VARCHAR(50),
    `account_name` VARCHAR(100),
    `provider` VARCHAR(50) DEFAULT 'manual',
    `payout_status` VARCHAR(50) DEFAULT 'pending',
    `reference` VARCHAR(100),
    `status` ENUM('pending','processing','approved','rejected') DEFAULT 'pending',
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

CREATE TABLE IF NOT EXISTS `marketplace_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `seller_id` INT NOT NULL,
    `buyer_name` VARCHAR(255),
    `total_amount` DECIMAL(12,2),
    `payment_status` VARCHAR(50) DEFAULT 'pending',
    `order_ref` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `settled_at` DATETIME NULL
);

CREATE TABLE IF NOT EXISTS `marketplace_sellers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_name` VARCHAR(255),
    `email` VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `academy_refund_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `webinar_id` INT,
    `amount` DECIMAL(12,2),
    `reason` TEXT,
    `status` ENUM('pending','under_review','approved','rejected') DEFAULT 'pending',
    `admin_notes` TEXT,
    `reviewed_by` INT,
    `reviewed_at` DATETIME,
    `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Update Webinar Registrations for Payment Reference
ALTER TABLE `webinar_registrations` ADD COLUMN IF NOT EXISTS `payment_reference` VARCHAR(100) NULL;
";

try {
    $pdo->exec($sql);
    echo "Database schema updated successfully.";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
