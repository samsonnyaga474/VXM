<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT id, full_name, email, phone, level_id, wallet_balance, total_earnings, status, is_admin, created_at
        FROM users";
$params = [];
$types = '';
if ($q !== '') {
    $sql .= " WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR referral_code LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}
$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$users = [];
while ($r = $res->fetch_assoc()) $users[] = $r;
$stmt->close();

/* Status toggle */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    require_csrf();
    $uid = (int)$_POST['user_id'];
    $new = $_POST['new_status'] === 'active' ? 'active' : 'suspended';
    if ($uid !== $admin['id']) {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $new, $uid);
        $stmt->execute();
        $stmt->close();
    }
    redirect('users.php?q=' . urlencode($q));
}

require __DIR__ . '/_layout.php';
admin_header('Users', 'users');
?>
<div class="panel" style="margin-bottom:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;">
    <input type="search" name="q" class="form-input" placeholder="Search name, email, phone, code…" value="<?= e($q) ?>" style="max-width:320px;" />
    <button class="btn btn-primary btn-sm">Search</button>
  </form>
</div>
<div class="panel">
  <?php if (empty($users)): ?>
    <div class="empty-state"><h3>No users found</h3></div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($users as $u): ?>
        <div class="tx-row">
          <div class="tx-info">
            <div class="tx-desc"><?= e($u['full_name']) ?> <?= $u['is_admin'] ? '<span class="badge badge-info">admin</span>' : '' ?></div>
            <div class="tx-date"><?= e($u['email']) ?> · <?= e($u['phone'] ?? '—') ?> · Bal <?= money((float)$u['wallet_balance']) ?> · <?= e($u['status']) ?></div>
          </div>
          <?php if ((int)$u['id'] !== $admin['id']): ?>
            <form method="POST" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>" />
              <input type="hidden" name="new_status" value="<?= $u['status']==='active'?'suspended':'active' ?>" />
              <button name="toggle_status" class="btn btn-ghost btn-sm"><?= $u['status']==='active'?'Suspend':'Activate' ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
