<?php
/**
 * 順序單號：QT-YYYYMM-001 / INV-YYYYMM-001
 * 舊發票 INV-YYYYMMDD-XXX 格式不會被新規則匹配，可並存。
 */

function next_document_number(string $table, string $column, string $prefix): string {
    $ym = date('Ym');
    $like = $prefix . '-' . $ym . '-%';
    $row = db_fetch_one(
        "SELECT `$column` AS n FROM `$table` WHERE `$column` LIKE ? ORDER BY `$column` DESC LIMIT 1",
        [$like]
    );

    $seq = 1;
    if (!empty($row['n']) && preg_match('/-(\d+)$/', $row['n'], $m)) {
        $seq = ((int)$m[1]) + 1;
    }

    return $prefix . '-' . $ym . '-' . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);
}

function next_quote_number(): string {
    return next_document_number('quotes', 'quote_number', 'QT');
}

function next_invoice_number(): string {
    return next_document_number('invoices', 'invoice_number', 'INV');
}

function insert_with_number_retry(string $table, array $data, string $column, callable $number_fn, int $attempts = 8): string {
    $last = '';
    for ($i = 0; $i < $attempts; $i++) {
        $data[$column] = $number_fn();
        $last = $data[$column];
        try {
            db_insert($table, $data);
            return $last;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate') === false && $e->getCode() != 23000) {
                throw $e;
            }
        }
    }
    throw new RuntimeException("無法產生唯一單號（{$column}），最後嘗試：{$last}");
}
