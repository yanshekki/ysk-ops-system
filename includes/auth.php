<?php
require_once __DIR__ . '/db.php';

function login_user($username, $password) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
        // Log activity
        log_activity($user['id'], 'login', 'users', $user['id']);
        return true;
    }
    return false;
}

function logout_user() {
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php?login=required");
        exit;
    }
}

function log_activity($user_id, $action, $table = null, $record_id = null, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        db_query(
            "INSERT INTO activity_log (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
            [$user_id, $action, $table, $record_id, $details, $ip]
        );
    } catch (Exception $e) {
        // Silent fail for logging
    }
}
?>