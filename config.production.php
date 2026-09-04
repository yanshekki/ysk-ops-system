<?php
/**
 * Production defaults. 跟 repo 上傳。真實帳密只放伺服器上嘅 config.production.local.php（或舊嘅 config.local.php）。
 */
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://ops.ysk.hk');
}
if (!defined('DB_CHAR')) {
    define('DB_CHAR', 'utf8mb4');
}
