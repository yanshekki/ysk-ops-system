<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$project_filter = (int)($_GET['project_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 處理 POST 請求 (新增、編輯、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 處理新增任務
    if (isset($_POST['add_task'])) {
        $data = [
            'project_id' => (int)$_POST['project_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'assigned_to_id' => !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null,
            'status' => $_POST['status'] ?? 'todo',
            'priority' => $_POST['priority'] ?? 'medium',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0)
        ];
        db_insert('tasks', $data);
        $success = '任務新增成功！';
    }
    
    // 2. 處理編輯任務 (補齊功能)
    elseif (isset($_POST['edit_task'])) {
        $task_id = (int)$_POST['task_id'];
        $data = [
            'project_id' => (int)$_POST['project_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'assigned_to_id' => !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null,
            'status' => $_POST['status'],
            'priority' => $_POST['priority'],
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0)
        ];
        db_update('tasks', $data, 'id = ?', [$task_id]);
        $success = '任務狀態已成功更新！';
    }
    
    // 3. 處理刪除任務 (補齊功能)
    elseif (isset($_POST['delete_task'])) {
        $delete_id = (int)$_POST['delete_task_id'];
        db_delete('tasks', 'id = ?', [$delete_id]);
        $success = '任務已成功移除！';
    }
}

// 建立分頁與搜尋的 SQL 篩選條件
$where_clauses = ["1=1"];
$params = [];

if ($project_filter) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $project_filter;
}

if ($search) {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_tasks = db_fetch_one("SELECT COUNT(*) as total FROM tasks t WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_tasks / $per_page);

// 獲取任務列表
$sql = "SELECT t.*, p.title as project_title, u.full_name as assignee_name 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users u ON t.assigned_to_id = u.id
        WHERE $where_sql
        ORDER BY CASE t.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 END, t.due_date ASC 
        LIMIT $per_page OFFSET $offset";
$tasks = db_fetch_all($sql, $params);

// 獲取關聯的下拉選單資料
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
$users = db_fetch_all("SELECT id, full_name FROM users ORDER BY full_name");

// 優先級與狀態標籤之色彩設定
$priority_options = [
    'urgent' => ['label' => '危急', 'color' => 'danger', 'icon' => 'bi-exclamation-triangle'],
    'high' => ['label' => '高', 'color' => 'warning', 'icon' => 'bi-arrow-up-circle'],
    'medium' => ['label' => '中', 'color' => 'info', 'icon' => 'bi-dash-circle'],
    'low' => ['label' => '低', 'color' => 'secondary', 'icon' => 'bi-arrow-down-circle']
];

$status_options = [
    'todo' => ['label' => '待辦', 'color' => 'secondary', 'icon' => 'bi-circle'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary', 'icon' => 'bi-play-circle'],
    'review' => ['label' => '審核中', 'color' => 'info', 'icon' => 'bi-eye'],
    'done' => ['label' => '已完成', 'color' => 'success', 'icon' => 'bi-check-circle-fill']
];
?>
<?php $page_title = "任務追蹤"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-list-task me-2 text-primary"></i> 任務追蹤</h2>
                <p class="text-muted mb-0 d-none d-md-block">指派、管理與跟進專案的子任務生命週期</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="bi bi-plus-circle me-1"></i> 新增任務
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="任務名稱 / 描述內容">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">所屬專案篩選</label>
                        <select name="project_id" class="form-select shadow-none" onchange="this.form.submit()">
                            <option value="0">全部專案項目</option>
                            <?php foreach ($projects as $pr): ?>
                                <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-outline-primary w-100">篩選</button>
                    </div>
                    <div class="col-md-1 text-end">
                        <a href="tasks.php" class="btn btn-light w-100 border">清除</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>代辦任務名稱</th>
                                <th>所屬專案</th>
                                <th>負責人</th>
                                <th>優先級</th>
                                <th>狀態</th>
                                <th>到期日</th>
                                <th>預估時數</th>
                                <th width="120" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): 
                                $p_info = $priority_options[$t['priority']] ?? $priority_options['medium'];
                                $s_info = $status_options[$t['status']] ?? $status_options['todo'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-slate-800"><?= htmlspecialchars($t['title'] ?? '') ?></div>
                                    <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?= htmlspecialchars($t['description'] ?? '無詳細描述') ?></small>
                                </td>
                                <td>
                                    <span class="text-slate-600 small fw-medium"><i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($t['project_title'] ?? '') ?></span>
                                </td>
                                <td>
                                    <?php if($t['assignee_name']): ?>
                                        <div class="small fw-medium text-slate-700"><i class="bi bi-person me-1"></i><?= htmlspecialchars($t['assignee_name']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">未指派</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $p_info['color'] ?> bg-opacity-10 text-<?= $p_info['color'] ?> px-2 py-1">
                                        <i class="bi <?= $p_info['icon'] ?> me-1"></i><?= $p_info['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $s_info['color'] ?> bg-opacity-10 text-<?= $s_info['color'] ?> px-2 py-1">
                                        <?= $s_info['label'] ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <i class="bi bi-calendar3 me-1"></i><?= $t['due_date'] ? date('Y-m-d', strtotime($t['due_date'])) : '-' ?>
                                </td>
                                <td class="fw-semibold text-slate-700"><?= $t['estimated_hours'] ?> 小時</td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editTaskModal<?= $t['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('確定要移除此代辦任務嗎？')">
                                        <input type="hidden" name="delete_task_id" value="<?= $t['id'] ?>">
                                        <button type="submit" name="delete_task" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editTaskModal<?= $t['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    變更任務進度與資料
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="edit_task" value="1">
                                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">所屬項目專案 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                            <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($projects as $pr): ?>
                                                                    <option value="<?= $pr['id'] ?>" <?= $t['project_id'] == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">任務名稱 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-bookmark-dash"></i></span>
                                                            <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($t['title'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">描述</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                                            <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="2"><?= htmlspecialchars($t['description'] ?? '') ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">指派負責人</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                                            <select name="assigned_to_id" class="form-select border-start-0 ps-0 shadow-none">
                                                                <option value="">未分配</option>
                                                                <?php foreach ($users as $u): ?>
                                                                    <option value="<?= $u['id'] ?>" <?= $t['assigned_to_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">當前進度狀態 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                                            <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($status_options as $key => $opt): ?>
                                                                    <option value="<?= $key ?>" <?= $t['status'] === $key ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">優先級 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-arrow-down-up"></i></span>
                                                            <select name="priority" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($priority_options as $key => $opt): ?>
                                                                    <option value="<?= $key ?>" <?= $t['priority'] === $key ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">預估工時 (小時)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-stopwatch"></i></span>
                                                            <input type="number" step="0.5" name="estimated_hours" class="form-control border-start-0 ps-0 shadow-none" value="<?= $t['estimated_hours'] ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">到期日</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-event"></i></span>
                                                            <input type="date" name="due_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $t['due_date'] ?? '' ?>">
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
                            
                            <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                    目前沒有任何相關任務項目
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
                        <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-file-earmark-plus fs-5"></i>
                        </div>
                        新增代辦任務
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_task" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">指派所屬項目 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇專案...</option>
                                    <?php foreach ($projects as $pr): ?>
                                        <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務名稱 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-bookmark-dash"></i></span>
                                <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none" placeholder="輸入任務摘要" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務細節描述</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="2" placeholder="詳細流程說明..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">指派負責人</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <select name="assigned_to_id" class="form-select border-start-0 ps-0 shadow-none">
                                    <option value="">未分配</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">優先級</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-arrow-down-up"></i></span>
                                <select name="priority" class="form-select border-start-0 ps-0 shadow-none">
                                    <option value="low">低</option>
                                    <option value="medium" selected>中</option>
                                    <option value="high">高</option>
                                    <option value="urgent">危急</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務到期日</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="due_date" class="form-control border-start-0 ps-0 shadow-none">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">預估時數 (小時)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-stopwatch"></i></span>
                                <input type="number" step="0.5" name="estimated_hours" class="form-control border-start-0 ps-0 shadow-none" value="4">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 新增任務</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>