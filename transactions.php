<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$typeFilter = $_GET['type'] ?? '';
$allowedTypes = ['deposit','task_reward','referral_bonus','level_purchase','withdrawal','refund','adjustment'];

$where = "user_id = ?";
$params = [$user_id];
$types = 'i';

if ($typeFilter && in_array($typeFilter, $allowedTypes, true)) {
    $where .= " AND type = ?";
    $params[] = $typeFilter;
    $types .= 's';
}

$sql = "SELECT id, type, amount, balance_before, balance_after, status, reference, description, created_at
        FROM transactions WHERE $where ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$transactions = [];
while ($row = $res->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

$typeLabels = [
  'deposit' => 'Deposit', 'task_reward' => 'Task reward', 'referral_bonus' => 'Referral bonus',
  'level_purchase' => 'Level purchase', 'withdrawal' => 'Withdrawal', 'refund' => 'Refund', 'adjustment' => 'Adjustment'
];

layout_header('Transactions', 'transactions');
?>

<div class="panel" style="margin-bottom:1.25rem;">
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a href="transactions.php" class="btn btn-sm <?= $typeFilter === '' ? 'btn-primary' : 'btn-ghost' ?>">All</a>
    <?php foreach ($allowedTypes as $t): ?>
      <a href="transactions.php?type=<?= urlencode($t) ?>"
         class="btn btn-sm <?= $typeFilter === $t ? 'btn-primary' : 'btn-ghost' ?>">
        <?= e($typeLabels[$t] ?? $t) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <?php if (empty($transactions)): ?>
    <div class="empty-state">
      <i class="bi bi-arrow-left-right"></i>
      <h3>No transactions</h3>
      <p>Activity will appear here as you deposit, earn, and withdraw.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($transactions as $tx):
        $amt = (float)$tx['amount'];
        $isCredit = $amt >= 0;
        $label = $typeLabels[$tx['type']] ?? $tx['type'];
      ?>
        <div class="tx-row">
          <div class="tx-icon <?= $isCredit ? 'credit' : 'debit' ?>">
            <i class="bi bi-<?= $isCredit ? 'arrow-down-left' : 'arrow-up-right' ?>"></i>
          </div>
          <div class="tx-info">
            <div class="tx-desc"><?= e($tx['description'] ?: $label) ?></div>
            <div class="tx-date">
              <?= e(date('M j, Y · H:i', strtotime($tx['created_at']))) ?>
              · <?= e($label) ?>
              <?php if ($tx['reference']): ?> · Ref <?= e($tx['reference']) ?><?php endif; ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div class="tx-amount <?= $isCredit ? 'positive' : 'negative' ?>">
              <?= $isCredit ? '+' : '' ?><?= money($amt) ?>
            </div>
            <div style="font-size:0.7rem;color:var(--text-muted);">
              Bal <?= money((float)$tx['balance_after']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>
