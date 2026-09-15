<?php
/* ===================================================================
   PAYMENT GATEWAY INTERFACE
   -------------------------------------------------------------------
   Every payment gateway (Razorpay now; others later) implements this.
   Nothing outside includes/payments/ ever calls a gateway class
   directly - only PaymentManager does, through this interface. That's
   what makes adding a new gateway later a matter of writing one new
   class and registering it in PaymentManager, with zero changes to
   checkout.php, PaymentManager's public methods, or any business
   logic.

   Every method here is deliberately generic:
   - createOrder()/verifyPayment() return/accept plain arrays, so
     each gateway's own field names never leak into a fixed shape
   - handleWebhook() takes a $headers ARRAY (not a single signature
     string) because gateways don't all sign the same way - Razorpay
     needs one header; others may need more. Each gateway class
     reads whichever keys IT needs and ignores the rest; the caller
     (a webhook-<gateway>.php endpoint) just builds whatever headers
     that gateway's docs say it signs with. PaymentManager never
     inspects these keys itself - it only ever passes the array
     through.
   - verification MECHANISM is entirely up to the gateway class: a
     cryptographic signature check (Razorpay), or whatever a future
     gateway uses. PaymentManager and checkout.php only ever see a
     plain true/false.
=================================================================== */

interface PaymentGatewayInterface
{
    /**
     * Create a payment order with the gateway for one of our orders.
     *
     * $order is the full `orders` table row (already loaded by the
     * caller). Returns an array the caller can use to render that
     * gateway's checkout widget - shape differs per gateway (e.g.
     * Razorpay returns a 'key_id' + 'gateway_order_id' pair to hand
     * to Checkout.js) - always includes 'gateway_order_id'
     * and 'raw' (the full decoded API response, for logging).
     */
    public function createOrder(array $order): array;

    /**
     * Confirm whether a payment actually succeeded. $callbackData is
     * whatever the payment page collected after the gateway's widget
     * finished (field names/contents differ per gateway - each class
     * reads only what it needs). The MECHANISM is entirely up to the
     * gateway: verifying a cryptographic signature the widget handed
     * back (Razorpay). Either way, returns a plain bool - callers
     * never need to know which approach was used.
     */
    public function verifyPayment(array $callbackData): bool;

    /**
     * Verify and interpret a server-to-server webhook call from the
     * gateway - the authoritative source of truth for payment status,
     * since it doesn't depend on the customer's browser still being
     * open.
     *
     * $rawPayload is the exact raw request body (needed as-is for
     * signature verification, never re-encoded JSON).
     * $headers is a generic associative array built by that gateway's
     * webhook-<gateway>.php endpoint from whatever HTTP headers its
     * signing scheme needs (e.g. Razorpay: ['signature' => ...]) - the keys
     * are a contract between each gateway class and its own webhook
     * endpoint file only; nothing else in the project reads them.
     *
     * Returns ['valid' => bool, 'gateway_order_id' => ?string,
     * 'gateway_payment_id' => ?string, 'status' => 'paid'|'failed'|null,
     * 'raw' => <decoded payload>].
     */
    public function handleWebhook(string $rawPayload, array $headers): array;

    /**
     * Are this gateway's required credentials configured? Checked by
     * every gateway's own constructor (throwing
     * PaymentConfigurationException immediately if not, before any
     * API call is attempted) - part of the required contract so a
     * future gateway can't skip this safety check.
     */
    public static function isConfigured(): bool;
}
