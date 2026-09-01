<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $level_id = (int)($_POST['level_id'] ?? 0) ?: null;
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $reward = (float)($_POST['reward'] ?? 0);
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE tasks SET level_id=?, title=?, description=?, reward=?, status=? WHERE id=?");
            $stmt->bind_param('issdsi', $level_id, $title, $desc, $reward, $status, $id);
        } else {
            $stmt = $db->prepare("INSERT INTO tasks (level_id, title, description, reward, status) VALUES (?,?,?,?,?)");
            $stmt->bind_param('issds', $level_id, $title, $desc, $reward, $status);
        }
        $stmt->execute();
        $stmt->close();
        redirect('tasks.php?saved=1');
    }
}

$levels = [];
$res = $db->query("SELECT id, name FROM levels ORDER BY price ASC");
while ($r = $res->fetch_assoc()) $levels[] = $r;

$tasks = [];
$res = $db->query(
    "SELECT t.*, l.name AS level_name FROM tasks t LEFT JOIN levels l ON l.id = t.level_id ORDER BY t.id DESC LIMIT 100"
);
while ($r = $res->fetch_assoc()) $tasks[] = $r;

require __DIR__ . '/_layout.php';
admin_header('Tasks', 'tasks');
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Task saved.</div><?php endif; ?>

<div class="panel" style="margin-bottom:1.5rem;">
  <h2 class="panel-title" style="margin-bottom:1rem;">Add / edit task</h2>
  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="tsk-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" id="tskId" value="0" />
    <div class="form-group"><label class="form-label">Title</label><input class="form-input" name="title" id="tskTitle" required /></div>
    <div class="form-group"><label class="form-label">Reward</label><input type="number" step="0.01" class="form-input" name="reward" id="tskReward" required /></div>
    <div class="form-group"><label class="form-label">Level</label>
      <select class="form-input" name="level_id" id="tskLevel">
        <option value="0">Any / none</option>
        <?php foreach ($levels as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Status</label>
      <select class="form-input" name="status" id="tskStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Description</label><input class="form-input" name="description" id="tskDesc" /></div>
    <div><button class="btn btn-primary">Save task</button></div>
  </form>
</div>

<div class="panel">
  <div class="tx-list">
    <?php foreach ($tasks as $t): ?>
      <div class="tx-row">
        <div class="tx-info">
          <div class="tx-desc"><?= e($t['title']) ?> · <?= money((float)$t['reward']) ?></div>
          <div class="tx-date"><?= e($t['level_name'] ?? 'No level') ?> · <?= e($t['status']) ?></div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick='editTask(<?= json_encode($t) ?>)'>Edit</button>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
function editTask(t) {
  document.getElementById('tskId').value = t.id;
  document.getElementById('tskTitle').value = t.title;
  document.getElementById('tskReward').value = t.reward;
  document.getElementById('tskLevel').value = t.level_id || 0;
  document.getElementById('tskStatus').value = t.status;
  document.getElementById('tskDesc').value = t.description || '';
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<style>@media(max-width:700px){.tsk-form{grid-template-columns:1fr!important;}}</style>
<?php admin_footer(); ?>
