<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_client']) || isset($_POST['update_client'])) {
        $data = [
            'company_name' => trim($_POST['company_name']),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];
        
        if (isset($_POST['add_client'])) {
            db_insert('clients', $data);
            $success = '客戶新增成功！';
        } else {
            $id = $_POST['client_id'];
            db_update('clients', $data, 'id = ?', [$id]);
            $success = '客戶資料已更新！';
        }
    }
    
    if (isset($_POST['delete_client'])) {
        db_delete('clients', 'id = ?', [$_POST['client_id']]);
        $success = '客戶已刪除！';
    }
}

// Build query for count
$count_sql = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
$count_params = [];

if ($search) {
    $count_sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

if ($status_filter) {
    $count_sql .= " AND status = ?";
    $count_params[] = $status_filter;
}

$total = db_fetch_one($count_sql, $count_params)['total'] ?? 0;
$total_pages = ceil($total / $per_page);

// Fetch clients with pagination
$sql = "SELECT * FROM clients WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$clients = db_fetch_all($sql, $params);
?>
<?php $page_title = "客戶管理"; ?>
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
            <h2><i class="bi bi-people me-2"></i> 客戶管理 (CRM)</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="bi bi-plus-circle me-1"></i> 新增客戶
            </button>
        </div>
        
        <!-- Search and Filter -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">搜尋</label>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="公司名稱 / 聯絡人 / 電郵">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">狀態</label>
                        <select name="status" class="form-select">
                            <option value="">全部</option>
                            <option value="active" <?= $status_filter=='active'?'selected':'' ?>>活躍</option>
                            <option value="lead" <?= $status_filter=='lead'?'selected':'' ?>>潛在客戶</option>
                            <option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>非活躍</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">搜尋</button>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="clients.php" class="btn btn-outline-secondary">清除篩選</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>公司名稱</th>
                            <th>聯絡人</th>
                            <th>電郵 / 電話</th>
                            <th>狀態</th>
                            <th>建立日期</th>
                            <th width="140">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['company_name'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($c['contact_person'] ?? '-') ?></td>
                            <td>
                                <?= htmlspecialchars($c['email'] ?? '-') ?><br>
                                <small class="text-muted"><?= htmlspecialchars($c['phone'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $c['status']==='active'?'success':($c['status']==='lead'?'warning':'secondary') ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>">編輯</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此客戶？')">
                                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="delete_client" class="btn btn-sm btn-outline-danger">刪除</button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Edit Modal -->
                        <div class="modal fade" id="editClientModal<?= $c['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">編輯客戶</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="update_client" value="1">
                                            <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">公司名稱 *</label>
                                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($c['company_name'] ?? '') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">聯絡人</label>
                                                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($c['contact_person'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">電郵</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($c['email'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">電話</label>
                                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">地址</label>
                                                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($c['address'] ?? '') ?></textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">狀態</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" <?= $c['status']=='active'?'selected':'' ?>>活躍</option>
                                                        <option value="lead" <?= $c['status']=='lead'?'selected':'' ?>>潛在客戶</option>
                                                        <option value="inactive" <?= $c['status']=='inactive'?'selected':'' ?>>非活躍</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">備註</label>
                                                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                            <button type="submit" class="btn btn-primary">儲存變更</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
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
                        <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">上一頁</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>">下一頁</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增客戶</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_client" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">公司名稱 *</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">聯絡人</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電郵</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電話</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">地址</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">備註</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">新增客戶</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>