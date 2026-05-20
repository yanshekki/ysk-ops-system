<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();
require_any_role(['pm', 'developer', 'finance', 'viewer']);

$success = $error = '';
$current_user_id = $_SESSION['user_id'];
$is_management = has_role('admin') || has_role('pm');

$category_filter = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12; // 每頁顯示 12 篇文章 (4行 x 3欄 或 3行 x 4欄)
$offset = ($page - 1) * $per_page;

// ==============================================
// 處理表單提交 (新增、編輯、刪除)
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. 新增文章
    if (isset($_POST['add_article'])) {
        $data = [
            'title' => trim($_POST['title']),
            'content' => trim($_POST['content']),
            'category' => $_POST['category'],
            'created_by' => $current_user_id
        ];
        
        if (!empty($data['title']) && !empty($data['content'])) {
            db_insert('knowledge_base', $data);
            $success = '文章已成功發佈至知識庫！';
        } else {
            $error = '請填寫完整的文章標題與內容。';
        }
    }
    
    // 2. 編輯文章
    elseif (isset($_POST['edit_article'])) {
        $article_id = (int)$_POST['article_id'];
        
        // 權限驗證：只有作者或管理層能修改
        $existing = db_fetch_one("SELECT created_by FROM knowledge_base WHERE id = ?", [$article_id]);
        
        if ($existing && ($existing['created_by'] == $current_user_id || $is_management)) {
            $data = [
                'title' => trim($_POST['title']),
                'content' => trim($_POST['content']),
                'category' => $_POST['category']
            ];
            db_update('knowledge_base', $data, 'id = ?', [$article_id]);
            $success = '文章內容已成功更新！';
        } else {
            $error = '權限不足！您只能修改自己發佈的文章。';
        }
    }
    
    // 3. 刪除文章
    elseif (isset($_POST['delete_article'])) {
        $article_id = (int)$_POST['delete_article_id'];
        
        // 權限驗證：只有作者或管理層能刪除
        $existing = db_fetch_one("SELECT created_by FROM knowledge_base WHERE id = ?", [$article_id]);
        
        if ($existing && ($existing['created_by'] == $current_user_id || $is_management)) {
            db_delete('knowledge_base', 'id = ?', [$article_id]);
            $success = '文章已從知識庫中徹底移除！';
        } else {
            $error = '權限不足！您只能刪除自己發佈的文章。';
        }
    }
}

// ==============================================
// 建立分頁與搜尋的 SQL 查詢
// ==============================================
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(k.title LIKE ? OR k.content LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_clauses[] = "k.category = ?";
    $params[] = $category_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// 計算總筆數
$total_count = db_fetch_one("SELECT COUNT(*) as total FROM knowledge_base k WHERE $where_sql", $params)['total'] ?? 0;
$total_pages = ceil($total_count / $per_page);

// 獲取當前頁資料
$sql = "SELECT k.*, u.full_name as author 
        FROM knowledge_base k 
        LEFT JOIN users u ON k.created_by = u.id 
        WHERE $where_sql 
        ORDER BY k.created_at DESC 
        LIMIT $per_page OFFSET $offset";
$articles = db_fetch_all($sql, $params);

// 分類設定與視覺
$categories = [
    'sop' => ['label' => 'SOP 標準流程', 'color' => 'primary', 'icon' => 'bi-diagram-3'],
    'technical' => ['label' => '技術文檔', 'color' => 'success', 'icon' => 'bi-code-square'],
    'client' => ['label' => '客戶溝通文件', 'color' => 'warning', 'icon' => 'bi-chat-quote'],
    'other' => ['label' => '其他資源', 'color' => 'secondary', 'icon' => 'bi-folder2-open']
];
?>
<?php $page_title = "知識庫與 SOP"; ?>
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
                            <i class="bi bi-book-half me-2 text-primary"></i> 知識庫與 SOP 系統
                        </h2>
                        <p class="text-muted mb-0 d-none d-md-block">集中管理公司內部標準流程、技術指南與客戶溝通範本</p>
                    </div>
                </div>
                <div>
                    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                        <i class="bi bi-pencil-square me-1"></i> 撰寫新文章
                    </button>
                </div>
            </div>
            
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋文章標題或內容關鍵字...">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="knowledge_base.php?search=<?= urlencode($search) ?>" class="btn btn-sm <?= empty($category_filter) ? 'btn-primary shadow-sm' : 'btn-outline-secondary border' ?>">全部</a>
                                <?php foreach ($categories as $key => $cat): ?>
                                    <a href="knowledge_base.php?category=<?= $key ?>&search=<?= urlencode($search) ?>" 
                                       class="btn btn-sm <?= $category_filter === $key ? 'btn-'.$cat['color'].' shadow-sm' : 'btn-outline-secondary border' ?>">
                                       <i class="bi <?= $cat['icon'] ?> me-1"></i><?= $cat['label'] ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="knowledge_base.php" class="btn btn-light w-100 border text-muted">清除條件</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i><?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error ?></div><?php endif; ?>
            
            <div class="row g-4 mb-4">
                <?php foreach ($articles as $a): 
                    $cat_info = $categories[$a['category']] ?? $categories['other'];
                    $avatar_char = mb_substr($a['author'] ?? 'U', 0, 1, 'UTF-8');
                    $can_edit = ($a['created_by'] == $current_user_id || $is_management);
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm d-flex flex-column" style="transition: transform 0.2s; border-radius: 12px;">
                        <div class="card-body p-4 flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-<?= $cat_info['color'] ?> bg-opacity-10 text-<?= $cat_info['color'] ?> px-2 py-1 border border-<?= $cat_info['color'] ?> border-opacity-25">
                                    <i class="bi <?= $cat_info['icon'] ?> me-1"></i><?= $cat_info['label'] ?>
                                </span>
                                <small class="text-slate-400"><i class="bi bi-calendar3 me-1"></i><?= date('Y/m/d', strtotime($a['created_at'])) ?></small>
                            </div>
                            
                            <h5 class="fw-bold text-slate-800 mb-2 lh-base" style="font-size: 1.15rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?= htmlspecialchars($a['title'] ?? '') ?>
                            </h5>
                            
                            <p class="text-slate-500 small mb-0" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6;">
                                <?= htmlspecialchars($a['content'] ?? '') ?>
                            </p>
                        </div>
                        
                        <div class="card-footer bg-white border-top px-4 py-3 d-flex justify-content-between align-items-center rounded-bottom-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                                    <span class="fw-bold" style="font-size: 0.75rem;"><?= htmlspecialchars($avatar_char) ?></span>
                                </div>
                                <small class="text-slate-600 fw-medium"><?= htmlspecialchars($a['author'] ?? '未知') ?></small>
                            </div>
                            
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-primary px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#readArticleModal<?= $a['id'] ?>" title="閱讀全文">
                                    <i class="bi bi-book-half"></i> 閱讀
                                </button>
                                
                                <?php if ($can_edit): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border text-muted shadow-none h-100" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editArticleModal<?= $a['id'] ?>"><i class="bi bi-pencil-square me-2 text-primary"></i>編輯文章</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" class="m-0 p-0" onsubmit="return confirm('確定要徹底刪除這篇文章嗎？刪除後無法復原。');">
                                                <input type="hidden" name="delete_article_id" value="<?= $a['id'] ?>">
                                                <button type="submit" name="delete_article" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>刪除文章</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="readArticleModal<?= $a['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-light border-bottom-0 pb-3 pt-4 px-4 d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-<?= $cat_info['color'] ?> bg-opacity-10 text-<?= $cat_info['color'] ?> px-2 py-1 mb-2 border border-<?= $cat_info['color'] ?> border-opacity-25">
                                        <i class="bi <?= $cat_info['icon'] ?> me-1"></i><?= $cat_info['label'] ?>
                                    </span>
                                    <h4 class="modal-title fw-bold text-slate-800 lh-base"><?= htmlspecialchars($a['title'] ?? '') ?></h4>
                                    <div class="text-muted small mt-2 d-flex align-items-center">
                                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px;">
                                            <span class="fw-bold" style="font-size: 0.65rem;"><?= htmlspecialchars($avatar_char) ?></span>
                                        </div>
                                        <span class="fw-semibold me-3"><?= htmlspecialchars($a['author'] ?? '未知') ?></span>
                                        <i class="bi bi-clock me-1"></i><?= $a['created_at'] ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-close align-self-start shadow-none" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4 bg-white" style="line-height: 1.8; color: #334155; font-size: 1.05rem;">
                                <div id="articleContent<?= $a['id'] ?>">
                                    <?= nl2br(htmlspecialchars($a['content'] ?? '')) ?>
                                </div>
                            </div>
                            <div class="modal-footer border-top-0 pt-3 pb-4 px-4 bg-light d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-primary shadow-sm fw-medium" onclick="copyToClipboard('articleContent<?= $a['id'] ?>', this)">
                                    <i class="bi bi-clipboard me-1"></i> 複製內容
                                </button>
                                <button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">關閉</button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($can_edit): ?>
                <div class="modal fade" id="editArticleModal<?= $a['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <form method="POST">
                                <div class="modal-header border-0 pb-0 pt-4 px-4">
                                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </div>
                                        編輯文章內容
                                    </h5>
                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    <input type="hidden" name="edit_article" value="1">
                                    <input type="hidden" name="article_id" value="<?= $a['id'] ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">文章標題 *</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                                <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none fw-bold" value="<?= htmlspecialchars($a['title'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬分類 *</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-tags"></i></span>
                                                <select name="category" class="form-select border-start-0 ps-0 shadow-none" required>
                                                    <?php foreach ($categories as $key => $cat): ?>
                                                        <option value="<?= $key ?>" <?= $a['category'] === $key ? 'selected' : '' ?>><?= $cat['label'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label text-slate-500 fw-semibold small mb-1">文章內容 *</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-body-text"></i></span>
                                                <textarea name="content" class="form-control border-start-0 ps-0 shadow-none bg-light" rows="12" required><?= htmlspecialchars($a['content'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                                    <button type="button" class="btn btn-light border fw-medium" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" class="btn btn-primary px-4 shadow-sm">儲存變更</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty($articles)): ?>
                <div class="col-12">
                    <div class="text-center py-5 text-muted bg-white rounded-3 shadow-sm border border-light-subtle">
                        <i class="bi bi-journal-x fs-1 d-block mb-3 opacity-25"></i>
                        找不到符合條件的知識庫文章
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-2">
                <nav>
                    <ul class="pagination shadow-sm">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        <div class="modal fade" id="addArticleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold text-slate-800 d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="bi bi-pencil-square fs-5"></i>
                        </div>
                        撰寫新文章
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="add_article" value="1">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">文章標題 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                <input type="text" name="title" class="form-control border-start-0 ps-0 shadow-none fw-bold" placeholder="例如：新員工入職 SOP" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">所屬分類 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-tags"></i></span>
                                <select name="category" class="form-select border-start-0 ps-0 shadow-none" required>
                                    <option value="">請選擇分類...</option>
                                    <?php foreach ($categories as $key => $cat): ?>
                                        <option value="<?= $key ?>"><?= $cat['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-slate-500 fw-semibold small mb-1">文章內容 *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0 align-items-start pt-2"><i class="bi bi-body-text"></i></span>
                                <textarea name="content" class="form-control border-start-0 ps-0 shadow-none" rows="12" placeholder="請輸入詳細說明步驟或內容範本..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-secondary border fw-medium" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-check-lg me-1"></i> 發佈文章</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 一鍵複製全文功能
function copyToClipboard(elementId, btnElement) {
    const content = document.getElementById(elementId).innerText;
    navigator.clipboard.writeText(content).then(() => {
        const originalText = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="bi bi-check2 me-1"></i> 已複製';
        btnElement.classList.replace('btn-outline-primary', 'btn-success');
        btnElement.classList.add('text-white');
        
        setTimeout(() => {
            btnElement.innerHTML = originalText;
            btnElement.classList.replace('btn-success', 'btn-outline-primary');
            btnElement.classList.remove('text-white');
        }, 2000);
    }).catch(err => {
        console.error('複製失敗: ', err);
        alert('複製失敗，請手動選取複製。');
    });
}
</script>

<?php include 'includes/footer.php'; ?>