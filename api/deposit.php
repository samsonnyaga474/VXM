<?php
/**
 * Initiate wallet deposit via M-Pesa STK Push
 * POST: amount, phone
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Mpesa.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$user = require_login();
require_csrf();

$amount = (float)($_POST['amount'] ?? 0);
$phone  = trim($_POST['phone'] ?? '');

if ($amount < MIN_DEPOSIT) {
    json_response(['error' => 'Minimum deposit is ' . money(MIN_DEPOSIT)], 400);
}

if (empty($phone)) {
    json_response(['error' => 'Phone number is required'], 400);
}

$db = db();
$mpesa = new Mpesa();

if (!$mpesa->isConfigured()) {
    // Simulated deposits ONLY in explicit development environment.
    // Production and sandbox without credentials must fail closed.
    if (VXM_ENV === 'development' && defined('ALLOW_SIMULATED_DEPOSITS') && ALLOW_SIMULATED_DEPOSITS === true) {
        try {
            $normalized = $mpesa->normalizePhone($phone);
        } catch (Throwable $e) {
            json_response(['error' => $e->getMessage()], 400);
        }

        $db->begin_transaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO deposits (user_id, amount, phone, status)
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt->bind_param('ids', $user['id'], $amount, $normalized);
            $stmt->execute();
            $depositId = (int)$stmt->insert_id;
            $stmt->close();

            // Simulate success in development
            $wallet = new Wallet($db);
            $txId = $wallet->credit(
                $user['id'],
                $amount,
                'deposit',
                'Simulated deposit (dev mode)',
                'DEV' . time(),
                $depositId
            );

            $stmt = $db->prepare(
                "UPDATE deposits SET status='completed', mpesa_receipt=?, transaction_id=?, completed_at=NOW()
                 WHERE id=?"
            );
            $receipt = 'DEV' . time();
            $stmt->bind_param('sii', $receipt, $txId, $depositId);
            $stmt->execute();
            $stmt->close();

            $db->commit();

            notify_user($user['id'], 'deposit', 'Deposit Successful (Dev)', 'Simulated credit of ' . money($amount));

            json_response([
                'success' => true,
                'message' => 'Development mode: wallet credited immediately.',
                'deposit_id' => $depositId,
                'mode' => 'simulated'
            ]);
        } catch (Throwable $e) {
            $db->rollback();
            json_response(['error' => $e->getMessage()], 500);
        }
    }

    $err = (VXM_ENV === 'production')
            ? 'M-Pesa is not configured on this server. Simulated deposits are disabled in production.'
            : 'M-Pesa is not configured. Set MPESA credentials, or enable ALLOW_SIMULATED_DEPOSITS only in development.';
    json_response(['error' => $err], 503);
}

try {
    $normalized = $mpesa->normalizePhone($phone);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}

// Create pending deposit record
$stmt = $db->prepare(
    "INSERT INTO deposits (user_id, amount, phone, status) VALUES (?, ?, ?, 'pending')"
);
$stmt->bind_param('ids', $user['id'], $amount, $normalized);
$stmt->execute();
$depositId = (int)$stmt->insert_id;
$stmt->close();

try {
    $response = $mpesa->stkPush(
        $normalized,
        $amount,
        'VXM' . $depositId,
        'VXM Wallet Top-up'
    );

    $checkoutId = $response['CheckoutRequestID'] ?? null;
    $merchantId = $response['MerchantRequestID'] ?? null;
    $respCode   = $response['ResponseCode'] ?? ($response['_http_code'] ?? '');

    if ($checkoutId && (string)$respCode === '0') {
        $stmt = $db->prepare(
            "UPDATE deposits SET
                status = 'processing',
                mpesa_checkout_request_id = ?,
                mpesa_merchant_request_id = ?,
                updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->bind_param('ssi', $checkoutId, $merchantId, $depositId);
        $stmt->execute();
        $stmt->close();

        json_response([
            'success' => true,
            'message' => 'STK Push sent. Check your phone and enter M-Pesa PIN.',
            'deposit_id' => $depositId,
            'checkout_request_id' => $checkoutId
        ]);
    } else {
        $desc = $response['ResponseDescription'] ?? $response['errorMessage'] ?? 'STK Push failed';
        $stmt = $db->prepare("UPDATE deposits SET status='failed', mpesa_result_desc=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('si', $desc, $depositId);
        $stmt->execute();
        $stmt->close();

        json_response(['error' => $desc], 400);
    }
} catch (Throwable $e) {
    $stmt = $db->prepare("UPDATE deposits SET status='failed', mpesa_result_desc=?, updated_at=NOW() WHERE id=?");
    $msg = $e->getMessage();
    $stmt->bind_param('si', $msg, $depositId);
    $stmt->execute();
    $stmt->close();

    json_response(['error' => $e->getMessage()], 500);
}
