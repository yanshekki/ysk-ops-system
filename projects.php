<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'developer', 'finance', 'viewer']);

$success = $error = '';

// 接收篩選與分頁參數
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9; // 3x3 網格
$offset = ($page - 1) * $per_page;

// ==============================================
// 處理表單提交 (新增、編輯、刪除)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增專案
    if (isset($_POST['create_project'])) {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'service_type' => $_POST['service_type'],
            'status' => 'planning',
            'progress_percent' => 0,
            'budget' => (float)($_POST['budget'] ?? 0),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'assigned_pm_id' => !empty($_POST['assigned_pm_id']) ? (int)$_POST['assigned_pm_id'] : null,
            'created_by' => $_SESSION['user_id']
        ];
        if (!empty($data['title']) && !empty($data['client_id'])) {
            db_insert('projects', $data);
            $success = '新專案項目已順利建立！';
        } else {
            $error = '請填寫必填欄位 (客戶及項目名稱)！';
        }
    }
    
    // 2. 編輯專案
    elseif (isset($_POST['edit_project'])) {
        $project_id = (int)$_POST['project_id'];
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'service_type' => $_POST['service_type'],
            'status' => $_POST['status'],
            'progress_percent' => (int)$_POST['progress_percent'],
            'budget' => (float)($_POST['budget'] ?? 0),
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'assigned_pm_id' => !empty($_POST['assigned_pm_id']) ? (int)$_POST['assigned_pm_id'] : null
        ];
        
        // 防呆：如果設定為 completed，進度自動 100%
        if ($data['status'] === 'completed') $data['progress_percent'] = 100;
        
        db_update('projects', $data, 'id = ?', [$project_id]);
        $success = '專案項目資料已成功更新！';
    }
    
    // 3. 刪除專案
    elseif (isset($_POST['delete_project'])) {
        $project_id = (int)$_POST['delete_project_id'];
        try {
            db_delete('projects', 'id = ?', [$project_id]);
            $success = '專案項目已成功刪除！';
        } catch (Exception $e) {
            $error = '無法刪除此項目，可能已有綁定的任務或發票記錄。';
        }
    }
}

// ==============================================
// 構建查詢條件與獲取資料
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

if ($status_filter) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取項目列表
$sql = "SELECT p.*, c.company_name, u.full_name as pm_name 
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id 
        LEFT JOIN users u ON p.assigned_pm_id = u.id 
        WHERE $where_sql 
        ORDER BY CASE WHEN p.status IN ('completed', 'cancelled') THEN 1 ELSE 0 END, p.updated_at DESC 
        LIMIT $per_page OFFSET $offset";
$projects = db_fetch_all($sql, $params);

// 獲取選單用資料
$clients = db_fetch_all("SELECT id, company_name FROM clients WHERE status='active' ORDER BY company_name");
$pms = db_fetch_all("SELECT id, full_name FROM users WHERE role IN ('admin','pm') AND is_active=1");

// 狀態與服務類型設定
$status_options = [
    'planning' => ['label' => '規劃中', 'color' => 'secondary'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary'],
    'review' => ['label' => '審核中', 'color' => 'info'],
    'completed' => ['label' => '已完成', 'color' => 'success'],
    'on_hold' => ['label' => '暫停', 'color' => 'warning'],
    'cancelled' => ['label' => '已取消', 'color' => 'danger']
];

$service_options = [
    'app_development' => 'App 開發',
    'ai_automation' => 'AI 自動化工程',
    'cloud_security' => '雲端安全架構',
    'web3_blockchain' => 'Web3 區塊鏈',
    'other' => '其他服務'
];

// ==============================================
// 視圖渲染開始 (套用黃金排版準則)
// ==============================================
$page_title = "項目管理 Projects";
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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-folder2-open me-2 text-primary"></i> 項目管理 (Projects)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">監控開發合約交付生命週期、時程進度與預算狀況</p>
                    </div>
                </div>
                <div>
                    <?php if(has_any_role(['admin', 'pm'])): ?>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                        <i class="bi bi-plus-circle me-1"></i> 建立新項目
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋項目名稱 / 客戶 / 描述...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">所有狀態</option>
                                <?php foreach ($status_options as $val => $opt): ?>
                                    <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="projects.php" class="btn btn-light border w-100 text-muted">清除</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

            <div class="row g-4">
                <?php foreach ($projects as $p): 
                    $progress = (int)$p['progress_percent'];
                    $stat = $status_options[$p['status']] ?? $status_options['planning'];
                    $svc_label = $service_options[$p['service_type']] ?? '其他服務';
                    $pm_avatar = mb_substr($p['pm_name'] ?? 'U', 0, 1, 'UTF-8');
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100 d-flex flex-column" style="transition: transform 0.2s; border-radius: 12px;">
                        <div class="card-body p-4 flex-grow-1 position-relative">
                            
                            <?php if(has_any_role(['admin', 'pm'])): ?>
                            <div class="position-absolute top-0 end-0 p-3">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border-0 text-muted shadow-none" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProjectModal<?= $p['id'] ?>"><i class="bi bi-pencil-square me-2 text-primary"></i>編輯項目</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="m-0 p-0" onsubmit="return confirm('確定要刪除「<?= htmlspecialchars($p['title']) ?>」嗎？
此動作無法復原！');">
                                                <input type="hidden" name="delete_project_id" value="<?= $p['id'] ?>">
                                                <button type="submit" name="delete_project" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>刪除項目</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex align-items-start mb-3 gap-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 small"><?= $svc_label ?></span>
                                <span class="badge bg-<?= $stat['color'] ?> bg-opacity-10 text-<?= $stat['color'] ?> px-2 py-1 small"><?= $stat['label'] ?></span>
                            </div>
                            
                            <h5 class="fw-bold text-slate-800 mt-2 mb-1 pe-4 lh-base" style="font-size: 1.15rem;">
                                <?= htmlspecialchars($p['title']) ?>
                            </h5>
                            
                            <p class="small text-muted mb-3"><i class="bi bi-building me-1"></i><?= htmlspecialchars($p['company_name'] ?? '未指定客戶') ?></p>
                            
                            <div class="text-slate-600 small mb-4" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2.8em;">
                                <?= htmlspecialchars($p['description'] ?: '沒有詳細說明...') ?>
                            </div>

                            <div class="d-flex align-items-center text-slate-500 mb-3" style="font-size: 0.8rem;">
                                <i class="bi bi-calendar-event me-2"></i>
                                <span><?= $p['start_date'] ? date('Y/m/d', strtotime($p['start_date'])) : '未定' ?></span>
                                <i class="bi bi-arrow-right mx-2 text-slate-300"></i>
                                <span class="<?= $p['end_date'] && strtotime($p['end_date']) < time() && $p['status'] != 'completed' ? 'text-danger fw-bold' : '' ?>">
                                    <?= $p['end_date'] ? date('Y/m/d', strtotime($p['end_date'])) : '未定' ?>
                                </span>
                            </div>
                            
                            <div class="mt-auto pt-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-slate-500 fw-semibold">研發進度</small>
                                    <small class="fw-bold <?= $progress == 100 ? 'text-success' : 'text-indigo' ?>"><?= $progress ?>%</small>
                                </div>
                                <div class="progress" style="height:6px; border-radius:3px; background-color: #f1f5f9;">
                                    <div class="progress-bar <?= $progress == 100 ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white border-top px-4 py-3 d-flex justify-content-between align-items-center rounded-bottom-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                                    <span class="fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($pm_avatar) ?></span>
                                </div>
                                <small class="text-slate-600 fw-medium"><?= htmlspecialchars($p['pm_name'] ?? '未指派 PM') ?></small>
                            </div>
                            <?php if(has_any_role(['admin', 'pm', 'finance'])): ?>
                            <div class="fw-bold text-slate-800 small">HK$ <?= number_format($p['budget'], 0) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if(has_any_role(['admin', 'pm'])): ?>
                <div class="modal fade" id="editProjectModal<?= $p['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <form method="POST">
                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </div>
                                        編輯項目詳情
                                    </h5>
                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <input type="hidden" name="edit_project" value="1">
                                    <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目名稱 *</label>
                                            <input type="text" name="title" class="form-control shadow-none fw-bold" value="<?= htmlspecialchars($p['title']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯客戶 *</label>
                                            <select name="client_id" class="form-select shadow-none" required>
                                                <?php foreach ($clients as $c): ?>
                                                    <option value="<?= $c['id'] ?>" <?= $p['client_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['company_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目經理 (PM)</label>
                                            <select name="assigned_pm_id" class="form-select shadow-none">
                                                <option value="">-- 未指派 --</option>
                                                <?php foreach ($pms as $m): ?>
                                                    <option value="<?= $m['id'] ?>" <?= $p['assigned_pm_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['full_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">進度狀態</label>
                                            <select name="status" class="form-select shadow-none">
                                                <?php foreach ($status_options as $val => $opt): ?>
                                                    <option value="<?= $val ?>" <?= $p['status'] === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">當前進度 (%)</label>
                                            <div class="input-group">
                                                <input type="number" min="0" max="100" name="progress_percent" class="form-control shadow-none border-end-0" value="<?= $p['progress_percent'] ?>">
                                                <span class="input-group-text bg-white text-muted">%</span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">合約預算 (HK$)</label>
                                            <input type="number" step="0.01" name="budget" class="form-control shadow-none" value="<?= $p['budget'] ?>">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">啟動日期</label>
                                            <input type="date" name="start_date" class="form-control shadow-none" value="<?= $p['start_date'] ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">預計交付日期</label>
                                            <input type="date" name="end_date" class="form-control shadow-none" value="<?= $p['end_date'] ?>">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">服務類型</label>
                                            <select name="service_type" class="form-select shadow-none">
                                                <?php foreach ($service_options as $val => $label): ?>
                                                    <option value="<?= $val ?>" <?= $p['service_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目說明</label>
                                            <textarea name="description" class="form-control shadow-none bg-light" rows="3"><?= htmlspecialchars($p['description']) ?></textarea>
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

                <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="text-center py-5 text-muted bg-white rounded-3 shadow-sm border border-light-subtle">
                        <i class="bi bi-folder-x fs-1 d-block mb-2 opacity-50"></i>
                        找不到符合條件的項目記錄
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-5">
                <nav>
                    <ul class="pagination shadow-sm">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <?php if(has_any_role(['admin', 'pm'])): ?>
        <div class="modal fade" id="createProjectModal" tabindex="-1">
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
                    <input type="hidden" name="create_project" value="1">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目名稱 *</label>
                            <input type="text" name="title" class="form-control shadow-none" placeholder="如：ERP 系統升級第二期" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">關聯客戶 *</label>
                            <select name="client_id" class="form-select shadow-none" required>
                                <option value="">請選擇客戶...</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">指派項目經理 (PM)</label>
                            <select name="assigned_pm_id" class="form-select shadow-none">
                                <option value="">-- 稍後分配 --</option>
                                <?php foreach ($pms as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">合約預算金額 (HK$)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">$</span>
                                <input type="number" step="0.01" name="budget" class="form-control shadow-none border-start-0 ps-0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">服務類型</label>
                            <select name="service_type" class="form-select shadow-none">
                                <?php foreach ($service_options as $val => $label): ?>
                                    <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">預計啟動日期</label>
                            <input type="date" name="start_date" class="form-control shadow-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">預計交付日期</label>
                            <input type="date" name="end_date" class="form-control shadow-none">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目範圍細節說明</label>
                            <textarea name="description" class="form-control shadow-none" rows="3" placeholder="簡述此專案的目標與交付物..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">確認建立項目</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>