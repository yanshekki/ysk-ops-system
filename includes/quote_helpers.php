<?php
require_once __DIR__ . '/quote_catalog.php';
require_once __DIR__ . '/numbering.php';

function quote_status_map(): array {
    return [
        'draft'       => ['label' => '草稿', 'color' => 'secondary'],
        'sent'        => ['label' => '待回覆', 'color' => 'warning'],
        'accepted'    => ['label' => '已接受', 'color' => 'success'],
        'declined'    => ['label' => '已拒絕', 'color' => 'danger'],
        'expired'     => ['label' => '已過期', 'color' => 'dark'],
        'converted'   => ['label' => '已轉換', 'color' => 'primary'],
        'superseded'  => ['label' => '已修訂', 'color' => 'secondary'],
    ];
}

function quote_year_one_multiplier(string $billing_type): int {
    switch ($billing_type) {
        case 'monthly':
        case 'every_30_days':
            return 12;
        case 'quarterly':
            return 4;
        case 'yearly':
        case 'one_time':
        default:
            return 1;
    }
}

function quote_is_recurring_billing(string $billing_type): bool {
    return in_array($billing_type, ['monthly', 'quarterly', 'yearly', 'every_30_days'], true);
}

function quote_next_period_date(string $from, string $billing_type): string {
    $ts = strtotime($from . ' 00:00:00');
    if ($ts === false) {
        $ts = time();
    }
    switch ($billing_type) {
        case 'monthly':
            return date('Y-m-d', strtotime('+1 month', $ts));
        case 'quarterly':
            return date('Y-m-d', strtotime('+3 months', $ts));
        case 'yearly':
            return date('Y-m-d', strtotime('+1 year', $ts));
        case 'every_30_days':
            return date('Y-m-d', strtotime('+30 days', $ts));
        default:
            return date('Y-m-d', $ts);
    }
}

function quote_recurring_frequency(string $billing_type): ?string {
    if ($billing_type === 'one_time') {
        return null;
    }
    return $billing_type;
}

function quote_normalize_items(array $raw): array {
    $allowed = array_keys(quote_billing_labels());
    $items = [];
    $order = 0;
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = trim((string)($row['title'] ?? ''));
        $description = trim((string)($row['description'] ?? ''));
        if ($title === '' && $description === '') {
            continue;
        }
        $qty = (float)($row['qty'] ?? 1);
        $unit_price = (float)($row['unit_price'] ?? 0);
        $billing = (string)($row['billing_type'] ?? 'one_time');
        if (!in_array($billing, $allowed, true)) {
            $billing = 'one_time';
        }
        if ($qty <= 0) {
            throw new InvalidArgumentException('明細數量必須大於 0。');
        }
        if ($unit_price < 0) {
            throw new InvalidArgumentException('單價不可為負數。');
        }
        $unit = trim((string)($row['unit'] ?? ''));
        if ($unit === '') {
            $unit = quote_billing_labels()[$billing]['unit'];
        }
        $catalog_key = trim((string)($row['catalog_key'] ?? ''));
        $line_total = round($qty * $unit_price, 2);
        $items[] = [
            'sort_order' => $order++,
            'catalog_key' => $catalog_key !== '' ? $catalog_key : null,
            'title' => $title !== '' ? $title : '服務項目',
            'description' => $description,
            'billing_type' => $billing,
            'qty' => $qty,
            'unit' => $unit,
            'unit_price' => round($unit_price, 2),
            'line_total' => $line_total,
        ];
    }
    if (!$items) {
        throw new InvalidArgumentException('請至少新增一項服務明細。');
    }
    return $items;
}

function quote_compute_totals(array $items, float $discount, float $tax_percent): array {
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float)$item['line_total'];
    }
    $subtotal = round($subtotal, 2);
    if ($discount < 0) {
        throw new InvalidArgumentException('折扣不可為負數。');
    }
    if ($discount > $subtotal) {
        throw new InvalidArgumentException('折扣不可大於小計。');
    }
    if ($tax_percent < 0) {
        throw new InvalidArgumentException('稅率不可為負數。');
    }
    $after = round($subtotal - $discount, 2);
    $tax_amount = round($after * ($tax_percent / 100), 2);
    $total = round($after + $tax_amount, 2);
    return [
        'subtotal' => $subtotal,
        'discount_amount' => round($discount, 2),
        'tax_percent' => round($tax_percent, 2),
        'tax_amount' => $tax_amount,
        'total_amount' => $total,
        'year_one_value' => quote_year_one_value($items),
    ];
}

function quote_year_one_value(array $items): float {
    $sum = 0.0;
    foreach ($items as $item) {
        $sum += (float)$item['line_total'] * quote_year_one_multiplier($item['billing_type'] ?? 'one_time');
    }
    return round($sum, 2);
}

function quote_first_invoice_items(array $items): array {
    $out = [];
    foreach ($items as $i => $item) {
        $title = $item['title'];
        if (quote_is_recurring_billing($item['billing_type'])) {
            $label = quote_billing_labels()[$item['billing_type']]['zh'];
            $title .= '（首期 · ' . $label . '）';
        }
        $out[] = [
            'sort_order' => $i,
            'title' => $title,
            'description' => $item['description'] ?? '',
            'qty' => $item['qty'],
            'unit' => $item['unit'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total'],
        ];
    }
    return $out;
}

function quote_expire_stale(): int {
    $stmt = db_query(
        "UPDATE quotes SET status = 'expired' WHERE status = 'sent' AND valid_until < CURDATE()"
    );
    return $stmt->rowCount();
}

function quote_fetch(int $id): ?array {
    $row = db_fetch_one(
        "SELECT q.*, c.company_name, c.contact_person, c.email, c.phone, c.address, c.status AS client_status
         FROM quotes q
         JOIN clients c ON c.id = q.client_id
         WHERE q.id = ?",
        [$id]
    );
    return $row ?: null;
}

function quote_fetch_items(int $quote_id): array {
    return db_fetch_all(
        "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC, id ASC",
        [$quote_id]
    );
}

function quote_is_valid_for_action(array $quote): bool {
    return ($quote['valid_until'] ?? '') >= date('Y-m-d');
}

function quote_can(string $action, array $quote): bool {
    $status = $quote['status'] ?? '';
    switch ($action) {
        case 'edit':
        case 'delete':
            return $status === 'draft';
        case 'send':
            return $status === 'draft';
        case 'accept':
        case 'decline':
            return $status === 'sent' && quote_is_valid_for_action($quote);
        case 'convert':
            if (!empty($quote['converted_invoice_id'])) {
                return false;
            }
            return $status === 'accepted';
        case 'revise':
            return $status === 'sent';
        case 'duplicate':
            return in_array($status, ['declined', 'expired', 'converted', 'superseded', 'accepted', 'sent', 'draft'], true);
        default:
            return false;
    }
}

function quote_replace_items(int $quote_id, array $items): void {
    db_delete('quote_items', 'quote_id = ?', [$quote_id]);
    foreach ($items as $item) {
        db_insert('quote_items', [
            'quote_id' => $quote_id,
            'sort_order' => (int)$item['sort_order'],
            'catalog_key' => $item['catalog_key'],
            'title' => $item['title'],
            'description' => $item['description'],
            'billing_type' => $item['billing_type'],
            'qty' => $item['qty'],
            'unit' => $item['unit'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total'],
        ]);
    }
}

function quote_save(array $header, array $items, ?int $quote_id = null): int {
    $totals = quote_compute_totals(
        $items,
        (float)($header['discount_amount'] ?? 0),
        (float)($header['tax_percent'] ?? 0)
    );

    $data = [
        'client_id' => (int)$header['client_id'],
        'title' => trim((string)$header['title']),
        'intro_text' => trim((string)($header['intro_text'] ?? '')),
        'issue_date' => $header['issue_date'],
        'valid_until' => $header['valid_until'],
        'tax_percent' => $totals['tax_percent'],
        'discount_amount' => $totals['discount_amount'],
        'subtotal' => $totals['subtotal'],
        'total_amount' => $totals['total_amount'],
        'notes' => trim((string)($header['notes'] ?? '')),
        'terms' => trim((string)($header['terms'] ?? '')),
    ];

    if ($data['title'] === '') {
        throw new InvalidArgumentException('請填寫報價標題。');
    }
    if ($data['client_id'] <= 0) {
        throw new InvalidArgumentException('請選擇客戶。');
    }
    if ($data['valid_until'] < $data['issue_date']) {
        throw new InvalidArgumentException('有效期不可早於開立日期。');
    }

    return db_transaction(function () use ($quote_id, $data, $items, $header) {
        if ($quote_id) {
            $existing = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
            if (!$existing) {
                throw new RuntimeException('找不到報價單。');
            }
            if ($existing['status'] !== 'draft') {
                throw new RuntimeException('只有草稿可以修改內容。');
            }
            db_update('quotes', $data, 'id = ?', [$quote_id]);
            quote_replace_items($quote_id, $items);
            return $quote_id;
        }

        $data['status'] = 'draft';
        $data['created_by'] = (int)($header['created_by'] ?? 0);
        $data['quote_number'] = next_quote_number();
        try {
            $id = (int)db_insert('quotes', $data);
        } catch (PDOException $e) {
            $data['quote_number'] = next_quote_number();
            $id = (int)db_insert('quotes', $data);
        }
        quote_replace_items($id, $items);
        return $id;
    });
}

function quote_clone(int $source_id, string $mode, int $user_id): int {
    $quote = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$source_id]);
    if (!$quote) {
        throw new RuntimeException('找不到報價單。');
    }
    $items = quote_fetch_items($source_id);

    return db_transaction(function () use ($quote, $items, $mode, $user_id, $source_id) {
        if ($mode === 'revise') {
            if (($quote['status'] ?? '') !== 'sent') {
                throw new RuntimeException('只有待回覆的報價可以建立修訂。');
            }
            db_update('quotes', ['status' => 'superseded'], 'id = ?', [$source_id]);
        }

        $data = [
            'quote_number' => next_quote_number(),
            'client_id' => $quote['client_id'],
            'project_id' => null,
            'title' => $quote['title'],
            'intro_text' => $quote['intro_text'],
            'status' => 'draft',
            'issue_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+14 days')),
            'tax_percent' => $quote['tax_percent'],
            'discount_amount' => $quote['discount_amount'],
            'subtotal' => $quote['subtotal'],
            'total_amount' => $quote['total_amount'],
            'notes' => $quote['notes'],
            'terms' => $quote['terms'],
            'created_by' => $user_id,
            'revision_of_id' => $mode === 'revise' ? $source_id : null,
        ];
        try {
            $new_id = (int)db_insert('quotes', $data);
        } catch (PDOException $e) {
            $data['quote_number'] = next_quote_number();
            $new_id = (int)db_insert('quotes', $data);
        }

        $normalized = [];
        foreach ($items as $i => $item) {
            $normalized[] = [
                'sort_order' => $i,
                'catalog_key' => $item['catalog_key'],
                'title' => $item['title'],
                'description' => $item['description'],
                'billing_type' => $item['billing_type'],
                'qty' => $item['qty'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ];
        }
        quote_replace_items($new_id, $normalized);
        return $new_id;
    });
}

function quote_set_status(int $id, string $to, array $extra = []): void {
    $quote = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$id]);
    if (!$quote) {
        throw new RuntimeException('找不到報價單。');
    }

    $action = $to === 'sent' ? 'send' : $to;
    if ($to === 'sent') {
        $action = 'send';
    }
    if (!quote_can($action, $quote) && !($to === 'expired')) {
        throw new RuntimeException('此報價單目前狀態不允許該操作。');
    }

    $data = array_merge(['status' => $to], $extra);
    db_update('quotes', $data, 'id = ?', [$id]);
}

function quote_promote_lead(int $client_id): void {
    db_query("UPDATE clients SET status = 'active' WHERE id = ? AND status = 'lead'", [$client_id]);
}

function quote_convert(int $quote_id, int $user_id, bool $create_project = true, ?int $assigned_pm_id = null, bool $send_invoice = false): array {
    return db_transaction(function () use ($quote_id, $user_id, $create_project, $assigned_pm_id, $send_invoice) {
        $quote = db_fetch_one("SELECT * FROM quotes WHERE id = ? FOR UPDATE", [$quote_id]);
        if (!$quote) {
            throw new RuntimeException('找不到報價單。');
        }
        quote_expire_stale();
        $quote = db_fetch_one("SELECT * FROM quotes WHERE id = ?", [$quote_id]);
        if (!quote_can('convert', $quote)) {
            throw new RuntimeException('此報價單不能轉換（可能已過期、已轉換或狀態不正確）。');
        }

        $items = quote_fetch_items($quote_id);
        if (!$items) {
            throw new RuntimeException('報價單沒有明細，無法轉換。');
        }

        $invoice_lines = quote_first_invoice_items($items);
        $items_sum = 0.0;
        foreach ($invoice_lines as $line) {
            $items_sum += (float)$line['line_total'];
        }
        $items_sum = round($items_sum, 2);
        if ($items_sum <= 0) {
            throw new RuntimeException('報價金額必須大於 0 才能轉換為發票。');
        }

        $discount = (float)$quote['discount_amount'];
        $invoice_subtotal = round(max(0, $items_sum - $discount), 2);
        $tax_percent = (float)$quote['tax_percent'];
        $invoice_total = round($invoice_subtotal + ($invoice_subtotal * ($tax_percent / 100)), 2);

        $project_id = $quote['project_id'] ? (int)$quote['project_id'] : null;
        if ($create_project && !$project_id) {
            $map_key = null;
            foreach ($items as $item) {
                if (!empty($item['catalog_key'])) {
                    $map_key = $item['catalog_key'];
                    break;
                }
            }
            $project_id = (int)db_insert('projects', [
                'client_id' => (int)$quote['client_id'],
                'title' => $quote['title'],
                'description' => '由報價單 ' . $quote['quote_number'] . ' 轉換。',
                'service_type' => quote_map_service_type($map_key),
                'status' => 'planning',
                'start_date' => date('Y-m-d'),
                'end_date' => null,
                'budget' => quote_year_one_value($items),
                'progress_percent' => 0,
                'assigned_pm_id' => $assigned_pm_id ?: null,
                'created_by' => $user_id,
                'quote_id' => $quote_id,
            ]);
        } elseif ($project_id) {
            db_query("UPDATE projects SET quote_id = ? WHERE id = ? AND quote_id IS NULL", [$quote_id, $project_id]);
        }

        $invoice_number = next_invoice_number();
        $invoice_id = null;
        $invoice_payload = [
            'invoice_number' => $invoice_number,
            'client_id' => (int)$quote['client_id'],
            'project_id' => $project_id,
            'quote_id' => $quote_id,
            'source' => 'quote',
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+14 days')),
            'subtotal' => $invoice_subtotal,
            'tax_percent' => $tax_percent,
            'total_amount' => $invoice_total,
            'status' => $send_invoice ? 'sent' : 'draft',
            'notes' => '由報價單 ' . $quote['quote_number'] . ' 轉換。週期項目的第一期已包含在本發票；其後由週期發票產生，避免重複收費。'
                . ($quote['notes'] ? "\n\n" . $quote['notes'] : ''),
            'created_by' => $user_id,
        ];
        try {
            $invoice_id = (int)db_insert('invoices', $invoice_payload);
        } catch (PDOException $e) {
            $invoice_payload['invoice_number'] = next_invoice_number();
            $invoice_number = $invoice_payload['invoice_number'];
            $invoice_id = (int)db_insert('invoices', $invoice_payload);
        }

        foreach ($invoice_lines as $line) {
            db_insert('invoice_items', [
                'invoice_id' => $invoice_id,
                'sort_order' => $line['sort_order'],
                'title' => $line['title'],
                'description' => $line['description'],
                'qty' => $line['qty'],
                'unit' => $line['unit'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
            ]);
        }
        if ($discount > 0) {
            db_insert('invoice_items', [
                'invoice_id' => $invoice_id,
                'sort_order' => count($invoice_lines) + 1,
                'title' => '報價折扣',
                'description' => '只適用於本張發票（由報價單 ' . $quote['quote_number'] . ' 轉入），不影響其後週期金額。',
                'qty' => 1,
                'unit' => '項',
                'unit_price' => -$discount,
                'line_total' => -$discount,
            ]);
        }

        $first_recurring_id = null;
        $today = date('Y-m-d');
        foreach ($items as $item) {
            $freq = quote_recurring_frequency($item['billing_type']);
            if (!$freq) {
                continue;
            }
            $recurring_row = [
                'client_id' => (int)$quote['client_id'],
                'project_id' => $project_id,
                'quote_id' => $quote_id,
                'title' => $item['title'],
                'amount' => $item['line_total'],
                'frequency' => $freq,
                'start_date' => $today,
                'next_invoice_date' => quote_next_period_date($today, $item['billing_type']),
                'contract_end_date' => date('Y-m-d', strtotime('+1 year')),
                'status' => 'active',
                'notes' => '由報價單 ' . $quote['quote_number'] . ' 轉換。第一期已在發票 ' . $invoice_number . ' 收取。',
                'created_by' => $user_id,
            ];
            try {
                $rid = (int)db_insert('recurring_invoices', $recurring_row);
            } catch (PDOException $e) {
                unset($recurring_row['contract_end_date']);
                $rid = (int)db_insert('recurring_invoices', $recurring_row);
            }
            if ($first_recurring_id === null) {
                $first_recurring_id = $rid;
            }
        }

        quote_promote_lead((int)$quote['client_id']);

        db_update('quotes', [
            'status' => 'converted',
            'accepted_at' => $quote['accepted_at'] ?: date('Y-m-d H:i:s'),
            'project_id' => $project_id,
            'converted_invoice_id' => $invoice_id,
            'converted_project_id' => $project_id,
            'converted_recurring_id' => $first_recurring_id,
        ], 'id = ?', [$quote_id]);

        return [
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'project_id' => $project_id,
            'recurring_id' => $first_recurring_id,
        ];
    });
}
