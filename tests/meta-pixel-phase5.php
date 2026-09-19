<?php
/* ===================================================================
   Meta Pixel Phase 5 - PHP-side Advanced Matching harness
   -------------------------------------------------------------------
   Exercises hashed manual Advanced Matching at Pixel init.

     php tests/meta-pixel-phase5.php guest
     php tests/meta-pixel-phase5.php matching
     php tests/meta-pixel-phase5.php empty
     php tests/meta-pixel-phase5.php unconfigured
     php tests/meta-pixel-phase5.php excluded
================================================================== */

$scenario = $argv[1] ?? 'guest';

$known = ['guest', 'matching', 'empty', 'unconfigured', 'excluded'];

if (!in_array($scenario, $known, true)) {
    fwrite(STDERR, "unknown scenario: $scenario\n");
    exit(2);
}

if ($scenario !== 'unconfigured') {
    define('META_PIXEL_ID', '123456789012345');
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = $scenario === 'excluded' ? '/account/dashboard.php' : '/checkout.php';

require_once __DIR__ . '/../includes/meta-pixel-functions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

if ($scenario === 'matching') {
    meta_pixel_set_matching_context([
        'email'       => '  Test@Example.com ',
        'phone'       => '9876543210',
        'name'        => 'Aanya Sharma',
        'city'        => 'Mumbai',
        'state'       => 'Maharashtra',
        'postal_code' => '400001',
        'country'     => 'India',
    ]);
} elseif ($scenario === 'empty') {
    meta_pixel_set_matching_context([
        'email' => '   ',
        'phone' => '',
        'name'  => '',
        'city'  => null,
    ]);
}

ob_start();
meta_pixel_print_base_tag();
$base = ob_get_clean();

if ($scenario === 'guest') {
    check(str_contains($base, "fbq('init', \"123456789012345\");"), 'guest init has pixel id only');
    check(!str_contains($base, '"em"'), 'guest init has no email matching');
    check(!str_contains($base, '"ph"'), 'guest init has no phone matching');
    check(substr_count($base, "fbq('init'") === 1, 'init exactly once');
    check(substr_count($base, "fbq('track', 'PageView')") === 1, 'PageView exactly once');
    echo "OK guest\n";
    exit(0);
}

if ($scenario === 'matching') {
    $expected = meta_pixel_build_advanced_matching([
        'email'       => '  Test@Example.com ',
        'phone'       => '9876543210',
        'name'        => 'Aanya Sharma',
        'city'        => 'Mumbai',
        'state'       => 'Maharashtra',
        'postal_code' => '400001',
        'country'     => 'India',
    ]);

    check(($expected['em'] ?? '') === hash('sha256', 'test@example.com'), 'email hash');
    check(($expected['ph'] ?? '') === hash('sha256', '919876543210'), 'phone hash includes country code');
    check(($expected['fn'] ?? '') === hash('sha256', 'aanya'), 'first name hash');
    check(($expected['ln'] ?? '') === hash('sha256', 'sharma'), 'last name hash');
    check(($expected['ct'] ?? '') === hash('sha256', 'mumbai'), 'city hash');
    check(($expected['st'] ?? '') === hash('sha256', 'maharashtra'), 'state hash');
    check(($expected['zp'] ?? '') === hash('sha256', '400001'), 'postal hash');
    check(($expected['country'] ?? '') === hash('sha256', 'in'), 'country hash');

    check(str_contains($base, "fbq('init', \"123456789012345\", {"), 'init receives hashed matching object');
    check(str_contains($base, '"em":"' . $expected['em'] . '"'), 'hashed email is in init');
    check(str_contains($base, '"ph":"' . $expected['ph'] . '"'), 'hashed phone is in init');
    check(str_contains($base, '"fn":"' . $expected['fn'] . '"'), 'hashed first name is in init');
    check(str_contains($base, '"ln":"' . $expected['ln'] . '"'), 'hashed last name is in init');
    check(str_contains($base, '"country":"' . $expected['country'] . '"'), 'hashed country is in init');

    check(!str_contains($base, 'Test@Example.com'), 'raw email is absent');
    check(!str_contains($base, 'test@example.com'), 'normalized raw email is absent');
    check(!str_contains($base, '9876543210'), 'raw phone is absent');
    check(!str_contains($base, 'Aanya'), 'raw first name is absent');
    check(!str_contains($base, 'Sharma'), 'raw last name is absent');
    check(!str_contains($base, 'Mumbai'), 'raw city is absent');
    check(!str_contains($base, 'Maharashtra'), 'raw state is absent');
    check(!str_contains($base, '400001'), 'raw postal is absent');

    $noscript = substr($base, (int) strpos($base, '<noscript'));
    check(!str_contains($noscript, $expected['em']), 'hashed identifiers are not in the noscript URL');
    check(str_contains($noscript, 'ev=PageView'), 'noscript remains PageView-only');

    echo "OK matching\n";
    exit(0);
}

if ($scenario === 'empty') {
    check(str_contains($base, "fbq('init', \"123456789012345\");"), 'blank context does not add matching object');
    check(!str_contains($base, '"em"'), 'blank email is omitted');
    echo "OK empty\n";
    exit(0);
}

if ($scenario === 'unconfigured') {
    check($base === '', 'unconfigured pixel emits nothing');
    echo "OK unconfigured\n";
    exit(0);
}

if ($scenario === 'excluded') {
    check($base === '', 'excluded page emits no pixel');
    echo "OK excluded\n";
    exit(0);
}

fwrite(STDERR, "unhandled scenario\n");
exit(2);
