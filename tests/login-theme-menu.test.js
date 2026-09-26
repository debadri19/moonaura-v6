/* ===================================================================
   Login page circular theme menu + heading copy
   Run with:  node tests/login-theme-menu.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

function read(rel) {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

const loginPhp = read('account/login.php');
const accountCss = read('assets/css/account.css');
const menuJs = read('assets/js/login-theme-menu.js');
const themeJs = read('assets/js/theme.js');
const registerPhp = read('account/register.php');
const profilePhp = read('account/profile.php');

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

test('login heading and subtitle copy', () => {
    assert.ok(loginPhp.includes('<h1>Welcome to MoonAura</h1>'));
    assert.ok(loginPhp.includes('Sign in to access your orders and account.'));
    assert.ok(!loginPhp.includes('Welcome Back'));
    assert.ok(!loginPhp.includes('Log in to view your orders and account details.'));
});

test('login uses a circular trigger, not a permanently visible segmented control', () => {
    assert.ok(loginPhp.includes('class="login-theme-menu-btn"'));
    assert.ok(loginPhp.includes('fa-moon'));
    assert.ok(loginPhp.includes('aria-haspopup="menu"'));
    assert.ok(loginPhp.includes('aria-expanded="false"'));
    assert.ok(loginPhp.includes('hidden'));
    assert.ok(!/class="theme-toggle" role="group"/.test(loginPhp));
    assert.strictEqual((loginPhp.match(/theme-toggle-btn/g) || []).length, 3);
    assert.ok(loginPhp.includes('data-theme-mode="light"'));
    assert.ok(loginPhp.includes('data-theme-mode="dark"'));
    assert.ok(loginPhp.includes('data-theme-mode="system"'));
});

test('login still reuses existing theme-toggle hooks', () => {
    assert.ok(loginPhp.includes('class="theme-toggle login-theme-menu-panel"'));
    assert.ok(themeJs.includes(".theme-toggle [data-theme-mode]"));
    assert.ok(loginPhp.includes("versioned_asset('assets/js/login-theme-menu.js')"));
    assert.ok(!themeJs.includes('login-theme-menu'));
});

test('menu script toggles, outside-click, and Escape close', () => {
    assert.ok(menuJs.includes("querySelector('.login-theme-menu')"));
    assert.ok(menuJs.includes("aria-expanded"));
    assert.ok(menuJs.includes("Escape"));
    assert.ok(menuJs.includes('menu.contains(event.target)'));
    assert.ok(menuJs.includes('fa-sun'));
    assert.ok(menuJs.includes('fa-moon'));
    assert.ok(menuJs.includes('fa-circle-half-stroke'));
    assert.ok(menuJs.includes('moonauraTheme.getMode'));
});

test('login CSS keeps the control compact and in the card corner', () => {
    assert.ok(accountCss.includes('border-radius: 50%'));
    assert.ok(accountCss.includes('.account-login-box .login-theme-menu-btn'));
    assert.ok(accountCss.includes('padding-top: 56px;'));
    assert.ok(accountCss.includes('padding-top: 52px;'));
    assert.ok(accountCss.includes('width: 36px;'));
    assert.ok(accountCss.includes('height: 36px;'));
    assert.ok(accountCss.includes('background: var(--color-surface);'));
    assert.ok(accountCss.includes('box-shadow: inset 3px 0 0 var(--gold);'));
    assert.ok(accountCss.includes('max-width: min(156px, calc(100vw - 48px));'));
});

test('unrelated pages were not given the login menu', () => {
    assert.ok(!registerPhp.includes('login-theme-menu'));
    assert.ok(profilePhp.includes('class="theme-toggle"'));
    assert.ok(!profilePhp.includes('login-theme-menu'));
});

if (failures.length) {
    process.exitCode = 1;
    failures.forEach(function (item) {
        console.error(item.name + ': ' + item.err.message);
    });
} else {
    console.log('\n' + passed + ' passed');
}
