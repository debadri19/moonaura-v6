<?php
/* ===================================================================
   TAX FUNCTIONS (Phase 5F - GST & Tax Architecture)
   -------------------------------------------------------------------
   The single server-side authority for GST on this project.

   PRICING MODEL - GST-INCLUSIVE (critical business rule):
   Product prices are GST-INCLUSIVE. A ₹100 product with a 3% GST rate
   means the customer pays ₹100; the ₹3 tax is DERIVED out of that ₹100
   and is never added on top. Therefore:

       grand_total = subtotal - discount + shipping_charge

   where subtotal is the sum of GST-inclusive line totals. gst_amount /
   cgst / sgst / igst are informational derivations of how much of the
   inclusive price is tax - NOT an extra charge.

   TAX TYPE (intra vs inter):
     - intra-state sale  -> GST split into CGST + SGST (half each)
     - inter-state sale  -> GST becomes IGST entirely
   Determined by comparing the order's shipping state
   (order_addresses.state) with the seller's configured state
   (settings.business_state). When business_state is not configured the
   tax type CANNOT be reliably determined - the caller stores
   tax_type = NULL and this library splits conservatively as CGST+SGST
   so the money always reconciles (gst = cgst + sgst + igst). That
   default is documented here, not silently hidden.

   ROUNDING:
   Every money value is rounded to 2 decimals with round() (half-up).
   The CGST/SGST split rounds the first half and derives the second as
   the remainder, so cgst + sgst always equals gst exactly (no
   off-by-one-paise drift).

   STATEFULNESS / ANTI-TAMPER:
   These functions only ever READ the rate and compute amounts - every
   caller is responsible for loading sell_price + gst_rate from the
   products table server-side. No client-supplied rate, taxable value,
   tax amount or grand total is ever trusted anywhere in the project.
   =================================================================== */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings-functions.php';
require_once __DIR__ . '/cart-functions.php';


/* ==========================================
   MAXIMUM ALLOWED GST RATE (%)
   GST slabs in India today top out at 28%,
   but the validation allows anything in
   [0, 100] so the store is never blocked by
   a future slab. Negative/malformed rates
   are always rejected.
========================================== */

const GST_MAX_RATE = 100.0;


/* ==========================================
   VALIDATE / NORMALIZE A GST RATE
   -------------------------------------------------
   Returns a float (0..100, 2 decimals) or null
   for anything invalid: non-numeric, negative,
   over 100, or more than 2 decimal places.
   Used by BOTH the admin product form and the
   calculation layer, so a bad rate can never
   reach the database or an order.
========================================== */

function normalize_gst_rate(mixed $rate): ?float
{
    if (is_string($rate)) {
        $rate = trim($rate);
    }

    if (!is_numeric($rate)) {
        return null;
    }

    $value = (float) $rate;

    if ($value < 0 || $value > GST_MAX_RATE) {
        return null;
    }

    // Reject more than 2 decimal places (e.g. 5.123%) - the storage
    // column is DECIMAL(5,2) and the maths assumes 2 decimals.
    $normalized = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    $decimalPart = str_contains($normalized, '.') ? substr(strrchr($normalized, '.'), 1) : '';

    if (strlen($decimalPart) > 2) {
        return null;
    }

    return round($value, 2);
}


/* ==========================================
   NORMALIZE AN INDIAN STATE NAME FOR COMPARISON
   -------------------------------------------------
   Checkout stores the state as free text
   ("Karnataka", "KARNATAKA", "  Karnataka ").
   This lowers/collapses it so the shipping
   state can be compared with business_state
   reliably. Best-effort by design - a future
   state dropdown (billing/shipping address
   split) would remove the free-text ambiguity
   entirely; that is a documented limitation,
   not something this phase fixes.
========================================== */

function normalize_state_name(?string $state): string
{
    $state = trim((string) $state);
    $state = preg_replace('/\s+/', ' ', $state) ?? '';
    $state = strtolower($state);

    // "State of X" / "X State" are common free-text variants.
    $state = preg_replace('/^(state of)\s+/', '', $state) ?? $state;
    $state = preg_replace('/\s+(state)$/', '', $state) ?? $state;

    return $state;
}


/* ==========================================
   RESOLVE THE TAX TYPE FOR AN ORDER
   -------------------------------------------------
   Returns 'intra' | 'inter' | 'unknown'.
     - 'intra'  : shipping state matches the seller's configured state
                  (business_state setting) -> CGST + SGST.
     - 'inter'  : shipping state differs -> IGST.
     - 'unknown': business_state is empty, OR the shipping state is
                  empty. Cannot be reliably determined - the caller
                  stores NULL and uses the conservative CGST+SGST split
                  via split_gst_amount(). We never invent a state.
========================================== */

function resolve_tax_type(?string $shippingState): string
{
    $businessState = normalize_state_name(get_setting('business_state', ''));

    if ($businessState === '') {
        return 'unknown';
    }

    $shippingState = normalize_state_name($shippingState);

    if ($shippingState === '') {
        return 'unknown';
    }

    return ($shippingState === $businessState) ? 'intra' : 'inter';
}


/* ==========================================
   SPLIT A GST AMOUNT INTO CGST / SGST / IGST
   -------------------------------------------------
   $taxType - 'intra' | 'inter' | 'unknown'.
   For 'intra' (and the conservative 'unknown'
   default) the GST splits half/half; 'inter'
   goes 100% to IGST. cgst is rounded first and
   sgst derived as the remainder so the two
   always sum EXACTLY to the input GST.
========================================== */

function split_gst_amount(float $gstAmount, string $taxType): array
{
    $gst = round((float) $gstAmount, 2);

    if ($taxType === 'inter') {
        return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $gst];
    }

    $cgst = round($gst / 2, 2);
    $sgst = round($gst - $cgst, 2);

    return ['cgst' => $cgst, 'sgst' => $sgst, 'igst' => 0.0];
}


/* ==========================================
   COMPUTE THE TAX BREAKDOWN FOR ONE INCLUSIVE LINE
   -------------------------------------------------
   $inclusiveTotal - the line total the customer pays
                     (sell_price * quantity, GST included).
   $rate           - the product's GST rate (already normalized).
   $taxType        - 'intra' | 'inter' | 'unknown'.

   GST-INCLUSIVE FORMULA (the correct reverse calculation):
       taxable_value = round(inclusiveTotal / (1 + rate/100), 2)
       gst_amount    = round(inclusiveTotal - taxable_value, 2)

   e.g. ₹100 inclusive @ 3% -> taxable = 97.09, gst = 2.91
        (100 / 1.03 = 97.087... -> 97.09; 100 - 97.09 = 2.91).

   Returns the full per-line breakdown. Works on the ROUNDED line
   total so every line reconciles independently.
========================================== */

function compute_line_tax(float $inclusiveTotal, float $rate, string $taxType): array
{
    $inclusiveTotal = round((float) $inclusiveTotal, 2);
    $rate           = normalize_gst_rate($rate) ?? 0.0;

    $taxableValue = round($inclusiveTotal / (1 + ($rate / 100)), 2);
    $gstAmount    = round($inclusiveTotal - $taxableValue, 2);

    $split = split_gst_amount($gstAmount, $taxType);

    return [
        'taxable_value' => $taxableValue,
        'gst_amount'    => $gstAmount,
        'cgst_amount'   => $split['cgst'],
        'sgst_amount'   => $split['sgst'],
        'igst_amount'   => $split['igst'],
    ];
}


/* ==========================================
   AGGREGATE PER-LINE TAX INTO ORDER TOTALS
   -------------------------------------------------
   $lines - array of line tax breakdowns (from
            compute_line_tax()), each with the
            shape ['taxable_value', 'gst_amount',
                   'cgst_amount', 'sgst_amount',
                   'igst_amount'].
   Returns the summed order-level breakdown.
========================================== */

function aggregate_line_tax(array $lines): array
{
    $totals = ['taxable_value' => 0.0, 'gst_amount' => 0.0, 'cgst_amount' => 0.0, 'sgst_amount' => 0.0, 'igst_amount' => 0.0];

    foreach ($lines as $line) {
        foreach ($totals as $key => $_v) {
            $totals[$key] += (float) ($line[$key] ?? 0.0);
        }
    }

    foreach ($totals as $key => $value) {
        $totals[$key] = round($value, 2);
    }

    return $totals;
}


/* ==========================================
   SINGLE SHARED RATE FOR THE ORDER-LEVEL gst_rate
   -------------------------------------------------
   Historical orders.gst_rate is a single "order-level rate" (used by
   the Phase 5D/5F.1-era invoice as "GST (x%)"). With product-wise GST
   an order can legitimately mix rates, so this returns the common
   rate when every line shares one, otherwise 0.00 (the rate is only
   meaningful for single-rate orders; the per-line gst_rate snapshot
   in order_items is authoritative for mixed orders).
========================================== */

function order_level_gst_rate(array $lines): float
{
    $rates = array_values(array_unique(array_map(
        static fn (array $line): float => (float) ($line['gst_rate'] ?? 0.0),
        $lines
    )));

    return count($rates) === 1 ? $rates[0] : 0.0;
}


/* ==========================================
   HUMAN-FRIENDLY LABEL FOR A STORED tax_type
========================================== */

function tax_type_label(?string $taxType): string
{
    return match ($taxType) {
        'intra'  => 'Intra-state (CGST + SGST)',
        'inter'  => 'Inter-state (IGST)',
        default  => 'Not determined',
    };
}


/* ==========================================
   CART SUBTOTAL GST (INFORMATIONAL)
   -------------------------------------------------
   #21 Phase A: extracted from cart.php's own inline
   GST loop so cart.php and the new cart AJAX
   responses (cart-remove.php / cart-update.php) share
   one calculation instead of duplicating it. Same
   'unknown' tax-type fallback cart.php always used -
   the cart page/AJAX endpoints never know a shipping
   state, so this stays informational only (see the
   GST-inclusive pricing model documented above).
   checkout.php's own totals are untouched by this -
   it computes GST itself once a real shipping
   address/state is known.
========================================== */

function get_cart_gst_amount(): float
{
    $gstAmount = 0.0;

    foreach (get_cart_items_with_details() as $item) {
        if (!$item['is_available']) {
            continue;
        }
        $gstRate    = normalize_gst_rate($item['product']['gst_rate'] ?? 0.0) ?? 0.0;
        $lineTax    = compute_line_tax((float) $item['line_total'], $gstRate, 'unknown');
        $gstAmount += $lineTax['gst_amount'];
    }

    return round($gstAmount, 2);
}
