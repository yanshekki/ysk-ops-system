<?php
require_once 'config.php';
require_once 'includes/db.php';

// Stripe Webhook (Stripe CLI test: stripe listen --forward-to "https://yourdomain.com/stripe_webhook.php")
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = STRIPE_WEBHOOK_SECRET; // 請在 config.php 設定

$event = null;

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// Handle the event
if ($event->type == 'payment_intent.succeeded') {
    $paymentIntent = $event->data->object;
    $invoice_id = $paymentIntent->metadata->invoice_id ?? null;
    
    if ($invoice_id) {
        db_update('invoices', ['status' => 'paid'], 'id = ?', [$invoice_id]);
        // 可選：記錄 log
    }
}

http_response_code(200);
?>