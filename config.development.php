<?php
/**
 * Development defaults. 跟 repo 上傳。真實帳密放 config.development.local.php 或 config.local.php。
 */
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://127.0.0.1');
}
if (!defined('DB_CHAR')) {
    define('DB_CHAR', 'utf8mb4');
}
