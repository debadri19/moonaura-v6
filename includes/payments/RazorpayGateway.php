<?php
/* ===================================================================
   RAZORPAY GATEWAY
   -------------------------------------------------------------------
   Implements PaymentGatewayInterface using raw cURL against
   Razorpay's REST API (no Composer SDK, consistent with the rest of
   this project having zero external dependencies).

   Test vs. live mode: entirely a function of which key pair is in
   config.php's RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET (via .env) - a
   "rzp_test_..." key talks to Razorpay's test environment
   automatically. Nothing in this class branches on mode.
=================================================================== */

require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/PaymentConfigurationException.php';

class RazorpayGateway implements PaymentGatewayInterface
{
    private const API_BASE = 'https://api.razorpay.com/v1';


    /* ==========================================
       FAIL FAST IF NOT CONFIGURED
       -------------------------------------------------
       Every entry point into this class (createOrder,
       verifyPayment, handleWebhook - all reached only
       via PaymentManager::resolveGateway()'s `new
       $className()`) goes through here first. Checked
       in the constructor rather than only in
       createOrder() so a missing config is caught
       "before attempting to initialize the Razorpay
       API" no matter which method is called first.
    ========================================== */

    public function __construct()
    {
        if (!self::isConfigured()) {
            throw new PaymentConfigurationException(
                'Razorpay Test API credentials are not configured. ' .
                'Please configure your Razorpay Test Keys or use Cash on Delivery.'
            );
        }
    }


    /* ==========================================
       ARE CREDENTIALS PRESENT?
       Only checks that RAZORPAY_KEY_ID/SECRET are
       non-empty - it does not (and cannot, without
       calling the API) verify they're valid. That's
       still handled by apiRequest()'s normal error
       handling on the API's response.
    ========================================== */

    public static function isConfigured(): bool
    {
        return RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_SECRET !== '';
    }


    /* ==========================================
       CREATE A RAZORPAY ORDER
    ========================================== */

    public function createOrder(array $order): array
    {
        // Razorpay wants the amount in paise (smallest currency unit),
        // as an integer - never a float, to avoid rounding surprises.
        $amountInPaise = (int) round(((float) $order['grand_total']) * 100);

        $response = $this->apiRequest('POST', '/orders', [
            'amount'   => $amountInPaise,
            'currency' => $order['currency'] ?? 'INR',
            'receipt'  => $order['order_number'],
            // Auto-capture the payment immediately on success, rather
            // than requiring a separate manual "capture" API call -
            // the simplest correct choice for this phase (no partial
            // payments/holds are in scope).
            'payment_capture' => 1,
        ]);

        return [
            'gateway_order_id' => $response['id'] ?? null,
            'amount'           => $amountInPaise,
            'currency'         => $order['currency'] ?? 'INR',
            'key_id'           => RAZORPAY_KEY_ID,
            'raw'              => $response,
        ];
    }


    /* ==========================================
       VERIFY A CLIENT-SIDE PAYMENT CALLBACK
       -------------------------------------------------
       Razorpay's checkout widget hands the browser
       razorpay_order_id, razorpay_payment_id, and
       razorpay_signature on success. The signature is
       HMAC-SHA256 of "<order_id>|<payment_id>" using the
       key secret - if it doesn't match exactly, the data
       cannot be trusted (could be forged/tampered).
    ========================================== */

    public function verifyPayment(array $callbackData): bool
    {
        $gatewayOrderId   = $callbackData['razorpay_order_id'] ?? '';
        $gatewayPaymentId = $callbackData['razorpay_payment_id'] ?? '';
        $signature        = $callbackData['razorpay_signature'] ?? '';

        if ($gatewayOrderId === '' || $gatewayPaymentId === '' || $signature === '') {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $gatewayOrderId . '|' . $gatewayPaymentId,
            RAZORPAY_KEY_SECRET
        );

        return hash_equals($expectedSignature, $signature);
    }


    /* ==========================================
       VERIFY + INTERPRET A WEBHOOK CALL
       -------------------------------------------------
       Signature here is HMAC-SHA256 of the RAW request
       body (not re-encoded JSON - Razorpay signs the
       exact bytes they sent) using the separate Webhook
       Secret (configured in the Razorpay dashboard, not
       the same as the API key secret).
    ========================================== */

    public function handleWebhook(string $rawPayload, array $headers): array
    {
        $signatureHeader = $headers['signature'] ?? '';

        $expectedSignature = hash_hmac('sha256', $rawPayload, RAZORPAY_WEBHOOK_SECRET);

        if ($signatureHeader === '' || !hash_equals($expectedSignature, $signatureHeader)) {
            return ['valid' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'status' => null, 'raw' => null];
        }

        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            return ['valid' => false, 'gateway_order_id' => null, 'gateway_payment_id' => null, 'status' => null, 'raw' => null];
        }

        $event   = $payload['event'] ?? '';
        $payment = $payload['payload']['payment']['entity'] ?? [];

        $status = match ($event) {
            'payment.captured' => 'paid',
            'payment.failed'   => 'failed',
            default             => null, // an event we don't act on (e.g. order.paid) - not an error
        };

        return [
            'valid'              => true,
            'gateway_order_id'   => $payment['order_id'] ?? null,
            'gateway_payment_id' => $payment['id'] ?? null,
            'status'             => $status,
            'raw'                => $payload,
        ];
    }


    /* ==========================================
       RAW cURL REQUEST HELPER
       -------------------------------------------------
       Razorpay authenticates API requests with HTTP
       Basic Auth: username = key ID, password = key
       secret - no OAuth/token dance needed.
    ========================================== */

    private function apiRequest(string $method, string $path, array $body): array
    {
        $ch = curl_init(self::API_BASE . $path);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $responseBody = curl_exec($ch);
        $curlError    = curl_error($ch);
        $httpStatus   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('Razorpay API request failed (network error): ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);

        if ($httpStatus >= 400) {
            $message = $decoded['error']['description'] ?? ('HTTP ' . $httpStatus);
            throw new RuntimeException('Razorpay API error: ' . $message);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
