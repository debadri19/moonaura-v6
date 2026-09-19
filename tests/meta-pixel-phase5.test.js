/* ===================================================================
   Meta Pixel Phase 5 - Advanced Matching tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/meta-pixel-phase5.test.js
================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const HELPER_SRC = fs.readFileSync(path.join(ROOT, 'assets/js/meta-pixel.js'), 'utf8');

let passed = 0;
const failures = [];

function test(name, fn) {
    try {
        fn();
        passed++;
        console.log('  ok  - ' + name);
    } catch (err) {
        failures.push({ name, err });
        console.log('  FAIL- ' + name + '\n        ' + (err && err.message));
    }
}

function read(rel) {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

function count(haystack, needle) {
    return haystack.split(needle).length - 1;
}

function git(args) {
    return execFileSync('git', args, { cwd: ROOT }).toString();
}

function loadHelper(opts) {
    opts = opts || {};
    const calls = [];
    const sandbox = { window: null };
    sandbox.window = sandbox;

    if (opts.currency) {
        sandbox.moonauraMetaCurrency = opts.currency;
    }
    if (opts.noFbq !== true) {
        sandbox.fbq = function () {
            calls.push(Array.prototype.slice.call(arguments));
        };
    }
    sandbox.localStorage = {
        getItem: function () { return null; },
        setItem: function () {},
        removeItem: function () {}
    };
    if (opts.queue) {
        sandbox.moonauraMetaQueue = opts.queue;
    }

    vm.createContext(sandbox);
    vm.runInContext(HELPER_SRC, sandbox);

    return { sandbox, calls, meta: sandbox.moonauraMeta };
}

function eventOf(call) {
    return call[1];
}

function payloadOf(call) {
    return call[2];
}

const PRODUCT = { item_id: 'SKU-100', item_name: 'Tiger Eye Bracelet', price: 499.5, quantity: 1 };
const PRODUCT2 = { item_id: 'SKU-200', item_name: 'Amethyst Ring', price: 250, quantity: 2 };

console.log('\nPart A - existing browser events remain unchanged');

test('ViewContent / AddToCart / Purchase payloads still omit matching fields', () => {
    const { meta, calls } = loadHelper({
        queue: [{
            event: 'ViewContent',
            type: 'product',
            items: [PRODUCT],
            contents: false,
            once: true
        }]
    });
    meta.product('AddToCart', [PRODUCT2]);
    meta.purchase([PRODUCT], 499.5, 'ORDER-1', 'ma_shared_event_id');

    assert.strictEqual(calls.length, 3);
    assert.strictEqual(eventOf(calls[0]), 'ViewContent');
    assert.strictEqual(eventOf(calls[1]), 'AddToCart');
    assert.strictEqual(eventOf(calls[2]), 'Purchase');
    assert.deepEqual(calls[2][3], { eventID: 'ma_shared_event_id' });

    const forbidden = ['em', 'ph', 'fn', 'ln', 'ct', 'st', 'zp', 'country', 'email', 'phone'];
    calls.forEach((call) => {
        const params = payloadOf(call);
        forbidden.forEach((key) => {
            assert.ok(!(key in params), 'event payload must not contain ' + key);
        });
    });
});

test('Purchase event_id forwarding is unchanged', () => {
    const { calls } = loadHelper({
        queue: [{
            event: 'Purchase',
            type: 'product',
            items: [PRODUCT],
            value: 499.5,
            name: false,
            once: true,
            dedupe: 'ORDER-9',
            event_id: 'ma_abc123'
        }]
    });
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'Purchase');
    assert.ok(!('event_id' in payloadOf(calls[0])));
    assert.deepEqual(calls[0][3], { eventID: 'ma_abc123' });
});

console.log('\nPart B - static Advanced Matching integration');

const metaPhp = read('includes/meta-pixel-functions.php');
const capiPhp = read('includes/meta-capi-functions.php');
const orderSuccessPhp = read('order-success.php');
const checkoutPhp = read('checkout.php');
const footerPhp = read('includes/footer.php');

test('Manual hashed Advanced Matching is added at the existing Pixel init', () => {
    assert.strictEqual(count(metaPhp, "fbq('init'"), 1);
    assert.strictEqual(count(metaPhp, "fbq('track', 'PageView')"), 1);
    assert.ok(metaPhp.includes('meta_pixel_build_advanced_matching'));
    assert.ok(metaPhp.includes('meta_pixel_set_matching_context'));
    assert.ok(metaPhp.includes('hash(\'sha256\''));
    assert.ok(metaPhp.includes('$initArgs'));
});

test('Empty matching values are omitted and raw PII is hashed before print', () => {
    assert.ok(metaPhp.includes("if ($normalized === '')"));
    assert.ok(metaPhp.includes('meta_pixel_hash_pii'));
    assert.ok(metaPhp.includes('meta_pixel_normalize_email'));
    assert.ok(metaPhp.includes('meta_pixel_normalize_phone'));
});

test('Checkout and order-success supply already-collected identifiers only', () => {
    assert.ok(checkoutPhp.includes('meta_pixel_set_matching_context($checkoutMatching)'));
    assert.ok(orderSuccessPhp.includes('meta_pixel_set_matching_context($metaMatchingContext)'));
    assert.ok(orderSuccessPhp.includes("$order['customer_email']"));
    assert.ok(orderSuccessPhp.includes("$order['customer_phone']"));
    assert.ok(!/console\.log/.test(metaPhp));
    assert.ok(!/console\.log/.test(orderSuccessPhp));
});

test('CAPI Purchase reuses hashed matching without changing event_id wiring', () => {
    assert.ok(orderSuccessPhp.includes('meta_capi_purchase_event_id($order)'));
    assert.strictEqual(count(orderSuccessPhp, 'meta_capi_purchase_event_id('), 1);
    assert.ok(orderSuccessPhp.includes('meta_capi_send_purchase($order, $purchaseItems, $metaPurchaseEventId, \'\', $metaCapiUserData)'));
    assert.ok(capiPhp.includes("meta_capi_assign_hashed_list($userData, 'fn'"));
    const requestFn = capiPhp.slice(capiPhp.indexOf('function meta_capi_request_user_data('));
    const body = requestFn.slice(0, requestFn.indexOf('\n}\n'));
    assert.ok(!/customer_email|customer_phone/.test(body), 'default CAPI request context still has no order PII');
});

test('Pixel still initializes once from the shared footer', () => {
    assert.strictEqual(count(footerPhp, 'meta_pixel_print_base_tag()'), 1);
    assert.strictEqual(count(footerPhp, 'meta_pixel_print_events()'), 1);
    assert.ok(footerPhp.indexOf('meta_pixel_print_base_tag()') < footerPhp.indexOf('meta_pixel_print_events()'));
});

test('Unrelated checkout/payment/GA4 files stay out of scope except matching hook', () => {
    const changed = git(['diff', '--name-only']).trim().split('\n').filter(Boolean);
    ['includes/analytics-functions.php', 'assets/js/ga4.js', 'includes/order-functions.php',
        'includes/payment-functions.php', 'payment.php', 'cart.php'].forEach((file) => {
        assert.ok(!changed.includes(file), file + ' must remain unchanged');
    });
    assert.ok(!HELPER_SRC.includes('em:'), 'JS helper does not build matching objects');
});

test('git diff --check is clean', () => {
    const out = git(['diff', '--check']).trim();
    assert.strictEqual(out, '', 'git diff --check must report no whitespace errors');
});

console.log('\nPart C - PHP Advanced Matching harness + prior suites');

['guest', 'matching', 'empty', 'unconfigured', 'excluded'].forEach((scenario) => {
    test('php Phase 5 harness scenario: ' + scenario, () => {
        const out = execFileSync(
            'php',
            [path.join('tests', 'meta-pixel-phase5.php'), scenario],
            { cwd: ROOT }
        ).toString();
        assert.ok(out.includes('OK ' + scenario), 'scenario output: ' + out);
    });
});

['meta-pixel-phase2.test.js', 'meta-pixel-phase3.test.js', 'meta-capi-phase4.test.js'].forEach((suite) => {
    test('existing suite still passes: ' + suite, () => {
        const out = execFileSync('node', [path.join('tests', suite)], { cwd: ROOT }).toString();
        assert.ok(out.includes('Failed: 0'), suite + ' must report zero failures');
    });
});

console.log('\n----------------------------------------');
console.log('Passed: ' + passed + '   Failed: ' + failures.length);
if (failures.length) {
    failures.forEach((f) => console.log('FAILED: ' + f.name + ' -> ' + (f.err && f.err.stack)));
    process.exit(1);
}
console.log('All Meta Pixel Phase 5 checks passed.');
