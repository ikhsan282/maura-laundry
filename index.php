<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(APP_URL . '/pages/dashboard.php');
} else {
    redirect(APP_URL . '/auth/login.php');
}
