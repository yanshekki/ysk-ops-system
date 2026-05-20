<?php
/**
 * YSK Ops System - 全局公共頁尾 (智能自適應版)
 * 職責：自動判斷登入前後狀態，提供 100% 貼底不走位版權列，並安全閉合主體容器。
 */
?>

<?php if (isset($show_login) && $show_login): ?>
    <style>
        /* 令 Footer 喺登入背景呈現極簡、懸浮透明、文字反白嘅頂級 SaaS 質感 */
        .global-footer-login {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            color: #94a3b8 !important;
            font-size: 0.8rem;
            z-index: 5;
            line-height: 1.6;
        }
        .global-footer-login strong, .global-footer-login a {
            color: #ffffff !important;
        }
    </style>
    
    <footer class="global-footer-login no-print">
        &copy; <?php echo date('Y'); ?> <strong style="color: #ffffff;">YSK Ops System</strong>. All rights reserved.<br>
        Powered by <a href="https://ysk.hk/" target="_blank" class="text-decoration-none fw-bold" style="color: #4f46e5;">YSK Limited</a>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>

<?php else: ?>
    </div> <footer class="global-backend-footer border-top py-3 px-4 mt-auto w-100 no-print" style="background-color: #f8fafc; font-size: 0.85rem; color: #64748b;">
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
        // 統一的手機版 Sidebar 切換邏輯
        window.toggleSidebar = function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) sidebar.classList.toggle('show');
        };

        // 點擊側邊欄以外的地方自動關閉 (提升手機版 UX)
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-nav-toggle');
            if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && (!toggle || !toggle.contains(e.target))) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // 系統提示訊息 (Alerts) 自動淡出隱藏
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            setTimeout(() => {
                alert.style.transition = "opacity 0.5s ease";
                alert.style.opacity = "0";
                setTimeout(() => { alert.remove(); }, 500);
            }, 4500);
        });
    });
    </script>
    </body>
    </html>
<?php endif; ?>