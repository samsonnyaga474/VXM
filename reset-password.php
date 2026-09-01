<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = false;

if ($token === '') {
    $error = 'Invalid or missing reset token.';
} else {
    $db = db();
    $stmt = $db->prepare(
        "SELECT id, user_id, expires_at, used_at FROM password_resets
         WHERE token = ? LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reset || $reset['used_at'] || strtotime($reset['expires_at']) < time()) {
        $error = 'This password reset link is invalid or has expired.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $uid = (int)$reset['user_id'];
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('si', $hash, $uid);
            $stmt->execute();
            $stmt->close();

            $rid = (int)$reset['id'];
            $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $rid);
            $stmt->execute();
            $stmt->close();

            // Invalidate other tokens for this user
            $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ? AND id != ?");
            $stmt->bind_param('ii', $uid, $rid);
            $stmt->execute();
            $stmt->close();

            redirect('login.html?reset=success');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password | VXM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/vxm.css" />
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;">
  <div class="card" style="width:100%;max-width:420px;">
    <h1 class="display" style="font-size:1.5rem;margin-bottom:0.5rem;">Reset password</h1>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin:1rem 0;"><?= e($error) ?></div>
      <a href="forgot-password.html" class="btn btn-ghost" style="width:100%;">Request a new link</a>
    <?php else: ?>
      <p class="text-muted" style="margin-bottom:1.25rem;font-size:0.9rem;">Choose a new password for your account.</p>
      <form method="POST">
        <input type="hidden" name="token" value="<?= e($token) ?>" />
        <div class="form-group">
          <label class="form-label">New password</label>
          <input type="password" class="form-input" name="password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label">Confirm password</label>
          <input type="password" class="form-input" name="confirm_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" required />
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
