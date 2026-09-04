<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify_request(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        csrf_token();
        return;
    }
    $sent = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(403);
        die('安全驗證失敗，請重新整理頁面後再提交。');
    }
}
