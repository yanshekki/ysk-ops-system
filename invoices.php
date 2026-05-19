<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 處理 POST 請求 (新增、編輯、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增發票
    if (isset($_POST['create_invoice'])) {
        $data = [
            'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT),
            'client_id' => (int)$_POST['client_id'],
            'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'issue_date' => $_POST['issue_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => (float)$_POST['subtotal'],
            'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
            'total_amount' => (float)$_POST['total_amount'],
            'status' => $_POST['status'] ?? 'draft',
            'notes' => trim($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        db_insert('invoices', $data);
        $success = '發票已成功開立！';
    }
    
    // 2. 編輯發票 (補齊功能)
    elseif (isset($_POST['edit_invoice'])) {
        $invoice_id = (int)$_POST['invoice_id'];
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'issue_date' => $_POST['issue_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => (float)$_POST['subtotal'],
            'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
            'total_amount' => (float)$_POST['total_amount'],
            'status' => $_POST['status'],
            'notes' => trim($_POST['notes'] ?? '')
        ];
        db_update('invoices', $data, 'id = ?', [$invoice_id]);
        $success = '發票資料已成功更新！';
    }
    
    // 3. 刪除發票 (補齊功能)
    elseif (isset($_POST['delete_invoice'])) {
        $delete_id = (int)$_POST['delete_invoice_id'];
        db_delete('invoices', 'id = ?', [$delete_id]);
        $success = '發票已成功刪除！';
    }
}

// 建立分頁與搜尋的 SQL 查詢
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(i.invoice_number LIKE ? OR c.company_name LIKE ? OR p.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_invoices = db_fetch_one("SELECT COUNT(*) as total FROM invoices i LEFT JOIN clients c ON i.client_id = c.id LEFT JOIN projects p ON i.project_id = p.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_invoices / $per_page);

// 獲取當前頁資料
$sql = "SELECT i.*, c.company_name, p.title as project_title 
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        LEFT JOIN projects p ON i.project_id = p.id 
        WHERE $where_sql 
        ORDER BY i.issue_date DESC, i.id DESC LIMIT $per_page OFFSET $offset";
$invoices = db_fetch_all($sql, $params);

// 獲取關聯資料供表單使用
$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");

// 狀態標籤設定
$status_options = [
    'draft' => ['label' => '草稿', 'color' => 'secondary', 'icon' => 'bi-file-earmark'],
    'sent' => ['label' => '已發送', 'color' => 'primary', 'icon' => 'bi-send'],
    'paid' => ['label' => '已付款', 'color' => 'success', 'icon' => 'bi-check-circle-fill'],
    'overdue' => ['label' => '已過期', 'color' => 'danger', 'icon' => 'bi-exclamation-circle'],
    'cancelled' => ['label' => '已取消', 'color' => 'warning', 'icon' => 'bi-x-circle']
];
?>
<?php $page_title = "發票管理"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-receipt me-2 text-primary"></i> 發票與賬單管理</h2>
                <p class="text-muted mb-0 d-none d-md-block">開立發票、追蹤款項及處理 Stripe 線上支付</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="bi bi-plus-circle me-1"></i> 新增發票
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="發票編號 / 客戶名稱 / 項目名稱">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">發票狀態</label>
                        <select name="status" class="form-select shadow-none">
                            <option value="">全部狀態</option>
                            <?php foreach ($status_options as $key => $s): ?>
                                <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">搜尋篩選</button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="invoices.php" class="btn btn-light w-100 border">清除</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>發票編號</th>
                                <th>客戶名稱</th>
                                <th>關聯項目</th>
                                <th>開立與到期日</th>
                                <th class="text-end">應付總額 (HK$)</th>
                                <th>狀態</th>
                                <th width="180" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): 
                                $s_info = $status_options[$inv['status']] ?? $status_options['draft'];
                                $avatar_char = mb_substr($inv['company_name'] ?? 'C', 0, 1, 'UTF-8');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-indigo"><i class="bi bi-receipt-cutoff me-1 text-muted"></i><?= $inv['invoice_number'] ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <span class="fw-bold small"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <span class="fw-semibold text-slate-800"><?= htmlspecialchars($inv['company_name'] ?? '未指定') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-slate-600 small"><?= htmlspecialchars($inv['project_title'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <div class="small text-slate-700">開立: <?= $inv['issue_date'] ?></div>
                                    <div class="small text-danger">到期: <?= $inv['due_date'] ?></div>
                                </td>
                                <td class="text-end">
                                    <h6 class="fw-bold mb-0 text-slate-800"><?= number_format($inv['total_amount'] ?? 0, 2) ?></h6>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $s_info['color'] ?> bg-opacity-10 text-<?= $s_info['color'] ?> px-2 py-1">
                                        <i class="bi <?= $s_info['icon'] ?> me-1"></i><?= $s_info['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-light border text-info me-1" title="查看與列印 PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                    
                                    <?php if ($inv['status'] != 'paid'): ?>
                                    <a href="stripe_checkout.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-light border text-success me-1" title="Stripe 線上收款"><i class="bi bi-credit-card"></i></a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editInvoiceModal<?= $inv['id'] ?>" title="編輯"><i class="bi bi-pencil-square"></i></button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('確定要永久刪除此發票嗎？刪除後無法復原。')">
                                        <input type="hidden" name="delete_invoice_id" value="<?= $inv['id'] ?>">
                                        <button type="submit" name="delete_invoice" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editInvoiceModal<?= $inv['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    編輯發票資料 <span class="ms-2 fs-6 text-muted fw-normal">(<?= $inv['invoice_number'] ?>)</span>
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="edit_invoice" value="1">
                                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">客戶 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                            <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($clients as $c): ?>
                                                                <option value="<?= $c['id'] ?>" <?= $inv['client_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">關聯項目 (可選)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-folder"></i></span>
                                                            <select name="project_id" class="form-select border-start-0 ps-0 shadow-none">
                                                                <option value="">無關聯項目</option>
                                                                <?php foreach ($projects as $p): ?>
                                                                <option value="<?= $p['id'] ?>" <?= $inv['project_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['title'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <hr class="text-muted my-3 opacity-25">
                                                    
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">開立日期 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                                            <input type="date" name="issue_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $inv['issue_date'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">到期日期 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-check"></i></span>
                                                            <input type="date" name="due_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $inv['due_date'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">發票狀態 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                                            <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($status_options as $key => $opt): ?>
                                                                    <option value="<?= $key ?>" <?= $inv['status'] === $key ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">小計 (HK$) *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                                            <input type="number" step="0.01" name="subtotal" class="form-control border-start-0 ps-0 shadow-none" value="<?= $inv['subtotal'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">稅率 (%)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-percent"></i></span>
                                                            <input type="number" step="0.1" name="tax_percent" class="form-control border-start-0 ps-0 shadow-none" value="<?= $inv['tax_percent'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">應收總額 (HK$) *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-cash-stack"></i></span>
                                                            <input type="number" step="0.01" name="total_amount" class="form-control border-start-0 ps-0 shadow-none fw-bold text-primary" value="<?= $inv['total_amount'] ?>" required>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">備註說明 (顯示於發票上)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                                            <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none" rows="2"><?= htmlspecialchars($inv['notes'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                                                <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                                                <button type="submit" class="btn btn-primary px-4 shadow-sm">儲存變更</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-receipt fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的發票記錄
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-receipt-cutoff fs-5"></i>
                        </div>
                        開立新發票
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="create_invoice" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">客戶 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇客戶...</option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯項目 (可選)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-folder"></i></span>
                                <select name="project_id" class="form-select border-start-0 ps-0 shadow-none">
                                    <option value="">無關聯項目</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <hr class="text-muted my-3 opacity-25">
                        
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">開立日期 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                <input type="date" name="issue_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">到期日期 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-check"></i></span>
                                <input type="date" name="due_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">初始狀態 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="draft" selected>草稿</option>
                                    <option value="sent">已發送</option>
                                    <option value="paid">已付款</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">小計 (HK$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" step="0.01" name="subtotal" class="form-control border-start-0 ps-0 shadow-none" value="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">稅率 (%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-percent"></i></span>
                                <input type="number" step="0.1" name="tax_percent" class="form-control border-start-0 ps-0 shadow-none" value="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">應收總額 (HK$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-cash-stack"></i></span>
                                <input type="number" step="0.01" name="total_amount" class="form-control border-start-0 ps-0 shadow-none fw-bold text-primary" value="0.00" required>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">備註說明 (顯示於發票上)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none" rows="2" placeholder="例如：首期款項、銀行轉賬資料..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 建立發票</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>