<?php
require_once __DIR__ . '/numbering.php';

function expire_overdue_invoices(): int {
    try {
        $stmt = db_query(
            "UPDATE invoices
             SET status = 'overdue'
             WHERE status = 'sent' AND due_date < CURDATE()"
        );
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function sync_task_logged_hours(?int $task_id): void {
    if (!$task_id) {
        return;
    }
    try {
        $row = db_fetch_one(
            "SELECT COALESCE(SUM(hours), 0) AS h FROM timesheets WHERE task_id = ? AND is_approved = 1",
            [$task_id]
        );
        db_update('tasks', ['logged_hours' => (float)($row['h'] ?? 0)], 'id = ?', [$task_id]);
    } catch (Throwable $e) {
        // 舊庫無 logged_hours 時略過
    }
}

function advance_recurring_date(string $from, string $frequency): string {
    $base = $from . ' 00:00:00';
    switch ($frequency) {
        case 'quarterly':
            return date('Y-m-d', strtotime($base . ' +3 months'));
        case 'yearly':
            return date('Y-m-d', strtotime($base . ' +1 year'));
        case 'every_30_days':
            return date('Y-m-d', strtotime($base . ' +30 days'));
        case 'monthly':
        default:
            return date('Y-m-d', strtotime($base . ' +1 month'));
    }
}

function generate_recurring_invoice(array $recurring, int $user_id): array {
    if (($recurring['status'] ?? '') !== 'active') {
        throw new RuntimeException('此週期規則並非活躍狀態。');
    }
    $end = $recurring['contract_end_date'] ?? null;
    if ($end && $end < date('Y-m-d')) {
        db_update('recurring_invoices', ['status' => 'ended'], 'id = ?', [(int)$recurring['id']]);
        throw new RuntimeException('合約已到期，已自動結束此週期規則。請先續約。');
    }

    $invoice_data = [
        'invoice_number' => next_invoice_number(),
        'client_id' => $recurring['client_id'],
        'project_id' => $recurring['project_id'] ?: null,
        'quote_id' => $recurring['quote_id'] ?? null,
        'source' => 'recurring',
        'issue_date' => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'subtotal' => $recurring['amount'],
        'tax_percent' => 0,
        'total_amount' => $recurring['amount'],
        'status' => 'draft',
        'notes' => $recurring['notes'] ?? '',
        'created_by' => $user_id,
    ];

    try {
        $inv_id = (int)db_insert('invoices', $invoice_data);
    } catch (PDOException $e) {
        $invoice_data['invoice_number'] = next_invoice_number();
        $inv_id = (int)db_insert('invoices', $invoice_data);
    }

    try {
        db_insert('invoice_items', [
            'invoice_id' => $inv_id,
            'sort_order' => 0,
            'title' => $recurring['title'],
            'description' => $recurring['notes'] ?? '',
            'qty' => 1,
            'unit' => '期',
            'unit_price' => $recurring['amount'],
            'line_total' => $recurring['amount'],
        ]);
    } catch (Throwable $e) {
        // invoice_items 尚未 migration 時略過
    }

    $next = advance_recurring_date($recurring['next_invoice_date'], $recurring['frequency'] ?? 'monthly');
    db_update('recurring_invoices', ['next_invoice_date' => $next], 'id = ?', [(int)$recurring['id']]);

    return [
        'invoice_id' => $inv_id,
        'invoice_number' => $invoice_data['invoice_number'],
        'next_invoice_date' => $next,
    ];
}

function due_recurring_invoices(): array {
    try {
        return db_fetch_all(
            "SELECT r.*, c.company_name
             FROM recurring_invoices r
             LEFT JOIN clients c ON c.id = r.client_id
             WHERE r.status = 'active' AND r.next_invoice_date <= CURDATE()
             ORDER BY r.next_invoice_date ASC"
        ) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function replace_invoice_items_from_subtotal(int $invoice_id, float $subtotal, string $title, string $description = ''): void {
    try {
        db_delete('invoice_items', 'invoice_id = ?', [$invoice_id]);
        db_insert('invoice_items', [
            'invoice_id' => $invoice_id,
            'sort_order' => 0,
            'title' => $title !== '' ? $title : 'Professional Services & Consulting',
            'description' => $description,
            'qty' => 1,
            'unit' => '項',
            'unit_price' => $subtotal,
            'line_total' => $subtotal,
        ]);
    } catch (Throwable $e) {
        // 無 invoice_items 表時略過
    }
}
