<footer class="global-footer border-top py-3 px-4 mt-auto w-100" style="background-color: #f8fafc; font-size: 0.85rem; color: #64748b;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <div>
            &copy; <?php echo date('Y'); ?> <strong style="color: #334155;">YSK Ops System</strong>. All rights reserved.
        </div>
        <div>
            Powered by <a href="https://ysk.hk/" target="_blank" class="text-decoration-none fw-bold" style="color: #4f46e5; transition: 0.2s;" onmouseover="this.style.color='#3730a3'" onmouseout="this.style.color='#4f46e5'">YSK Limited</a>
        </div>
    </div>
</footer>

</div> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. 統一的手機版 Sidebar 切換邏輯
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.toggle('show');
    };

    // 2. 點擊側邊欄以外的地方自動關閉 (提升手機版 UX)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-nav-toggle');
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && (!toggle || !toggle.contains(e.target))) {
                sidebar.classList.remove('show');
            }
        }
    });

    // 3. 全局初始化 Bootstrap Tooltips (滑鼠懸停提示)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 4. 全局初始化 Bootstrap Popovers (點擊彈出提示)
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // 5. 系統提示訊息 (Alerts) 自動淡出隱藏
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(() => {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 4500); // 4.5秒後自動消失
    });

});
</script>
</body>
</html>