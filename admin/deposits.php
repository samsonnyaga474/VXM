<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();
$res = $db->query(
    "SELECT d.*, u.full_name, u.email FROM deposits d JOIN users u ON u.id = d.user_id
     ORDER BY d.created_at DESC LIMIT 100"
);
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
require __DIR__ . '/_layout.php';
admin_header('Deposits', 'deposits');
?>
<div class="panel">
  <?php if (empty($rows)): ?>
    <div class="empty-state"><h3>No deposits</h3></div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($rows as $d): ?>
        <div class="tx-row">
          <div class="tx-info">
            <div class="tx-desc"><?= e($d['full_name']) ?> · <?= money((float)$d['amount']) ?></div>
            <div class="tx-date"><?= e($d['phone']) ?> · <?= e(date('M j, Y H:i', strtotime($d['created_at']))) ?>
              <?php if ($d['mpesa_receipt']): ?> · <?= e($d['mpesa_receipt']) ?><?php endif; ?>
            </div>
          </div>
          <span class="badge badge-<?= $d['status']==='completed'?'success':($d['status']==='pending'||$d['status']==='processing'?'pending':'failed') ?>"><?= e($d['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
