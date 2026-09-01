-- ============================================================
-- VXM Migration 002 — Database integrity constraints
-- Non-destructive. Safe to run after 001_schema.sql.
--
-- MySQL 5.7.6+ recommended (generated STORED columns).
-- MySQL 8.0+ fully supported.
--
-- Before running on a database with existing rows:
--   1. Backup the database.
--   2. Optionally check for orphaned rows (see notes at bottom).
--   3. If ALTER fails on FK due to orphans, clean data first.
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1) user_tasks: one completion per user + task per calendar day
-- ------------------------------------------------------------
-- Problem with 001:
--   UNIQUE (user_id, task_id, completed_at) uses full DATETIME,
--   so two completions on the same day with different timestamps
--   are still allowed at the DB layer.
--
-- Approach:
--   Add a STORED generated column completion_date = DATE(completed_at)
--   and UNIQUE (user_id, task_id, completion_date).
--   Compatible with MySQL 5.7.6+ / MariaDB 10.2+.
--
-- Application code already re-checks under FOR UPDATE; this is defense in depth.

-- Drop the weak unique if present (ignore error if already gone on re-run).
-- MySQL does not support IF EXISTS for DROP INDEX on all versions; use procedure-style checks.

SET @exist := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user_tasks'
    AND index_name = 'uk_user_task_day'
);
SET @sql := IF(@exist > 0,
  'ALTER TABLE user_tasks DROP INDEX uk_user_task_day',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add generated column if missing
SET @exist := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'user_tasks'
    AND column_name = 'completion_date'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE user_tasks ADD COLUMN completion_date DATE
     GENERATED ALWAYS AS (DATE(completed_at)) STORED',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Unique per user/task/day
SET @exist := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'user_tasks'
    AND index_name = 'uk_user_task_day_date'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE user_tasks ADD UNIQUE KEY uk_user_task_day_date (user_id, task_id, completion_date)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) deposits: unique CheckoutRequestID when present
-- ------------------------------------------------------------
-- MySQL UNIQUE allows multiple NULLs (InnoDB), so pending rows
-- without a checkout id remain valid. Duplicate non-NULL ids blocked.

SET @exist := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'deposits'
    AND index_name = 'uk_deposits_checkout'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE deposits ADD UNIQUE KEY uk_deposits_checkout (mpesa_checkout_request_id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3) Foreign keys (only where relationships are clear and nullable-safe)
-- ------------------------------------------------------------
-- ON DELETE:
--   SET NULL  — optional references (level_id)
--   CASCADE   — dependent child rows that must not outlive parent
--   RESTRICT  — financial history must not disappear with a careless delete
--
-- users.referred_by stores a CODE string, not users.id — NO FK possible.
-- login_attempts has no user_id — NO FK.
-- settings is key-value — NO FK.
-- transactions.related_id is polymorphic — NO FK.
-- earnings.reference_id is polymorphic — NO FK.
-- withdrawals.processed_by / transactions.created_by — optional admin ids;
--   FK to users with SET NULL is reasonable but omitted if admins may be purged.
-- deposits/withdrawals/referrals.transaction_id → transactions:
--   optional; ON DELETE SET NULL.

-- Helper pattern repeated below: add constraint only if missing.

-- users.level_id → levels.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'users'
    AND constraint_name = 'fk_users_level'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE users
     ADD CONSTRAINT fk_users_level
     FOREIGN KEY (level_id) REFERENCES levels(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tasks.level_id → levels.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'tasks'
    AND constraint_name = 'fk_tasks_level'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE tasks
     ADD CONSTRAINT fk_tasks_level
     FOREIGN KEY (level_id) REFERENCES levels(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- user_tasks.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'user_tasks'
    AND constraint_name = 'fk_user_tasks_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE user_tasks
     ADD CONSTRAINT fk_user_tasks_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- user_tasks.task_id → tasks.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'user_tasks'
    AND constraint_name = 'fk_user_tasks_task'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE user_tasks
     ADD CONSTRAINT fk_user_tasks_task
     FOREIGN KEY (task_id) REFERENCES tasks(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- earnings.user_id → users.id (RESTRICT: keep financial history)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'earnings'
    AND constraint_name = 'fk_earnings_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE earnings
     ADD CONSTRAINT fk_earnings_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- transactions.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND constraint_name = 'fk_transactions_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE transactions
     ADD CONSTRAINT fk_transactions_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- deposits.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'deposits'
    AND constraint_name = 'fk_deposits_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE deposits
     ADD CONSTRAINT fk_deposits_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- deposits.transaction_id → transactions.id (optional)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'deposits'
    AND constraint_name = 'fk_deposits_transaction'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE deposits
     ADD CONSTRAINT fk_deposits_transaction
     FOREIGN KEY (transaction_id) REFERENCES transactions(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- referrals.referrer_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'referrals'
    AND constraint_name = 'fk_referrals_referrer'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE referrals
     ADD CONSTRAINT fk_referrals_referrer
     FOREIGN KEY (referrer_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- referrals.referred_user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'referrals'
    AND constraint_name = 'fk_referrals_referred'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE referrals
     ADD CONSTRAINT fk_referrals_referred
     FOREIGN KEY (referred_user_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- referrals.transaction_id → transactions.id (optional)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'referrals'
    AND constraint_name = 'fk_referrals_transaction'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE referrals
     ADD CONSTRAINT fk_referrals_transaction
     FOREIGN KEY (transaction_id) REFERENCES transactions(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- withdrawals.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'withdrawals'
    AND constraint_name = 'fk_withdrawals_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE withdrawals
     ADD CONSTRAINT fk_withdrawals_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE RESTRICT ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- withdrawals.transaction_id → transactions.id (optional)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'withdrawals'
    AND constraint_name = 'fk_withdrawals_transaction'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE withdrawals
     ADD CONSTRAINT fk_withdrawals_transaction
     FOREIGN KEY (transaction_id) REFERENCES transactions(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- password_resets.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'password_resets'
    AND constraint_name = 'fk_password_resets_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE password_resets
     ADD CONSTRAINT fk_password_resets_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- notifications.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'notifications'
    AND constraint_name = 'fk_notifications_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE notifications
     ADD CONSTRAINT fk_notifications_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- support_tickets.user_id → users.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'support_tickets'
    AND constraint_name = 'fk_support_tickets_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE support_tickets
     ADD CONSTRAINT fk_support_tickets_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- support_messages.ticket_id → support_tickets.id
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'support_messages'
    AND constraint_name = 'fk_support_messages_ticket'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE support_messages
     ADD CONSTRAINT fk_support_messages_ticket
     FOREIGN KEY (ticket_id) REFERENCES support_tickets(id)
     ON DELETE CASCADE ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- support_messages.user_id → users.id (nullable for edge cases; SET NULL)
SET @exist := (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE table_schema = DATABASE()
    AND table_name = 'support_messages'
    AND constraint_name = 'fk_support_messages_user'
);
SET @sql := IF(@exist = 0,
  'ALTER TABLE support_messages
     ADD CONSTRAINT fk_support_messages_user
     FOREIGN KEY (user_id) REFERENCES users(id)
     ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- CONSTRAINTS INTENTIONALLY NOT ADDED (application-level)
-- ============================================================
-- 1. users.referred_by
--    Stores referral CODE (VARCHAR), not users.id. Cannot FK to users.id
--    without a schema change. Uniqueness of codes is already enforced.
--
-- 2. transactions.related_id / earnings.reference_id
--    Polymorphic (task_id, deposit_id, withdrawal_id, etc.). No single parent.
--
-- 3. withdrawals.processed_by / transactions.created_by
--    Optional admin user ids. Omitting FK avoids blocking admin account
--    removal/archival; application still records the id when present.
--
-- 4. login_attempts
--    Tracks email/IP strings, not necessarily registered users.
--
-- 5. settings
--    Global key-value store; no parent entity.
--
-- 6. Hard DELETE of users with financial history
--    RESTRICT on earnings/transactions/deposits/withdrawals/referrals means
--    you must not hard-delete users who have money history. Prefer status=suspended.
--
-- ORPHAN CHECK (run manually before 002 if data already exists):
--   SELECT u.id FROM users u LEFT JOIN levels l ON l.id = u.level_id
--     WHERE u.level_id IS NOT NULL AND l.id IS NULL;
--   SELECT ut.id FROM user_tasks ut LEFT JOIN users u ON u.id = ut.user_id
--     WHERE u.id IS NULL;
--   (repeat similarly for other FK children)
-- ============================================================
