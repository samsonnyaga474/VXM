-- ============================================================
-- VXM Migration 005 — Populate substantial task pools per level
-- Safe to re-run (uses NOT EXISTS / count checks).
-- XP is awarded separately from monetary reward.
-- Monetary rewards kept modest and aligned with existing level targets.
-- ============================================================

SET NAMES utf8mb4;

-- Ensure columns exist (idempotent)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tasks' AND column_name = 'xp_reward');
SET @sql := IF(@exist = 0, 'ALTER TABLE tasks ADD COLUMN xp_reward INT UNSIGNED NOT NULL DEFAULT 0 AFTER reward', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Helper: only insert if a task with same title+level does not already exist

-- ===================== STARTER LEVEL =====================
-- Target ~KES 20/day from up to 5 daily tasks. Many tasks available; daily limit still applies.

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch daily platform update', 'Open the daily update and watch the short video or read the summary carefully.', 3.00, 15, 'watch', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch daily platform update');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Complete short feedback form', 'Answer the short feedback questions honestly about your experience today.', 4.00, 20, 'interact', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Complete short feedback form');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Read todays tips article', 'Read the assigned tips article fully (minimum estimated reading time).', 3.50, 18, 'read', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Read todays tips article');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Visit partner information page', 'Open the partner information page and stay for at least 20 seconds.', 4.00, 20, 'visit', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Visit partner information page');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Confirm account details', 'Review and confirm your profile details are correct.', 2.50, 12, 'platform', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Confirm account details');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Share referral link once', 'Copy your referral link and share it with one person (platform records the action).', 5.00, 25, 'social', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Share referral link once');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Daily activity check-in', 'Open the dashboard and mark your daily check-in.', 2.00, 10, 'daily', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Daily activity check-in');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch safety reminder', 'Watch the short safety and platform rules reminder.', 3.00, 15, 'watch', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch safety reminder');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Answer one knowledge question', 'Answer a simple platform knowledge question correctly.', 3.50, 18, 'interact', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Answer one knowledge question');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Review wallet summary', 'Open your wallet and review the current balance and recent activity.', 2.00, 10, 'platform', 'active'
FROM levels l WHERE l.slug = 'starter' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Review wallet summary');

-- ===================== GROWTH LEVEL =====================
-- Higher daily limit (10). Slightly higher rewards.

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch growth strategy video', 'Watch the assigned growth strategy video to the end.', 5.00, 30, 'watch', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch growth strategy video');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Complete detailed survey', 'Complete the longer survey about your goals and experience.', 6.00, 35, 'interact', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Complete detailed survey');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Read market insight article', 'Read the full market insight article for the day.', 5.50, 32, 'read', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Read market insight article');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Visit featured partner site', 'Visit the featured partner page and remain for the required time.', 6.00, 35, 'visit', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Visit featured partner site');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Update referral message', 'Customize and save your preferred referral message.', 4.00, 25, 'platform', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Update referral message');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Share daily platform update', 'Share the daily platform update using your referral tools.', 7.00, 40, 'social', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Share daily platform update');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Growth level daily check-in', 'Complete the Growth level daily activity check-in.', 3.00, 20, 'daily', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Growth level daily check-in');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch compliance reminder', 'Watch the short compliance and fair-use reminder.', 4.50, 28, 'watch', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch compliance reminder');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Answer two knowledge questions', 'Correctly answer two platform knowledge questions.', 5.00, 30, 'interact', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Answer two knowledge questions');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Review transaction history', 'Open transactions and review at least the last 5 entries.', 3.50, 22, 'platform', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Review transaction history');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Engage with support FAQ', 'Open the support FAQ and mark one useful answer as helpful.', 4.00, 25, 'interact', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Engage with support FAQ');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Confirm notification preferences', 'Review and save your notification preferences.', 3.00, 20, 'platform', 'active'
FROM levels l WHERE l.slug = 'growth' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Confirm notification preferences');

-- ===================== PRO LEVEL =====================
-- Highest daily limit (15). Higher rewards.

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch advanced strategy briefing', 'Watch the full advanced strategy briefing video.', 8.00, 50, 'watch', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch advanced strategy briefing');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Complete pro research checklist', 'Complete the assigned research checklist items carefully.', 10.00, 60, 'interact', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Complete pro research checklist');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Read deep-dive analysis', 'Read the full deep-dive analysis article for the day.', 9.00, 55, 'read', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Read deep-dive analysis');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Visit premium partner page', 'Visit the premium partner page and complete the required dwell time.', 9.50, 55, 'visit', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Visit premium partner page');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Optimize referral setup', 'Review and optimize your referral tools and messaging.', 7.00, 45, 'platform', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Optimize referral setup');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Share advanced daily brief', 'Share the advanced daily brief through your referral channels.', 12.00, 70, 'social', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Share advanced daily brief');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Pro level daily check-in', 'Complete the Pro level daily activity and progress check-in.', 5.00, 30, 'daily', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Pro level daily check-in');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch advanced compliance briefing', 'Watch the advanced compliance and risk-awareness briefing.', 7.50, 45, 'watch', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch advanced compliance briefing');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Answer pro knowledge set', 'Correctly answer the set of advanced knowledge questions.', 8.00, 50, 'interact', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Answer pro knowledge set');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Audit recent wallet activity', 'Review wallet and transaction history for the past 7 days.', 6.00, 40, 'platform', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Audit recent wallet activity');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Engage with advanced FAQ', 'Open advanced support resources and mark useful items.', 6.50, 40, 'interact', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Engage with advanced FAQ');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Confirm security settings', 'Review and confirm your security and notification settings.', 5.50, 35, 'platform', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Confirm security settings');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Complete weekly goals reflection', 'Write a short reflection on weekly progress (platform records completion).', 8.50, 50, 'interact', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Complete weekly goals reflection');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Review level benefits', 'Open the levels page and review the benefits of your current level.', 4.00, 25, 'platform', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Review level benefits');

INSERT INTO tasks (level_id, title, description, reward, xp_reward, task_type, status)
SELECT l.id, 'Watch performance tips video', 'Watch the performance tips video for Pro members.', 7.00, 45, 'watch', 'active'
FROM levels l WHERE l.slug = 'pro' AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.level_id = l.id AND t.title = 'Watch performance tips video');
