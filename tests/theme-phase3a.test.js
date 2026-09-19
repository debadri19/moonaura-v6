/* ===================================================================
   Dark Mode Phase 3A - Cart + Wishlist tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase3a.test.js
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
const cartCss = read('assets/css/cart.css');
const wishlistCss = read('assets/css/wishlist.css');
const cartPhp = read('cart.php');
const wishlistPhp = read('wishlist.php');
const themeJs = read('assets/js/theme.js');

console.log('\nPart A - Phase 1/2 foundation reused');

test('Phase 1 manager and Phase 2 storefront tokens remain', () => {
    assert.ok(fs.existsSync(path.join(ROOT, 'assets/js/theme.js')));
    assert.ok(styleCss.includes('html[data-theme="dark"]'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
});

test('--white is still not remapped in dark mode', () => {
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(!/--white\s*:/.test(darkBlock));
});

console.log('\nPart B - Cart Dark Mode tokens');

test('cart alerts use semantic success/danger tokens', () => {
    assert.ok(cartCss.includes('var(--color-success-bg)'));
    assert.ok(cartCss.includes('var(--color-success-text)'));
    assert.ok(cartCss.includes('var(--color-success-border)'));
    assert.ok(cartCss.includes('var(--color-stock-out-bg)'));
    assert.ok(cartCss.includes('var(--color-stock-out-text)'));
    assert.ok(cartCss.includes('var(--color-danger-border)'));
});

test('cart items, quantity, and summary use theme surfaces', () => {
    assert.ok(cartCss.includes('background: var(--color-surface);'));
    assert.ok(cartCss.includes('background: var(--color-input-bg);'));
    assert.ok(cartCss.includes('background: var(--bg);'));
    assert.ok(cartCss.includes('border: 1px solid var(--border);'));
    assert.ok(cartCss.includes('var(--color-qty-btn-hover)'));
    assert.ok(cartCss.includes('color: var(--text);'));
    assert.ok(cartCss.includes('color: var(--text-light);'));
    assert.ok(cartCss.includes('color: var(--primary-dark);'));
});

test('cart empty icon well uses shared empty-well token', () => {
    assert.ok(cartCss.includes('var(--color-empty-icon-well)'));
    assert.ok(cartPhp.includes('empty-state cart-empty-state'));
    assert.ok(cartPhp.includes('cart-empty-icon'));
    assert.ok(cartPhp.includes('cart-empty-divider'));
});

test('cart Light Mode token values match previous paints', () => {
    assert.ok(styleCss.includes('--color-success-bg: #eaf7ee;'));
    assert.ok(styleCss.includes('--color-success-text: #1e7b34;'));
    assert.ok(styleCss.includes('--color-success-border: #c3e8cd;'));
    assert.ok(styleCss.includes('--color-empty-icon-well: #f3eef8;'));
    assert.ok(styleCss.includes('--color-qty-btn-hover: rgba(91, 46, 145, .08);'));
    assert.ok(styleCss.includes('--color-danger-border: #f6c6c2;'));
    assert.ok(styleCss.includes('--color-danger-hover: #8f1e18;'));
});

test('cart.css has no leftover hardcoded page colors', () => {
    assert.ok(!/#[0-9a-fA-F]{3,8}/.test(cartCss));
    assert.ok(!/rgba?\(/.test(cartCss));
});

test('cart layout geometry is unchanged', () => {
    assert.ok(cartCss.includes('grid-template-columns: 2fr 1fr;'));
    assert.ok(cartCss.includes('grid-template-columns: 90px minmax(0, 1fr) 6.25rem max-content 6.25rem;'));
    assert.ok(cartCss.includes('width: 90px;'));
    assert.ok(cartCss.includes('height: 90px;'));
    assert.ok(cartCss.includes('height: 40px;'));
    assert.ok(cartCss.includes('width: 40px;'));
    assert.ok(cartCss.includes('padding: 56px 28px 52px;'));
});

console.log('\nPart C - Wishlist Dark Mode tokens');

test('wishlist empty well and unavailable state use theme tokens', () => {
    assert.ok(wishlistCss.includes('var(--color-empty-icon-well)'));
    assert.ok(wishlistCss.includes('var(--color-stock-out-text)'));
    assert.ok(wishlistCss.includes('color: var(--text-light);'));
    assert.ok(wishlistPhp.includes('empty-state wishlist-empty-state'));
    assert.ok(wishlistPhp.includes('product-card'));
    assert.ok(wishlistPhp.includes('home.css'));
});

test('wishlist empty-state alignment rules were not modified', () => {
    assert.ok(wishlistCss.includes('.wishlist-page h1 + .empty-state'));
    assert.ok(wishlistCss.includes('margin-top: 16px;'));
    assert.ok(wishlistCss.includes('padding: 56px 28px 52px;'));
    assert.ok(wishlistCss.includes('width: 88px;'));
    assert.ok(wishlistCss.includes('height: 88px;'));
    assert.ok(wishlistCss.includes('margin: 20px 0 28px;'));
});

test('wishlist.css has no leftover hardcoded page colors', () => {
    assert.ok(!/#[0-9a-fA-F]{3,8}/.test(wishlistCss));
    assert.ok(!/rgba?\(/.test(wishlistCss));
});

console.log('\nPart D - scope + Light Mode safety');

test('Phase 3A does not add UI, persistence, or no-flash logic', () => {
    const withoutComments = themeJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
    assert.ok(!/localStorage/.test(withoutComments));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(cartPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(wishlistPhp));
});

test('Phase 3A does not touch checkout, payment, support, account, admin, pixel, or GA4', () => {
    const files = [
        'checkout.php',
        'includes/payment-functions.php',
        'support.php',
        'assets/css/account.css',
        'dashboard/assets/css/admin.css',
        'includes/meta-pixel-functions.php',
        'assets/js/ga4.js',
        'assets/js/cart.js',
        'assets/js/cart-wishlist-ajax.js'
    ];
    files.forEach(function (rel) {
        const src = read(rel);
        assert.ok(!/--color-success-bg|--color-empty-icon-well|--color-qty-btn-hover|--color-danger-border/.test(src), rel + ' must stay out of Phase 3A');
    });
});

test('existing Phase 1 and Phase 2 suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
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
console.log('All Dark Mode Phase 3A Cart + Wishlist checks passed.');
