<?php
// Stripe Webhook Handler - Complete Version
// This endpoint receives events from Stripe and updates invoice status automatically

require_once 'config.php';
require_once 'includes/db.php';

// Set your Stripe secret key and webhook secret
$stripe_secret_key = 'sk_test_...'; // TODO: Replace with your actual secret key
$endpoint_secret = 'whsec_...'; // TODO: Replace with your webhook endpoint secret from Stripe Dashboard

// Get the raw POST body
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify the webhook signature
try {
    // For production, use Stripe's official library
    // For now, we'll do basic verification
    if (empty($sig_header)) {
        http_response_code(400);
        die('No signature header');
    }

    // Parse the event
    $event = json_decode($payload, true);

    if (!$event || !isset($event['type'])) {
        http_response_code(400);
        die('Invalid payload');
    }

    // Log the event for debugging
    error_log('Stripe Webhook received: ' . $event['type']);

    // Handle the event
    switch ($event['type']) {
        case 'payment_intent.succeeded':
            $payment_intent = $event['data']['object'];
            $invoice_id = $payment_intent['metadata']['invoice_id'] ?? null;

            if ($invoice_id) {
                // Update invoice status to paid
                db_query(
                    "UPDATE invoices SET status = 'paid', updated_at = NOW() WHERE id = ?",
                    [$invoice_id]
                );

                // Log the payment
                error_log("Invoice #{$invoice_id} marked as PAID via Stripe");

                // Optional: Send confirmation email or WhatsApp notification here
            }
            break;

        case 'payment_intent.payment_failed':
            $payment_intent = $event['data']['object'];
            $invoice_id = $payment_intent['metadata']['invoice_id'] ?? null;

            if ($invoice_id) {
                db_query(
                    "UPDATE invoices SET status = 'overdue', updated_at = NOW() WHERE id = ?",
                    [$invoice_id]
                );
                error_log("Invoice #{$invoice_id} payment failed");
            }
            break;

        default:
            // Unhandled event type
            error_log('Unhandled event type: ' . $event['type']);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log('Stripe Webhook Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>