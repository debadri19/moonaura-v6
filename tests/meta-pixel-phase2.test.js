/* ===================================================================
   Meta Pixel Phase 2 - targeted tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/meta-pixel-phase2.test.js

   Part A executes assets/js/meta-pixel.js inside a sandbox with a fake
   window/fbq and asserts the standard event payloads, INR currency,
   once-per-page dedupe, failure/no-fbq guards and PII absence.

   Part B statically checks the PHP/JS integration points so that the
   Phase 1 Pixel, GA4 output and the AJAX success-only semantics cannot
   silently regress.
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
   Part A helper loader
------------------------------------------------------------------- */

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
    if (opts.queue) {
        sandbox.moonauraMetaQueue = opts.queue;
    }

    vm.createContext(sandbox);
    vm.runInContext(HELPER_SRC, sandbox);

    return { sandbox, calls, meta: sandbox.moonauraMeta };
}

// fbq is called as fbq('track', eventName, params)
function eventOf(call) {
    return call[1];
}
function payloadOf(call) {
    return call[2];
}

const PRODUCT = { item_id: 'SKU-100', item_name: 'Tiger Eye Bracelet', price: 499.5, quantity: 1 };
const PRODUCT2 = { item_id: 'SKU-200', item_name: 'Amethyst Ring', price: 250, quantity: 2 };

console.log('\nPart A - assets/js/meta-pixel.js behaviour');

/* --- ViewContent (product detail), fires once --- */
test('ViewContent product fires once and has the exact phase payload', () => {
    const record = {
        event: 'ViewContent',
        type: 'product',
        items: [PRODUCT],
        contents: false,
        once: true
    };
    const { calls } = loadHelper({ queue: [record, record] });
    assert.strictEqual(calls.length, 1, 'duplicate queued ViewContent must fire once');
    assert.strictEqual(calls[0][0], 'track');
    assert.strictEqual(eventOf(calls[0]), 'ViewContent');
    const params = payloadOf(calls[0]);
    assert.deepEqual(params.content_ids, ['SKU-100']);
    assert.strictEqual(params.content_name, 'Tiger Eye Bracelet');
    assert.strictEqual(params.content_type, 'product');
    assert.strictEqual(params.value, 499.5);
    assert.strictEqual(params.currency, 'INR');
    assert.ok(!('contents' in params), 'ViewContent spec has no contents array');
});

/* --- ViewContent (listing / product_group) --- */
test('ViewContent product_group fires once with all ids and summed value', () => {
    const record = {
        event: 'ViewContent',
        type: 'product_group',
        name: 'Shop',
        items: [PRODUCT, PRODUCT2],
        once: true
    };
    const { calls } = loadHelper({ queue: [record] });
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'ViewContent');
    const params = payloadOf(calls[0]);
    assert.deepEqual(params.content_ids, ['SKU-100', 'SKU-200']);
    assert.strictEqual(params.content_type, 'product_group');
    assert.strictEqual(params.content_name, 'Shop');
    assert.strictEqual(params.value, 999.5);
    assert.strictEqual(params.currency, 'INR');
    assert.ok(!('contents' in params), 'product groups do not send contents');
});

/* --- Search --- */
test('Search fires for a non-empty submitted query only', () => {
    const { calls } = loadHelper({
        queue: [{ event: 'Search', search_string: '  amethyst  ', once: true }]
    });
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'Search');
    const params = payloadOf(calls[0]);
    assert.deepStrictEqual(Object.keys(params), ['search_string']);
    assert.strictEqual(params.search_string, 'amethyst');
});

test('Search is skipped entirely for an empty/whitespace query', () => {
    const { calls } = loadHelper({
        queue: [{ event: 'Search', search_string: '   ', once: true }]
    });
    assert.strictEqual(calls.length, 0);
});

/* --- AddToCart --- */
test('AddToCart fires with contents and quantity-weighted value', () => {
    const { meta, calls } = loadHelper();
    const fired = meta.product('AddToCart', [PRODUCT2]);
    assert.strictEqual(fired, true);
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'AddToCart');
    const params = payloadOf(calls[0]);
    assert.deepEqual(params.content_ids, ['SKU-200']);
    assert.strictEqual(params.content_type, 'product');
    assert.strictEqual(params.value, 500);
    assert.strictEqual(params.currency, 'INR');
    assert.deepEqual(params.contents, [{ id: 'SKU-200', quantity: 2 }]);
});

/* --- RemoveFromCart --- */
test('RemoveFromCart fires with contents and value', () => {
    const { meta, calls } = loadHelper();
    meta.product('RemoveFromCart', [PRODUCT]);
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'RemoveFromCart');
    const params = payloadOf(calls[0]);
    assert.deepEqual(params.content_ids, ['SKU-100']);
    assert.strictEqual(params.value, 499.5);
    assert.deepEqual(params.contents, [{ id: 'SKU-100', quantity: 1 }]);
});

/* --- AddToWishlist --- */
test('AddToWishlist fires without a contents array', () => {
    const { meta, calls } = loadHelper();
    meta.product('AddToWishlist', [PRODUCT], { contents: false });
    assert.strictEqual(calls.length, 1);
    assert.strictEqual(eventOf(calls[0]), 'AddToWishlist');
    const params = payloadOf(calls[0]);
    assert.deepEqual(params.content_ids, ['SKU-100']);
    assert.strictEqual(params.currency, 'INR');
    assert.ok(!('contents' in params));
});

/* --- ViewCart / InitiateCheckout --- */
test('ViewCart and InitiateCheckout use contents + value override', () => {
    const { meta, calls } = loadHelper();
    meta.track('ViewCart', { type: 'product', items: [PRODUCT, PRODUCT2] });
    meta.track('InitiateCheckout', { type: 'product', items: [PRODUCT, PRODUCT2], value: 1234.5 });
    assert.strictEqual(calls.length, 2);
    assert.strictEqual(eventOf(calls[0]), 'ViewCart');
    assert.strictEqual(payloadOf(calls[0]).value, 999.5);
    assert.strictEqual(payloadOf(calls[0]).contents.length, 2);
    assert.strictEqual(eventOf(calls[1]), 'InitiateCheckout');
    assert.strictEqual(payloadOf(calls[1]).value, 1234.5);
    assert.strictEqual(payloadOf(calls[1]).currency, 'INR');
});

/* --- Guards --- */
test('No event fires and nothing throws when fbq is unavailable', () => {
    const { calls, meta } = loadHelper({
        noFbq: true,
        queue: [{ event: 'ViewContent', type: 'product', items: [PRODUCT], once: true }]
    });
    assert.strictEqual(calls.length, 0);
    assert.doesNotThrow(() => meta.product('AddToCart', [PRODUCT]));
    assert.strictEqual(calls.length, 0);
});

test('Empty items / invalid input never fire an event', () => {
    const { meta, calls } = loadHelper();
    assert.strictEqual(meta.product('AddToCart', []), false);
    assert.strictEqual(meta.track('Search', { search_string: '' }), false);
    assert.strictEqual(meta.track('ViewContent', { items: [{ item_name: 'no id' }] }), false);
    assert.strictEqual(calls.length, 0);
});

test('Repeated client calls are allowed (each real add is a distinct event)', () => {
    const { meta, calls } = loadHelper();
    meta.product('AddToCart', [PRODUCT]);
    meta.product('AddToCart', [PRODUCT]);
    assert.strictEqual(calls.length, 2);
});

test('Default currency is INR and payloads contain no PII fields', () => {
    const { meta, calls } = loadHelper();
    meta.product('AddToCart', [PRODUCT]);
    meta.track('ViewCart', { type: 'product', items: [PRODUCT] });
    meta.product('AddToWishlist', [PRODUCT], { contents: false });
    const serialized = JSON.stringify(calls.map((c) => payloadOf(c)));

    assert.ok(!serialized.includes('USD'));
    assert.ok(serialized.includes('"currency":"INR"'));

    const forbiddenKeys = [
        'em', 'ph', 'fn', 'ln', 'external_id', 'client_ip_address',
        'fbc', 'fbp', 'email', 'phone', 'address', 'order_id', 'transaction_id'
    ];
    calls.forEach((call) => {
        forbiddenKeys.forEach((key) => {
            assert.ok(!(key in payloadOf(call)), 'payload must not contain ' + key);
        });
    });
    assert.ok(!serialized.includes('@'), 'payload must not contain email-like data');
});

/* -------------------------------------------------------------------
   Part B - static integration checks
------------------------------------------------------------------- */

console.log('\nPart B - static integration checks');

const productPhp = read('product.php');
const shopPhp = read('shop.php');
const concernPhp = read('concern.php');
const cartPhp = read('cart.php');
const checkoutPhp = read('checkout.php');
const footerPhp = read('includes/footer.php');
const metaPhp = read('includes/meta-pixel-functions.php');
const ajaxJs = read('assets/js/cart-wishlist-ajax.js');

test('product.php queues exactly one product ViewContent', () => {
    assert.strictEqual(count(productPhp, 'meta_pixel_track_product_view('), 1);
    assert.ok(productPhp.includes('meta_pixel_track_product_view($viewItem)'));
});

test('shop.php fires Search only for a non-empty q, else a listing ViewContent', () => {
    assert.strictEqual(count(shopPhp, "meta_pixel_track_search($filters['q'])"), 1);
    assert.ok(/if \(\$filters\['q'\] !== ''\) \{\s*meta_pixel_track_search/.test(shopPhp));
    assert.ok(/elseif \(\$shopListItems\) \{\s*meta_pixel_track_list_view/.test(shopPhp));
});

test('concern.php queues exactly one listing ViewContent', () => {
    assert.strictEqual(count(concernPhp, 'meta_pixel_track_list_view('), 1);
});

test('cart.php queues exactly one ViewCart', () => {
    assert.strictEqual(count(cartPhp, 'meta_pixel_track_cart_view('), 1);
});

test('checkout.php queues exactly one InitiateCheckout', () => {
    assert.strictEqual(count(checkoutPhp, 'meta_pixel_track_checkout('), 1);
});

test('footer.php prints Phase 1 base tag before Phase 2 events, each once', () => {
    const base = footerPhp.indexOf('meta_pixel_print_base_tag()');
    const events = footerPhp.indexOf('meta_pixel_print_events()');
    assert.ok(base > -1 && events > -1);
    assert.ok(base < events, 'base tag must render before queued events so fbq exists');
    assert.strictEqual(count(footerPhp, 'meta_pixel_print_base_tag()'), 1);
    assert.strictEqual(count(footerPhp, 'meta_pixel_print_events()'), 1);
});

test('each instrumented page loads the Meta helper', () => {
    ['product.php', 'shop.php', 'concern.php', 'cart.php', 'checkout.php'].forEach((file) => {
        assert.ok(read(file).includes('meta-pixel-functions.php'), file + ' must require the helper');
    });
});

test('Phase 1 base Pixel is intact (loader, init, PageView, config-driven id)', () => {
    assert.ok(metaPhp.includes('connect.facebook.net/en_US/fbevents.js'));
    assert.strictEqual(count(metaPhp, "fbq('init'"), 1);
    assert.strictEqual(count(metaPhp, "fbq('track', 'PageView')"), 1);
    assert.ok(metaPhp.includes("defined('META_PIXEL_ID')"));
    assert.ok(!/META_PIXEL_ID',\s*'[0-9]{5,}'/.test(metaPhp), 'no pixel id may be hardcoded');
});

test('Phase 2 JS helper loads on configured pages via versioned_asset', () => {
    assert.ok(metaPhp.includes("versioned_asset('assets/js/meta-pixel.js')"));
});

test('No CAPI / Advanced Matching / PII in the Meta implementation', () => {
    const metaSources = [metaPhp, HELPER_SRC, ajaxJs];
    metaSources.forEach((src, i) => {
        assert.ok(!/graph\.facebook\.com|access_token|conversions\/api|client_ip_address/i.test(src), 'no CAPI in file ' + i);
        assert.ok(!/\b(external_id|client_user_data|fbc|fbp)\b/.test(src), 'no advanced matching in file ' + i);
        assert.ok(!/\bem:\s|['"]em['"]\s*:/.test(src), 'no hashed email field in file ' + i);
        assert.ok(!/\bph:\s|['"]ph['"]\s*:/.test(src), 'no hashed phone field in file ' + i);
    });
});

test('Meta event names used are only standard names', () => {
    const allowed = new Set([
        'ViewContent', 'Search', 'AddToCart', 'RemoveFromCart',
        'AddToWishlist', 'InitiateCheckout', 'ViewCart',
        // Added in Phase 3 (order-success.php only).
        'Purchase'
    ]);
    const names = new Set();
    const re = /meta_pixel_track\(\s*'([A-Za-z0-9]+)'/g;
    let m;
    while ((m = re.exec(metaPhp)) !== null) {
        names.add(m[1]);
    }
    ['AddToCart', 'RemoveFromCart', 'AddToWishlist'].forEach((n) => {
        const re2 = new RegExp("moonauraMeta\\.product\\('" + n + "'");
        if (re2.test(ajaxJs)) names.add(n);
    });
    assert.ok(names.size >= 5);
    names.forEach((n) => assert.ok(allowed.has(n), 'unexpected Meta event name: ' + n));
});

test('AJAX Meta events are success-only and never in the failure (catch) paths', () => {
    assert.strictEqual(count(ajaxJs, "moonauraMeta.product('AddToCart'"), 1);
    assert.strictEqual(count(ajaxJs, "moonauraMeta.product('RemoveFromCart'"), 1);
    assert.strictEqual(count(ajaxJs, "moonauraMeta.product('AddToWishlist'"), 1);

    // AddToWishlist must be gated on data.in_wishlist (add, not remove).
    assert.ok(/data\.in_wishlist && data\.item && window\.moonauraMeta/.test(ajaxJs));

    // Slice each .catch(...) body up to the following .finally(...) and
    // ensure nothing in the failure path references the Meta helper.
    const parts = ajaxJs.split('.catch(function');
    assert.ok(parts.length >= 5, 'expected multiple catch handlers, got ' + (parts.length - 1));
    parts.slice(1).forEach((part) => {
        const body = part.split('.finally(')[0];
        assert.ok(!body.includes('moonauraMeta'), 'no Meta event may fire from a failure path');
    });
});

test('GA4 files and payloads are untouched', () => {
    const changed = execFileSync('git', ['diff', '--name-only'], { cwd: ROOT })
        .toString().trim().split('\n').filter(Boolean);
    const untouched = [
        'includes/analytics-functions.php',
        'assets/js/ga4.js'
    ];
    untouched.forEach((file) => {
        assert.ok(!changed.includes(file), file + ' must remain unchanged');
    });

    // The three existing GA4 AJAX events and the GA4 queue helper remain.
    assert.ok(ajaxJs.includes("window.moonauraGa4.event('add_to_wishlist'"));
    assert.ok(ajaxJs.includes("window.moonauraGa4.event('remove_from_cart'"));
    assert.ok(ajaxJs.includes("window.moonauraGa4.event('add_to_cart'"));
    assert.ok(read('includes/analytics-functions.php').includes('function ga4_queue_event'));
});

/* -------------------------------------------------------------------
   Part C - server-side PHP harness (skipped if php is unavailable)
------------------------------------------------------------------- */

console.log('\nPart C - PHP queue/guard harness');

let phpAvailable = true;
try {
    execFileSync('php', ['-v'], { stdio: 'ignore' });
} catch (err) {
    phpAvailable = false;
    console.log('  skip- php binary not available; PHP harness not run');
}

if (phpAvailable) {
    ['configured', 'unconfigured', 'invalid', 'excluded'].forEach((scenario) => {
        test('php harness scenario: ' + scenario, () => {
            const out = execFileSync(
                'php',
                [path.join('tests', 'meta-pixel-phase2.php'), scenario],
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
console.log('All Meta Pixel Phase 2 checks passed.');
