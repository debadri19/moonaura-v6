<?php
/* ===================================================================
   RAZORPAY WEBHOOK
   -------------------------------------------------------------------
   Called directly by Razorpay's servers, not a browser - there is no
   session and no CSRF token to check here, by necessity. Authenticity
   comes entirely from the HMAC-SHA256 signature in the
   X-Razorpay-Signature header, verified against the raw request body
   using RAZORPAY_WEBHOOK_SECRET (configured in the Razorpay dashboard
   webhook settings, separate from the API key secret).

   This is the AUTHORITATIVE source of truth for payment status - it
   still confirms the payment even if the customer's browser never
   made it back to payment-verify.php (closed tab, network drop,
   etc.). PaymentManager::handleWebhook() applies the same idempotency
   guard either way, so it's safe if both paths fire for the same
   payment.

   Must always respond quickly with a 2xx status on success, or
   Razorpay will retry the same event later.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/payments/PaymentManager.php';

$rawPayload = file_get_contents('php://input');
$signature  = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if ($rawPayload === '' || $signature === '') {
    http_response_code(400);
    exit;
}

// Razorpay only signs with one header - this project's other
// gateways may need more (see PaymentGatewayInterface::handleWebhook()),
// but each webhook-<gateway>.php endpoint only ever builds what ITS
// gateway needs.
$headers = ['signature' => $signature];

try {

    $result = PaymentManager::handleWebhook('razorpay', $rawPayload, $headers);

    if (!$result['valid']) {
        // Signature didn't match - reject outright, do not process.
        error_log('webhook-razorpay.php: invalid signature');
        http_response_code(400);
        exit;
    }

    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    error_log('webhook-razorpay.php: error handling webhook - ' . $e->getMessage());
    http_response_code(500);
}
