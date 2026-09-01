<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.html');
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($email) || empty($password)) {
    redirect('login.html?error=empty');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('login.html?error=email');
}

if (is_login_rate_limited($email, $ip)) {
    redirect('login.html?error=locked');
}

$db = db();

$stmt = $db->prepare(
    "SELECT id, full_name, email, password, referral_code, level_id,
            wallet_balance, total_earnings, total_withdrawals, status, is_admin
     FROM users WHERE email = ? LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    record_login_attempt($email, $ip);
    $stmt->close();
    redirect('login.html?error=invalid');
}

$user = $result->fetch_assoc();
$stmt->close();

if ($user['status'] !== 'active') {
    redirect('login.html?error=inactive');
}

if (!password_verify($password, $user['password'])) {
    record_login_attempt($email, $ip);
    redirect('login.html?error=invalid');
}

clear_login_attempts($email);

session_regenerate_id(true);

$_SESSION['user_id']           = (int)$user['id'];
$_SESSION['full_name']         = $user['full_name'];
$_SESSION['email']             = $user['email'];
$_SESSION['referral_code']     = $user['referral_code'];
$_SESSION['level_id']          = $user['level_id'];
$_SESSION['wallet_balance']    = $user['wallet_balance'];
$_SESSION['total_earnings']    = $user['total_earnings'];
$_SESSION['total_withdrawals'] = $user['total_withdrawals'];
$_SESSION['is_admin']          = (bool)$user['is_admin'];
$_SESSION['logged_in']         = true;
$_SESSION['_created']          = time();

$stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
$uid = (int)$user['id'];
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->close();

redirect('dashboard.php');
