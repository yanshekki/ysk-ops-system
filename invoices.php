<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle create invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $data = [
        'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT),
        'client_id' => (int)$_POST['client_id'],
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'issue_date' => date('Y-m-d'),
        'due_date' => $_POST['due_date'],
        'subtotal' => (float)$_POST['subtotal'],
        'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
        'total_amount' => (float)$_POST['total_amount'],
        'status' => 'draft',
        'notes' => trim($_POST['notes'] ?? ''),
        'created_by' => $_SESSION['user_id']
    ];
    db_insert('invoices', $data);
    $success = '發票已新增！';
}

// Build query for count
$count_sql = "SELECT COUNT(*) as total FROM invoices WHERE 1=1";
$count_params = [];

if ($search) {
    $count_sql .= " AND (invoice_number LIKE ? OR (SELECT company_name FROM clients WHERE id = invoices.client_id) LIKE ? OR (SELECT title FROM projects WHERE id = invoices.project_id) LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
}

$total = db_fetch_one($count_sql, $count_params)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// Fetch invoices with pagination
$sql = "SELECT i.*, c.company_name, p.title as project_title FROM invoices i LEFT JOIN clients c ON i.client_id = c.id LEFT JOIN projects p ON i.project_id = p.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (i.invoice_number LIKE ? OR c.company_name LIKE ? OR p.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY i.issue_date DESC LIMIT $per_page OFFSET $offset";
$invoices = db_fetch_all($sql, $params);

$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>發票管理 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;">
        <div class="d-flex align-items-center mb-4 px-2">
            <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
            <span class="fs-4 fw-bold">YSK Ops</span>
        </div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="invoices.php" class="nav-link active mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <a href="recurring_invoices.php" class="nav-link mb-1"><i class="bi bi-arrow-repeat me-2"></i> 周期性發票</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt me-2"></i> 發票管理</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
                <i class="bi bi-plus-circle me-1"></i> 新增發票
            </button>
        </div>
        
        <!-- Search and Filter -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">搜尋</label>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="發票號 / 客戶 / 項目">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">狀態</label>
                        <select name="status" class="form-select">
                            <option value="">全部</option>
                            <option value="draft" <?= $status_filter=='draft'?'selected':'' ?>>草稿</option>
                            <option value="sent" <?= $status_filter=='sent'?'selected':'' ?>>已發送</option>
                            <option value="paid" <?= $status_filter=='paid'?'selected':'' ?>>已付款</option>
                            <option value="overdue" <?= $status_filter=='overdue'?'selected':'' ?>>已過期</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100 mt-4">搜尋</button>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="invoices.php" class="btn btn-outline-secondary mt-4">清除</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>發票號</th>
                            <th>客戶</th>
                            <th>項目</th>
                            <th>開立日</th>
                            <th>到期日</th>
                            <th class="text-end">金額 (HK$)</th>
                            <th>狀態</th>
                            <th width="200">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><strong><?= $inv['invoice_number'] ?></strong></td>
                            <td><?= htmlspecialchars($inv['company_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($inv['project_title'] ?? '-') ?></td>
                            <td><?= $inv['issue_date'] ?></td>
                            <td><?= $inv['due_date'] ?></td>
                            <td class="text-end"><strong><?= number_format($inv['total_amount'] ?? 0, 2) ?></strong></td>
                            <td>
                                <span class="badge bg-<?= $inv['status'] == 'paid' ? 'success' : ($inv['status'] == 'overdue' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst($inv['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($inv['status'] != 'paid'): ?>
                                <a href="stripe_checkout.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-success">
                                    <i class="bi bi-credit-card me-1"></i> Stripe 支付
                                </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-primary">編輯</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增發票</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="create_invoice" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">客戶 *</label>
                            <select name="client_id" class="form-select" required>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">關聯項目 (可選)</label>
                            <select name="project_id" class="form-select">
                                <option value="">無</option>
                                <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">開立日 *</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">到期日 *</label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">小計 (HK$)</label>
                            <input type="number" step="0.01" name="subtotal" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">備註</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">新增發票</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>