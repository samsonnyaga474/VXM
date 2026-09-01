<?php
/**
 * M-Pesa Daraja API Integration (STK Push)
 * 
 * Sandbox-ready. Production requires real credentials via environment variables.
 * Never claims success without a verified callback from Safaricom.
 */

class Mpesa
{
    private string $env;
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;

    public function __construct()
    {
        $this->env            = MPESA_ENV;
        $this->consumerKey    = MPESA_CONSUMER_KEY;
        $this->consumerSecret = MPESA_CONSUMER_SECRET;
        $this->shortcode      = MPESA_SHORTCODE;
        $this->passkey        = MPESA_PASSKEY;
        $this->callbackUrl    = MPESA_CALLBACK_URL;
    }

    public function isConfigured(): bool
    {
        return !empty($this->consumerKey)
            && !empty($this->consumerSecret)
            && !empty($this->shortcode)
            && !empty($this->passkey);
    }

    private function baseUrl(): string
    {
        return $this->env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Get OAuth access token
     */
    public function getAccessToken(): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('M-Pesa credentials are not configured.');
        }

        $url = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . $credentials],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('M-Pesa token request failed: ' . $error);
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            throw new RuntimeException('Unable to obtain M-Pesa access token. Check credentials.');
        }

        return $data['access_token'];
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     * 
     * @return array Raw Safaricom response
     */
    public function stkPush(string $phone, float $amount, string $accountReference, string $description): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('M-Pesa is not configured. Set MPESA_* environment variables.');
        }

        $phone = $this->normalizePhone($phone);
        $amount = (int)round($amount); // M-Pesa expects integer

        if ($amount < 1) {
            throw new InvalidArgumentException('Amount must be at least 1 KES.');
        }

        $token = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => substr($accountReference, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ];

        $url = $this->baseUrl() . '/mpesa/stkpush/v1/processrequest';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('STK Push request failed: ' . $error);
        }

        $data = json_decode($response, true) ?? [];
        $data['_http_code'] = $httpCode;

        return $data;
    }

    /**
     * Normalize Kenyan phone to 2547XXXXXXXX
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }
        if (!preg_match('/^254[17]\d{8}$/', $phone)) {
            throw new InvalidArgumentException('Invalid Kenyan phone number.');
        }
        return $phone;
    }

    /**
     * Process STK callback payload and update deposit
     * Returns true if a deposit was successfully completed
     */
    public function processCallback(array $payload, mysqli $db): bool
    {
        $body = $payload['Body']['stkCallback'] ?? null;
        if (!$body) {
            return false;
        }

        $checkoutId = $body['CheckoutRequestID'] ?? '';
        $resultCode = (string)($body['ResultCode'] ?? '-1');
        $resultDesc = $body['ResultDesc'] ?? '';

        if (empty($checkoutId)) {
            return false;
        }

        // Find deposit
        $stmt = $db->prepare(
            "SELECT id, user_id, amount, status FROM deposits
             WHERE mpesa_checkout_request_id = ? LIMIT 1 FOR UPDATE"
        );
        $db->begin_transaction();
        try {
            $stmt->bind_param('s', $checkoutId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows !== 1) {
                $db->rollback();
                return false;
            }
            $deposit = $result->fetch_assoc();
            $stmt->close();

            // Already processed?
            if ($deposit['status'] === 'completed' || $deposit['status'] === 'failed') {
                $db->commit();
                return $deposit['status'] === 'completed';
            }

            $raw = json_encode($payload);

            if ($resultCode === '0') {
                // Success – extract receipt
                $receipt = null;
                $items = $body['CallbackMetadata']['Item'] ?? [];
                foreach ($items as $item) {
                    if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                        $receipt = $item['Value'] ?? null;
                        break;
                    }
                }

                // Credit wallet INLINE (same transaction — no nested Wallet::credit)
                $uid = (int)$deposit['user_id'];
                $amt = (float)$deposit['amount'];
                $depId = (int)$deposit['id'];

                $stmt = $db->prepare(
                    "SELECT wallet_balance, total_deposits FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
                );
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $urow = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$urow) {
                    throw new RuntimeException('User missing for deposit');
                }
                $before = (float)$urow['wallet_balance'];
                $after = $before + $amt;
                $totalDep = (float)$urow['total_deposits'] + $amt;

                $stmt = $db->prepare(
                    "UPDATE users SET wallet_balance = ?, total_deposits = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->bind_param('ddi', $after, $totalDep, $uid);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Failed wallet credit');
                }
                $stmt->close();

                $type = 'deposit';
                $st = 'completed';
                $desc = 'M-Pesa deposit';
                $meta = json_encode(['checkout_id' => $checkoutId]);
                $stmt = $db->prepare(
                    "INSERT INTO transactions
                     (user_id, type, amount, balance_before, balance_after, status, reference, description, meta, related_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('isdddssssi', $uid, $type, $amt, $before, $after, $st, $receipt, $desc, $meta, $depId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Failed ledger insert');
                }
                $txId = (int)$stmt->insert_id;
                $stmt->close();

                $stmt = $db->prepare(
                    "UPDATE deposits SET
                        status = 'completed',
                        mpesa_receipt = ?,
                        mpesa_result_code = ?,
                        mpesa_result_desc = ?,
                        callback_raw = ?,
                        transaction_id = ?,
                        completed_at = NOW(),
                        updated_at = NOW()
                     WHERE id = ? AND status IN ('pending','processing')"
                );
                $stmt->bind_param('ssssii', $receipt, $resultCode, $resultDesc, $raw, $txId, $depId);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new RuntimeException('Deposit status update failed (possible duplicate)');
                }
                $stmt->close();

                notify_user(
                    $uid,
                    'deposit',
                    'Deposit Successful',
                    'Your wallet has been credited with ' . money($amt) . '.',
                    ['amount' => $amt, 'receipt' => $receipt]
                );

                $db->commit();
                return true;
            } else {
                // Failed
                $stmt = $db->prepare(
                    "UPDATE deposits SET
                        status = 'failed',
                        mpesa_result_code = ?,
                        mpesa_result_desc = ?,
                        callback_raw = ?,
                        updated_at = NOW()
                     WHERE id = ? AND status IN ('pending','processing')"
                );
                $stmt->bind_param('sssi', $resultCode, $resultDesc, $raw, $deposit['id']);
                $stmt->execute();
                $stmt->close();

                notify_user(
                    (int)$deposit['user_id'],
                    'deposit_failed',
                    'Deposit Failed',
                    'Your M-Pesa deposit could not be completed. ' . $resultDesc,
                    ['result_code' => $resultCode]
                );

                $db->commit();
                return false;
            }
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }
}
