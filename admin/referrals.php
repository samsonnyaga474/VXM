<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();
$res = $db->query(
    "SELECT r.*, u1.full_name AS referrer_name, u2.full_name AS referred_name
     FROM referrals r
     JOIN users u1 ON u1.id = r.referrer_id
     JOIN users u2 ON u2.id = r.referred_user_id
     ORDER BY r.created_at DESC LIMIT 100"
);
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
require __DIR__ . '/_layout.php';
admin_header('Referrals', 'referrals');
?>
<div class="panel">
  <div class="tx-list">
    <?php foreach ($rows as $r): ?>
      <div class="tx-row">
        <div class="tx-info">
          <div class="tx-desc"><?= e($r['referrer_name']) ?> → <?= e($r['referred_name']) ?></div>
          <div class="tx-date">Bonus <?= money((float)$r['bonus']) ?> · <?= e(date('M j, Y', strtotime($r['created_at']))) ?></div>
        </div>
        <span class="badge badge-<?= $r['status']==='paid'?'success':'pending' ?>"><?= e($r['status']) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><div class="empty-state"><h3>No referrals</h3></div><?php endif; ?>
  </div>
</div>
<?php admin_footer(); ?>
