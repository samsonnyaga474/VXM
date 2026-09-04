<?php
/**
 * Core helpers: CSRF, sessions, security, formatting
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Start secure session
 */
function vxm_session_start(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    if (VXM_ENV === 'production') {
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Lax');
    }

    session_name(SESSION_NAME);
    session_start();

    // Periodic regeneration
    if (!isset($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

/**
 * CSRF token generation & validation
 */
function csrf_token(): string {
    vxm_session_start();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
}

function verify_csrf(?string $token = null): bool {
    vxm_session_start();
    $token = $token ?? ($_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($token) || empty($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function require_csrf(): void {
    if (!verify_csrf()) {
        http_response_code(403);
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid security token. Please refresh and try again.']);
        } else {
            die('Invalid security token. Please go back, refresh the page, and try again.');
        }
        exit;
    }
}

function is_ajax_request(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Authentication helpers
 */
function require_login(): array {
    vxm_session_start();
    if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
        header('Location: login.html?error=login_required');
        exit;
    }
    return [
        'id'         => (int)$_SESSION['user_id'],
        'full_name'  => $_SESSION['full_name'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'is_admin'   => !empty($_SESSION['is_admin']),
        'level_id'   => (int)($_SESSION['level_id'] ?? 0),
    ];
}

function require_admin(): array {
    $user = require_login();
    if (empty($user['is_admin'])) {
        header('Location: dashboard.php');
        exit;
    }
    return $user;
}

function current_user_id(): int {
    vxm_session_start();
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Flash messages
 */
function flash(string $key, ?string $message = null) {
    vxm_session_start();
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return;
    }
    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

/**
 * Formatting
 */
function money(float $amount): string {
    return CURRENCY . ' ' . number_format($amount, 2);
}

function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Rate limiting (simple DB-based for login)
 */
function record_login_attempt(string $email, string $ip): void {
    $db = db();
    $stmt = $db->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->bind_param('ss', $email, $ip);
    $stmt->execute();
    $stmt->close();
}

function is_login_rate_limited(string $email, string $ip): bool {
    $db = db();
    $minutes = LOGIN_LOCKOUT_MINUTES;
    $max = LOGIN_MAX_ATTEMPTS;

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (email = ? OR ip_address = ?)
         AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->bind_param('ssi', $email, $ip, $minutes);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count >= $max;
}

function clear_login_attempts(string $email): void {
    $db = db();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();
}

/**
 * Generate unique referral code
 */
function generate_referral_code(mysqli $db): string {
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    } while ($exists);
    return $code;
}

/**
 * Create notification
 */
function notify_user(int $userId, string $type, string $title, string $message, ?array $data = null): void {
    $db = db();
    $json = $data ? json_encode($data) : null;
    $stmt = $db->prepare(
        "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $userId, $type, $title, $message, $json);
    $stmt->execute();
    $stmt->close();
}

/**
 * Admin audit trail (best-effort; never breaks the main action)
 */
function admin_audit(
    int $adminId,
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?array $before = null,
    ?array $after = null
): void {
    try {
        $db = db();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $beforeJson = $before !== null ? json_encode($before) : null;
        $afterJson = $after !== null ? json_encode($after) : null;
        $stmt = $db->prepare(
            "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, before_state, after_state, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param(
            'ississs',
            $adminId,
            $action,
            $targetType,
            $targetId,
            $beforeJson,
            $afterJson,
            $ip
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('VXM admin_audit failed: ' . $e->getMessage());
    }
}

/**
 * Simple redirect
 */
function redirect(string $url, int $code = 302): void {
    header("Location: $url", true, $code);
    exit;
}

/**
 * JSON response
 */
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
