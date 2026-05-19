<?php
// YSK Operations System - Configuration
// Edit these for your hosting environment

define('DB_HOST', 'localhost');           // Change to your MySQL host
define('DB_NAME', 'ysk_ops');
define('DB_USER', 'root');                // Change to your DB username
define('DB_PASS', '');                    // Change to your DB password
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'YSK 業務運作系統');
define('SITE_URL', 'http://localhost/ysk-ops');  // Change to your domain or hosting URL
define('ADMIN_EMAIL', 'admin@ysk.hk');

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Hong_Kong');

// Helper: Get current user
function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function has_role($required_role) {
    $user = current_user();
    if (!$user) return false;
    $roles_hierarchy = ['viewer' => 1, 'developer' => 2, 'pm' => 3, 'finance' => 3, 'admin' => 10];
    $user_level = $roles_hierarchy[$user['role']] ?? 0;
    $required_level = $roles_hierarchy[$required_role] ?? 0;
    return $user_level >= $required_level;
}
?>