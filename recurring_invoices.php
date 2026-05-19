<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

// Handle add recurring
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_recurring'])) {
    $data = [
        'client_id' => (int)$_POST['client_id'],
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'amount' => (float)$_POST['amount'],
        'frequency' => $_POST['frequency'],
        'next_invoice_date' => $_POST['next_invoice_date'],
        'description' => trim($_POST['description'] ?? ''),
        'is_active' => 1
    ];
    db_insert('recurring_invoices', $data);
    $success = '周期性發票已設定！';
}

// Fetch recurring
$recurrings = db_fetch_all("
    SELECT r.*, c.company_name, p.title as project_title 
    FROM recurring_invoices r 
    LEFT JOIN clients c ON r.client_id = c.id 
    LEFT JOIN projects p ON r.project_id = p.id 
    ORDER BY r.next_invoice_date ASC
");

$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>周期性發票 | <?= SITE_NAME ?></title>
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
            <a href="recurring_invoices.php" class="nav-link active mb-1"><i class="bi bi-arrow-repeat me-2"></i> 周期性發票</a>
            <a href="invoices.php" class="nav-link mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-arrow-repeat me-2"></i> 周期性發票設定</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecurringModal">
                <i class="bi bi-plus-circle me-1"></i> 新增周期性發票
            </button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>客戶</th>
                            <th>項目</th>
                            <th>金額 (HK$)</th>
                            <th>頻率</th>
                            <th>下次開立日</th>
                            <th>說明</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurrings as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['company_name']) ?></td>
                            <td><?= htmlspecialchars($r['project_title'] ?: '-') ?></td>
                            <td class="text-end"><strong><?= number_format($r['amount'], 2) ?></strong></td>
                            <td><span class="badge bg-info"><?= ucfirst($r['frequency']) ?></span></td>
                            <td><?= $r['next_invoice_date'] ?></td>
                            <td><?= htmlspecialchars($r['description']) ?></td>
                            <td>
                                <?php if ($r['is_active']): ?>
                                    <span class="badge bg-success">正常</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">停用</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Recurring Modal -->
<div class="modal fade" id="addRecurringModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增周期性發票</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_recurring" value="1">
                    <div class="mb-3">
                        <label class="form-label">客戶 *</label>
                        <select name="client_id" class="form-select" required>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">關聯項目 (可選)</label>
                        <select name="project_id" class="form-select">
                            <option value="">無</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">金額 (HK$) *</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">頻率 *</label>
                            <select name="frequency" class="form-select" required>
                                <option value="monthly">每月</option>
                                <option value="quarterly">每季</option>
                                <option value="yearly">每年</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">下次開立日 *</label>
                        <input type="date" name="next_invoice_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 month')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">說明</label>
                        <input type="text" name="description" class="form-control" placeholder="例如：2026年月費計劃">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">設定周期性發票</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>