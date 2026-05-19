<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

$login_error = '';
$show_login = true;

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
        'total_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status = 'paid'")['s'] ?? 0,
        // 新增：業務管線總值 (未結案項目的預算總計)
        'pipeline_value' => db_fetch_one("SELECT SUM(budget) as s FROM projects WHERE status IN ('planning', 'in_progress', 'review')")['s'] ?? 0,
        // 新增：待收款發票總額
        'outstanding_revenue' => db_fetch_one("SELECT SUM(total_amount) as s FROM invoices WHERE status IN ('draft', 'sent', 'overdue')")['s'] ?? 0,
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
        ORDER BY t.due_date ASC, t.priority DESC LIMIT 5
    ");

    // 新增：團隊即時動態 (撈取最新5筆工時日誌)
    $recent_activities = db_fetch_all("
        SELECT t.*, u.full_name, p.title as project_title 
        FROM timesheets t
        JOIN users u ON t.user_id = u.id
        JOIN projects p ON t.project_id = p.id
        ORDER BY t.created_at DESC LIMIT 5
    ");
}
?>
<?php $page_title = "儀表板"; ?>
<?php include 'includes/header.php'; ?>

<?php if ($show_login): ?>
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow-lg" style="max-width: 420px; width: 100%;">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-primary">YSK 業務運作系統</h2>
                    <p class="text-muted">YSK Limited 內部管理平台</p>
                    <div class="badge bg-success mb-3">PHP + MySQL</div>
                </div>
                
                <?php if ($login_error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">用戶名</label>
                        <input type="text" name="username" class="form-control form-control-lg" value="admin" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">密碼</label>
                        <input type="password" name="password" class="form-control form-control-lg" value="admin123" required>
                        <div class="form-text">測試帳號：admin / admin123 （請即更改）</div>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary btn-lg w-100">登入系統</button>
                </form>
                
                <div class="text-center mt-4">
                    <small class="text-muted">© 2026 YSK Limited • 香港</small>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex">
        <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>
        
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="flex-grow-1 p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-1 text-slate-800">歡迎回來，<?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</h1>
                    <p class="text-muted mb-0 d-none d-md-block">今天是 <?= date('Y年m月d日 l') ?> • 掌握 YSK 全局核心數據</p>
                </div>
                <div class="text-end d-none d-sm-block">
                    <span class="badge bg-primary text-white">運作系統 v2.5</span>
                </div>
            </div>
            
            <div class="global-search mb-4">
                <form method="GET" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="global_search" class="form-control border-start-0 shadow-none ps-0" 
                               value="<?= htmlspecialchars($global_search) ?>" 
                               placeholder="全系統搜尋：客戶、項目、發票、任務...">
                        <button type="submit" class="btn btn-primary">搜尋</button>
                    </div>
                </form>
            </div>
            
            <?php if ($global_search): ?>
            <div class="card mb-4">
                <div class="card-header bg-transparent border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-slate-800">搜尋結果："<?= htmlspecialchars($global_search) ?>"</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (!empty($global_results['clients'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-primary fw-bold mb-3"><i class="bi bi-people me-2"></i>客戶 (<?= count($global_results['clients']) ?>)</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($global_results['clients'] as $c): ?>
                                <a href="clients.php?search=<?= urlencode($c['company_name']) ?>" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <div class="fw-semibold"><?= htmlspecialchars($c['company_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($c['contact_person'] ?: $c['email']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($global_results['projects'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-success fw-bold mb-3"><i class="bi bi-folder me-2"></i>項目 (<?= count($global_results['projects']) ?>)</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($global_results['projects'] as $p): ?>
                                <a href="projects.php?search=<?= urlencode($p['title']) ?>" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <div class="fw-semibold"><?= htmlspecialchars($p['title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($p['company_name'] ?: '') ?> • <?= ucfirst(str_replace('_', ' ', $p['status'])) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($global_results['invoices'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-info fw-bold mb-3"><i class="bi bi-receipt me-2"></i>發票 (<?= count($global_results['invoices']) ?>)</h6>
                            <div class="list-group list-group-flush">
                                <?php foreach ($global_results['invoices'] as $inv): ?>
                                <a href="invoices.php?search=<?= urlencode($inv['invoice_number']) ?>" class="list-group-item list-group-item-action px-0 border-0 py-2">
                                    <div class="fw-semibold text-indigo"><?= $inv['invoice_number'] ?></div>
                                    <small class="text-muted">HK$ <?= number_format($inv['total_amount'], 2) ?> • <?= htmlspecialchars($inv['company_name']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($global_results['clients']) && empty($global_results['projects']) && empty($global_results['invoices'])): ?>
                        <div class="col-12 py-3 text-center">
                            <div class="text-muted mb-2"><i class="bi bi-exclamation-circle fs-3"></i></div>
                            <span class="text-muted">找不到與關鍵字相符的資料，請嘗試其他字詞。</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">已收總收入</h6>
                                <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-currency-dollar fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-slate-800 mb-2">HK$ <?= number_format($stats['total_revenue'], 0) ?></h3>
                            <div class="d-flex align-items-center mt-auto">
                                <span class="badge bg-success bg-opacity-10 text-success me-2 px-2 py-1"><i class="bi bi-shield-check me-1"></i>已核賬</span>
                                <small class="text-slate-400" style="font-size: 0.75rem;">安全資產</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">項目管線總值</h6>
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-graph-up-arrow fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-slate-800 mb-2">HK$ <?= number_format($stats['pipeline_value'], 0) ?></h3>
                            <div class="d-flex align-items-center mt-auto">
                                <span class="badge bg-primary bg-opacity-10 text-primary me-2 px-2 py-1"><i class="bi bi-folder2-open me-1"></i><?= $stats['projects'] ?> 個項目</span>
                                <small class="text-slate-400" style="font-size: 0.75rem;">進行中估值</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">應收未收賬款</h6>
                                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-receipt fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-slate-800 mb-2">HK$ <?= number_format($stats['outstanding_revenue'], 0) ?></h3>
                            <div class="d-flex align-items-center mt-auto">
                                <span class="badge bg-danger bg-opacity-10 text-danger me-2 px-2 py-1"><i class="bi bi-exclamation-circle me-1"></i><?= $stats['invoices_pending'] ?> 張單</span>
                                <small class="text-slate-400" style="font-size: 0.75rem;">待付清發票</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">待處理任務</h6>
                                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-list-task fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-slate-800 mb-2"><?= $stats['tasks_todo'] ?> <span class="fs-6 text-slate-400 fw-normal">個任務</span></h3>
                            <div class="d-flex align-items-center mt-auto">
                                <span class="badge bg-warning bg-opacity-10 text-warning me-2 px-2 py-1"><i class="bi bi-clock-history me-1"></i>跟進中</span>
                                <small class="text-slate-400" style="font-size: 0.75rem;">各開發階段</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-bottom py-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-folder2-open text-primary me-2"></i> 核心項目監控</h5>
                            <a href="projects.php" class="btn btn-sm btn-light border">所有項目</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>項目名稱</th>
                                            <th class="d-none d-md-table-cell">所屬客戶</th>
                                            <th>服務類型</th>
                                            <th>當前進度</th>
                                            <th>狀態</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_projects as $p): 
                                            $service_labels = [
                                                'ai_automation' => ['AI 自動化', 'primary'],
                                                'app_development' => ['App 開發', 'success'],
                                                'cloud_security' => ['雲端安全', 'info'],
                                                'web3_blockchain' => ['Web3 區塊鏈', 'warning'],
                                                'other' => ['其他', 'secondary']
                                            ];
                                            $svc = $service_labels[$p['service_type']] ?? ['其他', 'secondary'];
                                        ?>
                                        <tr onclick="window.location='projects.php'" style="cursor:pointer">
                                            <td><span class="fw-semibold text-slate-700"><?= htmlspecialchars($p['title']) ?></span></td>
                                            <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($p['company_name']) ?></td>
                                            <td><span class="badge bg-<?= $svc[1] ?>"><?= $svc[0] ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2" style="max-width: 140px;">
                                                    <div class="progress flex-grow-1" style="height: 6px; border-radius: 4px;">
                                                        <div class="progress-bar bg-success" style="width: <?= $p['progress_percent'] ?>%"></div>
                                                    </div>
                                                    <small class="fw-bold"><?= $p['progress_percent'] ?>%</small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'planning' => 'secondary', 'in_progress' => 'primary', 
                                                    'review' => 'info', 'completed' => 'success', 
                                                    'on_hold' => 'warning', 'cancelled' => 'danger'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_badges[$p['status']] ?? 'secondary' ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-transparent py-3 border-bottom">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-activity text-danger me-2"></i> 團隊即時營運日誌</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_activities)): ?>
                                <div class="py-4 text-center text-muted">暫無團隊工時日誌記錄</div>
                            <?php else: ?>
                                <div class="position-relative timeline-container">
                                    <?php foreach ($recent_activities as $act): ?>
                                        <div class="d-flex mb-3 align-items-start pb-3 border-bottom border-light">
                                            <div class="bg-light rounded-circle p-2 text-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-clock-history text-primary"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold text-slate-800"><?= htmlspecialchars($act['full_name']) ?></span>
                                                    <small class="text-muted"><?= date('m-d H:i', strtotime($act['created_at'])) ?></small>
                                                </div>
                                                <div class="small text-muted mt-1">
                                                    在 <span class="text-primary fw-semibold">「<?= htmlspecialchars($act['project_title']) ?>」</span> 
                                                    申報記錄了 <strong><?= $act['hours'] ?></strong> 小時：
                                                    <span class="fst-italic text-dark">"<?= htmlspecialchars($act['description'] ?: '無說明內容') ?>"</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-transparent py-3 border-bottom">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> 捷徑中心</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <a href="clients.php" class="btn btn-outline-primary w-100 d-flex flex-column align-items-center justify-content-center py-3 border-dashed shadow-none">
                                        <i class="bi bi-person-plus fs-4 mb-1"></i>
                                        <span class="small fw-semibold">新增客戶</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="projects.php" class="btn btn-outline-success w-100 d-flex flex-column align-items-center justify-content-center py-3 border-dashed shadow-none">
                                        <i class="bi bi-folder-plus fs-4 mb-1"></i>
                                        <span class="small fw-semibold">開立新項目</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="timesheets.php" class="btn btn-outline-info w-100 d-flex flex-column align-items-center justify-content-center py-3 border-dashed shadow-none">
                                        <i class="bi bi-stopwatch fs-4 mb-1"></i>
                                        <span class="small fw-semibold">登錄工時</span>
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="invoices.php" class="btn btn-outline-dark w-100 d-flex flex-column align-items-center justify-content-center py-3 border-dashed shadow-none">
                                        <i class="bi bi-file-earmark-plus fs-4 mb-1"></i>
                                        <span class="small fw-semibold">發出發票</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center border-bottom py-3">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-check text-warning me-2"></i> 高危到期任務</h5>
                            <a href="tasks.php" class="btn btn-sm btn-light border">全部</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_tasks)): ?>
                                <div class="p-4 text-center text-muted">目前暫無待辦任務</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_tasks as $t): 
                                        $priority_class = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'][$t['priority']] ?? 'secondary';
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start px-3 py-3">
                                        <div style="max-width: 70%;">
                                            <div class="fw-bold text-slate-800 small text-truncate"><?= htmlspecialchars($t['title']) ?></div>
                                            <small class="text-muted text-truncate d-block" style="font-size: 0.75rem;"><?= htmlspecialchars($t['project_title']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= $priority_class ?> mb-1" style="font-size: 0.65rem;"><?= strtoupper($t['priority']) ?></span>
                                            <div style="font-size: 0.75rem;"><small class="text-muted fw-semibold"><?= $t['due_date'] ? date('m/d', strtotime($t['due_date'])) : '無期限' ?></small></div>
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
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>