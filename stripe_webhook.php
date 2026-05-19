<?php
// stripe_webhook.php - Final Version (Recommended)
require_once 'config.php';
require_once 'includes/db.php';

// 如果你用 Composer 安裝了 stripe/stripe-php
require_once __DIR__ . '/vendor/autoload.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = STRIPE_WEBHOOK_SECRET;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

// 只處理 payment_intent.succeeded
if ($event->type === 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    $invoice_id = $paymentIntent->metadata->invoice_id ?? null;

    if ($invoice_id) {
        db_update('invoices', ['status' => 'paid'], 'id = ?', [$invoice_id]);
        error_log("Invoice #{$invoice_id} marked as PAID via Stripe");
    }
}

http_response_code(200);
echo json_encode(['status' => 'success']);