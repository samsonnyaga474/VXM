<?php
/**
 * Public registration — GET shows form, POST creates the account.
 * CSRF, rate limiting, server-side validation, hashed passwords.
 */
require_once __DIR__ . '/includes/bootstrap.php';

vxm_session_start();

// Already logged in → dashboard
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$old = [
    'full_name'      => '',
    'email'          => '',
    'phone'          => '',
    'referral_code'  => trim((string)($_GET['ref'] ?? $_GET['referral_code'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please refresh the page and try again.';
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $confirm   = (string)($_POST['confirm_password'] ?? '');
    $ref_code  = strtoupper(trim((string)($_POST['referral_code'] ?? '')));
    $terms     = isset($_POST['terms']) && (string)$_POST['terms'] === '1';

    $old['full_name'] = $full_name;
    $old['email'] = $email;
    $old['phone'] = $phone;
    $old['referral_code'] = $ref_code;

    if (is_register_rate_limited($email, $ip)) {
        $errors[] = 'Too many registration attempts. Please wait a few minutes and try again.';
    }

    if ($full_name === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (strlen($full_name) < 2 || strlen($full_name) > 150) {
        $errors[] = 'Please enter a valid full name.';
    }
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($phone) > 20 || !preg_match('/^[0-9+ ]{7,20}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number for M-Pesa.';
    }
    if (strlen($ref_code) > 20) {
        $errors[] = 'Referral code is not valid.';
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen($password) > 200) {
        $errors[] = 'Password is too long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password confirmation does not match.';
    }
    if (!$terms) {
        $errors[] = 'You must agree to the Terms and Privacy Policy to create an account.';
    }

    if (empty($errors)) {
        record_register_attempt($email, $ip);
        $db = db();

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            $errors[] = 'An account with that email already exists. Try signing in instead.';
        } else {
            $stmt->close();

            $referred_by = null;
            $referrer_id = null;
            if ($ref_code !== '') {
                $stmt = $db->prepare("SELECT id, referral_code, email FROM users WHERE referral_code = ? LIMIT 1");
                $stmt->bind_param('s', $ref_code);
                $stmt->execute();
                $ref = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($ref && strcasecmp((string)$ref['email'], $email) !== 0) {
                    $referrer_id = (int)$ref['id'];
                    $referred_by = $ref['referral_code'];
                }
                // Invalid referral codes are ignored rather than blocking registration.
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
                $errors[] = 'We could not create your account. Please try again.';
            }
        }
    }

    if (empty($errors) && isset($new_user_id) && $new_user_id > 0) {
        // Sign the user in after successful registration (existing session system).
        session_regenerate_id(true);
        $_SESSION['user_id']        = $new_user_id;
        $_SESSION['full_name']      = $full_name;
        $_SESSION['email']          = $email;
        $_SESSION['referral_code']  = $new_code ?? '';
        $_SESSION['level_id']       = 0;
        $_SESSION['is_admin']       = false;
        $_SESSION['logged_in']      = true;
        $_SESSION['_created']       = time();
        redirect('dashboard.php?registered=1');
    }
}

$errorParam = $_GET['error'] ?? '';
$paramMap = [
    'empty' => 'Please fill in all required fields.',
    'invalid' => 'Please check the details you entered and try again.',
    'email' => 'Please enter a valid email address.',
    'password_short' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.',
    'password_mismatch' => 'Password confirmation does not match.',
    'email_exists' => 'An account with that email already exists. Try signing in instead.',
    'registration_failed' => 'We could not create your account. Please try again.',
    'locked' => 'Too many attempts. Please wait and try again.',
];
if ($errorParam !== '' && isset($paramMap[$errorParam]) && empty($errors)) {
    $errors[] = $paramMap[$errorParam];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#030712" />
  <link rel="icon" href="images/favicon.png" type="image/png" sizes="32x32" />
  <link rel="icon" href="images/favicon-16.png" type="image/png" sizes="16x16" />
  <link rel="apple-touch-icon" href="images/favicon-180.png" sizes="180x180" />
  <title>Create Account | VXM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="assets/css/vxm.css" />
</head>
<body>
  <div class="auth-shell">
    <div class="auth-card">
      <a href="index.html" class="logo">
        <img src="images/logo.jpg" alt="VXM" onerror="this.style.display='none'" />
      </a>
      <h1>Create your account</h1>
      <p class="auth-sub">Join VXM to access levels, daily tasks, and your wallet.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
          <?php foreach ($errors as $err): ?>
            <p style="margin:0.25rem 0;"><?= e($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form action="register.php" method="post" id="registerForm" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label" for="name">Full name</label>
          <input class="form-control" type="text" id="name" name="full_name" required
                 autocomplete="name" maxlength="150"
                 value="<?= e($old['full_name']) ?>"
                 placeholder="Your full name" />
        </div>
        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" required
                 autocomplete="email" maxlength="190"
                 value="<?= e($old['email']) ?>"
                 placeholder="you@example.com" />
        </div>
        <div class="form-group">
          <label class="form-label" for="phone">Phone (M-Pesa)</label>
          <input class="form-control" type="tel" id="phone" name="phone" required
                 autocomplete="tel" maxlength="20"
                 value="<?= e($old['phone']) ?>"
                 placeholder="07XXXXXXXX" />
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input class="form-control" type="password" id="password" name="password" required
                 autocomplete="new-password" minlength="<?= (int)PASSWORD_MIN_LENGTH ?>"
                 placeholder="At least <?= (int)PASSWORD_MIN_LENGTH ?> characters" />
        </div>
        <div class="form-group">
          <label class="form-label" for="password_confirm">Confirm password</label>
          <input class="form-control" type="password" id="password_confirm" name="confirm_password" required
                 autocomplete="new-password"
                 placeholder="Repeat password" />
        </div>
        <div class="form-group">
          <label class="form-label" for="referral">Referral code (optional)</label>
          <input class="form-control" type="text" id="referral" name="referral_code"
                 maxlength="20" value="<?= e($old['referral_code']) ?>"
                 placeholder="If you have one" />
        </div>
        <div class="form-group">
          <label class="flex items-center gap-1" style="color:var(--text-secondary);font-size:0.875rem;cursor:pointer;">
            <input type="checkbox" name="terms" value="1" required style="accent-color:var(--accent);" />
            I agree to the <a href="terms.html" class="text-accent">Terms</a> and <a href="privacy.html" class="text-accent">Privacy Policy</a>
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg">Create account</button>
      </form>
      <div class="auth-footer">
        Already have an account? <a href="login.html">Sign in</a>
      </div>
    </div>
  </div>
</body>
</html>
