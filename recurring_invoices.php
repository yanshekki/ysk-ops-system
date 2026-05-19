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

// 處理 POST 請求 (生成發票、新增、編輯、暫停/恢復、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 生成當期發票
    if (isset($_POST['generate_invoice'])) {
        $recurring_id = (int)$_POST['recurring_id'];
        $recurring = db_fetch_one("SELECT * FROM recurring_invoices WHERE id = ?", [$recurring_id]);
        
        if ($recurring && $recurring['status'] == 'active') {
            $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT);
            
            $invoice_data = [
                'invoice_number' => $invoice_number,
                'client_id' => $recurring['client_id'],
                'project_id' => $recurring['project_id'],
                'issue_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'subtotal' => $recurring['amount'],
                'tax_percent' => 0,
                'total_amount' => $recurring['amount'],
                'status' => 'draft',
                'notes' => $recurring['notes'] ?? '',
                'created_by' => $_SESSION['user_id']
            ];
            
            db_insert('invoices', $invoice_data);
            
            // 根據頻率計算下次發票日
            $next_date = $recurring['next_invoice_date'];
            switch ($recurring['frequency']) {
                case 'monthly':
                    $next_date = date('Y-m-d', strtotime($next_date . ' +1 month'));
                    break;
                case 'quarterly':
                    $next_date = date('Y-m-d', strtotime($next_date . ' +3 months'));
                    break;
                case 'yearly':
                    $next_date = date('Y-m-d', strtotime($next_date . ' +1 year'));
                    break;
            }
            
            db_update('recurring_invoices', ['next_invoice_date' => $next_date], 'id = ?', [$recurring_id]);
            $success = "已為「{$recurring['title']}」成功生成草稿發票 #{$invoice_number}！";
        }
    }
    
    // 2. 新增周期性發票
    elseif (isset($_POST['create_recurring'])) {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'title' => trim($_POST['title']),
            'amount' => (float)$_POST['amount'],
            'frequency' => $_POST['frequency'],
            'start_date' => $_POST['start_date'],
            'next_invoice_date' => $_POST['start_date'],
            'status' => 'active',
            'notes' => trim($_POST['notes'] ?? ''),
            'created_by' => $_SESSION['user_id']
        ];
        db_insert('recurring_invoices', $data);
        $success = '周期性發票規則已成功設置！';
    }

    // 3. 編輯周期性發票 (補齊功能)
    elseif (isset($_POST['edit_recurring'])) {
        $recurring_id = (int)$_POST['recurring_id'];
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
            'title' => trim($_POST['title']),
            'amount' => (float)$_POST['amount'],
            'frequency' => $_POST['frequency'],
            'next_invoice_date' => $_POST['next_invoice_date'],
            'status' => $_POST['status'],
            'notes' => trim($_POST['notes'] ?? '')
        ];
        db_update('recurring_invoices', $data, 'id = ?', [$recurring_id]);
        $success = '周期性發票規則已成功更新！';
    }
    
    // 4. 操作 (暫停 / 恢復 / 刪除)
    elseif (isset($_POST['action'])) {
        $id = (int)$_POST['recurring_id'];
        if ($_POST['action'] === 'pause') {
            db_update('recurring_invoices', ['status' => 'paused'], 'id = ?', [$id]);
            $success = '已成功暫停此周期性發票。';
        } elseif ($_POST['action'] === 'resume') {
            db_update('recurring_invoices', ['status' => 'active'], 'id = ?', [$id]);
            $success = '已恢復此周期性發票。';
        } elseif ($_POST['action'] === 'delete') {
            db_delete('recurring_invoices', 'id = ?', [$id]);
            $success = '此周期性發票規則已徹底刪除。';
        }
    }
}

// 建立分頁與搜尋的 SQL 查詢
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(r.title LIKE ? OR c.company_name LIKE ? OR p.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "r.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM recurring_invoices r LEFT JOIN clients c ON r.client_id = c.id LEFT JOIN projects p ON r.project_id = p.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取當前頁資料
$sql = "SELECT r.*, c.company_name, p.title as project_title 
        FROM recurring_invoices r 
        LEFT JOIN clients c ON r.client_id = c.id 
        LEFT JOIN projects p ON r.project_id = p.id 
        WHERE $where_sql 
        ORDER BY r.status ASC, r.next_invoice_date ASC 
        LIMIT $per_page OFFSET $offset";
$recurring_invoices = db_fetch_all($sql, $params);

// 獲取關聯資料供表單使用
$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");

// 標籤設定
$frequency_labels = [
    'monthly' => ['label' => '每月', 'color' => 'primary', 'icon' => 'bi-calendar-month'],
    'quarterly' => ['label' => '每季', 'color' => 'info', 'icon' => 'bi-calendar3'],
    'yearly' => ['label' => '每年', 'color' => 'success', 'icon' => 'bi-calendar-check']
];

$status_options = [
    'active' => ['label' => '活躍運行中', 'color' => 'success', 'icon' => 'bi-play-circle'],
    'paused' => ['label' => '已暫停', 'color' => 'warning', 'icon' => 'bi-pause-circle'],
    'ended' => ['label' => '已結束', 'color' => 'secondary', 'icon' => 'bi-stop-circle']
];
?>
<?php $page_title = "周期性發票"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-arrow-repeat me-2 text-primary"></i> 周期性發票</h2>
                <p class="text-muted mb-0 d-none d-md-block">自動化管理月費、年費等定期收費服務規則</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createRecurringModal">
                <i class="bi bi-plus-circle me-1"></i> 新增周期發票
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="標題名稱 / 客戶 / 項目">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">運行狀態</label>
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
                        <a href="recurring_invoices.php" class="btn btn-light w-100 border">清除</a>
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
                                <th>發票規則標題</th>
                                <th>客戶名稱</th>
                                <th>關聯項目</th>
                                <th>頻率與金額</th>
                                <th>下次發票日</th>
                                <th>狀態</th>
                                <th width="200" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recurring_invoices as $r): 
                                $f_info = $frequency_labels[$r['frequency']] ?? $frequency_labels['monthly'];
                                $s_info = $status_options[$r['status']] ?? $status_options['ended'];
                            ?>
                            <tr class="<?= $r['status'] == 'paused' ? 'bg-light' : '' ?>">
                                <td>
                                    <div class="fw-bold text-slate-800"><i class="bi bi-arrow-repeat me-1 text-primary"></i><?= htmlspecialchars($r['title'] ?? '') ?></div>
                                    <?php if ($r['notes']): ?>
                                        <div class="small text-muted text-truncate" style="max-width: 150px;"><?= htmlspecialchars($r['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-slate-700"><?= htmlspecialchars($r['company_name'] ?? '未指定') ?></span>
                                </td>
                                <td>
                                    <span class="text-slate-600 small"><?= htmlspecialchars($r['project_title'] ?? '-') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $f_info['color'] ?> bg-opacity-10 text-<?= $f_info['color'] ?> me-2">
                                        <i class="bi <?= $f_info['icon'] ?> me-1"></i><?= $f_info['label'] ?>
                                    </span>
                                    <strong class="text-slate-800">HK$ <?= number_format($r['amount'] ?? 0, 2) ?></strong>
                                </td>
                                <td>
                                    <div class="text-slate-800 fw-medium"><i class="bi bi-calendar-event text-muted me-1"></i><?= $r['next_invoice_date'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $s_info['color'] ?> bg-opacity-10 text-<?= $s_info['color'] ?> px-2 py-1">
                                        <i class="bi <?= $s_info['icon'] ?> me-1"></i><?= $s_info['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($r['status'] == 'active'): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('確定要立即為此規則手動生成一張新草稿發票嗎？\n生成後，下次發票日期會自動延後。');">
                                            <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="generate_invoice" class="btn btn-sm btn-light border text-success me-1" title="立即生成發票">
                                                <i class="bi bi-file-earmark-plus-fill"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="action" value="pause" class="btn btn-sm btn-light border text-warning me-1" title="暫停執行">
                                                <i class="bi bi-pause-fill"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($r['status'] == 'paused'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="action" value="resume" class="btn btn-sm btn-light border text-success me-1" title="恢復執行">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editRecurringModal<?= $r['id'] ?>" title="編輯規則"><i class="bi bi-pencil-square"></i></button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('確定要永久刪除此周期性發票規則嗎？');">
                                        <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editRecurringModal<?= $r['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    編輯周期發票規則
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="edit_recurring" value="1">
                                                <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">客戶 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                            <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($clients as $c): ?>
                                                                <option value="<?= $c['id'] ?>" <?= $r['client_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name'] ?? '') ?></option>
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
                                                                <option value="<?= $p['id'] ?>" <?= $r['project_id'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['title'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">發票標題 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                                            <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($r['title'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">生成頻率 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-arrow-repeat"></i></span>
                                                            <select name="frequency" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <option value="monthly" <?= $r['frequency'] == 'monthly' ? 'selected' : '' ?>>每月</option>
                                                                <option value="quarterly" <?= $r['frequency'] == 'quarterly' ? 'selected' : '' ?>>每季</option>
                                                                <option value="yearly" <?= $r['frequency'] == 'yearly' ? 'selected' : '' ?>>每年</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">每次扣款金額 (HK$) *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                                            <input type="number" step="0.01" name="amount" class="form-control border-start-0 ps-0 shadow-none text-primary fw-bold" value="<?= $r['amount'] ?>" required>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">下次發票生成日 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-event"></i></span>
                                                            <input type="date" name="next_invoice_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $r['next_invoice_date'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">規則狀態 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                                            <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($status_options as $key => $opt): ?>
                                                                    <option value="<?= $key ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">備註說明</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                                            <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none" rows="2"><?= htmlspecialchars($r['notes'] ?? '') ?></textarea>
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
                            
                            <?php if (empty($recurring_invoices)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-arrow-repeat fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的周期性發票規則
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

<div class="modal fade" id="createRecurringModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-arrow-repeat fs-5"></i>
                        </div>
                        新增周期發票規則
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="create_recurring" value="1">
                    
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
                                    <option value="">無</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">發票標題 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" required placeholder="例如：每月系統維護服務費">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">生成頻率 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-arrow-repeat"></i></span>
                                <select name="frequency" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="monthly">每月</option>
                                    <option value="quarterly">每季</option>
                                    <option value="yearly">每年</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">每次扣款金額 (HK$) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" step="0.01" name="amount" class="form-control border-start-0 ps-0 shadow-none fw-bold text-primary" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">首次生成日期 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="start_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">備註說明</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none" rows="2" placeholder="顯示於每次生成之發票上..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 建立規則</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>