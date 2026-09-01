<?php
/**
 * Application bootstrap - include this at the top of every PHP entry point
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Wallet.php';

vxm_session_start();

// Security headers (compatible with most shared hosting)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (VXM_ENV === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
