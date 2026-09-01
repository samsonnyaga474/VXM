<?php
/**
 * Database connection (replaces root db.php)
 * Backward compatible: still provides $conn
 */

require_once __DIR__ . '/../config/config.php';

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    if (VXM_ENV === 'development') {
        die('Database connection failed: ' . $mysqli->connect_error);
    }
    http_response_code(503);
    die('Service temporarily unavailable.');
}

$mysqli->set_charset(DB_CHARSET);
$mysqli->query("SET time_zone = '+03:00'");

// Backward compatibility
$conn = $mysqli;

/**
 * Helper to get the mysqli instance
 */
function db(): mysqli {
    global $mysqli;
    return $mysqli;
}
