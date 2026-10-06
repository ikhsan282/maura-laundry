<?php
define('APP_NAME',    'Maura Laundry');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/maura-laundry');
define('APP_EMAIL',   'admin@mauralaundry.com');

// Session
define('SESSION_LIFETIME', 7200); // 2 hours

// Email (shared hosting uses php mail())
define('MAIL_FROM',      'noreply@mauralaundry.com');
define('MAIL_FROM_NAME', 'Maura Laundry');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (set to 0 on production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false, // set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
