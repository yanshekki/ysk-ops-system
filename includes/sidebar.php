<?php
// Unified Sidebar Navigation - Complete Version
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;" id="sidebar">
    <div class="d-flex align-items-center mb-4 px-2">
        <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
        <span class="fs-4 fw-bold">YSK Ops</span>
    </div>
    
    <nav class="nav flex-column">
        <!-- Core -->
        <a href="index.php" class="nav-link mb-1 <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> 儀表板
        </a>
        
        <?php if (has_role('admin')): ?>
        <a href="users.php" class="nav-link mb-1 <?= $current_page == 'users.php' ? 'active' : '' ?>">
            <i class="bi bi-people-fill me-2"></i> 用戶管理
        </a>
        <?php endif; ?>
        
        <a href="clients.php" class="nav-link mb-1 <?= $current_page == 'clients.php' ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> 客戶管理
        </a>
        
        <a href="projects.php" class="nav-link mb-1 <?= $current_page == 'projects.php' ? 'active' : '' ?>">
            <i class="bi bi-folder me-2"></i> 項目管理
        </a>
        
        <a href="tasks.php" class="nav-link mb-1 <?= $current_page == 'tasks.php' ? 'active' : '' ?>">
            <i class="bi bi-list-task me-2"></i> 任務追蹤
        </a>
        
        <a href="invoices.php" class="nav-link mb-1 <?= $current_page == 'invoices.php' ? 'active' : '' ?>">
            <i class="bi bi-receipt me-2"></i> 發票管理
        </a>
        
        <a href="recurring_invoices.php" class="nav-link mb-1 <?= $current_page == 'recurring_invoices.php' ? 'active' : '' ?>">
            <i class="bi bi-arrow-repeat me-2"></i> 周期性發票
        </a>
        
        <hr class="border-secondary my-3">
        
        <!-- Advanced Features -->
        <a href="timesheets.php" class="nav-link mb-1 <?= $current_page == 'timesheets.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history me-2"></i> 工時記錄
        </a>
        
        <a href="resource_utilization.php" class="nav-link mb-1 <?= $current_page == 'resource_utilization.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart me-2"></i> 資源利用率
        </a>
        
        <a href="profit_analysis.php" class="nav-link mb-1 <?= $current_page == 'profit_analysis.php' ? 'active' : '' ?>">
            <i class="bi bi-graph-up me-2"></i> 收益分析
        </a>
        
        <a href="client_contribution.php" class="nav-link mb-1 <?= $current_page == 'client_contribution.php' ? 'active' : '' ?>">
            <i class="bi bi-pie-chart me-2"></i> 客戶貢獻
        </a>
        
        <hr class="border-secondary my-3">
        
        <!-- Tools -->
        <a href="notifications.php" class="nav-link mb-1 <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            <i class="bi bi-bell me-2"></i> 通知中心
        </a>
        
        <a href="ai_assistant.php" class="nav-link mb-1 <?= $current_page == 'ai_assistant.php' ? 'active' : '' ?>">
            <i class="bi bi-robot me-2"></i> AI 助手
        </a>
        
        <hr class="border-secondary my-3">
        
        <a href="logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-right me-2"></i> 登出
        </a>
    </nav>
    
    <div class="mt-auto px-2 pt-4 small text-muted d-none d-md-block">
        <div>登入：<?= htmlspecialchars($_SESSION['user']['full_name'] ?? '') ?></div>
        <div class="text-primary"><?= ucfirst($_SESSION['user']['role'] ?? '') ?></div>
    </div>
</div>