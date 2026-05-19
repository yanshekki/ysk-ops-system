<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

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
            log_activity($_SESSION['user_id'], 'create', 'clients', null, $data['company_name']);
        } else {
            db_update('clients', $data, 'id = ?', [$id]);
            $success = '客戶資料已更新！';
            log_activity($_SESSION['user_id'], 'update', 'clients', $id);
        }
    }
    
    if (isset($_POST['delete_client'])) {
        $client_id = $_POST['client_id'];
        db_delete('clients', 'id = ?', [$client_id]);
        $success = '客戶已刪除！';
        log_activity($_SESSION['user_id'], 'delete', 'clients', $client_id);
    }
}

// Fetch clients
$clients = db_fetch_all("SELECT * FROM clients ORDER BY created_at DESC");

// If editing
$edit_client = null;
if ($action === 'edit' && $id) {
    $edit_client = db_fetch_one("SELECT * FROM clients WHERE id = ?", [$id]);
}
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客戶管理 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { background: #f8f9fa; } .table th { background:#f1f3f5; }</style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar (same as index) -->
    <div class="sidebar p-3 text-white" style="width: 240px; min-height: 100vh; background: #212529; flex-shrink: 0;">
        <div class="d-flex align-items-center mb-4 px-2">
            <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
            <span class="fs-4 fw-bold">YSK Ops</span>
        </div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="clients.php" class="nav-link active mb-1"><i class="bi bi-people me-2"></i> 客戶管理</a>
            <a href="projects.php" class="nav-link mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <a href="tasks.php" class="nav-link mb-1"><i class="bi bi-list-task me-2"></i> 任務追蹤</a>
            <a href="invoices.php" class="nav-link mb-1"><i class="bi bi-receipt me-2"></i> 發票管理</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i> 客戶管理</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="bi bi-plus-circle me-1"></i> 新增客戶
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
                            <th>公司名稱</th>
                            <th>聯絡人</th>
                            <th>電郵 / 電話</th>
                            <th>狀態</th>
                            <th>建立日期</th>
                            <th width="120">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?= $c['id'] ?></td>
                            <td><strong><?= htmlspecialchars($c['company_name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['contact_person'] ?: '-') ?></td>
                            <td>
                                <?= htmlspecialchars($c['email'] ?: '-') ?><br>
                                <small class="text-muted"><?= htmlspecialchars($c['phone'] ?: '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : ($c['status'] === 'lead' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                            <td>
                                <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>">編輯</a>
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
                                            <h5 class="modal-title">編輯客戶：<?= htmlspecialchars($c['company_name']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="update_client" value="1">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">公司名稱 *</label>
                                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($c['company_name']) ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">聯絡人</label>
                                                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($c['contact_person']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">電郵</label>
                                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($c['email']) ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">電話</label>
                                                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($c['phone']) ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">地址</label>
                                                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($c['address']) ?></textarea>
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
                                                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($c['notes']) ?></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>