<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

// 接收篩選與分頁參數
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ==============================================
// 處理表單提交 (新增、編輯、刪除)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 開立新發票
    if (isset($_POST['create_invoice'])) {
        $subtotal = (float)$_POST['subtotal'];
        $tax_percent = (float)($_POST['tax_percent'] ?? 0);
        $total_amount = $subtotal + ($subtotal * ($tax_percent / 100));
        
        // 自動生成發票單號 (INV-YYYYMMDD-XXX)
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $data = [
            'invoice_number' => $invoice_number,
            'client_id' => (int)$_POST['client_id'],
            'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'issue_date' => $_POST['issue_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => $subtotal,
            'tax_percent' => $tax_percent,
            'total_amount' => $total_amount,
            'status' => 'draft',
            'notes' => trim($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        
        if (!empty($data['client_id']) && $subtotal > 0) {
            db_insert('invoices', $data);
            $success = "發票 #{$invoice_number} 已成功建立！";
        } else {
            $error = '請選擇客戶並輸入有效的金額！';
        }
    }
    
    // 2. 編輯發票 (更新狀態、金額等)
    elseif (isset($_POST['edit_invoice'])) {
        $invoice_id = (int)$_POST['invoice_id'];
        $subtotal = (float)$_POST['subtotal'];
        $tax_percent = (float)($_POST['tax_percent'] ?? 0);
        $total_amount = $subtotal + ($subtotal * ($tax_percent / 100));

        $data = [
            'issue_date' => $_POST['issue_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => $subtotal,
            'tax_percent' => $tax_percent,
            'total_amount' => $total_amount,
            'status' => $_POST['status'],
            'notes' => trim($_POST['notes'] ?? '')
        ];
        
        db_update('invoices', $data, 'id = ?', [$invoice_id]);
        $success = '發票內容及狀態已成功更新！';
    }
    
    // 3. 刪除發票
    elseif (isset($_POST['delete_invoice'])) {
        $invoice_id = (int)$_POST['delete_invoice_id'];
        db_delete('invoices', 'id = ?', [$invoice_id]);
        $success = '發票記錄已成功刪除！';
    }
}

// ==============================================
// 構建查詢條件與獲取資料
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(i.invoice_number LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取發票列表
$sql = "SELECT i.*, c.company_name, p.title as project_title 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        LEFT JOIN projects p ON i.project_id = p.id 
        WHERE $where_sql 
        ORDER BY i.created_at DESC 
        LIMIT $per_page OFFSET $offset";
$invoices = db_fetch_all($sql, $params);

// 獲取選單用資料
$clients = db_fetch_all("SELECT id, company_name FROM clients WHERE status='active' ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects WHERE status != 'cancelled' ORDER BY updated_at DESC");

// 狀態設定
$status_badges = [
    'draft'     => ['label' => '草稿 (Draft)', 'color' => 'secondary'],
    'sent'      => ['label' => '待付款 (Sent)', 'color' => 'warning'],
    'paid'      => ['label' => '已結清 (Paid)', 'color' => 'success'],
    'overdue'   => ['label' => '已逾期 (Overdue)', 'color' => 'danger'],
    'cancelled' => ['label' => '已作廢 (Cancelled)', 'color' => 'dark']
];

// ==============================================
// 視圖渲染開始 (套用黃金排版準則)
// ==============================================
$page_title = "發票管理 Invoices";
include 'includes/header.php';
?>

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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-receipt-cutoff me-2 text-primary"></i> 發票與計費管理 (Invoices)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">管理業務應收賬單流量、結算對帳與開立正式收據</p>
                    </div>
                </div>
                <div>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                        <i class="bi bi-plus-circle me-1"></i> 開立新發票
                    </button>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋發票單號或客戶名稱...">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">所有賬單狀態</option>
                                <?php foreach ($status_badges as $val => $opt): ?>
                                    <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="invoices.php" class="btn btn-light border w-100 text-muted">清除篩選</a>
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
                            <thead class="bg-light text-slate-600">
                                <tr>
                                    <th class="ps-4 py-3">發票單號</th>
                                    <th class="py-3">客戶與關聯專案</th>
                                    <th class="py-3">開立與截止日</th>
                                    <th class="py-3 text-end">應付總額 (HK$)</th>
                                    <th class="py-3 text-center">狀態</th>
                                    <th class="text-end pe-4 py-3">操作</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($invoices as $i): 
                                    $s_badge = $status_badges[$i['status']] ?? $status_badges['draft'];
                                    $is_overdue = ($i['status'] !== 'paid' && $i['status'] !== 'cancelled' && strtotime($i['due_date']) < time());
                                    if ($is_overdue && $i['status'] !== 'overdue') {
                                        $s_badge = $status_badges['overdue']; // 動態顯示逾期
                                    }
                                ?>
                                <tr class="<?= $i['status'] === 'cancelled' ? 'opacity-50 bg-light' : '' ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-slate-800" style="font-size: 1.05rem;">
                                            <i class="bi bi-file-earmark-text text-muted me-1"></i><?= htmlspecialchars($i['invoice_number']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-slate-700"><?= htmlspecialchars($i['company_name']) ?></div>
                                        <small class="text-muted"><i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($i['project_title'] ?: '無關聯專案') ?></small>
                                    </td>
                                    <td>
                                        <div class="small text-slate-600 mb-1">開立: <?= $i['issue_date'] ?></div>
                                        <div class="small <?= $is_overdue ? 'text-danger fw-bold' : 'text-slate-600' ?>">
                                            截止: <?= $i['due_date'] ?>
                                            <?php if($is_overdue): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-slate-800 fs-6"><?= number_format($i['total_amount'], 2) ?></div>
                                        <?php if ($i['tax_percent'] > 0): ?>
                                            <small class="text-muted" style="font-size:0.7rem;">含稅 <?= $i['tax_percent'] ?>%</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $s_badge['color'] ?> bg-opacity-10 text-<?= $s_badge['color'] ?> px-2 py-1">
                                            <?= $s_badge['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="invoice_pdf.php?id=<?= $i['id'] ?>" target="_blank" class="btn btn-sm btn-indigo text-white shadow-sm" style="background-color:#4f46e5;" title="下載/列印 PDF">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <button class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#editInvoiceModal<?= $i['id'] ?>" title="編輯與更改狀態">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('確定要刪除發票 <?= htmlspecialchars($i['invoice_number']) ?> 嗎？\n注意：刪除後將無法復原！');">
                                                <input type="hidden" name="delete_invoice_id" value="<?= $i['id'] ?>">
                                                <button type="submit" name="delete_invoice" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editInvoiceModal<?= $i['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                            <i class="bi bi-receipt-cutoff fs-5"></i>
                                                        </div>
                                                        編輯發票及對帳狀態
                                                    </h5>
                                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="edit_invoice" value="1">
                                                    <input type="hidden" name="invoice_id" value="<?= $i['id'] ?>">
                                                    
                                                    <div class="alert alert-light border mb-4">
                                                        <strong>發票編號：</strong> <?= htmlspecialchars($i['invoice_number']) ?><br>
                                                        <strong>客戶名稱：</strong> <?= htmlspecialchars($i['company_name']) ?>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">對帳狀態 *</label>
                                                            <select name="status" class="form-select shadow-none fw-bold text-primary">
                                                                <?php foreach ($status_badges as $val => $opt): ?>
                                                                    <option value="<?= $val ?>" <?= $i['status'] === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6"></div> <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">開立日期 *</label>
                                                            <input type="date" name="issue_date" class="form-control shadow-none" value="<?= $i['issue_date'] ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">付款截止日 *</label>
                                                            <input type="date" name="due_date" class="form-control shadow-none" value="<?= $i['due_date'] ?>" required>
                                                        </div>
                                                        
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">發票小計金額 (HK$) *</label>
                                                            <input type="number" step="0.01" name="subtotal" class="form-control shadow-none fw-bold" value="<?= $i['subtotal'] ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">稅率 (%)</label>
                                                            <input type="number" step="0.1" name="tax_percent" class="form-control shadow-none" value="<?= $i['tax_percent'] ?>">
                                                        </div>
                                                        
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">條款註記與付款說明</label>
                                                            <textarea name="notes" class="form-control shadow-none bg-light" rows="3"><?= htmlspecialchars($i['notes'] ?? '') ?></textarea>
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
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
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
            <div class="d-flex justify-content-center mt-4">
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

        <div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-file-earmark-medical fs-5"></i>
                        </div>
                        開立新收費發票
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="create_invoice" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">目標收費客戶 *</label>
                            <select name="client_id" class="form-select shadow-none" required>
                                <option value="">請選擇客戶...</option>
                                <?php foreach ($clients as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯專案 (可選)</label>
                            <select name="project_id" class="form-select shadow-none">
                                <option value="">無關聯特定專案</option>
                                <?php foreach ($projects as $pj): ?>
                                    <option value="<?= $pj['id'] ?>"><?= htmlspecialchars($pj['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">發票開立日期 *</label>
                            <input type="date" name="issue_date" class="form-control shadow-none" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">付款截止死線 *</label>
                            <input type="date" name="due_date" class="form-control shadow-none" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目小計金額 (HK$) *</label>
                            <input type="number" step="0.01" name="subtotal" class="form-control shadow-none text-primary fw-bold" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">稅率百分比 (%)</label>
                            <input type="number" step="0.1" name="tax_percent" class="form-control shadow-none" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">條款註記與付款說明</label>
                            <textarea name="notes" class="form-control shadow-none bg-light" rows="3" placeholder="例如：請於收到本發票後 14 天內安排劃線支票或線上找數..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">確認生成草稿</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>