<?php
/**
 * M-Pesa STK Push Callback Endpoint
 * Safaricom posts payment results here.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Mpesa.php';

// Always respond 200 quickly so Safaricom does not retry endlessly on our processing errors
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

// Log for debugging (never expose to users)
$logFile = STORAGE_PATH . '/logs/mpesa_callback_' . date('Y-m-d') . '.log';
@file_put_contents($logFile, date('c') . ' ' . $raw . PHP_EOL, FILE_APPEND);

if (!$payload) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload']);
    exit;
}

try {
    $mpesa = new Mpesa();
    $mpesa->processCallback($payload, db());
} catch (Throwable $e) {
    @file_put_contents($logFile, date('c') . ' ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
}

// Safaricom expects this acknowledgement
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
