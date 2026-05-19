<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

// Handle generate invoice from recurring
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    $recurring_id = (int)$_POST['recurring_id'];
    $recurring = db_fetch_one("SELECT * FROM recurring_invoices WHERE id = ?", [$recurring_id]);
    
    if ($recurring && $recurring['status'] == 'active') {
        // Create new invoice
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
        
        $invoice_id = db_insert('invoices', $invoice_data);
        
        // Update next_invoice_date based on frequency
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
        
        $success = "已為「{$recurring['title']}」生成發票 #{$invoice_number}！";
    }
}

// Handle create recurring invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_recurring'])) {
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
    $success = '周期性發票已設置！';
}

// Handle pause/resume/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['recurring_id'];
    if ($_POST['action'] === 'pause') {
        db_update('recurring_invoices', ['status' => 'paused'], 'id = ?', [$id]);
        $success = '已暫停周期性發票';
    } elseif ($_POST['action'] === 'resume') {
        db_update('recurring_invoices', ['status' => 'active'], 'id = ?', [$id]);
        $success = '已恢復周期性發票';
    } elseif ($_POST['action'] === 'delete') {
        db_delete('recurring_invoices', 'id = ?', [$id]);
        $success = '周期性發票已刪除';
    }
}

// Fetch all recurring invoices
$recurring_invoices = db_fetch_all("
    SELECT r.*, c.company_name, p.title as project_title 
    FROM recurring_invoices r 
    LEFT JOIN clients c ON r.client_id = c.id 
    LEFT JOIN projects p ON r.project_id = p.id 
    ORDER BY r.next_invoice_date ASC
");

$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");

$frequency_labels = [
    'monthly' => '每月',
    'quarterly' => '每季',
    'yearly' => '每年'
];
?>
<?php $page_title = "周期性發票"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Unified Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-arrow-repeat me-2"></i> 周期性發票</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRecurringModal">
                <i class="bi bi-plus-circle me-1"></i> 新增周期性發票
            </button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>標題</th>
                            <th>客戶</th>
                            <th>項目</th>
                            <th>金額</th>
                            <th>頻率</th>
                            <th>下次發票日</th>
                            <th>狀態</th>
                            <th width="220">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurring_invoices as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['title']) ?></strong></td>
                            <td><?= htmlspecialchars($r['company_name']) ?></td>
                            <td><?= htmlspecialchars($r['project_title'] ?: '-') ?></td>
                            <td class="text-end"><strong>HK$ <?= number_format($r['amount'], 2) ?></strong></td>
                            <td><span class="badge bg-info"><?= $frequency_labels[$r['frequency']] ?></span></td>
                            <td><?= $r['next_invoice_date'] ?></td>
                            <td>
                                <?php if ($r['status'] == 'active'): ?>
                                    <span class="badge bg-success">活躍</span>
                                <?php elseif ($r['status'] == 'paused'): ?>
                                    <span class="badge bg-warning">暫停</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">已結束</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] == 'active'): ?>
                                    <!-- Generate Invoice Button -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="generate_invoice" class="btn btn-sm btn-success">
                                            <i class="bi bi-file-earmark-plus me-1"></i> 生成發票
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="action" value="pause" class="btn btn-sm btn-warning">暫停</button>
                                    </form>
                                <?php elseif ($r['status'] == 'paused'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="action" value="resume" class="btn btn-sm btn-success">恢復</button>
                                    </form>
                                <?php endif; ?>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此周期性發票？');">
                                    <input type="hidden" name="recurring_id" value="<?= $r['id'] ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recurring_invoices)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">暫無周期性發票</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Recurring Invoice Modal -->
<div class="modal fade" id="createRecurringModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增周期性發票</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="create_recurring" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">客戶 *</label>
                            <select name="client_id" class="form-select" required>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">關聯項目 (可選)</label>
                            <select name="project_id" class="form-select">
                                <option value="">無</option>
                                <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">發票標題 *</label>
                            <input type="text" name="title" class="form-control" required placeholder="例如：月度服務費">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">金額 (HK$) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">頻率 *</label>
                            <select name="frequency" class="form-select" required>
                                <option value="monthly">每月</option>
                                <option value="quarterly">每季</option>
                                <option value="yearly">每年</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">開始日期 *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">備註</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">建立周期性發票</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>