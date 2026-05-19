<?php
// Unified Sidebar Navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;">
    <div class="d-flex align-items-center mb-4 px-2">
        <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
        <span class="fs-4 fw-bold">YSK Ops</span>
    </div>
    
    <nav class="nav flex-column">
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