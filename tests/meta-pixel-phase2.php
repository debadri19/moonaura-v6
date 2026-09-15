<?php
/* ===================================================================
   Meta Pixel Phase 2 - PHP-side harness
   -------------------------------------------------------------------
   Exercises the server-side queue helpers and the print-time guards.
   Run one scenario per process (constants are fixed once defined):

     php tests/meta-pixel-phase2.php configured
     php tests/meta-pixel-phase2.php unconfigured
     php tests/meta-pixel-phase2.php invalid
     php tests/meta-pixel-phase2.php excluded
================================================================== */

$scenario = $argv[1] ?? 'configured';

$knownIds = [
    'configured'   => '123456789012345',
    'excluded'     => '123456789012345',
    'invalid'      => 'not-a-numeric-id',
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
$_SERVER['SCRIPT_NAME'] = $scenario === 'excluded' ? '/account/dashboard.php' : '/product.php';

require_once __DIR__ . '/../includes/meta-pixel-functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

$item = [
    'item_id'   => 'SKU-1',
    'item_name' => 'Tiger Eye Bracelet',
    'price'     => 499.5,
    'quantity'  => 1,
];
$cartItems = [
    $item,
    ['item_id' => 'SKU-2', 'item_name' => 'Amethyst', 'price' => 100, 'quantity' => 2],
];

// Queue the scenario's events before printing (print flushes the buffer).
if ($scenario === 'configured') {
    meta_pixel_track_product_view($item);
    meta_pixel_track_search('   ');          // must be ignored (empty query)
    meta_pixel_track_search('amethyst');     // must be queued once
    meta_pixel_track_cart_view($cartItems);  // ViewCart
    meta_pixel_track_checkout($cartItems, 1234.5); // InitiateCheckout
} elseif ($scenario === 'excluded') {
    meta_pixel_track_product_view($item);
}

ob_start();
meta_pixel_print_base_tag();
$base = ob_get_clean();

ob_start();
meta_pixel_print_events();
$events = ob_get_clean();

if ($scenario === 'configured') {
    check(meta_pixel_is_configured(), 'valid numeric id is configured');
    check(str_contains($base, 'fbevents.js'), 'phase 1 loader present');
    check(str_contains($base, "fbq('init'"), 'phase 1 init present');
    check(str_contains($base, "fbq('track', 'PageView')"), 'phase 1 PageView present');

    check(str_contains($events, 'assets/js/meta-pixel.js'), 'phase 2 helper script loaded');

    check(str_contains($events, '"event":"ViewContent"'), 'ViewContent queued');
    check(str_contains($events, '"type":"product"'), 'ViewContent product descriptor');
    check(str_contains($events, '"item_name":"Tiger Eye Bracelet"'), 'product name present');
    check(str_contains($events, '"price":499.5'), 'product price present');
    check(str_contains($events, '"contents":false'), 'ViewContent omits contents');
    check(str_contains($events, 'SKU-1'), 'product identifier present');
    check(substr_count($events, '"event":"ViewContent"') === 1, 'ViewContent queued exactly once');

    check(substr_count($events, '"event":"Search"') === 1, 'Search queued exactly once (empty query skipped)');
    check(str_contains($events, '"search_string":"amethyst"'), 'search_string is the submitted query');

    check(str_contains($events, '"event":"ViewCart"'), 'ViewCart queued');
    check(str_contains($events, '"event":"InitiateCheckout"'), 'InitiateCheckout queued');
    check(str_contains($events, '"value":1234.5'), 'checkout value override used');

    // No PII markers anywhere in the emitted payloads.
    foreach (['email', 'phone', 'address', 'order_id', 'external_id', 'transaction_id'] as $needle) {
        check(!str_contains(strtolower($events), $needle), 'no PII field: ' . $needle);
    }

    // Print guard: a second call emits nothing.
    ob_start();
    meta_pixel_print_events();
    check(ob_get_clean() === '', 'print_events is once-only');

    echo "OK configured\n";
    exit(0);
}

if ($scenario === 'invalid' || $scenario === 'unconfigured') {
    check(!meta_pixel_is_configured(), 'pixel is not configured');
    check($base === '', 'base tag emits nothing when unconfigured');
    check($events === '', 'events emit nothing when unconfigured');
    echo 'OK ' . $scenario . "\n";
    exit(0);
}

if ($scenario === 'excluded') {
    check(meta_pixel_is_configured(), 'pixel id is valid');
    check($base === '', 'excluded page emits no base tag');
    check($events === '', 'excluded page emits no events');
    echo "OK excluded\n";
    exit(0);
}

fwrite(STDERR, "unhandled scenario\n");
exit(2);
