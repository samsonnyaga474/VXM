-- ============================================================
-- VXM Migration 004 — XP / Points system
-- Non-destructive. Safe to run after previous migrations.
-- XP is a progress metric SEPARATE from wallet money.
-- No automatic conversion of XP to KSh is defined.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1) users.xp column (accumulated XP / points)
-- ------------------------------------------------------------
SET @exist := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND column_name = 'xp'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE users ADD COLUMN xp INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_deposits',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) tasks.xp_reward column
-- ------------------------------------------------------------
SET @exist := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'tasks'
    AND column_name = 'xp_reward'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE tasks ADD COLUMN xp_reward INT UNSIGNED NOT NULL DEFAULT 0 AFTER reward',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3) xp_ledger — audit trail for all XP changes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `xp_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` INT NOT NULL COMMENT 'Positive = credit, Negative = debit',
  `balance_after` INT UNSIGNED NOT NULL DEFAULT 0,
  `source` VARCHAR(50) NOT NULL DEFAULT 'task' COMMENT 'task, bonus, adjustment, referral, etc.',
  `reference_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'task_id, etc.',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_xp_ledger_user` (`user_id`),
  KEY `idx_xp_ledger_source` (`source`),
  KEY `idx_xp_ledger_created` (`created_at`),
  KEY `idx_xp_ledger_ref` (`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional FK (non-blocking if orphans exist)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'xp_ledger'
    AND constraint_name = 'fk_xp_ledger_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE xp_ledger ADD CONSTRAINT fk_xp_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
