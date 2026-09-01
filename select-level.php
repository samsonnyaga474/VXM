<?php
/**
 * Purchase / activate level — POST + CSRF + Wallet ledger debit
 */
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('levels.php');
}

$user = require_login();
require_csrf();

$user_id = $user['id'];
$level_id = (int)($_POST['level_id'] ?? 0);
if ($level_id <= 0) {
    redirect('levels.php?error=invalid');
}

$db = db();

$stmt = $db->prepare("SELECT id, name, price, status FROM levels WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $level_id);
$stmt->execute();
$level = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$level || $level['status'] !== 'active') {
    redirect('levels.php?error=not_found');
}
$level_price = (float)$level['price'];

$db->begin_transaction();
try {
    $stmt = $db->prepare(
        "SELECT level_id, wallet_balance FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) {
        throw new RuntimeException('user');
    }

    $current = (int)($u['level_id'] ?? 0);
    if ($current === $level_id) {
        $db->commit();
        redirect('dashboard.php?message=level_current');
    }

    $before = (float)$u['wallet_balance'];
    if ($before < $level_price) {
        throw new RuntimeException('insufficient');
    }
    $after = $before - $level_price;

    $stmt = $db->prepare(
        "UPDATE users SET wallet_balance = ?, level_id = ?, updated_at = NOW()
         WHERE id = ? AND wallet_balance >= ?"
    );
    $stmt->bind_param('diid', $after, $level_id, $user_id, $level_price);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('debit');
    }
    $stmt->close();

    $type = 'level_purchase';
    $status = 'completed';
    $desc = 'Level upgrade: ' . $level['name'];
    $neg = -$level_price;
    $stmt = $db->prepare(
        "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, status, description, related_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isdddssi', $user_id, $type, $neg, $before, $after, $status, $desc, $level_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('ledger');
    }
    $stmt->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    if ($e->getMessage() === 'insufficient') {
        redirect('levels.php?error=insufficient_balance');
    }
    redirect('levels.php?error=level_purchase_failed');
}

$_SESSION['level_id'] = $level_id;

// Referral bonus trigger: 5% of the purchased level price (REFERRAL_PERCENTAGE)
if (REFERRAL_BONUS_TRIGGER === 'on_level_purchase') {
    $stmt = $db->prepare(
        "SELECT id, referrer_id, bonus FROM referrals
         WHERE referred_user_id = ? AND status = 'pending' LIMIT 1"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $ref = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ref) {
        // Calculate bonus as percentage of the level the referred user just purchased
        $bonus_amount = round($level_price * (REFERRAL_PERCENTAGE / 100.0), 2);
        if ($bonus_amount > 0) {
            try {
                $wallet = new Wallet($db);
                $txId = $wallet->credit(
                    (int)$ref['referrer_id'],
                    $bonus_amount,
                    'referral_bonus',
                    'Referral bonus (' . REFERRAL_PERCENTAGE . '% of ' . money($level_price) . ') for user #' . $user_id,
                    null,
                    (int)$ref['id']
                );
                $stmt = $db->prepare(
                    "UPDATE referrals SET bonus = ?, status='paid', qualified_at=NOW(), paid_at=NOW(), transaction_id=? WHERE id=? AND status='pending'"
                );
                $stmt->bind_param('dii', $bonus_amount, $txId, $ref['id']);
                $stmt->execute();
                $stmt->close();
                notify_user((int)$ref['referrer_id'], 'referral_bonus', 'Referral Bonus',
                    'You received ' . money($bonus_amount) . ' for a successful referral.');
            } catch (Throwable $e) {
                // leave pending for retry
            }
        } else {
            $stmt = $db->prepare("UPDATE referrals SET status='cancelled' WHERE id=?");
            $stmt->bind_param('i', $ref['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

notify_user($user_id, 'level_activated', 'Level Activated', 'You are now on the ' . $level['name'] . ' level.');
redirect('dashboard.php?message=level_selected');
