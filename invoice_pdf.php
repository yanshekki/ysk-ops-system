<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$invoice_id = (int)($_GET['id'] ?? 0);

if (!$invoice_id) {
    die('Invalid invoice ID');
}

// Fetch invoice details
$invoice = db_fetch_one(
    "SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address, p.title as project_title
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     LEFT JOIN projects p ON i.project_id = p.id
     WHERE i.id = ?", 
    [$invoice_id]
);

if (!$invoice) {
    die('Invoice not found');
}

// Professional PDF-ready HTML (user can Print > Save as PDF)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= $invoice['invoice_number'] ?> - YSK Limited</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: 'Segoe UI', Arial, 'Microsoft YaHei', sans-serif; font-size: 11pt; line-height: 1.6; color: #222; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid #0d6efd; padding-bottom: 15px; margin-bottom: 25px; }
        .company { }
        .company-name { font-size: 22pt; font-weight: 700; color: #0d6efd; letter-spacing: -0.5px; }
        .company-info { font-size: 9pt; color: #555; line-height: 1.4; margin-top: 5px; }
        .invoice-meta { text-align: right; }
        .invoice-number { font-size: 16pt; font-weight: 700; color: #0d6efd; }
        .section-title { font-size: 10pt; font-weight: 600; color: #0d6efd; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .info-box { background: #f8f9fa; padding: 14px 18px; border-radius: 8px; border-left: 4px solid #0d6efd; }
        .info-box h4 { margin: 0 0 8px 0; font-size: 10pt; color: #0d6efd; }
        .amount-table { width: 100%; border-collapse: collapse; margin: 25px 0; }
        .amount-table th, .amount-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        .amount-table th { background: #f1f3f5; font-weight: 600; color: #495057; }
        .amount-table .text-right { text-align: right; }
        .total-row { background: #e7f1ff; font-weight: 700; font-size: 13pt; }
        .total-row td { border-top: 2px solid #0d6efd; padding-top: 15px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 9pt; font-weight: 600; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 9pt; color: #6c757d; text-align: center; }
        .thank-you { font-size: 11pt; color: #0d6efd; font-weight: 600; margin: 15px 0; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company">
                <div class="company-name">YSK LIMITED</div>
                <div class="company-info">
                    Tin Shui Wai, Hong Kong<br>
                    Tel: +852 6160 4242 | www.ysk.hk<br>
                    Email: billing@ysk.hk
                </div>
            </div>
            <div class="invoice-meta">
                <div class="invoice-number">#<?= $invoice['invoice_number'] ?></div>
                <div style="margin-top: 8px; font-size: 10pt;">
                    <strong>Issue Date:</strong> <?= $invoice['issue_date'] ?><br>
                    <strong>Due Date:</strong> <?= $invoice['due_date'] ?>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <span style="font-size: 13pt; font-weight: 700; color: #0d6efd; letter-spacing: 1px;">TAX INVOICE / 稅務發票</span>
        </div>

        <!-- Client & Invoice Info -->
        <div class="info-grid">
            <div class="info-box">
                <div class="section-title">Bill To / 客戶資料</div>
                <strong><?= htmlspecialchars($invoice['company_name']) ?></strong><br>
                <?= htmlspecialchars($invoice['contact_person'] ?: '-') ?><br>
                <?= htmlspecialchars($invoice['email'] ?: '-') ?><br>
                <?= htmlspecialchars($invoice['phone'] ?: '-') ?><br>
                <div style="margin-top: 6px; font-size: 9.5pt; color: #555;"><?= nl2br(htmlspecialchars($invoice['address'] ?: '-')) ?></div>
            </div>
            <div class="info-box">
                <div class="section-title">Invoice Details / 發票詳情</div>
                <strong>Status:</strong> 
                <span class="status-badge <?= $invoice['status'] == 'paid' ? 'status-paid' : 'status-pending' ?>">
                    <?= strtoupper($invoice['status']) ?>
                </span><br>
                <?php if ($invoice['project_title']): ?>
                <strong>Project:</strong> <?= htmlspecialchars($invoice['project_title']) ?><br>
                <?php endif; ?>
                <strong>Created By:</strong> System<br>
                <strong>Notes:</strong> <?= htmlspecialchars($invoice['notes'] ?: 'N/A') ?>
            </div>
        </div>

        <!-- Amount Breakdown -->
        <table class="amount-table">
            <thead>
                <tr>
                    <th style="width: 65%;">Description / 項目說明</th>
                    <th class="text-right" style="width: 35%;">Amount (HKD) / 金額</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?= $invoice['project_title'] ? htmlspecialchars($invoice['project_title']) : 'Professional Services & Consulting' ?><br>
                        <span style="font-size: 9.5pt; color: #666;"><?= nl2br(htmlspecialchars($invoice['notes'] ?: 'Development & Technical Support')) ?></span>
                    </td>
                    <td class="text-right" style="vertical-align: top; padding-top: 18px;">
                        <?= number_format($invoice['subtotal'], 2) ?>
                    </td>
                </tr>
                <?php if ($invoice['tax_percent'] > 0): ?>
                <tr>
                    <td style="text-align: right; padding-right: 15px; color: #555;">Tax / 稅項 (<?= $invoice['tax_percent'] ?>%)</td>
                    <td class="text-right"><?= number_format($invoice['total_amount'] - $invoice['subtotal'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td style="text-align: right; padding-right: 15px; font-size: 12.5pt;"><strong>TOTAL DUE / 應付總額 (HKD)</strong></td>
                    <td class="text-right" style="font-size: 15pt; color: #0d6efd; font-weight: 700;"><?= number_format($invoice['total_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div style="background: #f8f9fa; padding: 16px 20px; border-radius: 8px; margin: 25px 0; font-size: 9.5pt;">
            <strong>Payment Methods / 付款方式</strong><br>
            • Bank Transfer (請聯絡我們索取銀行資料)<br>
            • Stripe / Credit Card (recommended)<br>
            • PayPal available upon request
        </div>

        <div class="footer">
            <div class="thank-you">Thank you for your business! 感謝您的惠顧</div>
            <div>YSK Limited • Professional Digital Solutions</div>
            <div style="margin-top: 8px; font-size: 8.5pt;">This is a computer-generated document. No signature required.</div>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" style="background: #0d6efd; color: white; border: none; padding: 10px 25px; border-radius: 6px; font-size: 10pt; cursor: pointer;">
                🖨️ Print / Save as PDF
            </button>
        </div>
    </div>
</body>
</html>