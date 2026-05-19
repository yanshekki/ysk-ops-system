<?php
/**
 * YSK Ops System - 核心設定檔 (重構優化版)
 * 職責：只做純常數定義、環境設定，並自動橋接全域工具箱
 */

// 1. 啟動全域 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 系統基礎設定常數
define('SITE_NAME', 'YSK Ops System');
define('SITE_URL', 'https://ops.ysk.hk');
define('BASE_PATH', __DIR__);

// 3. 資料庫連線憑證
define('DB_HOST', 'localhost');
define('DB_NAME', 'ysk_ops');
define('DB_USER', 'ysk_db_user'); 
define('DB_PASS', 'your_secure_password'); 
define('DB_CHAR', 'utf8mb4');

// 4. 開發環境錯誤調試設定
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 5. 設定預設時區
date_default_timezone_set('Asia/Hong_Kong');

// =========================================================================
// 🎯 核心橋接：因為絕大多數頁面都 require 了本檔案，
// 我們在此自動幫所有頁面載入工具箱，這樣你其他幾十個頁面一個字都不用改！
// =========================================================================
require_once __DIR__ . '/includes/functions.php';