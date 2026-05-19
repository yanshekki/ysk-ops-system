<?php
// Unified Sidebar Navigation - Modern SaaS Version
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* =========================================
       SaaS 企業級 Sidebar 專屬樣式
       ========================================= */
    #sidebar {
        background-color: #0f172a; /* Slate 900 */
        width: 260px;
        min-height: 100vh;
        flex-shrink: 0;
        transition: all 0.3s ease;
        box-shadow: 4px 0 15px rgba(0,0,0,0.05);
        z-index: 1040;
        font-family: 'Inter', 'Noto Sans TC', sans-serif;
    }
    
    .sidebar-brand {
        color: #f8fafc;
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 1.5rem 1.5rem;
        margin-bottom: 0.5rem;
    }

    .sidebar-brand .logo-icon {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        border-radius: 8px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
    }

    .sidebar-heading {
        font-size: 0.7rem;
        font-weight: 700;
        color: #64748b; /* Slate 500 */
        text-transform: uppercase;
        letter-spacing: 1.2px;
        padding: 1.2rem 1.5rem 0.5rem;
    }

    .sidebar-nav {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 0 0.75rem;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        color: #94a3b8; /* Slate 400 */
        text-decoration: none;
        padding: 0.65rem 1rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .sidebar-link i {
        font-size: 1.1rem;
        margin-right: 12px;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .sidebar-link:hover {
        color: #f8fafc;
        background-color: rgba(255, 255, 255, 0.05);
    }

    .sidebar-link:hover i {
        color: #cbd5e1;
    }

    .sidebar-link.active {
        color: #ffffff;
        background-color: #1e293b; /* Slate 800 */
        box-shadow: inset 4px 0 0 #6366f1; /* Indigo 500 border highlight */
    }

    .sidebar-link.active i {
        color: #818cf8; /* Lighter indigo for icon */
    }

    /* 底部使用者卡片 */
    .sidebar-user-card {
        margin-top: auto;
        padding: 1.25rem 1.25rem;
        background-color: rgba(15, 23, 42, 0.95);
        border-top: 1px solid #1e293b;
    }

    .sidebar-user-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background-color: #334155;
        color: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1rem;
    }
    
    /* 自訂捲軸樣式 (Chrome/Edge/Safari) */
    .sidebar-scroll::-webkit-scrollbar { width: 5px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: #475569; }

    /* 手機版 Toggle 行為 */
    @media (max-width: 767.98px) {
        #sidebar {
            position: fixed;
            left: -260px;
        }
        #sidebar.show {
            left: 0;
        }
    }
</style>

<div id="sidebar" class="d-flex flex-column pb-0">
    
    <a href="index.php" class="sidebar-brand">
        <img src="https://ysk.hk/logo.svg" alt="YSK Logo" style="height: 32px; width: auto; max-width: 160px; margin-right: 12px; filter: brightness(0) invert(1);">
        <span class="fs-5 fw-bolder tracking-tight">YSK Ops</span>
    </a>
    
    <div class="overflow-y-auto flex-grow-1 sidebar-scroll" style="scrollbar-width: thin; scrollbar-color: #334155 transparent;">
        
        <div class="sidebar-heading">主控制台</div>
        <ul class="sidebar-nav">
            <li>
                <a href="index.php" class="sidebar-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> 儀表板 Overview
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">業務與專案</div>
        <ul class="sidebar-nav">
            <li>
                <a href="clients.php" class="sidebar-link <?= $current_page == 'clients.php' ? 'active' : '' ?>">
                    <i class="bi bi-buildings"></i> 客戶管理 CRM
                </a>
            </li>
            <li>
                <a href="projects.php" class="sidebar-link <?= $current_page == 'projects.php' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i> 項目管理 Projects
                </a>
            </li>
            <li>
                <a href="tasks.php" class="sidebar-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>">
                    <i class="bi bi-list-check"></i> 任務追蹤 Tasks
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">財務與計費</div>
        <ul class="sidebar-nav">
            <li>
                <a href="invoices.php" class="sidebar-link <?= $current_page == 'invoices.php' ? 'active' : '' ?>">
                    <i class="bi bi-receipt-cutoff"></i> 發票管理 Invoices
                </a>
            </li>
            <li>
                <a href="recurring_invoices.php" class="sidebar-link <?= $current_page == 'recurring_invoices.php' ? 'active' : '' ?>">
                    <i class="bi bi-arrow-repeat"></i> 周期發票 Recurring
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">分析與報表</div>
        <ul class="sidebar-nav">
            <li>
                <a href="timesheets.php" class="sidebar-link <?= $current_page == 'timesheets.php' ? 'active' : '' ?>">
                    <i class="bi bi-stopwatch"></i> 工時記錄 Timesheets
                </a>
            </li>
            <li>
                <a href="resource_utilization.php" class="sidebar-link <?= $current_page == 'resource_utilization.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-lines-fill"></i> 資源利用率 Utilization
                </a>
            </li>
            <li>
                <a href="profit_analysis.php" class="sidebar-link <?= $current_page == 'profit_analysis.php' ? 'active' : '' ?>">
                    <i class="bi bi-graph-up-arrow"></i> 收益分析 Profit
                </a>
            </li>
            <li>
                <a href="client_contribution.php" class="sidebar-link <?= $current_page == 'client_contribution.php' ? 'active' : '' ?>">
                    <i class="bi bi-pie-chart"></i> 客戶貢獻 Contribution
                </a>
            </li>
        </ul>

        <div class="sidebar-heading">系統與工具</div>
        <ul class="sidebar-nav mb-4">
            <li>
                <a href="notifications.php" class="sidebar-link <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
                    <i class="bi bi-bell"></i> 通知中心 Notifications
                </a>
            </li>
            <li>
                <a href="ai_assistant.php" class="sidebar-link <?= $current_page == 'ai_assistant.php' ? 'active' : '' ?>">
                    <i class="bi bi-robot"></i> 智能助理 AI Copilot
                </a>
            </li>
            <li>
                <a href="knowledge_base.php" class="sidebar-link <?= $current_page == 'knowledge_base.php' ? 'active' : '' ?>">
                    <i class="bi bi-journal-text"></i> 知識庫 Knowledge Base
                </a>
            </li>
            <?php if (has_role('admin')): ?>
            <li>
                <a href="users.php" class="sidebar-link <?= $current_page == 'users.php' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i> 團隊管理 Users
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
    
    <?php 
        $user_name = $_SESSION['user']['full_name'] ?? 'User';
        $user_role = $_SESSION['user']['role'] ?? 'viewer';
        $avatar_char = mb_substr($user_name, 0, 1, 'UTF-8');
        
        $role_colors = [
            'admin' => 'danger',
            'pm' => 'primary',
            'developer' => 'success',
            'finance' => 'warning',
            'viewer' => 'secondary'
        ];
        $r_color = $role_colors[$user_role] ?? 'secondary';
    ?>
    <div class="sidebar-user-card">
        <div class="d-flex align-items-center mb-3">
            <div class="sidebar-user-avatar me-3 shadow-sm border border-secondary border-opacity-25">
                <?= htmlspecialchars($avatar_char) ?>
            </div>
            <div class="overflow-hidden">
                <div class="text-white fw-bold text-truncate" style="font-size: 0.9rem;"><?= htmlspecialchars($user_name) ?></div>
                <div class="mt-1">
                    <span class="badge bg-<?= $r_color ?> bg-opacity-25 text-<?= $r_color ?> border border-<?= $r_color ?> border-opacity-25" style="font-size: 0.65rem; padding: 3px 6px;">
                        <?= strtoupper($user_role) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <a href="client_portal.php" target="_blank" class="btn btn-sm btn-outline-light w-100 d-flex align-items-center justify-content-center" style="font-size: 0.75rem; opacity: 0.75; border-color: rgba(255,255,255,0.2);" title="預覽對外客戶門戶">
                <i class="bi bi-box-arrow-up-right me-1"></i> 客戶門戶
            </a>
            <a href="logout.php" class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center px-3" style="font-size: 0.75rem; opacity: 0.85; border-color: rgba(220, 53, 69, 0.4);" title="登出系統">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</div>