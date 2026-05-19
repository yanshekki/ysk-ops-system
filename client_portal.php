<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

// 此為對外公開的客戶門戶，正式環境應改用 Token 驗證或客戶專屬密碼登入
// 目前為 Demo 模式，透過下拉選單進入

$client_id = (int)($_GET['client_id'] ?? 0);
$client = null;
$projects = [];
$invoices = [];
$outstanding_balance = 0;
$active_projects_count = 0;

if ($client_id) {
    $client = db_fetch_one("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if ($client) {
        // 獲取項目與發票
        $projects = db_fetch_all("SELECT * FROM projects WHERE client_id = ? ORDER BY CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END, updated_at DESC", [$client_id]);
        $invoices = db_fetch_all("SELECT * FROM invoices WHERE client_id = ? ORDER BY issue_date DESC", [$client_id]);
        
        // 計算未付款總額與活躍專案數
        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['sent', 'overdue', 'draft'])) {
                $outstanding_balance += $inv['total_amount'];
            }
        }
        foreach ($projects as $p) {
            if (!in_array($p['status'], ['completed', 'cancelled'])) {
                $active_projects_count++;
            }
        }
    }
}

$clients = db_fetch_all("SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name");

// 狀態標籤對應表
$status_options = [
    'planning' => ['label' => '規劃中', 'color' => 'secondary'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary'],
    'review' => ['label' => '審核中', 'color' => 'info'],
    'completed' => ['label' => '已完成', 'color' => 'success'],
    'on_hold' => ['label' => '暫停', 'color' => 'warning'],
    'cancelled' => ['label' => '已取消', 'color' => 'danger']
];

$inv_status_options = [
    'draft' => ['label' => '處理中', 'color' => 'secondary'], // 對客戶顯示為處理中較為適合
    'sent' => ['label' => '待付款', 'color' => 'warning'],
    'paid' => ['label' => '已付款', 'color' => 'success'],
    'overdue' => ['label' => '已過期', 'color' => 'danger'],
    'cancelled' => ['label' => '已取消', 'color' => 'secondary']
];
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客戶自助門戶 | YSK Limited</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-color: #4f46e5;
            --brand-dark: #3730a3;
        }
        body { 
            font-family: 'Inter', 'Noto Sans TC', sans-serif;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); 
            min-height: 100vh; 
            -webkit-font-smoothing: antialiased;
        }
        .portal-card { 
            background: #ffffff; 
            border-radius: 16px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); 
            border: none;
        }
        .nav-pills .nav-link {
            color: #64748b;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            margin-right: 8px;
            transition: all 0.2s ease;
        }
        .nav-pills .nav-link:hover {
            background-color: #f1f5f9;
        }
        .nav-pills .nav-link.active { 
            background: var(--brand-color); 
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }
        .stat-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .project-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .btn-brand {
            background-color: var(--brand-color);
            color: white;
            border: none;
        }
        .btn-brand:hover {
            background-color: var(--brand-dark);
            color: white;
        }
        .badge-soft { padding: 6px 12px; font-weight: 600; }
    </style>
</head>
<body>

<div class="container py-5">
    
    <div class="text-center mb-5">
        <h1 class="text-white fw-bolder mb-2" style="letter-spacing: -1px;">YSK Client Portal</h1>
        <p class="text-white-50 fs-5">管理您的專案進度、服務發票與線上付款</p>
    </div>
    
    <?php if (!$client_id || !$client): ?>
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="portal-card p-5">
                <div class="text-center mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-person-bounding-box fs-2"></i>
                    </div>
                    <h4 class="fw-bold text-slate-800">登入自助門戶</h4>
                    <p class="text-muted small">此為展示版本，請直接從下方選擇您的公司以進入系統。</p>
                </div>
                
                <form method="GET">
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-slate-700">企業/公司名稱</label>
                        <select name="client_id" class="form-select form-select-lg shadow-none" required>
                            <option value="">-- 請選擇您的公司 --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-brand btn-lg w-100 fw-bold shadow-sm">
                        進入門戶 <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <small class="text-muted">如有任何系統登入問題，請聯絡您的專案經理或前往 <a href="https://ysk.hk" target="_blank" class="text-decoration-none">ysk.hk</a> 尋求協助。</small>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="portal-card p-4 p-md-5">
                
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-4 mb-4">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="bg-indigo bg-opacity-10 text-indigo rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background-color:#e0e7ff; color:#4338ca;">
                            <span class="fw-bold fs-3"><?= mb_substr($client['company_name'], 0, 1, 'UTF-8') ?></span>
                        </div>
                        <div>
                            <h3 class="fw-bold text-slate-800 mb-1"><?= htmlspecialchars($client['company_name']) ?></h3>
                            <div class="text-muted small">
                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($client['contact_person'] ?: '未提供聯絡人') ?> 
                                <span class="mx-2">|</span> 
                                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($client['email'] ?: '未提供電郵') ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="client_portal.php" class="btn btn-light border text-muted fw-medium"><i class="bi bi-box-arrow-right me-1"></i> 登出切換</a>
                    </div>
                </div>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="stat-box d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                <i class="bi bi-kanban fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-slate-500 mb-1 fw-semibold">進行中的專案</h6>
                                <h3 class="mb-0 fw-bold text-slate-800"><?= $active_projects_count ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-box d-flex align-items-center">
                            <div class="bg-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?> bg-opacity-10 text-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                <i class="bi bi-wallet2 fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-slate-500 mb-1 fw-semibold">未付賬單餘額 (HK$)</h6>
                                <h3 class="mb-0 fw-bold text-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?>"><?= number_format($outstanding_balance, 2) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <ul class="nav nav-pills mb-4" id="portalTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects" type="button" role="tab">
                            <i class="bi bi-folder2-open me-1"></i> 專案進度
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="invoices-tab" data-bs-toggle="pill" data-bs-target="#invoices" type="button" role="tab">
                            <i class="bi bi-receipt-cutoff me-1"></i> 發票與付款
                            <?php if ($outstanding_balance > 0): ?>
                                <span class="badge bg-danger ms-1 rounded-pill">!</span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="portalTabsContent">
                    
                    <div class="tab-pane fade show active" id="projects" role="tabpanel">
                        <?php if (empty($projects)): ?>
                            <div class="text-center py-5 bg-light rounded-3 border border-light-subtle">
                                <i class="bi bi-folder-x fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-slate-700">目前沒有相關專案</h6>
                                <p class="text-muted small">當我們為您開啟新專案時，將會顯示於此。</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($projects as $p): 
                                    $stat = $status_options[$p['status']] ?? $status_options['planning'];
                                ?>
                                <div class="col-md-6">
                                    <div class="card project-card h-100 p-4">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?> badge-soft">
                                                <?= $stat['label'] ?>
                                            </span>
                                            <?php if($p['end_date']): ?>
                                                <small class="text-muted"><i class="bi bi-calendar-event me-1"></i>預計完成: <?= $p['end_date'] ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="fw-bold text-slate-800 mt-2 mb-2"><?= htmlspecialchars($p['title'] ?? '') ?></h5>
                                        <p class="text-slate-500 small mb-4" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            <?= htmlspecialchars($p['description'] ?? '無專案描述') ?>
                                        </p>
                                        
                                        <div class="mt-auto pt-3 border-top">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <small class="fw-semibold text-slate-700">開發進度</small>
                                                <small class="fw-bold text-<?= $p['progress_percent'] == 100 ? 'success' : 'primary' ?>"><?= $p['progress_percent'] ?>%</small>
                                            </div>
                                            <div class="progress" style="height: 8px; border-radius: 4px; background-color: #f1f5f9;">
                                                <div class="progress-bar bg-<?= $p['progress_percent'] == 100 ? 'success' : 'primary' ?> progress-bar-striped <?= $p['progress_percent'] < 100 ? 'progress-bar-animated' : '' ?>" style="width: <?= $p['progress_percent'] ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="invoices" role="tabpanel">
                        <?php if (empty($invoices)): ?>
                            <div class="text-center py-5 bg-light rounded-3 border border-light-subtle">
                                <i class="bi bi-receipt fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                <h6 class="fw-bold text-slate-700">目前沒有發票記錄</h6>
                                <p class="text-muted small">當我們為您開立服務發票時，將會顯示於此。</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border rounded-3 overflow-hidden">
                                    <thead class="bg-light text-slate-600 font-monospace" style="font-size: 0.85rem;">
                                        <tr>
                                            <th class="py-3 ps-4">發票編號</th>
                                            <th class="py-3">開立日期</th>
                                            <th class="py-3">到期日期</th>
                                            <th class="py-3 text-end">應付總額 (HK$)</th>
                                            <th class="py-3 text-center">狀態</th>
                                            <th class="py-3 text-end pe-4">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody class="border-top-0">
                                        <?php foreach ($invoices as $inv): 
                                            $istat = $inv_status_options[$inv['status']] ?? $inv_status_options['draft'];
                                            $is_unpaid = in_array($inv['status'], ['sent', 'overdue', 'draft']);
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-slate-800">
                                                <i class="bi bi-file-earmark-text text-muted me-1"></i><?= htmlspecialchars($inv['invoice_number'] ?? '') ?>
                                            </td>
                                            <td class="text-slate-600 small"><?= $inv['issue_date'] ?></td>
                                            <td class="text-<?= $inv['status'] == 'overdue' ? 'danger fw-bold' : 'slate-600' ?> small"><?= $inv['due_date'] ?></td>
                                            <td class="text-end fw-bold text-slate-800">
                                                <?= number_format($inv['total_amount'] ?? 0, 2) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $istat['color'] ?> bg-opacity-10 text-<?= $istat['color'] ?> badge-soft">
                                                    <?= $istat['label'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <a href="invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-light border text-primary" title="下載 PDF">
                                                        <i class="bi bi-download"></i> <span class="d-none d-md-inline ms-1">PDF</span>
                                                    </a>
                                                    
                                                    <?php if ($is_unpaid): ?>
                                                        <a href="stripe_checkout.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-brand fw-bold shadow-sm">
                                                            💳 付款
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-light border text-success fw-bold disabled" disabled>
                                                            <i class="bi bi-check2-circle"></i> 已付清
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
            
            <div class="text-center mt-5">
                <p class="text-white-50 small mb-1">&copy; <?= date('Y') ?> YSK Limited. All rights reserved.</p>
                <p class="text-white-50 small">客戶支援信箱：<a href="mailto:info@ysk.hk" class="text-white fw-semibold text-decoration-none">info@ysk.hk</a></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>