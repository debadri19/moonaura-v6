<?php
/* ===================================================================
   ORDER EMAIL NOTIFICATIONS (Phase 5D Step 2)
   -------------------------------------------------------------------
   The single place that decides WHEN a transactional order email
   (order confirmation / shipped / delivered) is sent, and the ONLY
   place that may call send_email() for those three emails.

   WHY A DEDICATED FILE:
   - Order emails have THREE trigger sites (order placement, the
     payment-confirmation path, and the admin order-status form) that
     can each fire more than once (retried webhooks, re-submitted
     status forms, double callbacks). The dedup + attempt log below
     centralises that so no caller has to remember to guard against
     duplicates.
   - "Email must never break the order/payment flow" is enforced here
     too: every public function is fail-closed (never throws), so a
     dead SMTP server, an invalid recipient, or a missing database
     column can only ever log an error, never 500 a checkout or a
     webhook.

   DEDUP SEMANTICS:
   - order_email_log rows with status = 'sent' are the "already
     delivered" marker. Only they suppress a retry.
   - A 'failed' attempt does NOT suppress a later attempt - a
     transient SMTP outage recovers on the next trigger. This is a
     "send at most once, but never silently drop a customer" policy.
   - The check and the insert are deliberately NOT one atomic
     transaction: the goal is "no duplicate emails", and the window
     between the SELECT and the INSERT is covered by the fact that the
     three trigger sites run sequentially per order in practice. A
     stricter SELECT ... FOR UPDATE would serialise concurrent
     webhooks but also adds locking to a hot path; the outcome of a
     genuine race is at worst two sends of the SAME email to the same
     order - acceptable and logged, and not something the admin status
     form can trigger at all.
================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/order-functions.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email-templates.php';


/* ==========================================
   EMAIL TYPE KEYS (order_email_log.email_type)
========================================== */

function order_email_type_confirmation(): string { return 'order_confirmation'; }
function order_email_type_shipped(): string     { return 'order_shipped'; }
function order_email_type_delivered(): string   { return 'order_delivered'; }


/* ==========================================
   HAS THIS EMAIL ALREADY BEEN SENT?
   Only status='sent' rows count. A failed
   attempt is not "sent" and does not block
   a retry.
========================================== */

function order_email_was_sent(int $orderId, string $emailType): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM order_email_log
         WHERE order_id = ? AND email_type = ? AND status = "sent"'
    );
    $stmt->execute([$orderId, $emailType]);

    return (int) $stmt->fetchColumn() > 0;
}


/* ==========================================
   RECORD AN ATTEMPT
   Logs one row per attempt for the audit
   trail. Never throws - a logging failure must
   not break the caller.
========================================== */

function order_email_log_attempt(
    int $orderId,
    string $emailType,
    string $recipient,
    string $subject,
    bool $ok,
    ?string $errorMessage = null
): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO order_email_log (order_id, email_type, recipient, subject, status, error_message)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $orderId,
            $emailType,
            mb_substr($recipient, 0, 190),
            mb_substr($subject, 0, 190),
            $ok ? 'sent' : 'failed',
            $errorMessage !== null ? mb_substr($errorMessage, 0, 1000) : null,
        ]);
    } catch (Exception $e) {
        error_log('order_email_log_attempt() failed for order #' . $orderId . ' (' . $emailType . '): ' . $e->getMessage());
    }
}


/* ==========================================
   LOAD AN ORDER'S LINE ITEMS + ADDRESS
========================================== */

function order_email_load_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $stmt->execute([$orderId]);

    return $stmt->fetchAll() ?: [];
}

function order_email_load_address(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_addresses WHERE order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);

    return $stmt->fetch() ?: [];
}


/* ==========================================
   GENERIC "SEND IF DUE" CORE
   -------------------------------------------------
   $orderId      - the order.
   $emailType    - one of the order_email_type_*() keys.
   $buildEmail   - callable(array $order, array $items, array $address)
                   -> ['subject', 'html', 'text'].

   Behaviour (fail-closed throughout):
   - already sent (status='sent') -> skip, return false;
   - order missing -> skip;
   - recipient missing/invalid   -> log a failed attempt, return false
     (the customer_email is never valid-empty for a real order, but a
     legacy/admin-typed row could have one);
   - send_email() fails (SMTP down, unconfigured, etc.) -> log the
     failed attempt, return false. The ORDER/PAYMENT flow is never
     affected.
========================================== */

function send_order_email_if_due(int $orderId, string $emailType, callable $buildEmail): bool
{
    try {

        if (order_email_was_sent($orderId, $emailType)) {
            return false; // already delivered - never send twice
        }

        $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            error_log('send_order_email_if_due(): order #' . $orderId . ' not found, email type ' . $emailType . ' skipped.');
            return false;
        }

        $recipient = normalize_email((string) ($order['customer_email'] ?? ''));

        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            error_log('send_order_email_if_due(): order #' . $orderId . ' has no valid customer email, ' . $emailType . ' email skipped.');
            order_email_log_attempt($orderId, $emailType, $recipient, '', false, 'Invalid or missing customer email.');
            return false;
        }

        $items   = order_email_load_items($orderId);
        $address = order_email_load_address($orderId);

        $email = $buildEmail($order, $items, $address);

        $failureReason = null;

        $ok = send_email(
            $recipient,
            (string) ($order['customer_name'] ?? ''),
            (string) ($email['subject'] ?? ''),
            (string) ($email['html'] ?? ''),
            (string) ($email['text'] ?? ''),
            null,
            $failureReason
        );

        order_email_log_attempt($orderId, $emailType, $recipient, (string) ($email['subject'] ?? ''), $ok, $failureReason);

        return $ok;

    } catch (Exception $e) {
        error_log('send_order_email_if_due() failed for order #' . $orderId . ' (' . $emailType . '): ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('send_order_email_if_due() unexpected error for order #' . $orderId . ' (' . $emailType . '): ' . $e->getMessage());
        return false;
    }
}


/* ==========================================
   IS THIS ORDER CONFIRMED ENOUGH TO EMAIL?
   -------------------------------------------------
   The confirmation email is only for orders that are genuinely
   accepted: COD (accepted at placement, even if the admin later
   marked it pending-ish) or payment_status='paid' (online gateways +
   Manual UPI after admin verification + admin-marked-paid). A
   cancelled order never gets a confirmation email even if it was paid
   once and then cancelled.
========================================== */

function is_order_confirmed_for_email(array $order): bool
{
    return $order['order_status'] !== 'cancelled'
        && ($order['payment_method'] === 'cod' || $order['payment_status'] === 'paid');
}


/* ==========================================
   ORDER CONFIRMATION EMAIL
   Safe to call from ANY of the confirmation
   points (checkout COD branch, payment-confirmed
   path, admin-created orders): the internal
   confirmed-check + dedup make it idempotent.
========================================== */

function send_order_confirmation_email(int $orderId): bool
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || !is_order_confirmed_for_email($order)) {
        return false; // not confirmed yet - nothing to send
    }

    return send_order_email_if_due($orderId, order_email_type_confirmation(), function (array $o, array $items, array $address) {
        return order_confirmation_email($o, $items, $address);
    });
}


/* ==========================================
   ORDER SHIPPED EMAIL
   The $shipDate is the moment the order really
   transitioned to 'shipped' - the caller
   (update_order_status()) passes the timestamp
   it just wrote to the timeline.
========================================== */

function send_order_shipped_email(int $orderId, string $shipDate): bool
{
    return send_order_email_if_due($orderId, order_email_type_shipped(), function (array $o, array $items, array $address) use ($shipDate) {
        return order_shipped_email($o, $items, $address, $shipDate);
    });
}


/* ==========================================
   ORDER DELIVERED EMAIL
========================================== */

function send_order_delivered_email(int $orderId, string $deliveryDate): bool
{
    return send_order_email_if_due($orderId, order_email_type_delivered(), function (array $o, array $items, array $address) use ($deliveryDate) {
        return order_delivered_email($o, $address, $deliveryDate);
    });
}
