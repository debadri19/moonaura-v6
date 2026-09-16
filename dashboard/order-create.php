<?php
/* ===================================================================
   ADMIN - CREATE ORDER (Phase 5C)
   -------------------------------------------------------------------
   Lets an admin place an order on behalf of a customer:
     - order for an EXISTING customer (linked to their account) or a
       GUEST (walk-in / phone order), with a shipping address;
     - add multiple products with a quantity selector, remove items,
       and a live order summary;
     - payment: COD (accepted at placement, stock deducted now),
       Manual UPI (payment pending - stock reserved only when an admin
       later verifies it as Paid in order-detail.php), or Paid (already
       collected - stock deducted now);
     - order status: Pending / Processing / Completed (Completed is the
       existing terminal 'delivered' status - see the option hint).

   It deliberately reuses the existing order architecture end-to-end:
   create_order() (incl. the transaction-safe order-number generator),
   update_order_payment_status(), update_order_status() and
   apply_order_stock_deduction() - the same functions checkout.php and
   dashboard/order-detail.php use. No client-supplied totals are trusted:
   unit prices, subtotal, shipping, discount and grand total are all
   recomputed server-side from the products table.

   The in-progress item list is carried as hidden inputs inside this
   single form (no session state, nothing to clean up if abandoned).
   Adding/removing items re-POSTs the whole form so nothing is lost.
=================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/customer-functions.php'; // get_customer_addresses()
require_once __DIR__ . '/../includes/order-functions.php';     // create_order() etc.
require_once __DIR__ . '/../includes/stock-functions.php';     // apply_order_stock_deduction()
require_once __DIR__ . '/../includes/payment-functions.php';   // update_order_payment_status()
require_once __DIR__ . '/../includes/order-emails.php';        // Phase 5D Step 2 confirmation email

require_admin_login();

$admin      = current_admin();
$pageTitle  = 'Create Order';
$activePage = 'order-create';

$errors          = [];
$successMessage  = flash_get('success');
$errorMessage    = flash_get('error');

/* ==========================================
   CURRENT FORM STATE (from POST, or defaults)
========================================== */

$customerMode   = $_POST['customer_mode'] ?? 'existing';
$customerMode   = in_array($customerMode, ['existing', 'guest'], true) ? $customerMode : 'existing';

$customerId     = (int) ($_POST['customer_id'] ?? 0);
$prevCustomerId = (int) ($_POST['prev_customer_id'] ?? 0);

$guestName  = trim($_POST['guest_name'] ?? '');
$guestEmail = trim($_POST['guest_email'] ?? '');
$guestPhone = trim($_POST['guest_phone'] ?? '');

$paymentMethod = $_POST['payment_method'] ?? 'cod';
$paymentMethod = in_array($paymentMethod, ['cod', 'manual_upi', 'paid'], true) ? $paymentMethod : 'cod';

$orderStatus = $_POST['order_status'] ?? 'pending';
$orderStatus = in_array($orderStatus, ['pending', 'processing', 'delivered'], true) ? $orderStatus : 'pending';

$shippingCharge = max(0.0, (float) ($_POST['shipping_charge'] ?? 0));
$discount       = max(0.0, (float) ($_POST['discount'] ?? 0));

/* ---- Line-item state, carried as hidden inputs ---- */

$itemProductIds = array_map('intval', $_POST['item_product_id'] ?? []);
$itemQtys       = array_map('intval', $_POST['item_qty'] ?? []);

// Keep the two arrays aligned no matter what was posted.
$count = count($itemProductIds);
$itemProductIds = array_slice($itemProductIds, 0, $count);
$itemQtys       = array_slice($itemQtys, 0, $count);

/* ---- Load the product rows for the current line items (server-side
   prices always - the client only ever sends product id + qty) ---- */

$itemProducts = [];
$itemQtys     = array_pad($itemQtys, count($itemProductIds), 1);

if (!empty($itemProductIds)) {
    $uniqueIds = array_values(array_unique(array_filter($itemProductIds, static fn ($v) => $v > 0)));
    if (!empty($uniqueIds)) {
        $in   = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = db()->prepare("SELECT * FROM products WHERE id IN ({$in})");
        $stmt->execute($uniqueIds);
        foreach ($stmt->fetchAll() as $p) {
            $itemProducts[$p['id']] = $p;
        }
    }
}

/* ==========================================
   REFERENCE DATA (products + customers)
========================================== */

$productOptions = db()->query(
    'SELECT id, name, sku, sell_price, mrp, stock_quantity
     FROM products WHERE status = "active"
     ORDER BY name ASC'
)->fetchAll();

$customers = db()->query(
    'SELECT id, name, email, phone FROM customers WHERE status = "active" ORDER BY name ASC'
)->fetchAll();

$selectedCustomer = null;
if ($customerMode === 'existing' && $customerId > 0) {
    $stmt = db()->prepare('SELECT id, name, email, phone FROM customers WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$customerId]);
    $selectedCustomer = $stmt->fetch() ?: null;
}

/* ---- Shipping address (editable; autofilled from the customer's
   default address when an existing customer is first picked) ---- */

$address = [
    'address_line1' => trim($_POST['address_line1'] ?? ''),
    'address_line2' => trim($_POST['address_line2'] ?? ''),
    'landmark'      => trim($_POST['landmark'] ?? ''),
    'city'          => trim($_POST['city'] ?? ''),
    'state'         => trim($_POST['state'] ?? ''),
    'pincode'       => trim($_POST['pincode'] ?? ''),
];

if ($customerMode === 'existing' && $customerId > 0 && $prevCustomerId !== $customerId) {
    $savedAddresses = get_customer_addresses($customerId);
    if (!empty($savedAddresses)) {
        $saved = $savedAddresses[0];
        $address['address_line1'] = (string) $saved['address_line1'];
        $address['address_line2'] = (string) ($saved['address_line2'] ?? '');
        $address['landmark']      = (string) ($saved['landmark'] ?? '');
        $address['city']          = (string) $saved['city'];
        $address['state']         = (string) $saved['state'];
        $address['pincode']       = (string) $saved['postal_code'];
    }
}

/* ==========================================
   FORM ACTIONS
========================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    /* ---------- Remove a line item ---------- */

    if (isset($_POST['remove_index'])) {
        $removeIndex = (int) $_POST['remove_index'];
        if (isset($itemProductIds[$removeIndex])) {
            array_splice($itemProductIds, $removeIndex, 1);
            array_splice($itemQtys, $removeIndex, 1);
        }
    }

    /* ---------- Add a product to the order ---------- */

    elseif (($_POST['action'] ?? '') === 'add_item') {

        $addProductId = (int) ($_POST['add_product_id'] ?? 0);
        $addQty       = (int) ($_POST['add_qty'] ?? 1);

        $addProduct = null;
        if ($addProductId > 0) {
            $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$addProductId]);
            $addProduct = $stmt->fetch() ?: null;
        }

        if (!$addProduct || $addProduct['status'] !== 'active') {
            $errors[] = 'Please choose a product to add.';
        } elseif ($addQty < 1) {
            $errors[] = 'Quantity must be at least 1.';
        } elseif ((int) $addProduct['stock_quantity'] < $addQty) {
            $errors[] = 'Only ' . (int) $addProduct['stock_quantity'] . ' left in stock for "'
                . $addProduct['name'] . '" (you asked for ' . $addQty . ').';
        } else {
            $existingIndex = array_search($addProductId, $itemProductIds, true);
            if ($existingIndex !== false) {
                // Merge with the existing line, still capped by real stock.
                $combined = $itemQtys[$existingIndex] + $addQty;
                if ($combined > (int) $addProduct['stock_quantity']) {
                    $errors[] = 'Only ' . (int) $addProduct['stock_quantity'] . ' left in stock for "'
                        . $addProduct['name'] . '" (your order already has '
                        . $itemQtys[$existingIndex] . ').';
                } else {
                    $itemQtys[$existingIndex] = $combined;
                }
            } else {
                $itemProductIds[] = $addProductId;
                $itemQtys[]       = $addQty;
            }
        }
    }

    /* ---------- Create the order ---------- */

    elseif (($_POST['action'] ?? '') === 'create_order') {

        /* ---- Products: reload rows from DB, never trust the client ---- */

        $cartItems = [];
        $subtotal  = 0.0;

        if (empty($itemProductIds)) {
            $errors[] = 'Add at least one product to the order.';
        }

        foreach ($itemProductIds as $i => $productId) {
            if (!isset($itemProducts[$productId])) {
                $errors[] = 'One of the chosen products no longer exists.';
                continue;
            }
            $product = $itemProducts[$productId];

            if ($product['status'] !== 'active') {
                $errors[] = '"' . $product['name'] . '" is no longer available.';
                continue;
            }

            $qty = (int) ($itemQtys[$i] ?? 0);
            if ($qty < 1) {
                $errors[] = 'Quantity for "' . $product['name'] . '" must be at least 1.';
                continue;
            }

            // Friendly pre-flight message; the authoritative no-negative
            // guarantee stays in apply_order_stock_deduction()'s guarded
            // UPDATE (see includes/stock-functions.php).
            if ((int) $product['stock_quantity'] < $qty) {
                $errors[] = 'Only ' . (int) $product['stock_quantity'] . ' left in stock for "'
                    . $product['name'] . '" (you asked for ' . $qty . ').';
                continue;
            }

            $lineTotal = (float) $product['sell_price'] * $qty;
            $subtotal += $lineTotal;

            $cartItems[] = [
                'product'    => $product,
                'quantity'   => $qty,
                'line_total' => $lineTotal,
            ];
        }

        /* ---- Customer ---- */

        $customer   = [];
        $customerId = $customerMode === 'existing' ? $customerId : null;

        if ($customerMode === 'existing') {
            if (!$selectedCustomer) {
                $errors[] = 'Please choose a valid existing customer.';
            } else {
                $customer = [
                    'full_name' => $selectedCustomer['name'],
                    'email'     => $selectedCustomer['email'],
                    'mobile'    => $selectedCustomer['phone'],
                ];
            }
        } else {
            $guestName  = trim($guestName);
            $guestEmail = normalize_email($guestEmail);
            $guestPhone = normalize_mobile_number($guestPhone);

            $customer = [
                'full_name' => $guestName,
                'email'     => $guestEmail,
                'mobile'    => $guestPhone,
            ];

            if ($guestName === '') {
                $errors[] = 'Customer name is required.';
            }
            if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid customer email.';
            }
            if (!is_valid_mobile_number($guestPhone)) {
                $errors[] = 'Please enter a valid 10-digit mobile number.';
            }
        }

        /* ---- Address ---- */

        if ($address['address_line1'] === '') {
            $errors[] = 'Address line 1 is required.';
        }
        if ($address['city'] === '') {
            $errors[] = 'City is required.';
        }
        if ($address['state'] === '') {
            $errors[] = 'State is required.';
        }
        if (!is_valid_pin_code($address['pincode'])) {
            $errors[] = 'Please enter a valid 6-digit PIN code.';
        }

        /* ---- Money (server-side, guarded) ---- */

        if ($discount > $subtotal) {
            $errors[] = 'Discount cannot exceed the order subtotal.';
        }

        /* ---- Create ---- */

        if (empty($errors)) {

            try {

                $order = create_order(
                    $cartItems,
                    $customer,
                    $address,
                    $customerId,
                    $paymentMethod,
                    $shippingCharge,
                    $discount
                );

                // Record the real payment/order state chosen on this page.
                // 'paid' is stored as payment_method='paid' with an already-
                // paid status; COD and Manual UPI start 'pending'.
                $paymentStatus = $paymentMethod === 'paid' ? 'paid' : 'pending';
                update_order_payment_status($order['id'], $paymentStatus, $orderStatus, $paymentMethod);

                // Stock deduction point for the admin flow, mirroring the
                // customer flow: COD is accepted at placement and 'paid'
                // means payment is already confirmed, so both reserve stock
                // now. Manual UPI is NOT deducted here - it is reserved only
                // when an admin later marks the payment Paid (the same rule
                // as the customer Manual UPI verify flow).
                if ($paymentMethod === 'cod' || $paymentMethod === 'paid') {
                    apply_order_stock_deduction($order['id']);
                }

                // Phase 5D Step 2: send the confirmation email for
                // orders that are confirmed at creation (COD is accepted
                // at placement; 'paid' means the payment was already
                // collected - that one also fired through
                // update_order_payment_status() above and is skipped here
                // by the dedup guard). Idempotent + fail-closed: a mail
                // outage can never break order creation.
                try {
                    send_order_confirmation_email($order['id']);
                } catch (Throwable $e) {
                    error_log('order-create.php: confirmation email trigger failed for order #' . $order['id'] . ' - ' . $e->getMessage());
                }

                flash_set('success', 'Order ' . $order['order_number'] . ' created successfully.');
                redirect('order-detail.php?id=' . $order['id']);

            } catch (Exception $e) {

                error_log('order-create.php: order creation failed - ' . $e->getMessage());

                // Rare edge: the order was created and confirmed but the
                // stock deduction then failed (a concurrent order took the
                // last units between our pre-flight check and the guarded
                // UPDATE). Don't leave a confirmed-but-unfulfillable order -
                // cancel it, same as checkout.php does for COD.
                if (isset($order['id']) && ($paymentMethod === 'cod' || $paymentMethod === 'paid')) {
                    try {
                        update_order_status($order['id'], 'cancelled', 'Cancelled automatically - insufficient stock.');
                    } catch (Exception $ignored) {
                        error_log('order-create.php: failed to auto-cancel order #' . $order['id'] . ' after stock deduction failure.');
                    }
                }

                $errors[] = 'Something went wrong while creating the order. Please try again.';
            }
        }
    }
}

/* ==========================================
   CURRENT TOTALS FOR THE SUMMARY
   (server-side, after any action ran)
========================================== */

// Re-load product rows for the (possibly modified) item list - an item
// added above must be resolvable for rendering and totals.
$itemProducts = [];
$itemQtys     = array_pad($itemQtys, count($itemProductIds), 1);

if (!empty($itemProductIds)) {
    $uniqueIds = array_values(array_unique(array_filter($itemProductIds, static fn ($v) => $v > 0)));
    if (!empty($uniqueIds)) {
        $in   = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = db()->prepare("SELECT * FROM products WHERE id IN ({$in})");
        $stmt->execute($uniqueIds);
        foreach ($stmt->fetchAll() as $p) {
            $itemProducts[$p['id']] = $p;
        }
    }
}

$subtotal = 0.0;
foreach ($itemProductIds as $i => $productId) {
    if (isset($itemProducts[$productId])) {
        $subtotal += (float) $itemProducts[$productId]['sell_price'] * (int) $itemQtys[$i];
    }
}
$grandTotal = max(0.0, $subtotal - $discount + $shippingCharge);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="<?= asset_url('assets/images/icons/favicon/favicon.webp') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('assets/images/icons/favicon/apple-touch-icon.webp') ?>">
    <title><?= h($pageTitle) ?> | MoonAura Admin</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= versioned_asset('dashboard/assets/css/admin.css', 'assets/css/admin.css') ?>">
</head>
<body class="admin-body">

    <div class="admin-wrapper">

        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>

        <div class="admin-main">

            <?php include __DIR__ . '/includes/admin-header.php'; ?>

            <div class="admin-content">

                <div class="admin-toolbar admin-toolbar-end admin-product-form-toolbar">
                    <a href="orders.php" class="admin-btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Orders
                    </a>
                    <button type="submit" form="admin-create-order-form" name="action" value="create_order" class="admin-btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Create Order
                    </button>
                </div>

                <?php if ($successMessage): ?>
                    <div class="admin-alert admin-alert-success"><?= h($successMessage) ?></div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="admin-alert admin-alert-error"><?= h($errorMessage) ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="admin-alert admin-alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="order-create.php" class="admin-create-order-form" id="admin-create-order-form">

                    <?= csrf_field() ?>

                    <!-- ============ CUSTOMER + SHIPPING ADDRESS ============ -->

                    <div class="admin-detail-grid">

                        <div class="admin-form-card">

                            <h3 class="admin-form-section-title">
                                Customer
                            </h3>

                            <label class="admin-checkbox-label" style="margin-top: 4px;">
                                <input type="radio" name="customer_mode" value="existing"
                                    <?= $customerMode === 'existing' ? 'checked' : '' ?>>
                                Existing customer
                            </label>

                            <label class="admin-checkbox-label">
                                <input type="radio" name="customer_mode" value="guest"
                                    <?= $customerMode === 'guest' ? 'checked' : '' ?>>
                                Guest order (walk-in / phone)
                            </label>

                            <div id="oc-existing-section"<?= $customerMode === 'guest' ? ' hidden' : '' ?>>

                                <label for="customer_id">Customer</label>
                                <select name="customer_id" id="customer_id">
                                    <option value="">Select a customer...</option>
                                    <?php foreach ($customers as $customerOption): ?>
                                        <option value="<?= (int) $customerOption['id'] ?>"
                                            <?= $customerId === (int) $customerOption['id'] ? 'selected' : '' ?>>
                                            <?= h($customerOption['name']) ?> &middot; <?= h($customerOption['email']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <?php if ($selectedCustomer): ?>
                                    <p style="font-size: 13px; color: var(--text-light); line-height: 1.6; margin-top: 8px;">
                                        <strong style="color: var(--text);"><?= h($selectedCustomer['name']) ?></strong><br>
                                        <?= h($selectedCustomer['email']) ?><br>
                                        +91 <?= h($selectedCustomer['phone']) ?>
                                    </p>
                                <?php else: ?>
                                    <p style="font-size: 13px; color: var(--text-light); line-height: 1.6; margin-top: 8px;">
                                        Picking a customer links the order to their account and
                                        autofills their default saved address.
                                    </p>
                                <?php endif; ?>

                                <input type="hidden" name="prev_customer_id" value="<?= (int) $customerId ?>">

                            </div>

                            <div id="oc-guest-section"<?= $customerMode === 'guest' ? '' : ' hidden' ?>>

                                <label for="guest_name">Full Name</label>
                                <input type="text" id="guest_name" name="guest_name" maxlength="150" value="<?= h($guestName) ?>">

                                <label for="guest_email">Email</label>
                                <input type="email" id="guest_email" name="guest_email" maxlength="150" value="<?= h($guestEmail) ?>">

                                <label for="guest_phone">Mobile</label>
                                <input type="tel" id="guest_phone" name="guest_phone" maxlength="15" value="<?= h($guestPhone) ?>" placeholder="10-digit mobile number">

                            </div>

                        </div>

                        <div class="admin-form-card">

                            <h3 class="admin-form-section-title">
                                Shipping Address
                            </h3>

                            <label for="address_line1">Address Line 1</label>
                            <input type="text" id="address_line1" name="address_line1" maxlength="255" value="<?= h($address['address_line1']) ?>" placeholder="House no, building, street">

                            <label for="address_line2">Address Line 2 <span class="admin-field-hint">(optional)</span></label>
                            <input type="text" id="address_line2" name="address_line2" maxlength="255" value="<?= h($address['address_line2']) ?>">

                            <label for="landmark">Landmark <span class="admin-field-hint">(optional)</span></label>
                            <input type="text" id="landmark" name="landmark" maxlength="255" value="<?= h($address['landmark']) ?>">

                            <div class="admin-detail-grid" style="gap: 12px; margin-top: 0;">

                                <div>
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" maxlength="100" value="<?= h($address['city']) ?>">
                                </div>

                                <div>
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state" maxlength="100" value="<?= h($address['state']) ?>">
                                </div>

                            </div>

                            <label for="pincode">PIN Code</label>
                            <input type="text" id="pincode" name="pincode" maxlength="6" value="<?= h($address['pincode']) ?>" placeholder="6-digit PIN code">

                        </div>

                    </div>

                    <!-- ============ PRODUCTS ============ -->

                    <div class="admin-form-card" style="margin-top: 24px; max-width: none;">

                        <h3 class="admin-form-section-title">
                            Products
                        </h3>

                        <label for="product_search">Search Products</label>
                        <input
                            type="text"
                            id="product_search"
                            autocomplete="off"
                            placeholder="Type to filter the product list..."
                        >

                        <div class="admin-detail-grid" style="gap: 12px; margin-top: 0;">

                            <div>
                                <label for="add_product_id">Product</label>
                                <select name="add_product_id" id="add_product_id">
                                    <option value="">Select a product...</option>
                                    <?php foreach ($productOptions as $productOption): ?>
                                        <option value="<?= (int) $productOption['id'] ?>">
                                            <?= h($productOption['name'] . ' (' . $productOption['sku'] . ') — '
                                                . format_price((float) $productOption['sell_price']) . ' — '
                                                . (int) $productOption['stock_quantity'] . ' in stock') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="max-width: 140px;">
                                <label for="add_qty">Quantity</label>
                                <input type="number" id="add_qty" name="add_qty" min="1" value="1">
                            </div>

                        </div>

                        <div class="admin-form-actions">
                            <button type="submit" name="action" value="add_item" class="admin-btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                Add Item
                            </button>
                        </div>

                        <div class="admin-table-card" style="margin-top: 20px;">

                            <table class="admin-table">

                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Unit Price</th>
                                        <th>Qty</th>
                                        <th>Line Total</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php if (empty($itemProductIds)): ?>

                                        <tr>
                                            <td colspan="5" class="admin-table-empty">
                                                No products added yet.
                                            </td>
                                        </tr>

                                    <?php else: ?>

                                        <?php foreach ($itemProductIds as $index => $productId): ?>

                                            <?php if (!isset($itemProducts[$productId])): continue; endif; ?>

                                            <?php $lineProduct = $itemProducts[$productId]; ?>
                                            <?php $lineQty = (int) ($itemQtys[$index] ?? 1); ?>

                                            <tr>
                                                <td>
                                                    <?= h($lineProduct['name']) ?>
                                                    <span class="admin-table-subtext">
                                                        <code><?= h($lineProduct['sku']) ?></code>
                                                        &middot; <?= (int) $lineProduct['stock_quantity'] ?> in stock
                                                    </span>
                                                </td>
                                                <td><?= h(format_price((float) $lineProduct['sell_price'])) ?></td>
                                                <td style="min-width: 96px;">
                                                    <input
                                                        type="hidden"
                                                        name="item_product_id[]"
                                                        value="<?= (int) $productId ?>"
                                                    >
                                                    <input
                                                        type="number"
                                                        name="item_qty[]"
                                                        min="1"
                                                        value="<?= $lineQty ?>"
                                                        style="width: 72px; padding: 6px 8px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px;"
                                                    >
                                                </td>
                                                <td><?= h(format_price((float) $lineProduct['sell_price'] * $lineQty)) ?></td>
                                                <td class="admin-table-actions">
                                                    <button
                                                        type="submit"
                                                        name="remove_index"
                                                        value="<?= $index ?>"
                                                        class="admin-icon-btn-danger"
                                                        title="Remove item"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                    <!-- ============ SUMMARY + PAYMENT/STATUS ============ -->

                    <div class="admin-detail-grid" style="margin-top: 24px;">

                        <div class="admin-form-card">

                            <h3 class="admin-form-section-title">
                                Order Summary
                            </h3>

                            <div class="admin-detail-row">
                                <span>Subtotal</span>
                                <span id="oc-subtotal" data-subtotal="<?= number_format($subtotal, 2, '.', '') ?>">
                                    <?= h(format_price($subtotal)) ?>
                                </span>
                            </div>

                            <label for="shipping_charge">Shipping Charge</label>
                            <input type="number" id="shipping_charge" name="shipping_charge" min="0" step="0.01" value="<?= h(number_format($shippingCharge, 2, '.', '')) ?>">

                            <label for="discount">Discount</label>
                            <input type="number" id="discount" name="discount" min="0" step="0.01" value="<?= h(number_format($discount, 2, '.', '')) ?>">

                            <div class="admin-detail-row" style="font-size: 16px; margin-top: 10px;">
                                <span>Grand Total</span>
                                <span id="oc-grand-total" style="color: var(--primary);">
                                    <?= h(format_price($grandTotal)) ?>
                                </span>
                            </div>

                        </div>

                        <div class="admin-form-card">

                            <h3 class="admin-form-section-title">
                                Payment &amp; Status
                            </h3>

                            <label style="margin-top: 4px;">Payment Method</label>

                            <label class="admin-checkbox-label">
                                <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
                                Cash on Delivery
                            </label>

                            <label class="admin-checkbox-label">
                                <input type="radio" name="payment_method" value="manual_upi" <?= $paymentMethod === 'manual_upi' ? 'checked' : '' ?>>
                                Manual UPI Payment
                            </label>

                            <label class="admin-checkbox-label">
                                <input type="radio" name="payment_method" value="paid" <?= $paymentMethod === 'paid' ? 'checked' : '' ?>>
                                Paid (already collected)
                            </label>

                            <p style="font-size: 13px; color: var(--text-light); line-height: 1.6; margin: 10px 0 0;">
                                <strong style="color: var(--text);">COD</strong> and <strong style="color: var(--text);">Paid</strong>
                                reserve stock immediately. <strong style="color: var(--text);">Manual UPI</strong> keeps
                                the order pending until you mark the payment Paid on the order page
                                (that is when stock is reserved).
                            </p>

                            <h3 class="admin-form-section-title">Order Status</h3>

                            <label for="order_status">Status</label>
                            <select name="order_status" id="order_status">
                                <option value="pending"    <?= $orderStatus === 'pending'    ? 'selected' : '' ?>>Pending</option>
                                <option value="processing" <?= $orderStatus === 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="delivered"  <?= $orderStatus === 'delivered'  ? 'selected' : '' ?>>Completed</option>
                            </select>
                            <p class="admin-field-hint" style="margin-top: 6px;">
                                "Completed" is stored as the existing <em>Delivered</em> status -
                                the terminal status the order timeline and badges already use.
                            </p>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

    <script>
    (function () {
        // --- Customer mode toggle (existing / guest) ---
        var existingSection = document.getElementById('oc-existing-section');
        var guestSection    = document.getElementById('oc-guest-section');
        var modeRadios      = document.querySelectorAll('input[name="customer_mode"]');

        function toggleMode() {
            var mode = document.querySelector('input[name="customer_mode"]:checked').value;
            existingSection.hidden = mode !== 'existing';
            guestSection.hidden    = mode !== 'guest';
        }

        modeRadios.forEach(function (radio) {
            radio.addEventListener('change', toggleMode);
        });
        toggleMode();

        // --- Client-side product search filter (no AJAX needed) ---
        var searchInput   = document.getElementById('product_search');
        var productSelect = document.getElementById('add_product_id');

        function filterProducts() {
            var query = searchInput.value.toLowerCase();
            Array.prototype.forEach.call(productSelect.options, function (option) {
                if (option.value === '') return;
                option.style.display = option.text.toLowerCase().indexOf(query) === -1 ? 'none' : '';
            });
        }

        if (searchInput && productSelect) {
            searchInput.addEventListener('input', filterProducts);
        }

        // --- Live grand-total preview (authoritative totals are server-side) ---
        var subtotalEl = document.getElementById('oc-subtotal');
        var shippingEl = document.getElementById('shipping_charge');
        var discountEl = document.getElementById('discount');
        var grandEl    = document.getElementById('oc-grand-total');

        function updateGrandTotal() {
            var subtotal = parseFloat(subtotalEl.getAttribute('data-subtotal')) || 0;
            var shipping = parseFloat(shippingEl.value) || 0;
            var discount = parseFloat(discountEl.value) || 0;
            grandEl.textContent = '\u20B9' + Math.max(0, subtotal - discount + shipping).toFixed(2);
        }

        if (subtotalEl && shippingEl && discountEl && grandEl) {
            shippingEl.addEventListener('input', updateGrandTotal);
            discountEl.addEventListener('input', updateGrandTotal);
        }
    })();
    </script>

</body>
</html>
