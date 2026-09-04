<?php
/**
 * 報價邏輯核對（唔使資料庫）
 */
require_once __DIR__ . '/../includes/quote_catalog.php';

function fail($m) { fwrite(STDERR, "FAIL: $m\n"); exit(1); }
function ok($m) { echo "OK: $m\n"; }

// 重用 helpers 內純函式：直接 include 會用到 numbering，但未呼叫 db 即可
require_once __DIR__ . '/../includes/quote_helpers.php';

$groups = quote_catalog_groups();
$flat = quote_catalog_flat();
if (count($flat) < 10) fail('catalog too small');
if (!isset($flat['remote_basic']) || $flat['remote_basic']['unit_price'] != 2000) fail('basic plan price');
if (!isset($flat['llm_hosted']) || $flat['llm_hosted']['unit_price'] != 88000) fail('llm price');
if ($flat['salonease_std']['billing_type'] !== 'every_30_days') fail('salonease billing');
ok('catalog prices');

$items = quote_normalize_items([
    ['title' => 'APP', 'description' => 'x', 'billing_type' => 'one_time', 'qty' => 1, 'unit' => '項', 'unit_price' => 80000],
    ['title' => 'Basic', 'description' => 'y', 'billing_type' => 'monthly', 'qty' => 1, 'unit' => '月', 'unit_price' => 2000],
]);
$totals = quote_compute_totals($items, 0, 0);
if ($totals['subtotal'] != 82000) fail('subtotal first period '.$totals['subtotal']);
if ($totals['year_one_value'] != 104000) fail('year one '.$totals['year_one_value']);
ok('mixed one_time + monthly totals');

$disc = quote_compute_totals($items, 2000, 0);
if ($disc['total_amount'] != 80000) fail('discount first invoice');
ok('discount only reduces first invoice total');

try {
    quote_compute_totals($items, 999999, 0);
    fail('discount > subtotal should throw');
} catch (InvalidArgumentException $e) {
    ok('discount cap');
}

$next = quote_next_period_date('2026-09-04', 'monthly');
if ($next !== '2026-10-04') fail("monthly next $next");
$next30 = quote_next_period_date('2026-09-04', 'every_30_days');
if ($next30 !== '2026-10-04') fail("30d next $next30");
ok('next period skips first period');

$sent = ['status' => 'sent', 'valid_until' => '2026-09-20', 'converted_invoice_id' => null];
$expired_date = ['status' => 'sent', 'valid_until' => '2020-01-01', 'converted_invoice_id' => null];
$accepted = ['status' => 'accepted', 'valid_until' => '2020-01-01', 'converted_invoice_id' => null];
$converted = ['status' => 'converted', 'valid_until' => '2026-12-01', 'converted_invoice_id' => 9];

if (quote_can('convert', $sent)) fail('sent must not convert until accepted');
if (quote_can('convert', $expired_date)) fail('expired sent must not convert');
if (!quote_can('convert', $accepted)) fail('accepted can convert even after valid_until');
if (quote_can('convert', $converted)) fail('double convert');
if (quote_can('edit', $sent)) fail('sent not editable');
if (quote_can('accept', $expired_date)) fail('expired not accept');
ok('status machine');

$first = quote_first_invoice_items($items);
if (count($first) !== 2) fail('first invoice lines');
if (strpos($first[1]['title'], '首期') === false) fail('recurring line marked first period');
ok('first invoice includes recurring first period');

if (quote_map_service_type('llm_hosted') !== 'ai_automation') fail('map llm');
if (quote_map_service_type('remote_basic') !== 'other') fail('map remote');
ok('service type map');

echo "ALL LOGIC CHECKS PASSED\n";
