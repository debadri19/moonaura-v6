<?php
/* ===================================================================
   STOCK FUNCTIONS
   -------------------------------------------------------------------
   Phase 6: inventory deduction/restoration tied to an order's
   CONFIRMATION lifecycle, not to order creation. The deduction point
   differs per payment method (see the Phase 6 stock audit):

     - COD:          the order is accepted at placement, so stock is
                     deducted right after checkout.php confirms it
                     (checkout.php's 'cod' branch).
     - Manual UPI:   deducted only when an admin VERIFIES the payment
                     in dashboard/order-detail.php - never at creation and
                     never on reject.
     - Razorpay:     deducted only after the gateway has confirmed
                     payment (PaymentManager::verifyPayment() and
                     PaymentManager::handleWebhook(), i.e. once the
                     order actually reaches payment_status='paid').

   Both helpers are idempotent via the orders.stock_deducted_at marker
   column: stock is deducted only while the marker is NULL and restored
   only while it is NOT NULL. Every caller can therefore invoke them
   unconditionally - a double-fired webhook, a retried payment, a
   rejected-then-verified Manual UPI payment, and cancelling an unpaid
   order that never consumed stock are all safe.

   Transactions: each helper runs inside a transaction that first locks
   the order row with SELECT ... FOR UPDATE (serialising concurrent
   callbacks for the same order), and each product decrement is guarded
   (AND stock_quantity >= qty) so stock can never go negative - if any
   line cannot be covered, the whole deduction rolls back and throws,
   leaving the order untouched.
=================================================================== */

require_once __DIR__ . '/db.php';


/* ==========================================
   HAS THIS ORDER'S STOCK ALREADY BEEN DEDUCTED?
   ------------------------------------------
   Read-only peek at the marker used by all the idempotency guards.
   Returns true only when stock is currently held against this order.
========================================== */

function order_has_deducted_stock(int $orderId): bool
{
    $stmt = db()->prepare('SELECT stock_deducted_at FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $marker = $stmt->fetchColumn();

    return $marker !== null && $marker !== false;
}


/* ==========================================
   RE-SYNC ONE PRODUCT'S stock_status WITH ITS stock_quantity
   ------------------------------------------
   Auto-flips a product to 'out_of_stock' when its quantity reaches
   zero (so an exhausted product is never still labelled "In Stock"),
   and upgrades 'out_of_stock' back to 'in_stock' when a restoration
   brings quantity above zero. An admin-managed 'low_stock' label is
   left untouched - there is no configured low-stock threshold in this
   system, so nothing else is auto-inferred.
========================================== */

function refresh_product_stock_status(PDO $pdo, int $productId): void
{
    $stmt = $pdo->prepare('SELECT stock_quantity, stock_status FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();

    if (!$row) {
        return; // product was deleted - nothing to resync
    }

    $quantity   = (int) $row['stock_quantity'];
    $newStatus  = $row['stock_status'];

    if ($quantity <= 0) {
        $newStatus = 'out_of_stock';
    } elseif ($newStatus === 'out_of_stock') {
        // Restored above zero - can't stay out_of_stock with stock on hand.
        $newStatus = 'in_stock';
    }

    if ($newStatus !== $row['stock_status']) {
        $stmt = $pdo->prepare('UPDATE products SET stock_status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $productId]);
    }
}


/* ==========================================
   DEDUCT STOCK FOR A CONFIRMED ORDER
   ------------------------------------------
   Call at the point the order becomes "confirmed/valid" for its
   payment method (see the header note). Safe to call repeatedly:
   once the marker is set, subsequent calls are a no-op.

   Throws RuntimeException if any line item has insufficient stock -
   the transaction rolls back so NO stock is deducted from ANY product
   and the marker stays NULL.
========================================== */

function apply_order_stock_deduction(int $orderId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {

        // Lock the order row and read the marker in the same statement -
        // serialises two callbacks arriving for the same order.
        $stmt = $pdo->prepare('SELECT stock_deducted_at FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $marker = $stmt->fetchColumn();

        if ($marker !== null && $marker !== false) {
            $pdo->rollBack(); // already deducted - nothing to do
            return;
        }

        $stmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        $deduct = $pdo->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity - ?,
                 updated_at     = NOW()
             WHERE id = ? AND stock_quantity >= ?'
        );

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity  = (int) $item['quantity'];

            if ($productId <= 0 || $quantity <= 0) {
                continue; // snapshot-only line (e.g. product since deleted)
            }

            $deduct->execute([$quantity, $productId, $quantity]);

            if ($deduct->rowCount() === 0) {
                throw new RuntimeException(
                    'Insufficient stock for product #' . $productId
                    . ' to fulfil order #' . $orderId . '.'
                );
            }

            refresh_product_stock_status($pdo, $productId);
        }

        $stmt = $pdo->prepare('UPDATE orders SET stock_deducted_at = NOW() WHERE id = ?');
        $stmt->execute([$orderId]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('apply_order_stock_deduction() failed for order #' . $orderId . ': ' . $e->getMessage());
        throw $e;
    }
}


/* ==========================================
   RESTORE STOCK FOR A CANCELLED ORDER
   ------------------------------------------
   Reverses apply_order_stock_deduction(). Safe to call repeatedly:
   an order that was never deducted (marker NULL - e.g. a cancelled
   unpaid online order) has nothing to restore and is a no-op.
========================================== */

function restore_order_stock(int $orderId): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {

        $stmt = $pdo->prepare('SELECT stock_deducted_at FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $marker = $stmt->fetchColumn();

        if ($marker === null || $marker === false) {
            $pdo->rollBack(); // never deducted - nothing to give back
            return;
        }

        $stmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();

        $restore = $pdo->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + ?,
                 updated_at     = NOW()
             WHERE id = ?'
        );

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity  = (int) $item['quantity'];

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $restore->execute([$quantity, $productId]);
            refresh_product_stock_status($pdo, $productId);
        }

        $stmt = $pdo->prepare('UPDATE orders SET stock_deducted_at = NULL WHERE id = ?');
        $stmt->execute([$orderId]);

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('restore_order_stock() failed for order #' . $orderId . ': ' . $e->getMessage());
        throw $e;
    }
}


/* ==========================================
   PRE-FLIGHT STOCK CHECK FOR THE CART
   ------------------------------------------
   Returns the cart items that CANNOT be fulfilled from current stock,
   each as ['name' => product name, 'requested' => qty in cart,
   'available' => current stock]. Used by checkout.php to show a
   friendly per-product message BEFORE an order is created - the
   authoritative no-negative guarantee still lives in
   apply_order_stock_deduction() (guarded UPDATE), this is purely a
   nicer error surface for the common case.
========================================== */

function validate_cart_stock(array $cartItems): array
{
    $shortages = [];

    foreach ($cartItems as $item) {
        if (empty($item['product']['id'])) {
            continue;
        }

        $productId = (int) $item['product']['id'];
        $requested = (int) $item['quantity'];

        if ($requested <= 0) {
            continue;
        }

        $stmt = db()->prepare('SELECT stock_quantity FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $available = (int) $stmt->fetchColumn();

        if ($available < $requested) {
            $shortages[] = [
                'name'      => (string) ($item['product']['name'] ?? 'Product'),
                'requested' => $requested,
                'available' => $available,
            ];
        }
    }

    return $shortages;
}
