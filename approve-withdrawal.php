<?php
require_once __DIR__ . '/includes/bootstrap.php';

$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/withdrawals.php');
}

require_csrf();

$withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
if ($withdrawal_id <= 0) {
    redirect('admin/withdrawals.php?error=invalid_id');
}

$db = db();
$admin_id = $admin['id'];

$db->begin_transaction();

try {
    $stmt = $db->prepare(
        "SELECT id, user_id, amount, status, transaction_id FROM withdrawals WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $withdrawal_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows !== 1) {
        throw new RuntimeException('not_found');
    }
    $w = $res->fetch_assoc();
    $stmt->close();

    if ($w['status'] !== 'pending') {
        throw new RuntimeException('already_processed');
    }

    $amount = (float)$w['amount'];
    $user_id = (int)$w['user_id'];

    // Mark approved – funds already held at request time
    $stmt = $db->prepare(
        "UPDATE withdrawals SET status='approved', processed_at=NOW(), processed_by=? WHERE id=? AND status='pending'"
    );
    $stmt->bind_param('ii', $admin_id, $withdrawal_id);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        throw new RuntimeException('approve_failed');
    }
    $stmt->close();

    // Increase total_withdrawals counter
    $stmt = $db->prepare(
        "UPDATE users SET total_withdrawals = total_withdrawals + ? WHERE id = ?"
    );
    $stmt->bind_param('di', $amount, $user_id);
    $stmt->execute();
    $stmt->close();

    $db->commit();

    notify_user($user_id, 'withdrawal_approved', 'Withdrawal Approved',
        'Your withdrawal of ' . money($amount) . ' has been approved and is being processed.');

} catch (Throwable $e) {
    $db->rollback();
    $code = $e->getMessage();
    if (in_array($code, ['not_found','already_processed','approve_failed'], true)) {
        redirect('admin/withdrawals.php?error=' . $code);
    }
    redirect('admin/withdrawals.php?error=approve_failed');
}

redirect('admin/withdrawals.php?success=approved');
