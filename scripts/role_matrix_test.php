<?php
/**
 * 角色權限矩陣（唔使資料庫）
 */
require_once __DIR__ . '/../includes/quote_helpers.php';

function fail($m) { fwrite(STDERR, "FAIL: $m\n"); exit(1); }
function ok($m) { echo "OK: $m\n"; }

$pages = [
    'clients.php' => ['pm','finance','viewer'],
    'projects.php' => ['pm','developer','finance','viewer'],
    'tasks.php' => ['pm','developer','viewer'],
    'quotes.php' => ['pm','finance','viewer'],
    'invoices.php' => ['pm','finance','viewer'],
    'timesheets.php' => ['pm','developer','finance'],
    'resource_utilization.php' => ['pm'],
    'profit_analysis.php' => ['pm','finance'],
    'users.php' => ['admin'],
];

function role_allowed($role, $allowed) {
    if ($role === 'admin') return true;
    return in_array($role, $allowed, true);
}

$roles = ['admin','pm','finance','developer','viewer'];
foreach ($pages as $page => $allowed) {
    foreach ($roles as $role) {
        $got = role_allowed($role, $allowed);
        $expect_dev_clients = !($page === 'clients.php' && $role === 'developer');
        if ($page === 'clients.php' && $role === 'developer' && $got) fail('developer should not access CRM');
        if ($role === 'admin' && !$got) fail("admin blocked from $page");
    }
}
ok('page allow-lists');

$sent = ['status' => 'sent', 'valid_until' => '2099-01-01', 'converted_invoice_id' => null];
$accepted = ['status' => 'accepted', 'valid_until' => '2000-01-01', 'converted_invoice_id' => null];
if (quote_can('convert', $sent)) fail('sent convert');
if (!quote_can('convert', $accepted)) fail('accepted convert');
if (!quote_can('accept', $sent)) fail('sent accept');
ok('quote convert requires accepted');

echo "ALL ROLE MATRIX CHECKS PASSED\n";
