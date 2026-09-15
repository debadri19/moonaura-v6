<?php
/* ===================================================================
   PAYMENT MANAGER
   -------------------------------------------------------------------
   The ONLY thing checkout.php and the payment pages talk to for
   gateway operations - they never reference RazorpayGateway (or any
   future gateway class) directly. Adding a new gateway later means:
     1. Write a new class implementing PaymentGatewayInterface
     2. Add one line to the $gatewayClasses map below
     3. Add its display label to $gatewayLabels
   Nothing else changes - not checkout.php, not payment.php, nothing.

   MULTI-GATEWAY MODEL (Phase 4C): replaced the old single
   "active_payment_gateway" setting with one *_enabled flag per
   gateway (razorpay_enabled) plus a default_payment_gateway
   setting that only controls which enabled option checkout
   pre-selects - never which options are shown. All of this still
   comes from the settings table (see includes/settings-functions.php),
   not a hardcoded constant.

   Current production gateways: Razorpay (online) + COD (checkout
   handles COD directly, not through this class).
=================================================================== */

require_once __DIR__ . '/PaymentGatewayInterface.php';
require_once __DIR__ . '/PaymentConfigurationException.php';
require_once __DIR__ . '/RazorpayGateway.php';
require_once __DIR__ . '/../settings-functions.php';
require_once __DIR__ . '/../payment-functions.php';
require_once __DIR__ . '/../stock-functions.php';


class PaymentManager
{
    // The one place a new gateway gets registered. Everything else
    // in this class works from this map, not from any gateway's name
    // hardcoded elsewhere.
    private static array $gatewayClasses = [
        'razorpay' => RazorpayGateway::class,
    ];

    // Customer-facing name shown at checkout - falls back to
    // ucfirst($name) in getGatewayLabel() if a gateway isn't listed
    // here, so this is a "nice to have" map, not load-bearing.
    private static array $gatewayLabels = [
        'razorpay' => 'Razorpay',
    ];

    // In-code fallback only (the settings table is always checked
    // first) - matches what a fresh install's seed.sql already seeds,
    // so behavior is identical whether or not migrations have run.
    private static array $defaultEnabled = [
        'razorpay' => '1',
    ];


    /* ==========================================
       EVERY GATEWAY REGISTERED HERE, ENABLED OR NOT
       -------------------------------------------------
       For admin/settings.php, which needs to render a toggle for
       every gateway that actually has a class (unlike
       getEnabledGatewayNames(), which only returns the ones
       currently switched on). Read-only - doesn't touch how any
       gateway processes a payment.
    ========================================== */

    public static function getRegisteredGatewayNames(): array
    {
        return array_keys(self::$gatewayClasses);
    }


    /* ==========================================
       WHICH GATEWAYS ARE CURRENTLY ENABLED?
       -------------------------------------------------
       Returns gateway names in $gatewayClasses' registration order
       (not settings-table order) - only those that are BOTH
       registered here AND have their own '{name}_enabled' setting
       set to '1'. A settings flag with no registered class can never
       appear here.
    ========================================== */

    public static function getEnabledGatewayNames(): array
    {
        $enabled = [];

        foreach (self::$gatewayClasses as $name => $className) {
            $default = self::$defaultEnabled[$name] ?? '0';

            if (get_setting($name . '_enabled', $default) === '1') {
                $enabled[] = $name;
            }
        }

        return $enabled;
    }


    /* ==========================================
       IS A REGISTERED GATEWAY'S CREDENTIALS PRESENT?
       -------------------------------------------------
       Delegates to that gateway class's own
       isConfigured() so checkout/admin never duplicate
       credential rules. Does not instantiate the
       gateway (constructors throw when unconfigured).
    ========================================== */

    public static function isGatewayConfigured(string $gatewayName): bool
    {
        if (!isset(self::$gatewayClasses[$gatewayName])) {
            return false;
        }

        $className = self::$gatewayClasses[$gatewayName];

        return $className::isConfigured();
    }


    /* ==========================================
       ENABLED *AND* CONFIGURED GATEWAYS
       -------------------------------------------------
       Checkout must not offer a gateway that is merely
       toggled on in settings if its API credentials are
       missing. Same registration order as
       getEnabledGatewayNames().
    ========================================== */

    public static function getEnabledAndConfiguredGatewayNames(): array
    {
        $ready = [];

        foreach (self::getEnabledGatewayNames() as $name) {
            if (self::isGatewayConfigured($name)) {
                $ready[] = $name;
            }
        }

        return $ready;
    }


    /* ==========================================
       WHICH ENABLED GATEWAY SHOULD BE PRE-SELECTED?
       -------------------------------------------------
       Reads 'default_payment_gateway', but never trusts it blindly -
       if it names a gateway that isn't currently enabled (e.g. an
       admin disabled the gateway that used to be the default),
       falls back to the first enabled gateway instead. Returns ''
       if literally nothing is enabled - callers (checkout.php) are
       responsible for deciding what to do in that case (see its
       "never let checkout have zero payment methods" guard).
    ========================================== */

    public static function getDefaultGatewayName(): string
    {
        $enabled = self::getEnabledGatewayNames();

        if (empty($enabled)) {
            return '';
        }

        $stored = get_setting('default_payment_gateway', 'razorpay');

        return in_array($stored, $enabled, true) ? $stored : $enabled[0];
    }


    /* ==========================================
       CUSTOMER-FACING LABEL FOR A GATEWAY NAME
    ========================================== */

    public static function getGatewayLabel(string $gatewayName): string
    {
        return self::$gatewayLabels[$gatewayName] ?? ucfirst($gatewayName);
    }


    /* ==========================================
       INSTANTIATE A GATEWAY BY NAME
    ========================================== */

    private static function resolveGateway(string $gatewayName): PaymentGatewayInterface
    {
        if (!isset(self::$gatewayClasses[$gatewayName])) {
            throw new RuntimeException("Unknown payment gateway: {$gatewayName}");
        }

        $className = self::$gatewayClasses[$gatewayName];

        return new $className();
    }


    /* ==========================================
       START A PAYMENT FOR AN ORDER
       -------------------------------------------------
       Creates a gateway order (via the gateway the customer actually
       selected at checkout, already stored on the order itself as
       payment_method - see checkout.php) and logs it as a new
       payment_transactions row. Returns everything the payment page
       needs to render that gateway's checkout widget.

       Deliberately does NOT re-check '{gateway}_enabled' here: once
       an order exists under a gateway, it stays payable under that
       gateway even if an admin disables it afterwards - the same
       philosophy verifyPayment()/handleWebhook() below already use
       (resolve from the transaction's own recorded gateway, not
       "whichever is active/enabled right now"). Retroactively
       blocking an in-progress order would strand the customer.
    ========================================== */

    public static function createPayment(array $order): array
    {
        $gatewayName = $order['payment_method'];
        $gateway     = self::resolveGateway($gatewayName);

        $result = $gateway->createOrder($order);

        log_payment_transaction($order['id'], $gatewayName, [
            'gateway_order_id' => $result['gateway_order_id'],
            'status'           => 'created',
            'raw_response'     => $result['raw'],
        ]);

        $result['gateway'] = $gatewayName;

        return $result;
    }


    /* ==========================================
       VERIFY A CLIENT-SIDE PAYMENT CALLBACK
       -------------------------------------------------
       Looks up which gateway the matching
       payment_transactions row was created under (not
       necessarily still "the active one" - a customer may
       have started checkout before an admin switched
       gateways), verifies against THAT gateway, and
       updates both the transaction row and the order on
       success.

       $expectedOrderId is the already ownership-verified
       local order id (payment-verify.php passes
       $order['id']). The transaction looked up by
       gateway_order_id MUST belong to that same order
       before any signature check or status write.
    ========================================== */

    public static function verifyPayment(string $gatewayOrderId, array $callbackData, int $expectedOrderId): bool
    {
        $transaction = get_payment_transaction_by_gateway_order_id($gatewayOrderId);

        if (!$transaction) {
            return false;
        }

        if ((int) $transaction['order_id'] !== $expectedOrderId) {
            error_log(
                'PaymentManager::verifyPayment() rejected: transaction #' . $transaction['id']
                . ' belongs to order #' . $transaction['order_id']
                . ' but expected order #' . $expectedOrderId
            );
            return false;
        }

        $gateway = self::resolveGateway($transaction['gateway']);
        $isValid = $gateway->verifyPayment($callbackData);

        if ($isValid) {

            // Razorpay hands its payment id back to the client widget,
            // so it arrives here in $callbackData.
            $gatewayPaymentId = $callbackData['razorpay_payment_id'] ?? null;

            if (method_exists($gateway, 'getVerifiedPaymentId')) {
                $gatewayPaymentId = $gateway->getVerifiedPaymentId() ?? $gatewayPaymentId;
            }

            update_payment_transaction($transaction['id'], [
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_signature'  => $callbackData['razorpay_signature'] ?? null,
                'status'             => 'paid',
            ]);

            update_order_payment_status($transaction['order_id'], 'paid', 'processing', $transaction['gateway']);

            // Online-gateway stock deduction point (Phase 6): reserve
            // stock only AFTER the gateway has confirmed the payment -
            // never at checkout, so an abandoned/never-paid order can't
            // hold inventory. Idempotent via orders.stock_deducted_at
            // (see includes/stock-functions.php), so a verify() call
            // racing the webhook (or a retry) can't double-deduct.
            self::reserveStockForOrder($transaction['order_id']);

        } else {

            update_payment_transaction($transaction['id'], ['status' => 'failed']);
        }

        return $isValid;
    }


    /* ==========================================
       HANDLE A WEBHOOK CALL
       -------------------------------------------------
       $gatewayName is known from context (which webhook
       URL was called - e.g. webhook-razorpay.php always
       passes 'razorpay'), so there's no ambiguity about
       which gateway's signature rules to apply.

       Idempotent: if this transaction is already marked
       'paid' or 'failed', the update is skipped - Razorpay
       (like most gateways) may call the same webhook more
       than once for the same event.
    ========================================== */

    public static function handleWebhook(string $gatewayName, string $rawPayload, array $headers): array
    {
        $gateway = self::resolveGateway($gatewayName);
        $event   = $gateway->handleWebhook($rawPayload, $headers);

        if (!$event['valid'] || !$event['status'] || !$event['gateway_order_id']) {
            return $event;
        }

        $transaction = get_payment_transaction_by_gateway_order_id($event['gateway_order_id']);

        if (!$transaction) {
            return $event; // unknown order - nothing of ours to update
        }

        // Idempotency guard - don't reprocess an already-settled transaction.
        if (in_array($transaction['status'], ['paid', 'failed'], true)) {
            return $event;
        }

        update_payment_transaction($transaction['id'], [
            'gateway_payment_id' => $event['gateway_payment_id'],
            'status'             => $event['status'],
            'raw_response'       => json_encode($event['raw']),
        ]);

        $orderStatus = $event['status'] === 'paid' ? 'processing' : 'pending';
        update_order_payment_status($transaction['order_id'], $event['status'], $orderStatus, $gatewayName);

        if ($event['status'] === 'paid') {
            // Same Phase 6 deduction point as verifyPayment(): only a
            // gateway-confirmed payment reserves stock. Idempotent, so a
            // webhook re-fire for an already-settled transaction is safe.
            self::reserveStockForOrder($transaction['order_id']);
        }

        return $event;
    }


    /* ==========================================
       RESERVE STOCK FOR A PAYMENT-CONFIRMED ORDER
       ------------------------------------------
       Wraps apply_order_stock_deduction() so a stock shortfall (a
       concurrent order taking the last units before this callback
       landed) can never 500 the payment confirmation path. The order is
       already paid and real - the guarded UPDATE in
       stock-functions.php already guarantees stock never goes negative,
       so the only cost of a failure here is that the admin tops stock up
       manually. The error is logged for exactly that purpose.
    ========================================== */

    private static function reserveStockForOrder(int $orderId): void
    {
        try {
            apply_order_stock_deduction($orderId);
        } catch (Exception $e) {
            error_log('PaymentManager::reserveStockForOrder() failed for order #' . $orderId . ': ' . $e->getMessage());
        }
    }
}
