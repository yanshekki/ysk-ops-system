<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role([]);

// 權限檢查：只有 admin 可以進入此頁面
if (!has_role('admin')) {
    header('Location: index.php');
    exit;
}

$success = $error = '';

// 接收搜尋與分頁參數
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ==============================================
// 處理表單提交 (新增、編輯、刪除)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 處理新增用戶
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // 檢查用戶名或電郵是否已存在
        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            $error = '新增失敗：用戶名或電郵已經存在，請使用其他名稱！';
        } else {
            $data = [
                'username' => $username,
                'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'full_name' => trim($_POST['full_name']),
                'email' => $email,
                'role' => $_POST['role'],
                'phone' => trim($_POST['phone'] ?? ''),
                'is_active' => 1
            ];
            db_insert('users', $data);
            $success = '團隊用戶已成功建立！';
        }
    }
    
    // 2. 處理編輯用戶
    elseif (isset($_POST['edit_user'])) {
        $user_id = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // 檢查是否有撞名 (排除自己)
        $existing = db_fetch_one("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?", [$username, $email, $user_id]);
        if ($existing) {
            $error = '更新失敗：用戶名或電郵已與其他帳號重複！';
        } else {
            $data = [
                'username' => $username,
                'full_name' => trim($_POST['full_name']),
                'email' => $email,
                'role' => $_POST['role'],
                'phone' => trim($_POST['phone'] ?? ''),
                // 自己不能停用自己
                'is_active' => ($user_id == $_SESSION['user_id']) ? 1 : (isset($_POST['is_active']) ? 1 : 0)
            ];
            
            // 如果有填寫新密碼，才更新密碼
            if (!empty($_POST['password'])) {
                $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            db_update('users', $data, 'id = ?', [$user_id]);
            $success = '用戶資料已成功更新！';
        }
    }
    
    // 3. 處理刪除用戶
    elseif (isset($_POST['delete_user'])) {
        $delete_id = (int)$_POST['delete_user_id'];
        if ($delete_id == $_SESSION['user_id']) {
            $error = '安全限制：你無法刪除自己正在使用的管理員帳號！';
        } else {
            db_delete('users', 'id = ?', [$delete_id]);
            $success = '用戶已從系統中徹底刪除！';
        }
    }
}

// ==============================================
// 建立分頁與搜尋的 SQL 查詢
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 📊 計算頂部 KPI 看板數據
$stats_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN role IN ('admin', 'pm') THEN 1 ELSE 0 END) as management_count
              FROM users";
$stats = db_fetch_one($stats_sql);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM users WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取當前頁資料
$users = db_fetch_all("SELECT * FROM users WHERE $where_sql ORDER BY is_active DESC, created_at DESC LIMIT $per_page OFFSET $offset", $params);

// 定義角色標籤與視覺 (配合 SaaS 顏色)
$roles = [
    'admin' => ['label' => '系統管理員 (Admin)', 'short' => 'Admin', 'color' => 'danger', 'icon' => 'bi-shield-lock-fill'],
    'pm' => ['label' => '項目經理 (PM)', 'short' => 'PM', 'color' => 'primary', 'icon' => 'bi-person-badge-fill'],
    'developer' => ['label' => '開發人員 (Dev)', 'short' => 'Dev', 'color' => 'success', 'icon' => 'bi-code-square'],
    'finance' => ['label' => '財務人員 (Finance)', 'short' => 'Finance', 'color' => 'warning', 'icon' => 'bi-calculator-fill'],
    'viewer' => ['label' => '查看者 (Viewer)', 'short' => 'Viewer', 'color' => 'secondary', 'icon' => 'bi-eye-fill']
];
?>
<?php $page_title = "團隊用戶管理"; ?>
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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-people-fill me-2 text-primary"></i> 團隊用戶管理 (Users)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">分配系統操作權限，管理團隊成員資料與登入狀態</p>
                    </div>
                </div>
                <div>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-1"></i> 新增團隊用戶
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6366f1 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">系統團隊總人數</small>
                                <h3 class="fw-bold mb-0 text-slate-800"><?= (int)$stats['total_users'] ?> <span class="fs-6 text-muted font-normal">人</span></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3"><i class="bi bi-people fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">當前活躍可登入</small>
                                <h3 class="fw-bold mb-0 text-success"><?= (int)$stats['active_users'] ?> <span class="fs-6 text-muted font-normal">帳號</span></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3"><i class="bi bi-person-check fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">管理層 (Admin & PM)</small>
                                <h3 class="fw-bold mb-0 text-warning"><?= (int)$stats['management_count'] ?> <span class="fs-6 text-muted font-normal">人</span></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3"><i class="bi bi-shield-lock fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋用戶名 / 真實姓名 / 電郵地址...">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-funnel"></i></span>
                                <select name="role" class="form-select border-start-0 ps-0 shadow-none" onchange="this.form.submit()">
                                    <option value="">全部權限角色</option>
                                    <?php foreach ($roles as $key => $r): ?>
                                        <option value="<?= $key ?>" <?= $role_filter === $key ? 'selected' : '' ?>><?= $r['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 text-end d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1 fw-medium">篩選</button>
                            <a href="users.php" class="btn btn-light border text-muted flex-grow-1" title="清除篩選"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div><?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-slate-600">
                                <tr>
                                    <th class="ps-4 py-3">團隊成員資料</th>
                                    <th class="py-3">聯絡方式</th>
                                    <th class="py-3">角色與權限</th>
                                    <th class="py-3 text-center">帳號狀態</th>
                                    <th class="py-3 text-center">加入日期</th>
                                    <th class="text-end pe-4 py-3" width="140">操作</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($users as $u): 
                                    $r_info = $roles[$u['role']] ?? ['label' => $u['role'], 'short' => $u['role'], 'color' => 'secondary', 'icon' => 'bi-person'];
                                    $avatar_char = mb_substr($u['full_name'] ?? $u['username'], 0, 1, 'UTF-8');
                                ?>
                                <tr class="<?= $u['is_active'] ? '' : 'bg-light opacity-75' ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-<?= $r_info['color'] ?> bg-opacity-10 text-<?= $r_info['color'] ?> rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                                <span class="fw-bold fs-5"><?= htmlspecialchars(strtoupper($avatar_char)) ?></span>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-slate-800" style="font-size: 1.05rem;"><?= htmlspecialchars($u['full_name'] ?? '') ?></div>
                                                <div class="small text-muted fw-medium">@<?= htmlspecialchars($u['username'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-slate-700 fw-medium small"><i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                        <div class="small text-muted mt-1"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($u['phone'] ?: '未提供') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $r_info['color'] ?> bg-opacity-10 text-<?= $r_info['color'] ?> px-2 py-1 border border-<?= $r_info['color'] ?> border-opacity-25" title="<?= $r_info['label'] ?>">
                                            <i class="bi <?= $r_info['icon'] ?> me-1"></i><?= $r_info['short'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>活躍登入</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1"><i class="bi bi-dash-circle-fill me-1"></i>已停用</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small text-center"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="編輯用戶資料">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        
                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('警告：徹底刪除用戶可能會影響歷史工時、任務等關聯數據。\n強烈建議使用「編輯」將用戶設為「已停用」以保留數據。\n\n確定要強制徹底刪除此用戶嗎？')">
                                            <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                        </form>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-light border text-muted disabled" title="無法刪除自己"><i class="bi bi-lock"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                            <i class="bi bi-person-gear fs-5"></i>
                                                        </div>
                                                        編輯與調整用戶權限
                                                    </h5>
                                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="edit_user" value="1">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">系統登入帳號 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                                                <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none fw-bold" value="<?= htmlspecialchars($u['username'] ?? '') ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">真實姓名 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                                                <input type="text" name="full_name" class="form-control border-start-0 ps-0 shadow-none fw-bold text-slate-800" value="<?= htmlspecialchars($u['full_name'] ?? '') ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">聯絡電郵地址 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                                                <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">系統操作權限 (Role) *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                                                                <select name="role" class="form-select border-start-0 ps-0 shadow-none text-primary fw-semibold" required>
                                                                    <?php foreach ($roles as $key => $r): ?>
                                                                        <option value="<?= $key ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= $r['label'] ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">聯絡電話</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                                                <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" placeholder="例如: +852 98765432">
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">重設密碼 (如不變更請留空)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                                                <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="••••••••">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-12 mt-3 bg-light rounded-3 p-3 border border-light-subtle">
                                                            <div class="form-check form-switch mb-0 d-flex align-items-center justify-content-between">
                                                                <div>
                                                                    <label class="form-check-label fw-bold text-slate-700" for="activeSwitch<?= $u['id'] ?>">允許此帳號登入系統 (活躍狀態)</label>
                                                                    <div class="text-muted" style="font-size: 0.75rem;">關閉此開關，該名員工將被阻擋登入，但歷史數據依然保留。</div>
                                                                </div>
                                                                <input class="form-check-input ms-0 border-secondary" type="checkbox" name="is_active" id="activeSwitch<?= $u['id'] ?>" value="1" <?= $u['is_active'] ? 'checked' : '' ?> <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?> style="width: 3em; height: 1.5em; cursor: pointer;">
                                                            </div>
                                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                                <div class="text-danger mt-2 fw-medium d-flex align-items-center" style="font-size: 0.75rem;">
                                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> 您無法停用自己目前正在登入使用的管理員帳號。
                                                                </div>
                                                            <?php endif; ?>
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
                                
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-search fs-1 d-block mb-3 opacity-25"></i>
                                        找不到符合條件的團隊用戶
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
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-person-plus-fill fs-5"></i>
                        </div>
                        建立新團隊用戶
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_user" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">系統登入帳號 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none" placeholder="例如: ysk_jason" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">真實姓名 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" name="full_name" class="form-control border-start-0 ps-0 shadow-none" placeholder="例如: 陳大文 Jason" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">聯絡電郵地址 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" placeholder="name@ysk.hk" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">設定初始密碼 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="請設定最少 8 碼" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">系統操作權限 (Role) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                                <select name="role" class="form-select border-start-0 ps-0 shadow-none fw-semibold text-primary" required>
                                    <?php foreach ($roles as $key => $r): ?>
                                        <option value="<?= $key ?>"><?= $r['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">聯絡電話</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" placeholder="例如: +852 9876 5432">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 正式建立用戶</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>