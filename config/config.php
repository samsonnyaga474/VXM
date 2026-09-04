<?php
/**
 * VXM Application Configuration
 *
 * Central configuration for the entire platform.
 * All business settings, contact info, and credentials should live here or in .env.
 * Do NOT hardcode support email, phone, M-Pesa number, level prices, or fees in individual pages.
 *
 * For local XAMPP: defaults below work after you create the `vxm` database.
 * Optional: copy .env.example to .env and edit values.
 */

// ---- Simple .env loader (no Composer required) ----
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($name !== '' && getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

// Environment: 'development' or 'production'
define('VXM_ENV', getenv('VXM_ENV') ?: 'development');

// Simulated M-Pesa deposits: ONLY when VXM_ENV is development.
// Even if ALLOW_SIMULATED_DEPOSITS=true is set in the environment, production never allows it.
define(
    'ALLOW_SIMULATED_DEPOSITS',
    VXM_ENV === 'development'
        && (
            getenv('ALLOW_SIMULATED_DEPOSITS') === 'true'
            || (getenv('ALLOW_SIMULATED_DEPOSITS') === false || getenv('ALLOW_SIMULATED_DEPOSITS') === '')
        )
);

// Database
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'vxm');
define('DB_CHARSET', 'utf8mb4');

// Application
define('APP_NAME', 'VXM');
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost', '/')); // No trailing slash
define('APP_DOMAIN', getenv('APP_DOMAIN') ?: 'vxm.co.ke');
define('APP_TIMEZONE', 'Africa/Nairobi');

// Support / Contact (use these constants everywhere — never hardcode)
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'fam500473@gmail.com');
define('SUPPORT_PHONE', getenv('SUPPORT_PHONE') ?: '0715385073');

// M-Pesa display number for Send Money instructions (user-facing)
define('MPESA_NUMBER', getenv('MPESA_NUMBER') ?: '0715385073');
define('MPESA_TYPE', getenv('MPESA_TYPE') ?: 'Send Money');

// Session
define('SESSION_NAME', 'VXMSESSID');
define('SESSION_LIFETIME', 7200); // 2 hours

// Security
define('CSRF_TOKEN_NAME', 'vxm_csrf');
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('RESET_TOKEN_EXPIRY_MINUTES', 60);

// Financial business configuration
define('CURRENCY', 'KES');
define('MIN_DEPOSIT', (float)(getenv('MIN_DEPOSIT') ?: 100.00));
define('MIN_WITHDRAWAL', (float)(getenv('MIN_WITHDRAWAL') ?: 200.00));
define('WITHDRAWAL_FEE', (float)(getenv('WITHDRAWAL_FEE') ?: 20.00));

// Referral: percentage of qualifying level purchase price
define('REFERRAL_PERCENTAGE', (float)(getenv('REFERRAL_PERCENTAGE') ?: 5.0));
// Options: 'on_level_purchase' | 'on_first_task' | 'on_registration' | 'manual'
define('REFERRAL_BONUS_TRIGGER', getenv('REFERRAL_BONUS_TRIGGER') ?: 'on_level_purchase');

// Level reference values (authoritative prices & structure live in DB `levels` table)
// Starter: KES 500  | target daily ~KES 20
// Growth:  KES 1,500 | target daily ~KES 60
// Pro:     KES 3,500 | target daily ~KES 140
// These are configurable business settings, not guaranteed returns.

// M-Pesa Daraja API (for STK / B2C when credentials are provided)
define('MPESA_ENV', getenv('MPESA_ENV') ?: 'sandbox'); // 'sandbox' or 'production'
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: ''); // Paybill / Till
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: '');
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: APP_URL . '/mpesa/callback.php');
define('MPESA_INITIATOR', getenv('MPESA_INITIATOR') ?: '');
define('MPESA_SECURITY_CREDENTIAL', getenv('MPESA_SECURITY_CREDENTIAL') ?: '');

// Email (for password reset & notifications)
// In development, tokens are logged. In production set a real mailer.
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@vxm.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'VXM');
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'log'); // 'log' | 'smtp' | 'mail'

// SMTP (if MAIL_DRIVER = smtp)
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');

// Rate limiting storage (simple file-based for shared hosting compatibility)
define('RATE_LIMIT_DIR', __DIR__ . '/../storage/rate_limits');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
if (VXM_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
