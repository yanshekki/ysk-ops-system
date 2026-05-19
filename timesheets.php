<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$project_filter = $_GET['project_id'] ?? 0;

// Handle add timesheet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_timesheet'])) {
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
    $success = '工時記錄已新增！';
}

// Fetch timesheets
$sql = "SELECT t.*, p.title as project_title, u.full_name as user_name, tk.title as task_title 
        FROM timesheets t 
        JOIN projects p ON t.project_id = p.id 
        JOIN users u ON t.user_id = u.id 
        LEFT JOIN tasks tk ON t.task_id = tk.id 
        WHERE 1=1";

if ($project_filter) $sql .= " AND t.project_id = " . (int)$project_filter;
$sql .= " ORDER BY t.work_date DESC, t.created_at DESC";
$timesheets = db_fetch_all($sql);

$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
$tasks = db_fetch_all("SELECT id, title, project_id FROM tasks ORDER BY title");
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工時記錄 | <?= SITE_NAME ?></title>
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
            <a href="projects.php" class="nav-link mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <a href="tasks.php" class="nav-link mb-1"><i class="bi bi-list-task me-2"></i> 任務追蹤</a>
            <a href="timesheets.php" class="nav-link active mb-1"><i class="bi bi-clock-history me-2"></i> 工時記錄</a>
            <a href="invoices.php" class="nav-link mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-clock-history me-2"></i> 工時記錄 (Timesheets)</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTimesheetModal">
                <i class="bi bi-plus-circle me-1"></i> 記錄工時
            </button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>日期</th>
                            <th>項目</th>
                            <th>任務</th>
                            <th>記錄人</th>
                            <th>工時</th>
                            <th>說明</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timesheets as $ts): ?>
                        <tr>
                            <td><?= $ts['work_date'] ?></td>
                            <td><?= htmlspecialchars($ts['project_title']) ?></td>
                            <td><?= htmlspecialchars($ts['task_title'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($ts['user_name']) ?></td>
                            <td><strong><?= $ts['hours'] ?></strong> 小時</td>
                            <td><?= htmlspecialchars($ts['description']) ?></td>
                            <td>
                                <?php if ($ts['is_approved']): ?>
                                    <span class="badge bg-success">已審核</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">待審核</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Timesheet Modal -->
<div class="modal fade" id="addTimesheetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">記錄工時</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_timesheet" value="1">
                    <div class="mb-3">
                        <label class="form-label">項目 *</label>
                        <select name="project_id" class="form-select" required>
                            <?php foreach ($projects as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">任務 (可選)</label>
                        <select name="task_id" class="form-select">
                            <option value="">無關任務</option>
                            <?php foreach ($tasks as $tk): ?>
                            <option value="<?= $tk['id'] ?>"><?= htmlspecialchars($tk['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">工作日期 *</label>
                            <input type="date" name="work_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">工時 *</label>
                            <input type="number" step="0.5" name="hours" class="form-control" value="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">工作說明</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="今天做了什麼..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">記錄工時</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>