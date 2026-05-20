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
        $login_error = '用戶名或密碼錯誤！請重新輸入。';
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
             WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? LIMIT 5", 
            ["%$global_search%", "%$global_search%", "%$global_search%"]
        );
        $global_results['projects'] = db_fetch_all(
            "SELECT p.id, p.title, p.status, c.company_name 
             FROM projects p LEFT JOIN clients c ON p.client_id = c.id 
             WHERE p.title LIKE ? OR p.description LIKE ? OR c.company_name LIKE ? LIMIT 5", 
            ["%$global_search%", "%$global_search%", "%$global_search%"]
        );
        $global_results['invoices'] = db_fetch_all(
            "SELECT i.id, i.invoice_number, i.total_amount, i.status, c.company_name 
             FROM invoices i LEFT JOIN clients c ON i.client_id = c.id 
             WHERE i.invoice_number LIKE ? OR c.company_name LIKE ? LIMIT 5", 
            ["%$global_search%", "%$global_search%"]
        );
    }
    
    // Dashboard stats
    $stats = [
        'clients' => db_fetch_one("SELECT COUNT(*) as c FROM clients WHERE status = 'active'")['c'] ?? 0,
        'projects' => db_fetch_one("SELECT COUNT(*) as c FROM projects WHERE status IN ('planning', 'in_progress', 'review')")['c'] ?? 0,
        'tasks_todo' => db_fetch_one("SELECT COUNT(*) as c FROM tasks WHERE status != 'done'")['c'] ?? 0,
        'pipeline_value' => db_fetch_one("SELECT SUM(budget) as s FROM projects WHERE status IN ('planning', 'in_progress', 'review')")['s'] ?? 0,
        'pending_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status IN ('sent', 'overdue')")['s'] ?? 0,
        'invoices_pending_count' => db_fetch_one("SELECT COUNT(*) as c FROM invoices WHERE status IN ('draft', 'sent', 'overdue')")['c'] ?? 0,
        'total_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status = 'paid'")['s'] ?? 0,
    ];
    
    $recent_projects = db_fetch_all("
        SELECT p.*, c.company_name FROM projects p JOIN clients c ON p.client_id = c.id ORDER BY p.updated_at DESC LIMIT 5
    ");
    $recent_tasks = db_fetch_all("
        SELECT t.*, p.title as project_title FROM tasks t JOIN projects p ON t.project_id = p.id 
        WHERE t.status != 'done' ORDER BY t.due_date ASC, CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END LIMIT 6
    ");
}

// 統一引入 Header (內含 HTML 頂部結構)
include 'includes/header.php';
?>

<?php if ($show_login): ?>
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%) !important;
            min-height: 100vh; display: flex !important; align-items: center !important; justify-content: center !important; padding: 20px;
        }
        .login-card { background: #ffffff; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); border: none; width: 100%; max-width: 440px; z-index: 10; }
        .login-input-group .input-group-text { background-color: #f8fafc; color: #94a3b8; }
        .login-form-control:focus { box-shadow: none; border-color: #6366f1; }
        .btn-indigo { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: white; border: none; padding: 12px; font-weight: 600; transition: 0.2s; }
        .btn-indigo:hover { background: linear-gradient(135deg, #3730a3 0%, #4f46e5 100%); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
    </style>

    <div class="login-card shadow-lg my-auto">
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
                    <label class="form-label fw-semibold small mb-1" style="color: #475569;">使用者名稱</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control login-form-control shadow-none border-start-0 ps-0" value="admin" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small mb-1" style="color: #475569;">安全密碼</label>
                    <div class="input-group login-input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control login-form-control shadow-none border-start-0 ps-0" value="admin123" required>
                    </div>
                    <div class="form-text mt-2" style="font-size: 0.75rem; color: #94a3b8;"><i class="bi bi-shield-lock me-1"></i>測試環境：admin / admin123</div>
                </div>
                <button type="submit" class="btn btn-indigo w-100 rounded-3 shadow-sm py-2">
                    安全登入 <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <style>
        .stat-card { transition: transform 0.2s; border-radius: 12px; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05) !important; }
        .border-left-thick { border-left: 4px solid; }
        .global-search { max-width: 600px; margin: 0 auto 2rem; }
    </style>

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
                            <h1 class="h3 fw-bold mb-1" style="color: #1e293b;">歡迎回來，<?= htmlspecialchars(explode(' ', $user['full_name'] ?? 'Team')[0]) ?>!</h1>
                            <p class="text-muted mb-0 d-none d-md-block">今天是 <?= date('Y年m月d日 l') ?> • 系統一切運行正常</p>
                        </div>
                    </div>
                    <div>
                        <a href="projects.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle me-1"></i> 新增項目</a>
                    </div>
                </div>
                
                <div class="global-search mb-4">
                    <form method="GET" class="d-flex shadow-sm rounded-3 overflow-hidden">
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="global_search" class="form-control border-start-0 ps-0 shadow-none py-2" value="<?= htmlspecialchars($global_search) ?>" placeholder="全系統聯動搜尋：客戶、項目、發票...">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">搜尋</button>
                        </div>
                    </form>
                </div>
                
                <?php if ($global_search): ?>
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
                <?php endif; ?>
                
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #6366f1 !important;">
                            <div class="card-body p-4">
                                <small class="text-muted d-block fw-semibold mb-3">日常營運概況</small>
                                <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-slate-600 small">活躍客戶</span><span class="fw-bold text-slate-800"><?= $stats['clients'] ?></span></div>
                                <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-slate-600 small">進行中項目</span><span class="fw-bold text-slate-800"><?= $stats['projects'] ?></span></div>
                                <div class="d-flex justify-content-between align-items-center"><span class="text-slate-600 small">待辦任務</span><span class="fw-bold text-slate-800"><?= $stats['tasks_todo'] ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #10b981 !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div><small class="text-muted d-block fw-semibold mb-1">業務管線總值</small><h3 class="fw-bold mb-0 text-success">HK$ <?= number_format($stats['pipeline_value'], 0) ?></h3></div>
                                <div class="mt-3 text-muted small"><i class="bi bi-graph-up-arrow text-success me-1"></i>來自進行中項目</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #f59e0b !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div><small class="text-muted d-block fw-semibold mb-1">待收款總額</small><h3 class="fw-bold mb-0 text-warning">HK$ <?= number_format($stats['pending_revenue'], 0) ?></h3></div>
                                <div class="mt-3 text-muted small"><i class="bi bi-clock-history text-warning me-1"></i>共 <?= $stats['invoices_pending_count'] ?> 張發票</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm h-100 border-0 border-left-thick" style="border-left-color: #3b82f6 !important;">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div><small class="text-muted d-block fw-semibold mb-1">已收款總額</small><h3 class="fw-bold mb-0 text-info">HK$ <?= number_format($stats['total_revenue'], 0) ?></h3></div>
                                <div class="mt-3 text-muted small"><i class="bi bi-check-circle-fill text-info me-1"></i>所有已結算發票</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0" style="color: #1e293b;"><i class="bi bi-folder me-2 text-primary"></i> 最近項目動態</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead><tr><th class="ps-4">項目名稱</th><th>客戶</th><th>進度</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($recent_projects as $p): ?>
                                            <tr>
                                                <td class="ps-4"><strong><?= htmlspecialchars($p['title'] ?? '') ?></strong></td>
                                                <td class="small"><?= htmlspecialchars($p['company_name'] ?? '') ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="progress flex-grow-1" style="height: 6px;"><div class="progress-bar bg-success" style="width: <?= $p['progress_percent'] ?>%"></div></div>
                                                        <small><?= $p['progress_percent'] ?>%</small>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-5">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0" style="color: #1e293b;"><i class="bi bi-clock-history me-2 text-warning"></i> 即將到期任務</h5>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_tasks as $t): 
                                        $priority_class = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'][$t['priority'] ?? 'medium'] ?? 'secondary';
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start p-3 px-4">
                                        <div>
                                            <div class="fw-bold small"><?= htmlspecialchars($t['title'] ?? '') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($t['project_title'] ?? '') ?></small>
                                        </div>
                                        <span class="badge bg-<?= $priority_class ?> bg-opacity-10 text-<?= $priority_class ?>"><?= strtoupper($t['priority'] ?? '') ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

<?php include 'includes/footer.php'; ?>