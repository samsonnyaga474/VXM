<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout_user.php';

$user = require_login();
$user_id = $user['id'];
$db = db();

$stmt = $db->prepare(
    "SELECT full_name, email, phone, referral_code, created_at FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

$flash = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (strlen($name) < 2) {
            $flash = 'Name is too short.';
            $flashType = 'error';
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ssi', $name, $phone, $user_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['full_name'] = $name;
            $u['full_name'] = $name;
            $u['phone'] = $phone;
            $flash = 'Profile updated.';
            $flashType = 'success';
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($hash);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current, $hash)) {
            $flash = 'Current password is incorrect.';
            $flashType = 'error';
        } elseif (strlen($new) < PASSWORD_MIN_LENGTH) {
            $flash = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            $flashType = 'error';
        } elseif ($new !== $confirm) {
            $flash = 'New passwords do not match.';
            $flashType = 'error';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $newHash, $user_id);
            $stmt->execute();
            $stmt->close();
            $flash = 'Password changed successfully.';
            $flashType = 'success';
        }
    }
}

layout_header('Account', 'account');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flashType) ?>">
  <i class="bi bi-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
  <?= e($flash) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;" class="acc-grid">
  <div class="panel">
    <h2 class="panel-title" style="margin-bottom:1.25rem;">Profile</h2>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile" />
      <div class="form-group">
        <label class="form-label">Full name</label>
        <input type="text" class="form-input" name="full_name" value="<?= e($u['full_name']) ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-input" value="<?= e($u['email']) ?>" disabled />
        <p class="text-muted" style="font-size:0.8rem;margin-top:0.3rem;">Email cannot be changed here.</p>
      </div>
      <div class="form-group">
        <label class="form-label">Phone</label>
        <input type="tel" class="form-input" name="phone" value="<?= e($u['phone'] ?? '') ?>" />
      </div>
      <div class="form-group">
        <label class="form-label">Referral code</label>
        <input type="text" class="form-input" value="<?= e($u['referral_code']) ?>" readonly />
      </div>
      <button type="submit" class="btn btn-primary">Save profile</button>
    </form>
  </div>

  <div class="panel">
    <h2 class="panel-title" style="margin-bottom:1.25rem;">Change password</h2>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password" />
      <div class="form-group">
        <label class="form-label">Current password</label>
        <input type="password" class="form-input" name="current_password" required />
      </div>
      <div class="form-group">
        <label class="form-label">New password</label>
        <input type="password" class="form-input" name="new_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Confirm new password</label>
        <input type="password" class="form-input" name="confirm_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required />
      </div>
      <button type="submit" class="btn btn-primary">Update password</button>
    </form>
  </div>
</div>

<div class="panel" style="margin-top:1.5rem;">
  <div class="label">Member since</div>
  <div style="font-family:var(--font-display);margin-top:0.25rem;"><?= e(date('F j, Y', strtotime($u['created_at']))) ?></div>
</div>

<style>
@media (max-width: 800px) { .acc-grid { grid-template-columns: 1fr !important; } }
</style>

<?php layout_footer(); ?>
