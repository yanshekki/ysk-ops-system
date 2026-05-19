<?php
/**
 * YSK Ops System 全域核心工具函式庫
 * 抽離自舊版 config.php，優化架構與維護性
 */

/**
 * 安全地輸出 HTML 內容，防止 XSS 攻擊，兼容 PHP 8.1+ Null 報錯
 */
function escape(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 格式化顯示貨幣金額 (預設加上 HK$)
 */
function format_amount($amount, int $decimals = 2): string {
    return 'HK$ ' . number_format((float)$amount, $decimals);
}

/**
 * 依據專案進度百分比，動態回傳對應的 Bootstrap 顏色級別
 */
function get_progress_color(int $percent): string {
    if ($percent >= 100) return 'success';
    if ($percent >= 75) return 'primary';
    if ($percent >= 40) return 'info';
    if ($percent >= 15) return 'warning';
    return 'danger';
}

/**
 * 統一發票狀態的 Badge 樣式映射
 */
function get_invoice_status_badge(string $status): array {
    $map = [
        'draft'     => ['label' => '草稿', 'color' => 'secondary'],
        'sent'      => ['label' => '已發送', 'color' => 'warning'],
        'paid'      => ['label' => '已結清', 'color' => 'success'],
        'overdue'   => ['label' => '已逾期', 'color' => 'danger'],
        'cancelled' => ['label' => '已取消', 'color' => 'dark']
    ];
    return $map[$status] ?? ['label' => strtoupper($status), 'color' => 'secondary'];
}

/**
 * 統一專案進度狀態的 Badge 樣式映射
 */
function get_project_status_badge(string $status): array {
    $map = [
        'planning'    => ['label' => '規劃中', 'color' => 'secondary'],
        'in_progress' => ['label' => '進行中', 'color' => 'primary'],
        'review'      => ['label' => '審核中', 'color' => 'info'],
        'completed'   => ['label' => '已完成', 'color' => 'success'],
        'on_hold'     => ['label' => '暫停', 'color' => 'warning'],
        'cancelled'   => ['label' => '已取消', 'color' => 'danger']
    ];
    return $map[$status] ?? ['label' => strtoupper($status), 'color' => 'secondary'];
}

/**
 * 獲取當前登入用戶的完整 Session 資料
 */
function current_user() {
    return $_SESSION['user'] ?? [
        'id' => 0,
        'username' => 'guest',
        'full_name' => '訪客',
        'role' => 'viewer'
    ];
}