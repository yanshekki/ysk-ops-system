<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// This is a public portal - no login required for demo
// In production, use token-based access or client login

$client_id = $_GET['client_id'] ?? 0;
$client = null;
$projects = [];
$invoices = [];

if ($client_id) {
    $client = db_fetch_one("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if ($client) {
        $projects = db_fetch_all("SELECT * FROM projects WHERE client_id = ? ORDER BY updated_at DESC", [$client_id]);
        $invoices = db_fetch_all("SELECT * FROM invoices WHERE client_id = ? ORDER BY issue_date DESC", [$client_id]);
    }
}

$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客戶自助門戶 | YSK Limited</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .portal-card { background: white; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .nav-pills .nav-link.active { background: #0d6efd; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="text-white display-4 fw-bold">歡迎來到 YSK Limited 客戶自助門戶</h1>
        <p class="text-white-50 lead">查看您的項目進度、發票及服務詳情</p>
    </div>
    
    <?php if (!$client_id): ?>
    <!-- Client Selection -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="portal-card p-5">
                <h4 class="mb-4 text-center">請選擇您的公司</h4>
                <form method="GET">
                    <select name="client_id" class="form-select form-select-lg mb-3" required>
                        <option value="">請選擇公司...</option>
                        <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-lg w-100">進入門戶</button>
                </form>
                <div class="text-center mt-4">
                    <small class="text-muted">如有問題請聯絡我們： <a href="https://ysk.hk" target="_blank">ysk.hk</a></small>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Client Portal Content -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="portal-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="mb-1"><?= htmlspecialchars($client['company_name']) ?></h3>
                        <p class="text-muted mb-0">聯絡人：<?= htmlspecialchars($client['contact_person'] ?: '-') ?> • <?= htmlspecialchars($client['email'] ?: '-') ?></p>
                    </div>
                    <a href="client_portal.php" class="btn btn-outline-secondary">切換客戶</a>
                </div>
                
                <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-projects-tab" data-bs-toggle="pill" data-bs-target="#pills-projects" type="button">項目進度</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-invoices-tab" data-bs-toggle="pill" data-bs-target="#pills-invoices" type="button">發票記錄</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="pills-tabContent">
                    <!-- Projects Tab -->
                    <div class="tab-pane fade show active" id="pills-projects">
                        <h5 class="mb-3">您的項目</h5>
                        <?php if (empty($projects)): ?>
                            <div class="alert alert-info">暫無正在進行的項目</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($projects as $p): ?>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($p['title']) ?></h6>
                                            <p class="card-text small text-muted"><?= htmlspecialchars(substr($p['description'], 0, 100)) ?>...</p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <div>
                                                    <span class="badge bg-<?= $p['status'] == 'completed' ? 'success' : 'primary' ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                                                    </span>
                                                </div>
                                                <div class="text-end">
                                                    <div class="progress" style="height: 8px; width: 120px;">
                                                        <div class="progress-bar" style="width: <?= $p['progress_percent'] ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?= $p['progress_percent'] ?>% 完成</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Invoices Tab -->
                    <div class="tab-pane fade" id="pills-invoices">
                        <h5 class="mb-3">您的發票記錄</h5>
                        <?php if (empty($invoices)): ?>
                            <div class="alert alert-info">暫無發票記錄</div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>發票號</th>
                                        <th>開立日</th>
                                        <th>到期日</th>
                                        <th class="text-end">金額</th>
                                        <th>狀態</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td><strong><?= $inv['invoice_number'] ?></strong></td>
                                        <td><?= $inv['issue_date'] ?></td>
                                        <td><?= $inv['due_date'] ?></td>
                                        <td class="text-end">HK$ <?= number_format($inv['total_amount'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $inv['status'] == 'paid' ? 'success' : ($inv['status'] == 'overdue' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($inv['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-white-50 small">如有任何問題請電郵我們： <a href="mailto:info@ysk.hk" class="text-white">info@ysk.hk</a></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>