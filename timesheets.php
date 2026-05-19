<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$search = trim($_GET['search'] ?? '');
$project_filter = (int)($_GET['project_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$current_user_role = current_user()['role'] ?? 'viewer';
$can_approve = in_array($current_user_role, ['admin', 'pm']);

// 處理 POST 請求 (新增、編輯、刪除、審核)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增工時
    if (isset($_POST['add_timesheet'])) {
        $data = [
            'user_id' => $_SESSION['user_id'],
            'project_id' => (int)$_POST['project_id'],
            'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
            'work_date' => $_POST['work_date'],
            'hours' => (float)$_POST['hours'],
            'description' => trim($_POST['description'] ?? ''),
            'is_approved' => 0
        ];
        db_insert('timesheets', $data);
        $success = '工時記錄已成功新增！';
    }
    
    // 2. 編輯工時
    elseif (isset($_POST['edit_timesheet'])) {
        $ts_id = (int)$_POST['timesheet_id'];
        $ts = db_fetch_one("SELECT * FROM timesheets WHERE id = ?", [$ts_id]);
        
        // 權限檢查：只能編輯自己的，或 admin/pm 可以編輯所有
        if ($ts && ($ts['user_id'] == $_SESSION['user_id'] || $can_approve)) {
            $data = [
                'project_id' => (int)$_POST['project_id'],
                'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
                'work_date' => $_POST['work_date'],
                'hours' => (float)$_POST['hours'],
                'description' => trim($_POST['description'] ?? '')
            ];
            // 如果修改了已審核的工時，自動變回待審核 (除非是 Admin/PM 自己改)
            if ($ts['is_approved'] && !$can_approve) {
                $data['is_approved'] = 0;
                $data['approved_by'] = null;
            }
            db_update('timesheets', $data, 'id = ?', [$ts_id]);
            $success = '工時記錄已成功更新！';
        } else {
            $error = '你沒有權限修改此記錄。';
        }
    }
    
    // 3. 刪除工時
    elseif (isset($_POST['delete_timesheet'])) {
        $ts_id = (int)$_POST['delete_timesheet_id'];
        $ts = db_fetch_one("SELECT * FROM timesheets WHERE id = ?", [$ts_id]);
        if ($ts && ($ts['user_id'] == $_SESSION['user_id'] || $can_approve)) {
            db_delete('timesheets', 'id = ?', [$ts_id]);
            $success = '工時記錄已成功刪除！';
        } else {
            $error = '你沒有權限刪除此記錄。';
        }
    }

    // 4. 審核 / 撤銷審核工時 (僅限 Admin / PM)
    elseif (isset($_POST['toggle_approve']) && $can_approve) {
        $ts_id = (int)$_POST['timesheet_id'];
        $current_status = (int)$_POST['current_status'];
        $new_status = $current_status ? 0 : 1;
        $approved_by = $new_status ? $_SESSION['user_id'] : null;
        
        db_update('timesheets', ['is_approved' => $new_status, 'approved_by' => $approved_by], 'id = ?', [$ts_id]);
        $success = $new_status ? '工時已成功審核！' : '工時已撤銷審核並退回！';
    }
}

// 建立分頁與搜尋的 SQL 查詢
$where_clauses = ["1=1"];
$params = [];

if ($project_filter) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $project_filter;
}

if ($search) {
    $where_clauses[] = "(u.full_name LIKE ? OR t.description LIKE ? OR tk.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== '') {
    $where_clauses[] = "t.is_approved = ?";
    $params[] = (int)$status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM timesheets t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN tasks tk ON t.task_id = tk.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取當前頁資料
$sql = "SELECT t.*, p.title as project_title, u.full_name as user_name, tk.title as task_title 
        FROM timesheets t 
        JOIN projects p ON t.project_id = p.id 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE $where_sql 
        ORDER BY t.work_date DESC, t.created_at DESC 
        LIMIT $per_page OFFSET $offset";
$timesheets = db_fetch_all($sql, $params);

// 獲取關聯資料供表單使用
$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
$tasks = db_fetch_all("SELECT id, title, project_id FROM tasks ORDER BY title");

// 狀態標籤
$status_options = [
    '0' => ['label' => '待審核', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
    '1' => ['label' => '已審核', 'color' => 'success', 'icon' => 'bi-check-circle-fill']
];
?>
<?php $page_title = "工時記錄"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-clock-history me-2 text-primary"></i> 工時記錄 (Timesheets)</h2>
                <p class="text-muted mb-0 d-none d-md-block">登錄日常工作時數，追蹤專案成本與資源</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addTimesheetModal">
                <i class="bi bi-plus-circle me-1"></i> 記錄工時
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="記錄人 / 任務 / 工作說明">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">所屬專案</label>
                        <select name="project_id" class="form-select shadow-none">
                            <option value="0">全部專案</option>
                            <?php foreach ($projects as $pr): ?>
                                <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">審核狀態</label>
                        <select name="status" class="form-select shadow-none">
                            <option value="">全部狀態</option>
                            <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>待審核</option>
                            <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>已審核</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-outline-primary w-100">篩選</button>
                    </div>
                    <div class="col-md-1 text-end">
                        <a href="timesheets.php" class="btn btn-light w-100 border">清除</a>
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
                                <th>工作日期</th>
                                <th>記錄人</th>
                                <th width="25%">項目與任務</th>
                                <th>工作說明</th>
                                <th>工時</th>
                                <th>狀態</th>
                                <th width="150" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timesheets as $ts): 
                                $s_info = $status_options[$ts['is_approved']] ?? $status_options['0'];
                                $avatar_char = mb_substr($ts['user_name'] ?? 'U', 0, 1, 'UTF-8');
                                $is_owner = ($ts['user_id'] == $_SESSION['user_id']);
                            ?>
                            <tr class="<?= $ts['is_approved'] ? 'bg-light bg-opacity-50' : '' ?>">
                                <td>
                                    <div class="text-slate-800 fw-medium"><i class="bi bi-calendar-event text-muted me-1"></i><?= $ts['work_date'] ?></div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                            <span class="fw-bold small"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <span class="fw-semibold text-slate-800"><?= htmlspecialchars($ts['user_name'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-slate-700 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($ts['project_title'] ?? '') ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px;"><i class="bi bi-list-task me-1"></i><?= htmlspecialchars($ts['task_title'] ?: '無綁定特定任務') ?></div>
                                </td>
                                <td>
                                    <span class="text-slate-600 small d-block text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($ts['description'] ?? '') ?>">
                                        <?= htmlspecialchars($ts['description'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="text-indigo fs-6"><?= $ts['hours'] ?></strong> <span class="text-muted small">小時</span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $s_info['color'] ?> bg-opacity-10 text-<?= $s_info['color'] ?> px-2 py-1">
                                        <i class="bi <?= $s_info['icon'] ?> me-1"></i><?= $s_info['label'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($can_approve): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $ts['is_approved'] ?>">
                                            <button type="submit" name="toggle_approve" class="btn btn-sm btn-light border text-<?= $ts['is_approved'] ? 'warning' : 'success' ?> me-1" title="<?= $ts['is_approved'] ? '撤銷審核' : '點擊審核' ?>">
                                                <i class="bi <?= $ts['is_approved'] ? 'bi-arrow-counterclockwise' : 'bi-check2-all' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($is_owner || $can_approve): ?>
                                        <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editTimesheetModal<?= $ts['id'] ?>" title="編輯"><i class="bi bi-pencil-square"></i></button>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('確定要刪除這筆工時記錄嗎？');">
                                            <input type="hidden" name="delete_timesheet_id" value="<?= $ts['id'] ?>">
                                            <button type="submit" name="delete_timesheet" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <?php if ($is_owner || $can_approve): ?>
                            <div class="modal fade" id="editTimesheetModal<?= $ts['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    修改工時記錄
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="edit_timesheet" value="1">
                                                <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                                                
                                                <?php if($ts['is_approved'] && !$can_approve): ?>
                                                    <div class="alert alert-warning small border-0"><i class="bi bi-info-circle me-1"></i>此記錄已審核。修改後將會退回至「待審核」狀態。</div>
                                                <?php endif; ?>

                                                <div class="row g-3">
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">所屬專案項目 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                            <select name="project_id" class="form-select border-start-0 ps-0 shadow-none project-select-edit" data-target="task-select-<?= $ts['id'] ?>" required>
                                                                <?php foreach ($projects as $pr): ?>
                                                                    <option value="<?= $pr['id'] ?>" <?= $ts['project_id'] == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">關聯任務 (可選)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-list-task"></i></span>
                                                            <select name="task_id" id="task-select-<?= $ts['id'] ?>" class="form-select border-start-0 ps-0 shadow-none">
                                                                <option value="" data-project="all">無綁定特定任務</option>
                                                                <?php foreach ($tasks as $tk): ?>
                                                                    <option value="<?= $tk['id'] ?>" data-project="<?= $tk['project_id'] ?>" <?= $ts['task_id'] == $tk['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tk['title'] ?? '') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">工作日期 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                                            <input type="date" name="work_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $ts['work_date'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">申報工時 (小時) *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-stopwatch"></i></span>
                                                            <input type="number" step="0.5" name="hours" class="form-control border-start-0 ps-0 shadow-none text-primary fw-bold" value="<?= $ts['hours'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">工作說明 (做了什麼?)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                                            <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="2"><?= htmlspecialchars($ts['description'] ?? '') ?></textarea>
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
                            <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if (empty($timesheets)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-stopwatch fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的工時記錄
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
                        <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="addTimesheetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                        記錄新工時
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_timesheet" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬專案項目 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" id="add-project-select" required>
                                    <option value="">請選擇專案...</option>
                                    <?php foreach ($projects as $pr): ?>
                                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯任務 (可選)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-list-task"></i></span>
                                <select name="task_id" id="add-task-select" class="form-select border-start-0 ps-0 shadow-none">
                                    <option value="" data-project="all">無綁定特定任務</option>
                                    <?php foreach ($tasks as $tk): ?>
                                        <option value="<?= $tk['id'] ?>" data-project="<?= $tk['project_id'] ?>"><?= htmlspecialchars($tk['title'] ?? '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">工作日期 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                <input type="date" name="work_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">申報工時 (小時) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-stopwatch"></i></span>
                                <input type="number" step="0.5" name="hours" class="form-control border-start-0 ps-0 shadow-none text-primary fw-bold" value="1" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">工作說明 (做了什麼?)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-text"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="2" placeholder="簡述今日工作內容..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 儲存記錄</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 綁定所有專案選擇器 (包含 Add 同 Edit Modals)
    const bindProjectTaskFilter = (projectSelect, taskSelect) => {
        if(!projectSelect || !taskSelect) return;
        
        projectSelect.addEventListener('change', function() {
            const projectId = this.value;
            const options = taskSelect.querySelectorAll('option');
            
            // 先重置 Task 選擇為第一項 (無綁定)
            taskSelect.value = "";
            
            options.forEach(opt => {
                // 保留「無綁定」選項 或 屬於該專案的任務
                if (opt.getAttribute('data-project') === 'all' || opt.getAttribute('data-project') === projectId) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
        });
        
        // 頁面載入時觸發一次，確保編輯 Modal 的選項正確
        projectSelect.dispatchEvent(new Event('change'));
    };

    // 初始化 Add Modal 的聯動
    bindProjectTaskFilter(document.getElementById('add-project-select'), document.getElementById('add-task-select'));

    // 初始化所有 Edit Modals 的聯動
    document.querySelectorAll('.project-select-edit').forEach(select => {
        const targetId = select.getAttribute('data-target');
        bindProjectTaskFilter(select, document.getElementById(targetId));
    });
});
</script>

<?php include 'includes/footer.php'; ?>