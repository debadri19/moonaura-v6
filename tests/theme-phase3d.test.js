/* ===================================================================
   Dark Mode Phase 3D - Full QA / Evidence (static)
   -------------------------------------------------------------------
   Broader coverage of already-shipped Dark Mode surfaces.
   Run with:  node tests/theme-phase3d.test.js
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

const STOREFRONT_PAGES = [
    'index.php',
    'shop.php',
    'product.php',
    'cart.php',
    'wishlist.php',
    'checkout.php',
    'payment.php',
    'payment-failure.php',
    'manual-upi-payment.php',
    'order-success.php',
    'support.php',
    'about.php',
    'policy.php',
    'concern.php',
    'concerns.php',
    'newsletter-confirmed.php',
    'account/login.php',
    'account/register.php',
    'account/forgot-password.php',
    'account/reset-password.php',
    'account/dashboard.php',
    'account/orders.php',
    'account/order-detail.php',
    'account/addresses.php',
    'account/address-form.php',
    'account/profile.php',
    'account/change-password.php'
];

const THEMED_CSS = [
    'assets/css/style.css',
    'assets/css/header.css',
    'assets/css/home.css',
    'assets/css/shop.css',
    'assets/css/product.css',
    'assets/css/cart.css',
    'assets/css/wishlist.css',
    'assets/css/checkout.css',
    'assets/css/support.css',
    'assets/css/account.css',
    'assets/css/about-us.css',
    'assets/css/policy.css'
];

const styleCss = read('assets/css/style.css');
const aboutCss = read('assets/css/about-us.css');
const policyCss = read('assets/css/policy.css');
const accountCss = read('assets/css/account.css');
const loginPhp = read('account/login.php');
const logoutPhp = read('account/logout.php');
const themeJs = read('assets/js/theme.js');
const functionsPhp = read('includes/functions.php');

console.log('\nPart A - existing Dark Mode suites');

test('Phase 1 through Phase 4 and Phase 6 still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3b.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3c.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase4.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase6.test.js'], { cwd: ROOT, stdio: 'pipe' });
});

console.log('\nPart B - page coverage');

test('every Dark Mode storefront page boots the theme before style.css', () => {
    STOREFRONT_PAGES.forEach(function (rel) {
        const src = read(rel);
        const bootAt = src.indexOf('theme_boot()');
        const cssAt = src.search(/versioned_asset\('assets\/css\/style\.css'\)/);
        assert.ok(bootAt !== -1, rel + ' must call theme_boot()');
        assert.ok(cssAt === -1 || bootAt < cssAt, rel + ' must boot before style.css');
    });
});

test('logout remains a redirect with no independent theme UI', () => {
    assert.ok(logoutPhp.includes('customer_logout()'));
    assert.ok(logoutPhp.includes("redirect('login.php')"));
    assert.ok(!logoutPhp.includes('theme_boot()'));
    assert.ok(!logoutPhp.includes('theme-toggle'));
});

test('themed CSS files still consume semantic surface tokens', () => {
    THEMED_CSS.forEach(function (rel) {
        const src = read(rel);
        assert.ok(
            /--color-(surface|page-bg|input-bg|stock-out-bg|stock-out-text|stock-low-bg|empty-icon-well)/.test(src),
            rel + ' must use semantic theme tokens'
        );
    });
});

console.log('\nPart C - About + Policy Dark Mode evidence');

test('About hero, story, purpose, and promise use theme tokens', () => {
    assert.ok(aboutCss.includes('.about-hero'));
    assert.ok(aboutCss.includes('background: var(--color-page-bg);'));
    assert.ok(aboutCss.includes('var(--color-hero-fade)'));
    assert.ok(aboutCss.includes('var(--color-hero-fade-97)'));
    assert.ok(aboutCss.includes('var(--color-hero-fade-98)'));
    assert.ok(aboutCss.includes('.our-story'));
    assert.ok(aboutCss.includes('.purpose-card'));
    assert.ok(aboutCss.includes('background: var(--color-surface);'));
    assert.ok(aboutCss.includes('var(--color-card-border)'));
    assert.ok(aboutCss.includes('var(--color-icon-well)'));
    assert.ok(aboutCss.includes('.promise-item'));
    assert.ok(aboutCss.includes('html[data-theme="dark"] .promise-icon'));
    assert.ok(aboutCss.includes('html[data-theme="dark"] .promise-item'));
});

test('About Light Mode gold icon well remains and is overridden in Dark Mode', () => {
    assert.ok(aboutCss.includes('background:#F8F2E4;'));
    assert.ok(aboutCss.includes('html[data-theme="dark"] .promise-icon'));
    assert.ok(aboutCss.includes('background: var(--color-icon-well);'));
});

test('Policy page, cards, nav, and help use theme tokens', () => {
    assert.ok(policyCss.includes('.policy-page'));
    assert.ok(policyCss.includes('var(--color-page-bg)'));
    assert.ok(policyCss.includes('.policy-hero'));
    assert.ok(policyCss.includes('.policy-card'));
    assert.ok(policyCss.includes('background-color: var(--color-surface);'));
    assert.ok(policyCss.includes('.policy-nav-item'));
    assert.ok(policyCss.includes('background: var(--color-surface);'));
    assert.ok(policyCss.includes('.policy-help'));
    assert.ok(policyCss.includes('var(--color-icon-well)'));
    assert.ok(policyCss.includes('html[data-theme="dark"] .policy-card'));
    assert.ok(policyCss.includes('html[data-theme="dark"] .policy-nav-item'));
    assert.ok(policyCss.includes('html[data-theme="dark"] .policy-help'));
});

test('Policy Light Mode shadows are preserved', () => {
    assert.ok(policyCss.includes('box-shadow:0 10px 28px rgba(0,0,0,.05);'));
    assert.ok(policyCss.includes('box-shadow:0 18px 45px rgba(0,0,0,.06);'));
    assert.ok(policyCss.includes('box-shadow:0 10px 30px rgba(0,0,0,.04);'));
});

console.log('\nPart D - login toggle position');

test('login toggle remains Light/Dark/System and is first in the card', () => {
    assert.ok(loginPhp.includes('class="login-theme-menu"'));
    assert.ok(loginPhp.includes('class="theme-toggle login-theme-menu-panel"'));
    assert.ok(loginPhp.includes('data-theme-mode="light"'));
    assert.ok(loginPhp.includes('data-theme-mode="dark"'));
    assert.ok(loginPhp.includes('data-theme-mode="system"'));
    const toggleAt = loginPhp.indexOf('class="login-theme-menu"');
    const headingAt = loginPhp.indexOf('<h1>Welcome to MoonAura</h1>');
    assert.ok(toggleAt !== -1 && headingAt !== -1);
    assert.ok(toggleAt < headingAt, 'toggle must sit above the login heading');
});

test('login toggle is absolutely positioned at the top-right of the card', () => {
    assert.ok(accountCss.includes('.account-auth-box.account-login-box'));
    assert.ok(accountCss.includes('position: relative;'));
    assert.ok(accountCss.includes('.account-login-box .login-theme-menu'));
    assert.ok(accountCss.includes('.account-login-box .theme-toggle'));
    assert.ok(accountCss.includes('position: absolute;'));
    assert.ok(accountCss.includes('top: 12px;'));
    assert.ok(accountCss.includes('right: 12px;'));
    assert.ok(accountCss.includes('padding-top: 56px;'));
    assert.ok(accountCss.includes('@media (max-width: 576px)'));
    assert.ok(accountCss.includes('top: 10px;'));
    assert.ok(accountCss.includes('right: 10px;'));
});

console.log('\nPart E - foundation unchanged');

test('theme manager, persistence, and No-Flash helpers were not rewritten', () => {
    assert.ok(themeJs.includes("STORAGE_KEY = 'moonaura_theme'"));
    assert.ok(functionsPhp.includes('function theme_boot(): void'));
    assert.ok(functionsPhp.includes('id="moonaura-theme-boot"'));
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    assert.ok(!/--white\s*:/.test(darkBlock));
});

test('Phase 3D does not add a second theme system', () => {
    assert.ok(!/dark-mode-toggle|theme-switch/.test(aboutCss));
    assert.ok(!/dark-mode-toggle|theme-switch/.test(policyCss));
    assert.ok(!accountCss.includes('dark-mode-toggle'));
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
console.log('All Dark Mode Phase 3D QA / evidence checks passed.');
