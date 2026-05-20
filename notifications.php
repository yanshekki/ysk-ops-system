<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'finance']);

$success = $error = '';

// ==============================================
// 處理表單提交 (發送通知)
// ==============================================
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
            $success = "已成功向 「" . htmlspecialchars($client['company_name'] ?? '') . "」 發送 {$type_label} 通知！（目前為模擬測試）";
        }
    } else {
        $error = '無效的客戶資料。';
    }
}

// ==============================================
// 構建查詢條件與獲取資料
// ==============================================
$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

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

// 📊 KPI 統計數據 (總發送量)
$stats_sql = "SELECT 
                COUNT(*) as total_sent,
                SUM(CASE WHEN type = 'whatsapp' THEN 1 ELSE 0 END) as wa_sent,
                SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as email_sent
              FROM notifications";
$stats = db_fetch_one($stats_sql);

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

// ==============================================
// 視圖渲染開始 (套用黃金排版準則)
// ==============================================
$page_title = "通知中心 Notifications";
include 'includes/header.php';
?>

<!-- 💡 開啟結構 1：最外層 Flex 容器 (撐滿全螢幕) -->
<div class="d-flex align-items-stretch" style="min-height: 100vh; width: 100%;">
    
    <!-- 引入獨立導航側邊欄 -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- 💡 開啟結構 2：右側垂直主面板 -->
    <div class="flex-grow-1 d-flex flex-column" style="background-color: #f8f9fa; min-width: 0;">
        
        <!-- 💡 開啟結構 3：放網頁內容嘅 Padded 容器 -->
        <div class="p-3 p-md-4 flex-grow-1">
            
            <!-- Page Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div class="d-flex align-items-center">
                    <button class="mobile-nav-toggle btn d-md-none me-2 p-1" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div>
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-bell-fill me-2 text-primary"></i> 通知中心 (Notifications)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">透過 WhatsApp API 或 Email 批量發送專案進度與付款提醒</p>
                    </div>
                </div>
            </div>

            <!-- KPI Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6366f1 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">歷史發送總量</small>
                                <h3 class="fw-bold mb-0 text-slate-800"><?= (int)$stats['total_sent'] ?> <span class="fs-6 text-muted font-normal">則</span></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle p-3"><i class="bi bi-send fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">WhatsApp 發送量</small>
                                <h3 class="fw-bold mb-0 text-success"><?= (int)$stats['wa_sent'] ?> <span class="fs-6 text-muted font-normal">則</span></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle p-3"><i class="bi bi-whatsapp fs-4"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #0ea5e9 !important;">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted d-block fw-semibold mb-1">Email 發送量</small>
                                <h3 class="fw-bold mb-0 text-info"><?= (int)$stats['email_sent'] ?> <span class="fs-6 text-muted font-normal">封</span></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 text-info rounded-circle p-3"><i class="bi bi-envelope-at fs-4"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div><?php endif; ?>
            
            <div class="row g-4 mt-1">
                <!-- 左側：發送通知表單 -->
                <div class="col-lg-5 col-xl-4">
                    <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
                        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                            <h5 class="fw-bold text-slate-800 mb-0 d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                    <i class="bi bi-send-plus-fill"></i>
                                </div>
                                新增訊息發送
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST">
                                <input type="hidden" name="send_notification" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label text-slate-500 fw-semibold small mb-1">選擇目標客戶 *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
                                        <select name="client_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                            <option value="">請選擇客戶...</option>
                                            <?php foreach ($clients as $c): ?>
                                                <option value="<?= $c['id'] ?>">
                                                    <?= htmlspecialchars($c['company_name'] ?? '') ?> 
                                                    <?= empty($c['phone']) ? '(無電話)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-slate-500 fw-semibold small mb-1">發送渠道 *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted border-end-0" id="channelIcon"><i class="bi bi-whatsapp text-success"></i></span>
                                        <select name="notif_type" class="form-select border-start-0 ps-0 shadow-none fw-bold" id="notifType" required>
                                            <option value="whatsapp" class="text-success">WhatsApp 訊息</option>
                                            <option value="email" class="text-info">電子郵件 (Email)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label text-slate-500 fw-semibold small mb-1">快捷範本 (可選)</label>
                                    <select class="form-select shadow-none bg-light" id="templateSelect" onchange="applyTemplate()">
                                        <option value="">-- 手動輸入自訂內容 --</option>
                                        <option value="progress">📊 專案進度更新</option>
                                        <option value="payment">💰 逾期付款提醒</option>
                                        <option value="meeting">📅 預約會議提醒</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label text-slate-500 fw-semibold small mb-1">消息具體內容 *</label>
                                    <textarea name="message" id="messageBox" class="form-control shadow-none" rows="6" required placeholder="請輸入您想發送的訊息內容..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100 fw-bold shadow-sm py-2 fs-6" id="submitBtn">
                                    <i class="bi bi-send-fill me-1"></i> 立即發送 WhatsApp
                                </button>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>目前為沙盒模擬模式，無實際費用產生。</small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 右側：歷史記錄與篩選 -->
                <div class="col-lg-7 col-xl-8">
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
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-funnel"></i></span>
                                        <select name="type" class="form-select border-start-0 ps-0 shadow-none" onchange="this.form.submit()">
                                            <option value="">全部渠道紀錄</option>
                                            <option value="whatsapp" <?= $type_filter === 'whatsapp' ? 'selected' : '' ?>>僅顯示 WhatsApp</option>
                                            <option value="email" <?= $type_filter === 'email' ? 'selected' : '' ?>>僅顯示 Email</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3 text-end d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1 fw-medium">篩選</button>
                                    <a href="notifications.php" class="btn btn-light border text-muted flex-grow-1" title="清除篩選"><i class="bi bi-arrow-counterclockwise"></i></a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-slate-800 mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>發送歷史記錄</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recent_notifications)): ?>
                                <div class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    找不到任何符合條件的通知記錄。
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_notifications as $n): 
                                        $is_wa = ($n['type'] == 'whatsapp');
                                        $badge_class = $is_wa ? 'success' : 'info';
                                        $icon_class = $is_wa ? 'whatsapp' : 'envelope-at-fill';
                                    ?>
                                    <div class="list-group-item p-4 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?= $badge_class ?> bg-opacity-10 text-<?= $badge_class ?> rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                                    <i class="bi bi-<?= $icon_class ?> fs-5"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-slate-800 mb-1" style="font-size: 1.05rem;"><?= htmlspecialchars($n['company_name'] ?? '已刪除客戶') ?></h6>
                                                    <small class="text-muted"><i class="bi bi-person me-1"></i>操作人: <?= htmlspecialchars($n['sender_name'] ?? '系統自動發送') ?></small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?= $badge_class ?> bg-opacity-10 text-<?= $badge_class ?> px-2 py-1 mb-1 border border-<?= $badge_class ?> border-opacity-25">
                                                    <?= $is_wa ? 'WhatsApp' : 'Email' ?>
                                                </span>
                                                <div class="small fw-semibold text-slate-500 mt-1"><i class="bi bi-clock me-1"></i><?= date('Y-m-d H:i', strtotime($n['sent_at'])) ?></div>
                                            </div>
                                        </div>
                                        <div class="bg-light p-3 rounded-3 mt-3 text-slate-700 border border-light-subtle" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?= htmlspecialchars($n['message'] ?? '') ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- 分頁導航 -->
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
            
        <!-- 💡 注意：內容容器到此結束，由 footer.php 進行標準閉合 -->

<script>
function applyTemplate() {
    const templates = {
        'progress': '【YSK 專案進度更新】\n您好，您委託的項目有最新進度更新！\n\n請登入客戶自助門戶查看詳細開發日誌及測試環境連結：\n👉 https://ysk.hk/portal\n\n如有任何問題，歡迎隨時聯絡我們。',
        'payment': '【YSK 付款提醒】\n您好，系統顯示您的賬單（發票）即將到期或已逾期。\n\n為免影響服務進度，請盡快付款，請忽略此訊息。謝謝您的合作！',
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

// 動態更新渠道按鈕外觀與圖示
document.getElementById('notifType').addEventListener('change', function() {
    const btn = document.getElementById('submitBtn');
    const channelIcon = document.getElementById('channelIcon');
    
    if (this.value === 'whatsapp') {
        btn.className = 'btn btn-success w-100 fw-bold shadow-sm py-2 fs-6';
        btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> 立即發送 WhatsApp';
        channelIcon.innerHTML = '<i class="bi bi-whatsapp text-success"></i>';
        this.className = 'form-select border-start-0 ps-0 shadow-none fw-bold text-success';
    } else {
        btn.className = 'btn btn-primary w-100 fw-bold shadow-sm py-2 fs-6';
        btn.innerHTML = '<i class="bi bi-envelope-at-fill me-1"></i> 立即發送 Email';
        channelIcon.innerHTML = '<i class="bi bi-envelope-at-fill text-primary"></i>';
        this.className = 'form-select border-start-0 ps-0 shadow-none fw-bold text-primary';
    }
});
</script>

<?php include 'includes/footer.php'; ?>