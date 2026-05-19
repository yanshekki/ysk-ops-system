<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_invoice'])) {
    $data = [
        'invoice_number' => 'INV-' . date('Ymd') . '-' . str_pad(rand(1,999), 3, '0', STR_PAD_LEFT),
        'client_id' => (int)$_POST['client_id'],
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'issue_date' => $_POST['issue_date'],
        'due_date' => $_POST['due_date'],
        'subtotal' => (float)$_POST['subtotal'],
        'tax_percent' => (float)($_POST['tax_percent'] ?? 0),
        'total_amount' => (float)$_POST['subtotal'] * (1 + (float)($_POST['tax_percent'] ?? 0)/100),
        'status' => 'draft',
        'notes' => trim($_POST['notes'] ?? ''),
        'created_by' => $_SESSION['user_id']
    ];
    $invoice_id = db_insert('invoices', $data);
    $success = '發票 #' . $data['invoice_number'] . ' 已建立！';
}

$invoices = db_fetch_all("
    SELECT i.*, c.company_name, p.title as project_title 
    FROM invoices i 
    LEFT JOIN clients c ON i.client_id = c.id 
    LEFT JOIN projects p ON i.project_id = p.id 
    ORDER BY i.created_at DESC
");

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
        <div class="d-flex align-items-center mb-4 px-2"><i class="bi bi-gear-fill fs-3 me-2 text-primary"></i><span class="fs-4 fw-bold">YSK Ops</span></div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="clients.php" class="nav-link mb-1"><i class="bi bi-people me-2"></i> 客戶管理</a>
            <a href="projects.php" class="nav-link mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <a href="tasks.php" class="nav-link mb-1"><i class="bi bi-list-task me-2"></i> 任務追蹤</a>
            <a href="invoices.php" class="nav-link active mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-receipt me-2"></i> 發票管理</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"><i class="bi bi-plus-circle me-1"></i> 開立新發票</button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>發票編號</th><th>客戶</th><th>項目</th><th>開立日期</th><th>到期日</th><th class="text-end">金額 (HK$)</th><th>狀態</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): 
                            $status_class = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','cancelled'=>'dark'][$inv['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                            <td><?= htmlspecialchars($inv['company_name']) ?></td>
                            <td><?= htmlspecialchars($inv['project_title'] ?: '-') ?></td>
                            <td><?= $inv['issue_date'] ?></td>
                            <td><?= $inv['due_date'] ?></td>
                            <td class="text-end"><?= number_format($inv['total_amount'], 2) ?></td>
                            <td><span class="badge bg-<?= $status_class ?>"><?= ucfirst($inv['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-3 text-muted small">提示：此為基本版本，完整版可加入 PDF 產生、付款記錄、 recurring 月費自動開立等功能。</div>
    </div>
</div>

<!-- Add Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">開立新發票</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="add_invoice" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">客戶 *</label>
                            <select name="client_id" class="form-select" required>
                                <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">關聯項目 (可選)</label>
                            <select name="project_id" class="form-select">
                                <option value="">無</option>
                                <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">開立日期 *</label>
                            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">到期日期 *</label>
                            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">小計 (HK$)</label>
                            <input type="number" step="0.01" name="subtotal" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">稅率 %</label>
                            <input type="number" step="0.01" name="tax_percent" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">總金額 (HK$)</label>
                            <input type="text" class="form-control" value="自動計算" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">備註 / 項目明細</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="例如：2026年5月 AI 自動化月費 + 開發工時"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">開立發票</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>