<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/quote_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_staff = is_logged_in();
$is_client = isset($_SESSION['client_auth']);

if (!$is_staff && !$is_client) {
    die('拒絕訪問 Access Denied：請先登入系統。');
}

$quote_id = (int)($_GET['id'] ?? 0);
if (!$quote_id) {
    die('無效的報價單編號');
}

$quote = quote_fetch($quote_id);
if (!$quote) {
    die('找不到該報價單');
}

if (!$is_staff && $is_client) {
    if ((int)$quote['client_id'] !== (int)$_SESSION['client_auth']['id']) {
        die('拒絕訪問 Access Denied：您無權查看其他公司的報價單。');
    }
    if (($quote['status'] ?? '') === 'draft') {
        die('此報價單尚未發送。');
    }
}

$items = quote_fetch_items($quote_id);
$totals = quote_compute_totals(
    $items,
    (float)$quote['discount_amount'],
    (float)$quote['tax_percent']
);
$year_one = $totals['year_one_value'];
$has_recurring = false;
foreach ($items as $it) {
    if (quote_is_recurring_billing($it['billing_type'])) {
        $has_recurring = true;
        break;
    }
}

$lang_param = $_GET['lang'] ?? 'zh';
$lang = in_array($lang_param, ['en', 'zh'], true) ? $lang_param : 'zh';
$toggle_lang = $lang === 'en' ? 'zh' : 'en';
$billing_labels = quote_billing_labels();

$i18n = [
    'en' => [
        'doc_title' => 'QUOTATION',
        'issue_date' => 'Issue Date:',
        'valid_until' => 'Valid Until:',
        'quoted_to' => 'Quoted To',
        'quote_details' => 'Quotation Details',
        'status_label' => 'Status:',
        'desc' => 'Description',
        'billing' => 'Billing',
        'qty' => 'Qty',
        'unit_price' => 'Unit Price',
        'amount' => 'Amount',
        'subtotal' => 'Subtotal:',
        'discount' => 'Discount:',
        'tax' => 'Tax',
        'total_due' => 'AMOUNT (FIRST PERIOD):',
        'year_one' => 'First-year contract value:',
        'year_one_note' => 'Includes one-off fees plus 12 months / 4 quarters / 1 year of recurring items. Not an invoice.',
        'accept_note_title' => 'How to proceed',
        'accept_note' => 'This is a quotation, not a tax invoice. No payment is due until you accept this quotation and YSK issues a formal invoice with bank / FPS instructions.',
        'thanks' => 'We look forward to working with you.',
        'tagline' => 'Hong Kong Remote Dev Team & Enterprise Solutions',
        'system_gen' => 'This is a computer-generated quotation. No signature or stamp is required.',
        'hint' => 'Preview mode. Use A4 and enable “Background graphics” when printing or saving as PDF.',
        'btn_print' => 'Print / Save PDF',
        'btn_toggle' => '🌐 切換為中文',
        'terms_title' => 'Terms',
        'std_terms' => [
            'This quotation is valid only until the date shown. It expires automatically thereafter.',
            'Remote developer outsourcing plans are remote-only with a minimum one-year term. All such plans include one US dedicated-IP VPS.',
            'Private LLM hosted service is a separate annual plan and is not included in outsourcing monthly fees.',
            'App store listing: platform fees are waived if listed under YSK Limited developer accounts; if listed under the client’s own accounts, the client pays Apple / Google fees.',
            'Published prices are starting prices. The line items on this quotation prevail.',
            'Governed by the laws of the Hong Kong SAR. See ysk.hk/privacy-policy.',
        ],
        'status' => [
            'draft' => 'DRAFT',
            'sent' => 'SENT',
            'accepted' => 'ACCEPTED',
            'declined' => 'DECLINED',
            'expired' => 'EXPIRED',
            'converted' => 'CONVERTED',
            'superseded' => 'SUPERSEDED',
        ],
    ],
    'zh' => [
        'doc_title' => '報價單',
        'issue_date' => '開立日期:',
        'valid_until' => '有效期至:',
        'quoted_to' => '客戶資料',
        'quote_details' => '報價詳情',
        'status_label' => '當前狀態:',
        'desc' => '項目說明',
        'billing' => '計費',
        'qty' => '數量',
        'unit_price' => '單價',
        'amount' => '金額',
        'subtotal' => '小計:',
        'discount' => '折扣:',
        'tax' => '稅項',
        'total_due' => '首期應付:',
        'year_one' => '首年合約值:',
        'year_one_note' => '含一次性費用及週期項目 12 個月／4 季／1 年估算。此數字並非發票。',
        'accept_note_title' => '如何進行',
        'accept_note' => '本文件為報價單，並非發票。接受本報價後，YSK 會另行發出正式發票及銀行／轉數快付款指示，屆時才須付款。',
        'thanks' => '期待與您合作！',
        'tagline' => '香港遠端開發團隊及企業解決方案',
        'system_gen' => '此乃電腦自動生成之報價單，毋須簽名或蓋章。',
        'hint' => '預覽模式。列印或儲存為 PDF 時，請使用 A4 尺寸並勾選「背景圖形」。',
        'btn_print' => '列印 / 下載 PDF',
        'btn_toggle' => '🌐 English Version',
        'terms_title' => '條款',
        'std_terms' => [
            '本報價僅於所示有效期內有效，逾期自動失效。',
            '開發者外判計劃為一年約、只限遠端；所有此類計劃含 1 部美國獨立 IP 伺服器 (VPS)。',
            '私有 LLM 全託管為獨立年費，不包含於外判月費。',
            'App 上架：使用 YSK Limited 開發者帳號則官方平台費全免；使用客戶公司帳號則客戶自付 Apple／Google 年費。',
            '公開價為起步價，最終以本報價明細為準。',
            '受香港特別行政區法律管轄。詳見 ysk.hk/privacy-policy。',
        ],
        'status' => [
            'draft' => '草稿',
            'sent' => '待回覆',
            'accepted' => '已接受',
            'declined' => '已拒絕',
            'expired' => '已過期',
            'converted' => '已轉換',
            'superseded' => '已修訂',
        ],
    ],
];
$t = $i18n[$lang];

$status_color_map = [
    'accepted' => '#16a34a',
    'converted' => '#4f46e5',
    'draft' => '#64748b',
    'sent' => '#d97706',
    'expired' => '#dc2626',
    'declined' => '#dc2626',
    'superseded' => '#475569',
];
$status_bg_map = [
    'accepted' => '#dcfce7',
    'converted' => '#e0e7ff',
    'draft' => '#f1f5f9',
    'sent' => '#fef3c7',
    'expired' => '#fee2e2',
    'declined' => '#fee2e2',
    'superseded' => '#e2e8f0',
];
$q_status = strtolower($quote['status'] ?? 'draft');
$s_color = $status_color_map[$q_status] ?? '#64748b';
$s_bg = $status_bg_map[$q_status] ?? '#f1f5f9';
$status_text = $t['status'][$q_status] ?? strtoupper($q_status);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'en' ? 'en' : 'zh-HK' ?>">
<head>
    <meta charset="UTF-8">
    <title>Quote_<?= htmlspecialchars($quote['quote_number']) ?>_YSK</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-color: #4f46e5;
            --brand-dark: #3730a3;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --page-width: 210mm;
            --page-height: 297mm;
        }
        body { font-family: 'Inter', 'Noto Sans TC', sans-serif; background-color: #cbd5e1; margin: 0; padding: 2rem 0; color: var(--text-main); -webkit-font-smoothing: antialiased; }
        .action-bar { width: var(--page-width); margin: 0 auto 20px auto; background: #fff; padding: 16px 24px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
        .action-info .title { font-size: 12pt; font-weight: 700; margin-bottom: 2px; }
        .action-info .hint { font-size: 9pt; color: var(--text-muted); max-width: 420px; }
        .action-buttons { display: flex; gap: 12px; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 10pt; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; border: none; }
        .btn-print { background: var(--text-main); color: #fff; }
        .btn-lang { background: #f1f5f9; color: var(--brand-color); border: 1px solid #cbd5e1; }
        .a4-sheet { width: var(--page-width); min-height: var(--page-height); margin: 0 auto; background: #fff; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); box-sizing: border-box; position: relative; overflow: hidden; border-top: 12px solid var(--brand-color); }
        .content-layer { padding: 18mm 18mm; position: relative; z-index: 1; }
        .watermark { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-size: 90px; font-weight: 800; border: 8px solid; border-radius: 20px; padding: 15px 40px; pointer-events: none; z-index: 0; letter-spacing: 8px; text-transform: uppercase; }
        .header-table { width: 100%; margin-bottom: 28px; border-collapse: collapse; }
        .header-table td { vertical-align: top; }
        .company-info { font-size: 9.5pt; color: var(--text-muted); line-height: 1.6; margin-top: 15px; }
        .company-info strong { color: var(--text-main); font-size: 11pt; }
        .doc-title { font-size: 28pt; font-weight: 800; letter-spacing: 2px; line-height: 1; margin-bottom: 10px; }
        .doc-number { font-size: 13pt; font-weight: 600; color: var(--text-light); }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 28px; background: var(--bg-light); border-radius: 12px; padding: 22px; border: 1px solid var(--border-color); }
        .info-section-title { font-size: 8.5pt; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .client-name { font-size: 13pt; font-weight: 700; margin-bottom: 6px; }
        .client-details { font-size: 10pt; line-height: 1.6; }
        .details-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        .details-table td { padding: 4px 0; }
        .details-table td.label { color: var(--text-muted); width: 42%; }
        .details-table td.value { font-weight: 600; text-align: right; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 8.5pt; font-weight: 700; letter-spacing: 1px; background: <?= $s_bg ?>; color: <?= $s_color ?>; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table th { padding: 10px 8px; text-align: left; font-size: 8pt; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color); }
        .items-table th.text-right, .items-table td.text-right { text-align: right; }
        .items-table td { padding: 14px 8px; border-bottom: 1px solid var(--border-color); vertical-align: top; font-size: 9.5pt; }
        .item-title { font-size: 10.5pt; font-weight: 600; margin-bottom: 4px; }
        .item-desc { font-size: 8.5pt; color: var(--text-muted); white-space: pre-line; line-height: 1.45; }
        .totals-wrapper { display: flex; justify-content: flex-end; margin-bottom: 28px; }
        .totals-table { width: 380px; border-collapse: collapse; }
        .totals-table td { padding: 8px 12px; text-align: right; }
        .totals-table td.label { color: var(--text-muted); font-size: 10pt; }
        .totals-table td.amount { font-weight: 600; }
        .total-due-row td { padding: 14px 12px; border-top: 2px solid var(--brand-color); background: var(--bg-light); }
        .total-due-row td.label { font-weight: 700; color: var(--text-main); }
        .total-due-row td.amount { font-size: 16pt; font-weight: 800; color: var(--brand-color); }
        .note-box, .terms-box { border: 1px solid var(--border-color); border-radius: 12px; padding: 20px 22px; margin-bottom: 18px; }
        .note-box h3, .terms-box h3 { font-size: 10pt; font-weight: 700; margin: 0 0 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .terms-box ol { margin: 0; padding-left: 18px; font-size: 8.5pt; color: var(--text-muted); line-height: 1.55; }
        .footer { text-align: center; font-size: 9pt; color: var(--text-muted); margin-top: 24px; padding-top: 16px; }
        .footer-thanks { font-size: 12pt; font-weight: 600; color: var(--text-main); margin-bottom: 8px; }
        @media print {
            body { background: #fff; padding: 0; }
            .action-bar { display: none !important; }
            .a4-sheet { box-shadow: none; width: 100%; min-height: auto; border-top: 12px solid var(--brand-color) !important; }
            .total-due-row td, .info-grid, .status-badge { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="action-bar no-print">
        <div class="action-info">
            <div class="title"><?= htmlspecialchars($quote['quote_number']) ?></div>
            <div class="hint"><?= $t['hint'] ?></div>
        </div>
        <div class="action-buttons">
            <a href="?id=<?= $quote_id ?>&lang=<?= $toggle_lang ?>" class="btn btn-lang"><?= $t['btn_toggle'] ?></a>
            <button onclick="window.print()" class="btn btn-print"><?= $t['btn_print'] ?></button>
        </div>
    </div>

    <div class="a4-sheet">
        <?php if (in_array($q_status, ['draft','expired','declined','superseded','accepted','converted'], true)): ?>
            <div class="watermark" style="color: <?= $s_color ?>1A; border-color: <?= $s_color ?>1A;"><?= $status_text ?></div>
        <?php endif; ?>

        <div class="content-layer">
            <table class="header-table">
                <tr>
                    <td style="width:50%;">
                        <img src="https://ysk.hk/logo.svg" alt="YSK Limited" style="height:52px;width:auto;filter:brightness(0);">
                        <div class="company-info">
                            <strong>YSK LIMITED</strong><br>
                            Hong Kong<br>
                            Tel: +852 6160 4242 | Web: www.ysk.hk<br>
                            Email: email@ysk.hk
                        </div>
                    </td>
                    <td style="width:50%; text-align:right;">
                        <div class="doc-title"><?= $t['doc_title'] ?></div>
                        <div class="doc-number">#<?= htmlspecialchars($quote['quote_number']) ?></div>
                    </td>
                </tr>
            </table>

            <div class="info-grid">
                <div>
                    <div class="info-section-title"><?= $t['quoted_to'] ?></div>
                    <div class="client-name"><?= htmlspecialchars($quote['company_name'] ?? '') ?></div>
                    <div class="client-details">
                        <?= htmlspecialchars($quote['contact_person'] ?? '') ?><br>
                        <?= htmlspecialchars($quote['email'] ?? '') ?><br>
                        <?= htmlspecialchars($quote['phone'] ?? '') ?>
                        <?php if (!empty($quote['address'])): ?>
                            <div style="margin-top:8px;color:var(--text-muted);"><?= nl2br(htmlspecialchars($quote['address'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="info-section-title"><?= $t['quote_details'] ?></div>
                    <table class="details-table">
                        <tr><td class="label"><?= $t['issue_date'] ?></td><td class="value"><?= htmlspecialchars($quote['issue_date']) ?></td></tr>
                        <tr><td class="label"><?= $t['valid_until'] ?></td><td class="value"><?= htmlspecialchars($quote['valid_until']) ?></td></tr>
                        <tr>
                            <td class="label"><?= $t['status_label'] ?></td>
                            <td class="value"><span class="status-badge"><?= $status_text ?></span></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($quote['title'])): ?>
                <h2 style="font-size:14pt;margin:0 0 8px;"><?= htmlspecialchars($quote['title']) ?></h2>
            <?php endif; ?>
            <?php if (!empty($quote['intro_text'])): ?>
                <p style="color:var(--text-muted);font-size:10pt;margin:0 0 20px;white-space:pre-line;"><?= htmlspecialchars($quote['intro_text']) ?></p>
            <?php endif; ?>

            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:42%;"><?= $t['desc'] ?></th>
                        <th><?= $t['billing'] ?></th>
                        <th class="text-right"><?= $t['qty'] ?></th>
                        <th class="text-right"><?= $t['unit_price'] ?></th>
                        <th class="text-right"><?= $t['amount'] ?> (HKD)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it):
                        $bkey = $it['billing_type'] ?? 'one_time';
                        $blabel = $billing_labels[$bkey][$lang] ?? $bkey;
                    ?>
                    <tr>
                        <td>
                            <div class="item-title"><?= htmlspecialchars($it['title']) ?></div>
                            <?php if (!empty($it['description'])): ?>
                                <div class="item-desc"><?= htmlspecialchars($it['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($blabel) ?></td>
                        <td class="text-right"><?= rtrim(rtrim(number_format((float)$it['qty'], 2), '0'), '.') ?> <?= htmlspecialchars($it['unit']) ?></td>
                        <td class="text-right"><?= number_format((float)$it['unit_price'], 2) ?></td>
                        <td class="text-right" style="font-weight:600;"><?= number_format((float)$it['line_total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals-wrapper">
                <table class="totals-table">
                    <tr><td class="label"><?= $t['subtotal'] ?></td><td class="amount"><?= number_format($totals['subtotal'], 2) ?></td></tr>
                    <?php if ($totals['discount_amount'] > 0): ?>
                    <tr><td class="label"><?= $t['discount'] ?></td><td class="amount">-<?= number_format($totals['discount_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if ($totals['tax_percent'] > 0): ?>
                    <tr><td class="label"><?= $t['tax'] ?> (<?= htmlspecialchars((string)$totals['tax_percent']) ?>%):</td><td class="amount"><?= number_format($totals['tax_amount'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr class="total-due-row">
                        <td class="label"><?= $t['total_due'] ?></td>
                        <td class="amount">HK$ <?= number_format($totals['total_amount'], 2) ?></td>
                    </tr>
                    <?php if ($has_recurring): ?>
                    <tr>
                        <td class="label" style="padding-top:14px;"><?= $t['year_one'] ?></td>
                        <td class="amount" style="padding-top:14px;">HK$ <?= number_format($year_one, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" style="text-align:right;font-size:8pt;color:var(--text-muted);font-weight:400;"><?= $t['year_one_note'] ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="note-box">
                <h3><?= $t['accept_note_title'] ?></h3>
                <div style="font-size:9.5pt;color:var(--text-muted);line-height:1.55;"><?= $t['accept_note'] ?></div>
            </div>

            <div class="terms-box">
                <h3><?= $t['terms_title'] ?></h3>
                <ol>
                    <?php foreach ($t['std_terms'] as $term): ?>
                        <li><?= htmlspecialchars($term) ?></li>
                    <?php endforeach; ?>
                    <?php if (!empty($quote['terms'])): ?>
                        <li><?= nl2br(htmlspecialchars($quote['terms'])) ?></li>
                    <?php endif; ?>
                </ol>
            </div>

            <div class="footer">
                <div class="footer-thanks"><?= $t['thanks'] ?></div>
                <div>YSK Limited • <?= $t['tagline'] ?></div>
                <div style="margin-top:6px;font-size:8pt;color:#94a3b8;"><?= $t['system_gen'] ?></div>
            </div>
        </div>
    </div>
</body>
</html>
