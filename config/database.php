<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_maura_laundry');
define('DB_PORT', 3306);

function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            error_log('DB connect failed: ' . $conn->connect_error);
            die('Koneksi database gagal. Hubungi administrator.');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
