<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$flash = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $subject = trim($_POST['subject'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $message = trim($_POST['message'] ?? '');
        $allowedCat = ['general', 'payment', 'withdrawal', 'account', 'technical'];
        if (!in_array($category, $allowedCat, true)) $category = 'general';
        if (strlen($subject) < 3 || strlen($message) < 5) {
            $flash = 'Subject and message are required.';
            $flashType = 'error';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO support_tickets (user_id, subject, category, status) VALUES (?, ?, ?, 'open')"
            );
            $stmt->bind_param('iss', $user_id, $subject, $category);
            $stmt->execute();
            $ticketId = (int)$stmt->insert_id;
            $stmt->close();

            $stmt = $db->prepare(
                "INSERT INTO support_messages (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)"
            );
            $stmt->bind_param('iis', $ticketId, $user_id, $message);
            $stmt->execute();
            $stmt->close();

            redirect('support.php?ticket=' . $ticketId . '&created=1');
        }
    }

    if ($action === 'reply') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        // Ownership check
        $stmt = $db->prepare("SELECT id, status FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $ticketId, $user_id);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$t) {
            $flash = 'Ticket not found.';
            $flashType = 'error';
        } elseif (in_array($t['status'], ['closed', 'resolved'], true)) {
            $flash = 'This ticket is closed.';
            $flashType = 'error';
        } elseif (strlen($message) < 1) {
            $flash = 'Message cannot be empty.';
            $flashType = 'error';
        } else {
            $stmt = $db->prepare(
                "INSERT INTO support_messages (ticket_id, user_id, is_admin, message) VALUES (?, ?, 0, ?)"
            );
            $stmt->bind_param('iis', $ticketId, $user_id, $message);
            $stmt->execute();
            $stmt->close();
            $stmt = $db->prepare("UPDATE support_tickets SET status='open', updated_at=NOW() WHERE id=?");
            $stmt->bind_param('i', $ticketId);
            $stmt->execute();
            $stmt->close();
            redirect('support.php?ticket=' . $ticketId . '&replied=1');
        }
    }
}

$viewId = (int)($_GET['ticket'] ?? 0);
$ticket = null;
$messages = [];

if ($viewId > 0) {
    $stmt = $db->prepare(
        "SELECT * FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $viewId, $user_id);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ticket) {
        $stmt = $db->prepare(
            "SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC"
        );
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $messages[] = $row;
        $stmt->close();
    }
}

$tickets = [];
$stmt = $db->prepare(
    "SELECT id, subject, category, status, created_at, updated_at
     FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC LIMIT 50"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $tickets[] = $row;
$stmt->close();

if (isset($_GET['created'])) { $flash = 'Ticket created.'; $flashType = 'success'; }
if (isset($_GET['replied'])) { $flash = 'Reply sent.'; $flashType = 'success'; }

layout_header('Support', 'support');
?>
<?php if ($flash): ?>
<div class="alert alert-<?= e($flashType) ?>"><i class="bi bi-info-circle"></i> <?= e($flash) ?></div>
<?php endif; ?>

<?php if ($ticket): ?>
  <div style="margin-bottom:1rem;"><a href="support.php" class="btn btn-ghost btn-sm">← All tickets</a></div>
  <div class="panel">
    <div class="panel-header">
      <div>
        <h2 class="panel-title"><?= e($ticket['subject']) ?></h2>
        <div class="text-muted" style="font-size:0.85rem;margin-top:0.25rem;">
          #<?= (int)$ticket['id'] ?> · <?= e($ticket['category']) ?> ·
          <span class="badge badge-<?= $ticket['status']==='open'?'pending':($ticket['status']==='resolved'?'success':'info') ?>"><?= e($ticket['status']) ?></span>
        </div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:1.5rem;">
      <?php foreach ($messages as $m): ?>
        <div style="padding:1rem;border-radius:var(--radius-md);background:<?= $m['is_admin'] ? 'rgba(56,189,248,0.08)' : 'var(--bg-elevated)' ?>;border:1px solid var(--border-subtle);">
          <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.4rem;">
            <?= $m['is_admin'] ? 'Support team' : 'You' ?> · <?= e(date('M j, Y H:i', strtotime($m['created_at']))) ?>
          </div>
          <div style="white-space:pre-wrap;font-size:0.95rem;"><?= e($m['message']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!in_array($ticket['status'], ['closed', 'resolved'], true)): ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reply" />
        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>" />
        <div class="form-group">
          <label class="form-label">Your reply</label>
          <textarea class="form-input" name="message" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send reply</button>
      </form>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="sup-grid">
    <div class="panel">
      <h2 class="panel-title" style="margin-bottom:1.25rem;">New ticket</h2>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create" />
        <div class="form-group">
          <label class="form-label">Subject</label>
          <input type="text" class="form-input" name="subject" required maxlength="200" />
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select class="form-input" name="category">
            <option value="general">General</option>
            <option value="payment">Payment / Deposit</option>
            <option value="withdrawal">Withdrawal</option>
            <option value="account">Account</option>
            <option value="technical">Technical</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <textarea class="form-input" name="message" rows="4" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Submit ticket</button>
      </form>
    </div>
    <div class="panel">
      <h2 class="panel-title" style="margin-bottom:1rem;">Your tickets</h2>
      <?php if (empty($tickets)): ?>
        <div class="empty-state" style="padding:2rem;"><i class="bi bi-chat-dots"></i><h3>No tickets yet</h3></div>
      <?php else: ?>
        <div class="tx-list">
          <?php foreach ($tickets as $t): ?>
            <a href="support.php?ticket=<?= (int)$t['id'] ?>" class="tx-row" style="text-decoration:none;color:inherit;">
              <div class="tx-info">
                <div class="tx-desc"><?= e($t['subject']) ?></div>
                <div class="tx-date"><?= e($t['category']) ?> · <?= e(date('M j, Y', strtotime($t['created_at']))) ?></div>
              </div>
              <span class="badge badge-<?= $t['status']==='open'?'pending':($t['status']==='resolved'?'success':'info') ?>"><?= e($t['status']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <style>@media(max-width:800px){.sup-grid{grid-template-columns:1fr!important;}}</style>
<?php endif; ?>
<?php layout_footer(); ?>
