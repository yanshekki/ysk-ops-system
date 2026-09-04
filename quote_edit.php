<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/quote_helpers.php';
require_login();
require_any_role(['pm', 'finance', 'viewer']);

$can_write = has_any_role(['pm', 'finance']);
$success = $error = '';
$quote_id = (int)($_GET['id'] ?? $_POST['quote_id'] ?? 0);
$prefill_client = (int)($_GET['client_id'] ?? 0);

$quote = null;
$items = [];
if ($quote_id) {
    $quote = quote_fetch($quote_id);
    if (!$quote) {
        die('找不到該報價單。');
    }
    $items = quote_fetch_items($quote_id);
}

$locked = $quote && ($quote['status'] ?? 'draft') !== 'draft';
$readonly = !$can_write || $locked;

if (!$quote_id && !$can_write) {
    header('Location: quotes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_write && !$locked) {
    try {
        $raw_items = $_POST['items'] ?? [];
        $normalized = quote_normalize_items(is_array($raw_items) ? $raw_items : []);
        $header = [
            'client_id' => (int)($_POST['client_id'] ?? 0),
            'title' => $_POST['title'] ?? '',
            'intro_text' => $_POST['intro_text'] ?? '',
            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
            'valid_until' => $_POST['valid_until'] ?? date('Y-m-d', strtotime('+14 days')),
            'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
            'discount_amount' => (float)($_POST['discount_amount'] ?? 0),
            'notes' => $_POST['notes'] ?? '',
            'terms' => $_POST['terms'] ?? '',
            'created_by' => (int)$_SESSION['user_id'],
        ];
        $quote_id = quote_save($header, $normalized, $quote_id ?: null);

        if (isset($_POST['save_and_send'])) {
            $saved_items = quote_fetch_items($quote_id);
            if (!$saved_items) {
                throw new RuntimeException('請先加入明細再發送。');
            }
            db_update('quotes', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$quote_id]);
            $_SESSION['flash_success'] = '報價單已儲存並標記為已發送。';
            header('Location: quotes.php');
            exit;
        }

        if (isset($_POST['save_and_preview'])) {
            header('Location: quote_pdf.php?id=' . $quote_id);
            exit;
        }

        header('Location: quote_edit.php?id=' . $quote_id . '&saved=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $quote = array_merge($quote ?? [], [
            'client_id' => $_POST['client_id'] ?? $prefill_client,
            'title' => $_POST['title'] ?? '',
            'intro_text' => $_POST['intro_text'] ?? '',
            'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
            'valid_until' => $_POST['valid_until'] ?? '',
            'tax_percent' => $_POST['tax_percent'] ?? 0,
            'discount_amount' => $_POST['discount_amount'] ?? 0,
            'notes' => $_POST['notes'] ?? '',
            'terms' => $_POST['terms'] ?? '',
            'quote_number' => $quote['quote_number'] ?? '（未儲存）',
            'status' => $quote['status'] ?? 'draft',
        ]);
        $items = is_array($_POST['items'] ?? null) ? array_values($_POST['items']) : [];
    }
}

if (isset($_GET['saved'])) {
    $success = '草稿已儲存。';
}

$clients = db_fetch_all("SELECT id, company_name, status FROM clients WHERE status IN ('active','lead') ORDER BY status ASC, company_name ASC");
$catalog_groups = quote_catalog_groups();
$billing_labels = quote_billing_labels();

if (!$quote) {
    $quote = [
        'id' => 0,
        'quote_number' => '新草稿',
        'client_id' => $prefill_client,
        'title' => '',
        'intro_text' => '',
        'status' => 'draft',
        'issue_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+14 days')),
        'tax_percent' => 0,
        'discount_amount' => 0,
        'notes' => '',
        'terms' => '',
    ];
}

if (!$items) {
    $items = [[
        'catalog_key' => '',
        'title' => '',
        'description' => '',
        'billing_type' => 'one_time',
        'qty' => 1,
        'unit' => '項',
        'unit_price' => 0,
        'line_total' => 0,
    ]];
}

$badge = get_quote_status_badge($quote['status'] ?? 'draft');
$page_title = '報價單編輯';
include 'includes/header.php';
?>

<style>
    .catalog-chip { border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; padding: 4px 12px; font-size: 0.8rem; cursor: pointer; }
    .catalog-chip:hover { border-color: #4f46e5; color: #4f46e5; background: #eef2ff; }
    .quote-sticky { position: sticky; bottom: 0; background: #fff; border-top: 1px solid #e2e8f0; z-index: 20; }
    #itemsBody { padding-bottom: 88px; }
    .item-row textarea { min-height: 180px; font-size: 0.85rem; line-height: 1.55; white-space: pre-wrap; }
    .catalog-scroll { max-height: 280px; overflow-y: auto; }
</style>

<div class="d-flex align-items-stretch" style="min-height: 100vh; width: 100%;">
    <?php include 'includes/sidebar.php'; ?>
    <div class="flex-grow-1 d-flex flex-column" style="background-color: #f8f9fa; min-width: 0;">
        <div class="p-3 p-md-4 flex-grow-1">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div class="d-flex align-items-center">
                    <button class="mobile-nav-toggle btn d-md-none me-2 p-1" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h2 class="h3 fw-bold mb-0 text-slate-800"><?= htmlspecialchars($quote['quote_number'] ?? '新報價') ?></h2>
                            <span class="badge bg-<?= $badge['color'] ?> bg-opacity-10 text-<?= $badge['color'] ?>"><?= $badge['label'] ?></span>
                        </div>
                        <p class="text-muted mb-0">以 ysk.hk 公開方案為模板，金額以本單明細為準。</p>
                    </div>
                </div>
                <a href="quotes.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i> 返回列表</a>
            </div>

            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($locked): ?><div class="alert alert-warning border-0 shadow-sm">此報價已發送或完結，內容已鎖定。如需改價請使用「修訂」或「複製」。</div><?php endif; ?>

            <form method="POST" id="quoteForm">
                <?= csrf_field() ?>
                <input type="hidden" name="quote_id" value="<?= (int)$quote_id ?>">

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-slate-800 mb-3">基本資料</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">客戶 *（含潛在客戶）</label>
                                        <select name="client_id" class="form-select shadow-none" <?= $readonly ? 'disabled' : 'required' ?>>
                                            <option value="">請選擇客戶...</option>
                                            <?php foreach ($clients as $cl): ?>
                                                <option value="<?= (int)$cl['id'] ?>" <?= (int)$quote['client_id'] === (int)$cl['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cl['company_name']) ?><?= $cl['status'] === 'lead' ? '（潛在）' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($readonly): ?><input type="hidden" name="client_id" value="<?= (int)$quote['client_id'] ?>"><?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">報價標題 *</label>
                                        <input type="text" name="title" class="form-control shadow-none" value="<?= htmlspecialchars($quote['title'] ?? '') ?>" <?= $readonly ? 'readonly' : 'required' ?> placeholder="例如：開發者外判標準計劃 + APP 開發">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">開立日期</label>
                                        <input type="date" name="issue_date" class="form-control shadow-none" value="<?= htmlspecialchars($quote['issue_date']) ?>" <?= $readonly ? 'readonly' : 'required' ?>>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">有效期至</label>
                                        <input type="date" name="valid_until" class="form-control shadow-none" value="<?= htmlspecialchars($quote['valid_until']) ?>" <?= $readonly ? 'readonly' : 'required' ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">稅率 %</label>
                                        <input type="number" step="0.1" min="0" name="tax_percent" id="taxPercent" class="form-control shadow-none" value="<?= htmlspecialchars($quote['tax_percent']) ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">折扣 HK$</label>
                                        <input type="number" step="0.01" min="0" name="discount_amount" id="discountAmount" class="form-control shadow-none" value="<?= htmlspecialchars($quote['discount_amount']) ?>" <?= $readonly ? 'readonly' : '' ?>>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">給客戶的引言（可選）</label>
                                        <textarea name="intro_text" class="form-control shadow-none" rows="2" <?= $readonly ? 'readonly' : '' ?> placeholder="簡述本次報價範圍"><?= htmlspecialchars($quote['intro_text'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold text-slate-800 mb-0">服務明細</h6>
                                    <?php if (!$readonly): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()"><i class="bi bi-plus-lg me-1"></i>空白行</button>
                                    <?php endif; ?>
                                </div>
                                <div id="itemsBody">
                                    <?php foreach ($items as $idx => $it): ?>
                                    <div class="item-row border rounded-3 p-3 mb-3 bg-light">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <input type="hidden" name="items[<?= $idx ?>][catalog_key]" value="<?= htmlspecialchars($it['catalog_key'] ?? '') ?>" class="js-catalog">
                                                <label class="form-label small text-muted mb-1">項目名稱</label>
                                                <input type="text" name="items[<?= $idx ?>][title]" class="form-control form-control-sm shadow-none js-title" value="<?= htmlspecialchars($it['title'] ?? '') ?>" <?= $readonly ? 'readonly' : '' ?> required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">計費週期</label>
                                                <select name="items[<?= $idx ?>][billing_type]" class="form-select form-select-sm shadow-none js-billing" <?= $readonly ? 'disabled' : '' ?>>
                                                    <?php foreach ($billing_labels as $bk => $bl): ?>
                                                        <option value="<?= $bk ?>" <?= ($it['billing_type'] ?? '') === $bk ? 'selected' : '' ?>><?= $bl['zh'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($readonly): ?><input type="hidden" name="items[<?= $idx ?>][billing_type]" value="<?= htmlspecialchars($it['billing_type'] ?? 'one_time') ?>"><?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <?php if (!$readonly): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger mt-4" onclick="this.closest('.item-row').remove(); recalc();">刪除</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small text-muted mb-1">說明（印上 PDF）</label>
                                                <textarea name="items[<?= $idx ?>][description]" class="form-control shadow-none js-desc" <?= $readonly ? 'readonly' : '' ?>><?= htmlspecialchars($it['description'] ?? '') ?></textarea>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">數量</label>
                                                <input type="number" step="0.01" min="0.01" name="items[<?= $idx ?>][qty]" class="form-control form-control-sm shadow-none js-qty" value="<?= htmlspecialchars($it['qty'] ?? 1) ?>" <?= $readonly ? 'readonly' : '' ?>>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">單位</label>
                                                <input type="text" name="items[<?= $idx ?>][unit]" class="form-control form-control-sm shadow-none js-unit" value="<?= htmlspecialchars($it['unit'] ?? '項') ?>" <?= $readonly ? 'readonly' : '' ?>>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">單價 HKD</label>
                                                <input type="number" step="0.01" min="0" name="items[<?= $idx ?>][unit_price]" class="form-control form-control-sm shadow-none js-price" value="<?= htmlspecialchars($it['unit_price'] ?? 0) ?>" <?= $readonly ? 'readonly' : '' ?>>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small text-muted mb-1">小計</label>
                                                <div class="form-control form-control-sm bg-white fw-bold js-line">HK$ 0.00</div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">內部備註（不印 PDF）</label>
                                        <textarea name="notes" class="form-control shadow-none" rows="3" <?= $readonly ? 'readonly' : '' ?>><?= htmlspecialchars($quote['notes'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-slate-500 fw-semibold small mb-1">附加條款（印上 PDF，標準法律條款會自動附上）</label>
                                        <textarea name="terms" class="form-control shadow-none" rows="3" <?= $readonly ? 'readonly' : '' ?> placeholder="例如：本報價含三次修訂"><?= htmlspecialchars($quote['terms'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <?php if (!$readonly): ?>
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-slate-800 mb-2"><i class="bi bi-lightning me-1 text-primary"></i> YSK 方案模板</h6>
                                <p class="small text-muted">一按插入公開起步價，仍可改金額。</p>
                                <div class="catalog-scroll">
                                    <?php foreach ($catalog_groups as $group): ?>
                                        <div class="small fw-bold text-slate-500 text-uppercase mb-2 mt-3"><i class="bi <?= $group['icon'] ?> me-1"></i><?= htmlspecialchars($group['label']) ?></div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($group['items'] as $ci): ?>
                                                <button type="button" class="catalog-chip" onclick='insertCatalog(<?= json_encode($ci, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) ?>)'>
                                                    <?= htmlspecialchars($ci['title']) ?>
                                                    <?php if ($ci['unit_price'] > 0): ?>
                                                        <span class="text-muted">· <?= number_format($ci['unit_price']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">· 定製</span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold text-slate-800 mb-3">金額摘要</h6>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">明細小計</span><span id="sumSubtotal" class="fw-semibold">HK$ 0</span></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">折扣</span><span id="sumDiscount" class="fw-semibold">HK$ 0</span></div>
                                <div class="d-flex justify-content-between mb-2"><span class="text-muted">稅項</span><span id="sumTax" class="fw-semibold">HK$ 0</span></div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3"><span class="fw-bold">應付（首期）</span><span id="sumTotal" class="fw-bold text-primary fs-5">HK$ 0</span></div>
                                <div class="bg-light rounded-3 p-3">
                                    <div class="small text-muted mb-1">首年合約值（管線用，非收入）</div>
                                    <div id="sumYear" class="fw-bold text-slate-800">HK$ 0</div>
                                    <div class="small text-muted mt-1">一次性 + 月費×12 + 季費×4 + 年費 + 30日×12</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!$readonly): ?>
                <div class="quote-sticky py-3 mt-2">
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button type="submit" name="save_quote" class="btn btn-light border fw-semibold">儲存草稿</button>
                        <button type="submit" name="save_and_preview" class="btn btn-outline-primary fw-semibold">儲存並預覽 PDF</button>
                        <button type="submit" name="save_and_send" class="btn btn-primary fw-bold" onclick="return confirm('發送後內容將鎖定，確定？');">儲存並標記已發送</button>
                    </div>
                </div>
                <?php elseif ($quote_id): ?>
                <div class="d-flex gap-2 justify-content-end mb-4">
                    <a class="btn btn-primary" href="quote_pdf.php?id=<?= (int)$quote_id ?>" target="_blank">開啟 PDF</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

<script>
const BILLING = <?= json_encode($billing_labels, JSON_UNESCAPED_UNICODE) ?>;
const YEAR_MULT = {one_time:1, monthly:12, quarterly:4, yearly:1, every_30_days:12};

function money(n){ return 'HK$ ' + Number(n||0).toLocaleString('en-HK', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function recalc(){
    let subtotal = 0, year = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.js-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.js-price')?.value || 0);
        const billing = row.querySelector('.js-billing')?.value || 'one_time';
        const line = qty * price;
        subtotal += line;
        year += line * (YEAR_MULT[billing] || 1);
        const el = row.querySelector('.js-line');
        if (el) el.textContent = money(line);
    });
    const discount = parseFloat(document.getElementById('discountAmount')?.value || 0);
    const taxPct = parseFloat(document.getElementById('taxPercent')?.value || 0);
    const after = Math.max(0, subtotal - discount);
    const tax = after * (taxPct/100);
    document.getElementById('sumSubtotal').textContent = money(subtotal);
    document.getElementById('sumDiscount').textContent = money(discount);
    document.getElementById('sumTax').textContent = money(tax);
    document.getElementById('sumTotal').textContent = money(after + tax);
    document.getElementById('sumYear').textContent = money(year);
}

function nextIndex(){
    return document.querySelectorAll('.item-row').length;
}

function addItemRow(data = {}){
    const i = nextIndex();
    const wrap = document.createElement('div');
    wrap.className = 'item-row border rounded-3 p-3 mb-3 bg-light';
    const row = document.createElement('div');
    row.className = 'row g-2';

    const colTitle = document.createElement('div');
    colTitle.className = 'col-md-6';
    const hid = document.createElement('input');
    hid.type = 'hidden'; hid.name = `items[${i}][catalog_key]`; hid.className = 'js-catalog';
    hid.value = data.key || data.catalog_key || '';
    const labTitle = document.createElement('label');
    labTitle.className = 'form-label small text-muted mb-1'; labTitle.textContent = '項目名稱';
    const inpTitle = document.createElement('input');
    inpTitle.type = 'text'; inpTitle.name = `items[${i}][title]`; inpTitle.className = 'form-control form-control-sm shadow-none js-title';
    inpTitle.required = true; inpTitle.value = data.title || '';
    colTitle.append(hid, labTitle, inpTitle);

    const colBill = document.createElement('div');
    colBill.className = 'col-md-3';
    const labBill = document.createElement('label');
    labBill.className = 'form-label small text-muted mb-1'; labBill.textContent = '計費週期';
    const sel = document.createElement('select');
    sel.name = `items[${i}][billing_type]`; sel.className = 'form-select form-select-sm shadow-none js-billing';
    Object.entries(BILLING).forEach(([k,v]) => {
        const opt = document.createElement('option');
        opt.value = k; opt.textContent = v.zh;
        if ((data.billing_type || 'one_time') === k) opt.selected = true;
        sel.appendChild(opt);
    });
    colBill.append(labBill, sel);

    const colDel = document.createElement('div');
    colDel.className = 'col-md-3 text-end';
    const btnDel = document.createElement('button');
    btnDel.type = 'button'; btnDel.className = 'btn btn-sm btn-outline-danger mt-4'; btnDel.textContent = '刪除';
    btnDel.addEventListener('click', () => { wrap.remove(); recalc(); });
    colDel.appendChild(btnDel);

    const colDesc = document.createElement('div');
    colDesc.className = 'col-12';
    const labDesc = document.createElement('label');
    labDesc.className = 'form-label small text-muted mb-1'; labDesc.textContent = '說明（印上 PDF）';
    const ta = document.createElement('textarea');
    ta.name = `items[${i}][description]`; ta.className = 'form-control shadow-none js-desc';
    ta.value = data.description || '';
    colDesc.append(labDesc, ta);

    function field(colClass, label, name, cls, attrs) {
        const col = document.createElement('div'); col.className = colClass;
        const lab = document.createElement('label'); lab.className = 'form-label small text-muted mb-1'; lab.textContent = label;
        const inp = document.createElement('input');
        Object.assign(inp, attrs); inp.name = name; inp.className = 'form-control form-control-sm shadow-none ' + cls;
        col.append(lab, inp); return col;
    }
    const colQty = field('col-md-3', '數量', `items[${i}][qty]`, 'js-qty', {type:'number', step:'0.01', min:'0.01', value: String(data.qty || 1)});
    const colUnit = field('col-md-3', '單位', `items[${i}][unit]`, 'js-unit', {type:'text', value: data.unit || '項'});
    const colPrice = field('col-md-3', '單價 HKD', `items[${i}][unit_price]`, 'js-price', {type:'number', step:'0.01', min:'0', value: String(data.unit_price || 0)});
    const colLine = document.createElement('div'); colLine.className = 'col-md-3';
    const labLine = document.createElement('label'); labLine.className = 'form-label small text-muted mb-1'; labLine.textContent = '小計';
    const line = document.createElement('div'); line.className = 'form-control form-control-sm bg-white fw-bold js-line'; line.textContent = 'HK$ 0.00';
    colLine.append(labLine, line);

    row.append(colTitle, colBill, colDel, colDesc, colQty, colUnit, colPrice, colLine);
    wrap.appendChild(row);
    document.getElementById('itemsBody').appendChild(wrap);
    wrap.querySelectorAll('input,select,textarea').forEach(el => el.addEventListener('input', recalc));
    recalc();
}

function insertCatalog(item){
    const rows = document.querySelectorAll('.item-row');
    const first = rows[0];
    const empty = first && !(first.querySelector('.js-title')?.value);
    if (empty) first.remove();
    addItemRow(item);
}

document.getElementById('quoteForm')?.addEventListener('input', recalc);
document.getElementById('quoteForm')?.addEventListener('change', recalc);
recalc();
</script>

<?php include 'includes/footer.php'; ?>
