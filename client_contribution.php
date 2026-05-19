<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// 權限檢查：限制只有 admin, pm, finance 可以查看財務相關報表
$current_role = $_SESSION['user']['role'] ?? 'viewer';
if (!in_array($current_role, ['admin', 'pm', 'finance'])) {
    header('Location: index.php');
    exit;
}

// 接收篩選參數
$search = trim($_GET['search'] ?? '');
$year_filter = $_GET['year'] ?? '';

// 建立 SQL 查詢條件
$search_clause = "";
$params = [];

if ($search) {
    $search_clause = " AND c.company_name LIKE ?";
    $params[] = "%$search%";
}

$year_clause = "";
if ($year_filter) {
    $year_clause = " AND YEAR(issue_date) = " . (int)$year_filter;
}

// 獲取客戶貢獻數據
$sql = "
    SELECT c.*,
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as project_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE client_id = c.id AND status = 'paid' $year_clause) as total_revenue,
           (SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE client_id = c.id AND status IN ('draft', 'sent', 'overdue') $year_clause) as pending_revenue
    FROM clients c 
    WHERE c.status = 'active' $search_clause
    ORDER BY total_revenue DESC
";
$clients = db_fetch_all($sql, $params);

// 計算頂部總計指標
$total_all_revenue = array_sum(array_column($clients, 'total_revenue'));
$total_all_pending = array_sum(array_column($clients, 'pending_revenue'));

// 找出貢獻最高客戶
$top_client = !empty($clients) && $clients[0]['total_revenue'] > 0 ? $clients[0] : null;

// 獲取系統中發票的所有年份供下拉選單使用
$years = db_fetch_all("SELECT DISTINCT YEAR(issue_date) as yr FROM invoices ORDER BY yr DESC");
?>
<?php $page_title = "客戶貢獻度報表"; ?>
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
                    <i class="bi bi-trophy me-2 text-warning"></i> 客戶貢獻度分析
                </h2>
                <p class="text-muted mb-0 d-none d-md-block">
                    追蹤核心客戶價值，優化資源與服務分配
                </p>
            </div>
        </div>

        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">客戶名稱搜尋</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="輸入客戶或公司名稱...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">發票年度篩選</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-calendar"></i></span>
                            <select name="year" class="form-select border-start-0 ps-0 shadow-none" onchange="this.form.submit()">
                                <option value="">所有時間 (All Time)</option>
                                <?php foreach ($years as $yr): ?>
                                    <option value="<?= $yr['yr'] ?>" <?= $year_filter == $yr['yr'] ? 'selected' : '' ?>><?= $yr['yr'] ?> 年</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill me-1"></i> 篩選</button>
                    </div>
                    <div class="col-md-2 text-end">
                        <a href="client_contribution.php" class="btn btn-light w-100 border"><i class="bi bi-arrow-counterclockwise me-1"></i> 清除</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">列表已收總額</h6>
                            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-success mb-1">HK$ <?= number_format($total_all_revenue, 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">列表待收總額</h6>
                            <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-hourglass-split fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-warning mb-1">HK$ <?= number_format($total_all_pending, 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-slate-500 fw-semibold mb-0" style="font-size: 0.85rem;">最高貢獻客戶 (Top 1)</h6>
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-award fs-5"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold text-slate-800 mb-1 text-truncate" style="max-width: 100%;" title="<?= htmlspecialchars($top_client['company_name'] ?? '無') ?>">
                            <?= $top_client ? htmlspecialchars($top_client['company_name']) : '-' ?>
                        </h3>
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
                                <th width="80" class="text-center">排名</th>
                                <th>客戶名稱</th>
                                <th class="text-center">累積項目</th>
                                <th class="text-end">已收款 (HK$)</th>
                                <th class="text-end">待收款 (HK$)</th>
                                <th width="25%">貢獻度占比</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($clients as $c): 
                                $contribution = $total_all_revenue > 0 ? round(($c['total_revenue'] / $total_all_revenue) * 100, 1) : 0;
                                $avatar_char = mb_substr($c['company_name'] ?? 'C', 0, 1, 'UTF-8');
                                
                                // 獎牌視覺設計
                                $rank_html = "<strong>{$rank}</strong>";
                                if ($rank == 1) $rank_html = "<i class='bi bi-award-fill fs-4 text-warning' title='Top 1'></i>";
                                elseif ($rank == 2) $rank_html = "<i class='bi bi-award-fill fs-4 text-secondary' title='Top 2'></i>";
                                elseif ($rank == 3) $rank_html = "<i class='bi bi-award-fill fs-4' style='color: #cd7f32;' title='Top 3'></i>";
                            ?>
                            <tr>
                                <td class="text-center text-slate-500 fs-5"><?= $rank_html ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-indigo bg-opacity-10 text-indigo rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 38px; height: 38px;">
                                            <span class="fw-bold fs-6"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <span class="fw-bold text-slate-800"><?= htmlspecialchars($c['company_name'] ?? '') ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="fw-bold text-indigo fs-6"><?= $c['project_count'] ?> <span class="text-muted small fw-normal">個</span></div>
                                </td>
                                <td class="text-end fw-bold text-success fs-6">
                                    <?= number_format($c['total_revenue'], 0) ?>
                                </td>
                                <td class="text-end text-warning fw-medium">
                                    <?= number_format($c['pending_revenue'], 0) ?>
                                </td>
                                <td class="pe-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px; border-radius: 4px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-primary" style="width: <?= $contribution ?>%"></div>
                                        </div>
                                        <small class="fw-bold text-primary" style="min-width: 45px;"><?= $contribution ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php $rank++; endforeach; ?>

                            <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-search fs-1 d-block mb-2 opacity-50"></i>
                                    找不到符合條件的客戶貢獻數據
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="3" class="text-end fw-bold text-slate-700 py-3">總計 (Overall) :</td>
                                <td class="text-end fw-bold text-success fs-5 py-3"><?= number_format($total_all_revenue, 0) ?></td>
                                <td class="text-end fw-bold text-warning fs-6 py-3"><?= number_format($total_all_pending, 0) ?></td>
                                <td class="pe-4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>