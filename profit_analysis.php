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
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>項目利潤分析 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;">
        <div class="d-flex align-items-center mb-4 px-2">
            <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
            <span class="fs-4 fw-bold">YSK Ops</span>
        </div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="profit_analysis.php" class="nav-link active mb-1"><i class="bi bi-graph-up me-2"></i> 利潤分析</a>
            <a href="timesheets.php" class="nav-link mb-1"><i class="bi bi-clock-history me-2"></i> 工時記錄</a>
            <a href="projects.php" class="nav-link mb-1"><i class="bi bi-folder me-2"></i> 項目管理</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <h2 class="mb-4"><i class="bi bi-graph-up me-2"></i> 項目利潤分析</h2>
        
        <div class="alert alert-info">
            <strong>說明：</strong> 估計成本 = 工時 × $800/小時（可在 config 中調整）
        </div>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>項目</th>
                            <th>客戶</th>
                            <th>預算 (HK$)</th>
                            <th>實際工時</th>
                            <th>估計成本 (HK$)</th>
                            <th>利潤 (HK$)</th>
                            <th>利潤率</th>
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
</body>
</html>