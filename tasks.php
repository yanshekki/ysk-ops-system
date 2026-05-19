<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$project_filter = $_GET['project_id'] ?? 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $data = [
        'project_id' => (int)$_POST['project_id'],
        'title' => trim($_POST['title']),
        'description' => trim($_POST['description'] ?? ''),
        'assigned_to_id' => !empty($_POST['assigned_to_id']) ? (int)$_POST['assigned_to_id'] : null,
        'status' => $_POST['status'] ?? 'todo',
        'priority' => $_POST['priority'] ?? 'medium',
        'due_date' => $_POST['due_date'] ?: null,
        'estimated_hours' => (float)($_POST['estimated_hours'] ?? 0)
    ];
    db_insert('tasks', $data);
    $success = '任務新增成功！';
}

// Build query for count
$count_sql = "SELECT COUNT(*) as total FROM tasks WHERE 1=1";
$count_params = [];

if ($project_filter) {
    $count_sql .= " AND project_id = ?";
    $count_params[] = $project_filter;
}

total = db_fetch_one($count_sql, $count_params)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// Fetch tasks with pagination
$sql = "SELECT t.*, p.title as project_title, u.full_name as assignee_name 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        LEFT JOIN users u ON t.assigned_to_id = u.id";
if ($project_filter) $sql .= " WHERE t.project_id = " . (int)$project_filter;
$sql .= " ORDER BY t.due_date ASC, t.priority DESC LIMIT $per_page OFFSET $offset";
$tasks = db_fetch_all($sql);

$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
$users = db_fetch_all("SELECT id, full_name FROM users ORDER BY full_name");

$priority_labels = ['urgent'=>'危急','high'=>'高','medium'=>'中','low'=>'低'];
$status_labels = ['todo'=>'待辦','in_progress'=>'進行中','review'=>'審核','done'=>'完成'];
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>任務追蹤 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;">
        <div class="d-flex align-items-center mb-4 px-2"><i class="bi bi-gear-fill fs-3 me-2 text-primary"></i><span class="fs-4 fw-bold">YSK Ops</span></div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="clients.php" class="nav-link mb-1"><i class="bi bi-people me-2"></i> 客戶管理</a>
            <a href="projects.php" class="nav-link mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <a href="tasks.php" class="nav-link active mb-1"><i class="bi bi-list-task me-2"></i> 任務追蹤</a>
            <a href="invoices.php" class="nav-link mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-list-task me-2"></i> 任務追蹤</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal"><i class="bi bi-plus-circle me-1"></i> 新增任務</button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="mb-3">
            <form method="GET" class="d-flex align-items-center gap-2">
                <label>篩選項目：</label>
                <select name="project_id" class="form-select w-auto" onchange="this.form.submit()">
                    <option value="0">全部項目</option>
                    <?php foreach ($projects as $pr): ?>
                    <option value="<?= $pr['id'] ?>" <?= $project_filter == $pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>任務</th><th>所屬項目</th><th>負責人</th><th>優先級</th><th>狀態</th><th>到期日</th><th>預估工時</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['title']) ?></strong><br><small><?= htmlspecialchars(substr($t['description'],0,50)) ?></small></td>
                            <td><?= htmlspecialchars($t['project_title']) ?></td>
                            <td><?= htmlspecialchars($t['assignee_name'] ?: '未分配') ?></td>
                            <td><span class="badge bg-<?= $t['priority']=='urgent'?'danger':($t['priority']=='high'?'warning':'info') ?>"><?= $priority_labels[$t['priority']] ?></span></td>
                            <td><span class="badge bg-<?= $t['status']=='done'?'success':($t['status']=='in_progress'?'primary':'secondary') ?>"><?= $status_labels[$t['status']] ?></span></td>
                            <td><?= $t['due_date'] ? date('Y-m-d', strtotime($t['due_date'])) : '-' ?></td>
                            <td><?= $t['estimated_hours'] ?> 小時</td>
                        </tr>
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
                        <a class="page-link" href="?page=<?= $page-1 ?>&project_id=<?= $project_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&project_id=<?= $project_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&project_id=<?= $project_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header"><h5 class="modal-title">新增任務</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="add_task" value="1">
                    <div class="mb-3">
                        <label class="form-label">所屬項目 *</label>
                        <select name="project_id" class="form-select" required>
                            <?php foreach ($projects as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['title']) ?></option>
                            <?php endforeach; ?>
                            </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">任務名稱 *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">負責人</label>
                            <select name="assigned_to_id" class="form-select">
                                <option value="">未分配</option>
                                <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">優先級</label>
                            <select name="priority" class="form-select">
                                <option value="low">低</option><option value="medium" selected>中</option>
                                <option value="high">高</option><option value="urgent">危急</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">到期日</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">預估工時</label>
                            <input type="number" step="0.5" name="estimated_hours" class="form-control" value="4">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">新增任務</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>