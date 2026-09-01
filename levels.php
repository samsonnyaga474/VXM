<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$stmt = $db->prepare("SELECT level_id, wallet_balance FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_level_id = (int)($u['level_id'] ?? 0);
$wallet_balance   = (float)$u['wallet_balance'];

$levels = [];
$res = $db->query(
    "SELECT id, name, price, daily_tasks, referral_bonus, description, status, sort_order
     FROM levels WHERE status = 'active' ORDER BY sort_order ASC, price ASC"
);
while ($row = $res->fetch_assoc()) {
    $levels[] = $row;
}

$error = '';
if (isset($_GET['error'])) {
    $map = [
        'insufficient_balance' => 'Your wallet balance is not enough for this level. Please top up first.',
        'level_purchase_failed' => 'Level purchase failed. Please try again.',
        'invalid' => 'Invalid level selected.',
        'not_found' => 'Level not found.',
        'inactive' => 'This level is not available.',
        'no_level' => 'Please select a level to continue.',
    ];
    $error = $map[$_GET['error']] ?? 'Something went wrong.';
}

layout_header('Levels', 'levels');
?>

<?php if ($error): ?>
<div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="panel" style="margin-bottom:1.5rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
    <div>
      <div class="label">Your wallet</div>
      <div style="font-family:var(--font-display);font-size:1.5rem;font-weight:700;"><?= money($wallet_balance) ?></div>
    </div>
    <a href="deposit.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Top up</a>
  </div>
</div>

<?php if (empty($levels)): ?>
  <div class="panel">
    <div class="empty-state">
      <i class="bi bi-layers"></i>
      <h3>No levels available</h3>
      <p>There are currently no active levels. Please check back later.</p>
    </div>
  </div>
<?php else: ?>
  <div class="level-grid">
    <?php foreach ($levels as $lv):
      $lid = (int)$lv['id'];
      $isCurrent = $lid === $current_level_id;
      $canAfford = $wallet_balance >= (float)$lv['price'];
    ?>
      <div class="level-card <?= $isCurrent ? 'current' : '' ?>">
        <div style="display:flex;justify-content:space-between;align-items:start;">
          <h3 style="font-family:var(--font-display);font-size:1.25rem;"><?= e($lv['name']) ?></h3>
          <?php if ($isCurrent): ?>
            <span class="badge badge-success">Current</span>
          <?php endif; ?>
        </div>
        <?php if (!empty($lv['description'])): ?>
          <p class="text-muted" style="font-size:0.9rem;margin-top:0.4rem;"><?= e($lv['description']) ?></p>
        <?php endif; ?>
        <div class="price"><?= money((float)$lv['price']) ?></div>
        <ul>
          <li><i class="bi bi-check2"></i> <?= (int)$lv['daily_tasks'] ?> daily tasks</li>
          <li><i class="bi bi-check2"></i> Referral bonus: <?= money((float)$lv['referral_bonus']) ?></li>
        </ul>
        <?php if ($isCurrent): ?>
          <button class="btn btn-ghost" disabled style="width:100%;opacity:0.6;">Already active</button>
        <?php else: ?>
          <form method="POST" action="select-level.php">
            <?= csrf_field() ?>
            <input type="hidden" name="level_id" value="<?= $lid ?>" />
            <button type="submit" class="btn btn-primary" style="width:100%;"
              <?= !$canAfford ? 'title="Insufficient balance — top up first"' : '' ?>>
              <?= $canAfford ? 'Activate level' : 'Insufficient balance' ?>
            </button>
          </form>
          <?php if (!$canAfford): ?>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;text-align:center;">
              Need <?= money((float)$lv['price'] - $wallet_balance) ?> more · <a href="deposit.php" style="color:var(--accent);">Top up</a>
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php layout_footer(); ?>
