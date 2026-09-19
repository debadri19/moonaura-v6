/* ===================================================================
   Meta Conversions API (CAPI) Phase 4 - targeted tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/meta-capi-phase4.test.js

   Part A executes assets/js/meta-pixel.js in a sandbox and verifies the
   shared event_id is forwarded to fbq as Meta's standard eventID
   option, that it never becomes a custom parameter, and that the
   existing Phase 3 Purchase behaviour (payload, refresh dedupe,
   localStorage) is unchanged when CAPI is disabled.

   Part B statically verifies the CAPI foundation: credential-optional
   configuration, server-only dispatch, shared event id, no PII, no GA4
   or order/payment/invoice changes, and clean git/syntax checks.

   Part C runs the mocked PHP CAPI harness (tests/meta-capi-phase4.php)
   and the existing Phase 2 / Phase 3 suites. No real Meta API request
   is made anywhere in these tests.
================================================================= */

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

    sandbox.fbq = function () {
        calls.push(Array.prototype.slice.call(arguments));
    };
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

function purchaseRecord(dedupe, items, value, eventId) {
    const record = {
        event: 'Purchase',
        type: 'product',
        items: items,
        value: value,
        name: false,
        once: true,
        dedupe: dedupe
    };
    if (eventId) {
        record.event_id = eventId;
    }
    return record;
}

const PRODUCT = { item_id: 'SKU-100', item_name: 'Tiger Eye Bracelet', price: 499.5, quantity: 1 };
const PRODUCT2 = { item_id: 'SKU-200', item_name: 'Amethyst Ring', price: 250, quantity: 2 };

console.log('\nPart A - shared event_id forwarding (browser Purchase)');

test('Browser Purchase forwards the shared event id as Meta eventID', () => {
    const record = purchaseRecord('ORDER-1', [PRODUCT, PRODUCT2], 1234.5, 'ma_shared_deadbeef');
    const { calls } = loadHelper({ queue: [record] });

    assert.strictEqual(calls.length, 1);
    assert.strictEqual(calls[0][1], 'Purchase');
    assert.strictEqual(calls[0].length, 4, 'fbq receives the eventID options argument');
    assert.deepEqual(calls[0][3], { eventID: 'ma_shared_deadbeef' });
});

test('The event id is never merged into the custom parameters', () => {
    const record = purchaseRecord('ORDER-2', [PRODUCT], 499.5, 'ma_opaque_1');
    const { calls } = loadHelper({ queue: [record] });
    const params = calls[0][2];

    assert.ok(!('event_id' in params), 'event_id must not be a custom param');
    assert.ok(!JSON.stringify(params).includes('ma_opaque_1'), 'event id must not appear in params');
    assert.deepEqual(params.content_ids, ['SKU-100']);
    assert.strictEqual(params.currency, 'INR');
});

test('Without a shared event id the browser call stays a plain 3-arg track', () => {
    const record = purchaseRecord('ORDER-3', [PRODUCT], 499.5);
    const { calls } = loadHelper({ queue: [record] });

    assert.strictEqual(calls.length, 1);
    assert.strictEqual(calls[0].length, 3);
});

test('The purchase() helper can forward an event id explicitly', () => {
    const { meta, calls } = loadHelper();
    assert.strictEqual(meta.purchase([PRODUCT], 499.5, 'ORDER-4', 'ma_explicit'), true);
    assert.deepEqual(calls[0][3], { eventID: 'ma_explicit' });
});

test('CAPI disabled: existing browser Purchase payload and refresh dedupe still work', () => {
    const storage = makeStorage();
    const record = purchaseRecord('ORDER-DISABLED', [PRODUCT, PRODUCT2], 1234.5, 'ma_disabled');
    const first = loadHelper({ queue: [record], storage });

    assert.strictEqual(first.calls.length, 1);
    assert.strictEqual(first.calls[0][2].value, 1234.5);
    assert.strictEqual(first.calls[0][2].currency, 'INR');
    assert.deepEqual(first.calls[0][2].contents, [
        { id: 'SKU-100', quantity: 1 },
        { id: 'SKU-200', quantity: 2 }
    ]);
    assert.notStrictEqual(storage.getItem('moonauraMetaPurchases'), null);

    // Refresh with the same localStorage must not fire a second Purchase.
    const second = loadHelper({ queue: [record], storage });
    assert.strictEqual(second.calls.length, 0);
});

/* -------------------------------------------------------------------
   Part B - static integration checks
------------------------------------------------------------------- */

console.log('\nPart B - static integration checks');

const orderSuccessPhp = read('order-success.php');
const metaPhp = read('includes/meta-pixel-functions.php');
const capiPhp = read('includes/meta-capi-functions.php');
const configPhp = read('config/config.php');

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

function git(args) {
    return execFileSync('git', args, { cwd: ROOT }).toString();
}

test('config.php introduces META_CAPI_ACCESS_TOKEN with an empty default', () => {
    assert.ok(configPhp.includes("define('META_CAPI_ACCESS_TOKEN', env('META_CAPI_ACCESS_TOKEN', ''));"),
        'token must be env-driven with an empty default');
    assert.ok(configPhp.includes("define('META_CAPI_API_VERSION', env('META_CAPI_API_VERSION', 'v21.0'));"));
    assert.ok(!/META_CAPI_ACCESS_TOKEN',\s*'[^']+'\)/.test(configPhp), 'no token literal may be shipped');
});

test('CAPI helper module exists and defines the foundation', () => {
    ['meta_capi_access_token', 'meta_capi_is_configured', 'meta_capi_endpoint_url',
        'meta_capi_purchase_event_id', 'meta_capi_build_user_data', 'meta_capi_build_purchase_event',
        'meta_capi_send_purchase', 'meta_capi_set_transport'].forEach((fn) => {
        assert.strictEqual(count(capiPhp, 'function ' + fn + '('), 1, fn + ' must be defined once');
    });
    assert.ok(capiPhp.includes('https://graph.facebook.com/'), 'uses the HTTPS Graph API endpoint');
    assert.ok(capiPhp.includes("'access_token'"), 'token is sent server-side');
    assert.ok(!capiPhp.includes('META_CAPI_ACCESS_TOKEN') || !/=\s*'[A-Za-z0-9_-]{20,}'/.test(capiPhp),
        'no hard-coded token in the helper');
});

test('order-success.php wires CAPI behind the settled-order guard', () => {
    assert.ok(orderSuccessPhp.includes("require_once __DIR__ . '/includes/meta-capi-functions.php';"));
    const guard = orderSuccessPhp.indexOf('$paymentIsSettled');
    const send = orderSuccessPhp.indexOf('meta_capi_send_purchase(');
    assert.ok(guard > -1 && send > guard, 'CAPI dispatch must run only after the settled-order guard');
});

test('Browser and server Purchase share one event id', () => {
    assert.ok(orderSuccessPhp.includes('meta_capi_purchase_event_id($order)'), 'one shared event id is generated');
    assert.strictEqual(count(orderSuccessPhp, 'meta_capi_purchase_event_id('), 1, 'event id generated exactly once');
    assert.ok(orderSuccessPhp.includes('meta_pixel_track_purchase(') && orderSuccessPhp.includes('$metaPurchaseEventId'),
        'the shared id is passed to the browser Purchase');
    const sendCall = orderSuccessPhp.slice(orderSuccessPhp.indexOf('meta_capi_send_purchase('));
    assert.ok(sendCall.includes('$metaPurchaseEventId'), 'the same shared id is passed to the server Purchase');
});

test('CAPI Purchase is dispatched only from the order-success path', () => {
    const files = walkPhp(ROOT, []).filter((f) => !f.startsWith('tests' + path.sep));
    const callers = files
        .filter((f) => read(f).includes('meta_capi_send_purchase('))
        .filter((f) => f !== 'includes/meta-capi-functions.php');
    assert.deepStrictEqual(callers.sort(), ['order-success.php']);
});

test('CAPI is never dispatched from cart/checkout/payment/admin/account pages', () => {
    ['cart.php', 'checkout.php', 'payment.php', 'payment-failure.php', 'payment-verify.php'].forEach((file) => {
        assert.ok(!read(file).includes('meta_capi_send_purchase('), file + ' must not dispatch CAPI');
    });
    ['dashboard', 'account'].forEach((dir) => {
        walkPhp(path.join(ROOT, dir), []).forEach((file) => {
            assert.ok(!read(file).includes('meta_capi_send_purchase('), file + ' must not dispatch CAPI');
        });
    });
});

test('Server Purchase uses authoritative total and canonical identifiers', () => {
    assert.ok(capiPhp.includes("$order['grand_total'] ?? null"), 'value comes from grand_total');
    assert.ok(capiPhp.includes("'currency'     => 'INR'"), 'currency is INR');
    assert.ok(capiPhp.includes("'content_type' => 'product'"), 'content_type is product');
    assert.ok(capiPhp.includes("'action_source' => 'website'"), 'action_source is website');
    assert.ok(!/\$order\['subtotal'\]/.test(capiPhp), 'cart subtotal must never be used');
});

test('Default CAPI user data carries no customer PII', () => {
    const fn = capiPhp.slice(capiPhp.indexOf('function meta_capi_request_user_data('));
    const body = fn.slice(0, fn.indexOf('\n}\n'));
    assert.ok(!/customer_email|customer_phone|customer_name|postal_code|full_name/.test(body),
        'default request context must not read order PII');
    assert.ok(body.includes('client_ip_address') && body.includes('client_user_agent'),
        'only privacy-safe request context is sent by default');
});

test('Phase 1/2/3 sources stay free of CAPI code', () => {
    [metaPhp, HELPER_SRC].forEach((src, i) => {
        assert.ok(!/graph\.facebook\.com|access_token|conversions\/api|client_ip_address/i.test(src),
            'no CAPI in file ' + i);
    });
    // The browser helper may only forward the opaque id, never manage a
    // token or build a server payload.
    assert.ok(HELPER_SRC.includes('eventID'), 'browser helper forwards the event id');
    assert.ok(!HELPER_SRC.includes('access_token'), 'browser helper never handles the access token');
});

test('GA4 implementation and files are unchanged', () => {
    const changed = git(['diff', '--name-only']).trim().split('\n').filter(Boolean);
    ['includes/analytics-functions.php', 'assets/js/ga4.js'].forEach((file) => {
        assert.ok(!changed.includes(file), file + ' must remain unchanged');
    });
    assert.strictEqual(count(orderSuccessPhp, "ga4_queue_event('purchase'"), 1, 'GA4 purchase must remain exactly once');
});

test('Order/payment/checkout/invoice logic is unchanged', () => {
    const changed = git(['diff', '--name-only']).trim().split('\n').filter(Boolean);
    [
        'includes/order-functions.php',
        'includes/payment-functions.php',
        'includes/invoice-functions.php',
        'includes/invoice-designer-functions.php',
        'payment.php',
        'cart.php'
    ].forEach((file) => {
        assert.ok(!changed.includes(file), file + ' must remain unchanged');
    });
});

test('No secret or token is introduced by the working tree', () => {
    const diff = git(['diff']).toString();
    assert.ok(!/META_CAPI_ACCESS_TOKEN',\s*'[^']+'\)/.test(diff), 'no token literal in the diff');
    assert.ok(!/access_token["']?\s*[:=]\s*["'][^"']{16,}["']/.test(diff), 'no hardcoded token value in the diff');
});

test('git diff --check is clean', () => {
    const out = git(['diff', '--check']).trim();
    assert.strictEqual(out, '', 'git diff --check must report no whitespace errors');
});

test('All Phase 4 PHP files pass php -l', () => {
    // Always check the files this phase owns, plus any PHP files still
    // modified in the working tree, so the check is meaningful both
    // before and after the changes are committed.
    const phaseFiles = [
        'config/config.php',
        'includes/meta-capi-functions.php',
        'includes/meta-pixel-functions.php',
        'order-success.php',
        'tests/meta-capi-phase4.php'
    ];
    const porcelain = git(['status', '--porcelain']).replace(/\n+$/, '').split('\n').filter(Boolean);
    const changed = porcelain
        .map((line) => line.slice(3))
        .map((p) => (p.includes(' -> ') ? p.split(' -> ').pop() : p))
        .filter((p) => p.endsWith('.php'));
    const phpFiles = Array.from(new Set(phaseFiles.concat(changed)));

    phpFiles.forEach((file) => {
        const out = execFileSync('php', ['-l', file], { cwd: ROOT }).toString();
        assert.ok(out.includes('No syntax errors'), file + ' must pass php -l');
    });
});

/* -------------------------------------------------------------------
   Part C - harnesses
------------------------------------------------------------------- */

console.log('\nPart C - CAPI PHP harness + existing phase suites');

const scenarios = [
    'no-token', 'empty-token', 'configured', 'no-pixel', 'invalid-data',
    'http-error', 'timeout', 'malformed', 'network', 'hashing'
];

scenarios.forEach((scenario) => {
    test('php CAPI harness scenario: ' + scenario, () => {
        const out = execFileSync(
            'php',
            [path.join('tests', 'meta-capi-phase4.php'), scenario],
            { cwd: ROOT }
        ).toString();
        assert.ok(out.includes('OK ' + scenario), 'scenario output: ' + out);
    });
});

['meta-pixel-phase2.test.js', 'meta-pixel-phase3.test.js'].forEach((suite) => {
    test('existing suite still passes: ' + suite, () => {
        const out = execFileSync('node', [path.join('tests', suite)], { cwd: ROOT }).toString();
        assert.ok(out.includes('Failed: 0'), suite + ' must report zero failures');
    });
});

/* -------------------------------------------------------------------
   Summary
------------------------------------------------------------------- */

console.log('\n----------------------------------------');
console.log('Passed: ' + passed + '   Failed: ' + failures.length);
if (failures.length) {
    failures.forEach((f) => console.log('FAILED: ' + f.name + ' -> ' + (f.err && f.err.stack)));
    process.exit(1);
}
console.log('All Meta CAPI Phase 4 checks passed.');
