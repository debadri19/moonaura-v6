'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

function read(rel) {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

const navPhp = read('account/includes/account-nav.php');
const dashboardPhp = read('account/dashboard.php');
const accountCss = read('assets/css/account.css');
const mainJs = read('assets/js/main.js');
const themeJs = read('assets/js/theme.js');
const loginPhp = read('account/login.php');

let passed = 0;
const failures = [];

function test(name, fn) {
    try {
        fn();
        passed++;
        console.log('  ok  - ' + name);
    } catch (err) {
        failures.push({ name: name, err: err });
        console.log('  FAIL- ' + name + '\n        ' + (err && err.message));
    }
}

test('dashboard reuses the shared account-nav include', () => {
    assert.ok(dashboardPhp.includes("include __DIR__ . '/includes/account-nav.php'"));
    assert.ok(!dashboardPhp.includes('<details class="account-nav-menu">'));
});

test('account nav still has existing links plus Theme and Logout', () => {
    assert.ok(navPhp.includes('href="dashboard.php"'));
    assert.ok(navPhp.includes('href="orders.php"'));
    assert.ok(navPhp.includes('href="addresses.php"'));
    assert.ok(navPhp.includes('href="profile.php"'));
    assert.ok(navPhp.includes('href="change-password.php"'));
    assert.ok(navPhp.includes('href="logout.php"'));
    assert.ok(navPhp.includes('account-nav-theme-btn'));
    assert.ok(navPhp.includes('data-theme-mode="light"'));
    assert.ok(navPhp.includes('data-theme-mode="dark"'));
    assert.ok(navPhp.includes('data-theme-mode="system"'));
    assert.ok(navPhp.includes('class="theme-toggle account-nav-theme-panel"'));
    const themeAt = navPhp.indexOf('account-nav-theme');
    const logoutAt = navPhp.indexOf('account-nav-logout');
    assert.ok(themeAt !== -1 && logoutAt !== -1 && themeAt < logoutAt);
});

test('panel animation no longer restarts via offsetWidth / keyframes', () => {
    assert.ok(!accountCss.includes('account-nav-panel-in'));
    assert.ok(!accountCss.includes('@keyframes accountNavPanelIn'));
    assert.ok(!mainJs.includes('restartAccountNavPanelAnimation'));
    assert.ok(!mainJs.includes('offsetWidth'));
    assert.ok(accountCss.includes('opacity: 0;'));
    assert.ok(accountCss.includes('visibility: hidden;'));
    assert.ok(accountCss.includes('pointer-events: none;'));
    assert.ok(accountCss.includes('transform: translateY(10px);'));
    assert.ok(accountCss.includes('.account-nav-menu.is-open > .account-nav-panel'));
    assert.ok(accountCss.includes('::details-content'));
});

test('account nav JS uses class-driven open/close and Escape/outside click', () => {
    assert.ok(mainJs.includes('function initAccountNav'));
    assert.ok(mainJs.includes('classList.add("is-open")'));
    assert.ok(mainJs.includes('classList.remove("is-open")'));
    assert.ok(mainJs.includes('requestAnimationFrame'));
    assert.ok(mainJs.includes('pointerdown'));
    assert.ok(mainJs.includes('Escape'));
    assert.ok(mainJs.includes('account-nav-theme-btn'));
    assert.ok(mainJs.includes('prefers-reduced-motion'));
});

test('theme submenu reuses theme.js hooks, not a second engine', () => {
    assert.ok(themeJs.includes(".theme-toggle [data-theme-mode]"));
    assert.ok(themeJs.includes("STORAGE_KEY = 'moonaura_theme'"));
    assert.ok(!mainJs.includes('localStorage.setItem'));
    assert.ok(!mainJs.includes('moonaura_theme'));
    assert.ok(loginPhp.includes('login-theme-menu'));
});

test('pill geometry tokens remain', () => {
    assert.ok(accountCss.includes('min-width: 160px;'));
    assert.ok(accountCss.includes('height: 44px;'));
    assert.ok(accountCss.includes('min-width: 220px;'));
    assert.ok(accountCss.includes('top: calc(100% + 8px);'));
    assert.ok(accountCss.includes('left: 0;'));
    assert.ok(accountCss.includes('border-radius: 999px;'));
});

if (failures.length) {
    process.exitCode = 1;
    failures.forEach(function (item) {
        console.error(item.name + ': ' + item.err.message);
    });
} else {
    console.log('\n' + passed + ' passed');
}
