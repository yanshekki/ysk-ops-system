<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$success = $error = '';
$category = $_GET['category'] ?? '';

// Handle add article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_article'])) {
    $data = [
        'title' => trim($_POST['title']),
        'content' => trim($_POST['content']),
        'category' => $_POST['category'],
        'created_by' => $_SESSION['user_id']
    ];
    db_insert('knowledge_base', $data);
    $success = '文章已新增！';
}

// Fetch articles
$sql = "SELECT k.*, u.full_name as author FROM knowledge_base k JOIN users u ON k.created_by = u.id";
if ($category) $sql .= " WHERE k.category = '" . $category . "'";
$sql .= " ORDER BY k.created_at DESC";
$articles = db_fetch_all($sql);

$categories = ['sop' => 'SOP 標準流程', 'technical' => '技術文檔', 'client' => '客戶文件', 'other' => '其他'];
?>
<!DOCTYPE html>
<html lang="zh-HK">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>知識庫 | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <div class="sidebar p-3 text-white" style="width:240px;min-height:100vh;background:#212529;flex-shrink:0;">
        <div class="d-flex align-items-center mb-4 px-2">
            <i class="bi bi-gear-fill fs-3 me-2 text-primary"></i>
            <span class="fs-4 fw-bold">YSK Ops</span>
        </div>
        <nav class="nav flex-column">
            <a href="index.php" class="nav-link mb-1"><i class="bi bi-speedometer2 me-2"></i> 儀表板</a>
            <a href="knowledge_base.php" class="nav-link active mb-1"><i class="bi bi-book me-2"></i> 知識庫</a>
            <a href="client_portal.php" class="nav-link mb-1"><i class="bi bi-globe me-2"></i> 客戶自助門戶</a>
            <hr class="border-secondary my-3">
            <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> 登出</a>
        </nav>
    </div>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-book me-2"></i> 知識庫與 SOP 系統</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addArticleModal">
                <i class="bi bi-plus-circle me-1"></i> 新增文章
            </button>
        </div>
        
        <!-- Category Filter -->
        <div class="mb-3">
            <div class="btn-group">
                <a href="knowledge_base.php" class="btn btn-outline-primary <?= !$category ? 'active' : '' ?>">全部</a>
                <?php foreach ($categories as $key => $label): ?>
                <a href="?category=<?= $key ?>" class="btn btn-outline-primary <?= $category == $key ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
        
        <div class="row g-3">
            <?php foreach ($articles as $a): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-primary"><?= $categories[$a['category']] ?? $a['category'] ?></span>
                            <small class="text-muted"><?= date('Y-m-d', strtotime($a['created_at'])) ?></small>
                        </div>
                        <h5 class="card-title mt-2"><?= htmlspecialchars($a['title']) ?></h5>
                        <p class="card-text text-muted"><?= htmlspecialchars(substr($a['content'], 0, 150)) ?>...</p>
                        <div class="text-end">
                            <small class="text-muted">作者：<?= htmlspecialchars($a['author']) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Article Modal -->
<div class="modal fade" id="addArticleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">新增文章</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="add_article" value="1">
                    <div class="mb-3">
                        <label class="form-label">文章標題 *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">分類 *</label>
                        <select name="category" class="form-select" required>
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">內容 *</label>
                        <textarea name="content" class="form-control" rows="8" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">新增文章</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>