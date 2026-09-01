<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $daily = (int)($_POST['daily_tasks'] ?? 0);
        $bonus = (float)($_POST['referral_bonus'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE levels SET name=?, price=?, daily_tasks=?, referral_bonus=?, description=?, status=? WHERE id=?");
            $stmt->bind_param('sdidssi', $name, $price, $daily, $bonus, $desc, $status, $id);
        } else {
            $stmt = $db->prepare("INSERT INTO levels (name, price, daily_tasks, referral_bonus, description, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('sdidss', $name, $price, $daily, $bonus, $desc, $status);
        }
        $stmt->execute();
        $stmt->close();
        redirect('levels.php?saved=1');
    }
}

$levels = [];
$res = $db->query("SELECT * FROM levels ORDER BY sort_order ASC, price ASC");
while ($r = $res->fetch_assoc()) $levels[] = $r;

require __DIR__ . '/_layout.php';
admin_header('Levels', 'levels');
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Level saved.</div><?php endif; ?>

<div class="panel" style="margin-bottom:1.5rem;">
  <h2 class="panel-title" style="margin-bottom:1rem;">Add / edit level</h2>
  <form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="lvl-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save" />
    <input type="hidden" name="id" id="lvlId" value="0" />
    <div class="form-group"><label class="form-label">Name</label><input class="form-input" name="name" id="lvlName" required /></div>
    <div class="form-group"><label class="form-label">Price</label><input type="number" step="0.01" class="form-input" name="price" id="lvlPrice" required /></div>
    <div class="form-group"><label class="form-label">Daily tasks</label><input type="number" class="form-input" name="daily_tasks" id="lvlDaily" required /></div>
    <div class="form-group"><label class="form-label">Referral bonus</label><input type="number" step="0.01" class="form-input" name="referral_bonus" id="lvlBonus" /></div>
    <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Description</label><input class="form-input" name="description" id="lvlDesc" /></div>
    <div class="form-group"><label class="form-label">Status</label>
      <select class="form-input" name="status" id="lvlStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select>
    </div>
    <div style="align-self:end;"><button class="btn btn-primary">Save level</button></div>
  </form>
</div>

<div class="panel">
  <div class="tx-list">
    <?php foreach ($levels as $lv): ?>
      <div class="tx-row">
        <div class="tx-info">
          <div class="tx-desc"><?= e($lv['name']) ?> · <?= money((float)$lv['price']) ?></div>
          <div class="tx-date"><?= (int)$lv['daily_tasks'] ?> tasks/day · Bonus <?= money((float)$lv['referral_bonus']) ?> · <?= e($lv['status']) ?></div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick='editLevel(<?= json_encode($lv) ?>)'>Edit</button>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
function editLevel(lv) {
  document.getElementById('lvlId').value = lv.id;
  document.getElementById('lvlName').value = lv.name;
  document.getElementById('lvlPrice').value = lv.price;
  document.getElementById('lvlDaily').value = lv.daily_tasks;
  document.getElementById('lvlBonus').value = lv.referral_bonus;
  document.getElementById('lvlDesc').value = lv.description || '';
  document.getElementById('lvlStatus').value = lv.status;
  window.scrollTo({top:0,behavior:'smooth'});
}
</script>
<style>@media(max-width:700px){.lvl-form{grid-template-columns:1fr!important;}}</style>
<?php admin_footer(); ?>
