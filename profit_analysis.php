<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// 權限檢查：限制只有 admin, pm, finance 可以查看收益
$current_role = $_SESSION['user']['role'] ?? 'viewer';
if (!in_array($current_role, ['admin', 'pm', 'finance'])) {
    header('Location: index.php');
    exit;
}

// 接收篩選參數
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// 動態接收「基準成本」 (預設為 800)
$hourly_rate = isset($_GET['hourly_rate']) && $_GET['hourly_rate'] !== '' ? (float)$_GET['hourly_rate'] : 800;

// 建立 SQL 查詢條件
$where_clauses = ["1=1"];
$params = [];

if ($status_filter) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_clauses[] = "(p.title LIKE ? OR c.company_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// 時間段篩選 (以項目建立日期為準)
if ($date_from) {
    $where_clauses[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where_clauses[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = implode(" AND ", $where_clauses);

// 確保傳入 SQL 的 rate 是安全的數字
$hourly_rate_safe = (float)$hourly_rate;

// 獲取包含財務數據的項目列表 (採用動態成本基數)
$projects = db_fetch_all("
    SELECT p.*, c.company_name,
           (SELECT COALESCE(SUM(hours), 0) FROM timesheets WHERE project_id = p.id) as total_hours,
           (SELECT COALESCE(SUM(hours * $hourly_rate_safe), 0) FROM timesheets WHERE project_id = p.id) as estimated_cost
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    WHERE $where_sql
    ORDER BY p.status ASC, p.updated_at DESC
", $params);

// 計算頂部總計指標
$total_revenue = 0;
$total_cost = 0;

foreach ($projects as $p) {
    $total_revenue += $p['budget'];
    $total_cost += $p['estimated_cost'];
}

$total_profit = $total_revenue - $total_cost;
$avg_profit_rate = $total_revenue > 0 ? round(($total_profit / $total_revenue) * 100, 1) : 0;

// 項目狀態標籤設定
$status_options = [
    'planning' => '規劃中',
    'in_progress' => '進行中',
    'review' => '審核中',
    'completed' => '已完成',
    'on_hold' => '暫停',
    'cancelled' => '已取消'
];
?>
<?php $page_title = "收益分析"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h3 fw-bold mb-1 text-slate-800">
                    <i class="bi bi-graph-up-arrow me-2 text-primary"></i> 項目收益分析 
                </h2>
                <p class="text-muted mb-0 d-none d-md-block">
                    監控專案預算與實際工時成本
                </p>
            </div>
        </div>

        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">關鍵字搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="項目名稱 / 客戶">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">項目狀態</label>
                        <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                            <option value="">全部狀態</option>
                            <?php foreach ($status_options as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">基準成本 (HK$/h)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                            <input type="number" step="0.1" min="0" name="hourly_rate" class="form-control border-start-0 ps-0 shadow-none fw-bold text-primary" value="<?= $hourly_rate ?>" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">項目建立 (由)</label>
                        <input type="date" name="date_from" class="form-control shadow-none" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">至</label>
                        <input type="date" name="date_to" class="form-control shadow-none" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-1 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1 px-0" title="套用篩選"><i class="bi bi-funnel-fill"></i></button>
                        <a href="profit_analysis.php" class="btn btn-light border px-0 flex-grow-1" title="清除重置"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">總預算收益</h6>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-1">HK$ <?= number_format($total_revenue, 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">估算總成本</h6>
                            <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-stopwatch fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-danger mb-1">HK$ <?= number_format($total_cost, 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">整體淨利潤</h6>
                            <div class="bg-<?= $total_profit >= 0 ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $total_profit >= 0 ? 'success' : 'danger' ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-piggy-bank fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-<?= $total_profit >= 0 ? 'success' : 'danger' ?> mb-1">HK$ <?= number_format($total_profit, 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">平均利潤率</h6>
                            <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-pie-chart fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-1"><?= $avg_profit_rate ?>%</h3>
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
                                <th>專案項目</th>
                                <th>所屬客戶</th>
                                <th>狀態</th>
                                <th class="text-end">項目預算 (HK$)</th>
                                <th class="text-center">實際工時</th>
                                <th class="text-end">估計成本 (HK$)</th>
                                <th class="text-end">利潤 (HK$)</th>
                                <th class="text-center pe-4">利潤率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $p): 
                                $profit = $p['budget'] - $p['estimated_cost'];
                                $profit_rate = $p['budget'] > 0 ? round(($profit / $p['budget']) * 100, 1) : 0;
                                
                                // 利潤狀態視覺化
                                if ($profit_rate >= 40) {
                                    $rate_color = 'success';
                                    $rate_icon = 'bi-arrow-up-right-circle';
                                } elseif ($profit_rate >= 15) {
                                    $rate_color = 'primary';
                                    $rate_icon = 'bi-dash-circle';
                                } elseif ($profit_rate >= 0) {
                                    $rate_color = 'warning';
                                    $rate_icon = 'bi-exclamation-circle';
                                } else {
                                    $rate_color = 'danger';
                                    $rate_icon = 'bi-arrow-down-right-circle';
                                }

                                $avatar_char = mb_substr($p['title'] ?? 'P', 0, 1, 'UTF-8');
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 38px; height: 38px;">
                                            <span class="fw-bold fs-6"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-800 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                            <div class="small text-muted"><?= date('Y-m-d', strtotime($p['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-slate-700 small"><?= htmlspecialchars($p['company_name'] ?? '已刪除客戶') ?></span>
                                </td>
                                <td>
                                    <span class="text-muted small"><?= $status_options[$p['status']] ?? $p['status'] ?></span>
                                </td>
                                <td class="text-end fw-semibold text-slate-700">
                                    <?= number_format($p['budget'] ?? 0, 0) ?>
                                </td>
                                <td class="text-center">
                                    <div class="fw-bold text-indigo fs-6"><?= number_format($p['total_hours'], 1) ?> <span class="text-muted small fw-normal">小時</span></div>
                                </td>
                                <td class="text-end text-danger fw-medium">
                                    <?= number_format($p['estimated_cost'], 0) ?>
                                </td>
                                <td class="text-end fw-bold <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($profit, 0) ?>
                                </td>
                                <td class="text-center pe-4">
                                    <span class="badge bg-<?= $rate_color ?> bg-opacity-10 text-<?= $rate_color ?> px-2 py-1" style="min-width: 70px;">
                                        <i class="bi <?= $rate_icon ?> me-1"></i><?= $profit_rate ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-graph-down fs-1 d-block mb-2 opacity-50"></i>
                                    在此篩選條件下，找不到符合的項目收益數據
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-slate-700 py-3">總計 (Overall) :</td>
                                <td class="text-end fw-bold text-slate-800 fs-6 py-3"><?= number_format($total_revenue, 0) ?></td>
                                <td></td>
                                <td class="text-end fw-bold text-danger fs-6 py-3"><?= number_format($total_cost, 0) ?></td>
                                <td class="text-end fw-bold <?= $total_profit >= 0 ? 'text-success' : 'text-danger' ?> fs-5 py-3">
                                    <?= number_format($total_profit, 0) ?>
                                </td>
                                <td class="text-center pe-4 py-3">
                                    <span class="fw-bold text-<?= $avg_profit_rate >= 0 ? 'success' : 'danger' ?> fs-6"><?= $avg_profit_rate ?>%</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>