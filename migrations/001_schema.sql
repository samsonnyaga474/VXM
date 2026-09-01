-- ============================================================
-- VXM Complete Database Schema
-- Migration 001 - Formalize & extend existing structure
-- Safe to run on existing databases (uses IF NOT EXISTS)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
  `benefits` TEXT DEFAULT NULL COMMENT 'JSON or plain text',
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
  `task_type` VARCHAR(50) DEFAULT 'general' COMMENT 'general, survey, social, etc.',
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
-- USER_TASKS (completion log)
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
  KEY `idx_user_tasks_date` (`user_id`, `completed_at`),
  UNIQUE KEY `uk_user_task_day` (`user_id`, `task_id`, `completed_at`) -- Note: adjust if pure daily uniqueness needed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- EARNINGS (legacy + still used)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `earnings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `earning_type` VARCHAR(50) NOT NULL DEFAULT 'task' COMMENT 'task, referral, bonus, adjustment',
  `description` VARCHAR(255) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'task_id, referral_id, etc.',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_earnings_user` (`user_id`),
  KEY `idx_earnings_type` (`earning_type`),
  KEY `idx_earnings_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TRANSACTIONS LEDGER (new - source of truth for money movement)
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
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'Positive = credit, Negative = debit',
  `balance_before` DECIMAL(14,2) NOT NULL,
  `balance_after` DECIMAL(14,2) NOT NULL,
  `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'completed',
  `reference` VARCHAR(100) DEFAULT NULL COMMENT 'External ref (M-Pesa receipt, etc.)',
  `description` VARCHAR(255) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `related_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'withdrawal_id, deposit_id, task_id...',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT 'Admin id if manual',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user` (`user_id`),
  KEY `idx_tx_type` (`type`),
  KEY `idx_tx_status` (`status`),
  KEY `idx_tx_reference` (`reference`),
  KEY `idx_tx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- DEPOSITS (M-Pesa top-ups)
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
  `phone` VARCHAR(20) NOT NULL COMMENT 'M-Pesa phone number',
  `status` ENUM('pending','processing','approved','rejected','failed','cancelled') NOT NULL DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `processed_by` INT UNSIGNED DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `mpesa_receipt` VARCHAR(50) DEFAULT NULL,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Ledger entry that debited wallet',
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
  `status` ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `closed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` BIGINT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL COMMENT 'NULL if admin system message',
  `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- LOGIN ATTEMPTS (rate limiting)
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
-- SETTINGS (key-value)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- SEED DEFAULT LEVELS (only if empty)
-- ------------------------------------------------------------
INSERT INTO `levels` (`name`, `slug`, `price`, `daily_tasks`, `referral_bonus`, `description`, `sort_order`, `status`)
SELECT * FROM (
  SELECT 'Starter' AS name, 'starter' AS slug, 500.00 AS price, 5 AS daily_tasks, 50.00 AS referral_bonus,
         'Entry level. Complete simple daily tasks and start earning.' AS description, 1 AS sort_order, 'active' AS status
  UNION ALL
  SELECT 'Growth', 'growth', 1500.00, 10, 150.00, 'More tasks and higher rewards for active earners.', 2, 'active'
  UNION ALL
  SELECT 'Pro', 'pro', 3500.00, 20, 350.00, 'Maximum daily capacity and strongest referral bonus.', 3, 'active'
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM levels LIMIT 1);

SET FOREIGN_KEY_CHECKS = 1;
