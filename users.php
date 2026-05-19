<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

if (!has_role('admin')) {
    header('Location: index.php');
    exit;
}

$success = $error = '';

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $data = [
        'username' => trim($_POST['username']),
        'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'full_name' => trim($_POST['full_name']),
        'email' => trim($_POST['email']),
        'role' => $_POST['role'],
        'phone' => trim($_POST['phone'] ?? '')
    ];
    
    // Check if username or email exists
    $existing = db_fetch_one("SELECT id FROM users WHERE username = ? OR email = ?", [$_POST['username'], $_POST['email']]);
    if ($existing) {
        $error = '用戶名或電郵已存在！';
    } else {
        db_insert('users', $data);
        $success = '用戶新增成功！';
    }
}

// Fetch all users
$users = db_fetch_all("SELECT * FROM users ORDER BY created_at DESC");

$roles = ['admin' => '管理員', 'pm' => '項目經理', 'developer' => '開發人員', 'finance' => '財務', 'viewer' => '查看者'];
?>
<?php $page_title = "用戶管理"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Unified Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people-fill me-2"></i> 用戶管理</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-circle me-1"></i> 新增用戶
            </button>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>用戶名</th>
                            <th>姓名</th>
                            <th>電郵</th>
                            <th>角色</th>
                            <th>電話</th>
                            <th>建立日期</th>
                            <th width="100">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="badge bg-primary"><?= $roles[$u['role']] ?? $u['role'] ?></span></td>
                            <td><?= htmlspecialchars($u['phone'] ?: '-') ?></td>
                            <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <a href="#" class="btn btn-sm btn-outline-primary">編輯</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此用戶？')">
                                    <input type="hidden" name="delete_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">自己</span>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增用戶</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_user" value="1">
                    <div class="mb-3">
                        <label class="form-label">用戶名 *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">姓名 *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">電郵 *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">密碼 *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">角色 *</label>
                        <select name="role" class="form-select" required>
                            <?php foreach ($roles as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">電話</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">新增用戶</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>