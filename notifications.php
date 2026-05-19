<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

// 處理發送通知 (模擬 WhatsApp / Email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $client_id = (int)$_POST['client_id'];
    $notif_type = $_POST['notif_type'] ?? 'whatsapp';
    $message = trim($_POST['message']);
    
    $client = db_fetch_one("SELECT company_name, phone, email FROM clients WHERE id = ?", [$client_id]);
    
    if ($client) {
        // 驗證是否有對應的聯絡方式
        if ($notif_type === 'whatsapp' && empty($client['phone'])) {
            $error = '發送失敗：該客戶尚未登記電話號碼，無法發送 WhatsApp。';
        } elseif ($notif_type === 'email' && empty($client['email'])) {
            $error = '發送失敗：該客戶尚未登記電郵地址，無法發送 Email。';
        } else {
            // 寫入資料庫
            db_insert('notifications', [
                'client_id' => $client_id,
                'type' => $notif_type,
                'message' => $message,
                'sent_by' => $_SESSION['user_id'],
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            
            $type_label = $notif_type === 'whatsapp' ? 'WhatsApp' : '電子郵件';
            $success = "已成功向 " . htmlspecialchars($client['company_name'] ?? '') . " 發送 {$type_label} 通知！（模擬測試）";
        }
    } else {
        $error = '無效的客戶資料。';
    }
}

// 接收搜尋與分頁參數
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// 構建查詢條件
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(c.company_name LIKE ? OR n.message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type_filter) {
    $where_clauses[] = "n.type = ?";
    $params[] = $type_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM notifications n LEFT JOIN clients c ON n.client_id = c.id WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取歷史記錄
$recent_notifications = db_fetch_all("
    SELECT n.*, c.company_name, u.full_name as sender_name 
    FROM notifications n 
    LEFT JOIN clients c ON n.client_id = c.id 
    LEFT JOIN users u ON n.sent_by = u.id
    WHERE $where_sql
    ORDER BY n.sent_at DESC 
    LIMIT $per_page OFFSET $offset
", $params);

// 獲取活躍客戶名單供發送選單使用
$clients = db_fetch_all("SELECT id, company_name, phone, email FROM clients WHERE status = 'active' ORDER BY company_name");
?>
<?php $page_title = "通知中心"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-bell me-2 text-primary"></i> 通知中心</h2>
                <p class="text-muted mb-0 d-none d-md-block">透過 WhatsApp API 或 Email 發送專案進度與付款提醒</p>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>
        
        <div class="row g-4 mt-1">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold text-slate-800 mb-0 d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                <i class="bi bi-send-fill"></i>
                            </div>
                            發送新通知
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="send_notification" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label text-slate-500 fw-semibold small mb-1">選擇目標客戶 *</label>
                                <select name="client_id" class="form-select shadow-none" required>
                                    <option value="">請選擇客戶...</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['company_name'] ?? '') ?> 
                                            <?= empty($c['phone']) ? '(無電話)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label text-slate-500 fw-semibold small mb-1">發送渠道 *</label>
                                <select name="notif_type" class="form-select shadow-none" id="notifType" required>
                                    <option value="whatsapp">WhatsApp 訊息</option>
                                    <option value="email">電子郵件 (Email)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-slate-500 fw-semibold small mb-1">快捷範本 (可選)</label>
                                <select class="form-select shadow-none" id="templateSelect" onchange="applyTemplate()">
                                    <option value="">-- 手動輸入內容 --</option>
                                    <option value="progress">📊 專案進度更新</option>
                                    <option value="payment">💰 逾期付款提醒</option>
                                    <option value="meeting">📅 預約會議提醒</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label text-slate-500 fw-semibold small mb-1">消息內容 *</label>
                                <textarea name="message" id="messageBox" class="form-control shadow-none" rows="5" required placeholder="請輸入您想發送的訊息內容..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                                <i class="bi bi-rocket-takeoff me-1"></i> 立即發送
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>目前為模擬發送模式，未來可正式串接 WhatsApp Business API 及 SendGrid。</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋客戶名稱或訊息內容...">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="type" class="form-select shadow-none" onchange="this.form.submit()">
                                    <option value="">全部渠道</option>
                                    <option value="whatsapp" <?= $type_filter === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
                                    <option value="email" <?= $type_filter === 'email' ? 'selected' : '' ?>>電子郵件</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="submit" class="btn btn-outline-primary w-100 d-md-none mb-2">搜尋</button>
                                <a href="notifications.php" class="btn btn-light border text-muted w-100">清除篩選</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom pt-3 pb-2 px-4">
                        <h6 class="fw-bold text-slate-700 mb-0">發送記錄歷史</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_notifications)): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                找不到任何符合條件的通知記錄。
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_notifications as $n): 
                                    $is_wa = ($n['type'] == 'whatsapp');
                                    $badge_class = $is_wa ? 'success' : 'info';
                                    $icon_class = $is_wa ? 'whatsapp' : 'envelope';
                                ?>
                                <div class="list-group-item p-4 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-<?= $badge_class ?> bg-opacity-10 text-<?= $badge_class ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                                <i class="bi bi-<?= $icon_class ?> fs-5"></i>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold text-slate-800 mb-0"><?= htmlspecialchars($n['company_name'] ?? '已刪除客戶') ?></h6>
                                                <small class="text-muted"><i class="bi bi-person me-1"></i>操作人: <?= htmlspecialchars($n['sender_name'] ?? '系統') ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= $badge_class ?> bg-opacity-10 text-<?= $badge_class ?> px-2 py-1 mb-1">
                                                <?= $is_wa ? 'WhatsApp' : 'Email' ?>
                                            </span>
                                            <div class="small text-slate-500" style="font-size: 0.75rem;"><?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?></div>
                                        </div>
                                    </div>
                                    <div class="bg-light p-3 rounded-3 mt-3 text-slate-700 small" style="white-space: pre-wrap;"><?= htmlspecialchars($n['message'] ?? '') ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav>
                        <ul class="pagination shadow-sm">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>">上一頁</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>">下一頁</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function applyTemplate() {
    const templates = {
        'progress': '【YSK 專案進度更新】\n您好，您委託的項目有最新進度更新！\n\n請登入客戶自助門戶查看詳細開發日誌及測試環境連結：\n👉 https://ysk.hk/portal\n\n如有任何問題，歡迎隨時聯絡我們。',
        'payment': '【YSK 付款提醒】\n您好，系統顯示您的賬單（發票）即將到期或已逾期。\n\n為免影響服務進度，請盡快透過以下連結使用 Stripe 或信用卡進行線上付款：\n👉 https://ysk.hk/portal/billing\n\n如已付款，請忽略此訊息。謝謝您的合作！',
        'meeting': '【YSK 會議提醒】\n您好，這是一則溫馨的會議提醒。\n我們即將在稍後進行線上/實體會議，請您準時出席。\n\n如需更改時間，請盡早通知負責的專案經理。謝謝！'
    };
    
    const select = document.getElementById('templateSelect');
    const msgBox = document.getElementById('messageBox');
    const selectedVal = select.value;
    
    if (selectedVal && templates[selectedVal]) {
        msgBox.value = templates[selectedVal];
    } else {
        msgBox.value = '';
    }
}

// 動態更新渠道按鈕外觀
document.getElementById('notifType').addEventListener('change', function() {
    const btn = document.querySelector('button[type="submit"]');
    const icon = btn.querySelector('i');
    
    if (this.value === 'whatsapp') {
        btn.className = 'btn btn-success w-100 fw-bold shadow-sm py-2';
        icon.className = 'bi bi-whatsapp me-1';
    } else {
        btn.className = 'btn btn-primary w-100 fw-bold shadow-sm py-2';
        icon.className = 'bi bi-envelope-fill me-1';
    }
});
</script>

<?php include 'includes/footer.php'; ?>