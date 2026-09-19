/* ===================================================================
   Dark Mode Phase 2 - Core Storefront UI tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase2.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

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
const headerCss = read('assets/css/header.css');
const homeCss = read('assets/css/home.css');
const shopCss = read('assets/css/shop.css');
const productCss = read('assets/css/product.css');
const footerCss = read('assets/css/footer.css');
const footerPhp = read('includes/footer.php');
const headerPhp = read('includes/header.php');
const themeJs = read('assets/js/theme.js');

console.log('\nPart A - Phase 1 foundation reused');

test('Phase 1 manager, tokens, and footer loader remain', () => {
    assert.ok(fs.existsSync(path.join(ROOT, 'assets/js/theme.js')));
    assert.ok(footerPhp.includes("versioned_asset('assets/js/theme.js')"));
    assert.ok(styleCss.includes('html[data-theme="dark"]'));
    assert.ok(styleCss.includes('html[data-theme-resolved="dark"]'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
});

test('--white is not remapped in dark mode', () => {
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(!/--white\s*:/.test(darkBlock));
});

console.log('\nPart B - core storefront surfaces use semantic tokens');

test('body uses page background and text tokens', () => {
    assert.ok(styleCss.includes('color: var(--color-text);'));
    assert.ok(styleCss.includes('background: var(--color-page-bg);'));
});

test('header glass, icons, search, and mobile menu use theme tokens', () => {
    assert.ok(headerCss.includes('background: var(--color-header-bg);'));
    assert.ok(headerCss.includes('background: var(--color-header-bg-scrolled);'));
    assert.ok(headerCss.includes('box-shadow: var(--color-header-shadow);'));
    assert.ok(headerCss.includes('background: var(--color-mobile-menu-bg);'));
    assert.ok(headerCss.includes('background: var(--color-menu-overlay);'));
    assert.ok(headerCss.includes('background: var(--color-surface);'));
    assert.ok(headerCss.includes('background: var(--color-input-bg);'));
    assert.ok(!headerCss.includes('rgba(255, 255, 255, .95)'));
    assert.ok(!headerCss.includes('rgba(255,255,255,.94)'));
});

test('home hero, sections, and newsletter input use theme tokens', () => {
    assert.ok(homeCss.includes('background: var(--color-page-bg);'));
    assert.ok(homeCss.includes('var(--color-hero-fade)'));
    assert.ok(homeCss.includes('.home-products'));
    assert.ok(homeCss.includes('background:var(--color-input-bg);') || homeCss.includes('background: var(--color-input-bg);'));
    assert.ok(homeCss.includes('var(--color-placeholder)'));
});

test('product cards use surface/border/shadow tokens', () => {
    assert.ok(homeCss.includes('.product-card'));
    assert.ok(homeCss.includes('background: var(--color-surface);'));
    assert.ok(homeCss.includes('border: 1px solid var(--color-card-border);'));
    assert.ok(homeCss.includes('var(--color-old-price)'));
    assert.ok(homeCss.includes('var(--color-stock-out-text)'));
    assert.ok(homeCss.includes('var(--color-stock-low-text)'));
});

test('shop toolbar/search/filters use theme tokens', () => {
    assert.ok(shopCss.includes('background: var(--bg);'));
    assert.ok(shopCss.includes('background: var(--color-input-bg);'));
    assert.ok(shopCss.includes('background: var(--color-surface);'));
});

test('product details stock pills use theme tokens', () => {
    assert.ok(productCss.includes('var(--color-stock-out-bg)'));
    assert.ok(productCss.includes('var(--color-stock-low-bg)'));
    assert.ok(productCss.includes('background: var(--bg);'));
});

test('footer structure remains the existing dark brand band', () => {
    assert.ok(footerCss.includes('#4C267B'));
    assert.ok(footerCss.includes('#43206D'));
    assert.ok(footerCss.includes('.footer-links'));
    assert.ok(footerCss.includes('.footer-social'));
    assert.ok(footerCss.includes('.footer-payment'));
});

console.log('\nPart C - Light Mode safety + scope');

test('Light Mode token values match the previous storefront paints', () => {
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-header-bg: rgba(255, 255, 255, .95);'));
    assert.ok(styleCss.includes('--color-hero-fade: #ffffff;'));
    assert.ok(styleCss.includes('--color-icon-well: #f5f0fb;'));
    assert.ok(styleCss.includes('--color-old-price: #9a9a9a;'));
    assert.ok(styleCss.includes('--color-stock-out-bg: #fdecea;'));
    assert.ok(styleCss.includes('--text: #222222;'));
    assert.ok(styleCss.includes('--bg: #faf8fc;'));
});

test('layout geometry is not restated as Dark Mode changes', () => {
    assert.ok(headerCss.includes('height: 72px;'));
    assert.ok(homeCss.includes('min-height:520px;') || homeCss.includes('min-height: 520px;'));
    assert.ok(homeCss.includes('height: 235px;'));
    assert.ok(productCss.includes('height: 480px;'));
    assert.ok(shopCss.includes('height: 52px;'));
});

test('Phase 2 does not add UI, persistence, or no-flash logic', () => {
    assert.ok(!/theme-toggle|dark-mode-toggle|theme-switch/.test(headerPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle|theme-switch/.test(footerPhp));
    const withoutComments = themeJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
    assert.ok(!/localStorage/.test(withoutComments));
    assert.ok(!/sessionStorage/.test(withoutComments));
    assert.ok(!/document\.cookie/.test(withoutComments));
});

test('Phase 2 does not touch checkout, payment, pixel, GA4, cart, account, or admin', () => {
    const files = [
        'checkout.php',
        'includes/payment-functions.php',
        'includes/meta-pixel-functions.php',
        'assets/js/ga4.js',
        'assets/css/cart.css',
        'assets/css/checkout.css',
        'assets/css/account.css',
        'dashboard/assets/css/admin.css'
    ];
    files.forEach(function (rel) {
        const src = read(rel);
        assert.ok(!/moonauraTheme|--color-header-bg|--color-hero-fade/.test(src), rel + ' must stay out of Phase 2');
    });
});

test('brand purple and gold remain the Dark Mode identity', () => {
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(darkBlock.includes('--color-brand: var(--primary);'));
    assert.ok(darkBlock.includes('--color-accent: var(--gold);'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
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
console.log('All Dark Mode Phase 2 storefront checks passed.');
