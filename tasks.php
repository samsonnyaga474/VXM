<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$stmt = $db->prepare("SELECT level_id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($level_id);
$stmt->fetch();
$stmt->close();
$level_id = (int)$level_id;

$level_name = '';
$daily_limit = 0;
if ($level_id > 0) {
    $stmt = $db->prepare("SELECT name, daily_tasks FROM levels WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param('i', $level_id);
    $stmt->execute();
    $lv = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($lv) {
        $level_name = $lv['name'];
        $daily_limit = (int)$lv['daily_tasks'];
    } else {
        $level_id = 0;
    }
}

$today_completed = 0;
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM user_tasks WHERE user_id = ? AND DATE(completed_at) = CURDATE()"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($today_completed);
$stmt->fetch();
$stmt->close();
$today_completed = (int)$today_completed;
$remaining = max(0, $daily_limit - $today_completed);
$progress_pct = $daily_limit > 0 ? min(100, round(($today_completed / $daily_limit) * 100)) : 0;

$tasks = [];
if ($level_id > 0) {
    $stmt = $db->prepare(
        "SELECT t.id, t.title, t.description, t.reward, t.status,
                CASE WHEN ut.id IS NOT NULL THEN 1 ELSE 0 END AS done_today
         FROM tasks t
         LEFT JOIN user_tasks ut ON ut.task_id = t.id AND ut.user_id = ? AND DATE(ut.completed_at) = CURDATE()
         WHERE t.level_id = ? AND t.status = 'active'
         ORDER BY t.id ASC"
    );
    $stmt->bind_param('ii', $user_id, $level_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }
    $stmt->close();
}

$flash = '';
$flashType = 'info';
if (isset($_GET['completed']) && $_GET['completed'] === 'success') {
    $flash = 'Task completed. Reward has been added to your wallet.';
    $flashType = 'success';
}
if (isset($_GET['error'])) {
    $map = [
        'already_completed' => 'You already completed this task today.',
        'daily_limit' => 'You have reached your daily task limit for today.',
        'not_eligible' => 'This task is not available for your level.',
        'inactive' => 'This task is no longer active.',
        'not_found' => 'Task not found.',
        'failed' => 'Could not complete the task. Please try again.',
        'invalid_task' => 'Invalid task.',
        'method' => 'Invalid request method.',
        'no_level' => 'Select a level first.',
    ];
    $flash = $map[$_GET['error']] ?? 'Something went wrong.';
    $flashType = 'error';
}

layout_header('Tasks', 'tasks');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flashType) ?>">
  <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <?= e($flash) ?>
</div>
<?php endif; ?>

<?php if ($level_id <= 0): ?>
  <div class="panel">
    <div class="empty-state">
      <i class="bi bi-layers"></i>
      <h3>Choose a level first</h3>
      <p>Tasks are unlocked when you activate a level.</p>
      <a href="levels.php" class="btn btn-primary" style="margin-top:1rem;">View levels</a>
    </div>
  </div>
<?php else: ?>

<div class="panel">
  <div class="panel-header">
    <div>
      <h2 class="panel-title"><?= e($level_name) ?> · Daily missions</h2>
      <p class="text-muted" style="font-size:0.85rem;margin-top:0.25rem;">Rewards are calculated and paid on the server.</p>
    </div>
  </div>
  <div style="margin-bottom:0.5rem;display:flex;justify-content:space-between;font-size:0.9rem;">
    <span><strong><?= $today_completed ?></strong> of <strong><?= $daily_limit ?></strong> completed today</span>
    <span class="text-muted"><?= $remaining ?> remaining</span>
  </div>
  <div class="progress-bar" style="margin-bottom:1.5rem;">
    <div class="progress-fill" style="width:<?= $progress_pct ?>%"></div>
  </div>

  <?php if (empty($tasks)): ?>
    <div class="empty-state">
      <i class="bi bi-check2-square"></i>
      <h3>No tasks available</h3>
      <p>There are no active tasks for your level at the moment.</p>
    </div>
  <?php else: ?>
    <div class="task-list">
      <?php foreach ($tasks as $t):
        $done = (int)$t['done_today'] === 1;
      ?>
        <div class="task-item <?= $done ? 'completed' : '' ?>">
          <div class="task-meta">
            <h4><?= e($t['title']) ?></h4>
            <p><?= e($t['description'] ?: ($done ? 'Completed today' : 'Ready to complete')) ?></p>
          </div>
          <div class="task-reward">+<?= money((float)$t['reward']) ?></div>
          <?php if ($done): ?>
            <span class="badge badge-success">Completed</span>
          <?php elseif ($remaining <= 0): ?>
            <span class="badge badge-pending">Limit reached</span>
          <?php else: ?>
            <form method="POST" action="complete-task.php" style="margin:0;">
              <?= csrf_field() ?>
              <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>" />
              <button type="submit" class="btn btn-primary btn-sm">Complete</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php layout_footer(); ?>
