<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// 權限檢查：只有 admin 可以進入此頁面
if (!has_role('admin')) {
    header('Location: index.php');
    exit;
}

$success = $error = '';

// 接收搜尋與分頁參數
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 處理 POST 請求 (新增、編輯、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 處理新增用戶
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // 檢查用戶名或電郵是否已存在
        $existing = db_fetch_one("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            $error = '用戶名或電郵已存在！';
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
            $success = '用戶新增成功！';
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
                'is_active' => isset($_POST['is_active']) ? 1 : 0
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
            $error = '你無法刪除自己的帳號！';
        } else {
            db_delete('users', 'id = ?', [$delete_id]);
            $success = '用戶已徹底刪除！';
        }
    }
}

// 建立分頁與搜尋的 SQL 查詢
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

// 計算總筆數
$total_users = db_fetch_one("SELECT COUNT(*) as total FROM users WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_users / $per_page);

// 獲取當前頁資料
$users = db_fetch_all("SELECT * FROM users WHERE $where_sql ORDER BY is_active DESC, created_at DESC LIMIT $per_page OFFSET $offset", $params);

// 定義角色標籤 (配合 SaaS 顏色)
$roles = [
    'admin' => ['label' => '管理員', 'color' => 'danger'],
    'pm' => ['label' => '項目經理', 'color' => 'primary'],
    'developer' => ['label' => '開發人員', 'color' => 'success'],
    'finance' => ['label' => '財務', 'color' => 'warning'],
    'viewer' => ['label' => '查看者', 'color' => 'secondary']
];
?>
<?php $page_title = "用戶管理"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-people me-2 text-primary"></i> 用戶管理</h2>
                <p class="text-muted mb-0 d-none d-md-block">管理系統操作人員及權限分配</p>
            </div>
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-1"></i> 新增用戶
            </button>
        </div>
        
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="用戶名 / 姓名 / 電郵">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small">角色篩選</label>
                        <select name="role" class="form-select shadow-none">
                            <option value="">全部角色</option>
                            <?php foreach ($roles as $key => $r): ?>
                                <option value="<?= $key ?>" <?= $role_filter === $key ? 'selected' : '' ?>><?= $r['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">搜尋</button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="users.php" class="btn btn-light w-100 border">清除</a>
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
                                <th>用戶</th>
                                <th>聯絡資料</th>
                                <th>角色權限</th>
                                <th>狀態</th>
                                <th>加入日期</th>
                                <th width="140" class="text-end pe-4">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): 
                                $r_info = $roles[$u['role']] ?? ['label' => $u['role'], 'color' => 'secondary'];
                            ?>
                            <tr class="<?= $u['is_active'] ? '' : 'bg-light' ?>">
                                <td class="text-center text-muted"><?= $u['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold fs-5"><?= strtoupper(substr($u['username'] ?? 'U', 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-800"><?= htmlspecialchars($u['full_name'] ?? '') ?></div>
                                            <div class="small text-muted">@<?= htmlspecialchars($u['username'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-slate-700"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($u['phone'] ?: '未提供') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $r_info['color'] ?> bg-opacity-10 text-<?= $r_info['color'] ?> px-2 py-1">
                                        <?= $r_info['label'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i>活躍</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-dash-circle me-1"></i>已停用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                                    
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('警告：刪除用戶可能會影響關聯的工時記錄。\n強烈建議使用「編輯」將用戶設為「已停用」。\n\n確定要強制徹底刪除此用戶嗎？')">
                                        <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash3"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-md modal-dialog-centered">
                                    <div class="modal-content border-0 shadow-lg">
                                        <form method="POST">
                                            <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                        <i class="bi bi-pencil-square fs-5"></i>
                                                    </div>
                                                    編輯用戶資料
                                                </h5>
                                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <input type="hidden" name="edit_user" value="1">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">用戶名 (登入用) *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                                            <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['username'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">真實姓名 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                                            <input type="text" name="full_name" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['full_name'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">電郵地址 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                                            <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">系統權限角色 *</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                                                            <select name="role" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                <?php foreach ($roles as $key => $r): ?>
                                                                    <option value="<?= $key ?>" <?= $u['role'] === $key ? 'selected' : '' ?>><?= $r['label'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">電話號碼</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                                            <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($u['phone'] ?? '') ?>">
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label text-slate-500 fw-semibold small mb-1">安全重設密碼 (如不變更請留空)</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                                            <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="••••••••">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-12 mt-3 bg-light rounded-3 p-3 border border-light-subtle">
                                                        <div class="form-check form-switch mb-0 d-flex align-items-center justify-content-between">
                                                            <div>
                                                                <label class="form-check-label fw-bold text-slate-700" for="activeSwitch<?= $u['id'] ?>">帳號活躍狀態</label>
                                                                <div class="text-muted" style="font-size: 0.75rem;">關閉停用後，該名員工將無法登入系統</div>
                                                            </div>
                                                            <input class="form-check-input ms-0" type="checkbox" name="is_active" id="activeSwitch<?= $u['id'] ?>" value="1" <?= $u['is_active'] ? 'checked' : '' ?> <?= $u['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?> style="width: 2.5em; height: 1.25em; cursor: pointer;">
                                                        </div>
                                                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                            <div class="text-danger mt-2 fw-medium d-flex align-items-center" style="font-size: 0.75rem;">
                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> 你不能停用自己目前正在登入使用的管理員帳號。
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
                                    <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的用戶
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

    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
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
                            <label class="form-label text-slate-500 fw-semibold small mb-1">用戶名 (登入用) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none" placeholder="例如: admin" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">真實姓名 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" name="full_name" class="form-control border-start-0 ps-0 shadow-none" placeholder="例如: 陳大文" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">電郵地址 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" placeholder="name@ysk.hk" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">初始密碼 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="••••••••" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">系統權限角色 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-shield-lock"></i></span>
                                <select name="role" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <?php foreach ($roles as $key => $r): ?>
                                        <option value="<?= $key ?>"><?= $r['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">電話號碼</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" placeholder="+852 1234 5678">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 建立用戶</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>