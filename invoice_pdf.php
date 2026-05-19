<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$invoice_id = $_GET['id'] ?? 0;
$invoice = db_fetch_one("SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.id = ?", [$invoice_id]);

if (!$invoice) {
    die('發票不存在');
}

// Simple HTML PDF (for production use TCPDF or mPDF)
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <title>發票 #<?= $invoice['invoice_number'] ?> - YSK Limited</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: right; }
        .invoice-title { font-size: 28px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .total { text-align: right; font-weight: bold; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>YSK Limited</h1>
        <p>香港 九龍 荃灣</p>
        <p>電話: 6160 4242 | www.ysk.hk</p>
    </div>
    
    <h2 class="invoice-title">發票</h2>
    <p><strong>發票編號：</strong> <?= $invoice['invoice_number'] ?></p>
    <p><strong>開立日期：</strong> <?= $invoice['issue_date'] ?> | <strong>到期日期：</strong> <?= $invoice['due_date'] ?></p>
    
    <div>
        <strong>客戶：</strong> <?= htmlspecialchars($invoice['company_name']) ?><br>
        <?= htmlspecialchars($invoice['contact_person'] ?: '') ?><br>
        <?= htmlspecialchars($invoice['email'] ?: '') ?><br>
        <?= htmlspecialchars($invoice['address'] ?: '') ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>項目描述</th>
                <th class="text-end">金額 (HK$)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($invoice['notes'] ?: '服務費用') ?></td>
                <td class="text-end"><?= number_format($invoice['total_amount'], 2) ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="total">
        <strong>小計：</strong> HK$ <?= number_format($invoice['subtotal'], 2) ?><br>
        <strong>稅金：</strong> HK$ <?= number_format($invoice['tax_percent'] / 100 * $invoice['subtotal'], 2) ?><br>
        <strong>總額：</strong> HK$ <?= number_format($invoice['total_amount'], 2) ?>
    </div>
    
    <div class="footer">
        <p>感謝您的惠顧 | YSK Limited © 2026</p>
        <p>此發票由 YSK Ops System 自動產生</p>
    </div>
    
    <script>window.print();</script>
</body>
</html>