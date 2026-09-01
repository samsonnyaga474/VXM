<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

/* ---- Fresh user data ---- */
$stmt = $db->prepare(
    "SELECT id, full_name, email, phone, referral_code, level_id,
            wallet_balance, total_earnings, total_withdrawals, total_deposits, status, created_at
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u || $u['status'] !== 'active') {
    session_unset();
    session_destroy();
    redirect('login.html?error=inactive');
}

$wallet_balance    = (float)$u['wallet_balance'];
$total_earnings    = (float)$u['total_earnings'];
$total_withdrawals = (float)$u['total_withdrawals'];
$total_deposits    = (float)$u['total_deposits'];
$level_id          = (int)($u['level_id'] ?? 0);
$referral_code     = $u['referral_code'];
$full_name         = $u['full_name'];

/* ---- Level ---- */
$level_name = 'No level';
$daily_tasks = 0;
$level_price = 0;
if ($level_id > 0) {
    $stmt = $db->prepare("SELECT name, price, daily_tasks FROM levels WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $level_id);
    $stmt->execute();
    $lv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($lv) {
        $level_name  = $lv['name'];
        $daily_tasks = (int)$lv['daily_tasks'];
        $level_price = (float)$lv['price'];
    }
}

/* ---- Today's earnings ---- */
$stmt = $db->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM earnings
     WHERE user_id = ? AND DATE(created_at) = CURDATE()"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($today_earnings);
$stmt->fetch();
$stmt->close();
$today_earnings = (float)$today_earnings;

/* ---- Today's task progress ---- */
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM user_tasks WHERE user_id = ? AND DATE(completed_at) = CURDATE()"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($today_completed);
$stmt->fetch();
$stmt->close();
$today_completed = (int)$today_completed;
$remaining = max(0, $daily_tasks - $today_completed);
$progress_pct = $daily_tasks > 0 ? min(100, round(($today_completed / $daily_tasks) * 100)) : 0;

/* ---- Referrals ---- */
$stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($referral_count);
$stmt->fetch();
$stmt->close();
$referral_count = (int)$referral_count;

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(bonus), 0) FROM referrals WHERE referrer_id = ? AND status = 'paid'"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($referral_earnings);
$stmt->fetch();
$stmt->close();
$referral_earnings = (float)$referral_earnings;

/* ---- Pending withdrawals ---- */
$stmt = $db->prepare(
    "SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM withdrawals
     WHERE user_id = ? AND status = 'pending'"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($pending_wd_count, $pending_wd_amount);
$stmt->fetch();
$stmt->close();

/* ---- Available tasks (preview) ---- */
$available_tasks = [];
if ($level_id > 0) {
    $stmt = $db->prepare(
        "SELECT t.id, t.title, t.reward,
                CASE WHEN ut.id IS NOT NULL THEN 1 ELSE 0 END AS done_today
         FROM tasks t
         LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ? AND DATE(ut.completed_at) = CURDATE()
         WHERE t.level_id = ? AND t.status = 'active'
         ORDER BY t.id ASC LIMIT 5"
    );
    $stmt->bind_param('ii', $user_id, $level_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $available_tasks[] = $row;
    }
    $stmt->close();
}

/* ---- Recent transactions ---- */
$wallet = new Wallet($db);
$recent_tx = $wallet->getRecent($user_id, 8);

/* ---- Flash / query messages ---- */
$msg = '';
$msgType = 'info';
if (isset($_GET['message'])) {
    $map = [
        'level_selected' => ['Level activated successfully.', 'success'],
        'level_current'  => ['You are already on this level.', 'info'],
    ];
    if (isset($map[$_GET['message']])) {
        [$msg, $msgType] = $map[$_GET['message']];
    }
}
if (isset($_GET['withdraw']) && $_GET['withdraw'] === 'success') {
    $msg = 'Withdrawal request submitted. It is pending review.';
    $msgType = 'success';
}
if (isset($_GET['withdraw_error'])) {
    $errMap = [
        'insufficient' => 'Insufficient balance for this withdrawal.',
        'min_amount'   => 'Amount is below the minimum withdrawal.',
        'phone'        => 'Please provide a valid phone number.',
        'failed'       => 'Withdrawal could not be processed. Please try again.',
    ];
    $msg = $errMap[$_GET['withdraw_error']] ?? 'Withdrawal failed.';
    $msgType = 'error';
}

layout_header('Dashboard', 'dashboard');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= e($msgType) ?>">
  <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : ($msgType === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
  <?= e($msg) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card highlight">
    <div class="label">Wallet Balance</div>
    <div class="value"><?= money($wallet_balance) ?></div>
    <div class="sub">Available to spend or withdraw</div>
  </div>
  <div class="stat-card">
    <div class="label">Total Earnings</div>
    <div class="value"><?= money($total_earnings) ?></div>
    <div class="sub">Lifetime: <?= money($today_earnings) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Current Level</div>
    <div class="value" style="font-size:1.25rem;"><?= e($level_name) ?></div>
    <div class="sub"><?= $daily_tasks ?> daily tasks</div>
  </div>
  <div class="stat-card">
    <div class="label">Referrals</div>
    <div class="value"><?= $referral_count ?></div>
    <div class="sub">Earned <?= money($referral_earnings) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="dash-two-col">

  <!-- Task progress -->
  <div class="panel">
    <div class="panel-header">
      <h2 class="panel-title">Today's Tasks</h2>
      <a href="tasks.php" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <?php if ($level_id <= 0): ?>
      <div class="empty-state">
        <i class="bi bi-layers"></i>
        <h3>No level selected</h3>
        <p>Choose a level to unlock daily tasks.</p>
        <a href="levels.php" class="btn btn-primary btn-sm" style="margin-top:1rem;">Choose level</a>
      </div>
    <?php else: ?>
      <div style="margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.4rem;">
          <span><?= $today_completed ?> / <?= $daily_tasks ?> completed</span>
          <span class="text-muted"><?= $remaining ?> remaining</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $progress_pct ?>%"></div>
        </div>
      </div>
      <?php if (empty($available_tasks)): ?>
        <div class="empty-state" style="padding:1.5rem;">
          <p>No tasks available for your level right now.</p>
        </div>
      <?php else: ?>
        <div class="task-list">
          <?php foreach ($available_tasks as $t): ?>
            <div class="task-item <?= (int)$t['done_today'] ? 'completed' : '' ?>">
              <div class="task-meta">
                <h4><?= e($t['title']) ?></h4>
                <p><?= (int)$t['done_today'] ? 'Completed today' : 'Available' ?></p>
              </div>
              <div class="task-reward">+<?= money((float)$t['reward']) ?></div>
              <?php if (!(int)$t['done_today'] && $remaining > 0): ?>
                <form method="POST" action="complete-task.php" style="margin:0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>" />
                  <button type="submit" class="btn btn-primary btn-sm">Complete</button>
                </form>
              <?php elseif ((int)$t['done_today']): ?>
                <span class="badge badge-success">Done</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Quick actions + pending -->
  <div class="panel">
    <div class="panel-header">
      <h2 class="panel-title">Quick Actions</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <a href="deposit.php" class="btn btn-primary" style="justify-content:flex-start;"><i class="bi bi-phone"></i> Top up wallet (M-Pesa)</a>
      <a href="levels.php" class="btn btn-ghost" style="justify-content:flex-start;"><i class="bi bi-layers"></i> View / change level</a>
      <a href="withdraw-page.php" class="btn btn-ghost" style="justify-content:flex-start;"><i class="bi bi-cash-stack"></i> Request withdrawal</a>
      <a href="referrals.php" class="btn btn-ghost" style="justify-content:flex-start;"><i class="bi bi-share"></i> Share referral code</a>
    </div>
    <?php if ($pending_wd_count > 0): ?>
      <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-subtle);">
        <div class="label" style="margin-bottom:0.5rem;">Pending withdrawals</div>
        <div style="font-family:var(--font-display);font-size:1.25rem;font-weight:600;"><?= money((float)$pending_wd_amount) ?></div>
        <div class="text-muted" style="font-size:0.8rem;"><?= (int)$pending_wd_count ?> request(s) awaiting review</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent transactions -->
<div class="panel">
  <div class="panel-header">
    <h2 class="panel-title">Recent Transactions</h2>
    <a href="transactions.php" class="btn btn-ghost btn-sm">View all</a>
  </div>
  <?php if (empty($recent_tx)): ?>
    <div class="empty-state">
      <i class="bi bi-arrow-left-right"></i>
      <h3>No transactions yet</h3>
      <p>Top up, complete tasks, or request a withdrawal to see activity here.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($recent_tx as $tx):
        $amt = (float)$tx['amount'];
        $isCredit = $amt >= 0;
        $typeLabels = [
          'deposit' => 'Deposit', 'task_reward' => 'Task reward', 'referral_bonus' => 'Referral bonus',
          'level_purchase' => 'Level purchase', 'withdrawal' => 'Withdrawal', 'refund' => 'Refund', 'adjustment' => 'Adjustment'
        ];
        $label = $typeLabels[$tx['type']] ?? $tx['type'];
      ?>
        <div class="tx-row">
          <div class="tx-icon <?= $isCredit ? 'credit' : 'debit' ?>">
            <i class="bi bi-<?= $isCredit ? 'arrow-down-left' : 'arrow-up-right' ?>"></i>
          </div>
          <div class="tx-info">
            <div class="tx-desc"><?= e($tx['description'] ?: $label) ?></div>
            <div class="tx-date"><?= e(date('M j, Y · H:i', strtotime($tx['created_at']))) ?></div>
          </div>
          <div class="tx-amount <?= $isCredit ? 'positive' : 'negative' ?>">
            <?= $isCredit ? '+' : '' ?><?= money($amt) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<style>
@media (max-width: 800px) {
  .dash-two-col { grid-template-columns: 1fr !important; }
}
</style>

<?php layout_footer(); ?>
