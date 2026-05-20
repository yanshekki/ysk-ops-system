<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$project_id = (int)($_GET['project_id'] ?? 0);
$analysis = null;

// 預設基準時薪成本 (用於計算預算燃燒率)
$hourly_rate = 800; 

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
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-calendar-x', 'text' => '項目已嚴重過期，但當前進度僅有 ' . $progress_percent . '%，強烈建議立即召開緊急會議並與客戶協商延期。'];
        } elseif ($time_elapsed_percent - $progress_percent > 30) {
            $risk_score += 35;
            $recommendations[] = ['type' => 'warning', 'icon' => 'bi-speedometer2', 'text' => "進度嚴重落後預期。時間已消耗 {$time_elapsed_percent}%，但產出進度只有 {$progress_percent}%。建議 PM 介入調整資源或增加開發人力。"];
        } elseif ($time_elapsed_percent - $progress_percent > 10) {
            $risk_score += 15;
            $recommendations[] = ['type' => 'info', 'icon' => 'bi-info-circle', 'text' => '進度稍微落後於時間表，請密切關注未來一週的交付狀況。'];
        } else {
            $insights[] = ['type' => 'success', 'text' => '開發進度表現優異，目前符合或領先於時間預期。'];
        }

        // 2. 任務阻塞分析 (精準定位)
        $tasks_data = db_fetch_one("
            SELECT COUNT(*) as total_tasks,
                   SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
                   SUM(CASE WHEN due_date < CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as overdue_tasks
            FROM tasks WHERE project_id = ?
        ", [$project_id]);
        
        $overdue_tasks = (int)($tasks_data['overdue_tasks'] ?? 0);
        
        // 獲取具體過期任務清單
        $overdue_tasks_list = [];
        if ($overdue_tasks > 0) {
            $risk_score += min($overdue_tasks * 8, 40); // 每個過期任務加 8 分風險，最高加 40 分
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-exclamation-octagon', 'text' => "檢測到 {$overdue_tasks} 個已逾期未完成的核心任務，這是造成項目潛在阻塞的主因，請優先排除！"];
            
            $overdue_tasks_list = db_fetch_all("
                SELECT title, due_date, status, (SELECT full_name FROM users WHERE id = assigned_to_id) as assignee 
                FROM tasks 
                WHERE project_id = ? AND due_date < CURDATE() AND status != 'done'
                ORDER BY due_date ASC LIMIT 5
            ", [$project_id]);
        } else {
            $insights[] = ['type' => 'success', 'text' => '項目任務流轉順暢，目前無任何逾期積壓的任務。'];
        }

        // 3. 預算與成本分析
        $actual_cost = db_fetch_one("SELECT COALESCE(SUM(hours)*?, 0) as cost FROM timesheets WHERE project_id = ?", [$hourly_rate, $project_id])['cost'] ?? 0;
        $budget_utilization = $project['budget'] > 0 ? round(($actual_cost / $project['budget']) * 100, 1) : 0;
        
        if ($budget_utilization > 100) {
            $risk_score += 40;
            $recommendations[] = ['type' => 'danger', 'icon' => 'bi-cash-stack', 'text' => '預算已超支！目前投入的工時成本已超過合約預算，此項目已處於虧損狀態。'];
        } elseif ($budget_utilization > 85) {
            $risk_score += 25;
            $recommendations[] = ['type' => 'warning', 'icon' => 'bi-wallet2', 'text' => "預算燃燒率 (Burn Rate) 高達 {$budget_utilization}%，剩餘利潤空間極小，請嚴格審批後續工時投入。"];
        } else {
            $insights[] = ['type' => 'success', 'text' => '成本利潤控制良好，目前預算消耗仍在健康安全範圍內。'];
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
                $recommendations[] = ['type' => 'success', 'icon' => 'bi-shield-check', 'text' => 'AI 診斷結果：項目各項指標均表現優異，無發現明顯風險，請繼續保持！'];
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
            'overdue_tasks' => $overdue_tasks,
            'overdue_tasks_list' => $overdue_tasks_list
        ];
    }
}

// 獲取所有項目供選單使用 (排除已取消項目) -- 💡 這裡修復了模稜兩可的 ID 問題
$projects = db_fetch_all("
    SELECT p.id, p.title, p.status, c.company_name 
    FROM projects p 
    LEFT JOIN clients c ON p.client_id = c.id 
    WHERE p.status != 'cancelled' 
    ORDER BY p.updated_at DESC
");

// 狀態對應中文
$status_map = ['planning' => '規劃中', 'in_progress' => '進行中', 'review' => '審核中', 'completed' => '已完成', 'on_hold' => '暫停'];
?>
<?php $page_title = "AI 項目智能分析"; ?>
<?php include 'includes/header.php'; ?>

<div class="d-flex align-items-stretch" style="min-height: 100vh; width: 100%;">
    
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 d-flex flex-column" style="background-color: #f8f9fa; min-width: 0;">
        
        <div class="p-3 p-md-4 flex-grow-1">
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div class="d-flex align-items-center">
                    <button class="mobile-nav-toggle btn d-md-none me-2 p-1" onclick="toggleSidebar()">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                    <div>
                        <h2 class="h3 fw-bold mb-1 text-slate-800">
                            <i class="bi bi-robot me-2 text-primary"></i> 項目智能分析輔助 
                            <span class="badge bg-primary bg-opacity-10 text-primary ms-2 fs-6 border border-primary border-opacity-25"><i class="bi bi-stars me-1"></i>Copilot AI</span>
                        </h2>
                        <p class="text-muted mb-0 d-none d-md-block">綜合分析項目進度、時間消耗與預算成本，自動預警潛在風險</p>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">選擇需要診斷分析的項目</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-folder2-open"></i></span>
                                <select name="project_id" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇正在進行的專案...</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['title'] ?? '') ?> 
                                            [<?= $status_map[$p['status']] ?? $p['status'] ?>] 
                                            - <?= htmlspecialchars($p['company_name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">
                                <i class="bi bi-magic me-1"></i> 開始智能診斷
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($analysis): ?>
            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100 position-relative overflow-hidden">
                        <div class="bg-<?= $analysis['risk_color'] ?>" style="height: 6px; width: 100%; position: absolute; top: 0; left: 0;"></div>
                        
                        <div class="card-body p-4 text-center mt-3 d-flex flex-column">
                            <h6 class="text-slate-500 fw-bold text-uppercase mb-4" style="letter-spacing: 1px;">綜合風險評分 (Risk Score)</h6>
                            
                            <div class="d-inline-flex justify-content-center align-items-center rounded-circle border border-<?= $analysis['risk_color'] ?> mb-3 mx-auto shadow-sm" style="width: 140px; height: 140px; border-width: 6px !important; background-color: #f8fafc;">
                                <div class="display-3 fw-bolder text-<?= $analysis['risk_color'] ?>" style="letter-spacing: -2px;">
                                    <?= $analysis['risk_score'] ?>
                                </div>
                            </div>
                            
                            <div class="mb-4 mt-2">
                                <span class="badge bg-<?= $analysis['risk_color'] ?> px-3 py-2 fs-6 shadow-sm border border-<?= $analysis['risk_color'] ?>">
                                    <i class="bi bi-shield-fill-exclamation me-1"></i> <?= $analysis['risk_label'] ?>
                                </span>
                            </div>
                            
                            <hr class="text-muted opacity-25 w-100 mt-auto mb-4">
                            
                            <div class="text-start bg-light p-3 rounded-3 border border-light-subtle">
                                <div class="mb-2">
                                    <small class="text-slate-500 fw-semibold"><i class="bi bi-folder2 me-1"></i>分析專案：</small><br>
                                    <span class="text-slate-800 fw-bold"><?= htmlspecialchars($project['title'] ?? '') ?></span>
                                </div>
                                <div class="mb-2 mt-3">
                                    <small class="text-slate-500 fw-semibold"><i class="bi bi-building me-1"></i>客戶名稱：</small><br>
                                    <span class="text-slate-800"><?= htmlspecialchars($project['company_name'] ?? '未指定') ?></span>
                                </div>
                                <div class="mt-3">
                                    <small class="text-slate-500 fw-semibold"><i class="bi bi-person-badge me-1"></i>負責經理 (PM)：</small><br>
                                    <span class="text-slate-800 fw-medium"><?= htmlspecialchars($project['pm_name'] ?? '未分配') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-slate-800 mb-0"><i class="bi bi-lightbulb-fill text-warning me-2"></i>AI 診斷報告與行動建議</h5>
                            <span class="badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-stars"></i> YSK Copilot</span>
                        </div>
                        <div class="card-body p-4">
                            
                            <div class="mb-4">
                                <?php foreach ($analysis['recommendations'] as $rec): ?>
                                <div class="alert alert-<?= $rec['type'] ?> bg-<?= $rec['type'] ?> bg-opacity-10 border border-<?= $rec['type'] ?> border-opacity-25 text-<?= $rec['type'] == 'danger' ? 'danger' : ($rec['type'] == 'warning' ? 'dark' : 'success') ?> d-flex align-items-start mb-3 shadow-sm">
                                    <i class="bi <?= $rec['icon'] ?> fs-4 me-3 mt-1"></i>
                                    <div>
                                        <strong class="d-block mb-1"><?= $rec['type'] == 'danger' ? '高風險警告 (Critical Issue)：' : ($rec['type'] == 'warning' ? '行動建議 (Action Required)：' : '系統建議：') ?></strong>
                                        <span style="line-height: 1.6;"><?= $rec['text'] ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="bg-light rounded-3 p-3 border border-light-subtle">
                                    <h6 class="fw-bold text-slate-700 mb-3 fs-6"><i class="bi bi-check2-square text-success me-2"></i>健康指標洞察</h6>
                                    <?php foreach ($analysis['insights'] as $insight): ?>
                                    <div class="d-flex align-items-start mb-2">
                                        <i class="bi bi-check-circle-fill text-success mt-1 me-2"></i>
                                        <span class="text-slate-700"><?= $insight['text'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="row g-4 mt-2">
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-4 h-100">
                                        <h6 class="fw-bold text-slate-800 mb-4 fs-6"><i class="bi bi-calendar-range text-indigo me-2"></i>時間消耗 vs 交付進度</h6>
                                        
                                        <div class="mb-1 d-flex justify-content-between">
                                            <small class="text-slate-500 fw-semibold">合約時間消耗率</small>
                                            <small class="fw-bold <?= $analysis['time_elapsed_percent'] > 90 ? 'text-danger' : 'text-slate-700' ?>"><?= $analysis['time_elapsed_percent'] ?>%</small>
                                        </div>
                                        <div class="progress mb-4" style="height: 10px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-<?= $analysis['time_elapsed_percent'] > 90 ? 'danger' : 'info' ?>" style="width: <?= $analysis['time_elapsed_percent'] ?>%"></div>
                                        </div>
                                        
                                        <div class="mb-1 d-flex justify-content-between">
                                            <small class="text-slate-500 fw-semibold">實際開發與交付進度</small>
                                            <small class="fw-bold text-success"><?= $project['progress_percent'] ?>%</small>
                                        </div>
                                        <div class="progress mb-2" style="height: 10px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-success progress-bar-striped" style="width: <?= $project['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-4 h-100">
                                        <h6 class="fw-bold text-slate-800 mb-4 fs-6"><i class="bi bi-fire text-danger me-2"></i>成本預算燃燒率 (Burn Rate)</h6>
                                        
                                        <div class="mb-1 d-flex justify-content-between">
                                            <small class="text-slate-500 fw-semibold">預算使用率</small>
                                            <small class="fw-bold <?= $analysis['budget_utilization'] > 85 ? 'text-danger' : 'text-primary' ?>"><?= $analysis['budget_utilization'] ?>%</small>
                                        </div>
                                        <div class="progress mb-4" style="height: 10px; background-color: #f1f5f9;">
                                            <div class="progress-bar bg-<?= $analysis['budget_utilization'] > 85 ? 'danger' : 'primary' ?>" style="width: <?= min($analysis['budget_utilization'], 100) ?>%"></div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                            <div class="text-start">
                                                <div class="small text-slate-500 mb-1">目前投入工時成本</div>
                                                <div class="fw-bold fs-5 text-danger">HK$ <?= number_format($analysis['actual_cost'], 0) ?></div>
                                            </div>
                                            <div class="text-end">
                                                <div class="small text-slate-500 mb-1">合約總預算金額</div>
                                                <div class="fw-bold fs-5 text-slate-800">HK$ <?= number_format($project['budget'] ?? 0, 0) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($analysis['overdue_tasks_list'])): ?>
                                <div class="col-12 mt-4">
                                    <div class="bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 p-4">
                                        <h6 class="fw-bold text-danger mb-3 fs-6"><i class="bi bi-exclamation-triangle-fill me-2"></i>造成進度阻塞的逾期任務清單</h6>
                                        <ul class="list-group list-group-flush shadow-sm rounded-3">
                                            <?php foreach ($analysis['overdue_tasks_list'] as $ot): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 bg-white border-bottom border-light-subtle">
                                                    <div>
                                                        <span class="fw-semibold text-slate-800"><?= htmlspecialchars($ot['title']) ?></span>
                                                        <div class="small text-muted mt-1"><i class="bi bi-person-fill me-1"></i>負責人: <?= htmlspecialchars($ot['assignee'] ?? '未分配') ?></div>
                                                    </div>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                                        <i class="bi bi-calendar-x me-1"></i>過期: <?= $ot['due_date'] ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif(isset($_GET['project_id'])): ?>
                <div class="alert alert-warning border-0 shadow-sm"><i class="bi bi-exclamation-triangle me-2"></i>找不到指定的專案資料，請重新選擇。</div>
            <?php else: ?>
                <div class="text-center py-5 my-5">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                        <i class="bi bi-magic fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-slate-800 mb-2">請從上方選擇一個項目</h4>
                    <p class="text-muted">Copilot AI 將會立即為您診斷項目健康狀況與風險分析</p>
                </div>
            <?php endif; ?>
            
        <?php include 'includes/footer.php'; ?>