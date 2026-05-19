<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// 接收篩選參數
$period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
$role_filter = $_GET['role'] ?? '';

// 驗證並設定時間區間 (7天, 30天, 90天)
$allowed_periods = [7 => '最近 7 天', 30 => '最近 30 天', 90 => '最近 90 天'];
if (!array_key_exists($period, $allowed_periods)) {
    $period = 30;
}

// 計算該區間的基準工時 (假設每月 30 天約有 160 工作小時)
$expected_hours = round(($period / 30) * 160, 1);

// 構建查詢條件
$where_clauses = ["u.is_active = 1"]; // 只計算活躍員工
$params = [$period];

if ($role_filter === 'developer') {
    $where_clauses[] = "u.role = 'developer'";
} elseif ($role_filter === 'pm') {
    $where_clauses[] = "u.role = 'pm'";
} else {
    $where_clauses[] = "u.role IN ('developer', 'pm')";
}

$where_sql = implode(" AND ", $where_clauses);

// 獲取團隊利用率數據
$team = db_fetch_all("
    SELECT u.id, u.full_name, u.username, u.role,
           (SELECT COALESCE(SUM(hours),0) FROM timesheets WHERE user_id = u.id AND work_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)) as hours_period,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to_id = u.id AND status != 'done') as active_tasks
    FROM users u 
    WHERE $where_sql
    ORDER BY hours_period DESC
", $params);

// 計算整體統計
$total_hours = array_sum(array_column($team, 'hours_period'));
$total_expected = count($team) > 0 ? count($team) * $expected_hours : 1; // 避免除以零
$avg_utilization = count($team) > 0 ? round(($total_hours / $total_expected) * 100, 1) : 0;
?>
<?php $page_title = "資源利用率分析"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-bar-chart-steps me-2 text-primary"></i> 團隊資源利用率分析</h2>
                <p class="text-muted mb-0 d-none d-md-block">監控開發者與項目經理的工作負荷與產能分配</p>
            </div>
        </div>

        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">時間區間</label>
                        <select name="period" class="form-select shadow-none" onchange="this.form.submit()">
                            <?php foreach ($allowed_periods as $days => $label): ?>
                                <option value="<?= $days ?>" <?= $period == $days ? 'selected' : '' ?>><?= $label ?> (基準: <?= round(($days/30)*160) ?> 小時)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-slate-500 fw-semibold small">職位角色</label>
                        <select name="role" class="form-select shadow-none" onchange="this.form.submit()">
                            <option value="">全部 (Developer + PM)</option>
                            <option value="developer" <?= $role_filter === 'developer' ? 'selected' : '' ?>>僅限 開發人員 (Developer)</option>
                            <option value="pm" <?= $role_filter === 'pm' ? 'selected' : '' ?>>僅限 項目經理 (PM)</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end ms-auto">
                        <a href="resource_utilization.php" class="btn btn-light w-100 border">重置條件</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">團隊總工時 (<?= $allowed_periods[$period] ?>)</h6>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-stopwatch fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-2"><?= number_format($total_hours, 1) ?> <span class="fs-6 text-slate-400 fw-normal">小時</span></h3>
                        <div class="mt-auto">
                            <small class="text-slate-400" style="font-size: 0.75rem;">基準總工時為 <?= number_format($total_expected, 0) ?> 小時</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">整體平均利用率</h6>
                            <div class="bg-<?= $avg_utilization >= 85 ? 'warning' : ($avg_utilization >= 60 ? 'success' : 'secondary') ?> bg-opacity-10 text-<?= $avg_utilization >= 85 ? 'warning' : ($avg_utilization >= 60 ? 'success' : 'secondary') ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-pie-chart fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-2"><?= $avg_utilization ?>%</h3>
                        <div class="mt-auto">
                            <small class="text-slate-400" style="font-size: 0.75rem;">健康目標區間：60% - 85%</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">列入計算人數</h6>
                            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-people fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-2"><?= count($team) ?> <span class="fs-6 text-slate-400 fw-normal">人</span></h3>
                        <div class="mt-auto">
                            <small class="text-slate-400" style="font-size: 0.75rem;">僅計算活躍的 PM 與 Developer</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>團隊成員</th>
                                <th>角色</th>
                                <th>期間工時</th>
                                <th class="text-center">跟進中任務</th>
                                <th width="25%">利用率進度</th>
                                <th>負荷狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team as $member): 
                                // 利用率計算 = (實際工時 / 基準工時) * 100
                                $util = $expected_hours > 0 ? round(($member['hours_period'] / $expected_hours) * 100, 1) : 0;
                                
                                // 正確的狀態邏輯分配
                                if ($util > 100) {
                                    $status_color = 'danger';
                                    $status_text = '嚴重超負荷';
                                } elseif ($util >= 85) {
                                    $status_color = 'warning';
                                    $status_text = '接近滿載';
                                } elseif ($util >= 60) {
                                    $status_color = 'success';
                                    $status_text = '健康狀態';
                                } else {
                                    $status_color = 'secondary';
                                    $status_text = '產能閒置';
                                }
                                
                                $avatar_char = mb_substr($member['full_name'] ?? 'U', 0, 1, 'UTF-8');
                                $role_label = $member['role'] == 'developer' ? '開發人員' : '項目經理';
                                $role_color = $member['role'] == 'developer' ? 'success' : 'primary';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-<?= $role_color ?> bg-opacity-10 text-<?= $role_color ?> rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="fw-bold fs-5"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-800"><?= htmlspecialchars($member['full_name']) ?></div>
                                            <div class="small text-muted">@<?= htmlspecialchars($member['username']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $role_color ?> bg-opacity-10 text-<?= $role_color ?> px-2 py-1">
                                        <?= $role_label ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-slate-700"><?= number_format($member['hours_period'], 1) ?> <span class="text-muted small fw-normal">/ <?= $expected_hours ?></span></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info bg-opacity-10 text-info fs-6 px-2 py-1"><?= $member['active_tasks'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-<?= $status_color ?>" style="width: <?= min($util, 100) ?>%"></div>
                                        </div>
                                        <small class="fw-bold text-<?= $status_color ?>" style="min-width: 45px;"><?= $util ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> px-2 py-1">
                                        <?= $status_text ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($team)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2 opacity-50"></i>
                                    目前沒有活躍的開發人員或項目經理。
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>