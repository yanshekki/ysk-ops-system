<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'developer', 'viewer']);

$success = $error = '';

// 接收篩選與分頁參數
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$project_filter = $_GET['project_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ==============================================
// 處理表單提交 (新增、編輯、刪除)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增任務
    if (isset($_POST['add_task'])) {
        $data = [
            'project_id' => (int)$_POST['project_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'assigned_to_id' => !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null,
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => 'todo',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0)
        ];
        if (!empty($data['title']) && !empty($data['project_id'])) {
            db_insert('tasks', $data);
            $success = '新任務已成功建立並指派！';
        } else {
            $error = '請填寫必填欄位 (任務標題及所屬項目)！';
        }
    }
    
    // 2. 編輯任務
    elseif (isset($_POST['edit_task'])) {
        $task_id = (int)$_POST['task_id'];
        $data = [
            'project_id' => (int)$_POST['project_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'assigned_to_id' => !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null,
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => $_POST['status'] ?? 'todo',
            'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0)
        ];
        
        // Developer 只能改 Status，其他由 admin/pm 處理
        if (has_any_role(['developer']) && !has_any_role(['admin', 'pm'])) {
            $data = ['status' => $_POST['status'] ?? 'todo'];
        }

        db_update('tasks', $data, 'id = ?', [$task_id]);
        $success = '任務內容已成功更新！';
    }
    
    // 3. 刪除任務
    elseif (isset($_POST['delete_task'])) {
        $task_id = (int)$_POST['delete_task_id'];
        db_delete('tasks', 'id = ?', [$task_id]);
        $success = '任務已成功刪除！';
    }
}

// ==============================================
// 構建查詢條件與獲取資料
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($project_filter) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $project_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM tasks t WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取任務列表
$sql = "SELECT t.*, p.title as project_title, u.full_name as dev_name 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users u ON t.assigned_to_id = u.id 
        WHERE $where_sql 
        ORDER BY 
            CASE t.status WHEN 'done' THEN 1 ELSE 0 END, /* 已完成沉底 */
            CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END, 
            t.due_date ASC
        LIMIT $per_page OFFSET $offset";
$tasks = db_fetch_all($sql, $params);

// 獲取選單用資料
$projects = db_fetch_all("SELECT id, title FROM projects WHERE status != 'cancelled' ORDER BY updated_at DESC");
$team = db_fetch_all("SELECT id, full_name FROM users WHERE is_active=1");

// 狀態設定
$status_badges = [
    'todo' => ['label' => '待處理', 'color' => 'secondary'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary'],
    'review' => ['label' => '審核中', 'color' => 'warning'],
    'done' => ['label' => '已完成', 'color' => 'success']
];

$priority_badges = [
    'low' => ['label' => 'LOW', 'color' => 'secondary'],
    'medium' => ['label' => 'MEDIUM', 'color' => 'info'],
    'high' => ['label' => 'HIGH', 'color' => 'warning'],
    'urgent' => ['label' => 'URGENT', 'color' => 'danger']
];

// ==============================================
// 視圖渲染開始 (套用黃金排版準則)
// ==============================================
$page_title = "任務追蹤 Tasks";
include 'includes/header.php';
?>

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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-list-task me-2 text-primary"></i> 任務追蹤 (Tasks)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">追蹤工程團隊任務指派、代辦狀態與交付死線</p>
                    </div>
                </div>
                <div>
                    <?php if(has_any_role(['admin', 'pm'])): ?>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                        <i class="bi bi-plus-circle me-1"></i> 新增任務
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋任務名稱...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="project_id" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">所有所屬項目</option>
                                <?php foreach ($projects as $pr): ?>
                                    <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">所有狀態</option>
                                <?php foreach ($status_badges as $val => $opt): ?>
                                    <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="tasks.php" class="btn btn-light border w-100 text-muted">清除篩選</a>
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
                            <thead class="bg-light text-slate-600">
                                <tr>
                                    <th class="ps-4 py-3">任務標題與說明</th>
                                    <th class="py-3">所屬專案</th>
                                    <th class="py-3">執行人</th>
                                    <th class="py-3">截止日期</th>
                                    <th class="py-3">優先級</th>
                                    <th class="py-3 text-center">狀態</th>
                                    <th class="text-end pe-4 py-3">操作</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($tasks as $t): 
                                    $p_badge = $priority_badges[$t['priority']] ?? $priority_badges['medium'];
                                    $s_badge = $status_badges[$t['status']] ?? $status_badges['todo'];
                                    $is_overdue = ($t['due_date'] && strtotime($t['due_date']) < time() && $t['status'] !== 'done');
                                ?>
                                <tr class="<?= $t['status'] === 'done' ? 'opacity-75 bg-light' : '' ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-slate-800 <?= $t['status'] === 'done' ? 'text-decoration-line-through' : '' ?>" style="font-size: 1.05rem;">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </div>
                                        <div class="text-slate-500 small text-truncate" style="max-width:300px;">
                                            <?= htmlspecialchars($t['description'] ?: '無詳細說明') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-slate-700 small fw-semibold"><i class="bi bi-folder2 me-1 text-muted"></i><?= htmlspecialchars($t['project_title']) ?></span>
                                    </td>
                                    <td>
                                        <span class="small fw-medium text-slate-700">
                                            <i class="bi bi-person-circle me-1 text-muted"></i><?= htmlspecialchars($t['dev_name'] ?? '未認領') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small <?= $is_overdue ? 'text-danger fw-bold' : 'text-slate-600' ?>">
                                            <?php if($is_overdue): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                            <?= $t['due_date'] ? date('Y-m-d', strtotime($t['due_date'])) : '-' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $p_badge['color'] ?> bg-opacity-10 text-<?= $p_badge['color'] ?> px-2 py-1">
                                            <?= $p_badge['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $s_badge['color'] ?> px-2 py-1">
                                            <?= $s_badge['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if(has_any_role(['admin', 'pm']) || (has_role('developer') && $t['assigned_to_id'] == $_SESSION['user_id'])): ?>
                                            <button class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#editTaskModal<?= $t['id'] ?>" title="編輯任務">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if(has_any_role(['admin', 'pm'])): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('確定要刪除任務「<?= htmlspecialchars($t['title']) ?>」嗎？');">
                                                <input type="hidden" name="delete_task_id" value="<?= $t['id'] ?>">
                                                <button type="submit" name="delete_task" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <?php if(has_any_role(['admin', 'pm']) || (has_role('developer') && $t['assigned_to_id'] == $_SESSION['user_id'])): ?>
                                <div class="modal fade" id="editTaskModal<?= $t['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                            <i class="bi bi-pencil-square fs-5"></i>
                                                        </div>
                                                        編輯任務
                                                    </h5>
                                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="edit_task" value="1">
                                                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                    <div class="row g-3">
                                                        <?php if(has_any_role(['admin', 'pm'])): ?>
                                                        <!-- PM / Admin 完整編輯表單 -->
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務標題 *</label>
                                                            <input type="text" name="title" class="form-control shadow-none fw-bold" value="<?= htmlspecialchars($t['title']) ?>" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬項目 *</label>
                                                            <select name="project_id" class="form-select shadow-none" required>
                                                                <?php foreach ($projects as $pr): ?>
                                                                    <option value="<?= $pr['id'] ?>" <?= $t['project_id'] == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">執行擔當人</label>
                                                            <select name="assigned_to_id" class="form-select shadow-none">
                                                                <option value="">-- 開放認領 --</option>
                                                                <?php foreach ($team as $tm): ?>
                                                                    <option value="<?= $tm['id'] ?>" <?= $t['assigned_to_id'] == $tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['full_name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">當前狀態</label>
                                                            <select name="status" class="form-select shadow-none">
                                                                <?php foreach ($status_badges as $val => $opt): ?>
                                                                    <option value="<?= $val ?>" <?= $t['status'] === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">優先程度</label>
                                                            <select name="priority" class="form-select shadow-none">
                                                                <?php foreach ($priority_badges as $val => $opt): ?>
                                                                    <option value="<?= $val ?>" <?= $t['priority'] === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">預估工時</label>
                                                            <input type="number" step="0.5" name="estimated_hours" class="form-control shadow-none" value="<?= $t['estimated_hours'] ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">截止死線日期</label>
                                                            <input type="date" name="due_date" class="form-control shadow-none" value="<?= $t['due_date'] ?>">
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務具體內容描述</label>
                                                            <textarea name="description" class="form-control shadow-none bg-light" rows="3"><?= htmlspecialchars($t['description']) ?></textarea>
                                                        </div>
                                                        <?php else: ?>
                                                        <!-- Developer 只能更新狀態的精簡表單 -->
                                                        <div class="col-12">
                                                            <h5 class="fw-bold text-slate-800 mb-1"><?= htmlspecialchars($t['title']) ?></h5>
                                                            <p class="text-muted small mb-4"><?= htmlspecialchars($t['description']) ?></p>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">更新當前狀態</label>
                                                            <select name="status" class="form-select shadow-none border-primary">
                                                                <?php foreach ($status_badges as $val => $opt): ?>
                                                                    <option value="<?= $val ?>" <?= $t['status'] === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <?php endif; ?>
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
                                <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if (empty($tasks)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                        找不到符合條件的任務記錄
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination shadow-sm">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&project_id=<?= $project_filter ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&project_id=<?= $project_filter ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&project_id=<?= $project_filter ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <?php if(has_any_role(['admin', 'pm'])): ?>
        <div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-patch-plus fs-5"></i>
                        </div>
                        指派新任務
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_task" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬項目 *</label>
                            <select name="project_id" class="form-select shadow-none" required>
                                <option value="">請選擇專案...</option>
                                <?php foreach ($projects as $pr): ?>
                                    <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務標題名稱 *</label>
                            <input type="text" name="title" class="form-control shadow-none fw-bold" placeholder="例如：優化結帳流程 API" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">執行擔當人</label>
                            <select name="assigned_to_id" class="form-select shadow-none">
                                <option value="">-- 開放認領 --</option>
                                <?php foreach ($team as $tm): ?>
                                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">優先程度</label>
                            <select name="priority" class="form-select shadow-none">
                                <?php foreach ($priority_badges as $val => $opt): ?>
                                    <option value="<?= $val ?>" <?= $val === 'medium' ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">截止死線日期</label>
                            <input type="date" name="due_date" class="form-control shadow-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">預估工時 (Hours)</label>
                            <input type="number" step="0.5" name="estimated_hours" class="form-control shadow-none" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">任務具體內容描述</label>
                            <textarea name="description" class="form-control shadow-none bg-light" rows="3" placeholder="任務的要求細節..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">儲存並分派</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>