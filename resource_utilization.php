<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// Get team utilization data
$team = db_fetch_all("
    SELECT u.id, u.full_name, u.role,
           (SELECT COALESCE(SUM(hours),0) FROM timesheets WHERE user_id = u.id AND work_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as hours_last_30d,
           (SELECT COUNT(*) FROM tasks WHERE assigned_to_id = u.id AND status != 'done') as active_tasks
    FROM users u 
    WHERE u.role IN ('developer', 'pm')
    ORDER BY hours_last_30d DESC
");

$total_hours = array_sum(array_column($team, 'hours_last_30d'));
$avg_utilization = count($team) > 0 ? round(($total_hours / (count($team) * 160)) * 100, 1) : 0; // 160 hours/month target
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資源利用率分析 | <?= SITE_NAME ?></title>
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
            <a href="resource_utilization.php" class="nav-link active mb-1"><i class="bi bi-people-fill me-2"></i> 資源利用率</a>
            <a href="profit_analysis.php" class="nav-link mb-1"><i class="bi bi-graph-up me-2"></i> 利潤分析</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <h2 class="mb-4"><i class="bi bi-people-fill me-2"></i> 團隊資源利用率分析</h2>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-start border-primary border-4">
                    <div class="card-body">
                        <h6 class="text-muted">團隊總工時 (30天)</h6>
                        <div class="display-6 fw-bold text-primary"><?= number_format($total_hours, 1) ?> 小時</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-start border-success border-4">
                    <div class="card-body">
                        <h6 class="text-muted">平均利用率</h6>
                        <div class="display-6 fw-bold text-success"><?= $avg_utilization ?>%</div>
                        <small class="text-muted">目標 75-85%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-start border-warning border-4">
                    <div class="card-body">
                        <h6 class="text-muted">活躍人數</h6>
                        <div class="display-6 fw-bold text-warning"><?= count($team) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>開發者 / PM</th>
                            <th>角色</th>
                            <th>30天工時</th>
                            <th>正在處理任務</th>
                            <th>利用率</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($team as $member): 
                            $util = round(($member['hours_last_30d'] / 160) * 100, 1);
                            $status = $util >= 85 ? 'success' : ($util >= 60 ? 'warning' : 'danger');
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($member['full_name']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= ucfirst($member['role']) ?></span></td>
                            <td><strong><?= number_format($member['hours_last_30d'], 1) ?></strong> 小時</td>
                            <td class="text-center"><span class="badge bg-info"><?= $member['active_tasks'] ?></span></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?= $status ?>" style="width: <?= $util ?>%"><?= $util ?>%</div>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= $status ?>"><?= $util >= 85 ? '超負荷' : ($util >= 60 ? '正常' : '低負荷') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>