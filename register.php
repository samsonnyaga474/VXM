<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.html');
}

$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$password  = $_POST['password'] ?? '';
$confirm   = $_POST['confirm_password'] ?? '';
$ref_code  = trim($_POST['referral_code'] ?? '');

if ($full_name === '' || $email === '' || $password === '') {
    redirect('register.html?error=empty');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('register.html?error=email');
}
if (strlen($password) < PASSWORD_MIN_LENGTH) {
    redirect('register.html?error=password_short');
}
if ($password !== $confirm) {
    redirect('register.html?error=password_mismatch');
}

$db = db();

$stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    redirect('register.html?error=email_exists');
}
$stmt->close();

$referred_by = null;
$referrer_id = null;
if ($ref_code !== '') {
    $stmt = $db->prepare("SELECT id, referral_code, email FROM users WHERE referral_code = ? LIMIT 1");
    $stmt->bind_param('s', $ref_code);
    $stmt->execute();
    $ref = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($ref) {
        // Prevent registering with own code only matters for existing users; new user can't own code yet.
        // Block if referral code email matches the registering email (same person).
        if (strcasecmp($ref['email'], $email) === 0) {
            redirect('register.html?error=registration_failed');
        }
        $referrer_id = (int)$ref['id'];
        $referred_by = $ref['referral_code'];
    }
}

$new_code = generate_referral_code($db);
$hashed = password_hash($password, PASSWORD_DEFAULT);

$db->begin_transaction();
try {
    $stmt = $db->prepare(
        "INSERT INTO users (full_name, email, phone, password, referral_code, referred_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('ssssss', $full_name, $email, $phone, $hashed, $new_code, $referred_by);
    if (!$stmt->execute()) {
        throw new RuntimeException('insert');
    }
    $new_user_id = (int)$stmt->insert_id;
    $stmt->close();

    if ($referrer_id !== null && $referrer_id !== $new_user_id) {
        // Bonus calculated at level purchase (REFERRAL_PERCENTAGE of level price)
        $bonus = 0.0;
        $stmt = $db->prepare(
            "INSERT INTO referrals (referrer_id, referred_user_id, bonus, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $stmt->bind_param('iid', $referrer_id, $new_user_id, $bonus);
        $stmt->execute();
        $stmt->close();
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    redirect('register.html?error=registration_failed');
}

redirect('login.html?registered=success');
