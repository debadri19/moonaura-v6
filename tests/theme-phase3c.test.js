/* ===================================================================
   Dark Mode Phase 3C - Support + Account + Shared Interactive UI
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase3c.test.js
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
const supportCss = read('assets/css/support.css');
const accountCss = read('assets/css/account.css');
const supportPhp = read('support.php');
const supportJs = read('assets/js/support.js');
const themeJs = read('assets/js/theme.js');
const passwordToggleJs = read('assets/js/password-toggle.js');
const dashboardPhp = read('account/dashboard.php');
const ordersPhp = read('account/orders.php');
const orderDetailPhp = read('account/order-detail.php');
const addressesPhp = read('account/addresses.php');
const addressFormPhp = read('account/address-form.php');
const profilePhp = read('account/profile.php');
const changePasswordPhp = read('account/change-password.php');
const loginPhp = read('account/login.php');
const accountNavPhp = read('account/includes/account-nav.php');

console.log('\nPart A - Phase 1/2/3A/3B foundation reused');

test('Phase 1 manager and prior-phase tokens remain', () => {
    assert.ok(fs.existsSync(path.join(ROOT, 'assets/js/theme.js')));
    assert.ok(styleCss.includes('html[data-theme="dark"]'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
    assert.ok(styleCss.includes('--color-success-bg: #eaf7ee;'));
    assert.ok(styleCss.includes('--color-status-paid-bg: #e4f6ea;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
});

test('--white is still not remapped in dark mode', () => {
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(!/--white\s*:/.test(darkBlock));
});

console.log('\nPart B - Support + FAQ Dark Mode tokens');

test('support hero, page, cards, and map use semantic surfaces', () => {
    assert.ok(supportCss.includes('background: var(--color-page-bg);'));
    assert.ok(supportCss.includes('var(--color-hero-fade)'));
    assert.ok(supportCss.includes('var(--color-hero-fade-97)'));
    assert.ok(supportCss.includes('var(--color-hero-fade-98)'));
    assert.ok(supportCss.includes('background: var(--color-surface);'));
    assert.ok(supportCss.includes('background: var(--color-input-bg);'));
    assert.ok(supportCss.includes('var(--color-faq-shadow)'));
    assert.ok(supportCss.includes('var(--color-info-card-shadow)'));
    assert.ok(supportCss.includes('var(--color-info-card-hover-border)'));
    assert.ok(supportCss.includes('var(--color-info-icon-well)'));
    assert.ok(supportCss.includes('var(--color-map-shadow)'));
    assert.ok(supportCss.includes('color: var(--color-placeholder);'));
});

test('FAQ accordion animation and search structure are unchanged', () => {
    assert.ok(supportCss.includes('grid-template-rows:0fr;'));
    assert.ok(supportCss.includes('grid-template-rows:1fr;'));
    assert.ok(supportCss.includes('transition:grid-template-rows .35s ease;'));
    assert.ok(supportCss.includes('max-height:300px;'));
    assert.ok(supportCss.includes('max-height:260px;'));
    assert.ok(supportPhp.includes('id="faqSearch"'));
    assert.ok(supportPhp.includes('id="faqList"'));
    assert.ok(supportPhp.includes('class="faq-empty"'));
    assert.ok(supportJs.includes('initSupportFaq'));
    assert.ok(supportJs.includes('classList.toggle("is-open"'));
    assert.ok(supportJs.includes('classList.toggle("is-hidden"'));
    assert.ok(!/moonauraTheme|data-theme|localStorage/.test(supportJs));
});

test('support contact cards and map markup remain', () => {
    assert.ok(supportPhp.includes('class="info-card"'));
    assert.ok(supportPhp.includes('class="map-card"'));
    assert.ok(supportPhp.includes('mailto:support@moonauracrystals.in'));
    assert.ok(supportPhp.includes('tel:+919242319596'));
    assert.ok(supportCss.includes('.info-card i'));
    assert.ok(supportCss.includes('width:48px;'));
    assert.ok(supportCss.includes('height:48px;'));
});

test('support.css has no leftover hardcoded page colors', () => {
    assert.ok(!/#[0-9a-fA-F]{3,8}/.test(supportCss));
    assert.ok(!/rgba?\(/.test(supportCss));
});

test('support layout geometry is unchanged', () => {
    assert.ok(supportCss.includes('min-height:520px;'));
    assert.ok(supportCss.includes('grid-template-columns:minmax(0,35fr) minmax(0,25fr) minmax(0,40fr);'));
    assert.ok(supportCss.includes('padding:24px;'));
    assert.ok(supportCss.includes('padding:20px;'));
    assert.ok(supportCss.includes('border-radius:16px;'));
    assert.ok(supportCss.includes('border-radius:14px;'));
    assert.ok(supportCss.includes('@media(min-width:993px)'));
    assert.ok(supportCss.includes('@media(max-width:992px)'));
    assert.ok(supportCss.includes('@media(max-width:768px)'));
    assert.ok(supportCss.includes('@media(max-width:576px)'));
});

console.log('\nPart C - Account Dark Mode tokens');

test('account cards, nav, and forms use theme surfaces', () => {
    assert.ok(accountCss.includes('background: var(--color-surface);'));
    assert.ok(accountCss.includes('background: var(--color-input-bg);'));
    assert.ok(accountCss.includes('color: var(--color-placeholder);'));
    assert.ok(accountCss.includes('var(--color-gold-divider)'));
    assert.ok(accountCss.includes('.account-nav-toggle'));
    assert.ok(accountCss.includes('.account-nav-panel'));
    assert.ok(accountCss.includes('.account-card'));
    assert.ok(accountCss.includes('.account-welcome-card'));
    assert.ok(accountCss.includes('.pw-toggle'));
});

test('account badges, alerts, and address actions use semantic tokens', () => {
    assert.ok(accountCss.includes('var(--color-stock-low-bg)'));
    assert.ok(accountCss.includes('var(--color-stock-low-text)'));
    assert.ok(accountCss.includes('var(--color-status-info-bg)'));
    assert.ok(accountCss.includes('var(--color-status-info-text)'));
    assert.ok(accountCss.includes('var(--color-positive-bg)'));
    assert.ok(accountCss.includes('var(--color-positive-text)'));
    assert.ok(accountCss.includes('var(--color-stock-out-bg)'));
    assert.ok(accountCss.includes('var(--color-stock-out-text)'));
    assert.ok(accountCss.includes('var(--color-error-border)'));
    assert.ok(accountCss.includes('var(--color-action-hover)'));
    assert.ok(accountCss.includes('.account-alert-success'));
    assert.ok(accountCss.includes('.account-alert-error'));
});

test('account Light Mode token values match previous paints', () => {
    assert.ok(styleCss.includes('--color-gold-divider: rgba(212, 175, 55, .35);'));
    assert.ok(styleCss.includes('--color-status-info-bg: #e8f0fe;'));
    assert.ok(styleCss.includes('--color-status-info-text: #1a56b0;'));
    assert.ok(styleCss.includes('--color-positive-bg: #eaf7ee;'));
    assert.ok(styleCss.includes('--color-positive-text: #1e7b34;'));
    assert.ok(styleCss.includes('--color-positive-border: #c3e8cd;'));
    assert.ok(styleCss.includes('--color-error-border: #f6c6c2;'));
    assert.ok(styleCss.includes('--color-action-hover: rgba(91, 46, 145, .06);'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-input-bg: var(--white);'));
});

test('account.css has no leftover hardcoded page colors', () => {
    assert.ok(!/#[0-9a-fA-F]{3,8}/.test(accountCss));
    assert.ok(!/rgba?\(/.test(accountCss));
});

test('account layout geometry is unchanged', () => {
    assert.ok(accountCss.includes('max-width: 420px;'));
    assert.ok(accountCss.includes('height: 44px;'));
    assert.ok(accountCss.includes('min-height: 220px;'));
    assert.ok(accountCss.includes('grid-template-columns: repeat(4, minmax(0, 1fr));'));
    assert.ok(accountCss.includes('grid-template-columns: 1fr 1fr;'));
    assert.ok(accountCss.includes('padding: 10px 12px;'));
    assert.ok(accountCss.includes('padding: 28px;'));
    assert.ok(accountCss.includes('border-radius: 16px;'));
    assert.ok(accountCss.includes('width: 32px;'));
    assert.ok(accountCss.includes('height: 32px;'));
    assert.ok(accountCss.includes('@media (max-width: 992px)'));
    assert.ok(accountCss.includes('@media (max-width: 700px)'));
    assert.ok(accountCss.includes('@media (max-width: 576px)'));
});

test('account pages still load existing markup and password toggle', () => {
    assert.ok(dashboardPhp.includes('account-welcome-card'));
    assert.ok(dashboardPhp.includes('account-nav-panel'));
    assert.ok(ordersPhp.includes('account-table'));
    assert.ok(orderDetailPhp.includes('account-order-heading'));
    assert.ok(addressesPhp.includes('account-address-card') || addressesPhp.includes('account-card'));
    assert.ok(addressFormPhp.includes('account-card'));
    assert.ok(profilePhp.includes('account-card'));
    assert.ok(changePasswordPhp.includes('pw-toggle'));
    assert.ok(loginPhp.includes('pw-toggle'));
    assert.ok(accountNavPhp.includes('account-nav-dropdown'));
    assert.ok(passwordToggleJs.includes('pw-toggle'));
    assert.ok(!/moonauraTheme|data-theme/.test(passwordToggleJs));
});

console.log('\nPart D - scope + Light Mode safety');

test('Phase 3C does not add UI, persistence, or no-flash logic', () => {
    const withoutComments = themeJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
    assert.ok(!/localStorage/.test(withoutComments));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(supportPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(dashboardPhp));
    assert.ok(!/theme-toggle|dark-mode-toggle/.test(loginPhp));
});

test('Phase 3C does not touch cart, checkout, payment, admin, pixel, or GA4', () => {
    const files = [
        'assets/css/cart.css',
        'assets/css/wishlist.css',
        'assets/css/checkout.css',
        'checkout.php',
        'includes/payment-functions.php',
        'dashboard/assets/css/admin.css',
        'includes/meta-pixel-functions.php',
        'assets/js/ga4.js',
        'assets/js/support.js',
        'assets/js/password-toggle.js'
    ];
    files.forEach(function (rel) {
        const src = read(rel);
        assert.ok(!/--color-gold-divider|--color-status-info-bg|--color-positive-bg|--color-faq-shadow|--color-action-hover/.test(src), rel + ' must stay out of Phase 3C');
    });
});

test('existing Phase 1, Phase 2, Phase 3A, and Phase 3B suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3b.test.js'], { cwd: ROOT, stdio: 'pipe' });
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
console.log('All Dark Mode Phase 3C Support + Account checks passed.');
