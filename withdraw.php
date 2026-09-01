<?php
/**
 * Request withdrawal — POST + CSRF + atomic hold + ledger
 */
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('withdraw-page.php');
}

$user = require_login();
require_csrf();

$user_id = $user['id'];
$amount = (float)($_POST['amount'] ?? 0);
$phone = preg_replace('/\D+/', '', trim($_POST['phone'] ?? ''));

if ($amount < MIN_WITHDRAWAL) {
    redirect('withdraw-page.php?error=min_amount');
}
if ($phone === '') {
    redirect('withdraw-page.php?error=phone');
}

$fee = (float)WITHDRAWAL_FEE;
$total_debit = $amount + $fee; // User receives $amount; fee is platform charge


$db = db();
$db->begin_transaction();

try {
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
    if ($before < $total_debit) {
        throw new RuntimeException('insufficient');
    }
    $after = $before - $total_debit;

    $stmt = $db->prepare(
        "INSERT INTO withdrawals (user_id, amount, phone, status) VALUES (?, ?, ?, 'pending')"
    );
    $stmt->bind_param('ids', $user_id, $amount, $phone);
    if (!$stmt->execute()) {
        throw new RuntimeException('insert');
    }
    $withdrawalId = (int)$stmt->insert_id;
    $stmt->close();

    $stmt = $db->prepare(
        "UPDATE users SET wallet_balance = ?, updated_at = NOW() WHERE id = ? AND wallet_balance >= ?"
    );
    $stmt->bind_param('did', $after, $user_id, $total_debit);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('debit');
    }
    $stmt->close();

    $type = 'withdrawal';
    $status = 'completed';
    $desc = "Withdrawal request #$withdrawalId to $phone (fee " . money($fee) . ")";
    $neg = -$total_debit;
    $stmt = $db->prepare(
        "INSERT INTO transactions (user_id, type, amount, balance_before, balance_after, status, description, related_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isdddssi', $user_id, $type, $neg, $before, $after, $status, $desc, $withdrawalId);
    if (!$stmt->execute()) {
        throw new RuntimeException('ledger');
    }
    $txId = (int)$stmt->insert_id;
    $stmt->close();

    $stmt = $db->prepare("UPDATE withdrawals SET transaction_id = ? WHERE id = ?");
    $stmt->bind_param('ii', $txId, $withdrawalId);
    $stmt->execute();
    $stmt->close();

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    if ($e->getMessage() === 'insufficient') {
        redirect('withdraw-page.php?error=insufficient');
    }
    redirect('withdraw-page.php?error=failed');
}

notify_user($user_id, 'withdrawal_submitted', 'Withdrawal Submitted',
    'Your withdrawal of ' . money($amount) . ' (fee ' . money($fee) . ') is pending review.');
redirect('withdraw-page.php?success=1');
