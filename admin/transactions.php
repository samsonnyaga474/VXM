<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();
$res = $db->query(
    "SELECT t.*, u.full_name FROM transactions t JOIN users u ON u.id = t.user_id
     ORDER BY t.created_at DESC LIMIT 150"
);
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
require __DIR__ . '/_layout.php';
admin_header('Transactions', 'transactions');
?>
<div class="panel">
  <div class="tx-list">
    <?php foreach ($rows as $tx):
      $amt = (float)$tx['amount'];
    ?>
      <div class="tx-row">
        <div class="tx-info">
          <div class="tx-desc"><?= e($tx['full_name']) ?> · <?= e($tx['type']) ?></div>
          <div class="tx-date"><?= e($tx['description'] ?? '') ?> · <?= e(date('M j, Y H:i', strtotime($tx['created_at']))) ?></div>
        </div>
        <div class="tx-amount <?= $amt >= 0 ? 'positive' : 'negative' ?>"><?= $amt >= 0 ? '+' : '' ?><?= money($amt) ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><div class="empty-state"><h3>No transactions</h3></div><?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
