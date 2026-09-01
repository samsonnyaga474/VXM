<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

$stats = [
  'users' => 0, 'active_users' => 0, 'pending_wd' => 0, 'pending_wd_amount' => 0,
  'total_deposits' => 0, 'total_earnings' => 0
];

$r = $db->query("SELECT COUNT(*) AS c FROM users");
$stats['users'] = (int)$r->fetch_assoc()['c'];
$r = $db->query("SELECT COUNT(*) AS c FROM users WHERE status='active'");
$stats['active_users'] = (int)$r->fetch_assoc()['c'];
$r = $db->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM withdrawals WHERE status='pending'");
$row = $r->fetch_assoc();
$stats['pending_wd'] = (int)$row['c'];
$stats['pending_wd_amount'] = (float)$row['s'];
$r = $db->query("SELECT COALESCE(SUM(amount),0) AS s FROM deposits WHERE status='completed'");
$stats['total_deposits'] = (float)$r->fetch_assoc()['s'];
$r = $db->query("SELECT COALESCE(SUM(amount),0) AS s FROM earnings");
$stats['total_earnings'] = (float)$r->fetch_assoc()['s'];

require __DIR__ . '/_layout.php';
admin_header('Dashboard', 'dashboard');
?>
<div class="stat-grid">
  <div class="stat-card highlight"><div class="label">Users</div><div class="value"><?= $stats['users'] ?></div><div class="sub"><?= $stats['active_users'] ?> active</div></div>
  <div class="stat-card"><div class="label">Pending withdrawals</div><div class="value"><?= $stats['pending_wd'] ?></div><div class="sub"><?= money($stats['pending_wd_amount']) ?></div></div>
  <div class="stat-card"><div class="label">Total deposits</div><div class="value"><?= money($stats['total_deposits']) ?></div></div>
  <div class="stat-card"><div class="label">Total earnings paid</div><div class="value"><?= money($stats['total_earnings']) ?></div></div>
</div>
<div class="panel">
  <div class="panel-header"><h2 class="panel-title">Quick links</h2></div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="withdrawals.php" class="btn btn-primary btn-sm">Review withdrawals</a>
    <a href="users.php" class="btn btn-ghost btn-sm">Manage users</a>
    <a href="levels.php" class="btn btn-ghost btn-sm">Levels</a>
    <a href="tasks.php" class="btn btn-ghost btn-sm">Tasks</a>
    <a href="deposits.php" class="btn btn-ghost btn-sm">Deposits</a>
  </div>
</div>
<?php admin_footer(); ?>
