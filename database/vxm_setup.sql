-- ============================================================
-- VXM FULL DATABASE SETUP — XAMPP / phpMyAdmin safe
-- ============================================================
-- How to import:
--   1. In phpMyAdmin, click the "vxm" database on the left
--      (create it first if needed: New → database name: vxm → Create)
--   2. Click Import → Choose this file → Go
-- ============================================================
-- This file does NOT touch information_schema.
-- Safe for a fresh empty vxm database.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `referral_code` VARCHAR(20) NOT NULL,
  `referred_by` VARCHAR(20) DEFAULT NULL COMMENT 'Referral code of referrer',
  `level_id` INT UNSIGNED DEFAULT NULL,
  `wallet_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_earnings` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_withdrawals` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_deposits` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `email_verified_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_referral_code` (`referral_code`),
  KEY `idx_users_level` (`level_id`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_referred_by` (`referred_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LEVELS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `levels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) DEFAULT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `daily_tasks` INT UNSIGNED NOT NULL DEFAULT 0,
  `referral_bonus` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `benefits` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_levels_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TASKS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `reward` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `task_type` VARCHAR(50) DEFAULT 'general',
  `external_url` VARCHAR(500) DEFAULT NULL,
  `verification_required` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_level` (`level_id`),
  KEY `idx_tasks_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- USER_TASKS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `task_id` INT UNSIGNED NOT NULL,
  `reward_earned` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tasks_user` (`user_id`),
  KEY `idx_user_tasks_task` (`task_id`),
  KEY `idx_user_tasks_date` (`user_id`, `completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- EARNINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `earnings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `earning_type` VARCHAR(50) NOT NULL DEFAULT 'task',
  `description` VARCHAR(255) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_earnings_user` (`user_id`),
  KEY `idx_earnings_type` (`earning_type`),
  KEY `idx_earnings_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TRANSACTIONS (ledger)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM(
    'deposit',
    'withdrawal',
    'task_reward',
    'referral_bonus',
    'level_purchase',
    'adjustment',
    'refund'
  ) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'Positive=credit, Negative=debit',
  `balance_before` DECIMAL(14,2) NOT NULL,
  `balance_after` DECIMAL(14,2) NOT NULL,
  `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
  `reference` VARCHAR(100) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `related_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`),
  KEY `idx_tx_type` (`type`),
  KEY `idx_tx_status` (`status`),
  KEY `idx_tx_created` (`created_at`),
  KEY `idx_tx_reference` (`reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DEPOSITS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `status` ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `mpesa_checkout_request_id` VARCHAR(100) DEFAULT NULL,
  `mpesa_merchant_request_id` VARCHAR(100) DEFAULT NULL,
  `mpesa_receipt` VARCHAR(50) DEFAULT NULL,
  `mpesa_result_code` VARCHAR(20) DEFAULT NULL,
  `mpesa_result_desc` VARCHAR(255) DEFAULT NULL,
  `callback_raw` JSON DEFAULT NULL,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deposits_user` (`user_id`),
  KEY `idx_deposits_status` (`status`),
  KEY `idx_deposits_checkout` (`mpesa_checkout_request_id`),
  KEY `idx_deposits_receipt` (`mpesa_receipt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- REFERRALS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_user_id` INT UNSIGNED NOT NULL,
  `bonus` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending','qualified','paid','cancelled') NOT NULL DEFAULT 'pending',
  `qualified_at` DATETIME DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_referral_pair` (`referrer_id`, `referred_user_id`),
  KEY `idx_referrals_referrer` (`referrer_id`),
  KEY `idx_referrals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- WITHDRAWALS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `status` ENUM('pending','processing','approved','rejected','failed','cancelled') NOT NULL DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `processed_by` INT UNSIGNED DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `mpesa_receipt` VARCHAR(50) DEFAULT NULL,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_withdrawals_user` (`user_id`),
  KEY `idx_withdrawals_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- PASSWORD RESETS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reset_token` (`token`),
  KEY `idx_reset_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SUPPORT TICKETS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'general',
  `status` ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SUPPORT MESSAGES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LOGIN ATTEMPTS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`, `attempted_at`),
  KEY `idx_attempts_ip` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SETTINGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- FOREIGN KEYS (plain ALTER — no information_schema)
-- Run only on a fresh empty database so these succeed once.
-- ------------------------------------------------------------
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_level`
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_level`
  FOREIGN KEY (`level_id`) REFERENCES `levels`(`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `user_tasks`
  ADD CONSTRAINT `fk_user_tasks_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `user_tasks`
  ADD CONSTRAINT `fk_user_tasks_task`
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `earnings`
  ADD CONSTRAINT `fk_earnings_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `deposits`
  ADD CONSTRAINT `fk_deposits_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_referrer`
  FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_referred`
  FOREIGN KEY (`referred_user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_withdrawals_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_tickets_user`
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `support_messages`
  ADD CONSTRAINT `fk_messages_ticket`
  FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DEV / TEST SEED DATA (local only — not production rules)
-- ============================================================

INSERT INTO `levels` (`name`, `slug`, `price`, `daily_tasks`, `referral_bonus`, `description`, `sort_order`, `status`)
SELECT 'Starter', 'starter', 500.00, 5, 25.00, 'Entry level. Target daily earnings around KES 20 through tasks.', 1, 'active'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `levels` LIMIT 1);

INSERT INTO `levels` (`name`, `slug`, `price`, `daily_tasks`, `referral_bonus`, `description`, `sort_order`, `status`)
SELECT 'Growth', 'growth', 1500.00, 10, 75.00, 'Mid level. Target daily earnings around KES 60 through tasks.', 2, 'active'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `levels` WHERE `slug` = 'growth' LIMIT 1);

INSERT INTO `levels` (`name`, `slug`, `price`, `daily_tasks`, `referral_bonus`, `description`, `sort_order`, `status`)
SELECT 'Pro', 'pro', 3500.00, 15, 175.00, 'Top level. Target daily earnings around KES 140 through tasks.', 3, 'active'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `levels` WHERE `slug` = 'pro' LIMIT 1);

-- Sample tasks (only if none exist)
INSERT INTO `tasks` (`level_id`, `title`, `description`, `reward`, `task_type`, `status`)
SELECT l.id, 'Watch daily reel', 'Open and watch the assigned reel content.', 4.00, 'general', 'active'
FROM `levels` l WHERE l.slug = 'starter'
  AND NOT EXISTS (SELECT 1 FROM `tasks` LIMIT 1);

INSERT INTO `tasks` (`level_id`, `title`, `description`, `reward`, `task_type`, `status`)
SELECT l.id, 'Complete short survey', 'Answer a short survey honestly.', 4.00, 'general', 'active'
FROM `levels` l WHERE l.slug = 'starter'
  AND (SELECT COUNT(*) FROM `tasks`) < 2;

INSERT INTO `tasks` (`level_id`, `title`, `description`, `reward`, `task_type`, `status`)
SELECT l.id, 'Visit partner page', 'Visit the partner link for at least 30 seconds.', 6.00, 'general', 'active'
FROM `levels` l WHERE l.slug = 'growth'
  AND (SELECT COUNT(*) FROM `tasks`) < 3;

INSERT INTO `tasks` (`level_id`, `title`, `description`, `reward`, `task_type`, `status`)
SELECT l.id, 'Share daily update', 'Share the daily update on your social channel.', 6.00, 'general', 'active'
FROM `levels` l WHERE l.slug = 'growth'
  AND (SELECT COUNT(*) FROM `tasks`) < 4;

INSERT INTO `tasks` (`level_id`, `title`, `description`, `reward`, `task_type`, `status`)
SELECT l.id, 'Pro research task', 'Complete the assigned research checklist.', 10.00, 'general', 'active'
FROM `levels` l WHERE l.slug = 'pro'
  AND (SELECT COUNT(*) FROM `tasks`) < 5;

-- Admin (DEV): admin@vxm.local / Admin@123
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `referral_code`, `status`, `is_admin`, `wallet_balance`)
SELECT 'VXM Admin', 'admin@vxm.local', '254700000001',
       '$2y$10$Gen69/32PQYCfmLbwMy3eOJgneTiRPHwwta6Rx57.zIgAG4agL0ha',
       'ADMIN001', 'active', 1, 0.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'admin@vxm.local' LIMIT 1);

-- Test user (DEV): user@vxm.local / User@123 — wallet 5000 for easy testing
INSERT INTO `users` (`full_name`, `email`, `phone`, `password`, `referral_code`, `status`, `is_admin`, `wallet_balance`, `total_deposits`)
SELECT 'Test User', 'user@vxm.local', '254700000002',
       '$2y$10$IvSdJP7BBgOqX/vH8AYGz.7ZzKdesVrbYycSbgrkYPEVfVdNDFIvC',
       'USER0001', 'active', 0, 5000.00, 5000.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `email` = 'user@vxm.local' LIMIT 1);
