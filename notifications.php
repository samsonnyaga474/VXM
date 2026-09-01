<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'read_one') {
        $nid = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $nid, $user_id);
        $stmt->execute();
        $stmt->close();
        redirect('notifications.php');
    }
    if ($action === 'read_all') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        redirect('notifications.php');
    }
}

$notifications = [];
$stmt = $db->prepare(
    "SELECT id, type, title, message, is_read, created_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $notifications[] = $row;
$stmt->close();

$unread = 0;
foreach ($notifications as $n) {
    if (!(int)$n['is_read']) $unread++;
}

layout_header('Notifications', 'notifications');
?>
<div class="panel">
  <div class="panel-header">
    <h2 class="panel-title">Notifications <?= $unread ? "($unread unread)" : '' ?></h2>
    <?php if ($unread > 0): ?>
      <form method="POST" style="margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="read_all" />
        <button class="btn btn-ghost btn-sm">Mark all read</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if (empty($notifications)): ?>
    <div class="empty-state">
      <i class="bi bi-bell"></i>
      <h3>No notifications</h3>
      <p>Updates about deposits, tasks, withdrawals and support will appear here.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($notifications as $n): ?>
        <div class="tx-row" style="<?= !(int)$n['is_read'] ? 'border-color:var(--border-glow);' : '' ?>">
          <div class="tx-icon credit"><i class="bi bi-bell"></i></div>
          <div class="tx-info">
            <div class="tx-desc"><?= e($n['title']) ?></div>
            <div class="tx-date"><?= e($n['message']) ?></div>
            <div class="tx-date"><?= e(date('M j, Y · H:i', strtotime($n['created_at']))) ?></div>
          </div>
          <?php if (!(int)$n['is_read']): ?>
            <form method="POST" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="read_one" />
              <input type="hidden" name="id" value="<?= (int)$n['id'] ?>" />
              <button class="btn btn-ghost btn-sm">Mark read</button>
            </form>
          <?php else: ?>
            <span class="badge badge-info">Read</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php layout_footer(); ?>
