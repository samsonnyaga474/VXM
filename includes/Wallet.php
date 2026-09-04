<?php
/**
 * Wallet & Transaction Ledger Service
 * 
 * All financial changes MUST go through this class.
 * Never update wallet_balance directly outside of this service.
 */

class Wallet
{
    private mysqli $db;

    public function __construct(?mysqli $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Get current balance (fresh from DB)
     */
    public function getBalance(int $userId): float
    {
        $stmt = $this->db->prepare("SELECT wallet_balance FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($balance);
        $stmt->fetch();
        $stmt->close();
        return (float)$balance;
    }

    /**
     * Credit wallet (deposit, task reward, referral, refund, adjustment)
     * Returns transaction id on success
     */
    public function credit(
        int $userId,
        float $amount,
        string $type,
        string $description = '',
        ?string $reference = null,
        ?int $relatedId = null,
        ?array $meta = null,
        ?int $createdBy = null
    ): int {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        $allowed = ['deposit', 'task_reward', 'referral_bonus', 'adjustment', 'refund'];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Invalid credit type: $type");
        }

        $this->db->begin_transaction();

        try {
            // Lock user row
            $stmt = $this->db->prepare(
                "SELECT wallet_balance, total_earnings, total_deposits FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows !== 1) {
                throw new RuntimeException('User not found.');
            }
            $user = $result->fetch_assoc();
            $stmt->close();

            $before = (float)$user['wallet_balance'];
            $after  = $before + $amount;

            // Update balances
            $totalEarnings = (float)$user['total_earnings'];
            $totalDeposits = (float)$user['total_deposits'];

            if (in_array($type, ['task_reward', 'referral_bonus', 'adjustment'], true) && $amount > 0) {
                $totalEarnings += $amount;
            }
            if ($type === 'deposit') {
                $totalDeposits += $amount;
            }

            $stmt = $this->db->prepare(
                "UPDATE users SET
                    wallet_balance = ?,
                    total_earnings = ?,
                    total_deposits = ?,
                    updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->bind_param('dddi', $after, $totalEarnings, $totalDeposits, $userId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Failed to update wallet.');
            }
            $stmt->close();

            // Ledger entry
            $txId = $this->insertLedger(
                $userId, $type, $amount, $before, $after,
                'completed', $reference, $description, $relatedId, $meta, $createdBy
            );

            // Also keep earnings table for backward compatibility on reward types
            if (in_array($type, ['task_reward', 'referral_bonus'], true)) {
                $earningType = $type === 'task_reward' ? 'task' : 'referral';
                $stmt = $this->db->prepare(
                    "INSERT INTO earnings (user_id, amount, earning_type, description, reference_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('idssi', $userId, $amount, $earningType, $description, $relatedId);
                $stmt->execute();
                $stmt->close();
            }

            $this->db->commit();
            return $txId;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Debit wallet (withdrawal, level purchase)
     */
    public function debit(
        int $userId,
        float $amount,
        string $type,
        string $description = '',
        ?string $reference = null,
        ?int $relatedId = null,
        ?array $meta = null,
        ?int $createdBy = null
    ): int {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        $allowed = ['withdrawal', 'level_purchase', 'adjustment'];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Invalid debit type: $type");
        }

        $this->db->begin_transaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT wallet_balance, total_withdrawals FROM users WHERE id = ? LIMIT 1 FOR UPDATE"
            );
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows !== 1) {
                throw new RuntimeException('User not found.');
            }
            $user = $result->fetch_assoc();
            $stmt->close();

            $before = (float)$user['wallet_balance'];
            if ($before < $amount) {
                throw new RuntimeException('Insufficient balance.');
            }
            $after = $before - $amount;

            $totalWithdrawals = (float)$user['total_withdrawals'];
            // Note: total_withdrawals is only increased when withdrawal is approved/paid

            $stmt = $this->db->prepare(
                "UPDATE users SET wallet_balance = ?, updated_at = NOW() WHERE id = ? AND wallet_balance >= ?"
            );
            $stmt->bind_param('did', $after, $userId, $amount);
            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new RuntimeException('Failed to debit wallet (possible concurrent modification).');
            }
            $stmt->close();

            $txId = $this->insertLedger(
                $userId, $type, -$amount, $before, $after,
                'completed', $reference, $description, $relatedId, $meta, $createdBy
            );

            $this->db->commit();
            return $txId;

        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Hold funds for pending withdrawal (debit immediately, status pending)
     * On rejection we credit back.
     */
    public function holdForWithdrawal(int $userId, float $amount, int $withdrawalId, string $phone): int
    {
        return $this->debit(
            $userId,
            $amount,
            'withdrawal',
            "Withdrawal request #$withdrawalId to $phone",
            null,
            $withdrawalId
        );
    }

    private function insertLedger(
        int $userId,
        string $type,
        float $amount,
        float $before,
        float $after,
        string $status,
        ?string $reference,
        string $description,
        ?int $relatedId,
        ?array $meta,
        ?int $createdBy
    ): int {
        $metaJson = $meta ? json_encode($meta) : null;
        $stmt = $this->db->prepare(
            "INSERT INTO transactions
             (user_id, type, amount, balance_before, balance_after, status, reference, description, meta, related_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // Types must match args: i,s,d,d,d,s,s,s,s,i,i
        // (meta is JSON string; related_id and created_by are nullable ints)
        $stmt->bind_param(
            'isdddssssii',
            $userId, $type, $amount, $before, $after, $status,
            $reference, $description, $metaJson, $relatedId, $createdBy
        );
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to write ledger entry.');
        }
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Get recent transactions for a user
     */
    public function getRecent(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}
