<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$project_id = (int)($_GET['project_id'] ?? 0);
$analysis = null;

if ($project_id) {
    // 獲取項目、客戶及 PM 資訊
    $project = db_fetch_one("
        SELECT p.*, c.company_name, u.full_name as pm_name 
        FROM projects p 
        LEFT JOIN clients c ON p.client_id = c.id 
        LEFT JOIN users u ON p.assigned_pm_id = u.id 
        WHERE p.id = ?
    ", [$project_id]);
    
    if ($project) {
        $risk_score = 0;
        $recommendations = [];
        $insights = [];
        
        $current_time = time();
        $start_time = strtotime($project['start_date'] ?? date('Y-m-d'));
        $end_time = strtotime($project['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
        $total_duration = max($end_time - $start_time, 86400); // 避免除以 0
        $elapsed_time = max($current_time - $start_time, 0);
        
        // 1. 時間與進度分析
        $time_elapsed_percent = min(round(($elapsed_time / $total_duration) * 100), 100);
        $progress_percent = (int)$project['progress_percent'];
        
        if ($current_time > $end_time && $progress_percent < 100) {
            $risk_score += 50;
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-calendar-x', 'text' => '項目已嚴重過期，但進度僅有 ' . $progress_percent . '%，建議立即與客戶協商延期。'];
        } elseif ($time_elapsed_percent - $progress_percent > 30) {
            $risk_score += 35;
            $recommendations[] = ['type' => 'warning', 'icon' => 'bi-speedometer2', 'text' => "進度嚴重落後。時間已消耗 {$time_elapsed_percent}%，但進度只有 {$progress_percent}%。建議增加開發人力。"];
        } elseif ($time_elapsed_percent - $progress_percent > 10) {
            $risk_score += 15;
            $recommendations[] = ['type' => 'info', 'icon' => 'bi-info-circle', 'text' => '進度稍微落後於時間表，請 PM 密切關注任務交付狀況。'];
        } else {
            $insights[] = ['type' => 'success', 'text' => '開發進度符合或優於時間預期。'];
        }

        // 2. 任務阻塞分析
        $tasks_data = db_fetch_one("
            SELECT COUNT(*) as total_tasks,
                   SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
                   SUM(CASE WHEN due_date < CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as overdue_tasks
            FROM tasks WHERE project_id = ?
        ", [$project_id]);
        
        $overdue_tasks = (int)($tasks_data['overdue_tasks'] ?? 0);
        if ($overdue_tasks > 0) {
            $risk_score += ($overdue_tasks * 5); // 每個過期任務加 5 分風險
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-octagon', 'text' => "檢測到 {$overdue_tasks} 個已逾期未完成的任務，可能是造成進度阻塞的主因。"];
        }

        // 3. 預算與成本分析
        $hourly_rate = 800; // 預設時薪成本
        $actual_cost = db_fetch_one("SELECT COALESCE(SUM(hours)*?, 0) as cost FROM timesheets WHERE project_id = ?", [$hourly_rate, $project_id])['cost'] ?? 0;
        $budget_utilization = $project['budget'] > 0 ? round(($actual_cost / $project['budget']) * 100, 1) : 0;
        
        if ($budget_utilization > 100) {
            $risk_score += 40;
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-cash-stack', 'text' => '預算已超支！實際成本已超過合約預算，此項目目前處於虧損狀態。'];
        } elseif ($budget_utilization > 85) {
            $risk_score += 25;
            $recommendations[] = ['type' => 'warning', 'icon' => 'bi-wallet2', 'text' => "預算使用率高達 {$budget_utilization}%，請嚴格控制剩餘工時，避免項目虧損。"];
        } else {
            $insights[] = ['type' => 'success', 'text' => '預算控制良好，目前成本仍在安全範圍內。'];
        }
        
        // 總結風險等級
        $risk_score = min($risk_score, 100);
        if ($risk_score >= 70) {
            $risk_level = 'high';
            $risk_color = 'danger';
            $risk_label = '高危險 (High Risk)';
        } elseif ($risk_score >= 40) {
            $risk_level = 'medium';
            $risk_color = 'warning';
            $risk_label = '需注意 (Medium Risk)';
        } else {
            $risk_level = 'low';
            $risk_color = 'success';
            $risk_label = '健康 (Low Risk)';
            if(empty($recommendations)) {
                $recommendations[] = ['type' => 'success', 'icon' => 'bi-check-circle', 'text' => '項目各項指標均表現優異，請繼續保持！'];
            }
        }

        $analysis = [
            'risk_score' => $risk_score,
            'risk_level' => $risk_level,
            'risk_color' => $risk_color,
            'risk_label' => $risk_label,
            'recommendations' => $recommendations,
            'insights' => $insights,
            'actual_cost' => $actual_cost,
            'budget_utilization' => $budget_utilization,
            'time_elapsed_percent' => $time_elapsed_percent,
            'overdue_tasks' => $overdue_tasks
        ];
    }
}

// 獲取所有項目供選單使用 (排除已取消項目)
$projects = db_fetch_all("SELECT id, title, status FROM projects WHERE status != 'cancelled' ORDER BY updated_at DESC");
?>
<?php $page_title = "AI 項目智能分析"; ?>
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
                    <i class="bi bi-robot me-2 text-primary"></i> 項目智能分析輔助 <span class="badge bg-primary bg-opacity-10 text-primary ms-2 fs-6">Copilot AI</span>
                </h2>
                <p class="text-muted mb-0 d-none d-md-block">綜合分析項目進度、時間消耗與預算成本，自動預警潛在風險</p>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label text-slate-500 fw-semibold small mb-1">選擇需要分析的項目</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-folder2-open"></i></span>
                            <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                <option value="">請選擇正在進行的專案...</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['title'] ?? '') ?> 
                                        [<?= ucfirst($p['status']) ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                            <i class="bi bi-magic me-1"></i> 開始智能分析
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($analysis): ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
                    <div class="bg-<?= $analysis['risk_color'] ?>" style="height: 6px; width: 100%; position: absolute; top: 0; left: 0;"></div>
                    
                    <div class="card-body p-4 text-center mt-3">
                        <h6 class="text-slate-500 fw-bold text-uppercase tracking-wide mb-3">綜合風險評分</h6>
                        
                        <div class="d-inline-flex justify-content-center align-items-center rounded-circle border border-<?= $analysis['risk_color'] ?> border-4 mb-3" style="width: 130px; height: 130px;">
                            <div class="display-3 fw-bolder text-<?= $analysis['risk_color'] ?>">
                                <?= $analysis['risk_score'] ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <span class="badge bg-<?= $analysis['risk_color'] ?> px-3 py-2 fs-6 shadow-sm">
                                <?= $analysis['risk_label'] ?>
                            </span>
                        </div>
                        
                        <hr class="text-muted opacity-25">
                        
                        <div class="text-start mt-4">
                            <div class="mb-2">
                                <small class="text-slate-500">分析專案：</small><br>
                                <strong class="text-slate-800"><?= htmlspecialchars($project['title'] ?? '') ?></strong>
                            </div>
                            <div class="mb-2">
                                <small class="text-slate-500">客戶名稱：</small><br>
                                <strong class="text-slate-800"><?= htmlspecialchars($project['company_name'] ?? '未指定') ?></strong>
                            </div>
                            <div>
                                <small class="text-slate-500">負責經理 (PM)：</small><br>
                                <strong class="text-slate-800"><i class="bi bi-person-badge text-muted me-1"></i><?= htmlspecialchars($project['pm_name'] ?? '未分配') ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                        <h5 class="fw-bold text-slate-800 mb-0"><i class="bi bi-lightbulb-fill text-warning me-2"></i>AI 診斷與建議</h5>
                    </div>
                    <div class="card-body p-4">
                        
                        <div class="mb-4">
                            <?php foreach ($analysis['recommendations'] as $rec): ?>
                            <div class="alert alert-<?= $rec['type'] ?> bg-<?= $rec['type'] ?> bg-opacity-10 border-0 text-<?= $rec['type'] == 'danger' ? 'danger' : ($rec['type'] == 'warning' ? 'dark' : 'success') ?> d-flex align-items-start mb-3">
                                <i class="bi <?= $rec['icon'] ?> fs-4 me-3 mt-1"></i>
                                <div>
                                    <strong><?= $rec['type'] == 'danger' ? '高風險警告：' : ($rec['type'] == 'warning' ? '注意事項：' : '系統建議：') ?></strong><br>
                                    <?= $rec['text'] ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php foreach ($analysis['insights'] as $insight): ?>
                            <div class="d-flex align-items-start mb-2 px-3 py-2">
                                <i class="bi bi-check-circle-fill text-success mt-1 me-2"></i>
                                <span class="text-slate-700"><?= $insight['text'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="row g-4 mt-2">
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3 border border-light-subtle h-100">
                                    <h6 class="fw-bold text-slate-700 mb-3 fs-6">進度與時間比對</h6>
                                    
                                    <div class="mb-1 d-flex justify-content-between">
                                        <small class="text-slate-500 fw-semibold">已耗時間</small>
                                        <small class="fw-bold <?= $analysis['time_elapsed_percent'] > 90 ? 'text-danger' : 'text-slate-700' ?>"><?= $analysis['time_elapsed_percent'] ?>%</small>
                                    </div>
                                    <div class="progress mb-3" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $analysis['time_elapsed_percent'] > 90 ? 'danger' : 'info' ?>" style="width: <?= $analysis['time_elapsed_percent'] ?>%"></div>
                                    </div>
                                    
                                    <div class="mb-1 d-flex justify-content-between">
                                        <small class="text-slate-500 fw-semibold">開發進度</small>
                                        <small class="fw-bold text-success"><?= $project['progress_percent'] ?>%</small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?= $project['progress_percent'] ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3 border border-light-subtle h-100">
                                    <h6 class="fw-bold text-slate-700 mb-3 fs-6">預算燃燒狀況 (Burn Rate)</h6>
                                    
                                    <div class="mb-1 d-flex justify-content-between">
                                        <small class="text-slate-500 fw-semibold">預算使用率</small>
                                        <small class="fw-bold <?= $analysis['budget_utilization'] > 85 ? 'text-danger' : 'text-primary' ?>"><?= $analysis['budget_utilization'] ?>%</small>
                                    </div>
                                    <div class="progress mb-3" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $analysis['budget_utilization'] > 85 ? 'danger' : 'primary' ?>" style="width: <?= min($analysis['budget_utilization'], 100) ?>%"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <div class="text-start">
                                            <div class="small text-slate-500">實際成本</div>
                                            <div class="fw-bold text-danger">HK$ <?= number_format($analysis['actual_cost'], 0) ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="small text-slate-500">合約預算</div>
                                            <div class="fw-bold text-slate-800">HK$ <?= number_format($project['budget'] ?? 0, 0) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php elseif(isset($_GET['project_id'])): ?>
            <div class="alert alert-warning border-0 shadow-sm"><i class="bi bi-exclamation-triangle me-2"></i>找不到指定的專案資料，請重新選擇。</div>
        <?php endif; ?>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>