<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'developer', 'finance']);

$success = $error = '';
$current_user_id = $_SESSION['user_id'];
$is_management = has_role('admin') || has_role('pm');

// 接收篩選與分頁參數
$search = trim($_GET['search'] ?? '');
$project_filter = (int)($_GET['project_id'] ?? 0);
$user_filter = $is_management ? (int)($_GET['user_id'] ?? 0) : $current_user_id; // 非管理層只可看自己
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$current_user_role = current_user()['role'] ?? 'viewer';
$can_approve = in_array($current_user_role, ['admin', 'pm']);

// ==============================================
// 處理表單提交 (新增、編輯、刪除、審核) - 🛡️ 嚴格後端權限驗證
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增工時 (所有具備填報權限的角色皆可)
    if (isset($_POST['add_timesheet'])) {
        $data = [
            'user_id' => $current_user_id,
            'project_id' => (int)$_POST['project_id'],
            'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
            'work_date' => $_POST['work_date'], 
            'hours' => (float)$_POST['hours'],
            'description' => trim($_POST['description'] ?? ''),
            'is_approved' => 0
        ];
        if (!empty($data['project_id']) && $data['hours'] > 0 && !empty($data['work_date'])) {
            db_insert('timesheets', $data);
            $success = '工時記錄已成功申報！';
        } else {
            $error = '請填寫所有必填欄位並輸入有效工時！';
        }
    }
    
    // 2. 編輯工時
    elseif (isset($_POST['edit_timesheet'])) {
        $ts_id = (int)$_POST['timesheet_id'];
        $ts = db_fetch_one("SELECT * FROM timesheets WHERE id = ?", [$ts_id]);
        
        // 權限檢查：只能編輯自己的，或 admin/pm 可以編輯所有
        if ($ts && ($ts['user_id'] == $current_user_id || $can_approve)) {
            $data = [
                'project_id' => (int)$_POST['project_id'],
                'task_id' => !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null,
                'work_date' => $_POST['work_date'],
                'hours' => (float)$_POST['hours'],
                'description' => trim($_POST['description'] ?? '')
            ];
            // 若非管理層修改了已審核的工時，自動退回「待審核」狀態
            if ($ts['is_approved'] && !$can_approve) {
                $data['is_approved'] = 0;
                $data['approved_by'] = null;
            }
            db_update('timesheets', $data, 'id = ?', [$ts_id]);
            $success = '工時記錄內容已成功更新！';
        } else {
            $error = '權限不足！你只能修改自己的工時記錄。';
        }
    }
    
    // 3. 刪除工時
    elseif (isset($_POST['delete_timesheet'])) {
        $ts_id = (int)$_POST['delete_timesheet_id'];
        $ts = db_fetch_one("SELECT * FROM timesheets WHERE id = ?", [$ts_id]);
        
        // 權限檢查：只能刪除自己的，或 admin/pm 可以刪除所有
        if ($ts && ($ts['user_id'] == $current_user_id || $can_approve)) {
            db_delete('timesheets', 'id = ?', [$ts_id]);
            $success = '工時記錄已徹底刪除！';
        } else {
            $error = '權限不足！你只能刪除自己的工時記錄。';
        }
    }

    // 4. 審核 / 撤銷審核工時 (僅限 Admin / PM)
    elseif (isset($_POST['toggle_approve'])) {
        if ($can_approve) {
            $ts_id = (int)$_POST['timesheet_id'];
            $current_status = (int)$_POST['current_status'];
            $new_status = $current_status ? 0 : 1;
            $approved_by = $new_status ? $current_user_id : null;
            
            db_update('timesheets', ['is_approved' => $new_status, 'approved_by' => $approved_by], 'id = ?', [$ts_id]);
            $success = $new_status ? '工時已審核通過！' : '工時已退回至待審核狀態！';
        } else {
            $error = '權限不足！您沒有審核工時的權限。';
        }
    }
}

// ==============================================
// 建立分頁與搜尋的 SQL 查詢
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($project_filter) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $project_filter;
}

if ($user_filter) {
    $where_clauses[] = "t.user_id = ?";
    $params[] = $user_filter;
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

if ($date_from) {
    $where_clauses[] = "t.work_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_clauses[] = "t.work_date <= ?";
    $params[] = $date_to;
}

$where_sql = implode(" AND ", $where_clauses);

// 📊 獲取頂部 KPI 統計數據 (依據篩選條件計算)
$stats_sql = "SELECT 
                COALESCE(SUM(t.hours), 0) as total_hours,
                COALESCE(SUM(CASE WHEN t.is_approved = 0 THEN t.hours ELSE 0 END), 0) as pending_hours,
                COUNT(t.id) as log_count
              FROM timesheets t 
              LEFT JOIN users u ON t.user_id = u.id 
              LEFT JOIN tasks tk ON t.task_id = tk.id 
              WHERE $where_sql";
$stats_data = db_fetch_one($stats_sql, $params);

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
$team_members = db_fetch_all("SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");

// 狀態標籤設定
$status_options = [
    '0' => ['label' => '待審核', 'color' => 'warning', 'icon' => 'bi-hourglass-split'],
    '1' => ['label' => '已審核', 'color' => 'success', 'icon' => 'bi-check-circle-fill']
];
?>
<?php $page_title = "工時記錄 Timesheets"; ?>
<?php include 'includes/header.php'; ?>

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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-stopwatch me-2 text-primary"></i> 工時記錄 (Timesheets)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">登錄日常工作時數，精準追蹤專案研發成本與人力資源</p>
                    </div>
                </div>
                <div>
                    <?php if(has_any_role(['admin', 'pm', 'developer'])): ?>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addTimesheetModal">
                        <i class="bi bi-plus-circle me-1"></i> 申報工時
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm" style="border-left: 4px solid #6366f1 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">條件範圍總工時</small>
                                <h3 class="fw-bold mb-0 text-slate-800"><?= number_format($stats_data['total_hours'], 1) ?> <span class="fs-6 text-muted font-normal">小時</span></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3"><i class="bi bi-clock-history fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">待審核工時</small>
                                <h3 class="fw-bold mb-0 text-warning"><?= number_format($stats_data['pending_hours'], 1) ?> <span class="fs-6 text-muted font-normal">小時</span></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3"><i class="bi bi-clipboard-pulse fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm" style="border-left: 4px solid #10b981 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">申報筆數</small>
                                <h3 class="fw-bold mb-0 text-success"><?= $stats_data['log_count'] ?> <span class="fs-6 text-muted font-normal">筆紀錄</span></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3"><i class="bi bi-file-earmark-check fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬專案</label>
                            <select name="project_id" class="form-select shadow-none border" onchange="this.form.submit()">
                                <option value="0">全部專案項目</option>
                                <?php foreach ($projects as $pr): ?>
                                    <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($is_management): ?>
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">團隊成員</label>
                            <select name="user_id" class="form-select shadow-none border" onchange="this.form.submit()">
                                <option value="0">全體員工數據</option>
                                <?php foreach ($team_members as $tm): ?>
                                    <option value="<?= $tm['id'] ?>" <?= $user_filter == $tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['full_name']) ?> (<?= strtoupper($tm['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">日期起 (From)</label>
                            <input type="date" name="date_from" class="form-control shadow-none border" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">日期迄 (To)</label>
                            <input type="date" name="date_to" class="form-control shadow-none border" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">審核狀態</label>
                            <select name="status" class="form-select shadow-none border" onchange="this.form.submit()">
                                <option value="">全部狀態</option>
                                <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>待審核</option>
                                <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>已審核</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3 d-flex gap-2">
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋工作說明或任務名稱...">
                                <button type="submit" class="btn btn-primary fw-medium px-4">搜尋套用</button>
                            </div>
                            <a href="timesheets.php" class="btn btn-light border text-muted px-4" title="重置過濾條件"><i class="bi bi-arrow-counterclockwise"></i></a>
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
                                    <th class="ps-4 py-3">工作日期</th>
                                    <th class="py-3">記錄人</th>
                                    <th class="py-3" width="25%">專案與關聯任務</th>
                                    <th class="py-3">具體工作說明</th>
                                    <th class="py-3 text-end">工時 (Hrs)</th>
                                    <th class="py-3 text-center">狀態</th>
                                    <th class="text-end pe-4 py-3" width="150">操作</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($timesheets as $ts): 
                                    $s_info = $status_options[$ts['is_approved']] ?? $status_options['0'];
                                    $avatar_char = mb_substr($ts['user_name'] ?? 'U', 0, 1, 'UTF-8');
                                    $is_owner = ($ts['user_id'] == $current_user_id);
                                ?>
                                <tr class="<?= $ts['is_approved'] ? 'bg-light bg-opacity-50' : '' ?>">
                                    <td class="ps-4 text-slate-800 fw-medium">
                                        <i class="bi bi-calendar-event text-muted me-1"></i><?= $ts['work_date'] ?>
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
                                        <div class="fw-bold text-slate-700 text-truncate" style="max-width: 250px;"><i class="bi bi-folder2 me-1 text-primary"></i><?= htmlspecialchars($ts['project_title'] ?? '') ?></div>
                                        <?php if ($ts['task_title']): ?>
                                            <div class="small text-muted text-truncate mt-1" style="max-width: 250px;"><i class="bi bi-list-task me-1"></i><?= htmlspecialchars($ts['task_title']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-slate-600 small d-block text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($ts['description'] ?? '') ?>">
                                            <?= htmlspecialchars($ts['description'] ?? '沒有填寫詳細說明') ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-indigo fs-6"><?= number_format($ts['hours'], 1) ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $s_info['color'] ?> bg-opacity-10 text-<?= $s_info['color'] ?> px-2 py-1">
                                            <i class="bi <?= $s_info['icon'] ?> me-1"></i><?= $s_info['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if ($can_approve): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $ts['is_approved'] ?>">
                                                    <button type="submit" name="toggle_approve" class="btn btn-sm btn-light border text-<?= $ts['is_approved'] ? 'warning' : 'success' ?>" title="<?= $ts['is_approved'] ? '撤銷審核' : '點擊審核' ?>">
                                                        <i class="bi <?= $ts['is_approved'] ? 'bi-arrow-counterclockwise' : 'bi-check-lg' ?>"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($is_owner || $can_approve): ?>
                                                <button class="btn btn-sm btn-light border text-primary" data-bs-toggle="modal" data-bs-target="#editTimesheetModal<?= $ts['id'] ?>" title="編輯時數">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                
                                                <form method="POST" class="d-inline" onsubmit="return confirm('確定要永久刪除這筆工時記錄嗎？');">
                                                    <input type="hidden" name="delete_timesheet_id" value="<?= $ts['id'] ?>">
                                                    <button type="submit" name="delete_timesheet" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-light border text-muted disabled" title="權限不足"><i class="bi bi-lock"></i></button>
                                            <?php endif; ?>
                                        </div>
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
                                                        修正工時記錄
                                                    </h5>
                                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="edit_timesheet" value="1">
                                                    <input type="hidden" name="timesheet_id" value="<?= $ts['id'] ?>">
                                                    
                                                    <?php if($ts['is_approved'] && !$can_approve): ?>
                                                        <div class="alert alert-warning small border-0"><i class="bi bi-info-circle me-1"></i>此記錄已被審核。若您修改內容，系統將會自動將其退回至「待審核」狀態。</div>
                                                    <?php endif; ?>

                                                    <div class="row g-3">
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯項目專案 *</label>
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
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">綁定細部任務 (可選)</label>
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
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">實際工作日期 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                                                                <input type="date" name="work_date" class="form-control border-start-0 ps-0 shadow-none" value="<?= $ts['work_date'] ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">投入工時 (Hours) *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-stopwatch"></i></span>
                                                                <input type="number" step="0.5" min="0.5" name="hours" class="form-control border-start-0 ps-0 shadow-none text-primary fw-bold" value="<?= $ts['hours'] ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">工作具體描述 (做了什麼) *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-card-text"></i></span>
                                                                <textarea name="description" class="form-control border-start-0 ps-0 shadow-none bg-light" rows="3" required><?= htmlspecialchars($ts['description'] ?? '') ?></textarea>
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
                                        在此篩選條件下，找不到符合的工時記錄
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
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&project_id=<?= $project_filter ?>&user_id=<?= $user_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&project_id=<?= $project_filter ?>&user_id=<?= $user_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&project_id=<?= $project_filter ?>&user_id=<?= $user_filter ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <?php if(has_any_role(['admin', 'pm', 'developer'])): ?>
        <div class="modal fade" id="addTimesheetModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-clock-history fs-5"></i>
                        </div>
                        填報新工時
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_timesheet" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">研發投入專案 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" id="add-project-select" required>
                                    <option value="">請選擇專案項目...</option>
                                    <?php foreach ($projects as $pj): ?>
                                        <option value="<?= $pj['id'] ?>"><?= htmlspecialchars($pj['title'] ?? '') ?></option>
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
                            <label class="form-label text-slate-500 fw-semibold small mb-1">本段工時 (Hours) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary fw-bold border-end-0"><i class="bi bi-stopwatch"></i></span>
                                <input type="number" step="0.5" min="0.5" name="hours" class="form-control border-start-0 ps-0 shadow-none text-primary fw-bold" placeholder="例如: 3.5" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">具體研發工作說明描述 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-card-text"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0 shadow-none" rows="3" placeholder="請詳細敘述您在此時段內完成的開發工作、Bug 修復或會議內容..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 提交申報</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 綁定動態「專案 -> 任務」下拉選單聯動 (JS 前端過濾)
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
        
        // 初始化載入時觸發一次
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