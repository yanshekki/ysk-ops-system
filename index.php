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
<?php $page_title = "客戶管理"; ?>
<?php include 'includes/header.php'; ?>
<?php if ($show_login): ?>
    <!-- Login Page -->
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
    <!-- Dashboard -->
    <div class="d-flex">
        <!-- Mobile Menu Toggle -->
        <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>
        
        <!-- Unified Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-grow-1 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">歡迎回來，<?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?>!</h1>
                    <p class="text-muted mb-0 d-none d-md-block">今天是 <?= date('Y年m月d日 l') ?> • YSK Limited 內部系統</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="projects.php?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i> <span class="d-none d-md-inline">新增項目</span></a>
                </div>
            </div>
            
            <!-- Global Search Bar -->
            <div class="global-search mb-4">
                <form method="GET" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="global_search" class="form-control form-control-lg" 
                               value="<?= htmlspecialchars($global_search) ?>" 
                               placeholder="全系統搜尋：客戶、項目、發票、任務...">
                        <button type="submit" class="btn btn-primary">搜尋</button>
                    </div>
                </form>
            </div>
            
            <?php if ($global_search): ?>
            <!-- Global Search Results -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">搜尋結果："<?= htmlspecialchars($global_search) ?>"</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (!empty($global_results['clients'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-primary"><i class="bi bi-people me-2"></i>客戶 (<?= count($global_results['clients']) ?>)</h6>
                            <div class="list-group">
                                <?php foreach ($global_results['clients'] as $c): ?>
                                <a href="clients.php?id=<?= $c['id'] ?>" class="list-group-item list-group-item-action">
                                    <strong><?= htmlspecialchars($c['company_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($c['contact_person'] ?: $c['email']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($global_results['projects'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-success"><i class="bi bi-folder me-2"></i>項目 (<?= count($global_results['projects']) ?>)</h6>
                            <div class="list-group">
                                <?php foreach ($global_results['projects'] as $p): ?>
                                <a href="projects.php?id=<?= $p['id'] ?>" class="list-group-item list-group-item-action">
                                    <strong><?= htmlspecialchars($p['title']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($p['company_name'] ?: '') ?> • <?= ucfirst(str_replace('_', ' ', $p['status'])) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($global_results['invoices'])): ?>
                        <div class="col-12 col-md-4 mb-3">
                            <h6 class="text-info"><i class="bi bi-receipt me-2"></i>發票 (<?= count($global_results['invoices']) ?>)</h6>
                            <div class="list-group">
                                <?php foreach ($global_results['invoices'] as $inv): ?>
                                <a href="invoices.php" class="list-group-item list-group-item-action">
                                    <strong><?= $inv['invoice_number'] ?></strong><br>
                                    <small class="text-muted">HK$ <?= number_format($inv['total_amount'], 0) ?> • <?= htmlspecialchars($inv['company_name']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($global_results['clients']) && empty($global_results['projects']) && empty($global_results['invoices'])): ?>
                        <div class="col-12">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                未找到相關結果，請嘗試不同關鍵字。
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">活躍客戶</h6>
                                    <h2 class="fw-bold mb-0"><?= $stats['clients'] ?></h2>
                                </div>
                                <i class="bi bi-people fs-1 text-primary opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">進行中項目</h6>
                                    <h2 class="fw-bold mb-0"><?= $stats['projects'] ?></h2>
                                </div>
                                <i class="bi bi-folder-check fs-1 text-success opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">待辦任務</h6>
                                    <h2 class="fw-bold mb-0"><?= $stats['tasks_todo'] ?></h2>
                                </div>
                                <i class="bi bi-list-task fs-1 text-warning opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-muted">總收入 (已收款)</h6>
                                    <h2 class="fw-bold mb-0">HK$ <?= number_format($stats['total_revenue'], 0) ?></h2>
                                    <small class="text-muted"><?= $stats['invoices_pending'] ?> 張待收款發票</small>
                                </div>
                                <i class="bi bi-currency-dollar fs-1 text-info opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-3">
                <!-- Recent Projects -->
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-folder me-2"></i> 最近項目</h5>
                            <a href="projects.php" class="btn btn-sm btn-outline-primary">查看全部</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>項目</th>
                                            <th class="d-none d-md-table-cell">客戶</th>
                                            <th>服務</th>
                                            <th>進度</th>
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
                                        <tr onclick="window.location='projects.php?id=<?= $p['id'] ?>'" style="cursor:pointer">
                                            <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                                            <td class="d-none d-md-table-cell"><?= htmlspecialchars($p['company_name']) ?></td>
                                            <td><span class="badge bg-<?= $svc[1] ?> service-badge"><?= $svc[0] ?></span></td>
                                            <td>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?= $p['progress_percent'] ?>%"></div>
                                                </div>
                                                <small><?= $p['progress_percent'] ?>%</small>
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
                </div>
                
                <!-- Upcoming Tasks -->
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> 即將到期任務</h5>
                            <a href="tasks.php" class="btn btn-sm btn-outline-primary">全部任務</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_tasks)): ?>
                                <div class="p-4 text-center text-muted">暫無待辦任務</div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_tasks as $t): 
                                        $priority_class = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'][$t['priority']] ?? 'secondary';
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($t['project_title']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= $priority_class ?>"><?= strtoupper($t['priority']) ?></span>
                                            <div><small class="text-muted"><?= $t['due_date'] ? date('m/d', strtotime($t['due_date'])) : '無期限' ?></small></div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-4 text-center text-muted small">
                YSK Limited • 遠端開發團隊 • PHP + MySQL 運作系統 v2.0 • 
                <a href="https://ysk.hk" target="_blank" class="text-decoration-none">ysk.hk</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.mobile-nav-toggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('show')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    }
});
</script>
</body>
</html>