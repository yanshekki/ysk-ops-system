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

// 接收篩選與分頁參數
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 動態接收「基準成本」 (預設為 800)
$hourly_rate = isset($_GET['hourly_rate']) && $_GET['hourly_rate'] !== '' ? (float)$_GET['hourly_rate'] : 800;
$hourly_rate_safe = (float)$hourly_rate; // 確保數字安全，用於直接嵌入 SQL

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

// ==============================================
// 計算頂部整體 KPI 指標 (不限於當前分頁)
// ==============================================
$agg_sql = "SELECT 
                COALESCE(SUM(p.budget), 0) as overall_budget,
                COALESCE(SUM((SELECT COALESCE(SUM(hours * $hourly_rate_safe), 0) FROM timesheets WHERE project_id = p.id)), 0) as overall_cost,
                COUNT(p.id) as total_projects
            FROM projects p 
            LEFT JOIN clients c ON p.client_id = c.id 
            WHERE $where_sql";
$agg_data = db_fetch_one($agg_sql, $params);

$total_revenue = $agg_data['overall_budget'];
$total_cost = $agg_data['overall_cost'];
$total_profit = $total_revenue - $total_cost;
$avg_profit_rate = $total_revenue > 0 ? round(($total_profit / $total_revenue) * 100, 1) : 0;

$total_pages = ceil(($agg_data['total_projects'] ?? 0) / $per_page);

// ==============================================
// 獲取當前分頁的項目列表
// ==============================================
$sql = "SELECT p.*, c.company_name,
               (SELECT COALESCE(SUM(hours), 0) FROM timesheets WHERE project_id = p.id) as total_hours,
               (SELECT COALESCE(SUM(hours * $hourly_rate_safe), 0) FROM timesheets WHERE project_id = p.id) as estimated_cost
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id 
        WHERE $where_sql
        ORDER BY CASE p.status WHEN 'in_progress' THEN 1 WHEN 'planning' THEN 2 WHEN 'review' THEN 3 ELSE 4 END, p.updated_at DESC
        LIMIT $per_page OFFSET $offset";
$projects = db_fetch_all($sql, $params);

// 項目狀態標籤設定
$status_options = [
    'planning' => ['label' => '規劃中', 'color' => 'secondary'],
    'in_progress' => ['label' => '進行中', 'color' => 'primary'],
    'review' => ['label' => '審核中', 'color' => 'info'],
    'completed' => ['label' => '已完成', 'color' => 'success'],
    'on_hold' => ['label' => '暫停', 'color' => 'warning'],
    'cancelled' => ['label' => '已取消', 'color' => 'danger']
];

$service_options = [
    'ai_automation' => ['label' => 'AI 自動化', 'color' => 'primary', 'icon' => 'bi-robot'],
    'app_development' => ['label' => 'App 開發', 'color' => 'success', 'icon' => 'bi-phone'],
    'cloud_security' => ['label' => '雲端安全', 'color' => 'info', 'icon' => 'bi-cloud-shield'],
    'web3_blockchain' => ['label' => 'Web3 區塊鏈', 'color' => 'warning', 'icon' => 'bi-link-45deg'],
    'other' => ['label' => '其他服務', 'color' => 'secondary', 'icon' => 'bi-box']
];
?>
<?php $page_title = "收益分析 Profit"; ?>
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
                        <h2 class="h3 fw-bold mb-1 text-slate-800">
                            <i class="bi bi-graph-up-arrow me-2 text-primary"></i> 項目收益分析 
                        </h2>
                        <p class="text-muted mb-0 d-none d-md-block">
                            嚴格監控專案預算與實際工時投入成本，確保公司營運利潤
                        </p>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">搜尋項目或客戶</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="關鍵字...">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">項目狀態</label>
                            <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">全部狀態</option>
                                <?php foreach ($status_options as $key => $opt): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">基準時薪成本 (HK$)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-currency-dollar"></i></span>
                                <input type="number" step="0.1" min="0" name="hourly_rate" class="form-control border-start-0 ps-0 shadow-none fw-bold text-primary" value="<?= $hourly_rate ?>" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">建立日期 (起)</label>
                            <input type="date" name="date_from" class="form-control shadow-none" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">建立日期 (迄)</label>
                            <input type="date" name="date_to" class="form-control shadow-none" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-1 text-end mt-auto d-flex gap-1">
                            <button type="submit" class="btn btn-primary flex-grow-1 px-0" title="套用篩選"><i class="bi bi-funnel-fill"></i></button>
                            <a href="profit_analysis.php" class="btn btn-light border px-0 flex-grow-1 text-muted" title="清除重置"><i class="bi bi-arrow-counterclockwise"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #6366f1 !important;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">總合約預算收益</h6>
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-cash-stack fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-slate-800 mb-1">HK$ <?= number_format($total_revenue, 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #ef4444 !important;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">估算工時總成本</h6>
                                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="bi bi-stopwatch fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold text-danger mb-1">HK$ <?= number_format($total_cost, 0) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid <?= $total_profit >= 0 ? '#10b981' : '#ef4444' ?> !important;">
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
                    <div class="card stat-card h-100 border-0 shadow-sm" style="border-left: 4px solid #0ea5e9 !important;">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">平均整體利潤率</h6>
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
                            <thead class="bg-light text-slate-600">
                                <tr>
                                    <th class="ps-4 py-3">專案項目與類型</th>
                                    <th class="py-3">所屬客戶</th>
                                    <th class="py-3">當前狀態</th>
                                    <th class="text-end py-3">合約預算 (HK$)</th>
                                    <th class="text-center py-3">實際投入工時</th>
                                    <th class="text-end py-3">估計成本 (HK$)</th>
                                    <th class="text-end py-3">估算利潤 (HK$)</th>
                                    <th class="text-center pe-4 py-3">利潤率</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($projects as $p): 
                                    $profit = $p['budget'] - $p['estimated_cost'];
                                    $profit_rate = $p['budget'] > 0 ? round(($profit / $p['budget']) * 100, 1) : 0;
                                    
                                    // 利潤狀態視覺化
                                    if ($profit_rate >= 40) {
                                        $rate_color = 'success'; $rate_icon = 'bi-arrow-up-right-circle';
                                    } elseif ($profit_rate >= 15) {
                                        $rate_color = 'primary'; $rate_icon = 'bi-dash-circle';
                                    } elseif ($profit_rate >= 0) {
                                        $rate_color = 'warning'; $rate_icon = 'bi-exclamation-circle';
                                    } else {
                                        $rate_color = 'danger'; $rate_icon = 'bi-arrow-down-right-circle';
                                    }

                                    $avatar_char = mb_substr($p['title'] ?? 'P', 0, 1, 'UTF-8');
                                    $stat_info = $status_options[$p['status']] ?? ['label' => $p['status'], 'color' => 'secondary'];
                                    $svc_info = $service_options[$p['service_type']] ?? $service_options['other'];
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-<?= $svc_info['color'] ?> bg-opacity-10 text-<?= $svc_info['color'] ?> rounded-3 d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 40px; height: 40px;">
                                                <span class="fw-bold fs-5"><?= htmlspecialchars($avatar_char) ?></span>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-slate-800 text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title'] ?? '') ?></div>
                                                <div class="small mt-1"><span class="badge bg-<?= $svc_info['color'] ?> bg-opacity-10 text-<?= $svc_info['color'] ?> border border-<?= $svc_info['color'] ?> border-opacity-25" style="font-size: 0.65rem; padding: 2px 6px;"><i class="bi <?= $svc_info['icon'] ?> me-1"></i><?= $svc_info['label'] ?></span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-slate-700 fw-medium small"><i class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($p['company_name'] ?? '已刪除客戶') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $stat_info['color'] ?> bg-opacity-10 text-<?= $stat_info['color'] ?> px-2 py-1">
                                            <?= $stat_info['label'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-slate-700">
                                        <?= number_format($p['budget'] ?? 0, 0) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold text-indigo fs-6"><?= number_format($p['total_hours'], 1) ?> <span class="text-muted small fw-normal">小時</span></div>
                                    </td>
                                    <td class="text-end text-danger fw-bold">
                                        <?= number_format($p['estimated_cost'], 0) ?>
                                    </td>
                                    <td class="text-end fw-bold fs-6 <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $profit > 0 ? '+' : '' ?><?= number_format($profit, 0) ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-<?= $rate_color ?> bg-opacity-10 text-<?= $rate_color ?> px-2 py-1" style="min-width: 75px; font-size: 0.8rem;">
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
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination shadow-sm">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&hourly_rate=<?= $hourly_rate ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&hourly_rate=<?= $hourly_rate ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&hourly_rate=<?= $hourly_rate ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <?php include 'includes/footer.php'; ?>