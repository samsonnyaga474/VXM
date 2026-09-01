<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

$status = $_GET['status'] ?? 'pending';
$allowed = ['pending','approved','rejected','processing','all'];
if (!in_array($status, $allowed, true)) $status = 'pending';

$sql = "SELECT w.*, u.full_name, u.email
        FROM withdrawals w JOIN users u ON u.id = w.user_id";
if ($status !== 'all') {
    $sql .= " WHERE w.status = ?";
}
$sql .= " ORDER BY w.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
if ($status !== 'all') {
    $stmt->bind_param('s', $status);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$msg = '';
if (isset($_GET['success'])) {
    $msg = $_GET['success'] === 'approved' ? 'Withdrawal approved.' : 'Withdrawal rejected and funds returned.';
}

require __DIR__ . '/_layout.php';
admin_header('Withdrawals', 'withdrawals');
?>
<?php if ($msg): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= e($msg) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:1rem;">
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php foreach (['pending','approved','rejected','all'] as $s): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s?'btn-primary':'btn-ghost' ?>"><?= ucfirst($s) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <?php if (empty($rows)): ?>
    <div class="empty-state"><i class="bi bi-cash-stack"></i><h3>No withdrawals</h3></div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($rows as $w): ?>
        <div class="tx-row" style="flex-wrap:wrap;">
          <div class="tx-info" style="flex:1;min-width:180px;">
            <div class="tx-desc"><?= e($w['full_name']) ?> · <?= money((float)$w['amount']) ?></div>
            <div class="tx-date"><?= e($w['phone']) ?> · <?= e(date('M j, Y H:i', strtotime($w['created_at']))) ?> · <?= e($w['status']) ?></div>
          </div>
          <?php if ($w['status'] === 'pending'): ?>
            <form method="POST" action="../approve-withdrawal.php" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="withdrawal_id" value="<?= (int)$w['id'] ?>" />
              <button class="btn btn-primary btn-sm">Approve</button>
            </form>
            <form method="POST" action="../reject-withdrawal.php" style="margin:0;display:flex;gap:0.4rem;">
              <?= csrf_field() ?>
              <input type="hidden" name="withdrawal_id" value="<?= (int)$w['id'] ?>" />
              <input type="text" name="admin_note" class="form-input" placeholder="Note" style="width:120px;padding:0.4rem;" />
              <button class="btn btn-ghost btn-sm" style="color:var(--danger);">Reject</button>
            </form>
          <?php else: ?>
            <span class="badge badge-<?= $w['status']==='approved'?'success':'failed' ?>"><?= e($w['status']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
