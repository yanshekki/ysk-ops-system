<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

$invoice_id = $_GET['invoice_id'] ?? 0;
$invoice = db_fetch_one("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);

if (!$invoice) {
    die('Invoice not found');
}

$client = db_fetch_one("SELECT * FROM clients WHERE id = ?", [$invoice['client_id']]);
?>
<?php $page_title = "Stripe 支付"; ?>
<?php include 'includes/header.php'; ?>
<script src="https://js.stripe.com/v3/"></script>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">使用 Stripe 支付</h4>
                </div>
                <div class="card-body">
                    <h5>發票 #<?= $invoice['invoice_number'] ?></h5>
                    <p class="text-muted">客戶：<?= htmlspecialchars($client['company_name']) ?></p>
                    
                    <div class="alert alert-info">
                        <strong>金額：</strong> HK$ <?= number_format($invoice['total_amount'], 2) ?><br>
                        <strong>到期日：</strong> <?= $invoice['due_date'] ?>
                    </div>
                    
                    <form id="payment-form">
                        <div id="card-element" class="form-control mb-3" style="height: 50px;"></div>
                        <div id="card-errors" class="text-danger mb-3"></div>
                        
                        <button type="submit" class="btn btn-success btn-lg w-100" id="submit-button">
                            支付 HK$ <?= number_format($invoice['total_amount'], 2) ?>
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">支持 Visa / Mastercard / Apple Pay / Google Pay</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const stripe = Stripe('pk_test_your_stripe_publishable_key'); // TODO: Replace with real key

const elements = stripe.elements();
const card = elements.create('card');
card.mount('#card-element');

const form = document.getElementById('payment-form');
const submitButton = document.getElementById('submit-button');

const clientSecret = '<?= $invoice['stripe_payment_intent_id'] ?? 'pi_test_123' ?>'; // TODO: Generate real Payment Intent

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';
    
    // In production, create Payment Intent on server and confirm here
    // For demo, simulate success
    setTimeout(() => {
        alert('Payment successful! (Demo)');
        window.location.href = 'invoices.php?paid=1';
    }, 1500);
});
</script>
</body>
</html>