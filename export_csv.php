<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'finance']);

$type = $_GET['type'] ?? 'invoices';
$allowed = ['invoices', 'clients', 'quotes'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    die('不支援的匯出類型');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('YmdHis') . '.csv"');

$output = fopen('php://output', 'w');

if ($type === 'invoices') {
    fputcsv($output, ['發票編號', '客戶', '項目', '開立日期', '到期日', '金額', '狀態']);
    $invoices = db_fetch_all("SELECT i.*, c.company_name, p.title as project_title FROM invoices i LEFT JOIN clients c ON i.client_id = c.id LEFT JOIN projects p ON i.project_id = p.id");
    foreach ($invoices as $inv) {
        fputcsv($output, [
            $inv['invoice_number'],
            $inv['company_name'],
            $inv['project_title'] ?: '-',
            $inv['issue_date'],
            $inv['due_date'],
            $inv['total_amount'],
            $inv['status']
        ]);
    }
} elseif ($type === 'quotes') {
    fputcsv($output, ['報價編號', '客戶', '標題', '狀態', '開立日', '有效期', '小計', '折扣', '總額']);
    $quotes = db_fetch_all("SELECT q.*, c.company_name FROM quotes q LEFT JOIN clients c ON q.client_id = c.id ORDER BY q.created_at DESC");
    foreach ($quotes as $q) {
        fputcsv($output, [
            $q['quote_number'],
            $q['company_name'],
            $q['title'],
            $q['status'],
            $q['issue_date'],
            $q['valid_until'],
            $q['subtotal'],
            $q['discount_amount'],
            $q['total_amount'],
        ]);
    }
} elseif ($type === 'clients') {
    fputcsv($output, ['公司名稱', '聯絡人', '電郵', '電話', '狀態', '建立日期']);
    $clients = db_fetch_all("SELECT * FROM clients");
    foreach ($clients as $c) {
        fputcsv($output, [
            $c['company_name'],
            $c['contact_person'],
            $c['email'],
            $c['phone'],
            $c['status'],
            $c['created_at']
        ]);
    }
}

fclose($output);
exit;
?>