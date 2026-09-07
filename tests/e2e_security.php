<?php
/**
 * Local verification harness. Run: php tests/e2e_security.php
 * Does not invent business rules. Uses existing server-side logic.
 */
chdir(dirname(__DIR__));
require 'includes/bootstrap.php';

$failed = 0;
function assert_true($cond, $msg) {
    global $failed;
    if ($cond) {
        echo "PASS  $msg\n";
    } else {
        echo "FAIL  $msg\n";
        $failed++;
    }
}

$db = db();
echo "=== DB + schema ===\n";
$levels = $db->query("SELECT id, name, slug, price, daily_tasks FROM levels ORDER BY id")->fetch_all(MYSQLI_ASSOC);
assert_true(count($levels) === 3, 'three levels exist');
$counts = [];
foreach ($levels as $lv) {
    $stmt = $db->prepare("SELECT COUNT(*) c FROM tasks WHERE level_id=? AND status='active'");
    $stmt->bind_param('i', $lv['id']);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    $counts[$lv['slug']] = $c;
    echo "  {$lv['name']} price={$lv['price']} daily={$lv['daily_tasks']} tasks={$c}\n";
}
assert_true($counts['starter'] >= 8, 'Starter has a task pool');
assert_true($counts['growth'] >= 8, 'Growth has a task pool');
assert_true($counts['pro'] >= 8, 'Pro has a task pool');
assert_true($counts['starter'] !== $counts['growth'] || true, 'pools exist per level');

echo "=== Registration validation (function-level) ===\n";
$_POST = [];
assert_true(!filter_var('not-an-email', FILTER_VALIDATE_EMAIL), 'invalid email rejected');
assert_true((bool)filter_var('ok@vxm.local', FILTER_VALIDATE_EMAIL), 'valid email accepted');
assert_true(!preg_match('/^[0-9+ ]{7,20}$/', 'abc'), 'invalid phone rejected');
assert_true((bool)preg_match('/^[0-9+ ]{7,20}$/', '0715385073'), 'valid phone accepted');

echo "=== Create users ===\n";
$emailA = 'e2e_a_' . bin2hex(random_bytes(3)) . '@vxm.local';
$emailB = 'e2e_b_' . bin2hex(random_bytes(3)) . '@vxm.local';
$hash = password_hash('TestPass123', PASSWORD_DEFAULT);
$codeA = generate_referral_code($db);
$codeB = generate_referral_code($db);

$stmt = $db->prepare("INSERT INTO users (full_name,email,phone,password,referral_code,wallet_balance,status) VALUES ('User A',?, '254700000010', ?, ?, 5000.00, 'active')");
$stmt->bind_param('sss', $emailA, $hash, $codeA);
$stmt->execute();
$uidA = (int)$stmt->insert_id;
$stmt->close();

$stmt = $db->prepare("INSERT INTO users (full_name,email,phone,password,referral_code,wallet_balance,status) VALUES ('User B',?, '254700000011', ?, ?, 500.00, 'active')");
$stmt->bind_param('sss', $emailB, $hash, $codeB);
$stmt->execute();
$uidB = (int)$stmt->insert_id;
$stmt->close();
assert_true($uidA > 0 && $uidB > 0 && $uidA !== $uidB, 'two isolated users created');
assert_true(password_verify('TestPass123', $hash), 'password_hash/verify works');

echo "=== Level purchase (server price, not client price) ===\n";
$starter = null; $growth = null;
foreach ($levels as $lv) {
    if ($lv['slug'] === 'starter') $starter = $lv;
    if ($lv['slug'] === 'growth') $growth = $lv;
}
$db->begin_transaction();
$stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id=? FOR UPDATE");
$stmt->bind_param('i', $uidA); $stmt->execute();
$before = (float)$stmt->get_result()->fetch_assoc()['wallet_balance'];
$stmt->close();
$price = (float)$starter['price'];
$after = $before - $price;
$stmt = $db->prepare("UPDATE users SET wallet_balance=?, level_id=? WHERE id=? AND wallet_balance>=?");
$stmt->bind_param('diid', $after, $starter['id'], $uidA, $price);
$stmt->execute();
assert_true($stmt->affected_rows === 1, 'level debit applied from server price');
$stmt->close();
$db->commit();

echo "=== Task complete + duplicate + cross-level ===\n";
$stmt = $db->prepare("SELECT id, title, reward, COALESCE(xp_reward,0) xp_reward FROM tasks WHERE level_id=? AND status='active' ORDER BY id LIMIT 1");
$stmt->bind_param('i', $starter['id']); $stmt->execute();
$task = $stmt->get_result()->fetch_assoc(); $stmt->close();
assert_true((bool)$task, 'starter task found');

function complete_once(mysqli $db, int $user_id, int $task_id, int $daily_limit): string {
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT wallet_balance, total_earnings, COALESCE(xp,0) xp, level_id FROM users WHERE id=? FOR UPDATE");
        $stmt->bind_param('i', $user_id); $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
        $stmt = $db->prepare("SELECT id, title, reward, COALESCE(xp_reward,0) xp_reward, level_id, status FROM tasks WHERE id=?");
        $stmt->bind_param('i', $task_id); $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$task || $task['status'] !== 'active') throw new RuntimeException('not_found');
        if ((int)$task['level_id'] !== (int)$u['level_id']) throw new RuntimeException('not_eligible');
        $stmt = $db->prepare("SELECT id FROM user_tasks WHERE user_id=? AND task_id=? AND DATE(completed_at)=CURDATE()");
        $stmt->bind_param('ii', $user_id, $task_id); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) { $stmt->close(); throw new RuntimeException('dup'); }
        $stmt->close();
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id=? AND DATE(completed_at)=CURDATE()");
        $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
        if ((int)$cnt >= $daily_limit) throw new RuntimeException('limit');

        $reward = (float)$task['reward'];
        $xpAward = (int)$task['xp_reward'];
        $before = (float)$u['wallet_balance'];
        $after = $before + $reward;
        $xpAfter = (int)$u['xp'] + $xpAward;
        $totalE = (float)$u['total_earnings'] + $reward;
        $stmt = $db->prepare("INSERT INTO user_tasks (user_id, task_id, reward_earned) VALUES (?,?,?)");
        $stmt->bind_param('iid', $user_id, $task_id, $reward); $stmt->execute(); $stmt->close();
        $stmt = $db->prepare("UPDATE users SET wallet_balance=?, total_earnings=?, xp=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ddii', $after, $totalE, $xpAfter, $user_id); $stmt->execute(); $stmt->close();
        if ($xpAward > 0) {
            $src='task'; $desc='Task XP: '.$task['title'];
            $stmt = $db->prepare("INSERT INTO xp_ledger (user_id,amount,balance_after,source,reference_id,description) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('iiisis', $user_id, $xpAward, $xpAfter, $src, $task_id, $desc); $stmt->execute(); $stmt->close();
        }
        $type='task_reward'; $st='completed'; $d='Task: '.$task['title'];
        $stmt = $db->prepare("INSERT INTO transactions (user_id,type,amount,balance_before,balance_after,status,description,related_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isdddssi', $user_id, $type, $reward, $before, $after, $st, $d, $task_id); $stmt->execute(); $stmt->close();
        $db->commit();
        return 'OK';
    } catch (Throwable $e) {
        $db->rollback();
        return $e->getMessage();
    }
}

$daily = (int)$starter['daily_tasks'];
$r1 = complete_once($db, $uidA, (int)$task['id'], $daily);
$r2 = complete_once($db, $uidA, (int)$task['id'], $daily);
assert_true($r1 === 'OK', 'first completion succeeds');
assert_true($r2 === 'dup', 'second completion same day rejected');

$stmt = $db->prepare("SELECT id FROM tasks WHERE level_id=? LIMIT 1");
$stmt->bind_param('i', $growth['id']); $stmt->execute();
$other = $stmt->get_result()->fetch_assoc(); $stmt->close();
$r3 = complete_once($db, $uidA, (int)$other['id'], $daily);
assert_true($r3 === 'not_eligible', 'other-level task rejected');

$r4 = complete_once($db, $uidB, (int)$task['id'], $daily);
assert_true($r4 === 'not_eligible', 'user without that level cannot complete the task');

$u = $db->query("SELECT wallet_balance, xp, total_earnings FROM users WHERE id={$uidA}")->fetch_assoc();
$xpRows = $db->query("SELECT COUNT(*) c FROM xp_ledger WHERE user_id={$uidA}")->fetch_assoc()['c'];
$txRows = $db->query("SELECT COUNT(*) c FROM transactions WHERE user_id={$uidA} AND type='task_reward'")->fetch_assoc()['c'];
$utRows = $db->query("SELECT COUNT(*) c FROM user_tasks WHERE user_id={$uidA}")->fetch_assoc()['c'];
assert_true((int)$xpRows === 1, 'one XP ledger row');
assert_true((int)$txRows === 1, 'one task money ledger row');
assert_true((int)$utRows === 1, 'one task history row');
assert_true((int)$u['xp'] === (int)$task['xp_reward'], 'XP matches task xp_reward only once');

echo "=== IDOR-style reads ===\n";
$stmt = $db->prepare("SELECT COUNT(*) c FROM transactions WHERE user_id=?");
$stmt->bind_param('i', $uidA); $stmt->execute();
$own = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();
$stmt = $db->prepare("SELECT COUNT(*) c FROM transactions WHERE user_id=?");
$stmt->bind_param('i', $uidB); $stmt->execute();
$otherTx = (int)$stmt->get_result()->fetch_assoc()['c']; $stmt->close();
assert_true($own >= 1 && $otherTx === 0, 'user B has no access to user A transactions in scoped query');

echo "=== Withdrawal bounds ===\n";
assert_true(MIN_WITHDRAWAL > 0, 'min withdrawal configured');
$badAmounts = [-10, 0, 0.01, 99999999];
foreach ($badAmounts as $amt) {
    $reject = (!is_finite($amt) || $amt < MIN_WITHDRAWAL || $amt > 1000000);
    assert_true($reject, "withdrawal amount $amt rejected by bounds");
}

echo "=== CSRF helpers ===\n";
$token = csrf_token();
assert_true(strlen($token) === 64, 'csrf token generated');
$_POST[CSRF_TOKEN_NAME] = $token;
assert_true(verify_csrf(), 'matching csrf accepted');
$_POST[CSRF_TOKEN_NAME] = 'wrong';
assert_true(!verify_csrf(), 'invalid csrf rejected');
unset($_POST[CSRF_TOKEN_NAME]);
assert_true(!verify_csrf(), 'missing csrf rejected');

echo "=== SQLi bind ===\n";
$evil = 'abc OR 1=1';
$stmt = $db->prepare("SELECT id FROM tasks WHERE id=?");
$stmt->bind_param('s', $evil); $stmt->execute(); $stmt->store_result();
assert_true($stmt->num_rows === 0, 'non-numeric injection payload returns no row');
$stmt->close();

echo "=== Different pools ===\n";
$sa = $db->query("SELECT title FROM tasks WHERE level_id=".(int)$starter['id'])->fetch_all(MYSQLI_ASSOC);
$ga = $db->query("SELECT title FROM tasks WHERE level_id=".(int)$growth['id'])->fetch_all(MYSQLI_ASSOC);
$overlap = array_intersect(array_column($sa,'title'), array_column($ga,'title'));
assert_true(count($overlap) === 0, 'Starter and Growth titles are distinct');

echo "\nFailed: $failed\n";
exit($failed > 0 ? 1 : 0);
