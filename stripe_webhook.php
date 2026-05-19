<?php
// Stripe Webhook Handler - Complete Version v2.0
// This endpoint receives events from Stripe and updates invoice status automatically

require_once 'config.php';
require_once 'includes/db.php';

// TODO: Replace with your actual Stripe keys from Dashboard
$stripe_secret_key = 'sk_test_YOUR_SECRET_KEY_HERE';
$endpoint_secret = 'whsec_YOUR_WEBHOOK_SECRET_HERE';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    if (empty($sig_header)) {
        http_response_code(400);
        die('No signature header');
    }

    $event = json_decode($payload, true);

    if (!$event || !isset($event['type'])) {
        http_response_code(400);
        die('Invalid payload');
    }

    error_log('Stripe Webhook: ' . $event['type']);

    switch ($event['type']) {
        case 'payment_intent.succeeded':
            $payment_intent = $event['data']['object'];
            $invoice_id = $payment_intent['metadata']['invoice_id'] ?? null;

            if ($invoice_id) {
                db_query("UPDATE invoices SET status = 'paid', updated_at = NOW() WHERE id = ?", [$invoice_id]);
                error_log("Invoice #{$invoice_id} marked as PAID");
            }
            break;

        case 'payment_intent.payment_failed':
            $payment_intent = $event['data']['object'];
            $invoice_id = $payment_intent['metadata']['invoice_id'] ?? null;

            if ($invoice_id) {
                db_query("UPDATE invoices SET status = 'overdue', updated_at = NOW() WHERE id = ?", [$invoice_id]);
                error_log("Invoice #{$invoice_id} payment failed");
            }
            break;

        default:
            error_log('Unhandled event: ' . $event['type']);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log('Stripe Webhook Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>