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

// Handle POST (新增、編輯、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_project']) || isset($_POST['update_project'])) {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'service_type' => $_POST['service_type'],
            'status' => $_POST['status'],
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
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
            $success = '項目資料已更新！';
        }
    }
    
    if (isset($_POST['delete_project'])) {
        db_delete('projects', 'id = ?', [$_POST['project_id']]);
        $success = '項目已徹底刪除！';
    }
}

// 建立分頁與搜尋的 SQL 查詢
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

$total = db_fetch_one($count_sql, $count_params)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// 獲取項目資料
$sql = "SELECT p.*, c.company_name, u.full_name as pm_name 
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id 
        LEFT JOIN users u ON p.assigned_pm_id = u.id
        WHERE 1=1";
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

// 獲取關聯資料供表單使用
$clients = db_fetch_all("SELECT id, company_name FROM clients ORDER BY company_name");
$users = db_fetch_all("SELECT id, full_name FROM users WHERE role IN ('admin','pm') ORDER BY full_name");

// 服務類型與狀態的視覺設定
$service_options = [
    'ai_automation' => ['label' => 'AI 自動化', 'color' => 'primary', 'icon' => 'bi-robot'],
    'app_development' => ['label' => 'App 開發', 'color' => 'success', 'icon' => 'bi-phone'],
    'cloud_security' => ['label' => '雲端安全', 'color' => 'info', 'icon' => 'bi-cloud-shield'],
    'web3_blockchain' => ['label' => 'Web3 區塊鏈', 'color' => 'warning', 'icon' => 'bi-link-45deg'],
    'other' => ['label' => '其他服務', 'color' => 'secondary', 'icon' => 'bi-box']
];

$status_options = [
    'planning' => ['label' => '規劃中', 'color' => 'secondary'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary'],
    'review' => ['label' => '審核中', 'color' => 'info'],
    'completed' => ['label' => '已完成', 'color' => 'success'],
    'on_hold' => ['label' => '暫停', 'color' => 'warning'],
    'cancelled' => ['label' => '已取消', 'color' => 'danger']
];
?>
<?php $page_title = "項目管理"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-folder2-open me-2 text-primary"></i> 項目管理</h2>
                <p class="text-muted mb-0 d-none d-md-block">追蹤各項目的進度、預算及負責人</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <i class="bi bi-plus-circle me-1"></i> 新增項目
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="項目名稱 / 客戶 / 描述">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">服務類型</label>
                        <select name="service" class="form-select shadow-none">
                            <option value="">全部類型</option>
                            <?php foreach ($service_options as $val => $s): ?>
                                <option value="<?= $val ?>" <?= $service_filter == $val ? 'selected' : '' ?>><?= $s['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">項目狀態</label>
                        <select name="status" class="form-select shadow-none">
                            <option value="">全部狀態</option>
                            <?php foreach ($status_options as $val => $s): ?>
                                <option value="<?= $val ?>" <?= $status_filter == $val ? 'selected' : '' ?>><?= $s['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-outline-primary w-100">篩選</button>
                    </div>
                    <div class="col-md-1 text-end">
                        <a href="projects.php" class="btn btn-light w-100 border">清除</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th width="60" class="text-center">#</th>
                                <th width="25%">項目名稱</th>
                                <th>客戶</th>
                                <th>服務類型</th>
                                <th>狀態</th>
                                <th width="15%">專案進度</th>
                                <th class="text-end">預算 (HK$)</th>
                                <th>負責 PM</th>
                                <th width="120" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $p): 
                                $svc = $service_options[$p['service_type']] ?? $service_options['other'];
                                $stat = $status_options[$p['status']] ?? $status_options['planning'];
                                $avatar_char = mb_substr($p['title'] ?? 'P', 0, 1, 'UTF-8');
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?= $p['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-<?= $svc['color'] ?> bg-opacity-10 text-<?= $svc['color'] ?> rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold fs-5"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-800 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                            <div class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($p['description'] ?? '無描述') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-slate-700 fw-medium"><i class="bi bi-building text-muted me-1"></i><?= htmlspecialchars($p['company_name'] ?? '已刪除客戶') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $svc['color'] ?> bg-opacity-10 text-<?= $svc['color'] ?> px-2 py-1">
                                        <i class="bi <?= $svc['icon'] ?> me-1"></i><?= $svc['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?> px-2 py-1">
                                        <?= $stat['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 6px; border-radius: 4px;">
                                            <div class="progress-bar bg-<?= $p['progress_percent'] == 100 ? 'success' : 'primary' ?>" style="width: <?= $p['progress_percent'] ?>%"></div>
                                        </div>
                                        <small class="fw-bold text-slate-700" style="min-width: 35px;"><?= $p['progress_percent'] ?>%</small>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold text-slate-800">
                                    <?= number_format($p['budget'] ?? 0, 0) ?>
                                </td>
                                <td>
                                    <?php if($p['pm_name']): ?>
                                        <div class="small fw-medium"><i class="bi bi-person-badge text-muted me-1"></i><?= htmlspecialchars($p['pm_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">未分配</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editProjectModal<?= $p['id'] ?>" title="編輯"><i class="bi bi-pencil-square"></i></button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('⚠️ 警告！\n\n刪除項目將永久刪除其關聯的：\n- 所有任務 (Tasks)\n- 所有工時記錄 (Timesheets)\n\n建議將狀態改為「已取消」或「暫停」。\n\n確定強制刪除此項目嗎？')">
                                        <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="delete_project" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editProjectModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    編輯項目資料
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="update_project" value="1">
                                                <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">所屬客戶 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                            <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($clients as $cl): ?>
                                                                    <option value="<?= $cl['id'] ?>" <?= $p['client_id'] == $cl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cl['company_name'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">服務類型 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-layers"></i></span>
                                                            <select name="service_type" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($service_options as $val => $s): ?>
                                                                    <option value="<?= $val ?>" <?= $p['service_type'] == $val ? 'selected' : '' ?>><?= $s['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">項目名稱 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                                            <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($p['title'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">項目描述及目標</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                                            <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="3"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    
                                                    <hr class="text-muted my-4 opacity-25">
                                                    
                                                    <div class="col-md-3">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">開始日期</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                                            <input type="date" name="start_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $p['start_date'] ?? '' ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">預計結束日期</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-check"></i></span>
                                                            <input type="date" name="end_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $p['end_date'] ?? '' ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">總預算 (HK$)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                                            <input type="number" step="0.01" name="budget" class="form-control border-start-0 ps-0 shadow-none" value="<?= $p['budget'] ?? 0 ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">完成進度 (%)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-percent"></i></span>
                                                            <input type="number" name="progress_percent" class="form-control border-start-0 ps-0 shadow-none" min="0" max="100" value="<?= $p['progress_percent'] ?? 0 ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">負責項目經理 (PM)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person-badge"></i></span>
                                                            <select name="assigned_pm_id" class="form-select border-start-0 ps-0 shadow-none">
                                                                <option value="">未分配</option>
                                                                <?php foreach ($users as $u): ?>
                                                                    <option value="<?= $u['id'] ?>" <?= $p['assigned_pm_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">項目狀態</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                                            <select name="status" class="form-select border-start-0 ps-0 shadow-none">
                                                                <?php foreach ($status_options as $val => $s): ?>
                                                                    <option value="<?= $val ?>" <?= $p['status'] == $val ? 'selected' : '' ?>><?= $s['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 pt-0 pb-4 px-4">
                                                <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                                                <button type="submit" class="btn btn-primary px-4 shadow-sm">儲存變更</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-folder-x fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的項目
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&service=<?= $service_filter ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<div class="modal fade" id="addProjectModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-folder-plus fs-5"></i>
                        </div>
                        建立新項目
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_project" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬客戶 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇客戶...</option>
                                    <?php foreach ($clients as $cl): ?>
                                        <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['company_name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">服務類型 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-layers"></i></span>
                                <select name="service_type" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇類型...</option>
                                    <?php foreach ($service_options as $val => $s): ?>
                                        <option value="<?= $val ?>"><?= $s['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目名稱 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" required placeholder="例如：物流公司 ERP 系統開發">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目描述及目標</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="3" placeholder="詳細說明項目範圍、預期成果..."></textarea>
                            </div>
                        </div>
                        
                        <hr class="text-muted my-4 opacity-25">
                        
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">開始日期</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                <input type="date" name="start_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">預計結束日期</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-check"></i></span>
                                <input type="date" name="end_date" class="form-control border-start-0 ps-0 shadow-none">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">總預算 (HK$)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" step="0.01" name="budget" class="form-control border-start-0 ps-0 shadow-none" value="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">初始進度 (%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-percent"></i></span>
                                <input type="number" name="progress_percent" class="form-control border-start-0 ps-0 shadow-none" min="0" max="100" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">負責項目經理 (PM)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person-badge"></i></span>
                                <select name="assigned_pm_id" class="form-select border-start-0 ps-0 shadow-none">
                                    <option value="">未分配</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目狀態</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                <select name="status" class="form-select border-start-0 ps-0 shadow-none">
                                    <?php foreach ($status_options as $val => $s): ?>
                                        <option value="<?= $val ?>" <?= $val == 'in_progress' ? 'selected' : '' ?>><?= $s['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 建立項目</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>