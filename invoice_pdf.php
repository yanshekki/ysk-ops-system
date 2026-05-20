<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// 啟動 Session (確保沒有重複啟動)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 驗證登入狀態 (支援後台員工 或 Portal 客戶)
$is_staff = is_logged_in();
$is_client = isset($_SESSION['client_auth']);

if (!$is_staff && !$is_client) {
    die('拒絕訪問 Access Denied：請先登入系統。');
}

$invoice_id = (int)($_GET['id'] ?? 0);

if (!$invoice_id) {
    die('無效的發票編號 (Invalid invoice ID)');
}

// 獲取發票詳情
$invoice = db_fetch_one(
    "SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address, p.title as project_title
     FROM invoices i
     LEFT JOIN clients c ON i.client_id = c.id
     LEFT JOIN projects p ON i.project_id = p.id
     WHERE i.id = ?", 
    [$invoice_id]
);

if (!$invoice) {
    die('找不到該發票 (Invoice not found)');
}

// 🔥 核心安全限制：如果登入的身份是「客戶」，嚴格檢查該發票是否屬於他！
if (!$is_staff && $is_client) {
    if ($invoice['client_id'] != $_SESSION['client_auth']['id']) {
        die('拒絕訪問 Access Denied：您無權查看其他公司的發票。');
    }
}

// Professional PDF-ready HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <title>Invoice_<?= htmlspecialchars($invoice['invoice_number'] ?? '') ?>_YSK</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-color: #4f46e5;
            --brand-dark: #3730a3;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --page-width: 210mm;
            --page-height: 297mm;
        }

        /* 網頁桌面背景 */
        body {
            font-family: 'Inter', 'Noto Sans TC', sans-serif;
            background-color: #cbd5e1;
            margin: 0;
            padding: 2rem 0;
            -webkit-font-smoothing: antialiased;
            color: var(--text-main);
        }

        /* 頂部操作列 */
        .action-bar {
            width: var(--page-width);
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }

        .action-info .title {
            font-size: 13pt;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .action-info .hint {
            font-size: 9.5pt;
            color: var(--text-muted);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 10pt;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .btn-print { background: var(--text-main); color: #fff; border: none; }
        .btn-print:hover { background: #0f172a; }
        .btn-pay { background: var(--brand-color); color: #fff; border: none; }
        .btn-pay:hover { background: var(--brand-dark); }

        /* 真實 A4 紙張模擬 */
        .a4-sheet {
            width: var(--page-width);
            min-height: var(--page-height);
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
            padding: 20mm;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 140px;
            font-weight: 800;
            color: rgba(22, 101, 52, 0.04);
            border: 12px solid rgba(22, 101, 52, 0.04);
            border-radius: 24px;
            padding: 20px 50px;
            pointer-events: none;
            z-index: 0;
            letter-spacing: 10px;
        }

        .content-layer { position: relative; z-index: 1; }

        /* Header */
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 25px;
            margin-bottom: 30px;
        }
        
        .company-address { font-size: 9.5pt; color: var(--text-muted); line-height: 1.6; }
        .company-address strong { color: var(--text-main); font-size: 11pt; }
        
        .inv-title {
            font-size: 22pt;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: 1px;
            text-align: right;
            margin-bottom: 5px;
        }
        .inv-number {
            font-size: 13pt;
            font-weight: 600;
            color: var(--brand-color);
            text-align: right;
            margin-bottom: 15px;
        }
        .dates-table { width: auto; margin-left: auto; border-collapse: collapse; font-size: 9.5pt; }
        .dates-table td { padding: 3px 0; text-align: right; }
        .dates-table td.label { color: var(--text-muted); padding-right: 15px; }
        .dates-table td.value { font-weight: 600; color: var(--text-main); }

        /* 雙欄網格 */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .section-label {
            font-size: 8.5pt;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            border-left: 3px solid var(--brand-color);
            padding-left: 8px;
        }
        .client-name { font-size: 12pt; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
        .client-details { font-size: 9.5pt; color: var(--text-main); line-height: 1.6; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-draft { background: #f1f5f9; color: #475569; }

        .details-table { font-size: 9.5pt; width: 100%; border-collapse: collapse; }
        .details-table td { padding: 3px 0; }
        .details-table td.label { color: var(--text-muted); width: 35%; }
        .details-table td.value { font-weight: 500; color: var(--text-main); }

        /* 金額明細表 */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-size: 8.5pt;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }
        .items-table th.text-right { text-align: right; }
        .items-table td {
            padding: 16px 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        .items-table td.text-right { text-align: right; font-weight: 500; font-size: 10.5pt; }
        .item-title { font-weight: 600; color: var(--text-main); margin-bottom: 4px; font-size: 10.5pt; }
        .item-desc { font-size: 9pt; color: var(--text-muted); white-space: pre-line; }

        /* 總計計算區 */
        .totals-container { display: flex; justify-content: flex-end; margin-bottom: 40px; }
        .totals-table { width: 320px; border-collapse: collapse; }
        .totals-table td { padding: 8px 15px; text-align: right; }
        .totals-table td.label { color: var(--text-muted); font-size: 9.5pt; }
        .totals-table td.amount { font-weight: 600; font-size: 10.5pt; color: var(--text-main); }
        
        .total-row td {
            padding: 16px 15px;
            border-top: 2px solid var(--brand-color);
            background: #f8fafc;
        }
        .total-row td.label { font-weight: 700; font-size: 10.5pt; color: var(--text-main); vertical-align: middle; }
        .total-row td.amount { 
            font-size: 16pt; 
            font-weight: 800; 
            color: var(--brand-color); 
            white-space: nowrap;
        }
        .currency { font-size: 11pt; color: var(--text-muted); margin-right: 4px; font-weight: 600; }

        /* 底部付款資訊 */
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        .payment-box {
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 9pt;
            line-height: 1.7;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }
        .payment-box strong { color: var(--text-main); font-weight: 600; display: block; margin-bottom: 8px; font-size: 9.5pt;}
        
        .footer {
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            font-size: 8.5pt;
            color: var(--text-muted);
        }
        .footer .thanks { font-size: 11pt; font-weight: 600; color: var(--brand-color); margin-bottom: 6px; }

        /* Print 專用樣式 */
        @media print {
            body { background-color: #fff; padding: 0; margin: 0; }
            .action-bar { display: none !important; }
            .a4-sheet { box-shadow: none; margin: 0; width: 100%; }
            @page { size: A4; margin: 0; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>

    <div class="action-bar no-print">
        <div class="action-info">
            <div class="title">Invoice #<?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></div>
            <div class="hint">請使用 A4 尺寸並勾選「背景圖形」進行列印或儲存為 PDF。</div>
        </div>
        <div class="action-buttons">
            <button onclick="window.print()" class="btn btn-print">🖨️ 列印 / 下載 PDF</button>
        </div>
    </div>

    <div class="a4-sheet">
        
        <?php if ($invoice['status'] === 'paid'): ?>
            <div class="watermark">PAID</div>
        <?php endif; ?>

        <div class="content-layer">
            
            <div class="inv-header">
                <div>
                    <img src="https://ysk.hk/logo.svg" alt="YSK Limited" style="height: 48px; width: auto; margin-bottom: 15px; filter: brightness(0);">
                    <div class="company-address">
                        <strong>YSK LIMITED</strong><br>
                        Hong Kong<br>
                        Tel: +852 6160 4242 | Web: www.ysk.hk<br>
                        Email: email@ysk.hk
                    </div>
                </div>
                <div>
                    <div class="inv-title">TAX INVOICE</div>
                    <div class="inv-number">#<?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></div>
                    <table class="dates-table">
                        <tr>
                            <td class="label">Issue Date:</td>
                            <td class="value"><?= htmlspecialchars($invoice['issue_date'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="label">Due Date:</td>
                            <td class="value"><?= htmlspecialchars($invoice['due_date'] ?? '') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="info-grid">
                <div>
                    <div class="section-label">Bill To / 客戶資料</div>
                    <div class="client-name"><?= htmlspecialchars($invoice['company_name'] ?? 'Unknown Client') ?></div>
                    <div class="client-details">
                        <?= htmlspecialchars($invoice['contact_person'] ?? '') ?><br>
                        <?= htmlspecialchars($invoice['email'] ?? '') ?><br>
                        <?= htmlspecialchars($invoice['phone'] ?? '') ?><br>
                        <?php if (!empty($invoice['address'])): ?>
                            <div style="margin-top: 6px; color: var(--text-muted);">
                                <?= nl2br(htmlspecialchars($invoice['address'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <div class="section-label">Invoice Details / 發票詳情</div>
                    <?php 
                        $status_class = 'status-pending';
                        if ($invoice['status'] == 'paid') $status_class = 'status-paid';
                        if ($invoice['status'] == 'overdue') $status_class = 'status-overdue';
                        if ($invoice['status'] == 'draft') $status_class = 'status-draft';
                    ?>
                    <div class="status-badge <?= $status_class ?>">
                        <?= strtoupper(htmlspecialchars($invoice['status'] ?? 'Draft')) ?>
                    </div>
                    
                    <table class="details-table">
                        <?php if (!empty($invoice['project_title'])): ?>
                        <tr>
                            <td class="label">Project:</td>
                            <td class="value"><?= htmlspecialchars($invoice['project_title']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="label">Generated By:</td>
                            <td class="value">YSK System</td>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 75%;">Description / 項目說明</th>
                        <th class="text-right" style="width: 25%;">Amount (HKD)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-title">
                                <?= !empty($invoice['project_title']) ? htmlspecialchars($invoice['project_title']) : 'Professional Services & Consulting' ?>
                            </div>
                            <div class="item-desc">
                                <?= !empty($invoice['notes']) ? nl2br(htmlspecialchars($invoice['notes'])) : 'Development & Technical Support Services' ?>
                            </div>
                        </td>
                        <td class="text-right">
                            <?= number_format($invoice['subtotal'] ?? 0, 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="totals-container">
                <table class="totals-table">
                    <tr>
                        <td class="label">Subtotal / 小計:</td>
                        <td class="amount"><?= number_format($invoice['subtotal'] ?? 0, 2) ?></td>
                    </tr>
                    <?php if (($invoice['tax_percent'] ?? 0) > 0): ?>
                    <tr>
                        <td class="label">Tax / 稅項 (<?= htmlspecialchars($invoice['tax_percent']) ?>%):</td>
                        <td class="amount"><?= number_format(($invoice['total_amount'] ?? 0) - ($invoice['subtotal'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td class="label">TOTAL DUE / 應付總額:</td>
                        <td class="amount">
                            <span class="currency">HK$</span><?= number_format($invoice['total_amount'] ?? 0, 2) ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="payment-grid">
                <div class="payment-box">
                    <strong>1. Bank Transfer / FPS (銀行轉賬/轉數快)</strong>
                    Bank: HSBC (Hong Kong)<br>
                    Account Name: YSK LIMITED<br>
                    A/C No: 691-239008-838<br>
                    FPS ID: +85261604242<br>
                    <span style="color: var(--text-muted); font-size: 8pt; display:block; margin-top: 5px;">* Please send the receipt to billing@ysk.hk after payment.</span>
                </div>
            </div>

            <div class="footer">
                <div class="thanks">Thank you for your business! 感謝您的惠顧</div>
                <div>YSK Limited • Professional Digital Solutions</div>
                <div style="margin-top: 4px;">This is a computer-generated document. No signature or stamp is required.</div>
            </div>

        </div>
    </div>

</body>
</html>