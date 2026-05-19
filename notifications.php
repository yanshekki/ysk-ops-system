<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';

// Handle send notification (simulated WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $client_id = (int)$_POST['client_id'];
    $message = trim($_POST['message']);
    
    $client = db_fetch_one("SELECT company_name, phone FROM clients WHERE id = ?", [$client_id]);
    
    if ($client && $client['phone']) {
        $success = '已像 ' . htmlspecialchars($client['company_name']) . ' 發送 WhatsApp 通知！（模擬）';
        
        db_insert('notifications', [
            'client_id' => $client_id,
            'type' => 'whatsapp',
            'message' => $message,
            'sent_by' => $_SESSION['user_id'],
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $error = '該客戶沒有電話號碼，無法發送 WhatsApp';
    }
}

$clients = db_fetch_all("SELECT id, company_name, phone FROM clients WHERE phone != '' ORDER BY company_name");
$recent_notifications = db_fetch_all("
    SELECT n.*, c.company_name 
    FROM notifications n 
    JOIN clients c ON n.client_id = c.id 
    ORDER BY n.sent_at DESC LIMIT 10
");
?>
<?php $page_title = "通知中心"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Unified Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <h2><i class="bi bi-bell me-2"></i> 通知中心（WhatsApp 整合）</h2>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        
        <div class="row g-4 mt-2">
            <!-- Send Notification -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">發送 WhatsApp 通知</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="send_notification" value="1">
                            <div class="mb-3">
                                <label class="form-label">選擇客戶</label>
                                <select name="client_id" class="form-select" required>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?> (<?= $c['phone'] ?>)</option>
                                    <?php endforeach; ?>
                                    </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">消息內容</label>
                                <textarea name="message" class="form-control" rows="4" required placeholder="您的項目已進行到 50%，請查看自助門戶...">您的項目有新進度！請查看自助門戶：https://ysk.hk/portal</textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-whatsapp me-1"></i> 發送 WhatsApp
                            </button>
                            <div class="form-text mt-2">注：目前為模擬模式，未來可接 WhatsApp Business API</div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">最近發送記錄</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_notifications)): ?>
                            <div class="p-4 text-center text-muted">暫無通知記錄</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recent_notifications as $n): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($n['company_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($n['message']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?= $n['sent_at'] ?></small><br>
                                        <span class="badge bg-success">WhatsApp</span>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}
</script>
</body>
</html>