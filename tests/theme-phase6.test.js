/* ===================================================================
   Dark Mode Phase 6 - No-flash / early theme initialization
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase6.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const STORAGE_KEY = 'moonaura_theme';

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

function stripComments(src) {
    return src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
}

const functionsPhp = read('includes/functions.php');
const themeJs = read('assets/js/theme.js');
const footerPhp = read('includes/footer.php');
const headerPhp = read('includes/header.php');
const loginPhp = read('account/login.php');
const styleCss = read('assets/css/style.css');
const withoutThemeComments = stripComments(themeJs);

const STOREFRONT_PAGES = [
    'index.php',
    'shop.php',
    'product.php',
    'cart.php',
    'checkout.php',
    'support.php',
    'about.php',
    'wishlist.php',
    'payment.php',
    'manual-upi-payment.php',
    'payment-failure.php',
    'order-success.php',
    'newsletter-confirmed.php',
    'policy.php',
    'concern.php',
    'concerns.php',
    'account/login.php',
    'account/register.php',
    'account/dashboard.php',
    'account/orders.php',
    'account/order-detail.php',
    'account/addresses.php',
    'account/address-form.php',
    'account/profile.php',
    'account/change-password.php',
    'account/forgot-password.php',
    'account/reset-password.php'
];

const OUT_OF_SCOPE = [
    'maintenance.php',
    'dashboard/login.php',
    'dashboard/dashboard.php',
    'account/invoice.php',
    'guest-invoice.php'
];

function extractBootJs() {
    const match = functionsPhp.match(/<script id="moonaura-theme-boot-js">([\s\S]*?)<\/script>/);
    assert.ok(match, 'theme_boot() must emit #moonaura-theme-boot-js');
    return match[1];
}

function runBoot(opts) {
    opts = opts || {};
    const attrs = Object.assign({}, opts.attrs || {});
    const data = Object.assign({}, opts.stored || {});
    let matches = opts.systemDark === true;
    let threw = false;

    const storage = opts.storage === false ? undefined : (opts.storage || {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
        },
        setItem: function (key, value) {
            data[key] = String(value);
        }
    });

    const root = {
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        setAttribute: function (name, value) {
            attrs[name] = String(value);
        },
        hasAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name);
        }
    };

    const sandbox = {
        window: null,
        document: { documentElement: root },
        localStorage: storage
    };
    sandbox.window = sandbox;
    sandbox.window.matchMedia = function () {
        return { matches: matches };
    };
    if (opts.storage === false) {
        delete sandbox.localStorage;
        sandbox.window.localStorage = undefined;
    }

    vm.createContext(sandbox);
    try {
        vm.runInContext(extractBootJs(), sandbox);
    } catch (err) {
        threw = true;
        sandbox._err = err;
    }

    return { attrs: attrs, threw: threw, storage: storage };
}

console.log('\nPart A - early bootstrap wiring');

test('theme_boot() exists as a tiny shared helper', () => {
    assert.ok(functionsPhp.includes('function theme_boot(): void'));
    const bootFn = functionsPhp.split('function theme_boot(): void')[1].split('function ')[0];
    assert.ok(bootFn.includes('moonaura_theme'));
    assert.ok(bootFn.includes('data-theme-resolved'));
    assert.ok(bootFn.includes('prefers-color-scheme'));
    assert.ok(bootFn.includes('catch(e)'));
    assert.ok(!bootFn.includes('fetch('));
    assert.ok(!bootFn.includes('XMLHttpRequest'));
});

test('boot script is inline and does not load a network resource', () => {
    const boot = extractBootJs();
    assert.ok(boot.length < 600, 'boot script must stay tiny, got ' + boot.length);
    assert.ok(!/src=/.test(boot));
    assert.ok(!/\bfetch\s*\(/.test(boot));
    assert.ok(functionsPhp.includes("localStorage.getItem(\"moonaura_theme\")"));
});

test('boot CSS paints dark page background before style.css', () => {
    assert.ok(functionsPhp.includes('id="moonaura-theme-boot"'));
    assert.ok(functionsPhp.includes('background-color:#160e22'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
});

test('every storefront page calls theme_boot() before stylesheet links', () => {
    STOREFRONT_PAGES.forEach(function (rel) {
        const src = read(rel);
        const bootAt = src.indexOf('theme_boot()');
        const cssAt = src.search(/versioned_asset\('assets\/css\/style\.css'\)/);
        assert.ok(bootAt !== -1, rel + ' must call theme_boot()');
        assert.ok(cssAt === -1 || bootAt < cssAt, rel + ' must boot before style.css');
        const headAt = src.indexOf('<head>');
        assert.ok(headAt !== -1, rel + ' must have <head>');
        assert.ok(bootAt > headAt, rel + ' must boot inside <head>');
    });
});

test('out-of-scope pages do not call theme_boot()', () => {
    OUT_OF_SCOPE.forEach(function (rel) {
        const src = read(rel);
        assert.ok(!src.includes('theme_boot()'), rel + ' must stay out of Phase 6');
    });
});

test('runtime manager remains assets/js/theme.js loaded once from the footer', () => {
    assert.ok(footerPhp.includes("versioned_asset('assets/js/theme.js')"));
    assert.strictEqual(footerPhp.split('assets/js/theme.js').length - 1, 1);
    assert.ok(withoutThemeComments.includes("STORAGE_KEY = 'moonaura_theme'"));
    assert.ok(!headerPhp.includes('theme.js'));
});

console.log('\nPart B - boot resolution');

test('stored dark sets data-theme and data-theme-resolved before manager load', () => {
    const { attrs } = runBoot({ stored: { [STORAGE_KEY]: 'dark' } });
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('stored light sets light resolved appearance', () => {
    const { attrs } = runBoot({ stored: { [STORAGE_KEY]: 'light' } });
    assert.strictEqual(attrs['data-theme'], 'light');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
});

test('stored system follows OS dark preference', () => {
    const { attrs } = runBoot({ stored: { [STORAGE_KEY]: 'system' }, systemDark: true });
    assert.strictEqual(attrs['data-theme'], 'system');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('stored system follows OS light preference without overwriting system', () => {
    const { attrs } = runBoot({ stored: { [STORAGE_KEY]: 'system' }, systemDark: false });
    assert.strictEqual(attrs['data-theme'], 'system');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
});

test('missing stored preference is a no-op Light fallback', () => {
    const { attrs } = runBoot();
    assert.strictEqual(attrs['data-theme'], undefined);
    assert.strictEqual(attrs['data-theme-resolved'], undefined);
});

test('malformed stored values are ignored', () => {
    ['sepia', '', '{dark}', 'DARK', 'null', '1'].forEach(function (value) {
        const { attrs } = runBoot({ stored: { [STORAGE_KEY]: value } });
        assert.strictEqual(attrs['data-theme'], undefined);
        assert.strictEqual(attrs['data-theme-resolved'], undefined);
    });
});

test('blocked localStorage does not throw', () => {
    const blocked = {
        getItem: function () {
            throw new Error('SecurityError');
        }
    };
    const { attrs, threw } = runBoot({ storage: blocked });
    assert.strictEqual(threw, false);
    assert.strictEqual(attrs['data-theme'], undefined);
});

test('missing localStorage does not throw', () => {
    const { attrs, threw } = runBoot({ storage: false });
    assert.strictEqual(threw, false);
    assert.strictEqual(attrs['data-theme'], undefined);
});

test('boot does not write localStorage', () => {
    const stored = {};
    runBoot({ stored: stored, storage: {
        getItem: function () { return 'dark'; },
        setItem: function () { stored.wrote = true; }
    }});
    assert.strictEqual(stored.wrote, undefined);
});

console.log('\nPart C - login toggle + scope');

test('login page still has the Phase 4 Light/Dark/System toggle', () => {
    assert.ok(loginPhp.includes('class="login-theme-menu"'));
    assert.ok(loginPhp.includes('class="theme-toggle login-theme-menu-panel"'));
    assert.ok(loginPhp.includes('data-theme-mode="light"'));
    assert.ok(loginPhp.includes('data-theme-mode="dark"'));
    assert.ok(loginPhp.includes('data-theme-mode="system"'));
    assert.ok(loginPhp.includes('theme_boot()'));
});

test('Phase 6 does not add account sync', () => {
    assert.ok(!/customer|account_id|user_id|Authorization/.test(withoutThemeComments));
    assert.ok(!functionsPhp.includes('account_theme'));
    assert.ok(!functionsPhp.includes('user_theme'));
});

test('header and footer still have no site-wide theme toggle', () => {
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(headerPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(footerPhp));
});

test('Light Mode brand tokens remain unchanged', () => {
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
});

test('existing Phase 1 through Phase 4 suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3b.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3c.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase4.test.js'], { cwd: ROOT, stdio: 'pipe' });
});

console.log('\n----------------------------------------');
if (failures.length) {
    console.log('Passed: ' + passed + '   Failed: ' + failures.length);
    failures.forEach(function (item) {
        console.log('  - ' + item.name + ': ' + item.err.message);
    });
    process.exit(1);
}

console.log('Passed: ' + passed + '   Failed: 0');
console.log('All Dark Mode Phase 6 no-flash checks passed.');
