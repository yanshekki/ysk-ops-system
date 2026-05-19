<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$search = $_GET['search'] ?? '';
$service_filter = $_GET['service'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_project']) || isset($_POST['update_project'])) {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'service_type' => $_POST['service_type'],
            'status' => $_POST['status'],
            'start_date' => $_POST['start_date'] ?: null,
            'end_date' => $_POST['end_date'] ?: null,
            'budget' => (float)($_POST['budget'] ?? 0),
            'progress_percent' => (int)($_POST['progress_percent'] ?? 0),
            'assigned_pm_id' => !empty($_POST['assigned_pm_id']) ? (int)$_POST['assigned_pm_id'] : null,
            'created_by' => $_SESSION['user_id']
        ];
        
        if (isset($_POST['add_project'])) {
            db_insert('projects', $data);
            $success = '項目新增成功！';
        } else {
            $id = $_POST['project_id'];
            db_update('projects', $data, 'id = ?', [$id]);
            $success = '項目已更新！';
        }
    }
    
    if (isset($_POST['delete_project'])) {
        db_delete('projects', 'id = ?', [$_POST['project_id']]);
        $success = '項目已刪除！';
    }
}

// Build query for count
$count_sql = "SELECT COUNT(*) as total FROM projects WHERE 1=1";
$count_params = [];

if ($search) {
    $count_sql .= " AND (title LIKE ? OR description LIKE ? OR (SELECT company_name FROM clients WHERE id = projects.client_id) LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

if ($service_filter) {
    $count_sql .= " AND service_type = ?";
    $count_params[] = $service_filter;
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
}

total = db_fetch_one($count_sql, $count_params)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// Fetch projects with pagination
$sql = "SELECT p.*, c.company_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($service_filter) {
    $sql .= " AND p.service_type = ?";
    $params[] = $service_filter;
}

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.updated_at DESC LIMIT $per_page OFFSET $offset";
$projects = db_fetch_all($sql, $params);

$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$users = db_fetch_all("SELECT id, full_name FROM users WHERE role IN ('admin','pm') ORDER BY full_name");

$service_options = [
    'ai_automation' => 'AI 自動化 (私有 LLM)',
    'app_development' => 'App 及系統開發',
    'cloud_security' => '雲端安全與架構',
    'web3_blockchain' => 'Web3 區塊鏈',
    'other' => '其他服務'
];
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>項目管理 | <?= SITE_NAME ?></title>
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
            <a href="users.php" class="nav-link mb-1"><i class="bi bi-people-fill me-2"></i> 用戶管理</a>
            <a href="clients.php" class="nav-link mb-1"><i class="bi bi-people me-2"></i> 客戶管理</a>
            <a href="projects.php" class="nav-link active mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <a href="tasks.php" class="nav-link mb-1"><i class="bi bi-list-task me-2"></i> 任務追蹤</a>
            <a href="invoices.php" class="nav-link mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-folder me-2"></i> 項目管理</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <i class="bi bi-plus-circle me-1"></i> 新增項目
            </button>
        </div>
        
        <!-- Search and Filter -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">搜尋</label>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="項目名稱 / 客戶">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">服務類型</label>
                        <select name="service" class="form-select">
                            <option value="">全部</option>
                            <?php foreach ($service_options as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $service_filter==$val?'selected':'' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">狀態</label>
                        <select name="status" class="form-select">
                            <option value="">全部</option>
                            <option value="planning" <?= $status_filter=='planning'?'selected':'' ?>>規劃中</option>
                            <option value="in_progress" <?= $status_filter=='in_progress'?'selected':'' ?>>進行中</option>
                            <option value="review" <?= $status_filter=='review'?'selected':'' ?>>審核中</option>
                            <option value="completed" <?= $status_filter=='completed'?'selected':'' ?>>已完成</option>
                            <option value="on_hold" <?= $status_filter=='on_hold'?'selected':'' ?>>暫停</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100 mt-4">搜尋</button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="projects.php" class="btn btn-outline-secondary mt-4">清除</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>項目名稱</th>
                            <th>客戶</th>
                            <th>服務類型</th>
                            <th>狀態</th>
                            <th>進度</th>
                            <th>預算 (HK$)</th>
                            <th>負責PM</th>
                            <th width="140">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): 
                            $svc_label = $service_options[$p['service_type']] ?? '其他';
                            $status_class = ['planning'=>'secondary','in_progress'=>'primary','review'=>'info','completed'=>'success','on_hold'=>'warning','cancelled'=>'danger'][$p['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['title']) ?></strong><br><small class="text-muted"><?= htmlspecialchars(substr($p['description'],0,50)) ?>...</small></td>
                            <td><?= htmlspecialchars($p['company_name']) ?></td>
                            <td><span class="badge bg-primary"><?= $svc_label ?></span></td>
                            <td><span class="badge bg-<?= $status_class ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
                            <td>
                                <div class="progress" style="height:8px;"><div class="progress-bar" style="width:<?= $p['progress_percent'] ?>%"></div></div>
                                <small><?= $p['progress_percent'] ?>%</small>
                            </td>
                            <td class="text-end"><?= number_format($p['budget'], 0) ?></td>
                            <td><?= htmlspecialchars($p['pm_name'] ?: '未分配') ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProjectModal<?= $p['id'] ?>">編輯</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此項目？')">
                                    <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_project" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editProjectModal<?= $p['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-xl">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">編輯項目</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="update_project" value="1">
                                            <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">客戶 *</label>
                                                    <select name="client_id" class="form-select" required>
                                                        <?php foreach ($clients as $cl): ?>
                                                        <option value="<?= $cl['id'] ?>" <?= $p['client_id']==$cl['id']?'selected':'' ?>><?= htmlspecialchars($cl['company_name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">服務類型 *</label>
                                                    <select name="service_type" class="form-select" required>
                                                        <?php foreach ($service_options as $val => $label): ?>
                                                        <option value="<?= $val ?>" <?= $p['service_type']==$val?'selected':'' ?>><?= $label ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">項目名稱 *</label>
                                                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($p['title']) ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">項目描述</label>
                                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($p['description']) ?></textarea>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">開始日期</label>
                                                    <input type="date" name="start_date" class="form-control" value="<?= $p['start_date'] ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">結束日期</label>
                                                    <input type="date" name="end_date" class="form-control" value="<?= $p['end_date'] ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">預算 (HK$)</label>
                                                    <input type="number" step="0.01" name="budget" class="form-control" value="<?= $p['budget'] ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">進度 %</label>
                                                    <input type="number" name="progress_percent" class="form-control" min="0" max="100" value="<?= $p['progress_percent'] ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">負責 PM</label>
                                                    <select name="assigned_pm_id" class="form-select">
                                                        <option value="">未分配</option>
                                                        <?php foreach ($users as $u): ?>
                                                        <option value="<?= $u['id'] ?>" <?= $p['assigned_pm_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">狀態</label>
                                                    <select name="status" class="form-select">
                                                        <option value="planning" <?= $p['status']=='planning'?'selected':'' ?>>規劃中</option>
                                                        <option value="in_progress" <?= $p['status']=='in_progress'?'selected':'' ?>>進行中</option>
                                                        <option value="review" <?= $p['status']=='review'?'selected':'' ?>>審核中</option>
                                                        <option value="completed" <?= $p['status']=='completed'?'selected':'' ?>>已完成</option>
                                                        <option value="on_hold" <?= $p['status']=='on_hold'?'selected':'' ?>>暫停</option>
                                                        <option value="cancelled" <?= $p['status']=='cancelled'?'selected':'' ?>>已取消</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                            <button type="submit" class="btn btn-primary">儲存變更</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">新增項目</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_project" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">客戶 *</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">請選擇客戶...</option>
                                <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">服務類型 *</label>
                            <select name="service_type" class="form-select" required>
                                <?php foreach ($service_options as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">項目名稱 *</label>
                            <input type="text" name="title" class="form-control" required placeholder="例如：物流公司 ERP 系統開發">
                        </div>
                        <div class="col-12">
                            <label class="form-label">項目描述</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="詳細說明項目範圍、目標..."></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">開始日期</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">結束日期</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">預算 (HK$)</label>
                            <input type="number" step="0.01" name="budget" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">初始進度 %</label>
                            <input type="number" name="progress_percent" class="form-control" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">負責 PM</label>
                            <select name="assigned_pm_id" class="form-select">
                                <option value="">未分配</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">狀態</label>
                            <select name="status" class="form-select">
                                <option value="planning">規劃中</option>
                                <option value="in_progress" selected>進行中</option>
                                <option value="review">審核中</option>
                                <option value="completed">已完成</option>
                                <option value="on_hold">暫停</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">建立項目</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>