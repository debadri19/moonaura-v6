<?php
/* ===================================================================
   Meta Conversions API (CAPI) Phase 4 - PHP-side harness
   -------------------------------------------------------------------
   Exercises the server-side Purchase helper end to end with a mocked
   HTTP transport. NO real Meta Graph API request is ever made.

   Run one scenario per process (constants are fixed once defined):

     php tests/meta-capi-phase4.php no-token
     php tests/meta-capi-phase4.php empty-token
     php tests/meta-capi-phase4.php configured
     php tests/meta-capi-phase4.php no-pixel
     php tests/meta-capi-phase4.php invalid-data
     php tests/meta-capi-phase4.php http-error
     php tests/meta-capi-phase4.php timeout
     php tests/meta-capi-phase4.php malformed
     php tests/meta-capi-phase4.php network
     php tests/meta-capi-phase4.php hashing

   The token literal used below is an obvious test fixture. It is not a
   real credential and is never sent anywhere (the transport is mocked).
================================================================= */

$scenario = $argv[1] ?? '';

$known = [
    'no-token', 'empty-token', 'configured', 'no-pixel', 'invalid-data',
    'http-error', 'timeout', 'malformed', 'network', 'hashing',
];

if (!in_array($scenario, $known, true)) {
    fwrite(STDERR, "unknown scenario: $scenario\n");
    exit(2);
}

// Deterministic environment regardless of the host shell.
putenv('META_PIXEL_ID');
putenv('META_CAPI_ACCESS_TOKEN');
putenv('META_CAPI_API_VERSION');

$testToken = 'TEST_CAPI_TOKEN_NOT_A_REAL_CREDENTIAL';

// The token/dataset are defined before config.php loads so its
// defined() guards keep these values (exactly how .env/config.local
// override works in production).
$usesToken = in_array($scenario, ['configured', 'invalid-data', 'http-error', 'timeout', 'malformed', 'network'], true);
$usesPixel = $scenario !== 'no-pixel';

if ($usesPixel) {
    define('META_PIXEL_ID', '123456789012345');
}
if ($usesToken) {
    define('META_CAPI_ACCESS_TOKEN', $testToken);
}
if ($scenario === 'empty-token') {
    define('META_CAPI_ACCESS_TOKEN', '');
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME']    = '/order-success.php';

require_once __DIR__ . '/../includes/meta-capi-functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

function array_has_key_recursive(array $array, array $needles): bool
{
    foreach ($array as $key => $value) {
        if (in_array((string) $key, $needles, true)) {
            return true;
        }
        if (is_array($value) && array_has_key_recursive($value, $needles)) {
            return true;
        }
    }

    return false;
}

// Authoritative, GA4-shaped order items (SKU id strategy + quantities).
$order = [
    'id'           => 77,
    'order_number' => 'MA-2026-0001',
    'grand_total'  => '1234.50',
    'currency'     => 'INR',
];
$items = [
    ['item_id' => 'SKU-1', 'item_name' => 'Tiger Eye Bracelet', 'price' => 499.5, 'quantity' => 2],
    ['item_id' => 'SKU-2', 'item_name' => 'Amethyst', 'price' => 100, 'quantity' => 1],
];

// Mocked transport - records calls and returns a canned response.
$GLOBALS['capi_calls']  = [];
$GLOBALS['capi_result'] = ['ok' => true, 'status' => 200, 'body' => '{"events_received":1}', 'error' => ''];

meta_capi_set_transport(function ($url, $payload, $body, $timeout) {
    $GLOBALS['capi_calls'][] = [
        'url'     => $url,
        'payload' => $payload,
        'body'    => $body,
        'timeout' => $timeout,
    ];

    return $GLOBALS['capi_result'];
});

$expectedEventId = 'ma_' . hash('sha256', 'moonaura:purchase:MA-2026-0001');


/* ------------------------------------------------------------------ */

if ($scenario === 'no-token' || $scenario === 'empty-token') {
    check(meta_capi_access_token() === '', 'token is empty');
    check(!meta_capi_is_configured(), 'CAPI is not configured without a token');

    $sent = meta_capi_send_purchase($order, $items, $expectedEventId);

    check($sent === false, 'send returns false without a token');
    check(count($GLOBALS['capi_calls']) === 0, 'no HTTP request is made without a token');

    echo "OK $scenario\n";
    exit(0);
}

if ($scenario === 'no-pixel') {
    check(!meta_capi_is_configured(), 'CAPI requires the Pixel dataset id too');

    $sent = meta_capi_send_purchase($order, $items, $expectedEventId);

    check($sent === false, 'send returns false without a Pixel id');
    check(count($GLOBALS['capi_calls']) === 0, 'no HTTP request is made without a Pixel id');

    echo "OK no-pixel\n";
    exit(0);
}

if ($scenario === 'invalid-data') {
    check(meta_capi_is_configured(), 'CAPI is configured');

    check(meta_capi_send_purchase($order, [], $expectedEventId) === false, 'empty item list is ignored');
    check(meta_capi_send_purchase(['grand_total' => 'not-a-number'], $items, $expectedEventId) === false, 'non-numeric total is ignored');
    check(meta_capi_send_purchase(['grand_total' => -5], $items, $expectedEventId) === false, 'negative total is ignored');
    check(meta_capi_send_purchase($order, $items, '') === false, 'missing event id is ignored');
    check(count($GLOBALS['capi_calls']) === 0, 'no request is made for invalid data');

    echo "OK invalid-data\n";
    exit(0);
}

if ($scenario === 'configured') {
    check(meta_capi_is_configured(), 'CAPI is configured');
    check(meta_capi_access_token() === $testToken, 'token is read from configuration');

    $sent = meta_capi_send_purchase($order, $items, $expectedEventId);

    check($sent === true, 'send reports success on a 2xx acknowledgement');
    check(count($GLOBALS['capi_calls']) === 1, 'exactly one HTTP request is made');

    $call    = $GLOBALS['capi_calls'][0];
    $url     = $call['url'];
    $payload = $call['payload'];
    $json    = json_encode($payload);

    // Endpoint shape; token must not appear in the URL.
    check(str_starts_with($url, 'https://graph.facebook.com/'), 'uses the HTTPS Graph API endpoint');
    check(str_contains($url, '/123456789012345/events'), 'endpoint targets the configured dataset');
    check(!str_contains($url, $testToken), 'the access token is never placed in the URL');

    // Token is transmitted only in the server-side body.
    check(($payload['access_token'] ?? '') === $testToken, 'token is included in the server-side body');

    check(isset($payload['data'][0]) && is_array($payload['data'][0]), 'single event payload present');
    $event = $payload['data'][0];

    check($event['event_name'] === 'Purchase', 'event_name is exactly Purchase');
    check($event['action_source'] === 'website', 'action_source is exactly website');
    check(isset($event['event_time']) && is_int($event['event_time']) && $event['event_time'] > 0, 'event_time is a positive integer');
    check(($event['event_id'] ?? '') === $expectedEventId, 'event_id is the shared, stable id');

    $custom = $event['custom_data'] ?? [];
    check(($custom['currency'] ?? '') === 'INR', 'currency is exactly INR');
    check(($custom['value'] ?? null) === 1234.5, 'value comes from the authoritative grand_total');
    check(($custom['content_type'] ?? '') === 'product', 'content_type is product');
    check(($custom['content_ids'] ?? []) === ['SKU-1', 'SKU-2'], 'content_ids use canonical identifiers');
    check(($custom['contents'] ?? []) === [
        ['id' => 'SKU-1', 'quantity' => 2],
        ['id' => 'SKU-2', 'quantity' => 1],
    ], 'contents carry the authoritative quantities');

    // event_source_url is present and carries no order number.
    check(isset($event['event_source_url']) && str_contains($event['event_source_url'], 'order-success.php'), 'event_source_url is present');
    check(!str_contains($event['event_source_url'], 'MA-2026-0001'), 'event_source_url omits the order number');

    // No order identifier / PII keys anywhere in the event.
    foreach (['order_number', 'order_id', 'transaction_id', 'customer_email', 'customer_phone', 'customer_name', 'full_name', 'address', 'postal_code'] as $needle) {
        check(!array_has_key_recursive($event, [$needle]), 'event has no key: ' . $needle);
    }

    // The raw order number must never be forwarded as a Meta value.
    check(!str_contains((string) $json, 'MA-2026-0001'), 'raw order number is absent from the payload');

    // Default user_data carries no hashed customer PII.
    $userData = $event['user_data'] ?? [];
    foreach (['em', 'ph', 'fn', 'ln', 'external_id', 'fbc', 'fbp', 'address'] as $needle) {
        check(!array_has_key_recursive($userData, [$needle]), 'user_data has no PII key: ' . $needle);
    }

    echo "OK configured\n";
    exit(0);
}

if (in_array($scenario, ['http-error', 'timeout', 'malformed', 'network'], true)) {
    check(meta_capi_is_configured(), 'CAPI is configured');

    $logFile = sys_get_temp_dir() . '/moonaura-capi-phase4-' . $scenario . '-' . getmypid() . '.log';
    ini_set('error_log', $logFile);

    if ($scenario === 'http-error') {
        $GLOBALS['capi_result'] = ['ok' => false, 'status' => 500, 'body' => '', 'error' => ''];
    } elseif ($scenario === 'timeout') {
        $GLOBALS['capi_result'] = ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'timeout'];
    } elseif ($scenario === 'malformed') {
        $GLOBALS['capi_result'] = ['ok' => true, 'status' => 200, 'body' => '<html>not json</html>', 'error' => ''];
    } else {
        $GLOBALS['capi_result'] = ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'network_error'];
    }

    $sent = meta_capi_send_purchase($order, $items, $expectedEventId);

    check($sent === false, 'failure is reported as false');
    check(count($GLOBALS['capi_calls']) === 1, 'exactly one request was attempted');
    check(is_file($logFile), 'a safe diagnostic was logged');

    $log = (string) file_get_contents($logFile);
    check(str_contains($log, 'moonaura-meta-capi'), 'log contains only the safe reason line');
    check(!str_contains($log, $testToken), 'log never contains the access token');
    check(!str_contains($log, 'MA-2026-0001'), 'log never contains the order number');
    check(!str_contains($log, 'Tiger Eye'), 'log never contains customer/product PII');

    echo "OK $scenario\n";
    exit(0);
}

if ($scenario === 'hashing') {
    // Meta's server-side format: email lowercased/trimmed, phone digits
    // only, then SHA-256. Raw values must never survive.
    $userData = meta_capi_build_user_data([
        'email' => '  Test@Example.com ',
        'phone' => '+91 98765-43210',
    ]);

    check(($userData['em'] ?? []) === [hash('sha256', 'test@example.com')], 'email is normalized and hashed');
    check(($userData['ph'] ?? []) === [hash('sha256', '919876543210')], 'phone is normalized and hashed');

    $json = json_encode($userData);
    check(!str_contains($json, 'Test@Example.com'), 'raw email is never emitted');
    check(!str_contains($json, '98765'), 'raw phone is never emitted');

    // The default request context sends no customer PII keys.
    $default = meta_capi_request_user_data();
    foreach (['em', 'ph', 'fn', 'ln', 'external_id'] as $needle) {
        check(!array_key_exists($needle, $default), 'default request user_data has no ' . $needle);
    }

    // Stable per order, different across orders, and never the raw number.
    $sameOrder = meta_capi_purchase_event_id($order);
    check($sameOrder === $expectedEventId, 'event id is deterministic for the same order');
    check(meta_capi_purchase_event_id(['order_number' => 'MA-2026-0002']) !== $expectedEventId, 'event id differs across orders');
    check(!str_contains($sameOrder, 'MA-2026-0001'), 'event id does not contain the order number');
    check(meta_capi_purchase_event_id([]) === '', 'event id is empty when the order identity is missing');

    echo "OK hashing\n";
    exit(0);
}

fwrite(STDERR, "unhandled scenario\n");
exit(2);
