<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$stmt = $db->prepare("SELECT wallet_balance, phone FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();
$wallet_balance = (float)$u['wallet_balance'];
$user_phone = $u['phone'] ?? '';

$withdrawals = [];
$stmt = $db->prepare(
    "SELECT id, amount, phone, status, admin_note, created_at, processed_at
     FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 30"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $withdrawals[] = $row;
}
$stmt->close();

$flash = '';
$flashType = 'info';
if (isset($_GET['success'])) {
    $flash = 'Withdrawal request submitted. Funds are held pending admin review.';
    $flashType = 'success';
}
if (isset($_GET['error'])) {
    $map = [
        'insufficient' => 'Insufficient balance.',
        'min_amount' => 'Amount is below the minimum of ' . money(MIN_WITHDRAWAL) . '.',
        'phone' => 'A valid phone number is required.',
        'failed' => 'Could not submit withdrawal. Please try again.',
    ];
    $flash = $map[$_GET['error']] ?? 'Withdrawal failed.';
    $flashType = 'error';
}

layout_header('Withdraw', 'withdraw');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flashType) ?>">
  <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <?= e($flash) ?>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card highlight">
    <div class="label">Available balance</div>
    <div class="value"><?= money($wallet_balance) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Minimum withdrawal</div>
    <div class="value" style="font-size:1.25rem;"><?= money(MIN_WITHDRAWAL) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Withdrawal fee</div>
    <div class="value" style="font-size:1.25rem;"><?= money(WITHDRAWAL_FEE) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="wd-grid">
  <div class="panel form-card">
    <h2 class="panel-title" style="margin-bottom:1.25rem;">Request withdrawal</h2>
    <form method="POST" action="withdraw.php">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="amount">Amount (KES)</label>
        <input type="number" class="form-input" id="amount" name="amount"
               min="<?= MIN_WITHDRAWAL ?>" step="1" max="<?= $wallet_balance ?>" required
               placeholder="e.g. 500" />
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">M-Pesa phone number</label>
        <input type="tel" class="form-input" id="phone" name="phone"
               value="<?= e($user_phone) ?>" required
               placeholder="07XXXXXXXX" />
      </div>
      <p class="text-muted" style="font-size:0.85rem;margin-bottom:1rem;">
        A fee of <?= money(WITHDRAWAL_FEE) ?> is deducted in addition to the amount you request.
        Total charged = amount + fee. Funds are held immediately. If rejected, the full amount including fee is returned to your wallet.
      </p>
      <button type="submit" class="btn btn-primary" style="width:100%;"
        <?= $wallet_balance < MIN_WITHDRAWAL ? 'disabled' : '' ?>>
        <i class="bi bi-cash-stack"></i> Submit withdrawal
      </button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title" style="margin-bottom:1rem;">Status guide</h2>
    <ul style="list-style:none;font-size:0.9rem;color:var(--text-secondary);line-height:2;">
      <li><span class="badge badge-pending">pending</span> Awaiting admin review</li>
      <li><span class="badge badge-success">approved</span> Approved — processing payout</li>
      <li><span class="badge badge-failed">rejected</span> Rejected — funds returned</li>
    </ul>
  </div>
</div>

<div class="panel" style="margin-top:1.5rem;">
  <div class="panel-header">
    <h2 class="panel-title">Withdrawal history</h2>
  </div>
  <?php if (empty($withdrawals)): ?>
    <div class="empty-state">
      <i class="bi bi-cash-stack"></i>
      <h3>No withdrawals yet</h3>
      <p>Your withdrawal requests will appear here.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($withdrawals as $w):
        $badge = match($w['status']) {
          'approved', 'processing' => 'badge-success',
          'pending' => 'badge-pending',
          default => 'badge-failed'
        };
      ?>
        <div class="tx-row">
          <div class="tx-icon debit"><i class="bi bi-arrow-up-right"></i></div>
          <div class="tx-info">
            <div class="tx-desc"><?= money((float)$w['amount']) ?> → <?= e($w['phone']) ?></div>
            <div class="tx-date">
              <?= e(date('M j, Y · H:i', strtotime($w['created_at']))) ?>
              <?php if ($w['admin_note']): ?> · <?= e($w['admin_note']) ?><?php endif; ?>
            </div>
          </div>
          <span class="badge <?= $badge ?>"><?= e($w['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 800px) { .wd-grid { grid-template-columns: 1fr !important; } }
</style>

<?php layout_footer(); ?>
