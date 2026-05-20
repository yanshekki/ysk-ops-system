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

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php?login=required");
        exit;
    }
}

// 檢查單一角色 (Admin 永遠回傳 true)
function has_role($role) {
    if (!is_logged_in()) return false;
    $user_role = $_SESSION['user']['role'];
    if ($user_role === 'admin') return true; 
    return $user_role === $role;
}

// 🔥 新增：檢查是否擁有陣列中「任何一個」角色 (Admin 永遠回傳 true)
function has_any_role($allowed_roles) {
    if (!is_logged_in()) return false;
    $user_role = $_SESSION['user']['role'] ?? 'viewer';
    if ($user_role === 'admin') return true; 
    return in_array($user_role, $allowed_roles);
}

// 🔥 新增：強制攔截頁面，沒有權限則直接踢出
function require_any_role($allowed_roles) {
    if (!has_any_role($allowed_roles)) {
        die("
        <div style='display:flex; height:100vh; align-items:center; justify-content:center; background-color:#f8fafc; font-family:sans-serif;'>
            <div style='text-align:center; background:#fff; padding:40px 60px; border-radius:16px; box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);'>
                <h1 style='font-size:4rem; margin:0;'>🛑</h1>
                <h2 style='color:#ef4444; margin-top:20px;'>拒絕訪問 (Access Denied)</h2>
                <p style='color:#64748b; font-size:1.1rem; margin-bottom:30px;'>您的帳號角色 (<b>" . strtoupper($_SESSION['user']['role']) . "</b>) 權限不足以訪問此頁面。</p>
                <a href='index.php' style='background:#4f46e5; color:#fff; padding:10px 24px; text-decoration:none; border-radius:8px; font-weight:bold;'>返回控制台</a>
            </div>
        </div>
        ");
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