/* ===================================================================
   Dark Mode Phase 3B - Checkout + Payment UI tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase3b.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

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

const styleCss = read('assets/css/style.css');
const checkoutCss = read('assets/css/checkout.css');
const checkoutPhp = read('checkout.php');
const checkoutJs = read('assets/js/checkout.js');
const paymentPhp = read('payment.php');
const manualUpiPhp = read('manual-upi-payment.php');
const paymentFailurePhp = read('payment-failure.php');
const paymentJs = read('assets/js/payment.js');
const paymentFunctions = read('includes/payment-functions.php');
const themeJs = read('assets/js/theme.js');

console.log('\nPart A - Phase 1/2/3A foundation reused');

test('Phase 1 manager and Phase 2/3A tokens remain', () => {
    assert.ok(fs.existsSync(path.join(ROOT, 'assets/js/theme.js')));
    assert.ok(styleCss.includes('html[data-theme="dark"]'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
    assert.ok(styleCss.includes('--color-success-bg: #eaf7ee;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
});

test('--white is still not remapped in dark mode', () => {
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(!/--white\s*:/.test(darkBlock));
});

console.log('\nPart B - Checkout Dark Mode tokens');

test('checkout alerts, form card, and fields use semantic tokens', () => {
    assert.ok(checkoutCss.includes('var(--color-success-bg)'));
    assert.ok(checkoutCss.includes('var(--color-success-text)'));
    assert.ok(checkoutCss.includes('var(--color-success-border)'));
    assert.ok(checkoutCss.includes('var(--color-stock-out-bg)'));
    assert.ok(checkoutCss.includes('var(--color-stock-out-text)'));
    assert.ok(checkoutCss.includes('var(--color-danger-border)'));
    assert.ok(checkoutCss.includes('background: var(--color-surface);'));
    assert.ok(checkoutCss.includes('background: var(--color-input-bg);'));
    assert.ok(checkoutCss.includes('color: var(--color-placeholder);'));
    assert.ok(checkoutCss.includes('color: var(--text);'));
    assert.ok(checkoutCss.includes('color: var(--text-light);'));
});

test('saved address selector stays a native select with dark color-scheme', () => {
    assert.ok(checkoutPhp.includes('<select id="saved_address" name="saved_address_id">'));
    assert.ok(checkoutCss.includes('.checkout-form-card select'));
    assert.ok(checkoutCss.includes('background-color: var(--color-input-bg);'));
    assert.ok(checkoutCss.includes('color-scheme: light;'));
    assert.ok(checkoutCss.includes('html[data-theme="dark"] .checkout-form-card select'));
    assert.ok(checkoutCss.includes('html[data-theme-resolved="dark"] .checkout-form-card select'));
    assert.ok(checkoutCss.includes('html[data-theme="system"] .checkout-form-card select'));
    assert.ok(!/custom-dropdown|js-dropdown|select2/.test(checkoutJs));
    assert.ok(checkoutJs.includes("getElementById('saved_address')"));
});

test('order summary and payment options use theme surfaces', () => {
    assert.ok(checkoutCss.includes('.checkout-summary'));
    assert.ok(checkoutCss.includes('background: var(--bg);'));
    assert.ok(checkoutCss.includes('border: 1px solid var(--border);'));
    assert.ok(checkoutCss.includes('.checkout-payment-option'));
    assert.ok(checkoutCss.includes('.checkout-summary-total'));
    assert.ok(checkoutPhp.includes('checkout-payment-option'));
    assert.ok(checkoutPhp.includes('value="cod"'));
    assert.ok(checkoutPhp.includes('value="manual_upi"'));
});

test('checkout Light Mode token values match previous paints', () => {
    assert.ok(styleCss.includes('--color-stock-out-bg: #fdecea;'));
    assert.ok(styleCss.includes('--color-stock-out-text: #b3261e;'));
    assert.ok(styleCss.includes('--color-danger-border: #f6c6c2;'));
    assert.ok(styleCss.includes('--color-stock-low-bg: #fff6e5;'));
    assert.ok(styleCss.includes('--color-stock-low-text: #a86400;'));
    assert.ok(styleCss.includes('--color-warning-border: #f3d99b;'));
    assert.ok(styleCss.includes('--color-icon-well: #f5f0fb;'));
    assert.ok(styleCss.includes('--color-status-paid-bg: #e4f6ea;'));
    assert.ok(styleCss.includes('--color-status-paid-text: #1e7e42;'));
    assert.ok(styleCss.includes('--color-status-pending-bg: #fdf3e0;'));
    assert.ok(styleCss.includes('--color-status-pending-text: #966611;'));
    assert.ok(styleCss.includes('--color-status-failed-bg: #fbe6e6;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-input-bg: var(--white);'));
});

test('checkout.css has no leftover hardcoded page colors', () => {
    assert.ok(!/#[0-9a-fA-F]{3,8}/.test(checkoutCss));
    assert.ok(!/rgba?\(/.test(checkoutCss));
});

test('checkout layout geometry is unchanged', () => {
    assert.ok(checkoutCss.includes('grid-template-columns: 3fr 2fr;'));
    assert.ok(checkoutCss.includes('grid-template-columns: 1fr 1fr;'));
    assert.ok(checkoutCss.includes('padding: 10px 12px;'));
    assert.ok(checkoutCss.includes('padding: 10px 36px 10px 12px;'));
    assert.ok(checkoutCss.includes('padding: 28px;'));
    assert.ok(checkoutCss.includes('border-radius: 16px;'));
    assert.ok(checkoutCss.includes('border-radius: 8px;'));
    assert.ok(checkoutCss.includes('@media (max-width: 900px)'));
    assert.ok(checkoutCss.includes('width: 56px;'));
    assert.ok(checkoutCss.includes('height: 56px;'));
});

console.log('\nPart C - Payment UI Dark Mode tokens');

test('payment pages reuse checkout theme surfaces', () => {
    assert.ok(paymentPhp.includes("versioned_asset('assets/css/checkout.css')"));
    assert.ok(manualUpiPhp.includes("versioned_asset('assets/css/checkout.css')"));
    assert.ok(paymentFailurePhp.includes("versioned_asset('assets/css/checkout.css')"));
    assert.ok(paymentPhp.includes('checkout-form-card'));
    assert.ok(paymentPhp.includes('checkout-test-mode-banner'));
    assert.ok(manualUpiPhp.includes('checkout-alert-success'));
    assert.ok(paymentFailurePhp.includes('var(--color-stock-out-text)'));
});

test('payment status badges and test-mode banner use semantic tokens', () => {
    assert.ok(checkoutCss.includes('var(--color-stock-low-bg)'));
    assert.ok(checkoutCss.includes('var(--color-stock-low-text)'));
    assert.ok(checkoutCss.includes('var(--color-warning-border)'));
    assert.ok(checkoutCss.includes('var(--color-status-paid-bg)'));
    assert.ok(checkoutCss.includes('var(--color-status-paid-text)'));
    assert.ok(checkoutCss.includes('var(--color-status-pending-bg)'));
    assert.ok(checkoutCss.includes('var(--color-status-pending-text)'));
    assert.ok(checkoutCss.includes('var(--color-status-failed-bg)'));
    assert.ok(checkoutCss.includes('var(--color-icon-well)'));
});

test('payment gateway logic files were not modified for theme', () => {
    assert.ok(!/--color-surface|--color-input-bg|data-theme/.test(paymentJs));
    assert.ok(!/--color-surface|--color-input-bg|data-theme/.test(paymentFunctions));
    assert.ok(!/moonauraTheme/.test(checkoutJs));
    assert.ok(!/moonauraTheme/.test(paymentJs));
});

console.log('\nPart D - scope + Light Mode safety');

test('Phase 3B does not add UI, persistence, or no-flash logic', () => {
    const withoutComments = themeJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
    assert.ok(!/localStorage/.test(withoutComments));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(checkoutPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(paymentPhp));
});

test('Phase 3B does not touch support, account, admin, pixel, or GA4', () => {
    const files = [
        'support.php',
        'assets/css/account.css',
        'dashboard/assets/css/admin.css',
        'includes/meta-pixel-functions.php',
        'assets/js/ga4.js',
        'assets/css/cart.css',
        'assets/css/wishlist.css'
    ];
    files.forEach(function (rel) {
        const src = read(rel);
        assert.ok(!/--color-warning-border|--color-status-paid-bg|--color-status-pending-bg/.test(src), rel + ' must stay out of Phase 3B');
    });
});

test('existing Phase 1, Phase 2, and Phase 3A suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
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
console.log('All Dark Mode Phase 3B Checkout + Payment UI checks passed.');
