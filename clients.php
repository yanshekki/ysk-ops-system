<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'finance', 'viewer']);

$success = $error = '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 處理 POST 請求 (新增、編輯、刪除)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 新增或更新客戶
    if (isset($_POST['add_client']) || isset($_POST['update_client'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $data = [
            'company_name' => trim($_POST['company_name']),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'active'
        ];

        // 處理使用者帳號
        if (!empty($username)) {
            $data['username'] = $username;
        }

        if (isset($_POST['add_client'])) {
            // 新增模式：檢查帳號重複
            $existing = null;
            if (!empty($username)) {
                $existing = db_fetch_one("SELECT id FROM clients WHERE username = ?", [$username]);
            }
            
            if ($existing) {
                $error = '新增失敗：客戶登入帳號已存在，請更換！';
            } else {
                if (!empty($password)) {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                db_insert('clients', $data);
                $success = '客戶新增成功！';
            }
        } else {
            // 更新模式
            $id = (int)$_POST['client_id'];
            $existing = null;
            if (!empty($username)) {
                $existing = db_fetch_one("SELECT id FROM clients WHERE username = ? AND id != ?", [$username, $id]);
            }

            if ($existing) {
                $error = '更新失敗：客戶登入帳號已與其他公司重複！';
            } else {
                if (!empty($password)) {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                db_update('clients', $data, 'id = ?', [$id]);
                $success = '客戶資料已更新！';
            }
        }
    }
    
    if (isset($_POST['delete_client'])) {
        db_delete('clients', 'id = ?', [$_POST['client_id']]);
        $success = '客戶已徹底刪除！';
    }
}

// 建立分頁與搜尋的 SQL 查詢
$count_sql = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
$count_params = [];

if ($search) {
    $count_sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR username LIKE ?)";
    $count_params[] = "%$search%";
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

// 獲取客戶資料
$sql = "SELECT * FROM clients WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
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

// 狀態標籤定義
$status_labels = [
    'active' => ['text' => '活躍客戶', 'color' => 'success', 'icon' => 'bi-check-circle'],
    'lead' => ['text' => '潛在客戶', 'color' => 'warning', 'icon' => 'bi-star'],
    'inactive' => ['text' => '非活躍', 'color' => 'secondary', 'icon' => 'bi-pause-circle']
];
?>
<?php $page_title = "客戶管理"; ?>
<?php include 'includes/header.php'; ?>

<div class="d-flex align-items-stretch" style="min-height: 100vh; width: 100%;">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 d-flex flex-column" style="background-color: #f8f9fa; min-width: 0;">
        
        <div class="p-3 p-md-4 flex-grow-1">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <button class="mobile-nav-toggle btn d-md-none me-2 p-1" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div>
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-buildings me-2 text-primary"></i> 客戶管理 (CRM)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">管理公司客戶檔案、聯絡資訊與 Client Portal 登入權限</p>
                    </div>
                </div>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i class="bi bi-plus-circle me-1"></i> 新增客戶
                </button>
            </div>
            
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label text-slate-500 fw-semibold small">關鍵字搜尋</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="公司名稱 / 帳號 / 聯絡人 / 電郵">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small">客戶狀態</label>
                            <select name="status" class="form-select shadow-none">
                                <option value="">全部狀態</option>
                                <?php foreach ($status_labels as $key => $s): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $s['text'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-primary w-100">搜尋</button>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="clients.php" class="btn btn-light w-100 border">清除</a>
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
                                    <th>公司名稱與帳號</th>
                                    <th>主要聯絡人</th>
                                    <th>聯絡方式</th>
                                    <th>狀態</th>
                                    <th>加入日期</th>
                                    <th width="140" class="text-end pe-4">操作</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($clients as $c): 
                                    $status_info = $status_labels[$c['status']] ?? $status_labels['inactive'];
                                    $avatar_char = mb_substr($c['company_name'] ?? 'C', 0, 1, 'UTF-8');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center py-1">
                                            <div class="bg-indigo bg-opacity-10 text-indigo rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:40px; height:40px; background-color:#e0e7ff; color:#4338ca;">
                                                <span class="fw-bold fs-5"><?= htmlspecialchars($avatar_char) ?></span>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-slate-800" style="font-size: 1.05rem;"><?= htmlspecialchars($c['company_name'] ?? '') ?></div>
                                                <?php if($c['username']): ?>
                                                    <div class="small text-primary fw-medium"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($c['username']) ?></div>
                                                <?php else: ?>
                                                    <div class="small text-muted fst-italic">未開通 Portal 登入</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-slate-700 fw-medium"><?= htmlspecialchars($c['contact_person'] ?: '未提供') ?></span>
                                    </td>
                                    <td>
                                        <div class="text-slate-700"><i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($c['email'] ?: '-') ?></div>
                                        <div class="small text-muted mt-1"><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($c['phone'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $status_info['color'] ?> bg-opacity-10 text-<?= $status_info['color'] ?> px-2 py-1">
                                            <i class="bi <?= $status_info['icon'] ?> me-1"></i><?= $status_info['text'] ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small"><?= date('Y-m-d', strtotime($c['created_at'])) ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-light border text-primary me-1" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>" title="編輯"><i class="bi bi-pencil-square"></i></button>
                                        
                                        <form method="POST" class="d-inline" onsubmit="return confirm('⚠️ 嚴重警告！\n\n刪除客戶將會連帶永久刪除該客戶名下的：\n- 所有項目 (Projects)\n- 所有任務 (Tasks)\n- 所有發票 (Invoices)\n\n建議在編輯中將狀態改為「非活躍」。\n\n確定要強制刪除嗎？')">
                                            <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="delete_client" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                
                                <div class="modal fade" id="editClientModal<?= $c['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                            <i class="bi bi-pencil-square fs-5"></i>
                                                        </div>
                                                        編輯客戶資料
                                                    </h5>
                                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="update_client" value="1">
                                                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                                    
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">公司名稱 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                                                <input type="text" name="company_name" class="form-control border-start-0 ps-0 shadow-none fw-bold" value="<?= htmlspecialchars($c['company_name'] ?? '') ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">客戶狀態 *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                                                <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                                                    <?php foreach ($status_labels as $key => $s): ?>
                                                                        <option value="<?= $key ?>" <?= $c['status'] == $key ? 'selected' : '' ?>><?= $s['text'] ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 mt-4 mb-2">
                                                            <h6 class="fw-bold text-slate-700 border-bottom pb-2"><i class="bi bi-shield-lock me-2 text-primary"></i>客戶門戶 (Portal) 登入設定</h6>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">登入帳號 (Username)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                                                <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none text-primary fw-medium" value="<?= htmlspecialchars($c['username'] ?? '') ?>" placeholder="設定客戶登入帳號">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">登入密碼 (留空代表不更改)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                                                <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="••••••••">
                                                            </div>
                                                        </div>

                                                        <div class="col-12 mt-4 mb-2">
                                                            <h6 class="fw-bold text-slate-700 border-bottom pb-2"><i class="bi bi-telephone-inbound me-2 text-primary"></i>一般聯絡資訊</h6>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">主要聯絡人</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                                                <input type="text" name="contact_person" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($c['contact_person'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">電郵地址</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                                                <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($c['email'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">電話號碼</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                                                <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">公司地址</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-geo-alt"></i></span>
                                                                <textarea name="address" class="form-control border-start-0 ps-0 shadow-none" rows="2"><?= htmlspecialchars($c['address'] ?? '') ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label text-slate-500 fw-semibold small mb-1">內部備註 (僅員工可見)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-card-text"></i></span>
                                                                <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none bg-light" rows="3"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>
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
                                <?php endforeach; ?>
                                
                                <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                        找不到符合條件的客戶
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
            
        <div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-building-add fs-5"></i>
                        </div>
                        新增客戶資料
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_client" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">公司名稱 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                <input type="text" name="company_name" class="form-control border-start-0 ps-0 shadow-none fw-bold" placeholder="例如：YSK Limited" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">客戶狀態 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-activity"></i></span>
                                <select name="status" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <?php foreach ($status_labels as $key => $s): ?>
                                        <option value="<?= $key ?>"><?= $s['text'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 mt-4 mb-2">
                            <h6 class="fw-bold text-slate-700 border-bottom pb-2"><i class="bi bi-shield-lock me-2 text-primary"></i>客戶門戶 (Portal) 登入設定</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">登入帳號 (Username)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-at"></i></span>
                                <input type="text" name="username" class="form-control border-start-0 ps-0 shadow-none text-primary fw-medium" placeholder="設定客戶專屬帳號">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">登入密碼</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" class="form-control border-start-0 ps-0 shadow-none" placeholder="設定初始密碼">
                            </div>
                        </div>

                        <div class="col-12 mt-4 mb-2">
                            <h6 class="fw-bold text-slate-700 border-bottom pb-2"><i class="bi bi-telephone-inbound me-2 text-primary"></i>一般聯絡資訊</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">主要聯絡人</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" name="contact_person" class="form-control border-start-0 ps-0 shadow-none" placeholder="聯絡人姓名">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">電郵地址</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control border-start-0 ps-0 shadow-none" placeholder="contact@example.com">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">電話號碼</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="phone" class="form-control border-start-0 ps-0 shadow-none" placeholder="+852 1234 5678">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">公司地址</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-geo-alt"></i></span>
                                <textarea name="address" class="form-control border-start-0 ps-0 shadow-none" rows="2" placeholder="詳細地址..."></textarea>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">內部備註 (僅員工可見)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-card-text"></i></span>
                                <textarea name="notes" class="form-control border-start-0 ps-0 shadow-none bg-light" rows="3" placeholder="添加有關此客戶的備註..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 新增客戶</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>