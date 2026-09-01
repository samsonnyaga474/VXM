<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();
$db = db();

$viewId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $ticketId = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'reply' && $ticketId > 0) {
        $message = trim($_POST['message'] ?? '');
        $newStatus = $_POST['status'] ?? 'in_progress';
        $allowed = ['open','in_progress','resolved','closed'];
        if (!in_array($newStatus, $allowed, true)) $newStatus = 'in_progress';
        if (strlen($message) > 0) {
            $stmt = $db->prepare(
                "INSERT INTO support_messages (ticket_id, user_id, is_admin, message) VALUES (?, ?, 1, ?)"
            );
            $aid = $admin['id'];
            $stmt->bind_param('iis', $ticketId, $aid, $message);
            $stmt->execute();
            $stmt->close();

            // Notify user
            $stmt = $db->prepare("SELECT user_id, subject FROM support_tickets WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $ticketId);
            $stmt->execute();
            $t = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($t) {
                notify_user((int)$t['user_id'], 'support_reply', 'Support replied',
                    'A reply was added to your ticket: ' . $t['subject']);
            }
        }
        $stmt = $db->prepare("UPDATE support_tickets SET status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('si', $newStatus, $ticketId);
        $stmt->execute();
        $stmt->close();
        redirect('support.php?id=' . $ticketId . '&saved=1');
    }
}

require __DIR__ . '/_layout.php';

if ($viewId > 0) {
    $stmt = $db->prepare(
        "SELECT t.*, u.full_name, u.email FROM support_tickets t
         JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $viewId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $messages = [];
    if ($ticket) {
        $stmt = $db->prepare("SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $messages[] = $r;
        $stmt->close();
    }
    admin_header('Ticket #' . $viewId, 'support');
    if (!$ticket) {
        echo '<div class="alert alert-error">Ticket not found.</div>';
        admin_footer();
        exit;
    }
    ?>
    <div style="margin-bottom:1rem;"><a href="support.php" class="btn btn-ghost btn-sm">← All tickets</a></div>
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Saved.</div><?php endif; ?>
    <div class="panel">
      <h2 class="panel-title"><?= e($ticket['subject']) ?></h2>
      <p class="text-muted" style="font-size:0.85rem;margin:0.5rem 0 1rem;">
        <?= e($ticket['full_name']) ?> (<?= e($ticket['email']) ?>) · <?= e($ticket['category']) ?> · <?= e($ticket['status']) ?>
      </p>
      <?php foreach ($messages as $m): ?>
        <div style="padding:1rem;margin-bottom:0.75rem;border-radius:var(--radius-md);background:var(--bg-elevated);border:1px solid var(--border-subtle);">
          <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.35rem;">
            <?= $m['is_admin'] ? 'Admin' : 'User' ?> · <?= e(date('M j, Y H:i', strtotime($m['created_at']))) ?>
          </div>
          <div style="white-space:pre-wrap;"><?= e($m['message']) ?></div>
        </div>
      <?php endforeach; ?>
      <form method="POST" style="margin-top:1.25rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reply" />
        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
        <div class="form-group">
          <label class="form-label">Reply</label>
          <textarea class="form-input" name="message" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-input" name="status">
            <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary">Save</button>
      </form>
    </div>
    <?php
    admin_footer();
    exit;
}

$status = $_GET['status'] ?? 'open';
$sql = "SELECT t.*, u.full_name FROM support_tickets t JOIN users u ON u.id = t.user_id";
if ($status !== 'all') {
    $sql .= " WHERE t.status = ?";
}
$sql .= " ORDER BY t.updated_at DESC, t.created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
if ($status !== 'all') $stmt->bind_param('s', $status);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

admin_header('Support', 'support');
?>
<div class="panel" style="margin-bottom:1rem;">
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <?php foreach (['open','in_progress','resolved','closed','all'] as $s): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= $status===$s?'btn-primary':'btn-ghost' ?>"><?= $s ?></a>
    <?php endforeach; ?>
  </div>
</div>
<div class="panel">
  <?php if (empty($rows)): ?>
    <div class="empty-state"><h3>No tickets</h3></div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($rows as $t): ?>
        <a href="support.php?id=<?= (int)$t['id'] ?>" class="tx-row" style="text-decoration:none;color:inherit;">
          <div class="tx-info">
            <div class="tx-desc"><?= e($t['subject']) ?></div>
            <div class="tx-date"><?= e($t['full_name']) ?> · <?= e($t['category']) ?> · <?= e(date('M j, Y', strtotime($t['created_at']))) ?></div>
          </div>
          <span class="badge badge-pending"><?= e($t['status']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_footer(); ?>
