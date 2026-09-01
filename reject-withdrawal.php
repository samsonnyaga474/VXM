<?php
require_once __DIR__ . '/includes/bootstrap.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/withdrawals.php');
}
require_csrf();

$withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
$note = trim($_POST['admin_note'] ?? '');
if ($withdrawal_id <= 0) {
    redirect('admin/withdrawals.php?error=invalid_id');
}

$db = db();
$admin_id = $admin['id'];

$db->begin_transaction();
try {
    $stmt = $db->prepare(
        "SELECT id, user_id, amount, status FROM withdrawals WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $withdrawal_id);
    $stmt->execute();
    $w = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$w) {
        throw new RuntimeException('not_found');
    }
    if ($w['status'] !== 'pending') {
        throw new RuntimeException('already_processed');
    }

    $amount = (float)$w['amount'];
    $user_id = (int)$w['user_id'];

    // Refund inline
    $stmt = $db->prepare(
        "SELECT wallet_balance FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$u) {
        throw new RuntimeException('user');
    }
    $before = (float)$u['wallet_balance'];
    $after = $before + $amount;

    $stmt = $db->prepare("UPDATE users SET wallet_balance = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('di', $after, $user_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('credit');
    }
    $stmt->close();

    $type = 'refund';
    $st = 'completed';
    $desc = 'Withdrawal #' . $withdrawal_id . ' rejected – funds returned';
    $stmt = $db->prepare(
        "INSERT INTO transactions
         (user_id, type, amount, balance_before, balance_after, status, description, related_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isdddssii', $user_id, $type, $amount, $before, $after, $st, $desc, $withdrawal_id, $admin_id);
    if (!$stmt->execute()) {
        throw new RuntimeException('ledger');
    }
    $stmt->close();

    $stmt = $db->prepare(
        "UPDATE withdrawals SET status='rejected', admin_note=?, processed_at=NOW(), processed_by=?
         WHERE id=? AND status='pending'"
    );
    $stmt->bind_param('sii', $note, $admin_id, $withdrawal_id);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('reject_failed');
    }
    $stmt->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    redirect('admin/withdrawals.php?error=reject_failed');
}

notify_user($user_id, 'withdrawal_rejected', 'Withdrawal Rejected',
    'Your withdrawal of ' . money($amount) . ' was rejected. Funds have been returned to your wallet.' .
    ($note ? ' Note: ' . $note : ''));

redirect('admin/withdrawals.php?success=rejected');
