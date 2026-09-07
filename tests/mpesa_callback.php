<?php
chdir(dirname(__DIR__));
require 'includes/bootstrap.php';
require 'includes/Mpesa.php';

$failed = 0;
function assert_true($c, $m) {
    global $failed;
    echo ($c ? "PASS  " : "FAIL  ") . $m . "\n";
    if (!$c) $failed++;
}

$db = db();
$mpesa = new Mpesa();

$hash = password_hash('x', PASSWORD_DEFAULT);
$code = generate_referral_code($db);
$email = 'cb_' . bin2hex(random_bytes(3)) . '@vxm.local';
$stmt = $db->prepare("INSERT INTO users (full_name,email,phone,password,referral_code,wallet_balance,status) VALUES ('CB',?, '254700000020', ?, ?, 0, 'active')");
$stmt->bind_param('sss', $email, $hash, $code);
$stmt->execute();
$uid = (int)$stmt->insert_id;
$stmt->close();

$amt = 250.00;
$checkout = 'ws_CO_' . bin2hex(random_bytes(6));
$stmt = $db->prepare("INSERT INTO deposits (user_id, amount, phone, status, mpesa_checkout_request_id) VALUES (?, ?, '254700000020', 'pending', ?)");
$stmt->bind_param('ids', $uid, $amt, $checkout);
$stmt->execute();
$depId = (int)$stmt->insert_id;
$stmt->close();

function payload($checkout, $code, $amount, $receipt = 'NLJ7RT61SV') {
    return [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => $checkout,
                'ResultCode' => $code,
                'ResultDesc' => $code === 0 ? 'Success' : 'Failed',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => $amount],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => $receipt],
                    ],
                ],
            ],
        ],
    ];
}

echo "=== M-Pesa callback ===\n";

// unknown transaction
$ok = $mpesa->processCallback(payload('unknown_checkout', 0, 250), $db);
assert_true($ok === false, 'unknown checkout does not credit');

// mismatch amount
try {
    $mpesa->processCallback(payload($checkout, 0, 9999), $db);
    assert_true(false, 'mismatch amount should not succeed');
} catch (Throwable $e) {
    assert_true(strpos($e->getMessage(), 'does not match') !== false, 'mismatch amount rejected');
}
$bal = $db->query("SELECT wallet_balance FROM users WHERE id={$uid}")->fetch_assoc()['wallet_balance'];
$st = $db->query("SELECT status FROM deposits WHERE id={$depId}")->fetch_assoc()['status'];
assert_true((float)$bal === 0.0, 'wallet unchanged after mismatch');
assert_true($st === 'pending', 'deposit still pending after mismatch');

// matching amount
$ok = $mpesa->processCallback(payload($checkout, 0, 250.00), $db);
assert_true($ok === true, 'matching callback credits once');
$bal = $db->query("SELECT wallet_balance FROM users WHERE id={$uid}")->fetch_assoc()['wallet_balance'];
$st = $db->query("SELECT status FROM deposits WHERE id={$depId}")->fetch_assoc()['status'];
$tx = $db->query("SELECT COUNT(*) c FROM transactions WHERE user_id={$uid} AND type='deposit'")->fetch_assoc()['c'];
assert_true((float)$bal === 250.0, 'wallet credited stored amount 250');
assert_true($st === 'completed', 'deposit marked completed');
assert_true((int)$tx === 1, 'one deposit ledger row');

// duplicate callback
$ok = $mpesa->processCallback(payload($checkout, 0, 250.00, 'DUP2'), $db);
assert_true($ok === true, 'duplicate success callback is idempotent');
$bal = $db->query("SELECT wallet_balance FROM users WHERE id={$uid}")->fetch_assoc()['wallet_balance'];
$tx = $db->query("SELECT COUNT(*) c FROM transactions WHERE user_id={$uid} AND type='deposit'")->fetch_assoc()['c'];
assert_true((float)$bal === 250.0, 'no double credit on duplicate callback');
assert_true((int)$tx === 1, 'still one deposit ledger row');

// failed payment on a new deposit
$checkout2 = 'ws_CO_' . bin2hex(random_bytes(6));
$stmt = $db->prepare("INSERT INTO deposits (user_id, amount, phone, status, mpesa_checkout_request_id) VALUES (?, ?, '254700000020', 'pending', ?)");
$stmt->bind_param('ids', $uid, $amt, $checkout2);
$stmt->execute();
$dep2 = (int)$stmt->insert_id;
$stmt->close();
$ok = $mpesa->processCallback(payload($checkout2, 1032, 250), $db);
assert_true($ok === false, 'failed result code does not credit');
$st = $db->query("SELECT status FROM deposits WHERE id={$dep2}")->fetch_assoc()['status'];
assert_true($st === 'failed', 'failed deposit marked failed');
$bal = $db->query("SELECT wallet_balance FROM users WHERE id={$uid}")->fetch_assoc()['wallet_balance'];
assert_true((float)$bal === 250.0, 'failed callback does not change existing balance');

// malformed
$ok = $mpesa->processCallback(['foo' => 'bar'], $db);
assert_true($ok === false, 'malformed payload ignored');

echo "Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
