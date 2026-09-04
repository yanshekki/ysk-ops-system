<?php
// Unified Sidebar Navigation - Modern SaaS Version
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* =========================================
       SaaS 企業級 Sidebar 專屬樣式
       ========================================= */
    #sidebar {
        background: linear-gradient(180deg, #0b1224 0%, #0f172a 40%, #0b1220 100%);
        width: 260px; min-height: 100vh; flex-shrink: 0; transition: all 0.3s ease;
        box-shadow: 8px 0 32px rgba(2, 6, 23, 0.28); z-index: 1040;
        font-family: 'Inter', 'Noto Sans TC', sans-serif;
        border-right: 1px solid rgba(148, 163, 184, 0.08);
    }
    .sidebar-brand { color: #f8fafc; text-decoration: none; display: flex; align-items: center; gap: 12px; padding: 1.35rem 1.25rem 1.1rem; }
    .sidebar-logo-wrap {
        width: 40px; height: 40px; border-radius: 11px; background: #020617;
        display: inline-flex; align-items: center; justify-content: center;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06);
        flex-shrink: 0;
    }
    .sidebar-logo-wrap img { height: 24px; width: auto; filter: none; }
    .sidebar-brand-sub { display: block; font-size: 0.68rem; font-weight: 600; color: #64748b; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 1px; }
    .sidebar-heading { font-size: 0.68rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1.3px; padding: 1.15rem 1.35rem 0.4rem; }
    .sidebar-nav { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; padding: 0 0.75rem; }
    .sidebar-link { display: flex; align-items: center; color: #94a3b8; text-decoration: none; padding: 0.62rem 0.9rem; border-radius: 10px; font-size: 0.88rem; font-weight: 500; transition: all 0.18s ease; }
    .sidebar-link i { font-size: 1.05rem; margin-right: 12px; color: #64748b; transition: all 0.18s ease; width: 1.15rem; text-align: center; }
    .sidebar-link:hover { color: #f8fafc; background-color: rgba(255, 255, 255, 0.05); }
    .sidebar-link:hover i { color: #c7d2fe; }
    .sidebar-link.active {
        color: #ffffff;
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        box-shadow: 0 10px 18px -10px rgba(79, 70, 229, 0.9);
    }
    .sidebar-link.active i { color: #ffffff; }
    .sidebar-user-card { margin-top: auto; padding: 1.15rem 1.15rem 1.25rem; background: rgba(2, 6, 23, 0.55); border-top: 1px solid rgba(148,163,184,0.12); }
    .sidebar-user-avatar { width: 38px; height: 38px; border-radius: 12px; background: linear-gradient(135deg, #334155, #1e293b); color: #f8fafc; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; }
    @media (max-width: 767.98px) { #sidebar { position: fixed; left: -260px; } #sidebar.show { left: 0; } }
</style>

<div id="sidebar" class="d-flex flex-column pb-0 no-print">
    
    <a href="index.php" class="sidebar-brand">
        <span class="sidebar-logo-wrap">
            <img src="assets/favicon.svg" alt="YSK">
        </span>
        <span>
            <span class="fs-5 fw-bolder lh-1 d-block" style="letter-spacing:-0.03em;">YSK Ops</span>
            <span class="sidebar-brand-sub">Internal OS</span>
        </span>
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

        <?php if (has_any_role(['pm', 'developer', 'finance', 'viewer'])): ?>
        <div class="sidebar-heading">業務與專案</div>
        <ul class="sidebar-nav">
            <?php if (has_any_role(['pm', 'finance', 'viewer'])): ?>
            <li>
                <a href="clients.php" class="sidebar-link <?= $current_page == 'clients.php' ? 'active' : '' ?>">
                    <i class="bi bi-buildings"></i> 客戶管理 CRM
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="projects.php" class="sidebar-link <?= $current_page == 'projects.php' ? 'active' : '' ?>">
                    <i class="bi bi-folder2-open"></i> 項目管理 Projects
                </a>
            </li>
            <?php if (has_any_role(['pm', 'developer', 'viewer'])): ?>
            <li>
                <a href="tasks.php" class="sidebar-link <?= $current_page == 'tasks.php' ? 'active' : '' ?>">
                    <i class="bi bi-list-check"></i> 任務追蹤 Tasks
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php if (has_any_role(['finance', 'pm', 'viewer'])): ?>
        <div class="sidebar-heading">財務與計費</div>
        <ul class="sidebar-nav">
            <li>
                <a href="quotes.php" class="sidebar-link <?= in_array($current_page, ['quotes.php', 'quote_edit.php', 'quote_pdf.php'], true) ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-ruled"></i> 報價單 Quotes
                </a>
            </li>
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
        <?php endif; ?>

        <?php if (has_any_role(['pm', 'finance', 'developer'])): ?>
        <div class="sidebar-heading">分析與報表</div>
        <ul class="sidebar-nav">
            <?php if (has_any_role(['pm', 'developer', 'finance'])): ?>
            <li>
                <a href="timesheets.php" class="sidebar-link <?= $current_page == 'timesheets.php' ? 'active' : '' ?>">
                    <i class="bi bi-stopwatch"></i> 工時記錄 Timesheets
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_any_role(['pm'])): ?>
            <li>
                <a href="resource_utilization.php" class="sidebar-link <?= $current_page == 'resource_utilization.php' ? 'active' : '' ?>">
                    <i class="bi bi-person-lines-fill"></i> 資源利用率 Utilization
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_any_role(['pm', 'finance'])): ?>
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
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <div class="sidebar-heading">系統與工具</div>
        <ul class="sidebar-nav mb-4">
            <?php if (has_any_role(['pm', 'finance'])): ?>
            <li>
                <a href="notifications.php" class="sidebar-link <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
                    <i class="bi bi-bell"></i> 通知中心 Notifications
                </a>
            </li>
            <?php endif; ?>
            <?php if (has_any_role(['pm'])): ?>
            <li>
                <a href="ai_assistant.php" class="sidebar-link <?= $current_page == 'ai_assistant.php' ? 'active' : '' ?>">
                    <i class="bi bi-robot"></i> 智能助理 AI Copilot
                </a>
            </li>
            <?php endif; ?>
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
    <div class="sidebar-user-card no-print">
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
            <a href="client_portal.php" target="_blank" class="btn btn-sm btn-outline-light w-100 d-flex align-items-center justify-content-center" style="font-size: 0.75rem; border-color: rgba(255,255,255,0.18); color: #e2e8f0;" title="預覽對外客戶門戶">
                <i class="bi bi-box-arrow-up-right me-1"></i> 客戶門戶
            </a>
            <a href="logout.php" class="btn btn-sm d-flex align-items-center justify-content-center px-3" style="font-size: 0.75rem; background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25);" title="登出系統">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</div>