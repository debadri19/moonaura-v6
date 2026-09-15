<?php
/* ===================================================================
   Meta Pixel Phase 3 - PHP-side harness
   -------------------------------------------------------------------
   Exercises the server-side Purchase queue wrapper and its guards.
   Run one scenario per process (constants are fixed once defined):

     php tests/meta-pixel-phase3.php configured
     php tests/meta-pixel-phase3.php unconfigured
     php tests/meta-pixel-phase3.php excluded
================================================================== */

$scenario = $argv[1] ?? 'configured';

$knownIds = [
    'configured'   => '123456789012345',
    'excluded'     => '123456789012345',
    'unconfigured' => '',
];

if (!array_key_exists($scenario, $knownIds)) {
    fwrite(STDERR, "unknown scenario: $scenario\n");
    exit(2);
}

if ($knownIds[$scenario] !== '') {
    define('META_PIXEL_ID', $knownIds[$scenario]);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = $scenario === 'excluded' ? '/account/orders.php' : '/order-success.php';

require_once __DIR__ . '/../includes/meta-pixel-functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

// Authoritative, GA4-shaped order items (SKU id strategy + quantities).
$orderItems = [
    ['item_id' => 'SKU-1', 'item_name' => 'Tiger Eye Bracelet', 'price' => 499.5, 'quantity' => 2],
    ['item_id' => 'SKU-2', 'item_name' => 'Amethyst', 'price' => 100, 'quantity' => 1],
];

if ($scenario === 'configured') {
    // Authoritative final order amount is 1234.5 while the item sum is
    // only 999.0 + 100 = 1099.0, proving the passed value wins.
    meta_pixel_track_purchase($orderItems, 1234.5, 'MA-2026-0001');

    // Every one of these is missing/invalid order data and must be
    // silently ignored (their dedupe tokens must never appear).
    meta_pixel_track_purchase([], 500, 'MA-EMPTY');
    meta_pixel_track_purchase($orderItems, null, 'MA-NULL');
    meta_pixel_track_purchase($orderItems, 'not-a-number', 'MA-BAD');
    meta_pixel_track_purchase($orderItems, -1, 'MA-NEG');
}

if ($scenario === 'excluded') {
    meta_pixel_track_purchase($orderItems, 1234.5, 'MA-EXCLUDED');
}

ob_start();
meta_pixel_print_base_tag();
$base = ob_get_clean();

ob_start();
meta_pixel_print_events();
$events = ob_get_clean();

if ($scenario === 'configured') {
    check(meta_pixel_is_configured(), 'valid numeric id is configured');

    // Phase 1 must remain exactly as it was.
    check(str_contains($base, 'fbevents.js'), 'phase 1 loader present');
    check(str_contains($base, "fbq('init'"), 'phase 1 init present');
    check(str_contains($base, "fbq('track', 'PageView')"), 'phase 1 PageView present');

    check(str_contains($events, 'assets/js/meta-pixel.js'), 'phase 3 reuses the phase 2 helper script');

    check(substr_count($events, '"event":"Purchase"') === 1, 'Purchase queued exactly once');
    check(str_contains($events, '"value":1234.5'), 'authoritative final order value used, not the item sum');
    check(str_contains($events, '"type":"product"'), 'content_type product descriptor present');
    check(str_contains($events, '"SKU-1"'), 'canonical SKU identifier present');
    check(str_contains($events, '"dedupe":"MA-2026-0001"'), 'internal dedupe token attached for the browser guard');

    check(!str_contains($events, 'MA-EMPTY'), 'empty item list is ignored');
    check(!str_contains($events, 'MA-NULL'), 'missing value is ignored');
    check(!str_contains($events, 'MA-BAD'), 'non-numeric value is ignored');
    check(!str_contains($events, 'MA-NEG'), 'negative value is ignored');

    // No PII and no order id in the emitted record.
    foreach (['customer_name', 'customer_email', 'customer_phone', 'postal_code', 'email', 'phone', 'address', 'order_id', 'transaction_id'] as $needle) {
        check(!str_contains(strtolower($events), $needle), 'no PII / order id field: ' . $needle);
    }

    // Print guard: a second call emits nothing.
    ob_start();
    meta_pixel_print_events();
    check(ob_get_clean() === '', 'print_events is once-only');

    echo "OK configured\n";
    exit(0);
}

if ($scenario === 'unconfigured') {
    check(!meta_pixel_is_configured(), 'pixel is not configured');
    check($base === '', 'base tag emits nothing when unconfigured');
    check($events === '', 'events emit nothing when unconfigured');

    echo "OK unconfigured\n";
    exit(0);
}

if ($scenario === 'excluded') {
    check(meta_pixel_is_configured(), 'pixel id is valid');
    check($base === '', 'excluded page emits no base tag');
    check($events === '', 'excluded page emits no Purchase');

    echo "OK excluded\n";
    exit(0);
}

fwrite(STDERR, "unhandled scenario\n");
exit(2);
