<?php
/* ===================================================================
   ORDER SUCCESS PAGE
   -------------------------------------------------------------------
   Shown right after checkout.php creates an order. Access is gated
   by $_SESSION['last_order_number'] (set only at the moment THIS
   browser just completed checkout) matching the order_number in the
   URL - otherwise anyone could view anyone else's name/address/order
   just by guessing or incrementing the number, since this site has
   no login system to check against.
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/invoice-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';
require_once __DIR__ . '/includes/meta-capi-functions.php';

$orderNumber = trim($_GET['order'] ?? '');

$isThisBrowsersOrder =
    $orderNumber !== ''
    && !empty($_SESSION['last_order_number'])
    && hash_equals($_SESSION['last_order_number'], $orderNumber);

if (!$isThisBrowsersOrder) {
    redirect('index.php');
}


/* ==========================================
   LOAD THE ORDER
========================================== */

$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    redirect('index.php');
}

// Phase 3A: an order only counts as "successful" here once payment is
// actually settled - paid online, or accepted as Cash on Delivery.
// A Razorpay order still sitting at payment_status = 'pending' means
// payment was never completed - send them to finish it instead of
// showing a false confirmation. Pending Manual UPI orders go to their
// dedicated page (there is no gateway for them, so payment.php can't
// help).
$paymentIsSettled = $order['payment_status'] === 'paid' || $order['payment_method'] === 'cod';

if (!$paymentIsSettled) {
    if ($order['payment_method'] === 'manual_upi') {
        redirect('manual-upi-payment.php?order=' . urlencode($order['order_number']));
    }
    redirect('payment.php?order=' . urlencode($order['order_number']));
}

$stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
$stmt->execute([$order['id']]);
$orderItems = $stmt->fetchAll();

// Certificate Included: show only when ALL purchased products are
// Yes (1). Any No, missing product, or unavailable column hides
// the card (fail closed).
$showCertificateIncluded = false;
$certificateProductIds = [];
foreach ($orderItems as $orderItem) {
    $certificateProductId = (int) ($orderItem['product_id'] ?? 0);
    if ($certificateProductId > 0) {
        $certificateProductIds[] = $certificateProductId;
    }
}
$certificateProductIds = array_values(array_unique($certificateProductIds));
if ($certificateProductIds) {
    try {
        $placeholders = implode(',', array_fill(0, count($certificateProductIds), '?'));
        $stmt = db()->prepare(
            "SELECT id, certificate_included FROM products WHERE id IN ($placeholders)"
        );
        $stmt->execute($certificateProductIds);
        $certificateFlagsByProductId = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $showCertificateIncluded = order_should_show_certificate_included(
            $orderItems,
            is_array($certificateFlagsByProductId) ? $certificateFlagsByProductId : null
        );
    } catch (PDOException $e) {
        $showCertificateIncluded = false;
    }
}

$stmt = db()->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
$stmt->execute([$order['id']]);
$orderAddress = $stmt->fetch();

/* ==========================================
   PAYMENT DETAILS (for the summary card below)
   -------------------------------------------------
   COD never creates a payment_transactions row - see
   includes/payment-functions.php's file-level comment:
   checkout.php calls update_order_payment_status()
   directly for COD, skipping the gateway layer entirely.
   So this lookup only runs for online payments.
========================================== */

$paymentTransaction = null;

if ($order['payment_method'] !== 'cod') {
    $stmt = db()->prepare(
        "SELECT * FROM payment_transactions
         WHERE order_id = ?
         ORDER BY (status = 'paid') DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$order['id']]);
    $paymentTransaction = $stmt->fetch() ?: null;
}

// Same gateway-name mapping used just below for the top confirmation
// line - shared here so both use one dynamic source (order.payment_method).
$paymentMethodLabel = match ($order['payment_method']) {
    'cod'        => 'Cash on Delivery',
    'manual_upi' => 'Manual UPI Payment',
    'razorpay'   => 'Paid Online (Razorpay)',
    'cashfree'   => 'Paid Online (Cashfree)',
    default      => 'Paid Online',
};

$paymentStatusLabel = match ($order['payment_status']) {
    'paid'     => 'Paid',
    'pending'  => 'Pending',
    'failed'   => 'Failed',
    'refunded' => 'Refunded',
    default    => ucfirst($order['payment_status']),
};

// Transaction ID: the gateway's payment id if we have one, falling
// back to its gateway order id (e.g. the fast checkout-callback path
// confirmed payment before a payment id was available - see
// PaymentManager::verifyPayment()).
$transactionId = $paymentTransaction['gateway_payment_id']
    ?? $paymentTransaction['gateway_order_id']
    ?? null;

// Only show the gateway order id as a SEPARATE secondary reference
// when it isn't already the value shown above as the Transaction ID.
$showGatewayOrderId = $paymentTransaction
    && !empty($paymentTransaction['gateway_order_id'])
    && $paymentTransaction['gateway_order_id'] !== $transactionId;

// When the transaction actually settled (updated_at only changes on a
// real update_payment_transaction() call) for online payments, or
// simply when the order was placed for Cash on Delivery.
$paymentDateTime = $paymentTransaction['updated_at'] ?? $order['created_at'];

// Guest invoice download/print links (guest-invoice.php) - signed so
// this order's invoice can be fetched with no login/session. null
// when INVOICE_TOKEN_SECRET isn't configured (see
// generate_guest_invoice_token()'s doc comment) - the buttons below
// are hidden entirely in that case rather than shown broken.
$guestInvoiceToken = generate_guest_invoice_token($order['order_number']);

// This session value's only job was to authorize viewing THIS page
// once, right after checkout. Clear it now so it can't be reused to
// view the same confirmation again via a stored/shared link.
unset($_SESSION['last_order_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Order Confirmed | MoonAura Crystals</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/header.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/footer.css') ?>">
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/checkout.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="checkout-page">

        <div class="container">

            <div class="order-confirmation-card">

                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>

                <h1>Thank You! Your Order is Confirmed.</h1>

                <div class="order-confirmation-details">

                    <div class="order-confirmation-detail">
                        <span class="order-confirmation-detail-label">Order Number</span>
                        <span class="order-confirmation-detail-value"><strong><?= h($order['order_number']) ?></strong></span>
                    </div>

                    <div class="order-confirmation-detail">
                        <span class="order-confirmation-detail-label">Payment Method</span>
                        <span class="order-confirmation-detail-value"><?= h($paymentMethodLabel) ?></span>
                    </div>

                    <div class="order-confirmation-detail">
                        <span class="order-confirmation-detail-label">Payment Status</span>
                        <span class="order-confirmation-detail-value">
                            <span class="payment-status-badge payment-status-<?= h($order['payment_status']) ?>">
                                <?= h($paymentStatusLabel) ?>
                            </span>
                        </span>
                    </div>

                    <?php if ($transactionId): ?>
                        <div class="order-confirmation-detail">
                            <span class="order-confirmation-detail-label">Transaction ID</span>
                            <span class="order-confirmation-detail-value payment-detail-value"><?= h($transactionId) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($showGatewayOrderId): ?>
                        <div class="order-confirmation-detail">
                            <span class="order-confirmation-detail-label">Gateway Order ID</span>
                            <span class="order-confirmation-detail-value payment-detail-value payment-detail-secondary"><?= h($paymentTransaction['gateway_order_id']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="order-confirmation-detail">
                        <span class="order-confirmation-detail-label">Payment Date &amp; Time</span>
                        <span class="order-confirmation-detail-value"><?= h(date('d M Y, h:i A', strtotime($paymentDateTime))) ?></span>
                    </div>

                    <div class="order-confirmation-detail order-confirmation-detail-last">
                        <span class="order-confirmation-detail-label">Invoice Number</span>
                        <span class="order-confirmation-detail-value<?= $order['invoice_number'] ? '' : ' payment-detail-pending' ?>">
                            <?= $order['invoice_number']
                                ? h($order['invoice_number'])
                                : 'Invoice will be generated after order processing.' ?>
                        </span>
                    </div>

                </div>

                <p class="order-confirmation-email">
                    We have sent you a confirmation email along with your order details.
                </p>

                <a href="shop.php" class="btn btn-primary">Continue Shopping</a>

            </div>

            <div class="order-trust-cards <?= $showCertificateIncluded ? 'order-trust-cards-4' : 'order-trust-cards-3' ?>">

                <div class="order-trust-card">
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                    <span>GST Invoice Available</span>
                </div>

                <div class="order-trust-card">
                    <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
                    <span>Authenticity Guarantee</span>
                </div>

                <div class="order-trust-card">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    <span>Dedicated Support</span>
                </div>

                <?php if ($showCertificateIncluded): ?>
                    <div class="order-trust-card">
                        <i class="fa-solid fa-certificate" aria-hidden="true"></i>
                        <span>Certificate of Authenticity Included</span>
                    </div>
                <?php endif; ?>

            </div>

            <div class="payment-details-card order-estimated-delivery">

                <h2>Estimated Delivery</h2>

                <div class="order-estimated-delivery-windows">

                    <div class="order-estimated-delivery-window">
                        <div class="order-estimated-delivery-value">3–7 Business Days</div>
                        <div class="order-estimated-delivery-caption">For Regular Orders</div>
                    </div>

                    <div class="order-estimated-delivery-window">
                        <div class="order-estimated-delivery-value">7–10 Business Days</div>
                        <div class="order-estimated-delivery-caption">For Custom Orders</div>
                    </div>

                </div>

            </div>

            <?php if ($guestInvoiceToken !== null): ?>
                <div class="invoice-actions">

                    <a href="guest-invoice.php?order=<?= urlencode($order['order_number']) ?>&exp=<?= h((string) $guestInvoiceToken['exp']) ?>&sig=<?= h($guestInvoiceToken['sig']) ?>&mode=download" class="btn btn-primary">
                        Download Invoice
                    </a>
                    <a href="guest-invoice.php?order=<?= urlencode($order['order_number']) ?>&exp=<?= h((string) $guestInvoiceToken['exp']) ?>&sig=<?= h($guestInvoiceToken['sig']) ?>&mode=print" class="btn btn-outline" target="_blank" rel="noopener">
                        Print Invoice
                    </a>

                    <?php if (!$order['invoice_number']): ?>
                        <p class="invoice-actions-note">
                            Your invoice will be generated automatically when you download it for the first time.
                        </p>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

            <p class="payment-support-note">
                <i class="fa-solid fa-circle-info"></i>
                Please keep your Order Number and Transaction ID for future support.
            </p>

            <div class="checkout-layout" style="margin-top: 32px;">

                <div class="checkout-form-card">

                    <h2>Order Items</h2>

                    <?php foreach ($orderItems as $orderItem): ?>

                        <div class="checkout-summary-item">

                            <div class="checkout-summary-item-name">
                                <?= h($orderItem['product_name']) ?>
                                <div class="checkout-summary-item-qty">Qty: <?= (int) $orderItem['quantity'] ?></div>
                            </div>

                            <div class="checkout-summary-item-total">
                                <?= h(format_price((float) $orderItem['line_total'])) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                    <?php if ($orderAddress): ?>

                        <h2 style="margin-top: 24px;">Delivery Address</h2>

                        <p style="font-size: 14px; color: var(--text-light); line-height: 1.7;">
                            <?= h($orderAddress['full_name']) ?><br>
                            <?= h($orderAddress['address_line1']) ?><br>
                            <?php if (!empty($orderAddress['address_line2'])): ?>
                                <?= h($orderAddress['address_line2']) ?><br>
                            <?php endif; ?>
                            <?php if (!empty($orderAddress['landmark'])): ?>
                                Landmark: <?= h($orderAddress['landmark']) ?><br>
                            <?php endif; ?>
                            <?= h($orderAddress['city']) ?>, <?= h($orderAddress['state']) ?> - <?= h($orderAddress['postal_code']) ?><br>
                            Phone: <?= h($orderAddress['phone']) ?>
                        </p>

                    <?php endif; ?>

                </div>

                <div class="checkout-summary">

                    <h2>Order Total</h2>

                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span><?= h(format_price((float) $order['subtotal'])) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>Shipping</span>
                        <span><?= h(format_price((float) $order['shipping_charge'])) ?></span>
                    </div>

                    <div class="checkout-summary-row">
                        <span>GST (Included)</span>
                        <span><?= h(format_price((float) $order['gst_amount'])) ?></span>
                    </div>

                    <div class="checkout-summary-row checkout-summary-total">
                        <span>Grand Total</span>
                        <span><?= h(format_price((float) $order['grand_total'])) ?></span>
                    </div>

                </div>

            </div>

        </div>

    </section>

    <?php
    $purchaseItems = ga4_items_from_order_items($orderItems);
    $purchaseCurrency = strtoupper(trim((string) ($order['currency'] ?? '')));
    if ($purchaseCurrency === '') {
        $purchaseCurrency = ga4_currency();
    }
    ga4_queue_event('purchase', [
        'transaction_id' => (string) $order['order_number'],
        'value'          => round((float) $order['grand_total'], 2),
        'currency'       => $purchaseCurrency,
        'shipping'       => round((float) ($order['shipping_charge'] ?? 0), 2),
        'tax'            => round((float) ($order['gst_amount'] ?? 0), 2),
        'items'          => $purchaseItems,
    ]);

    // Meta Phase 3: standard Purchase conversion, and only on this
    // confirmed-order page. The payment/order guards above already
    // redirect away anything that isn't genuinely settled (paid, or a
    // COD order accepted at placement), so reaching here IS the
    // success condition. The value is the authoritative grand total
    // recorded on the order - never the cart subtotal - and the items
    // are the same canonical order-item data the GA4 event above
    // already built (SKU when present, else product id). The order
    // number is used solely as the browser-side once-only dedupe token
    // so a refresh/revisit cannot fire a second Purchase; it is NOT
    // included in the Meta payload.
    //
    // Meta Phase 4: one stable, opaque event id is derived from this
    // same authoritative order and shared by BOTH the browser event
    // below and the server-side Conversions API dispatch at the end of
    // the page, so Meta deduplicates them into a single conversion. The
    // CAPI helper is credential-optional and no-ops safely when
    // META_CAPI_ACCESS_TOKEN is not configured.
    $metaPurchaseEventId = meta_capi_purchase_event_id($order);

    meta_pixel_track_purchase(
        $purchaseItems,
        $order['grand_total'] ?? null,
        (string) ($order['order_number'] ?? ''),
        $metaPurchaseEventId
    );
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>

</body>
</html>
<?php
// Server-side CAPI Purchase (Phase 4). Dispatched only for this
// confirmed/settled order, AFTER the whole page has been rendered, so a
// slow or failing Meta call can never delay, alter or break the
// customer's order-success response. Under PHP-FPM the response is
// flushed first; elsewhere it remains a best-effort call. The helper is
// a no-op when the token/dataset is unconfigured, and any HTTP/network/
// timeout/malformed response fails silently.
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
}

meta_capi_send_purchase($order, $purchaseItems, $metaPurchaseEventId);
?>
