<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$stmt = $db->prepare("SELECT full_name, referral_code FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

$referral_code = $u['referral_code'];
$baseUrl = rtrim(APP_URL, '/');
$referral_link = $baseUrl . '/register.html?ref=' . urlencode($referral_code);

/* Stats */
$stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($total_refs);
$stmt->fetch();
$stmt->close();

$stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'pending'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($pending_refs);
$stmt->fetch();
$stmt->close();

$stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'paid'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($paid_refs);
$stmt->fetch();
$stmt->close();

$stmt = $db->prepare("SELECT COALESCE(SUM(bonus), 0) FROM referrals WHERE referrer_id = ? AND status = 'paid'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($paid_amount);
$stmt->fetch();
$stmt->close();

/* List */
$referrals = [];
$stmt = $db->prepare(
    "SELECT r.id, r.bonus, r.status, r.created_at, r.paid_at, u.full_name, u.email
     FROM referrals r
     JOIN users u ON u.id = r.referred_user_id
     WHERE r.referrer_id = ?
     ORDER BY r.created_at DESC LIMIT 50"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $referrals[] = $row;
}
$stmt->close();

$triggerLabel = match (REFERRAL_BONUS_TRIGGER) {
    'on_level_purchase' => 'when your referral activates a level',
    'on_first_task' => 'when your referral completes their first task',
    'on_registration' => 'when your referral registers',
    default => 'when the referral qualifies (admin or system rule)',
};

layout_header('Referrals', 'referrals');
?>

<div class="stat-grid">
  <div class="stat-card highlight">
    <div class="label">Total referrals</div>
    <div class="value"><?= (int)$total_refs ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Pending</div>
    <div class="value"><?= (int)$pending_refs ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Paid</div>
    <div class="value"><?= (int)$paid_refs ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Referral earnings</div>
    <div class="value"><?= money((float)$paid_amount) ?></div>
  </div>
</div>

<div class="panel">
  <h2 class="panel-title" style="margin-bottom:0.75rem;">Your referral link</h2>
  <p class="text-muted" style="font-size:0.9rem;margin-bottom:1rem;">
    Share this link or code. You earn a bonus <?= e($triggerLabel) ?>.
  </p>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;">
    <input type="text" class="form-input" id="refLink" value="<?= e($referral_link) ?>" readonly style="flex:1;min-width:200px;" />
    <button type="button" class="btn btn-primary btn-sm" id="copyLink"><i class="bi bi-clipboard"></i> Copy link</button>
  </div>
  <div style="margin-top:1rem;">
    <span class="text-muted" style="font-size:0.85rem;">Code:</span>
    <strong style="font-family:var(--font-display);letter-spacing:0.08em;margin-left:0.5rem;"><?= e($referral_code) ?></strong>
  </div>
</div>

<div class="panel">
  <div class="panel-header">
    <h2 class="panel-title">Referral list</h2>
  </div>
  <?php if (empty($referrals)): ?>
    <div class="empty-state">
      <i class="bi bi-people"></i>
      <h3>No referrals yet</h3>
      <p>Share your link to start building your network.</p>
    </div>
  <?php else: ?>
    <div class="tx-list">
      <?php foreach ($referrals as $r):
        $badge = match($r['status']) {
          'paid' => 'badge-success',
          'pending' => 'badge-pending',
          'qualified' => 'badge-info',
          default => 'badge-failed'
        };
      ?>
        <div class="tx-row">
          <div class="tx-icon credit"><i class="bi bi-person-plus"></i></div>
          <div class="tx-info">
            <div class="tx-desc"><?= e($r['full_name']) ?></div>
            <div class="tx-date"><?= e(date('M j, Y', strtotime($r['created_at']))) ?> · Bonus <?= money((float)$r['bonus']) ?></div>
          </div>
          <span class="badge <?= $badge ?>"><?= e($r['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
document.getElementById('copyLink')?.addEventListener('click', function() {
  const input = document.getElementById('refLink');
  input.select();
  navigator.clipboard?.writeText(input.value).then(() => {
    this.innerHTML = '<i class="bi bi-check2"></i> Copied';
    setTimeout(() => { this.innerHTML = '<i class="bi bi-clipboard"></i> Copy link'; }, 2000);
  });
});
</script>

<?php layout_footer(); ?>
