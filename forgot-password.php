<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/Mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('forgot-password.html');
}

$email = trim($_POST['email'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Always show same response to avoid email enumeration
$successRedirect = 'forgot-password.html?reset=sent';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect($successRedirect);
}

// Simple rate limit by IP using login_attempts table pattern
$db = db();
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
$stmt->bind_param('s', $ip);
$stmt->execute();
$stmt->bind_result($attempts);
$stmt->fetch();
$stmt->close();
if ($attempts >= 10) {
    redirect($successRedirect);
}
record_login_attempt($email, $ip);

$stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    $stmt->close();
    redirect($successRedirect);
}
$user = $res->fetch_assoc();
$stmt->close();

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY_MINUTES * 60);

$stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
$uid = (int)$user['id'];
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->close();

$stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $uid, $token, $expires);
$stmt->execute();
$stmt->close();

$resetUrl = rtrim(APP_URL, '/') . '/reset-password.php?token=' . urlencode($token);

$html = '<p>Hello ' . htmlspecialchars($user['full_name']) . ',</p>'
      . '<p>You requested a password reset for your VXM account.</p>'
      . '<p><a href="' . htmlspecialchars($resetUrl) . '">Reset your password</a></p>'
      . '<p>This link expires in ' . RESET_TOKEN_EXPIRY_MINUTES . ' minutes.</p>'
      . '<p>If you did not request this, you can ignore this email.</p>';

Mail::send($email, 'Reset your VXM password', $html, strip_tags($html));

// Development: also log the link clearly
if (VXM_ENV === 'development') {
    $logDir = STORAGE_PATH . '/logs';
    @file_put_contents(
        $logDir . '/password_reset_dev.log',
        date('c') . " $email $resetUrl\n",
        FILE_APPEND
    );
}

redirect($successRedirect);
