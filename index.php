<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$login_error = '';
$show_login = true;
$page_title = "安全登入系統"; // 預設 Title (登入前)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (login_user($username, $password)) {
        header("Location: index.php");
        exit;
    } else {
        $login_error = '用戶名或密碼錯誤！';
    }
}

if (is_logged_in()) {
    $show_login = false;
    $page_title = "控制台儀表板"; // 登入後的 Title
    $user = current_user();
    
    // Global search
    $global_search = trim($_GET['global_search'] ?? '');
    $global_results = [];
    
    if ($global_search) {
        $global_results['clients'] = db_fetch_all(
            "SELECT id, company_name, contact_person, email FROM clients 
             WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? 
             LIMIT 5", 
            ["%$global_search%", "%$global_search%", "%$global_search%"]
        );
        $global_results['projects'] = db_fetch_all(
            "SELECT p.id, p.title, p.status, c.company_name 
             FROM projects p 
             LEFT JOIN clients c ON p.client_id = c.id 
             WHERE p.title LIKE ? OR p.description LIKE ? OR c.company_name LIKE ? 
             LIMIT 5", 
            ["%$global_search%", "%$global_search%", "%$global_search%"]
        );
        $global_results['invoices'] = db_fetch_all(
            "SELECT i.id, i.invoice_number, i.total_amount, i.status, c.company_name 
             FROM invoices i 
             LEFT JOIN clients c ON i.client_id = c.id 
             WHERE i.invoice_number LIKE ? OR c.company_name LIKE ? 
             LIMIT 5", 
            ["%$global_search%", "%$global_search%"]
        );
    }
    
    // Dashboard stats
    $stats = [
        'clients' => db_fetch_one("SELECT COUNT(*) as c FROM clients")['c'] ?? 0,
        'projects' => db_fetch_one("SELECT COUNT(*) as c FROM projects WHERE status IN ('planning', 'in_progress', 'review')")['c'] ?? 0,
        'tasks_todo' => db_fetch_one("SELECT COUNT(*) as c FROM tasks WHERE status = 'todo'")['c'] ?? 0,
        'invoices_pending' => db_fetch_one("SELECT COUNT(*) as c FROM invoices WHERE status IN ('draft', 'sent', 'overdue')")['c'] ?? 0,
        'pipeline_value' => db_fetch_one("SELECT SUM(budget) as s FROM projects WHERE status IN ('planning', 'in_progress', 'review')")['s'] ?? 0,
        'pending_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status IN ('sent', 'overdue')")['s'] ?? 0,
        'total_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status = 'paid'")['s'] ?? 0,
    ];
    
    // Recent projects
    $recent_projects = db_fetch_all("
        SELECT p.*, c.company_name 
        FROM projects p 
        JOIN clients c ON p.client_id = c.id 
        ORDER BY p.updated_at DESC LIMIT 5
    ");
    
    // Recent tasks
    $recent_tasks = db_fetch_all("
        SELECT t.*, p.title as project_title 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        WHERE t.status != 'done' 
        ORDER BY t.due_date ASC, t.priority DESC LIMIT 6
    ");
}
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- 動態 Title -->
    <title><?= $page_title ?> | <?= defined('SITE_NAME') ? SITE_NAME : 'YSK Ops System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
    
    <?php if ($show_login): ?>
    <style>
        body { 
            font-family: 'Inter', 'Noto Sans TC', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            -webkit-font-smoothing: antialiased;
        }
        .login-card { background: #ffffff; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: none; width: 100%; max-width: 440px; }
        .login-input-group .input-group-text { background-color: #f8fafc; border-end-0: none; color: #94a3b8; }
        .login-form-control:focus { box-shadow: none; border-color: #6366f1; }
        .btn-indigo { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: white; border: none; padding: 12px; font-weight: 600; transition: 0.2s; }
        .btn-indigo:hover { background: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
    </style>
    <?php else: ?>
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', 'Noto Sans TC', sans-serif; -webkit-font-smoothing: antialiased; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); border-radius: 12px; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        .border-left-thick { border-left: 4px solid; }
        .service-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        .table th { background: #f8fafc; color: #475569; font-weight: 600; }
        .global-search { max-width: 600px; margin: 0 auto 2rem; }
        /* Reset any conflicting styles from flex-grow */
        .main-content { min-width: 0; width: 100%; }
    </style>
    <?php endif; ?>
</head>
<body>

<?php if ($show_login): ?>
    <!-- ==============================================
         全新重新設計：SaaS 高階登入介面
         ============================================== -->
    <div class="login-card shadow-lg">
        <div class="p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="https://ysk.hk/logo.svg" alt="YSK Logo" style="height: 48px; width: auto; margin-bottom: 16px;">
                <h4 class="fw-bold" style="color: #1e293b;">YSK 業務運作系統</h4>
                <p class="text-muted small mb-0">內部核心管理與營運分析平台</p>
            </div>
            
            <?php if ($login_error): ?>
                <div class="alert alert-danger border-0 small py-2 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="login" value="1">
                <div class="mb-3">
                    <label class="form-label text-slate-600 fw-semibold small mb-1" style="color: #475569;">使用者帳號</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control login-form-control shadow-none border-start-0 ps-0" value="" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-slate-600 fw-semibold small mb-1" style="color: #475569;">安全密碼</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control login-form-control shadow-none border-start-0 ps-0" value="" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-indigo w-100 rounded-3 shadow-sm py-2 mb-3">
                    安全登入 <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
            </form>
            
            <!-- 加入版權資訊 -->
            <div class="text-center pt-4 border-top">
                <small style="color: #64748b; font-size: 0.75rem; display: block; line-height: 1.6;">
                    &copy; <?= date('Y') ?> <strong>YSK Ops System</strong>. All rights reserved.<br>
                    Powered by <a href="https://ysk.hk/" target="_blank" class="text-decoration-none fw-bold" style="color: #4f46e5;">YSK Limited</a>
                </small>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- ==============================================
         已登入狀態：控制台大儀表板
         ============================================== -->
    <div class="d-flex align-items-stretch" style="min-height: 100vh;">
        <!-- 引入統一 Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-grow-1 main-content d-flex flex-column" style="background-color: #f8f9fa;">
            <div class="p-3 p-md-4 flex-grow-1">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div class="d-flex align-items-center">
                        <button class="mobile-nav-toggle btn d-md-none me-2 p-1" onclick="toggleSidebar()">
                            <i class="bi bi-list fs-3"></i>
                        </button>
                        <div>
                            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">歡迎回來，<?= htmlspecialchars(explode(' ', $user['full_name'] ?? 'Team')[0]) ?>!</h1>
                            <p class="text-muted mb-0 d-none d-md-block">今天是 <?= date('Y年m月d日 l') ?> • 系統一切運行正常</p>
                        </div>
                    </div>
                    <div>
                        <a href="projects.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle me-1"></i> 新增項目</a>
                    </div>
                </div>
                
                <!-- Global Search Bar -->
                <div class="global-search mb-4">
                    <form method="GET" class="d-flex shadow-sm rounded-3 overflow-hidden">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="global_search" class="form-control border-start-0 ps-0 shadow-none py-2" 
                                   value="<?= htmlspecialchars($global_search) ?>" 
                                   placeholder="全系統聯動搜尋：客戶、項目、發票、任務...">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">搜尋</button>
                        </div>
                    </form>
                </div>
                
                <?php if ($global_search): ?>
                <!-- Global Search Results UI -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-primary bg-opacity-10 border-0 pt-3 pb-2 px-4">
                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-text-paragraph me-2"></i>搜尋結果："<?= htmlspecialchars($global_search) ?>"</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <?php if (!empty($global_results['clients'])): ?>
                            <div class="col-md-4">
                                <h6 class="text-primary fw-bold mb-2"><i class="bi bi-buildings me-2"></i>關聯客戶</h6>
                                <div class="list-group shadow-none">
                                    <?php foreach ($global_results['clients'] as $c): ?>
                                    <a href="clients.php?search=<?= urlencode($c['company_name'] ?? '') ?>" class="list-group-item list-group-item-action border rounded-3 mb-1">
                                        <div class="fw-bold text-slate-800 small"><?= htmlspecialchars($c['company_name'] ?? '') ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($c['contact_person'] ?: ($c['email'] ?? '')) ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($global_results['projects'])): ?>
                            <div class="col-md-4">
                                <h6 class="text-success fw-bold mb-2"><i class="bi bi-folder2-open me-2"></i>關聯項目</h6>
                                <div class="list-group">
                                    <?php foreach ($global_results['projects'] as $p): ?>
                                    <a href="projects.php" class="list-group-item list-group-item-action border rounded-3 mb-1">
                                        <div class="fw-bold text-slate-800 small"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($p['company_name'] ?: '') ?> • <?= ucfirst(str_replace('_', ' ', $p['status'] ?? '')) ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($global_results['invoices'])): ?>
                            <div class="col-md-4">
                                <h6 class="text-info fw-bold mb-2"><i class="bi bi-receipt-cutoff me-2"></i>關聯發票</h6>
                                <div class="list-group">
                                    <?php foreach ($global_results['invoices'] as $inv): ?>
                                    <a href="invoices.php" class="list-group-item list-group-item-action border rounded-3 mb-1">
                                        <div class="fw-bold text-slate-800 small"><?= htmlspecialchars($inv['invoice_number'] ?? '') ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;">HK$ <?= number_format($inv['total_amount'] ?? 0, 0) ?> • <?= htmlspecialchars($inv['company_name'] ?? '') ?></small>
                                    </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (empty($global_results['clients']) && empty($global_results['projects']) && empty($global_results['invoices'])): ?>
                            <div class="col-12"><div class="alert alert-light border text-center py-4 mb-0 text-muted">無匹配紀錄。</div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </body>
        </html>
                <?php endif; ?>
                
                <!-- 企業級四大 KPI 卡片 -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #6366f1 !important;">
                            <div class="card-body p-4">
                                <small class="text-muted d-block fw-semibold mb-3">日常營運概況</small>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-slate-600 small"><i class="bi bi-buildings me-1"></i>活躍客戶</span>
                                    <span class="fw-bold text-slate-800"><?= $stats['clients'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-slate-600 small"><i class="bi bi-folder2-open me-1"></i>進行中項目</span>
                                    <span class="fw-bold text-slate-800"><?= $stats['projects'] ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-slate-600 small"><i class="bi bi-list-task me-1"></i>待辦任務</span>
                                    <span class="fw-bold text-slate-800"><?= $stats['tasks_todo'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #10b981 !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <small class="text-muted d-block fw-semibold mb-1">業務管線總值 (Pipeline)</small>
                                    <h3 class="fw-bold mb-0 text-success">HK$ <?= number_format($stats['pipeline_value'], 0) ?></h3>
                                </div>
                                <div class="mt-3 text-muted small">
                                    <i class="bi bi-graph-up-arrow text-success me-1"></i>來自 <?= $stats['projects'] ?> 個進行中項目
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #f59e0b !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <small class="text-muted d-block fw-semibold mb-1">待收款總額 (Pending)</small>
                                    <h3 class="fw-bold mb-0 text-warning">HK$ <?= number_format($stats['pending_revenue'], 0) ?></h3>
                                </div>
                                <div class="mt-3 text-muted small">
                                    <i class="bi bi-clock-history text-warning me-1"></i>共 <?= $stats['invoices_pending_count'] ?> 張待收發票
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #3b82f6 !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <small class="text-muted d-block fw-semibold mb-1">已收款總額 (Revenue)</small>
                                    <h3 class="fw-bold mb-0 text-info">HK$ <?= number_format($stats['total_revenue'], 0) ?></h3>
                                </div>
                                <div class="mt-3 text-muted small">
                                    <i class="bi bi-check-circle-fill text-info me-1"></i>所有已結算入帳之發票
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Recent Projects -->
                    <div class="col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0" style="color: #1e293b;"><i class="bi bi-folder me-2 text-primary"></i> 最近項目動態</h5>
                                <a href="projects.php" class="btn btn-sm btn-light border small text-muted">檢視全部</a>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="ps-4">項目名稱</th>
                                                <th>客戶</th>
                                                <th>進度</th>
                                                <th>狀態</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_projects as $p): ?>
                                            <tr onclick="window.location='projects.php?search=<?= urlencode($p['title'] ?? '') ?>'" style="cursor:pointer">
                                                <td class="ps-4"><strong><?= htmlspecialchars($p['title'] ?? '') ?></strong></td>
                                                <td class="small" style="color: #475569;"><?= htmlspecialchars($p['company_name'] ?? '') ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height: 6px; min-width: 50px;">
                                                            <div class="progress-bar bg-success" style="width: <?= $p['progress_percent'] ?>%"></div>
                                                        </div>
                                                        <small class="fw-bold" style="font-size:0.75rem; color: #475569;"><?= $p['progress_percent'] ?>%</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_badges = [
                                                        'planning' => 'secondary', 'in_progress' => 'primary', 
                                                        'review' => 'info', 'completed' => 'success', 
                                                        'on_hold' => 'warning', 'cancelled' => 'danger'
                                                    ];
                                                    $status_color = $status_badges[$p['status'] ?? 'planning'] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $p['status'] ?? '')) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Tasks -->
                    <div class="col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0" style="color: #1e293b;"><i class="bi bi-clock-history me-2 text-warning"></i> 即將到期任務</h5>
                                <a href="tasks.php" class="btn btn-sm btn-light border small text-muted">所有任務</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recent_tasks)): ?>
                                    <div class="p-5 text-center text-muted small"><i class="bi bi-check-circle me-1 text-success"></i> 目前工程團隊無任何積壓任務</div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($recent_tasks as $t): 
                                            $priority_class = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'][$t['priority'] ?? 'medium'] ?? 'secondary';
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start p-3 px-4">
                                            <div class="overflow-hidden me-2">
                                                <div class="fw-bold small text-truncate" style="color: #1e293b;"><?= htmlspecialchars($t['title'] ?? '') ?></div>
                                                <small class="text-muted text-truncate d-block" style="font-size:0.75rem;"><i class="bi bi-folder me-1"></i><?= htmlspecialchars($t['project_title'] ?? '') ?></small>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <span class="badge bg-<?= $priority_class ?> bg-opacity-10 text-<?= $priority_class ?> mb-1" style="font-size:0.65rem;"><?= strtoupper($t['priority'] ?? '') ?></span>
                                                <div style="font-size: 0.72rem; color: #64748b;"><i class="bi bi-calendar-x me-1"></i><?= $t['due_date'] ? date('m/d', strtotime($t['due_date'])) : '無期限' ?></div>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            
<?php include 'includes/footer.php'; ?>
<?php endif; ?>