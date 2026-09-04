<?php
require_once __DIR__ . '/../config.php';

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $hint = '請在網站根目錄建立 config.local.php（可複製 config.local.php.example）並填入真實資料庫帳密。git pull 唔會改呢個檔。';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('Database connection failed: ' . htmlspecialchars($e->getMessage()) . '<br>' . $hint);
            }
            die('資料庫連線失敗。' . $hint);
        }
    }
    return $pdo;
}

// Helper functions for queries
function db_query($sql, $params = []) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch_all($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

function db_fetch_one($sql, $params = []) {
    return db_query($sql, $params)->fetch();
}

function db_insert($table, $data) {
    $pdo = get_db_connection();
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));
    return $pdo->lastInsertId();
}

function db_update($table, $data, $where, $where_params = []) {
    $pdo = get_db_connection();
    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
    $sql = "UPDATE $table SET $set WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(array_values($data), $where_params));
    return $stmt->rowCount();
}

function db_delete($table, $where, $params = []) {
    $pdo = get_db_connection();
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_transaction(callable $fn) {
    $pdo = get_db_connection();
    $started = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $started = true;
    }
    try {
        $result = $fn($pdo);
        if ($started) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
?>