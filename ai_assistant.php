<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$project_id = $_GET['project_id'] ?? 0;
$analysis = null;

if ($project_id) {
    $project = db_fetch_one("SELECT * FROM projects WHERE id = ?", [$project_id]);
    
    if ($project) {
        $risk_score = 0;
        $recommendations = [];
        
        if ($project['progress_percent'] < 30 && strtotime($project['end_date']) < time()) {
            $risk_score = 85;
            $recommendations[] = '項目已過期且進度低於 30%，建議立即開會討論調整期限';
        } elseif ($project['progress_percent'] < 50) {
            $risk_score = 45;
            $recommendations[] = '項目進度偏慢，建議增加開發人力';
        } else {
            $risk_score = 20;
            $recommendations[] = '項目進度良好，繼續保持';
        }
        
        $actual_cost = db_fetch_one("SELECT COALESCE(SUM(hours)*800, 0) as cost FROM timesheets WHERE project_id = ?", [$project_id])['cost'] ?? 0;
        if ($actual_cost > $project['budget'] * 0.8) {
            $risk_score += 25;
            $recommendations[] = '預算使用率超過 80%，建議檢查是否有额外成本';
        }
        
        $analysis = [
            'risk_score' => min($risk_score, 100),
            'risk_level' => $risk_score > 70 ? 'high' : ($risk_score > 40 ? 'medium' : 'low'),
            'recommendations' => $recommendations,
            'actual_cost' => $actual_cost,
            'budget_utilization' => $project['budget'] > 0 ? round(($actual_cost / $project['budget']) * 100, 1) : 0
        ];
    }
}

$projects = db_fetch_all("SELECT id, title FROM projects ORDER BY title");
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 助手 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Unified Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <h2><i class="bi bi-robot me-2"></i> AI 項目風險分析輔助</h2>
        
        <div class="card mb-4 mt-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">選擇項目</label>
                        <select name="project_id" class="form-select" required>
                            <option value="">請選擇項目...</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                            </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-magic me-1"></i> AI 分析
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($analysis): ?>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-start border-<?= $analysis['risk_level'] == 'high' ? 'danger' : ($analysis['risk_level'] == 'medium' ? 'warning' : 'success') ?> border-4 h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted">風險評分</h6>
                        <div class="display-1 fw-bold text-<?= $analysis['risk_level'] == 'high' ? 'danger' : ($analysis['risk_level'] == 'medium' ? 'warning' : 'success') ?>">
                            <?= $analysis['risk_score'] ?>
                        </div>
                        <div class="badge bg-<?= $analysis['risk_level'] == 'high' ? 'danger' : ($analysis['risk_level'] == 'medium' ? 'warning' : 'success') ?> fs-6">
                            <?= strtoupper($analysis['risk_level']) ?> RISK
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">建議與注意事項</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($analysis['recommendations'] as $rec): ?>
                            <li class="list-group-item">
                                <i class="bi bi-lightbulb text-warning me-2"></i> <?= $rec ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="mt-4">
                            <h6>預算使用情況</h6>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-<?= $analysis['budget_utilization'] > 80 ? 'danger' : 'success' ?>" style="width: <?= $analysis['budget_utilization'] ?>%">
                                    <?= $analysis['budget_utilization'] ?>% (HK$ <?= number_format($analysis['actual_cost'], 0) ?> / <?= number_format($project['budget'], 0) ?>)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
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