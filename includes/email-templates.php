<?php
/* ===================================================================
   EMAIL TEMPLATES (Phase 5D)
   -------------------------------------------------------------------
   Transactional email templates for MoonAura Crystals. Each template
   is a function returning an array:
       ['subject' => string, 'html' => string, 'text' => string]
   which is handed straight to send_email() in includes/mailer.php.

   BRANDING:
       MoonAura Crystals - "Guided by the Moon, Inspired by Nature"

   DESIGN RULES (kept on purpose):
   - Mobile-first single-column layout, max 600px, inline CSS only
     (mail clients strip <style> blocks), no external assets/fonts so
     it renders identically in every client without network access.
   - A single obvious call-to-action button for the primary action.
   - An expiry notice on every reset email (links live 60 minutes).
   - Text-alternative bodies are provided for every HTML template.
================================================================== */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/order-functions.php'; // timeline_event_label() for order status labels


/* ==========================================
   SHARED HTML SHELL
   Wraps a heading + body + optional CTA button
   in the branded, mobile-safe email layout.
   $ctaText/$ctaUrl - if both are non-empty a
   big obvious button is rendered.
   $footerNote  - replaces the default "If you
     did not request this..." paragraph (which
     is password-reset-specific). Pass '' to
     keep the default reset wording.
   $footerCaption - the small line under the
     footer. Order emails pass their own so the
     "replies are not monitored" default (which
     is wrong when support reads replies) can be
     swapped for the support contact line.
========================================== */

function email_layout_html(
    string $heading,
    string $bodyHtml,
    string $ctaText = '',
    string $ctaUrl = '',
    string $footerNote = '',
    string $footerCaption = 'This is an automated message; replies are not monitored.'
): string
{
    $button = '';

    if ($ctaText !== '' && $ctaUrl !== '') {
        $button = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 28px 0 8px;">
                <tr>
                    <td align="center">
                        <a href="' . h($ctaUrl) . '"
                           style="display: inline-block; background: #3b4a6b; color: #ffffff; text-decoration: none;
                                  font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: 600;
                                  line-height: 50px; padding: 0 36px; border-radius: 8px;">
                            ' . h($ctaText) . '
                        </a>
                    </td>
                </tr>
            </table>';
    }

    $siteName = h(SITE_NAME);

    if ($footerNote === '') {
        $footerNote = 'If you did not request this, you can safely ignore this email &mdash; your password will not change unless you click the link above.';
    }

    return '
<!DOCTYPE html>
<html lang="en">
<body style="margin: 0; padding: 0; background: #f3efe8;">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: #f3efe8; padding: 24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width: 600px;">

                    <!-- Brand header -->
                    <tr>
                        <td align="center" style="padding: 8px 0 20px;">
                            <div style="font-family: Georgia, \'Times New Roman\', serif; font-size: 26px; font-weight: 700; color: #2c2a4a; letter-spacing: 1px;">
                                ' . $siteName . '
                            </div>
                            <div style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #8b8678; letter-spacing: 2px; margin-top: 4px;">
                                GUIDED BY THE MOON, INSPIRED BY NATURE
                            </div>
                        </td>
                    </tr>

                    <!-- Card -->
                    <tr>
                        <td style="background: #ffffff; border-radius: 10px; padding: 32px 28px;">

                            <h1 style="margin: 0 0 16px; font-family: Georgia, \'Times New Roman\', serif; font-size: 22px; font-weight: 700; color: #2c2a4a;">
                                ' . h($heading) . '
                            </h1>

                            ' . $bodyHtml . '

                            ' . $button . '

                            <p style="margin: 24px 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.6; color: #8b8678;">
                                ' . $footerNote . '
                            </p>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 20px 8px 0;">
                            <div style="font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.7; color: #8b8678;">
                                ' . $siteName . ' &middot; Guided by the Moon, Inspired by Nature<br>
                                ' . h($footerCaption) . '
                            </div>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>';
}


/* ==========================================
   SHARED PLAIN-TEXT SHELL
   Keeps the branding + expiry wording in sync
   with the HTML version for text-only clients.
========================================== */

function email_layout_text(string $heading, string $bodyText, string $footerNote = ''): string
{
    $text = SITE_NAME . " - Guided by the Moon, Inspired by Nature\n"
        . "==========================================\n\n"
        . $heading . "\n\n"
        . trim($bodyText) . "\n";

    if ($footerNote !== '') {
        $text .= "\n" . $footerNote . "\n";
    }

    return $text;
}


/* ==========================================
   CUSTOMER PASSWORD RESET
   $resetLink - the one-time, 60-minute link to
   account/reset-password.php?token=...
========================================== */

function customer_password_reset_email(string $resetLink): array
{
    $heading = 'Reset Your Password';
    $bodyHtml = '
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            We received a request to reset the password for your MoonAura Crystals account.
            To choose a new password, click the button below.
        </p>
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            This link is <strong>valid for 60 minutes</strong> and can only be used once.
        </p>';

    $bodyText = "We received a request to reset the password for your MoonAura Crystals account.\n"
        . "To choose a new password, open the link below.\n\n"
        . $resetLink . "\n\n"
        . "This link is valid for 60 minutes and can only be used once.";

    return [
        'subject' => 'Reset your MoonAura Crystals password',
        'html'    => email_layout_html($heading, $bodyHtml, 'Reset My Password', $resetLink),
        'text'    => email_layout_text($heading, $bodyText),
    ];
}


/* ==========================================
   ADMIN PASSWORD RESET
   Same shape as the customer version, but aimed
   at a MoonAura admin account.
========================================== */

function admin_password_reset_email(string $resetLink): array
{
    $heading = 'Reset Your Admin Password';
    $bodyHtml = '
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            We received a request to reset the password for your MoonAura Crystals admin account.
            To choose a new password, click the button below.
        </p>
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            This link is <strong>valid for 60 minutes</strong> and can only be used once.
        </p>';

    $bodyText = "We received a request to reset the password for your MoonAura Crystals admin account.\n"
        . "To choose a new password, open the link below.\n\n"
        . $resetLink . "\n\n"
        . "This link is valid for 60 minutes and can only be used once.";

    return [
        'subject' => 'Reset your MoonAura Crystals admin password',
        'html'    => email_layout_html($heading, $bodyHtml, 'Reset My Password', $resetLink),
        'text'    => email_layout_text($heading, $bodyText),
    ];
}


/* ===================================================================
   ORDER TRANSACTIONAL EMAILS (Phase 5D Step 2)
   -------------------------------------------------------------------
   Three templates: order confirmation (on placement / payment
   confirmation), order shipped, and order delivered. Each returns
   ['subject', 'html', 'text'] for send_email() in includes/mailer.php.

   Inputs are always the raw database rows (the order + its
   order_items + its order_addresses row). ALL customer/product/order
   values are escaped with h() before they touch HTML, and every HTML
   template has a hand-written plain-text twin (the HTML->text fallback
   in mailer.php is only a safety net, not the primary body).

   The sender/Reply-To are handled by the mailer (MAIL_FROM_ADDRESS =
   noreply@..., MAIL_REPLY_TO_ADDRESS = support@... by default) - the
   templates below never embed addresses in the body except the
   support contact shown in the delivered email.
================================================================== */

/* ------------------------------------------
   SHARED: formatted address block (HTML)
------------------------------------------ */

function order_address_html(array $address): string
{
    $lines = [];

    if (!empty($address['full_name'])) {
        $lines[] = h($address['full_name']);
    }

    if (!empty($address['address_line1'])) {
        $lines[] = h($address['address_line1']);
    }

    if (!empty($address['address_line2'])) {
        $lines[] = h($address['address_line2']);
    }

    if (!empty($address['landmark'])) {
        $lines[] = 'Landmark: ' . h($address['landmark']);
    }

    if (!empty($address['city'])) {
        $parts = [h($address['city'])];
        if (!empty($address['state'])) {
            $parts[] = h($address['state']);
        }
        if (!empty($address['postal_code'])) {
            $parts[] = h($address['postal_code']);
        }
        $lines[] = implode(', ', $parts);
    }

    if (!empty($address['phone'])) {
        $lines[] = 'Phone: ' . h($address['phone']);
    }

    return implode('<br>', $lines);
}


/* ------------------------------------------
   SHARED: formatted address block (plain text)
------------------------------------------ */

function order_address_text(array $address): string
{
    $lines = [];

    foreach (['full_name', 'address_line1', 'address_line2', 'landmark'] as $key) {
        if (!empty($address[$key])) {
            $lines[] = ($key === 'landmark' ? 'Landmark: ' : '') . $address[$key];
        }
    }

    $cityLine = '';
    if (!empty($address['city'])) {
        $cityLine = $address['city'];
    }
    if (!empty($address['state'])) {
        $cityLine .= ($cityLine !== '' ? ', ' : '') . $address['state'];
    }
    if (!empty($address['postal_code'])) {
        $cityLine .= ($cityLine !== '' ? ' - ' : '') . $address['postal_code'];
    }
    if ($cityLine !== '') {
        $lines[] = $cityLine;
    }

    if (!empty($address['phone'])) {
        $lines[] = 'Phone: ' . $address['phone'];
    }

    return implode("\n", $lines);
}


/* ------------------------------------------
   SHARED: order items + totals summary (HTML)
   A responsive single-column table of every
   line item (name, qty, unit price, line total)
   followed by the money rows (subtotal,
   discount, shipping, GST, grand total).
------------------------------------------ */

function order_summary_html(array $order, array $items): string
{
    $rows = '';

    foreach ($items as $item) {
        $name  = h($item['product_name']);
        $qty   = (int) $item['quantity'];
        $unit  = h(format_price((float) $item['unit_price']));
        $total = h(format_price((float) $item['line_total']));

        $rows .= '
            <tr>
                <td style="padding: 10px 8px; border-bottom: 1px solid #eeeef2; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; line-height: 1.5;">'
                    . $name . '<div style="font-size: 12px; color: #8b8678;">Qty: ' . $qty . ' &times; ' . $unit . '</div>
                </td>
                <td style="padding: 10px 8px; border-bottom: 1px solid #eeeef2; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; text-align: right; white-space: nowrap;">' . $total . '</td>
            </tr>';
    }

    $discountRow = '';
    if ((float) $order['discount'] > 0) {
        $discountRow = '
            <tr>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Discount</td>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; text-align: right;">- ' . h(format_price((float) $order['discount'])) . '</td>
            </tr>';
    }

    return '
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 16px 0 4px; border-top: 1px solid #eeeef2;">
            <thead>
                <tr>
                    <th align="left" style="padding: 8px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #8b8678; border-bottom: 2px solid #e4e0d8;">Item</th>
                    <th align="right" style="padding: 8px; font-family: Arial, Helvetica, sans-serif; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #8b8678; border-bottom: 2px solid #e4e0d8;">Total</th>
                </tr>
            </thead>
            <tbody>' . $rows . '
            </tbody>
        </table>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Subtotal</td>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; text-align: right;">' . h(format_price((float) $order['subtotal'])) . '</td>
            </tr>' . $discountRow . '
            <tr>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Shipping</td>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; text-align: right;">' . h(format_price((float) $order['shipping_charge'])) . '</td>
            </tr>
            <tr>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">GST</td>
                <td style="padding: 6px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742; text-align: right;">' . h(format_price((float) $order['gst_amount'])) . '</td>
            </tr>
            <tr>
                <td style="padding: 10px 8px 4px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: 700; color: #2c2a4a;">Grand Total</td>
                <td style="padding: 10px 8px 4px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: 700; color: #2c2a4a; text-align: right;">' . h(format_price((float) $order['grand_total'])) . '</td>
            </tr>
        </table>';
}


/* ------------------------------------------
   SHARED: order items + totals summary (plain text)
------------------------------------------ */

function order_summary_text(array $order, array $items): string
{
    $lines = [];

    foreach ($items as $item) {
        $lines[] = $item['product_name']
            . ' - Qty: ' . (int) $item['quantity']
            . ' x ' . format_price((float) $item['unit_price'])
            . ' = ' . format_price((float) $item['line_total']);
    }

    $lines[] = '';
    $lines[] = 'Subtotal: ' . format_price((float) $order['subtotal']);

    if ((float) $order['discount'] > 0) {
        $lines[] = 'Discount: - ' . format_price((float) $order['discount']);
    }

    $lines[] = 'Shipping: ' . format_price((float) $order['shipping_charge']);
    $lines[] = 'GST: ' . format_price((float) $order['gst_amount']);
    $lines[] = 'Grand Total: ' . format_price((float) $order['grand_total']);

    return implode("\n", $lines);
}


/* ------------------------------------------
   ORDER CONFIRMATION
   Sent when an order becomes confirmed: at
   placement for COD, or when payment is
   confirmed for Razorpay / Manual UPI.
------------------------------------------ */

function order_confirmation_email(array $order, array $items, array $address): array
{
    $heading      = 'Order Confirmed';
    $statusLabel  = timeline_event_label((string) $order['order_status']);
    $paymentLabel = payment_method_label((string) $order['payment_method']);

    $orderMetaHtml = '
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 4px 0 16px;">
            <tr>
                <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Order Number</td>
                <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 600; color: #2c2a4a;">' . h($order['order_number']) . '</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Order Date</td>
                <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h(date('d M Y, h:i A', strtotime($order['created_at']))) . '</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Payment Method</td>
                <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h($paymentLabel) . '</td>
            </tr>
            <tr>
                <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Order Status</td>
                <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h($statusLabel) . '</td>
            </tr>
        </table>';

    $billingHtml = '
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            Thank you for shopping with ' . h(SITE_NAME) . '. Your order has been confirmed
            and is now being prepared.
        </p>
        <h2 style="margin: 20px 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #2c2a4a;">Billing</h2>
        <p style="margin: 0 0 4px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
            ' . h($order['customer_name']) . '<br>
            ' . h($order['customer_email']) . '<br>
            Phone: ' . h($order['customer_phone']) . '
        </p>
        <h2 style="margin: 20px 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #2c2a4a;">Shipping Address</h2>
        <p style="margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
            ' . order_address_html($address) . '
        </p>';

    $bodyHtml = $orderMetaHtml . $billingHtml . order_summary_html($order, $items);

    $orderMetaText = 'Order Number: ' . $order['order_number'] . "\n"
        . 'Order Date: ' . date('d M Y, h:i A', strtotime($order['created_at'])) . "\n"
        . 'Payment Method: ' . $paymentLabel . "\n"
        . 'Order Status: ' . $statusLabel . "\n";

    $bodyText = 'Thank you for shopping with ' . SITE_NAME . '. Your order has been confirmed'
        . " and is now being prepared.\n\n"
        . $orderMetaText . "\n"
        . "Billing\n"
        . "-------\n"
        . $order['customer_name'] . "\n"
        . $order['customer_email'] . "\n"
        . 'Phone: ' . $order['customer_phone'] . "\n\n"
        . "Shipping Address\n"
        . "----------------\n"
        . order_address_text($address) . "\n\n"
        . "Order Summary\n"
        . "-------------\n"
        . order_summary_text($order, $items);

    return [
        'subject' => 'Order Confirmed - ' . $order['order_number'] . ' | ' . SITE_NAME,
        'html'    => email_layout_html(
            $heading,
            $bodyHtml,
            '',
            '',
            'If you have any questions about this order, reply to this email or contact our support team.',
            'This is an automated message from ' . SITE_NAME . '. For help, reply to this email.'
        ),
        'text'    => email_layout_text($heading, $bodyText, 'If you have any questions about this order, reply to this email or contact our support team.'),
    ];
}


/* ------------------------------------------
   ORDER SHIPPED
   Sent on the REAL transition to order_status
   'shipped' (guarded by update_order_status() +
   the order_email_log dedup in order-emails.php).
------------------------------------------ */

function order_shipped_email(array $order, array $items, array $address, string $shipDate): array
{
    $heading = 'Your Order Has Been Shipped';

    $trackingHtml = '';

    if (!empty($order['courier_partner']) || !empty($order['awb_number']) || !empty($order['tracking_url'])) {
        $trackingHtml = '
            <h2 style="margin: 20px 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #2c2a4a;">Tracking Details</h2>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 0 8px;">
                <tr>
                    <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Courier</td>
                    <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h((string) ($order['courier_partner'] ?? 'Not provided')) . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Tracking Number</td>
                    <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h((string) ($order['awb_number'] ?? 'Not provided')) . '</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px 4px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">Shipment Date</td>
                    <td style="padding: 4px 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">' . h(date('d M Y', strtotime($shipDate))) . '</td>
                </tr>
            </table>';

        if (!empty($order['tracking_url'])) {
            $trackingHtml .= '
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 8px 0;">
                    <tr>
                        <td align="center">
                            <a href="' . h($order['tracking_url']) . '"
                               style="display: inline-block; background: #3b4a6b; color: #ffffff; text-decoration: none;
                                      font-family: Arial, Helvetica, sans-serif; font-size: 15px; font-weight: 600;
                                      line-height: 46px; padding: 0 32px; border-radius: 8px;">
                                Track Your Package
                            </a>
                        </td>
                    </tr>
                </table>';
        }
    } else {
        $trackingHtml = '
            <p style="margin: 12px 0 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
                Tracking details will be shared with you shortly.
            </p>';
    }

    $bodyHtml = '
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            Good news, ' . h($order['customer_name']) . ' - your order
            <strong>' . h($order['order_number']) . '</strong> has been shipped
            on ' . h(date('d M Y', strtotime($shipDate))) . ' and is on its way to you.
        </p>'
        . $trackingHtml . '
        <h2 style="margin: 20px 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #2c2a4a;">Shipping Address</h2>
        <p style="margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
            ' . order_address_html($address) . '
        </p>'
        . order_summary_html($order, $items);

    $trackingText = '';

    if (!empty($order['courier_partner']) || !empty($order['awb_number']) || !empty($order['tracking_url'])) {
        $trackingText = "\nTracking Details\n"
            . "----------------\n"
            . 'Courier: ' . ($order['courier_partner'] ?? 'Not provided') . "\n"
            . 'Tracking Number: ' . ($order['awb_number'] ?? 'Not provided') . "\n"
            . 'Shipment Date: ' . date('d M Y', strtotime($shipDate)) . "\n";

        if (!empty($order['tracking_url'])) {
            $trackingText .= 'Tracking URL: ' . $order['tracking_url'] . "\n";
        }
    } else {
        $trackingText = "\nTracking details will be shared with you shortly.\n";
    }

    $bodyText = 'Good news, ' . $order['customer_name'] . ' - your order '
        . $order['order_number'] . ' has been shipped on '
        . date('d M Y', strtotime($shipDate)) . " and is on its way to you.\n"
        . $trackingText . "\n"
        . "Shipping Address\n"
        . "----------------\n"
        . order_address_text($address) . "\n\n"
        . "Order Summary\n"
        . "-------------\n"
        . order_summary_text($order, $items);

    return [
        'subject' => 'Your Order Has Been Shipped - ' . $order['order_number'] . ' | ' . SITE_NAME,
        'html'    => email_layout_html(
            $heading,
            $bodyHtml,
            '',
            '',
            'If you have any questions about this delivery, reply to this email or contact our support team.',
            'This is an automated message from ' . SITE_NAME . '. For help, reply to this email.'
        ),
        'text'    => email_layout_text($heading, $bodyText, 'If you have any questions about this delivery, reply to this email or contact our support team.'),
    ];
}


/* ------------------------------------------
   ORDER DELIVERED
   Sent on the REAL transition to order_status
   'delivered'. Includes the delivery date, a
   thank-you message, support contact info and a
   store link.
------------------------------------------ */

function order_delivered_email(array $order, array $address, string $deliveryDate): array
{
    $heading       = 'Your Order Has Been Delivered';
    $supportEmail  = MAIL_REPLY_TO_ADDRESS !== '' ? MAIL_REPLY_TO_ADDRESS : 'support@moonauracrystals.in';
    $storeUrl      = site_url();

    $bodyHtml = '
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            Your order <strong>' . h($order['order_number']) . '</strong> was delivered on
            <strong>' . h(date('d M Y', strtotime($deliveryDate))) . '</strong>. We hope you love it!
        </p>
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.7; color: #3a3742;">
            Thank you for choosing ' . h(SITE_NAME) . '. We would be delighted to see you again.
        </p>
        <h2 style="margin: 20px 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #2c2a4a;">Need Help?</h2>
        <p style="margin: 0 0 16px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
            Our support team is here for you - email us at
            <a href="mailto:' . h($supportEmail) . '" style="color: #3b4a6b;">' . h($supportEmail) . '</a>
            and mention your order number ' . h($order['order_number']) . '.
        </p>
        <p style="margin: 0 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #3a3742;">
            Delivered to:
        </p>
        <p style="margin: 0 0 20px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #3a3742;">
            ' . order_address_html($address) . '
        </p>';

    $bodyText = 'Your order ' . $order['order_number'] . ' was delivered on '
        . date('d M Y', strtotime($deliveryDate)) . ". We hope you love it!\n\n"
        . "Thank you for choosing " . SITE_NAME . ". We would be delighted to see you again.\n\n"
        . "Delivered to:\n"
        . order_address_text($address) . "\n\n"
        . "Need Help?\n"
        . "----------\n"
        . "Our support team is here for you - email us at " . $supportEmail
        . " and mention your order number " . $order['order_number'] . ".\n"
        . 'Visit our store: ' . $storeUrl;

    return [
        'subject' => 'Your Order Has Been Delivered - ' . $order['order_number'] . ' | ' . SITE_NAME,
        'html'    => email_layout_html(
            $heading,
            $bodyHtml,
            'Continue Shopping',
            $storeUrl,
            'If you have any questions about this order, reply to this email or contact our support team.',
            'This is an automated message from ' . SITE_NAME . '. For help, reply to this email.'
        ),
        'text'    => email_layout_text($heading, $bodyText, 'If you have any questions about this order, reply to this email or contact our support team.'),
    ];
}
