<?php
/**
 * YSK Ops System - 核心設定檔 (重構優化版)
 * 職責：只做純常數定義、環境設定，並自動橋接全域工具箱
 */

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
require_once __DIR__ . '/includes/boot.php';

// 1. 啟動全域 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/csrf.php';
csrf_verify_request();

// 2. 系統基礎設定常數
define('SITE_NAME', 'YSK Ops System');
define('SITE_URL', 'https://ops.ysk.hk');
define('BASE_PATH', __DIR__);

// 3. 資料庫連線憑證（真實帳密只放 config.local.php，git pull 唔會蓋）
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ysk_ops');
    define('DB_USER', 'ysk_db_user');
    define('DB_PASS', 'your_secure_password');
    define('DB_CHAR', 'utf8mb4');
}

// 4. 錯誤顯示由 includes/boot.php 依 APP_DEBUG 控制（production 預設關閉）

// 5. 設定預設時區
date_default_timezone_set('Asia/Hong_Kong');

// =========================================================================
// 🎯 核心橋接：因為絕大多數頁面都 require 了本檔案，
// 我們在此自動幫所有頁面載入工具箱，這樣你其他幾十個頁面一個字都不用改！
// =========================================================================
require_once __DIR__ . '/includes/functions.php';