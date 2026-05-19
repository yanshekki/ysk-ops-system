<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// Get all projects with financial data
$projects = db_fetch_all("
    SELECT p.*, c.company_name,
           (SELECT SUM(hours) FROM timesheets WHERE project_id = p.id) as total_hours,
           (SELECT SUM(hours * 800) FROM timesheets WHERE project_id = p.id) as estimated_cost
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    ORDER BY p.updated_at DESC
");

$total_revenue = 0;
$total_cost = 0;
?>
<?php $page_title = "收益分析"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <h2><i class="bi bi-graph-up me-2"></i> 收益分析</h2>
        
        <div class="alert alert-info mt-3">
            <strong>說明：</strong> 估計成本 = 工時 × $800/小時
        </div>
        
        <div class="card mt-3">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>項目</th>
                            <th>客戶</th>
                            <th class="text-end">預算 (HK$)</th>
                            <th class="text-center">實際工時</th>
                            <th class="text-end">估計成本 (HK$)</th>
                            <th class="text-end">利潤 (HK$)</th>
                            <th class="text-center">利潤率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): 
                            $profit = $p['budget'] - ($p['estimated_cost'] ?? 0);
                            $profit_rate = $p['budget'] > 0 ? round(($profit / $p['budget']) * 100, 1) : 0;
                            $total_revenue += $p['budget'];
                            $total_cost += ($p['estimated_cost'] ?? 0);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                            <td><?= htmlspecialchars($p['company_name']) ?></td>
                            <td class="text-end"><?= number_format($p['budget'], 0) ?></td>
                            <td class="text-center"><?= number_format($p['total_hours'] ?? 0, 1) ?></td>
                            <td class="text-end text-danger"><?= number_format($p['estimated_cost'] ?? 0, 0) ?></td>
                            <td class="text-end <?= $profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                <strong><?= number_format($profit, 0) ?></strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $profit_rate >= 30 ? 'success' : ($profit_rate >= 10 ? 'warning' : 'danger') ?>">
                                    <?= $profit_rate ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">總計</th>
                            <th class="text-end"><?= number_format($total_revenue, 0) ?></th>
                            <th></th>
                            <th class="text-end text-danger"><?= number_format($total_cost, 0) ?></th>
                            <th class="text-end <?= ($total_revenue - $total_cost) >= 0 ? 'text-success' : 'text-danger' ?>">
                                <strong><?= number_format($total_revenue - $total_cost, 0) ?></strong>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');}</script>
</body>
</html>