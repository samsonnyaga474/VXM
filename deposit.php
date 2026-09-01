<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';
require_once __DIR__ . '/includes/Mpesa.php';

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

$mpesa = new Mpesa();
$mpesaConfigured = $mpesa->isConfigured();
$isDev = (VXM_ENV === 'development');

/* Recent deposits */
$deposits = [];
$stmt = $db->prepare(
    "SELECT id, amount, phone, status, mpesa_receipt, created_at, completed_at
     FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 15"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $deposits[] = $row;
}
$stmt->close();

layout_header('Top Up', 'deposit');
?>

<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
  <div class="stat-card highlight">
    <div class="label">Wallet Balance</div>
    <div class="value"><?= money($wallet_balance) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Minimum deposit</div>
    <div class="value" style="font-size:1.25rem;"><?= money(MIN_DEPOSIT) ?></div>
  </div>
</div>

<?php if (!$mpesaConfigured && $isDev): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle"></i>
  <div>
    <strong>Development mode</strong> — M-Pesa credentials are not set.
    Deposits will be simulated and credited immediately for testing.
  </div>
</div>
<?php elseif (!$mpesaConfigured): ?>
<div class="alert alert-error">
  <i class="bi bi-x-circle"></i>
  M-Pesa is not configured. Please contact support.
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="dep-grid">
  <div class="panel form-card">
    <h2 class="panel-title" style="margin-bottom:1.25rem;">Top up with M-Pesa</h2>
    <form id="depositForm">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label" for="amount">Amount (KES)</label>
        <input type="number" class="form-input" id="amount" name="amount"
               min="<?= MIN_DEPOSIT ?>" step="1" required
               placeholder="e.g. 500" />
      </div>
      <div class="form-group">
        <label class="form-label" for="phone">M-Pesa phone number</label>
        <input type="tel" class="form-input" id="phone" name="phone"
               value="<?= e($user_phone) ?>" required
               placeholder="07XXXXXXXX or 2547XXXXXXXX" />
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;" id="depositBtn">
        <i class="bi bi-phone"></i> Pay with M-Pesa
      </button>
    </form>
    <div id="depositStatus" style="margin-top:1.25rem;display:none;"></div>
  </div>

  <div class="panel">
    <h2 class="panel-title" style="margin-bottom:1rem;">How it works</h2>
    <ol style="padding-left:1.25rem;color:var(--text-secondary);font-size:0.9rem;line-height:1.8;">
      <li>Enter the amount and your M-Pesa number.</li>
      <li>Confirm the STK Push prompt on your phone.</li>
      <li>Enter your M-Pesa PIN.</li>
      <li>We credit your wallet only after Safaricom confirms payment.</li>
    </ol>
    <p class="text-muted" style="font-size:0.85rem;margin-top:1rem;">
      Failed or cancelled payments are not credited. You can try again at any time.
    </p>
  </div>
</div>

<div class="panel" style="margin-top:1.5rem;">
  <div class="panel-header">
    <h2 class="panel-title">Deposit history</h2>
  </div>
  <?php if (empty($deposits)): ?>
    <div class="empty-state" style="padding:2rem;">
      <i class="bi bi-phone"></i>
      <h3>No deposits yet</h3>
      <p>Your top-up history will appear here.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($deposits as $d):
        $status = $d['status'];
        $badge = match($status) {
          'completed' => 'badge-success',
          'pending', 'processing' => 'badge-pending',
          default => 'badge-failed'
        };
      ?>
        <div class="tx-row">
          <div class="tx-icon <?= $status === 'completed' ? 'credit' : 'debit' ?>">
            <i class="bi bi-phone"></i>
          </div>
          <div class="tx-info">
            <div class="tx-desc"><?= money((float)$d['amount']) ?> · <?= e($d['phone']) ?></div>
            <div class="tx-date">
              <?= e(date('M j, Y · H:i', strtotime($d['created_at']))) ?>
              <?php if ($d['mpesa_receipt']): ?> · Receipt <?= e($d['mpesa_receipt']) ?><?php endif; ?>
            </div>
          </div>
          <span class="badge <?= $badge ?>"><?= e($status) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 800px) { .dep-grid { grid-template-columns: 1fr !important; } }
</style>

<script>
(function(){
  const form = document.getElementById('depositForm');
  const btn = document.getElementById('depositBtn');
  const status = document.getElementById('depositStatus');
  if (!form) return;

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing…';
    status.style.display = 'block';
    status.className = 'alert alert-info';
    status.innerHTML = '<i class="bi bi-info-circle"></i> Initiating payment…';

    const fd = new FormData(form);
    try {
      const res = await fetch('api/deposit.php', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const data = await res.json();
      if (data.success) {
        status.className = 'alert alert-success';
        if (data.mode === 'simulated') {
          status.innerHTML = '<i class="bi bi-check-circle"></i> ' + (data.message || 'Deposit simulated and credited.');
          setTimeout(() => location.reload(), 1500);
        } else {
          status.innerHTML = '<i class="bi bi-phone"></i> ' + (data.message || 'Check your phone and enter M-Pesa PIN.');
          // Poll is optional; user can refresh later
        }
      } else {
        status.className = 'alert alert-error';
        status.innerHTML = '<i class="bi bi-x-circle"></i> ' + (data.error || 'Payment failed.');
      }
    } catch (err) {
      status.className = 'alert alert-error';
      status.innerHTML = '<i class="bi bi-x-circle"></i> Network error. Please try again.';
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-phone"></i> Pay with M-Pesa';
  });
})();
</script>

<?php layout_footer(); ?>
