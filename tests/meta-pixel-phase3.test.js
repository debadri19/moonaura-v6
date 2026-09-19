/* ===================================================================
   Meta Pixel Phase 3 - targeted tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/meta-pixel-phase3.test.js

   Part A executes assets/js/meta-pixel.js in a sandbox with a fake
   window/fbq/localStorage and asserts the Purchase payload, the
   once-per-order refresh/revisit dedupe, the different-order/
   no-fbq/blocked-storage guards and PII/order-id absence.

   Part B statically checks that Purchase is wired only from the
   confirmed order-success page, that the final order value and
   canonical identifiers are sourced correctly, and that Phase 1,
   Phase 2 and GA4 remain intact.

   Part C runs the PHP Purchase queue harness (tests/meta-pixel-phase3.php).
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

/* -------------------------------------------------------------------
   Sandbox loader (fake fbq + localStorage)
------------------------------------------------------------------- */

function makeStorage(initial) {
    const data = initial || {};
    return {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
        },
        setItem: function (key, value) {
            data[key] = String(value);
        },
        removeItem: function (key) {
            delete data[key];
        },
        _data: data
    };
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
    if (opts.storage !== null) {
        sandbox.localStorage = opts.storage || makeStorage();
    }
    if (opts.queue) {
        sandbox.moonauraMetaQueue = opts.queue;
    }

    vm.createContext(sandbox);
    vm.runInContext(HELPER_SRC, sandbox);

    return { sandbox, calls, meta: sandbox.moonauraMeta, storage: sandbox.localStorage };
}

function eventOf(call) {
    return call[1];
}
function payloadOf(call) {
    return call[2];
}

const PRODUCT = { item_id: 'SKU-100', item_name: 'Tiger Eye Bracelet', price: 499.5, quantity: 1 };
const PRODUCT2 = { item_id: 'SKU-200', item_name: 'Amethyst Ring', price: 250, quantity: 2 };

function purchaseRecord(dedupe, items, value) {
    return {
        event: 'Purchase',
        type: 'product',
        items: items,
        value: value,
        name: false,
        once: true,
        dedupe: dedupe
    };
}

console.log('\nPart A - Purchase event behaviour');

/* 1. Successful completed order -> exactly one Purchase event. */
test('Purchase fires once with the authoritative payload', () => {
    const record = purchaseRecord('ORDER-1', [PRODUCT, PRODUCT2], 1234.5);
    const { calls } = loadHelper({ queue: [record] });
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'Purchase');
    const p = payloadOf(calls[0]);
    assert.deepEqual(p.content_ids, ['SKU-100', 'SKU-200']);
    assert.strictEqual(p.content_type, 'product');
    assert.strictEqual(p.value, 1234.5);
    assert.strictEqual(p.currency, 'INR');
    assert.deepEqual(p.contents, [
        { id: 'SKU-100', quantity: 1 },
        { id: 'SKU-200', quantity: 2 }
    ]);
    assert.ok(!('content_name' in p), 'order-level Purchase must not force a content_name');
});

/* 9. Final order value is used, not the cart subtotal. */
test('Purchase value is the passed final amount, never the item subtotal', () => {
    // items sum to 999.5, but the completed order recorded 1234.5
    const record = purchaseRecord('ORDER-2', [PRODUCT, PRODUCT2], 1234.5);
    const { calls } = loadHelper({ queue: [record] });
    assert.strictEqual(payloadOf(calls[0]).value, 1234.5);
    assert.notStrictEqual(payloadOf(calls[0]).value, 999.5);
});

/* 2. Refreshing the same order page -> no duplicate. */
test('Refreshing the same order page does not fire again', () => {
    const storage = makeStorage();
    const record = purchaseRecord('ORDER-REFRESH', [PRODUCT], 499.5);
    const first = loadHelper({ queue: [record], storage });
    assert.strictEqual(first.calls.length, 1);

    // A refresh is a brand new JS context but the same localStorage.
    const second = loadHelper({ queue: [record], storage });
    assert.strictEqual(second.calls.length, 0);
});

/* 3. Revisiting the same success page -> no duplicate. */
test('Revisiting the same order page later does not fire again', () => {
    const storage = makeStorage();
    const record = purchaseRecord('ORDER-REVISIT', [PRODUCT, PRODUCT2], 808);
    loadHelper({ queue: [record], storage });
    loadHelper({ queue: [record], storage });
    const third = loadHelper({ queue: [record], storage });
    assert.strictEqual(third.calls.length, 0);
});

/* 5. Duplicate records queued in one load still fire once. */
test('Duplicate Purchase records in one page load fire once', () => {
    const record = purchaseRecord('ORDER-DUP', [PRODUCT], 499.5);
    const { calls } = loadHelper({ queue: [record, record] });
    assert.strictEqual(calls.length, 1);
});

/* 4. A genuinely different completed order is allowed. */
test('A different completed order still fires', () => {
    const storage = makeStorage();
    loadHelper({ queue: [purchaseRecord('ORDER-A', [PRODUCT], 499.5)], storage });
    const second = loadHelper({ queue: [purchaseRecord('ORDER-B', [PRODUCT], 499.5)], storage });
    assert.strictEqual(second.calls.length, 1);
    assert.strictEqual(eventOf(second.calls[0]), 'Purchase');
});

test('A different order is allowed even within the same page load', () => {
    const { meta, calls } = loadHelper();
    assert.strictEqual(meta.purchase([PRODUCT], 499.5, 'ORDER-C'), true);
    assert.strictEqual(meta.purchase([PRODUCT], 499.5, 'ORDER-C'), false, 'same order twice must be blocked');
    assert.strictEqual(meta.purchase([PRODUCT], 499.5, 'ORDER-D'), true, 'new order must be allowed');
    assert.strictEqual(calls.length, 2);
});

/* 7. Missing / invalid order data -> no Purchase. */
test('Missing or invalid order data never fires', () => {
    const { meta, calls } = loadHelper();
    assert.strictEqual(meta.purchase([], 100, 'ORDER-NOITEMS'), false);
    assert.strictEqual(meta.purchase([{ item_name: 'no id' }], 100, 'ORDER-NOID'), false);
    assert.strictEqual(meta.track('Purchase', { type: 'product', items: [], value: 10, dedupe: 'X' }), false);
    assert.strictEqual(calls.length, 0);
});

/* 8. Missing fbq / unconfigured Pixel -> no event, site continues. */
test('Without fbq nothing fires, nothing throws, and the order is not marked', () => {
    const storage = makeStorage();
    const record = purchaseRecord('ORDER-NOFBQ', [PRODUCT], 499.5);
    const first = loadHelper({ noFbq: true, queue: [record], storage });
    assert.strictEqual(first.calls.length, 0);
    assert.doesNotThrow(() => first.meta.purchase([PRODUCT], 499.5, 'ORDER-NOFBQ'));
    assert.strictEqual(first.calls.length, 0);
    assert.strictEqual(storage.getItem('moonauraMetaPurchases'), null, 'unsent Purchase must not be marked');

    // Once the Pixel is available again the order can still convert.
    const second = loadHelper({ queue: [record], storage });
    assert.strictEqual(second.calls.length, 1);
});

/* Blocked localStorage must not break the storefront. */
test('Blocked localStorage still fires once and does not throw', () => {
    const throwStorage = {
        getItem: function () { throw new Error('blocked'); },
        setItem: function () { throw new Error('blocked'); }
    };
    const { meta, calls } = loadHelper({ storage: throwStorage });
    assert.doesNotThrow(() => meta.purchase([PRODUCT], 499.5, 'ORDER-BLOCKED'));
    assert.strictEqual(meta.purchase([PRODUCT], 499.5, 'ORDER-BLOCKED'), false);
    assert.strictEqual(calls.length, 1);
});

/* 11. Canonical identifier strategy: SKU when available, else product id. */
test('content_ids prefer SKU and fall back to the product id', () => {
    const { meta, calls } = loadHelper();
    const withSku = { item_id: 'SKU-9', item_name: 'Sku item', price: 10, quantity: 1 };
    const withoutSku = { id: '42', item_name: 'Id item', price: 10, quantity: 1 };
    meta.purchase([withSku, withoutSku], 20, 'ORDER-IDS');
    const p = payloadOf(calls[0]);
    assert.deepEqual(p.content_ids, ['SKU-9', '42']);
    assert.deepEqual(p.contents, [{ id: 'SKU-9', quantity: 1 }, { id: '42', quantity: 1 }]);
});

/* 12. contents carry correct ids and quantities. */
test('contents mirror the authoritative order quantities', () => {
    const item = { item_id: 'SKU-Q', item_name: 'Q', price: 100, quantity: 3 };
    const { meta, calls } = loadHelper();
    meta.purchase([item], 300, 'ORDER-QTY');
    assert.deepEqual(payloadOf(calls[0]).contents, [{ id: 'SKU-Q', quantity: 3 }]);
});

/* 10. Currency is exactly INR. */
test('Currency is exactly INR', () => {
    const { meta, calls } = loadHelper();
    meta.purchase([PRODUCT], 499.5, 'ORDER-CUR');
    assert.strictEqual(payloadOf(calls[0]).currency, 'INR');
});

/* 13/14. No PII, no order id, and no dedupe token in the Meta payload. */
test('Purchase payload contains no PII, no order id and no dedupe token', () => {
    const { meta, calls } = loadHelper();
    meta.purchase([PRODUCT, PRODUCT2], 1234.5, 'MA-2026-SECRET');
    const payload = payloadOf(calls[0]);
    const serialized = JSON.stringify(payload);

    const forbiddenKeys = [
        'em', 'ph', 'fn', 'ln', 'external_id', 'client_ip_address',
        'fbc', 'fbp', 'email', 'phone', 'address', 'postal_code',
        'order_id', 'transaction_id', 'dedupe'
    ];
    forbiddenKeys.forEach((key) => {
        assert.ok(!(key in payload), 'payload must not contain ' + key);
    });
    assert.ok(!serialized.includes('MA-2026-SECRET'), 'the internal dedupe token must never be sent');
    assert.ok(!serialized.includes('@'), 'payload must not contain email-like data');
});

/* 16. Existing Phase 2 events remain intact. */
test('Phase 2 events still fire unchanged through the same helper', () => {
    const { meta, calls } = loadHelper();
    meta.product('AddToCart', [PRODUCT2]);
    meta.track('ViewCart', { type: 'product', items: [PRODUCT, PRODUCT2] });
    assert.strictEqual(calls.length, 2);
    assert.strictEqual(eventOf(calls[0]), 'AddToCart');
    assert.deepEqual(payloadOf(calls[0]).content_ids, ['SKU-200']);
    assert.strictEqual(payloadOf(calls[0]).currency, 'INR');
    assert.strictEqual(eventOf(calls[1]), 'ViewCart');
    assert.strictEqual(payloadOf(calls[1]).contents.length, 2);
    // Phase 2 "once" per-page dedupe still works.
    const once = loadHelper({ queue: [{ event: 'ViewContent', type: 'product', items: [PRODUCT], once: true }] });
    assert.strictEqual(once.calls.length, 1);
});

/* -------------------------------------------------------------------
   Part B - static integration checks
------------------------------------------------------------------- */

console.log('\nPart B - static integration checks');

const orderSuccessPhp = read('order-success.php');
const metaPhp = read('includes/meta-pixel-functions.php');
const ga4Php = read('includes/analytics-functions.php');
const checkoutPhp = read('checkout.php');
const cartPhp = read('cart.php');
const paymentFailurePhp = read('payment-failure.php');
const footerPhp = read('includes/footer.php');

const SKIP_DIRS = new Set(['.git', 'node_modules', 'vendor']);

function walkPhp(dir, out) {
    fs.readdirSync(dir, { withFileTypes: true }).forEach((entry) => {
        if (SKIP_DIRS.has(entry.name)) {
            return;
        }
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walkPhp(full, out);
        } else if (entry.name.endsWith('.php')) {
            out.push(path.relative(ROOT, full));
        }
    });
    return out;
}

test('order-success.php requires the Meta helper', () => {
    assert.ok(orderSuccessPhp.includes("require_once __DIR__ . '/includes/meta-pixel-functions.php';"));
});

test('Purchase is queued only from the confirmed order-success page', () => {
    const files = walkPhp(ROOT, []).filter((f) => !f.startsWith('tests' + path.sep));
    const callers = files
        .filter((f) => read(f).includes('meta_pixel_track_purchase('))
        .filter((f) => f !== 'includes/meta-pixel-functions.php');
    assert.deepStrictEqual(callers.sort(), ['order-success.php']);
});

test('Purchase is never queued on cart/checkout/failure/admin/account pages', () => {
    [checkoutPhp, cartPhp, paymentFailurePhp].forEach((src) => {
        assert.ok(!src.includes('meta_pixel_track_purchase('), 'unexpected Purchase queue outside order-success');
    });
    ['dashboard', 'account'].forEach((dir) => {
        walkPhp(path.join(ROOT, dir), []).forEach((file) => {
            assert.ok(!read(file).includes('meta_pixel_track_purchase('), file + ' must not queue Purchase');
        });
    });
});

test('Purchase call is gated behind the settled-payment guard', () => {
    const guard = orderSuccessPhp.indexOf('$paymentIsSettled');
    const call = orderSuccessPhp.indexOf('meta_pixel_track_purchase(');
    assert.ok(guard > -1, 'payment settled guard must exist');
    assert.ok(call > guard, 'Purchase must only run after the order is confirmed');
    assert.ok(orderSuccessPhp.indexOf("redirect('payment.php?order=") < call, 'unsettled orders redirect before any Purchase');
});

test('Purchase uses the authoritative order total and order-number dedupe token', () => {
    assert.ok(orderSuccessPhp.includes("$order['grand_total'] ?? null"));
    assert.ok(orderSuccessPhp.includes("$order['order_number'] ?? ''"));
    assert.ok(!/\$purchaseItems,\s*\$order\['subtotal'\]/.test(orderSuccessPhp), 'cart subtotal must not be used');
});

test('Meta Phase 3 wrapper is defined once and validates order data', () => {
    assert.strictEqual(count(metaPhp, 'function meta_pixel_track_purchase('), 1);
    assert.ok(/!is_numeric\(\$value\)/.test(metaPhp), 'missing/invalid value must be rejected');
    assert.ok(metaPhp.includes("meta_pixel_track('Purchase'"), 'uses the shared queue helper');
});

test('Phase 1 base Pixel is intact and there is no second loader', () => {
    assert.ok(metaPhp.includes('connect.facebook.net/en_US/fbevents.js'));
    assert.strictEqual(count(metaPhp, "fbq('init'"), 1);
    assert.strictEqual(count(metaPhp, "fbq('track', 'PageView')"), 1);
    assert.ok(metaPhp.includes("defined('META_PIXEL_ID')"));
    assert.strictEqual(count(HELPER_SRC, 'fbevents.js'), 0);
    assert.strictEqual(count(metaPhp + HELPER_SRC, 'fbevents.js'), 1, 'only one Pixel loader may exist');
});

test('Phase 2 event wrappers and helper architecture remain', () => {
    ['meta_pixel_track_product_view', 'meta_pixel_track_list_view', 'meta_pixel_track_search',
        'meta_pixel_track_cart_view', 'meta_pixel_track_checkout'].forEach((fn) => {
        assert.strictEqual(count(metaPhp, 'function ' + fn + '('), 1, fn + ' must remain');
    });
    assert.ok(HELPER_SRC.includes('window.moonauraMeta'));
    assert.ok(metaPhp.includes("versioned_asset('assets/js/meta-pixel.js')"), 'reuses the Phase 2 helper loader');
});

test('footer still prints Phase 1 base tag before the queued Phase 3 event', () => {
    const base = footerPhp.indexOf('meta_pixel_print_base_tag()');
    const events = footerPhp.indexOf('meta_pixel_print_events()');
    assert.ok(base > -1 && events > -1 && base < events);
});

test('GA4 implementation and Purchase tracking are unchanged', () => {
    const changed = execFileSync('git', ['diff', '--name-only'], { cwd: ROOT })
        .toString().trim().split('\n').filter(Boolean);
    ['includes/analytics-functions.php', 'assets/js/ga4.js'].forEach((file) => {
        assert.ok(!changed.includes(file), file + ' must remain unchanged');
    });
    assert.strictEqual(count(orderSuccessPhp, "ga4_queue_event('purchase'"), 1, 'GA4 purchase must remain exactly once');
    assert.ok(orderSuccessPhp.includes("'transaction_id' => (string) $order['order_number']"));
    assert.ok(orderSuccessPhp.includes("'currency'       => $purchaseCurrency"));
    assert.ok(ga4Php.includes('function ga4_queue_event'));
});

test('No CAPI in the Phase 3 browser sources; helper payloads stay PII-free', () => {
    assert.ok(!/graph\.facebook\.com|access_token|conversions\/api|client_ip_address/i.test(HELPER_SRC), 'no CAPI in the JS helper');
    assert.ok(!/\b(external_id|client_user_data|fbc|fbp)\b/.test(HELPER_SRC), 'no advanced matching in the JS helper');
    assert.ok(!/\bem:\s|['"]em['"]\s*:/.test(HELPER_SRC), 'no hashed email field in the JS helper');
    assert.ok(!/\bph:\s|['"]ph['"]\s*:/.test(HELPER_SRC), 'no hashed phone field in the JS helper');
    assert.ok(!/graph\.facebook\.com|access_token|conversions\/api|client_ip_address/i.test(metaPhp), 'CAPI stays out of the browser Pixel helper');
});

test('No order id is exposed to Meta (dedupe stays client-side only)', () => {
    // The wrapper must not forward an order id/transaction id into $data.
    const wrapper = metaPhp.slice(metaPhp.indexOf('function meta_pixel_track_purchase('));
    const body = wrapper.slice(0, wrapper.indexOf('\n}\n'));
    assert.ok(!/order_number|order_id|transaction_id/.test(body), 'wrapper data must not carry the order id');
    assert.ok(body.includes("$data['dedupe'] = $dedupeKey"), 'dedupe token is passed separately to the client guard');
});

/* -------------------------------------------------------------------
   Part C - PHP harness (skipped if php is unavailable)
------------------------------------------------------------------- */

console.log('\nPart C - PHP Purchase queue harness');

let phpAvailable = true;
try {
    execFileSync('php', ['-v'], { stdio: 'ignore' });
} catch (err) {
    phpAvailable = false;
    console.log('  skip- php binary not available; PHP harness not run');
}

if (phpAvailable) {
    ['configured', 'unconfigured', 'excluded'].forEach((scenario) => {
        test('php harness scenario: ' + scenario, () => {
            const out = execFileSync(
                'php',
                [path.join('tests', 'meta-pixel-phase3.php'), scenario],
                { cwd: ROOT }
            ).toString();
            assert.ok(out.includes('OK ' + scenario), 'scenario output: ' + out);
        });
    });
}

/* -------------------------------------------------------------------
   Summary
------------------------------------------------------------------- */

console.log('\n----------------------------------------');
console.log('Passed: ' + passed + '   Failed: ' + failures.length);
if (failures.length) {
    failures.forEach((f) => console.log('FAILED: ' + f.name + ' -> ' + (f.err && f.err.stack)));
    process.exit(1);
}
console.log('All Meta Pixel Phase 3 checks passed.');
