<?php
/**
 * YSK Ops System — 共用入口。真實 DB 密碼唔好寫喺呢個檔。
 *
 * 環境：
 *   production  = HTTP_HOST 係 ops.ysk.hk；CLI 則看 config.production.local.php
 *   development = 本機 / 127.0.0.1 / 其他
 *   可覆寫：環境變數 APP_ENV / YSK_APP_ENV，或 gitignore 嘅 config.env.php
 *
 * 載入順序（後者只填未 define 嘅常數）：
 *   1. config.{env}.local.php   gitignore，該環境專用密碼
 *   2. config.local.php         gitignore，舊檔兼容（production 建議改用 1）
 *   3. config.{env}.php         跟 repo 一齊上傳，無密碼
 */

if (!function_exists('ysk_app_env')) {
    function ysk_app_env(): string {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $forced = getenv('YSK_APP_ENV') ?: getenv('APP_ENV') ?: ($_SERVER['YSK_APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '');
        $forced = strtolower(trim((string)$forced));
        if (in_array($forced, ['production', 'prod'], true)) {
            return $resolved = 'production';
        }
        if (in_array($forced, ['development', 'dev', 'local'], true)) {
            return $resolved = 'development';
        }

        if (is_file(__DIR__ . '/config.env.php')) {
            require_once __DIR__ . '/config.env.php';
            if (defined('APP_ENV')) {
                $v = strtolower((string)APP_ENV);
                if (in_array($v, ['production', 'prod'], true)) {
                    return $resolved = 'production';
                }
                if (in_array($v, ['development', 'dev', 'local'], true)) {
                    return $resolved = 'development';
                }
            }
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        if (in_array($host, ['ops.ysk.hk', 'www.ops.ysk.hk'], true)) {
            return $resolved = 'production';
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return $resolved = 'development';
        }

        // CLI / cron 冇 HTTP_HOST：伺服器上有 production.local 就當 production
        if (($host === '' || PHP_SAPI === 'cli') && is_file(__DIR__ . '/config.production.local.php')) {
            return $resolved = 'production';
        }

        return $resolved = 'development';
    }
}

if (!defined('APP_ENV')) {
    define('APP_ENV', ysk_app_env());
}

$ysk_env_local = __DIR__ . '/config.' . APP_ENV . '.local.php';
if (is_file($ysk_env_local)) {
    require_once $ysk_env_local;
}
if (!defined('DB_HOST') && is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

$ysk_env_defaults = __DIR__ . '/config.' . APP_ENV . '.php';
if (is_file($ysk_env_defaults)) {
    require_once $ysk_env_defaults;
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV !== 'production');
}

require_once __DIR__ . '/includes/boot.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/csrf.php';
csrf_verify_request();

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'YSK Ops System');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', APP_ENV === 'production' ? 'https://ops.ysk.hk' : 'http://127.0.0.1');
}
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ysk_ops');
    define('DB_USER', 'ysk_db_user');
    define('DB_PASS', '');
    define('DB_CHAR', 'utf8mb4');
}
if (!defined('DB_CHAR')) {
    define('DB_CHAR', 'utf8mb4');
}

date_default_timezone_set('Asia/Hong_Kong');

require_once __DIR__ . '/includes/functions.php';
