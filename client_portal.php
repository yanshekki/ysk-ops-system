<?php
require_once 'config.php';
require_once 'includes/db.php';

// 啟動 Session (確保沒有重複啟動)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$login_error = '';

// 處理登出
if (isset($_GET['logout'])) {
    unset($_SESSION['client_auth']);
    header("Location: client_portal.php");
    exit;
}

// 處理客戶登入 (獨立驗證，與員工後台分開)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $client = db_fetch_one("SELECT * FROM clients WHERE username = ? AND status = 'active'", [$username]);
        
        if ($client && password_verify($password, $client['password_hash'])) {
            $_SESSION['client_auth'] = $client;
            header("Location: client_portal.php");
            exit;
        } else {
            $login_error = '登入失敗：帳號或密碼錯誤，或帳號已被停用。';
        }
    } else {
        $login_error = '請輸入帳號與密碼。';
    }
}

// 判斷登入狀態並獲取資料
$is_logged_in = isset($_SESSION['client_auth']);
$client = $is_logged_in ? $_SESSION['client_auth'] : null;

$projects = [];
$invoices = [];
$outstanding_balance = 0;
$active_projects_count = 0;

// 分頁與 Tab 狀態設定
$active_tab = $_GET['tab'] ?? 'projects';
$p_page = max(1, (int)($_GET['p_page'] ?? 1));
$i_page = max(1, (int)($_GET['i_page'] ?? 1));
$per_page = 6; // 每頁顯示數量 (專案卡片較大，設為6個剛好)

$p_total_pages = 0;
$i_total_pages = 0;

if ($is_logged_in) {
    $client_id = $client['id'];
    
    // 1. 計算全域統計數據 (不受分頁影響)
    $global_stats = db_fetch_one("
        SELECT 
            (SELECT COUNT(*) FROM projects WHERE client_id = ? AND status NOT IN ('completed', 'cancelled')) as active_projects,
            (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE client_id = ? AND status IN ('sent', 'overdue', 'draft')) as outstanding_balance
    ", [$client_id, $client_id]);
    
    $active_projects_count = $global_stats['active_projects'];
    $outstanding_balance = $global_stats['outstanding_balance'];
    
    // 2. 計算總筆數與分頁
    $total_projects = db_fetch_one("SELECT COUNT(*) as c FROM projects WHERE client_id = ?", [$client_id])['c'] ?? 0;
    $total_invoices = db_fetch_one("SELECT COUNT(*) as c FROM invoices WHERE client_id = ?", [$client_id])['c'] ?? 0;
    
    $p_total_pages = ceil($total_projects / $per_page);
    $i_total_pages = ceil($total_invoices / $per_page);
    
    $p_offset = ($p_page - 1) * $per_page;
    $i_offset = ($i_page - 1) * $per_page;
    
    // 3. 獲取登入客戶專屬的項目與發票 (帶分頁限制)
    $projects = db_fetch_all("
        SELECT * FROM projects 
        WHERE client_id = ? 
        ORDER BY CASE WHEN status IN ('completed', 'cancelled') THEN 1 ELSE 0 END, updated_at DESC 
        LIMIT $per_page OFFSET $p_offset
    ", [$client_id]);
    
    $invoices = db_fetch_all("
        SELECT * FROM invoices 
        WHERE client_id = ? 
        ORDER BY issue_date DESC 
        LIMIT $per_page OFFSET $i_offset
    ", [$client_id]);
}

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
    'draft' => ['label' => '處理中', 'color' => 'secondary'],
    'sent' => ['label' => '待付款', 'color' => 'warning'],
    'paid' => ['label' => '已結清', 'color' => 'success'],
    'overdue' => ['label' => '已逾期', 'color' => 'danger'],
    'cancelled' => ['label' => '已作廢', 'color' => 'secondary']
];
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal | YSK Limited</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-color: #4f46e5;
            --brand-dark: #3730a3;
            --portal-bg: #f8fafc;
        }
        body { 
            font-family: 'Inter', 'Noto Sans TC', sans-serif;
            background-color: var(--portal-bg); 
            min-height: 100vh; 
            -webkit-font-smoothing: antialiased;
        }
        /* Login Page Styles */
        .login-wrapper {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .portal-login-card { 
            background: #ffffff; 
            border-radius: 20px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); 
            border: none;
            width: 100%;
            max-width: 420px;
        }
        
        /* Dashboard Styles */
        .portal-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .portal-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .nav-pills .nav-link {
            color: #64748b;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            margin-right: 8px;
            transition: all 0.2s ease;
        }
        .nav-pills .nav-link:hover { background-color: #f1f5f9; }
        .nav-pills .nav-link.active { 
            background: var(--brand-color); 
            color: white;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
        }
        .stat-box {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .project-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
        }
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -5px rgba(0,0,0,0.1);
        }
        .btn-brand { background-color: var(--brand-color); color: white; border: none; transition: 0.2s; }
        .btn-brand:hover { background-color: var(--brand-dark); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .badge-soft { padding: 6px 12px; font-weight: 600; border-radius: 6px; }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
    <div class="login-wrapper">
        <div class="portal-login-card p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-shield-lock fs-2"></i>
                </div>
                <h4 class="fw-bold text-slate-800 mb-1">YSK Client Portal</h4>
                <p class="text-muted small">客戶專屬自助服務與專案查詢系統</p>
            </div>
            
            <?php if ($login_error): ?>
                <div class="alert alert-danger border-0 small shadow-sm py-2"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="client_login" value="1">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-slate-600 small mb-1">公司登入帳號 (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control shadow-none border-start-0 ps-0 fw-medium" required placeholder="請輸入帳號">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold text-slate-600 small mb-1">安全密碼 (Password)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                        <input type="password" name="password" class="form-control shadow-none border-start-0 ps-0" required placeholder="••••••••">
                    </div>
                </div>
                <button type="submit" class="btn btn-brand btn-lg w-100 fw-bold shadow-sm py-2">
                    安全登入 <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>
            
            <div class="text-center mt-4 border-top pt-4">
                <small class="text-muted">忘記密碼？請聯絡您的 YSK 專案經理重設。<br>&copy; <?= date('Y') ?> YSK Limited.</small>
            </div>
        </div>
    </div>

<?php else: ?>
    <nav class="portal-navbar">
        <div class="container-xl d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="https://ysk.hk/logo.svg" alt="YSK" style="height: 28px; margin-right: 12px;">
                <span class="fw-bolder text-slate-800 border-start ps-3 ms-1" style="font-size: 1.1rem; letter-spacing: -0.5px;">Client Portal</span>
            </div>
            <div class="d-flex align-items-center">
                <div class="d-none d-md-block text-end me-3">
                    <div class="fw-bold text-slate-800" style="font-size: 0.9rem;"><?= htmlspecialchars($client['company_name']) ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($client['contact_person'] ?? '') ?></div>
                </div>
                <div class="bg-indigo bg-opacity-10 text-indigo rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm border border-indigo border-opacity-25" style="width: 40px; height: 40px; background-color:#e0e7ff; color:#4338ca;">
                    <span class="fw-bold fs-5"><?= mb_substr($client['company_name'], 0, 1, 'UTF-8') ?></span>
                </div>
                <a href="?logout=1" class="btn btn-sm btn-light border text-danger fw-semibold px-3"><i class="bi bi-box-arrow-right me-1"></i>登出</a>
            </div>
        </div>
    </nav>

    <div class="container-xl py-4 py-md-5">
        
        <div class="mb-4 pb-2">
            <h2 class="fw-bold text-slate-800 mb-1">歡迎回來，<?= htmlspecialchars($client['contact_person'] ?: $client['company_name']) ?>！</h2>
            <p class="text-muted">在這裡掌握您的專案開發進度，並輕鬆管理財務帳單。</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="stat-box d-flex align-items-center h-100">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center me-4" style="width: 60px; height: 60px;">
                        <i class="bi bi-rocket-takeoff fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-slate-500 mb-1 fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.8rem;">進行中及待審核專案</h6>
                        <h2 class="mb-0 fw-bold text-slate-800"><?= $active_projects_count ?> <span class="fs-6 text-muted fw-normal">個項目</span></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-box d-flex align-items-center h-100" style="<?= $outstanding_balance > 0 ? 'border-left: 4px solid #ef4444;' : 'border-left: 4px solid #10b981;' ?>">
                    <div class="bg-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?> bg-opacity-10 text-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?> rounded-3 d-flex align-items-center justify-content-center me-4" style="width: 60px; height: 60px;">
                        <i class="bi bi-wallet2 fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-slate-500 mb-1 fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.8rem;">未付賬單總餘額</h6>
                        <h2 class="mb-0 fw-bold text-<?= $outstanding_balance > 0 ? 'danger' : 'success' ?>">HK$ <?= number_format($outstanding_balance, 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="portal-card p-4">
            <ul class="nav nav-pills mb-4 border-bottom pb-3" id="portalTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab == 'projects' ? 'active' : '' ?>" id="projects-tab" data-bs-toggle="pill" data-bs-target="#projects" type="button" role="tab" onclick="history.replaceState(null, '', '?tab=projects&p_page=<?= $p_page ?>&i_page=<?= $i_page ?>')">
                        <i class="bi bi-folder2-open me-2"></i> 專案進度追蹤
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $active_tab == 'invoices' ? 'active' : '' ?>" id="invoices-tab" data-bs-toggle="pill" data-bs-target="#invoices" type="button" role="tab" onclick="history.replaceState(null, '', '?tab=invoices&p_page=<?= $p_page ?>&i_page=<?= $i_page ?>')">
                        <i class="bi bi-receipt-cutoff me-2"></i> 財務與發票
                        <?php if ($outstanding_balance > 0): ?>
                            <span class="badge bg-danger ms-2 rounded-pill shadow-sm">待處理</span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="portalTabsContent">
                
                <div class="tab-pane fade <?= $active_tab == 'projects' ? 'show active' : '' ?>" id="projects" role="tabpanel">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-5 bg-light rounded-3 border border-light-subtle my-2">
                            <i class="bi bi-folder-x fs-1 text-muted opacity-25 mb-3 d-block"></i>
                            <h6 class="fw-bold text-slate-700">目前沒有相關專案記錄</h6>
                            <p class="text-muted small">當我們為您開啟新專案時，將會顯示於此。</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($projects as $p): 
                                $stat = $status_options[$p['status']] ?? $status_options['planning'];
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="project-card h-100 p-4 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?> badge-soft border border-<?= $stat['color'] ?> border-opacity-25">
                                            <?= $stat['label'] ?>
                                        </span>
                                        <?php if($p['end_date']): ?>
                                            <small class="text-muted fw-medium"><i class="bi bi-calendar-check me-1"></i>交付日: <?= date('m/d', strtotime($p['end_date'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="fw-bold text-slate-800 mt-1 mb-2 lh-base"><?= htmlspecialchars($p['title'] ?? '') ?></h5>
                                    <p class="text-slate-500 small mb-4" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6;">
                                        <?= htmlspecialchars($p['description'] ?? '無專案描述') ?>
                                    </p>
                                    
                                    <div class="mt-auto pt-3 border-top">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <small class="fw-bold text-slate-600">總體開發進度</small>
                                            <span class="badge bg-<?= $p['progress_percent'] == 100 ? 'success' : 'primary' ?> rounded-pill px-2"><?= $p['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 8px; border-radius: 4px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-<?= $p['progress_percent'] == 100 ? 'success' : 'primary' ?> progress-bar-striped <?= $p['progress_percent'] < 100 && $p['progress_percent'] > 0 ? 'progress-bar-animated' : '' ?>" style="width: <?= $p['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($p_total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav>
                                <ul class="pagination shadow-sm">
                                    <li class="page-item <?= $p_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link text-slate-500" href="?tab=projects&p_page=<?= $p_page-1 ?>&i_page=<?= $i_page ?>">上一頁</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $p_total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $p_page ? 'active' : '' ?>">
                                        <a class="page-link <?= $i == $p_page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?tab=projects&p_page=<?= $i ?>&i_page=<?= $i_page ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $p_page >= $p_total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link text-slate-500" href="?tab=projects&p_page=<?= $p_page+1 ?>&i_page=<?= $i_page ?>">下一頁</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="tab-pane fade <?= $active_tab == 'invoices' ? 'show active' : '' ?>" id="invoices" role="tabpanel">
                    <?php if (empty($invoices)): ?>
                        <div class="text-center py-5 bg-light rounded-3 border border-light-subtle my-2">
                            <i class="bi bi-receipt fs-1 text-muted opacity-25 mb-3 d-block"></i>
                            <h6 class="fw-bold text-slate-700">目前沒有發票記錄</h6>
                            <p class="text-muted small">當我們為您開立服務發票時，將會顯示於此。</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border rounded-3 overflow-hidden mb-0">
                                <thead class="bg-light text-slate-600 font-monospace" style="font-size: 0.85rem;">
                                    <tr>
                                        <th class="py-3 ps-3">發票編號 (Invoice)</th>
                                        <th class="py-3">開立日</th>
                                        <th class="py-3">付款到期日</th>
                                        <th class="py-3 text-end">應付總額 (HK$)</th>
                                        <th class="py-3 text-center">狀態</th>
                                        <th class="py-3 text-end pe-3">操作與付款</th>
                                    </tr>
                                </thead>
                                <tbody class="border-top-0">
                                    <?php foreach ($invoices as $inv): 
                                        $istat = $inv_status_options[$inv['status']] ?? $inv_status_options['draft'];
                                        $is_unpaid = in_array($inv['status'], ['sent', 'overdue', 'draft']);
                                    ?>
                                    <tr>
                                        <td class="ps-3 py-3 fw-bold text-slate-800">
                                            <i class="bi bi-file-earmark-text text-muted me-2"></i><?= htmlspecialchars($inv['invoice_number'] ?? '') ?>
                                        </td>
                                        <td class="text-slate-600 small"><?= $inv['issue_date'] ?></td>
                                        <td class="text-<?= $inv['status'] == 'overdue' ? 'danger fw-bold' : 'slate-600' ?> small">
                                            <?= $inv['due_date'] ?>
                                            <?= $inv['status'] == 'overdue' ? '<i class="bi bi-exclamation-triangle-fill ms-1"></i>' : '' ?>
                                        </td>
                                        <td class="text-end fw-bold text-slate-800">
                                            <?= number_format($inv['total_amount'] ?? 0, 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $istat['color'] ?> bg-opacity-10 text-<?= $istat['color'] ?> badge-soft border border-<?= $istat['color'] ?> border-opacity-25">
                                                <?= $istat['label'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="invoice_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-light border text-primary fw-medium" title="檢視及下載 PDF">
                                                    <i class="bi bi-download me-1"></i> 檢視
                                                </a>
                                                
                                                <?php if (!$is_unpaid): ?>
                                                    <span class="btn btn-sm btn-success fw-bold px-3 text-white shadow-sm" style="pointer-events: none;">
                                                        <i class="bi bi-check-circle-fill me-1"></i> 已結清
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($i_total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav>
                                <ul class="pagination shadow-sm">
                                    <li class="page-item <?= $i_page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link text-slate-500" href="?tab=invoices&p_page=<?= $p_page ?>&i_page=<?= $i_page-1 ?>">上一頁</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $i_total_pages; $i++): ?>
                                    <li class="page-item <?= $i == $i_page ? 'active' : '' ?>">
                                        <a class="page-link <?= $i == $i_page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?tab=invoices&p_page=<?= $p_page ?>&i_page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $i_page >= $i_total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link text-slate-500" href="?tab=invoices&p_page=<?= $p_page ?>&i_page=<?= $i_page+1 ?>">下一頁</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
        
        <div class="text-center mt-5">
            <p class="text-slate-500 small mb-1">&copy; <?= date('Y') ?> YSK Limited. All rights reserved.</p>
            <p class="text-slate-400 small">若需協助，請聯絡您的專案經理或電郵至 <a href="mailto:email@ysk.hk" class="text-brand fw-semibold text-decoration-none">email@ysk.hk</a></p>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>