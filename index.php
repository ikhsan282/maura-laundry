<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user && $user['role_id'] == 5) {
        redirect(APP_URL . '/pages/portal.php');
    }
    redirect(APP_URL . '/pages/dashboard.php');
} else {
    redirect(APP_URL . '/auth/login.php');
}
