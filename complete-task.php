<?php
/**
 * Complete a task (POST + CSRF + daily limit + atomic ledger credit)
 */
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tasks.php?error=method');
}

$user = require_login();
require_csrf();

$user_id = $user['id'];
$task_id = (int)($_POST['task_id'] ?? 0);
if ($task_id <= 0) {
    redirect('tasks.php?error=invalid_task');
}

$db = db();

$stmt = $db->prepare("SELECT level_id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($level_id);
$stmt->fetch();
$stmt->close();
$level_id = (int)$level_id;
if ($level_id <= 0) {
    redirect('levels.php?error=no_level');
}

$stmt = $db->prepare("SELECT daily_tasks FROM levels WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('i', $level_id);
$stmt->execute();
$stmt->bind_result($daily_limit);
$stmt->fetch();
$stmt->close();
$daily_limit = (int)$daily_limit;

$stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id = ? AND DATE(completed_at) = CURDATE()");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($today_count);
$stmt->fetch();
$stmt->close();
if ((int)$today_count >= $daily_limit) {
    redirect('tasks.php?error=daily_limit');
}

$stmt = $db->prepare("SELECT id, title, reward, COALESCE(xp_reward, 0) AS xp_reward, level_id, status FROM tasks WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$task) {
    redirect('tasks.php?error=not_found');
}
if ($task['status'] !== 'active') {
    redirect('tasks.php?error=inactive');
}
if ($task['level_id'] !== null && (int)$task['level_id'] !== $level_id) {
    redirect('tasks.php?error=not_eligible');
}

$stmt = $db->prepare(
    "SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? AND DATE(completed_at) = CURDATE() LIMIT 1"
);
$stmt->bind_param('ii', $user_id, $task_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirect('tasks.php?error=already_completed');
}
$stmt->close();

$reward = (float)$task['reward'];
if ($reward < 0) {
    redirect('tasks.php?error=failed');
}

$db->begin_transaction();
try {
    // Lock user row
    $stmt = $db->prepare(
        "SELECT wallet_balance, total_earnings, COALESCE(xp, 0) AS xp FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) {
        throw new RuntimeException('user');
    }

    // Re-check duplicate under lock
    $stmt = $db->prepare(
        "SELECT id FROM user_tasks WHERE user_id = ? AND task_id = ? AND DATE(completed_at) = CURDATE() LIMIT 1"
    );
    $stmt->bind_param('ii', $user_id, $task_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        throw new RuntimeException('dup');
    }
    $stmt->close();

    // Re-check daily limit under lock
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM user_tasks WHERE user_id = ? AND DATE(completed_at) = CURDATE()"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if ((int)$cnt >= $daily_limit) {
        throw new RuntimeException('limit');
    }

    $before = (float)$u['wallet_balance'];
    $after = $before + $reward;
    $totalEarnings = (float)$u['total_earnings'] + $reward;
    $xpAward = (int)($task['xp_reward'] ?? 0);
    $xpBefore = (int)$u['xp'];
    $xpAfter = $xpBefore + $xpAward;

    $stmt = $db->prepare(
        "INSERT INTO user_tasks (user_id, task_id, reward_earned) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('iid', $user_id, $task_id, $reward);
    if (!$stmt->execute()) {
        throw new RuntimeException('insert');
    }
    $stmt->close();

    $stmt = $db->prepare(
        "UPDATE users SET wallet_balance = ?, total_earnings = ?, xp = ?, updated_at = NOW() WHERE id = ?"
    );
    $stmt->bind_param('ddii', $after, $totalEarnings, $xpAfter, $user_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('balance');
    }
    $stmt->close();

    // XP ledger (only if XP awarded)
    if ($xpAward > 0) {
        $xpSource = 'task';
        $xpDesc = 'Task XP: ' . $task['title'];
        $stmt = $db->prepare(
            "INSERT INTO xp_ledger (user_id, amount, balance_after, source, reference_id, description)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iiisis', $user_id, $xpAward, $xpAfter, $xpSource, $task_id, $xpDesc);
        if (!$stmt->execute()) {
            throw new RuntimeException('xp_ledger');
        }
        $stmt->close();
    }

    $type = 'task_reward';
    $status = 'completed';
    $desc = 'Task: ' . $task['title'];
    $stmt = $db->prepare(
        "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, status, description, related_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isdddssi', $user_id, $type, $reward, $before, $after, $status, $desc, $task_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('ledger');
    }
    $stmt->close();

    $stmt = $db->prepare(
        "INSERT INTO earnings (user_id, amount, earning_type, description, reference_id) VALUES (?, ?, 'task', ?, ?)"
    );
    $stmt->bind_param('idsi', $user_id, $reward, $desc, $task_id);
    $stmt->execute();
    $stmt->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    $code = $e->getMessage();
    if ($code === 'dup') {
        redirect('tasks.php?error=already_completed');
    }
    if ($code === 'limit') {
        redirect('tasks.php?error=daily_limit');
    }
    redirect('tasks.php?error=failed');
}

$msg = 'You earned ' . money($reward);
if (!empty($xpAward) && $xpAward > 0) {
    $msg .= ' and ' . $xpAward . ' XP';
}
$msg .= ' for "' . $task['title'] . '".';
notify_user($user_id, 'task_completed', 'Task Completed', $msg);
redirect('tasks.php?completed=success');
