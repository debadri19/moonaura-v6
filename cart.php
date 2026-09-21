<?php
/* ===================================================================
   CART PAGE
=================================================================== */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/product-image-variants.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/tax-functions.php';
require_once __DIR__ . '/includes/analytics-functions.php';
require_once __DIR__ . '/includes/meta-pixel-functions.php';

$cartItems = get_cart_items_with_details();

// GST is informational only here (prices are GST-inclusive - Phase
// 5F - so this is never added on top of $subtotal). The cart page
// doesn't know a shipping state yet, so this uses the same
// conservative 'unknown' resolution create_order() itself falls
// back to (see includes/tax-functions.php) - checkout.php refines
// this once an address is entered.
$cartGstAmount = 0.0;
$hasUnpurchasableItems = false;
$purchasableSubtotal = 0.0;
foreach ($cartItems as $item) {
    $itemStockQuantity = (int) ($item['product']['stock_quantity'] ?? 0);
    $itemStockStatus   = $item['product']['stock_status'] ?? 'in_stock';
    $itemOutOfStock    = $itemStockQuantity <= 0 || $itemStockStatus === 'out_of_stock';
    if (!$item['is_available'] || $itemOutOfStock) {
        $hasUnpurchasableItems = true;
        continue;
    }
    $purchasableSubtotal += (float) $item['line_total'];
    $gstRate = normalize_gst_rate($item['product']['gst_rate'] ?? 0.0) ?? 0.0;
    $lineTax = compute_line_tax((float) $item['line_total'], $gstRate, 'unknown');
    $cartGstAmount += $lineTax['gst_amount'];
}
$cartGstAmount = round($cartGstAmount, 2);
$purchasableSubtotal = round($purchasableSubtotal, 2);

$successMessage = flash_get('success');
$errorMessage   = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php theme_boot(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title>Your Cart | MoonAura Crystals</title>
    <meta name="robots" content="noindex, follow">

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
    <link rel="stylesheet" href="<?= versioned_asset('assets/css/cart.css') ?>">

</head>
<body>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <section class="cart-page">

        <div class="container">

            <h1>Your Cart</h1>

            <?php if ($successMessage): ?>
                <div class="cart-alert cart-alert-success"><?= h($successMessage) ?></div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="cart-alert cart-alert-error"><?= h($errorMessage) ?></div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>

                <!-- ==========================================
                     EMPTY CART
                ========================================== -->

                <div class="empty-state cart-empty-state">
                    <span class="cart-empty-icon" aria-hidden="true">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </span>
                    <p>Your cart is empty.</p>
                    <span class="cart-empty-divider" aria-hidden="true"></span>
                    <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                </div>

            <?php else: ?>

                <div class="cart-layout">

                    <!-- ==========================================
                         CART ITEMS
                    ========================================== -->

                    <div class="cart-items">

                        <?php foreach ($cartItems as $item): ?>

                            <?php
                                $product      = $item['product'];
                                $imagePath    = get_product_primary_image((int) $product['id']);
                                $isAvailable  = $item['is_available'];
                                $stockQuantity = (int) ($product['stock_quantity'] ?? 0);
                                $stockStatus   = $product['stock_status'] ?? 'in_stock';
                                $isOutOfStock  = $stockQuantity <= 0 || $stockStatus === 'out_of_stock';
                                $canPurchase   = $isAvailable && !$isOutOfStock;
                            ?>

                            <div class="cart-item <?= $canPurchase ? '' : 'cart-item-unavailable' ?>">

                                <div class="cart-item-image">
                                    <img src="<?= h(product_image_variant_url($imagePath, 'sm')) ?>" alt="<?= h($product['name']) ?>">
                                </div>

                                <div class="cart-item-name">

                                    <a href="product.php?slug=<?= h($product['slug']) ?>">
                                        <?= h($product['name']) ?>
                                    </a>

                                    <?php if ($isOutOfStock): ?>
                                        <span class="cart-item-unavailable-note">Out of Stock</span>
                                    <?php elseif (!$isAvailable): ?>
                                        <span class="cart-item-unavailable-note">No longer available</span>
                                    <?php endif; ?>

                                </div>

                                <div class="cart-item-price">
                                    <?= h(format_price((float) $product['sell_price'])) ?>
                                </div>

                                <div class="cart-item-controls">

                                    <?php if ($canPurchase): ?>

                                        <form method="post" action="cart-update.php" class="cart-item-quantity-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                            <div class="cart-qty-control">
                                                <button type="button" class="cart-qty-btn cart-qty-minus" aria-label="Decrease quantity">
                                                    <i class="fa-solid fa-minus"></i>
                                                </button>
                                                <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="99" aria-label="Quantity">
                                                <button type="button" class="cart-qty-btn cart-qty-plus" aria-label="Increase quantity">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                            </div>
                                            <button type="submit" class="cart-qty-submit">Update</button>
                                        </form>

                                    <?php else: ?>

                                        <div class="cart-qty-control cart-qty-control-static" aria-label="Quantity">
                                            <span class="cart-qty-value"><?= (int) $item['quantity'] ?></span>
                                        </div>

                                    <?php endif; ?>

                                    <form method="post" action="cart-remove.php" class="cart-item-remove-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                        <button type="submit" class="cart-item-remove" title="Remove" aria-label="Remove item">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                </div>

                                <div class="cart-item-line-total">
                                    <?= $canPurchase ? h(format_price($item['line_total'])) : '—' ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <!-- ==========================================
                         CART SUMMARY
                    ========================================== -->

                    <div class="cart-summary">

                        <h2>Cart Summary</h2>

                        <div class="cart-summary-row">
                            <span>Subtotal</span>
                            <span id="cartSubtotalValue"><?= h(format_price($purchasableSubtotal)) ?></span>
                        </div>

                        <div class="cart-summary-row">
                            <span>GST (Included)</span>
                            <span id="cartGstValue"><?= h(format_price($cartGstAmount)) ?></span>
                        </div>

                        <div class="cart-summary-row cart-summary-total">
                            <span>Total</span>
                            <span id="cartTotalValue"><?= h(format_price($purchasableSubtotal)) ?></span>
                        </div>

                         <?php if ($hasUnpurchasableItems): ?>
                             <p class="cart-summary-stock-note">Remove out of stock items before checkout.</p>
                             <span class="btn btn-primary cart-checkout-disabled" aria-disabled="true">Proceed to Checkout</span>
                         <?php else: ?>
                             <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
                         <?php endif; ?>
                         <a href="shop.php" class="btn btn-outline">Continue Shopping</a>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php
    $cartGaItems = ga4_items_from_cart_items($cartItems);
    ga4_queue_event('view_cart', [
        'currency' => ga4_currency(),
        'value'    => ga4_items_value($cartGaItems),
        'items'    => $cartGaItems,
    ]);

    // Meta Phase 2: ViewCart fires once when the cart page is actually
    // viewed with items. Server-queued once per page load.
    meta_pixel_track_cart_view($cartGaItems);
    ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="<?= versioned_asset('assets/js/main.js') ?>"></script>
    <script>
    (function () {
        var cartItems = document.querySelector('.cart-items');
        if (!cartItems) return;

        function clampQty(value) {
            var qty = parseInt(value, 10);
            if (isNaN(qty)) qty = 1;
            return Math.max(1, Math.min(99, qty));
        }

        function submitQtyForm(form) {
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }

        cartItems.addEventListener('click', function (event) {
            var minus = event.target.closest('.cart-qty-minus');
            var plus = event.target.closest('.cart-qty-plus');
            if (!minus && !plus) return;

            var form = event.target.closest('.cart-item-quantity-form');
            if (!form) return;

            event.preventDefault();
            var input = form.querySelector('input[name="quantity"]');
            if (!input) return;

            input.value = clampQty(clampQty(input.value) + (plus ? 1 : -1));
            submitQtyForm(form);
        });

        cartItems.addEventListener('change', function (event) {
            var input = event.target.closest('.cart-item-quantity-form input[name="quantity"]');
            if (!input) return;

            input.value = clampQty(input.value);
            submitQtyForm(input.closest('.cart-item-quantity-form'));
        });
    })();
    </script>

</body>
</html>
