<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/quote_helpers.php';
require_login();
require_any_role(['pm', 'finance', 'viewer']);

$success = $error = '';
$can_write = has_any_role(['pm', 'finance']);

quote_expire_stale();

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_write) {
        $error = '權限不足！您沒有新增、修改或轉換報價單的權限。';
    } else {
        try {
            $quote_id = (int)($_POST['quote_id'] ?? 0);
            $user_id = (int)$_SESSION['user_id'];

            if (isset($_POST['delete_quote'])) {
                $q = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
                if (!$q || !quote_can('delete', $q)) {
                    throw new RuntimeException('只有草稿可以刪除。');
                }
                db_delete('quotes', 'id = ?', [$quote_id]);
                $success = '草稿報價單已刪除。';
            } elseif (isset($_POST['send_quote'])) {
                $q = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
                $items = quote_fetch_items($quote_id);
                if (!$q || !quote_can('send', $q)) {
                    throw new RuntimeException('只有草稿可以發送。');
                }
                if (!$items) {
                    throw new RuntimeException('請先加入明細再發送。');
                }
                db_update('quotes', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$quote_id]);
                $success = '報價單 ' . $q['quote_number'] . ' 已標記為已發送。';
            } elseif (isset($_POST['accept_quote'])) {
                $q = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
                if (!$q || !quote_can('accept', $q)) {
                    throw new RuntimeException('此報價單不能接受（可能已過期）。');
                }
                db_update('quotes', [
                    'status' => 'accepted',
                    'accepted_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$quote_id]);
                quote_promote_lead((int)$q['client_id']);
                $success = '客戶已接受報價 ' . $q['quote_number'] . '。請轉換為發票。';
            } elseif (isset($_POST['decline_quote'])) {
                $q = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
                if (!$q || !quote_can('decline', $q)) {
                    throw new RuntimeException('此報價單不能拒絕。');
                }
                db_update('quotes', [
                    'status' => 'declined',
                    'declined_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$quote_id]);
                $success = '報價單 ' . $q['quote_number'] . ' 已標記為拒絕。';
            } elseif (isset($_POST['revise_quote'])) {
                $new_id = quote_clone($quote_id, 'revise', $user_id);
                header('Location: quote_edit.php?id=' . $new_id);
                exit;
            } elseif (isset($_POST['duplicate_quote'])) {
                $new_id = quote_clone($quote_id, 'duplicate', $user_id);
                header('Location: quote_edit.php?id=' . $new_id);
                exit;
            } elseif (isset($_POST['convert_quote'])) {
                $create_project = isset($_POST['create_project']);
                $result = quote_convert($quote_id, $user_id, $create_project);
                $success = '已轉換為發票 ' . $result['invoice_number'] . '（草稿）。第一期已入帳，週期下次扣費不會重複第一期。';
                $_SESSION['flash_success'] = $success;
                header('Location: invoices.php?search=' . urlencode($result['invoice_number']));
                exit;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (!empty($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

$where_clauses = ['1=1'];
$params = [];
if ($search) {
    $where_clauses[] = '(q.quote_number LIKE ? OR q.title LIKE ? OR c.company_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $where_clauses[] = 'q.status = ?';
    $params[] = $status_filter;
}
$where_sql = implode(' AND ', $where_clauses);

$total_count = db_fetch_one(
    "SELECT COUNT(*) AS total FROM quotes q JOIN clients c ON c.id = q.client_id WHERE $where_sql",
    $params
)['total'] ?? 0;
$total_pages = (int)ceil($total_count / $per_page);

$quotes = db_fetch_all(
    "SELECT q.*, c.company_name, c.status AS client_status
     FROM quotes q
     JOIN clients c ON c.id = q.client_id
     WHERE $where_sql
     ORDER BY q.updated_at DESC
     LIMIT $per_page OFFSET $offset",
    $params
);

$kpis = db_fetch_one("
    SELECT
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS drafts,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'sent' THEN total_amount ELSE 0 END) AS pending_amount,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
        SUM(CASE WHEN status = 'converted' AND MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE()) THEN total_amount ELSE 0 END) AS converted_month
    FROM quotes
") ?: [];

$quote_items_by_id = [];
if ($quotes) {
    $ids = array_map(fn($q) => (int)$q['id'], $quotes);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $all_items = db_fetch_all("SELECT * FROM quote_items WHERE quote_id IN ($in) ORDER BY sort_order, id", $ids);
    foreach ($all_items as $it) {
        $quote_items_by_id[(int)$it['quote_id']][] = $it;
    }
}

$status_badges = quote_status_map();
$page_title = '報價單 Quotes';
include 'includes/header.php';
?>

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
                        <h2 class="h3 fw-bold mb-1 text-slate-800"><i class="bi bi-file-earmark-ruled me-2 text-primary"></i> 報價單 (Quotes)</h2>
                        <p class="text-muted mb-0 d-none d-md-block">書面報價 → 客戶接受 → 轉換發票／週期單。報價本身不計入收入。</p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="export_csv.php?type=quotes" class="btn btn-light border">CSV</a>
                    <?php if ($can_write): ?>
                    <a href="quote_edit.php" class="btn btn-primary shadow-sm fw-bold">
                        <i class="bi bi-plus-circle me-1"></i> 開立新報價
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 border-left-thick" style="border-left: 4px solid #94a3b8;">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold">草稿</div>
                            <div class="fs-4 fw-bold text-slate-800"><?= (int)($kpis['drafts'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b;">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold">待回覆</div>
                            <div class="fs-4 fw-bold text-slate-800"><?= (int)($kpis['pending'] ?? 0) ?></div>
                            <div class="small text-muted">HK$ <?= number_format((float)($kpis['pending_amount'] ?? 0), 0) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981;">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold">已接受未轉</div>
                            <div class="fs-4 fw-bold text-slate-800"><?= (int)($kpis['accepted'] ?? 0) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4f46e5;">
                        <div class="card-body p-3">
                            <div class="small text-muted fw-semibold">本月已轉換</div>
                            <div class="fs-4 fw-bold text-slate-800">HK$ <?= number_format((float)($kpis['converted_month'] ?? 0), 0) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body p-3">
                    <form method="GET" class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0 shadow-none" value="<?= htmlspecialchars($search) ?>" placeholder="搜尋單號、標題或客戶...">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <select name="status" class="form-select shadow-none" onchange="this.form.submit()">
                                <option value="">所有狀態</option>
                                <?php foreach ($status_badges as $val => $opt): ?>
                                    <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 text-end">
                            <a href="quotes.php" class="btn btn-light border w-100 text-muted">清除篩選</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($success): ?><div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-slate-600">
                                <tr>
                                    <th class="ps-4 py-3">報價單號</th>
                                    <th class="py-3">客戶與標題</th>
                                    <th class="py-3">有效期</th>
                                    <th class="py-3 text-end">首年合約值</th>
                                    <th class="py-3 text-center">狀態</th>
                                    <th class="text-end pe-4 py-3">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quotes as $q):
                                    $badge = $status_badges[$q['status']] ?? $status_badges['draft'];
                                    $items = $quote_items_by_id[(int)$q['id']] ?? [];
                                    $year_one = quote_year_one_value($items);
                                    $expired_soon = $q['status'] === 'sent' && $q['valid_until'] < date('Y-m-d', strtotime('+3 days'));
                                ?>
                                <tr class="<?= in_array($q['status'], ['declined','expired','superseded'], true) ? 'opacity-75' : '' ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-slate-800"><?= htmlspecialchars($q['quote_number']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($q['issue_date']) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-slate-700">
                                            <?= htmlspecialchars($q['company_name']) ?>
                                            <?php if (($q['client_status'] ?? '') === 'lead'): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning ms-1">潛在</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($q['title']) ?></small>
                                    </td>
                                    <td>
                                        <div class="small <?= ($q['status'] === 'sent' && $q['valid_until'] < date('Y-m-d')) || $expired_soon ? 'text-danger fw-bold' : 'text-slate-600' ?>">
                                            <?= htmlspecialchars($q['valid_until']) ?>
                                            <?php if ($expired_soon): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="fw-bold text-slate-800">HK$ <?= number_format($year_one, 0) ?></div>
                                        <small class="text-muted">應付 HK$ <?= number_format((float)$q['total_amount'], 0) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $badge['color'] ?> bg-opacity-10 text-<?= $badge['color'] ?> px-2 py-1"><?= $badge['label'] ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1 flex-wrap">
                                            <a href="quote_pdf.php?id=<?= (int)$q['id'] ?>" target="_blank" class="btn btn-sm btn-light border" title="PDF"><i class="bi bi-printer"></i></a>
                                            <?php if ($can_write && quote_can('edit', $q)): ?>
                                                <a href="quote_edit.php?id=<?= (int)$q['id'] ?>" class="btn btn-sm btn-light border text-primary" title="編輯"><i class="bi bi-pencil-square"></i></a>
                                            <?php else: ?>
                                                <a href="quote_edit.php?id=<?= (int)$q['id'] ?>" class="btn btn-sm btn-light border text-muted" title="檢視"><i class="bi bi-eye"></i></a>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('send', $q)): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('確定將 <?= htmlspecialchars($q['quote_number']) ?> 標記為已發送？發送後內容將鎖定。');">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="send_quote" class="btn btn-sm btn-primary" title="發送"><i class="bi bi-send"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('accept', $q)): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="accept_quote" class="btn btn-sm btn-success" title="接受"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('確定拒絕此報價？');">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="decline_quote" class="btn btn-sm btn-outline-danger" title="拒絕"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('convert', $q)): ?>
                                                <button class="btn btn-sm btn-indigo text-white" style="background:#4f46e5;" data-bs-toggle="modal" data-bs-target="#convertModal<?= (int)$q['id'] ?>" title="轉換">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('revise', $q)): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('將建立修訂草稿，原單會標為已修訂。');">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="revise_quote" class="btn btn-sm btn-light border" title="修訂"><i class="bi bi-files"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('duplicate', $q)): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="duplicate_quote" class="btn btn-sm btn-light border" title="複製"><i class="bi bi-copy"></i></button>
                                            </form>
                                            <?php endif; ?>
                                            <?php if ($can_write && quote_can('delete', $q)): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('確定刪除此草稿？');">
                                                <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" name="delete_quote" class="btn btn-sm btn-light border text-danger" title="刪除"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <?php if ($can_write && quote_can('convert', $q)):
                                    $preview_lines = quote_first_invoice_items($items);
                                    $recurring_preview = array_filter($items, fn($it) => quote_is_recurring_billing($it['billing_type']));
                                ?>
                                <div class="modal fade" id="convertModal<?= (int)$q['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg">
                                            <form method="POST">
                                                <div class="modal-header border-0 pt-4 px-4">
                                                    <h5 class="modal-title fw-bold">轉換報價 <?= htmlspecialchars($q['quote_number']) ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body px-4">
                                                    <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                                                    <p class="text-muted small">會開一張<strong>草稿發票</strong>（含一次性 + 各週期首期）。週期單的下次執行日會跳過第一期，避免雙重收費。報價金額不會計入已收款。</p>
                                                    <div class="table-responsive mb-3">
                                                        <table class="table table-sm">
                                                            <thead><tr><th>首張發票明細</th><th class="text-end">金額</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach ($preview_lines as $line): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($line['title']) ?></td>
                                                                    <td class="text-end">HK$ <?= number_format($line['line_total'], 2) ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <?php if ($recurring_preview): ?>
                                                    <div class="alert alert-light border small mb-3">
                                                        <strong>其後週期（第一期之後）：</strong>
                                                        <ul class="mb-0 mt-2">
                                                            <?php foreach ($recurring_preview as $rp): ?>
                                                            <li><?= htmlspecialchars($rp['title']) ?> — HK$ <?= number_format($rp['line_total'], 2) ?> / <?= htmlspecialchars(quote_billing_labels()[$rp['billing_type']]['zh']) ?>，下次 <?= quote_next_period_date(date('Y-m-d'), $rp['billing_type']) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="create_project" id="cp<?= (int)$q['id'] ?>" checked>
                                                        <label class="form-check-label" for="cp<?= (int)$q['id'] ?>">同時建立專案（預算 = 首年合約值 HK$ <?= number_format($year_one, 0) ?>）</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pb-4 px-4">
                                                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">取消</button>
                                                    <button type="submit" name="convert_quote" class="btn btn-primary">確認轉換</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if (empty($quotes)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="bi bi-file-earmark-x fs-1 d-block mb-2 opacity-50"></i>
                                        尚未有報價單
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination shadow-sm">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">上一頁</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link <?= $i == $page ? 'bg-primary border-primary' : 'text-slate-500' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link text-slate-500" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">下一頁</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

<?php include 'includes/footer.php'; ?>
