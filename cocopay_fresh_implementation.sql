-- Cocopay Fresh Implementation Schema
-- Target: MySQL 8+ / MariaDB 10.5+
-- Purpose: fresh, production-oriented Cocopay install with robust fintech controls.
-- Notes:
--   1. Keep money movement in the double-entry ledger tables.
--   2. Do not mutate ledger rows after posting. Use reversal entries.
--   3. Store provider secrets encrypted at application level before insert.
--   4. Replace seeded placeholder passwords/secrets before use.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS cocopay
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE cocopay;

-- ---------------------------------------------------------------------------
-- Identity, Access, And Configuration
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS admin_permission_role;
DROP TABLE IF EXISTS admin_role_user;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS feature_flags;
DROP TABLE IF EXISTS emergency_controls;

CREATE TABLE app_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value JSON NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feature_flags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flag_key VARCHAR(120) NOT NULL UNIQUE,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE emergency_controls (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  control_key VARCHAR(120) NOT NULL UNIQUE,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  reason VARCHAR(255) NULL,
  enabled_by BIGINT UNSIGNED NULL,
  enabled_at TIMESTAMP NULL,
  disabled_by BIGINT UNSIGNED NULL,
  disabled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  public_id CHAR(26) NOT NULL UNIQUE,
  firstname VARCHAR(80) NULL,
  lastname VARCHAR(80) NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  mobile VARCHAR(30) NULL,
  country_code VARCHAR(8) NULL,
  password VARCHAR(255) NOT NULL,
  referral_code VARCHAR(40) NULL UNIQUE,
  referred_by BIGINT UNSIGNED NULL,
  status ENUM('active','pending','suspended','closed') NOT NULL DEFAULT 'pending',
  email_verified_at TIMESTAMP NULL,
  mobile_verified_at TIMESTAMP NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  two_factor_secret TEXT NULL,
  kyc_tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
  risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  CONSTRAINT fk_users_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_users_status (status),
  INDEX idx_users_kyc_tier (kyc_tier),
  INDEX idx_users_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 1,
  two_factor_secret TEXT NULL,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(160) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_role_user (
  admin_user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (admin_user_id, role_id),
  CONSTRAINT fk_aru_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_aru_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_permission_role (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_apr_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_apr_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('system','admin','user','provider') NOT NULL DEFAULT 'system',
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(160) NOT NULL,
  resource_type VARCHAR(120) NULL,
  resource_id VARCHAR(120) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_actor (actor_type, actor_id),
  INDEX idx_audit_resource (resource_type, resource_id),
  INDEX idx_audit_action_created (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Security, Devices, OTP, And KYC
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS otp_verifications;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS kyc_documents;
DROP TABLE IF EXISTS kyc_profiles;

CREATE TABLE user_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  device_fingerprint CHAR(64) NOT NULL,
  device_name VARCHAR(160) NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  trusted TINYINT(1) NOT NULL DEFAULT 0,
  last_seen_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_device (user_id, device_fingerprint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE otp_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  channel ENUM('email','sms','whatsapp') NOT NULL,
  purpose VARCHAR(80) NOT NULL,
  destination VARCHAR(190) NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
  expires_at TIMESTAMP NOT NULL,
  verified_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_destination (destination, purpose),
  INDEX idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_type ENUM('user','admin') NOT NULL DEFAULT 'user',
  email VARCHAR(190) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_email (account_type, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  tier TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('not_started','pending','approved','rejected','expired') NOT NULL DEFAULT 'not_started',
  bvn_hash CHAR(64) NULL,
  nin_hash CHAR(64) NULL,
  date_of_birth DATE NULL,
  address_line VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state VARCHAR(120) NULL,
  country VARCHAR(80) DEFAULT 'Nigeria',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  rejection_reason VARCHAR(255) NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_kyc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_kyc_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_kyc_status (status, tier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kyc_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kyc_profile_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('id_card','passport','drivers_license','utility_bill','selfie','other') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_hash CHAR(64) NOT NULL,
  verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  provider_reference VARCHAR(120) NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_kyc_doc_profile FOREIGN KEY (kyc_profile_id) REFERENCES kyc_profiles(id) ON DELETE CASCADE,
  INDEX idx_kyc_docs_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Wallets And Double-Entry Ledger
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS ledger_lines;
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS ledger_accounts;
DROP TABLE IF EXISTS balance_snapshots;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS transaction_limits;
DROP TABLE IF EXISTS idempotency_keys;

CREATE TABLE wallets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  status ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
  available_balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  locked_balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  total_credited DECIMAL(28,8) NOT NULL DEFAULT 0,
  total_debited DECIMAL(28,8) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wallet_user_currency (user_id, currency),
  INDEX idx_wallet_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ledger_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_code VARCHAR(80) NOT NULL UNIQUE,
  owner_type ENUM('system','user','provider') NOT NULL DEFAULT 'system',
  owner_id BIGINT UNSIGNED NULL,
  account_type ENUM('asset','liability','income','expense','equity') NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  name VARCHAR(160) NOT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ledger_accounts_owner (owner_type, owner_id),
  INDEX idx_ledger_accounts_type (account_type, currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ledger_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entry_uuid CHAR(36) NOT NULL UNIQUE,
  reference VARCHAR(120) NOT NULL UNIQUE,
  event_type VARCHAR(100) NOT NULL,
  status ENUM('pending','posted','reversed','void') NOT NULL DEFAULT 'pending',
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  description VARCHAR(255) NULL,
  source_type VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(160) NULL UNIQUE,
  posted_at TIMESTAMP NULL,
  reversed_entry_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_reversal FOREIGN KEY (reversed_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_ledger_entries_status (status, posted_at),
  INDEX idx_ledger_entries_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ledger_lines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ledger_entry_id BIGINT UNSIGNED NOT NULL,
  ledger_account_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('debit','credit') NOT NULL,
  amount DECIMAL(28,8) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ledger_line_entry FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE CASCADE,
  CONSTRAINT fk_ledger_line_account FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT,
  INDEX idx_ledger_lines_account (ledger_account_id),
  INDEX idx_ledger_lines_entry (ledger_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE balance_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wallet_id BIGINT UNSIGNED NOT NULL,
  snapshot_date DATE NOT NULL,
  opening_available DECIMAL(28,8) NOT NULL DEFAULT 0,
  opening_locked DECIMAL(28,8) NOT NULL DEFAULT 0,
  closing_available DECIMAL(28,8) NOT NULL DEFAULT 0,
  closing_locked DECIMAL(28,8) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_balance_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
  UNIQUE KEY uq_wallet_snapshot (wallet_id, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transaction_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  kyc_tier TINYINT UNSIGNED NULL,
  transaction_type ENUM('deposit','withdrawal','transfer','bill_payment') NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  per_transaction_limit DECIMAL(28,8) NOT NULL DEFAULT 0,
  daily_limit DECIMAL(28,8) NOT NULL DEFAULT 0,
  monthly_limit DECIMAL(28,8) NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_limits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_limits_tier_type (kyc_tier, transaction_type),
  INDEX idx_limits_user_type (user_id, transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(160) NOT NULL UNIQUE,
  actor_type ENUM('system','admin','user','provider') NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  request_hash CHAR(64) NOT NULL,
  response_code SMALLINT UNSIGNED NULL,
  response_body JSON NULL,
  locked_until TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_idem_actor (actor_type, actor_id),
  INDEX idx_idem_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Provider Configuration, Webhooks, And Reconciliation
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS reconciliation_items;
DROP TABLE IF EXISTS webhook_events;
DROP TABLE IF EXISTS provider_credentials;
DROP TABLE IF EXISTS providers;

CREATE TABLE providers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_key VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  provider_type ENUM('payment','payout','billing','sms','identity','banking') NOT NULL,
  environment ENUM('test','live') NOT NULL DEFAULT 'test',
  status ENUM('active','inactive','degraded') NOT NULL DEFAULT 'inactive',
  base_url VARCHAR(255) NULL,
  webhook_url VARCHAR(255) NULL,
  health_status ENUM('unknown','operational','degraded','down') NOT NULL DEFAULT 'unknown',
  last_health_check_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_providers_type_status (provider_type, status),
  INDEX idx_providers_environment (environment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_credentials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  credential_key VARCHAR(120) NOT NULL,
  encrypted_value TEXT NOT NULL,
  last_four VARCHAR(12) NULL,
  rotated_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_credentials_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  UNIQUE KEY uq_provider_credential (provider_id, credential_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  event_uuid CHAR(36) NOT NULL UNIQUE,
  provider_event_id VARCHAR(190) NULL,
  event_type VARCHAR(120) NOT NULL,
  signature_valid TINYINT(1) NOT NULL DEFAULT 0,
  replay_detected TINYINT(1) NOT NULL DEFAULT 0,
  payload_hash CHAR(64) NOT NULL,
  payload JSON NOT NULL,
  processing_status ENUM('received','processing','processed','failed','ignored') NOT NULL DEFAULT 'received',
  processed_at TIMESTAMP NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_webhook_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  INDEX idx_webhook_provider_event (provider_id, provider_event_id),
  INDEX idx_webhook_status (processing_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reconciliation_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  item_type ENUM('deposit','withdrawal','bill_payment','transfer','settlement') NOT NULL,
  internal_reference VARCHAR(120) NOT NULL,
  provider_reference VARCHAR(190) NULL,
  expected_amount DECIMAL(28,8) NOT NULL DEFAULT 0,
  provider_amount DECIMAL(28,8) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  status ENUM('pending','matched','mismatch','missing_provider','missing_internal','resolved') NOT NULL DEFAULT 'pending',
  difference_amount DECIMAL(28,8) NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at TIMESTAMP NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_recon_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_recon_admin FOREIGN KEY (resolved_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_recon_status (status, item_type),
  INDEX idx_recon_internal (internal_reference),
  INDEX idx_recon_provider_ref (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Deposits, Withdrawals, Transfers, And Bank Accounts
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS payout_attempts;
DROP TABLE IF EXISTS withdrawals;
DROP TABLE IF EXISTS deposits;
DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS beneficiaries;
DROP TABLE IF EXISTS user_bank_accounts;
DROP TABLE IF EXISTS banks;

CREATE TABLE banks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country CHAR(2) NOT NULL DEFAULT 'NG',
  bank_code VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  provider_key VARCHAR(80) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_country_bank_code (country, bank_code),
  INDEX idx_banks_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_bank_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  bank_id BIGINT UNSIGNED NOT NULL,
  account_number VARCHAR(30) NOT NULL,
  account_name VARCHAR(190) NOT NULL,
  name_match_score DECIMAL(5,2) NULL,
  verification_status ENUM('pending','verified','failed') NOT NULL DEFAULT 'pending',
  provider_reference VARCHAR(190) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_bank_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_bank_bank FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_user_bank_account (user_id, bank_id, account_number),
  INDEX idx_user_bank_verified (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE beneficiaries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  beneficiary_type ENUM('cocopay_user','bank_account','mobile','bill_account') NOT NULL,
  nickname VARCHAR(120) NULL,
  target_user_id BIGINT UNSIGNED NULL,
  bank_account_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_beneficiary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_beneficiary_target_user FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_beneficiary_bank_account FOREIGN KEY (bank_account_id) REFERENCES user_bank_accounts(id) ON DELETE SET NULL,
  INDEX idx_beneficiary_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE deposits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  provider_reference VARCHAR(190) NULL,
  amount DECIMAL(28,8) NOT NULL,
  fee DECIMAL(28,8) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  channel ENUM('manual','paystack','monnify','bank_transfer','card','other') NOT NULL,
  status ENUM('initiated','pending','successful','failed','reversed') NOT NULL DEFAULT 'initiated',
  ledger_entry_id BIGINT UNSIGNED NULL,
  confirmed_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deposit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_deposit_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_deposit_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
  CONSTRAINT fk_deposit_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_deposits_user_status (user_id, status),
  INDEX idx_deposits_provider_ref (provider_id, provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE withdrawals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  bank_account_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(28,8) NOT NULL,
  fee DECIMAL(28,8) NOT NULL DEFAULT 0,
  total_debit DECIMAL(28,8) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  mode ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
  status ENUM('draft','otp_pending','pending_review','approved','processing','settled','failed','reversed','rejected','cancelled') NOT NULL DEFAULT 'draft',
  risk_score SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  maker_id BIGINT UNSIGNED NULL,
  checker_id BIGINT UNSIGNED NULL,
  ledger_lock_entry_id BIGINT UNSIGNED NULL,
  ledger_settlement_entry_id BIGINT UNSIGNED NULL,
  rejection_reason VARCHAR(255) NULL,
  requested_at TIMESTAMP NULL,
  approved_at TIMESTAMP NULL,
  settled_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_withdraw_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_withdraw_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_withdraw_bank FOREIGN KEY (bank_account_id) REFERENCES user_bank_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_withdraw_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
  CONSTRAINT fk_withdraw_maker FOREIGN KEY (maker_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_withdraw_checker FOREIGN KEY (checker_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_withdraw_lock_ledger FOREIGN KEY (ledger_lock_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  CONSTRAINT fk_withdraw_settle_ledger FOREIGN KEY (ledger_settlement_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_withdraw_user_status (user_id, status),
  INDEX idx_withdraw_status_created (status, created_at),
  INDEX idx_withdraw_provider (provider_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payout_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  withdrawal_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  attempt_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  provider_reference VARCHAR(190) NULL,
  status ENUM('queued','sent','successful','failed','unknown') NOT NULL DEFAULT 'queued',
  request_payload JSON NULL,
  response_payload JSON NULL,
  error_message TEXT NULL,
  sent_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payout_withdrawal FOREIGN KEY (withdrawal_id) REFERENCES withdrawals(id) ON DELETE CASCADE,
  CONSTRAINT fk_payout_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_payout_attempt (withdrawal_id, attempt_no),
  INDEX idx_payout_provider_ref (provider_id, provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  sender_wallet_id BIGINT UNSIGNED NOT NULL,
  beneficiary_id BIGINT UNSIGNED NULL,
  receiver_user_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(28,8) NOT NULL,
  fee DECIMAL(28,8) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  status ENUM('initiated','pending','successful','failed','reversed') NOT NULL DEFAULT 'initiated',
  ledger_entry_id BIGINT UNSIGNED NULL,
  narration VARCHAR(255) NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_transfer_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transfer_sender_wallet FOREIGN KEY (sender_wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_transfer_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_transfer_sender_status (sender_user_id, status),
  INDEX idx_transfer_receiver (receiver_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Billing Services
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS bill_orders;
DROP TABLE IF EXISTS bill_products;
DROP TABLE IF EXISTS bill_categories;

CREATE TABLE bill_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_key VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bill_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  provider_product_code VARCHAR(120) NOT NULL,
  name VARCHAR(190) NOT NULL,
  amount_type ENUM('fixed','range','open') NOT NULL DEFAULT 'fixed',
  min_amount DECIMAL(28,8) NULL,
  max_amount DECIMAL(28,8) NULL,
  fixed_amount DECIMAL(28,8) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  commission_type ENUM('flat','percent','none') NOT NULL DEFAULT 'none',
  commission_value DECIMAL(18,8) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bill_product_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_bill_product_category FOREIGN KEY (category_id) REFERENCES bill_categories(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_provider_product (provider_id, provider_product_code),
  INDEX idx_bill_product_category_status (category_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bill_orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  bill_product_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  provider_reference VARCHAR(190) NULL,
  customer_identifier VARCHAR(190) NOT NULL,
  customer_name VARCHAR(190) NULL,
  amount DECIMAL(28,8) NOT NULL,
  fee DECIMAL(28,8) NOT NULL DEFAULT 0,
  commission DECIMAL(28,8) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  status ENUM('initiated','verified','processing','successful','failed','reversed') NOT NULL DEFAULT 'initiated',
  ledger_entry_id BIGINT UNSIGNED NULL,
  verification_payload JSON NULL,
  provider_payload JSON NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bill_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bill_order_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bill_order_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bill_order_product FOREIGN KEY (bill_product_id) REFERENCES bill_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_bill_order_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_bill_order_user_status (user_id, status),
  INDEX idx_bill_order_provider_ref (provider_id, provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Cooperative, Savings, Loans, Notifications, Support, Jobs, And Backups
-- ---------------------------------------------------------------------------

DROP TABLE IF EXISTS backup_runs;
DROP TABLE IF EXISTS scheduled_jobs;
DROP TABLE IF EXISTS support_messages;
DROP TABLE IF EXISTS support_tickets;
DROP TABLE IF EXISTS notification_logs;
DROP TABLE IF EXISTS notification_templates;
DROP TABLE IF EXISTS loan_repayments;
DROP TABLE IF EXISTS loan_disbursements;
DROP TABLE IF EXISTS loan_repayment_schedules;
DROP TABLE IF EXISTS loan_guarantors;
DROP TABLE IF EXISTS loan_collateral;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS cooperative_benefit_claims;
DROP TABLE IF EXISTS cooperative_benefit_programs;
DROP TABLE IF EXISTS cooperative_dues;
DROP TABLE IF EXISTS cooperative_share_transactions;
DROP TABLE IF EXISTS cooperative_share_subscriptions;
DROP TABLE IF EXISTS cooperative_share_products;
DROP TABLE IF EXISTS cooperative_memberships;
DROP TABLE IF EXISTS cooperative_groups;
DROP TABLE IF EXISTS loan_products;
DROP TABLE IF EXISTS savings_plans;

CREATE TABLE cooperative_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  registration_number VARCHAR(120) NULL,
  status ENUM('active','inactive','closed') NOT NULL DEFAULT 'active',
  settings JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_coop_groups_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_memberships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_group_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_no VARCHAR(80) NOT NULL UNIQUE,
  membership_type ENUM('regular','staff','executive','associate') NOT NULL DEFAULT 'regular',
  status ENUM('pending','active','suspended','exited') NOT NULL DEFAULT 'pending',
  joined_at DATE NULL,
  exited_at DATE NULL,
  approved_by BIGINT UNSIGNED NULL,
  approved_at TIMESTAMP NULL,
  contribution_wallet_id BIGINT UNSIGNED NULL,
  share_balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  savings_balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  welfare_balance DECIMAL(28,8) NOT NULL DEFAULT 0,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_coop_member_group FOREIGN KEY (cooperative_group_id) REFERENCES cooperative_groups(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coop_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coop_member_admin FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_coop_member_wallet FOREIGN KEY (contribution_wallet_id) REFERENCES wallets(id) ON DELETE SET NULL,
  UNIQUE KEY uq_coop_user (cooperative_group_id, user_id),
  INDEX idx_coop_members_status (status, membership_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_share_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_group_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  unit_price DECIMAL(28,8) NOT NULL,
  minimum_units INT UNSIGNED NOT NULL DEFAULT 1,
  maximum_units INT UNSIGNED NULL,
  dividend_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_share_product_group FOREIGN KEY (cooperative_group_id) REFERENCES cooperative_groups(id) ON DELETE RESTRICT,
  INDEX idx_share_product_status (cooperative_group_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_share_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  share_product_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  units INT UNSIGNED NOT NULL,
  amount DECIMAL(28,8) NOT NULL,
  status ENUM('pending','active','cancelled','redeemed') NOT NULL DEFAULT 'pending',
  ledger_entry_id BIGINT UNSIGNED NULL,
  subscribed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_share_subscription_member FOREIGN KEY (membership_id) REFERENCES cooperative_memberships(id) ON DELETE RESTRICT,
  CONSTRAINT fk_share_subscription_product FOREIGN KEY (share_product_id) REFERENCES cooperative_share_products(id) ON DELETE RESTRICT,
  CONSTRAINT fk_share_subscription_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_share_subscription_status (membership_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_share_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  transaction_type ENUM('purchase','dividend','redemption','adjustment') NOT NULL,
  units DECIMAL(18,4) NOT NULL DEFAULT 0,
  amount DECIMAL(28,8) NOT NULL DEFAULT 0,
  ledger_entry_id BIGINT UNSIGNED NULL,
  status ENUM('pending','posted','reversed') NOT NULL DEFAULT 'pending',
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_share_tx_member FOREIGN KEY (membership_id) REFERENCES cooperative_memberships(id) ON DELETE RESTRICT,
  CONSTRAINT fk_share_tx_subscription FOREIGN KEY (subscription_id) REFERENCES cooperative_share_subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_share_tx_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_share_tx_member_type (membership_id, transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_dues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  due_type ENUM('monthly_contribution','welfare','levy','penalty','other') NOT NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(28,8) NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('unpaid','part_paid','paid','waived','overdue') NOT NULL DEFAULT 'unpaid',
  paid_amount DECIMAL(28,8) NOT NULL DEFAULT 0,
  ledger_entry_id BIGINT UNSIGNED NULL,
  paid_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_coop_due_member FOREIGN KEY (membership_id) REFERENCES cooperative_memberships(id) ON DELETE RESTRICT,
  CONSTRAINT fk_coop_due_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_coop_dues_status_due (status, due_date),
  INDEX idx_coop_dues_member (membership_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_benefit_programs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_group_id BIGINT UNSIGNED NOT NULL,
  benefit_key VARCHAR(80) NOT NULL,
  name VARCHAR(160) NOT NULL,
  benefit_type ENUM('dividend','welfare_grant','emergency_support','insurance','rebate','education_support') NOT NULL,
  eligibility_rule JSON NULL,
  max_amount DECIMAL(28,8) NULL,
  requires_approval TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_benefit_program_group FOREIGN KEY (cooperative_group_id) REFERENCES cooperative_groups(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_group_benefit (cooperative_group_id, benefit_key),
  INDEX idx_benefit_program_status (status, benefit_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cooperative_benefit_claims (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  membership_id BIGINT UNSIGNED NOT NULL,
  benefit_program_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  requested_amount DECIMAL(28,8) NOT NULL,
  approved_amount DECIMAL(28,8) NULL,
  status ENUM('draft','submitted','under_review','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'draft',
  approved_by BIGINT UNSIGNED NULL,
  approved_at TIMESTAMP NULL,
  ledger_entry_id BIGINT UNSIGNED NULL,
  reason TEXT NULL,
  rejection_reason VARCHAR(255) NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_benefit_claim_member FOREIGN KEY (membership_id) REFERENCES cooperative_memberships(id) ON DELETE RESTRICT,
  CONSTRAINT fk_benefit_claim_program FOREIGN KEY (benefit_program_id) REFERENCES cooperative_benefit_programs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_benefit_claim_admin FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  CONSTRAINT fk_benefit_claim_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_benefit_claim_status (status, created_at),
  INDEX idx_benefit_claim_member (membership_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE savings_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_type ENUM('dps','fdr','target') NOT NULL,
  name VARCHAR(160) NOT NULL,
  interest_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
  min_amount DECIMAL(28,8) NOT NULL DEFAULT 0,
  max_amount DECIMAL(28,8) NULL,
  tenure_days INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cooperative_group_id BIGINT UNSIGNED NULL,
  product_code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  product_type ENUM('personal','salary_advance','cooperative','asset_finance','emergency','business') NOT NULL DEFAULT 'cooperative',
  interest_method ENUM('flat','reducing_balance','interest_free') NOT NULL DEFAULT 'flat',
  interest_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
  min_principal DECIMAL(28,8) NOT NULL DEFAULT 0,
  max_principal DECIMAL(28,8) NULL,
  min_tenure_days INT UNSIGNED NOT NULL DEFAULT 30,
  max_tenure_days INT UNSIGNED NOT NULL DEFAULT 365,
  guarantors_required TINYINT UNSIGNED NOT NULL DEFAULT 0,
  collateral_required TINYINT(1) NOT NULL DEFAULT 0,
  requires_membership TINYINT(1) NOT NULL DEFAULT 1,
  eligibility_rule JSON NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_product_group FOREIGN KEY (cooperative_group_id) REFERENCES cooperative_groups(id) ON DELETE SET NULL,
  INDEX idx_loan_product_type_status (product_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  cooperative_membership_id BIGINT UNSIGNED NULL,
  loan_product_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  principal DECIMAL(28,8) NOT NULL,
  interest DECIMAL(28,8) NOT NULL DEFAULT 0,
  total_payable DECIMAL(28,8) NOT NULL,
  outstanding_amount DECIMAL(28,8) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NGN',
  purpose VARCHAR(255) NULL,
  tenure_days INT UNSIGNED NULL,
  repayment_frequency ENUM('daily','weekly','monthly','bullet') NOT NULL DEFAULT 'monthly',
  status ENUM('draft','pending','guarantor_review','committee_review','approved','disbursed','rejected','closed','defaulted','written_off') NOT NULL DEFAULT 'draft',
  approved_by BIGINT UNSIGNED NULL,
  approved_at TIMESTAMP NULL,
  disbursed_at TIMESTAMP NULL,
  due_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_loan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_loan_membership FOREIGN KEY (cooperative_membership_id) REFERENCES cooperative_memberships(id) ON DELETE SET NULL,
  CONSTRAINT fk_loan_product FOREIGN KEY (loan_product_id) REFERENCES loan_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_loan_admin FOREIGN KEY (approved_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_loans_user_status (user_id, status),
  INDEX idx_loans_product_status (loan_product_id, status),
  INDEX idx_loans_membership_status (cooperative_membership_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_guarantors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  guarantor_membership_id BIGINT UNSIGNED NULL,
  guarantor_user_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NULL,
  mobile VARCHAR(30) NULL,
  email VARCHAR(190) NULL,
  guarantee_amount DECIMAL(28,8) NOT NULL DEFAULT 0,
  status ENUM('invited','accepted','declined','released') NOT NULL DEFAULT 'invited',
  accepted_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_guarantor_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  CONSTRAINT fk_guarantor_membership FOREIGN KEY (guarantor_membership_id) REFERENCES cooperative_memberships(id) ON DELETE SET NULL,
  CONSTRAINT fk_guarantor_user FOREIGN KEY (guarantor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_guarantor_status (loan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_collateral (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  collateral_type ENUM('shares','savings_balance','asset','document','other') NOT NULL,
  description VARCHAR(255) NOT NULL,
  estimated_value DECIMAL(28,8) NOT NULL DEFAULT 0,
  file_path VARCHAR(255) NULL,
  status ENUM('pending','verified','released','liquidated','rejected') NOT NULL DEFAULT 'pending',
  verified_by BIGINT UNSIGNED NULL,
  verified_at TIMESTAMP NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_collateral_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  CONSTRAINT fk_collateral_admin FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_collateral_status (loan_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_repayment_schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  installment_no INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  principal_due DECIMAL(28,8) NOT NULL DEFAULT 0,
  interest_due DECIMAL(28,8) NOT NULL DEFAULT 0,
  fees_due DECIMAL(28,8) NOT NULL DEFAULT 0,
  amount_due DECIMAL(28,8) NOT NULL,
  amount_paid DECIMAL(28,8) NOT NULL DEFAULT 0,
  status ENUM('pending','part_paid','paid','overdue','waived') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_schedule_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  UNIQUE KEY uq_loan_installment (loan_id, installment_no),
  INDEX idx_schedule_due_status (due_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_disbursements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  wallet_id BIGINT UNSIGNED NOT NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(28,8) NOT NULL,
  ledger_entry_id BIGINT UNSIGNED NULL,
  status ENUM('pending','posted','failed','reversed') NOT NULL DEFAULT 'pending',
  disbursed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_disbursement_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE RESTRICT,
  CONSTRAINT fk_disbursement_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_disbursement_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL,
  INDEX idx_disbursement_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_repayments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id BIGINT UNSIGNED NOT NULL,
  repayment_schedule_id BIGINT UNSIGNED NULL,
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(28,8) NOT NULL,
  principal_component DECIMAL(28,8) NOT NULL DEFAULT 0,
  interest_component DECIMAL(28,8) NOT NULL DEFAULT 0,
  fee_component DECIMAL(28,8) NOT NULL DEFAULT 0,
  ledger_entry_id BIGINT UNSIGNED NULL,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_repayment_loan FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  CONSTRAINT fk_repayment_schedule FOREIGN KEY (repayment_schedule_id) REFERENCES loan_repayment_schedules(id) ON DELETE SET NULL,
  CONSTRAINT fk_repayment_ledger FOREIGN KEY (ledger_entry_id) REFERENCES ledger_entries(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(120) NOT NULL UNIQUE,
  channel ENUM('email','sms','push','in_app') NOT NULL,
  subject VARCHAR(190) NULL,
  body TEXT NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  template_key VARCHAR(120) NULL,
  channel ENUM('email','sms','push','in_app') NOT NULL,
  destination VARCHAR(190) NULL,
  status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  message TEXT NULL,
  provider_response JSON NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_notification_status (status, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  ticket_no VARCHAR(40) NOT NULL UNIQUE,
  subject VARCHAR(190) NOT NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
  assigned_admin_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_ticket_admin FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
  INDEX idx_ticket_status (status, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  support_ticket_id BIGINT UNSIGNED NOT NULL,
  sender_type ENUM('user','admin') NOT NULL,
  sender_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  attachments JSON NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_message_ticket FOREIGN KEY (support_ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduled_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_key VARCHAR(120) NOT NULL UNIQUE,
  expression VARCHAR(80) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at TIMESTAMP NULL,
  next_run_at TIMESTAMP NULL,
  last_status ENUM('never','success','failed') NOT NULL DEFAULT 'never',
  last_error TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  backup_uuid CHAR(36) NOT NULL UNIQUE,
  backup_type ENUM('database','files','full') NOT NULL,
  storage_disk VARCHAR(80) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size BIGINT UNSIGNED NULL,
  checksum CHAR(64) NULL,
  status ENUM('running','successful','failed') NOT NULL DEFAULT 'running',
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_backup_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed Data
-- ---------------------------------------------------------------------------

INSERT INTO roles (name, description) VALUES
('Super Admin', 'Full system access'),
('Operations Manager', 'Money movement, reconciliation, and support oversight'),
('Compliance Officer', 'KYC, risk, and audit oversight');

INSERT INTO permissions (permission_key, description) VALUES
('users.view', 'View users'),
('wallets.view', 'View wallets'),
('deposits.manage', 'Manage deposits'),
('withdrawals.manage', 'Manage withdrawals'),
('withdrawals.approve_large', 'Approve high-value withdrawals'),
('billing.manage', 'Manage billing products and orders'),
('cooperative.manage', 'Manage cooperative groups and memberships'),
('cooperative.benefits.manage', 'Manage cooperative member benefits'),
('cooperative.dues.manage', 'Manage dues, levies, and member contributions'),
('loans.manage', 'Manage loan products and applications'),
('loans.approve', 'Approve cooperative loan applications'),
('providers.manage', 'Manage provider configuration'),
('settings.manage', 'Manage system settings'),
('audit.view', 'View audit logs'),
('backup.manage', 'Manage backups');

INSERT INTO app_settings (setting_key, setting_value, is_sensitive) VALUES
('site', JSON_OBJECT('name','Cocopay','currency','NGN','timezone','Africa/Lagos'), 0),
('withdrawals', JSON_OBJECT('mode','manual','maker_checker_threshold','100000.00'), 0),
('cooperative', JSON_OBJECT('enabled',true,'default_group_code','COCOPAY-COOP','require_membership_for_loans',true,'minimum_share_units',1), 0),
('loans', JSON_OBJECT('enabled',true,'committee_review_threshold','500000.00','allow_wallet_disbursement',true,'allow_auto_repayment',true), 0),
('cron', JSON_OBJECT('secret','REPLACE_WITH_LONG_RANDOM_SECRET'), 1),
('backup', JSON_OBJECT('enabled',true,'frequency','daily','include_database',true,'include_files',true), 0);

INSERT INTO feature_flags (flag_key, enabled, description) VALUES
('deposits.enabled', 1, 'Allow deposits'),
('withdrawals.enabled', 1, 'Allow withdrawals'),
('transfers.enabled', 1, 'Allow wallet transfers'),
('billing.enabled', 1, 'Allow bill payments'),
('cooperative.enabled', 1, 'Allow cooperative membership and benefits'),
('cooperative.shares.enabled', 1, 'Allow member share subscriptions and dividends'),
('cooperative.dues.enabled', 1, 'Allow dues, levies, and member contribution tracking'),
('loans.enabled', 1, 'Allow loan applications and repayments'),
('automatic_payouts.enabled', 0, 'Allow automatic payouts');

INSERT INTO emergency_controls (control_key, enabled, reason) VALUES
('pause_deposits', 0, NULL),
('pause_withdrawals', 0, NULL),
('pause_transfers', 0, NULL),
('pause_billing', 0, NULL),
('pause_cooperative', 0, NULL),
('pause_loans', 0, NULL),
('maintenance_mode', 0, NULL);

INSERT INTO providers (provider_key, name, provider_type, environment, status, health_status) VALUES
('paystack', 'Paystack', 'payment', 'test', 'inactive', 'unknown'),
('monnify', 'Monnify', 'payment', 'test', 'inactive', 'unknown'),
('vtu_ng', 'VTU.ng', 'billing', 'test', 'inactive', 'unknown'),
('airtimepay', 'AirtimePay', 'billing', 'test', 'inactive', 'unknown'),
('beem_africa', 'Beem Africa', 'billing', 'test', 'inactive', 'unknown'),
('twilio', 'Twilio', 'sms', 'test', 'inactive', 'unknown'),
('vonage', 'Vonage', 'sms', 'test', 'inactive', 'unknown'),
('messagebird', 'MessageBird', 'sms', 'test', 'inactive', 'unknown');

INSERT INTO bill_categories (category_key, name, status) VALUES
('airtime', 'Airtime', 'active'),
('data', 'Mobile Data', 'active'),
('electricity', 'Electricity', 'active'),
('cable_tv', 'Cable TV', 'active'),
('internet', 'Internet', 'active');

INSERT INTO banks (country, bank_code, name, active) VALUES
('NG', '058', 'Guaranty Trust Bank', 1),
('NG', '044', 'Access Bank', 1),
('NG', '033', 'United Bank for Africa', 1),
('NG', '057', 'Zenith Bank', 1),
('NG', '011', 'First Bank of Nigeria', 1);

INSERT INTO cooperative_groups (group_code, name, description, status, settings) VALUES
('COCOPAY-COOP', 'Cocopay Members Cooperative', 'Default cooperative group for member savings, loans, dividends, welfare, and benefits.', 'active',
 JSON_OBJECT('monthly_contribution','5000.00','welfare_contribution','1000.00','loan_multiplier',3,'dividend_frequency','annual'));

INSERT INTO cooperative_share_products (cooperative_group_id, name, unit_price, minimum_units, maximum_units, dividend_rate, status)
SELECT id, 'Standard Member Shares', 1000.00, 1, NULL, 8.0000, 'active'
FROM cooperative_groups
WHERE group_code = 'COCOPAY-COOP';

INSERT INTO cooperative_benefit_programs (cooperative_group_id, benefit_key, name, benefit_type, eligibility_rule, max_amount, requires_approval, status)
SELECT id, 'annual_dividend', 'Annual Member Dividend', 'dividend',
       JSON_OBJECT('requires_active_membership', true, 'minimum_months_active', 6), NULL, 1, 'active'
FROM cooperative_groups
WHERE group_code = 'COCOPAY-COOP';

INSERT INTO cooperative_benefit_programs (cooperative_group_id, benefit_key, name, benefit_type, eligibility_rule, max_amount, requires_approval, status)
SELECT id, 'emergency_support', 'Emergency Member Support', 'emergency_support',
       JSON_OBJECT('requires_active_membership', true, 'minimum_contribution_score', 70), 100000.00, 1, 'active'
FROM cooperative_groups
WHERE group_code = 'COCOPAY-COOP';

INSERT INTO loan_products (cooperative_group_id, product_code, name, product_type, interest_method, interest_rate, min_principal, max_principal, min_tenure_days, max_tenure_days, guarantors_required, collateral_required, requires_membership, eligibility_rule, status)
SELECT id, 'COOP-PERSONAL', 'Cooperative Personal Loan', 'cooperative', 'reducing_balance', 12.0000, 10000.00, 1000000.00, 30, 365, 2, 0, 1,
       JSON_OBJECT('minimum_months_active', 3, 'minimum_share_units', 1, 'max_multiplier_of_savings', 3), 'active'
FROM cooperative_groups
WHERE group_code = 'COCOPAY-COOP';

INSERT INTO loan_products (cooperative_group_id, product_code, name, product_type, interest_method, interest_rate, min_principal, max_principal, min_tenure_days, max_tenure_days, guarantors_required, collateral_required, requires_membership, eligibility_rule, status)
SELECT id, 'COOP-EMERGENCY', 'Emergency Member Loan', 'emergency', 'flat', 5.0000, 5000.00, 200000.00, 7, 90, 1, 0, 1,
       JSON_OBJECT('minimum_months_active', 1, 'requires_no_defaulted_loan', true), 'active'
FROM cooperative_groups
WHERE group_code = 'COCOPAY-COOP';

INSERT INTO ledger_accounts (account_code, owner_type, account_type, currency, name) VALUES
('SYS:CASH:NGN', 'system', 'asset', 'NGN', 'System Cash NGN'),
('SYS:WALLET_LIABILITY:NGN', 'system', 'liability', 'NGN', 'Customer Wallet Liability NGN'),
('SYS:FEE_INCOME:NGN', 'system', 'income', 'NGN', 'Fee Income NGN'),
('SYS:BILLING_CLEARING:NGN', 'system', 'asset', 'NGN', 'Billing Clearing NGN'),
('SYS:PAYOUT_CLEARING:NGN', 'system', 'asset', 'NGN', 'Payout Clearing NGN'),
('SYS:COOP_SHARE_CAPITAL:NGN', 'system', 'equity', 'NGN', 'Cooperative Share Capital NGN'),
('SYS:COOP_DUES_RECEIVABLE:NGN', 'system', 'asset', 'NGN', 'Cooperative Dues Receivable NGN'),
('SYS:COOP_WELFARE_FUND:NGN', 'system', 'liability', 'NGN', 'Cooperative Welfare Fund NGN'),
('SYS:LOAN_PORTFOLIO:NGN', 'system', 'asset', 'NGN', 'Loan Portfolio NGN'),
('SYS:LOAN_INTEREST_INCOME:NGN', 'system', 'income', 'NGN', 'Loan Interest Income NGN');

INSERT INTO scheduled_jobs (job_key, expression, enabled) VALUES
('process_pending_webhooks', '*/2 * * * *', 1),
('reconcile_providers', '*/15 * * * *', 1),
('expire_otps', '*/5 * * * *', 1),
('run_daily_backup', '0 2 * * *', 1),
('wallet_balance_snapshot', '55 23 * * *', 1),
('generate_monthly_cooperative_dues', '0 1 1 * *', 1),
('mark_overdue_loan_installments', '0 3 * * *', 1),
('auto_collect_due_loan_repayments', '*/30 * * * *', 1),
('calculate_cooperative_dividends', '0 4 31 12 *', 0);

SET FOREIGN_KEY_CHECKS = 1;

-- End of Cocopay fresh implementation schema.
