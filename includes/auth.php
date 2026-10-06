<?php
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect(APP_URL . '/auth/login.php');
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    static $user = null;
    if ($user === null) {
        $db   = db();
        $stmt = $db->prepare(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.is_active = 1"
        );
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    return $user;
}

function user_permissions(): array {
    static $perms = null;
    if ($perms !== null) return $perms;
    $user = current_user();
    if (!$user) return [];
    $db   = db();
    $stmt = $db->prepare(
        "SELECT p.name FROM permissions p
         JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = ?"
    );
    $stmt->bind_param('i', $user['role_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $perms  = [];
    while ($row = $result->fetch_assoc()) {
        $perms[] = $row['name'];
    }
    $stmt->close();
    return $perms;
}

function can(string $permission): bool {
    return in_array($permission, user_permissions(), true);
}

function require_permission(string $permission): void {
    require_login();
    if (!can($permission)) {
        flash('error', 'Anda tidak memiliki akses ke halaman ini.');
        redirect(APP_URL . '/pages/dashboard.php');
    }
}
