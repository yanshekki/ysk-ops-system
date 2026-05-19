<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

// Get client contribution data
$clients = db_fetch_all("
    SELECT c.*,
           (SELECT COUNT(*) FROM projects WHERE client_id = c.id) as project_count,
           (SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE client_id = c.id AND status = 'paid') as total_revenue,
           (SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE client_id = c.id AND status IN ('draft', 'sent', 'overdue')) as pending_revenue
    FROM clients c 
    WHERE c.status = 'active'
    ORDER BY total_revenue DESC
");

$total_all_revenue = array_sum(array_column($clients, 'total_revenue'));
?>
<?php $page_title = "客戶貢獻度報表"; ?>
<?php include 'includes/header.php'; ?>
<div class="d-flex">
    <!-- Mobile Menu Toggle -->
    <button class="mobile-nav-toggle btn d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list fs-4"></i>
    </button>
    
    <!-- Unified Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="flex-grow-1 p-4">
        <h2><i class="bi bi-trophy me-2"></i> 客戶貢獻度報表</h2>
        
        <div class="alert alert-info mt-3">
            <strong>說明：</strong> 按已收款金額排序，顯示每個客戶的總貢獻度
        </div>
        
        <div class="card mt-3">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>客戶名稱</th>
                            <th>項目數</th>
                            <th>已收款 (HK$)</th>
                            <th>待收款 (HK$)</th>
                            <th>貢獻度</th>
                            <th>占比</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($clients as $c): 
                            $contribution = $total_all_revenue > 0 ? round(($c['total_revenue'] / $total_all_revenue) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?= $rank++ ?></strong></td>
                            <td><strong><?= htmlspecialchars($c['company_name']) ?></strong></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $c['project_count'] ?></span></td>
                            <td class="text-end text-success"><strong><?= number_format($c['total_revenue'], 0) ?></strong></td>
                            <td class="text-end text-warning"><?= number_format($c['pending_revenue'], 0) ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: <?= $contribution ?>%"> <?= $contribution ?>% </div>
                                </div>
                            </td>
                            <td class="text-center"><strong><?= $contribution ?>%</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3">總計</th>
                            <th class="text-end text-success"><strong><?= number_format($total_all_revenue, 0) ?></strong></th>
                            <th></th>
                            <th></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
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