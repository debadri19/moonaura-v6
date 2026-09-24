/* ===================================================================
   Dark Mode Phase 5 - Authenticated account theme sync
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase5.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const STORAGE_KEY = 'moonaura_theme';
const THEME_SRC = fs.readFileSync(path.join(ROOT, 'assets/js/theme.js'), 'utf8');
const SYNC_SRC = fs.readFileSync(path.join(ROOT, 'assets/js/theme-sync.js'), 'utf8');

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
const customerFunctionsPhp = read('includes/customer-functions.php');
const customerAuthPhp = read('includes/customer-auth.php');
const themeSavePhp = read('account/theme-save.php');
const loginPhp = read('account/login.php');
const logoutPhp = read('account/logout.php');
const registerPhp = read('account/register.php');
const profilePhp = read('account/profile.php');
const dashboardPhp = read('account/dashboard.php');
const footerPhp = read('includes/footer.php');
const headerPhp = read('includes/header.php');
const schemaSql = read('database/schema.sql');
const migrationSql = read('database/migration_phase5_customer_theme_preference.sql');
const themeJs = read('assets/js/theme.js');
const themeSyncJs = read('assets/js/theme-sync.js');
const accountCss = read('assets/css/account.css');
const styleCss = read('assets/css/style.css');
const withoutThemeComments = stripComments(themeJs);

function extractBootJs() {
    const match = functionsPhp.match(/<script id="moonaura-theme-boot-js">([\s\S]*?)<\/script>/);
    assert.ok(match, 'theme_boot() must emit #moonaura-theme-boot-js');
    return match[1];
}

function extractSeedJs() {
    const match = footerPhp.match(/<script id="moonaura-theme-auth-seed">([\s\S]*?)<\/script>/);
    assert.ok(match, 'footer must seed localStorage from the account preference');
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
    sandbox.window.moonauraAuthTheme = Object.prototype.hasOwnProperty.call(opts, 'authTheme')
        ? opts.authTheme
        : null;
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

    return { attrs: attrs, threw: threw, storage: storage, data: data };
}

function runSeed(opts) {
    opts = opts || {};
    const data = Object.assign({}, opts.stored || {});
    const storage = {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
        },
        setItem: function (key, value) {
            data[key] = String(value);
        },
        removeItem: function (key) {
            delete data[key];
        }
    };
    const sandbox = { window: null, localStorage: storage };
    sandbox.window = sandbox;
    sandbox.window.moonauraAuthTheme = Object.prototype.hasOwnProperty.call(opts, 'authTheme')
        ? opts.authTheme
        : null;
    vm.createContext(sandbox);
    vm.runInContext(extractSeedJs(), sandbox);
    return data;
}

function loadTheme(opts) {
    opts = opts || {};
    const attrs = Object.assign({}, opts.attrs || {});
    const stored = Object.assign({}, opts.stored || {});
    const fetches = [];

    const storage = {
        getItem: function (key) {
            return Object.prototype.hasOwnProperty.call(stored, key) ? stored[key] : null;
        },
        setItem: function (key, value) {
            stored[key] = String(value);
        },
        removeItem: function (key) {
            delete stored[key];
        }
    };

    const buttons = {
        light: { attrs: { 'data-theme-mode': 'light', 'aria-pressed': 'false' }, className: 'theme-toggle-btn', getAttribute: function (n) { return this.attrs[n]; }, setAttribute: function (n, v) { this.attrs[n] = String(v); }, closest: function (sel) { return sel === '[data-theme-mode]' || sel === '.theme-toggle' ? this : null; } },
        dark: { attrs: { 'data-theme-mode': 'dark', 'aria-pressed': 'false' }, className: 'theme-toggle-btn', getAttribute: function (n) { return this.attrs[n]; }, setAttribute: function (n, v) { this.attrs[n] = String(v); }, closest: function (sel) { return sel === '[data-theme-mode]' || sel === '.theme-toggle' ? this : null; } },
        system: { attrs: { 'data-theme-mode': 'system', 'aria-pressed': 'false' }, className: 'theme-toggle-btn', getAttribute: function (n) { return this.attrs[n]; }, setAttribute: function (n, v) { this.attrs[n] = String(v); }, closest: function (sel) { return sel === '[data-theme-mode]' || sel === '.theme-toggle' ? this : null; } }
    };
    const buttonList = [buttons.light, buttons.dark, buttons.system];
    const clickListeners = [];

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
        document: {
            documentElement: root,
            querySelectorAll: function (sel) {
                if (sel === '.theme-toggle [data-theme-mode]') {
                    return buttonList;
                }
                return [];
            },
            addEventListener: function (type, fn) {
                if (type === 'click') {
                    clickListeners.push(fn);
                }
            }
        },
        localStorage: storage,
        FormData: function () {
            this.pairs = {};
            this.append = function (k, v) { this.pairs[k] = String(v); };
            this.has = function (k) { return Object.prototype.hasOwnProperty.call(this.pairs, k); };
        }
    };
    sandbox.window = sandbox;
    sandbox.window.localStorage = storage;
    sandbox.window.matchMedia = function () {
        return {
            matches: opts.systemDark === true,
            addEventListener: function () {},
            removeEventListener: function () {},
            addListener: function () {},
            removeListener: function () {}
        };
    };
    sandbox.window.fetch = function (url, init) {
        fetches.push({ url: url, init: init });
        return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ success: true }); } });
    };
    sandbox.window.moonauraThemeSync = opts.sync || null;

    vm.createContext(sandbox);
    vm.runInContext(THEME_SRC, sandbox);
    if (opts.withSync) {
        vm.runInContext(SYNC_SRC, sandbox);
    }

    function click(btn) {
        clickListeners.forEach(function (fn) {
            fn({ target: btn, preventDefault: function () {} });
        });
    }

    return {
        theme: sandbox.window.moonauraTheme,
        attrs: attrs,
        stored: stored,
        fetches: fetches,
        buttons: buttons,
        click: click,
        sandbox: sandbox
    };
}

console.log('\nPart A - valid modes and invalid rejection');

test('supported account modes remain light/dark/system only', () => {
    assert.ok(customerFunctionsPhp.includes("value === 'light' || $value === 'dark' || $value === 'system'"));
    assert.ok(schemaSql.includes("theme_preference    ENUM('light','dark','system') NULL DEFAULT NULL"));
    assert.ok(/ENUM\((?:\\')?light(?:\\')?,(?:\\')?dark(?:\\')?,(?:\\')?system(?:\\')?\)/.test(migrationSql));
    assert.ok(!schemaSql.includes("'sepia'"));
    assert.ok(!customerFunctionsPhp.includes("'auto'"));
});

test('invalid modes normalize to null and are not saved', () => {
    ['sepia', '', 'DARK', 'null', '1', 'light '].forEach(function (value) {
        assert.ok(!customerFunctionsPhp.includes("=== '" + value + "'") || value === '');
    });
    assert.ok(customerFunctionsPhp.includes('function customer_theme_normalize(?string $value): ?string'));
    assert.ok(customerFunctionsPhp.includes('if ($mode === null || $customerId <= 0)'));
    assert.ok(themeSavePhp.includes('customer_theme_normalize'));
    assert.ok(themeSavePhp.includes("'Invalid theme preference.'"));
    assert.ok(themeSavePhp.includes('400'));
});

console.log('\nPart B - guest localStorage behavior');

test('theme.js still persists only via localStorage and does not fetch', () => {
    assert.ok(withoutThemeComments.includes("STORAGE_KEY = 'moonaura_theme'"));
    assert.ok(/localStorage/.test(withoutThemeComments));
    assert.ok(!/sessionStorage/.test(withoutThemeComments));
    assert.ok(!/\bfetch\s*\(/.test(withoutThemeComments));
    assert.ok(!/XMLHttpRequest/.test(withoutThemeComments));
});

test('guests keep localStorage control and never load theme-sync.js', () => {
    assert.ok(footerPhp.includes("!empty($_SESSION['customer_id'])"));
    const syncBlock = footerPhp.split("!empty($_SESSION['customer_id'])")[1];
    assert.ok(syncBlock.includes('theme-sync.js'));
    const { stored } = loadTheme({ stored: { [STORAGE_KEY]: 'dark' } });
    assert.strictEqual(stored[STORAGE_KEY], 'dark');
});

test('guest boot still uses localStorage when no account preference is injected', () => {
    const { attrs } = runBoot({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: null });
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('guest seed is a no-op when account preference is null', () => {
    const data = runSeed({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: null });
    assert.strictEqual(data[STORAGE_KEY], 'dark');
});

console.log('\nPart C - authenticated persistence + login precedence');

test('schema and migration add a nullable customers.theme_preference column', () => {
    const customersBlock = schemaSql.split('CREATE TABLE customers')[1].split('ENGINE=InnoDB')[0];
    assert.ok(customersBlock.includes('theme_preference'));
    assert.ok(customersBlock.includes('NULL DEFAULT NULL'));
    assert.ok(migrationSql.includes('ALTER TABLE customers ADD COLUMN theme_preference'));
    assert.ok(migrationSql.includes('information_schema.COLUMNS'));
    assert.ok(migrationSql.includes("TABLE_NAME = 'customers'"));
    assert.ok(migrationSql.includes('DO NOT run this against production from application code'));
});

test('login caches the stored account preference from the session customer id', () => {
    assert.ok(customerAuthPhp.includes('customer_theme_apply_login((int) $customer[\'id\'])'));
    assert.ok(registerPhp.includes('customer_theme_apply_login($customerId)'));
    assert.ok(customerFunctionsPhp.includes('function customer_theme_apply_login(int $customerId): void'));
    assert.ok(customerFunctionsPhp.includes('$_SESSION[\'customer_theme_preference\'] = $mode'));
    assert.ok(!themeSavePhp.includes('$_POST[\'customer_id\']'));
    assert.ok(!customerFunctionsPhp.includes('$_POST[\'customer_id\']'));
});

test('boot prefers a valid account preference over stale localStorage', () => {
    const darkWins = runBoot({ stored: { [STORAGE_KEY]: 'light' }, authTheme: 'dark' });
    assert.strictEqual(darkWins.attrs['data-theme'], 'dark');
    assert.strictEqual(darkWins.attrs['data-theme-resolved'], 'dark');

    const lightWins = runBoot({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: 'light' });
    assert.strictEqual(lightWins.attrs['data-theme'], 'light');
    assert.strictEqual(lightWins.attrs['data-theme-resolved'], 'light');

    const systemDark = runBoot({ stored: { [STORAGE_KEY]: 'light' }, authTheme: 'system', systemDark: true });
    assert.strictEqual(systemDark.attrs['data-theme'], 'system');
    assert.strictEqual(systemDark.attrs['data-theme-resolved'], 'dark');
});

test('footer seed writes the account preference into localStorage before theme.js', () => {
    const seedAt = footerPhp.indexOf('moonaura-theme-auth-seed');
    const themeAt = footerPhp.indexOf("versioned_asset('assets/js/theme.js')");
    assert.ok(seedAt !== -1 && themeAt !== -1);
    assert.ok(seedAt < themeAt);
    const data = runSeed({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: 'light' });
    assert.strictEqual(data[STORAGE_KEY], 'light');
});

test('NULL or invalid account preference falls back to localStorage without writing', () => {
    const missing = runBoot({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: null });
    assert.strictEqual(missing.attrs['data-theme'], 'dark');
    const invalid = runBoot({ stored: { [STORAGE_KEY]: 'light' }, authTheme: 'sepia' });
    assert.strictEqual(invalid.attrs['data-theme'], 'light');
    const seeded = runSeed({ stored: { [STORAGE_KEY]: 'dark' }, authTheme: 'sepia' });
    assert.strictEqual(seeded[STORAGE_KEY], 'dark');
    assert.ok(customerFunctionsPhp.includes('return null;'));
});

console.log('\nPart D - logged-in change, logout, re-login, CSRF');

test('logged-in setMode persists immediately and POSTs the selected mode', () => {
    const ctx = loadTheme({
        stored: { [STORAGE_KEY]: 'light' },
        withSync: true,
        sync: { url: 'http://127.0.0.1:8000/account/theme-save.php', csrf: 'test-csrf' }
    });
    const applied = ctx.theme.setMode('dark');
    assert.strictEqual(applied, 'dark');
    assert.strictEqual(ctx.attrs['data-theme'], 'dark');
    assert.strictEqual(ctx.stored[STORAGE_KEY], 'dark');
    assert.strictEqual(ctx.fetches.length, 1);
    assert.ok(ctx.fetches[0].url.indexOf('theme-save.php') !== -1);
    assert.strictEqual(ctx.fetches[0].init.method, 'POST');
    assert.strictEqual(ctx.fetches[0].init.credentials, 'same-origin');
    assert.strictEqual(ctx.fetches[0].init.body.pairs.theme, 'dark');
    assert.strictEqual(ctx.fetches[0].init.body.pairs.csrf_token, 'test-csrf');
    assert.strictEqual(ctx.fetches[0].init.body.pairs.ajax, '1');
});

test('profile Appearance toggle reuses Light/Dark/System and posts on click', () => {
    assert.ok(profilePhp.includes('account-appearance'));
    assert.ok(profilePhp.includes('class="theme-toggle"'));
    assert.ok(profilePhp.includes('data-theme-mode="light"'));
    assert.ok(profilePhp.includes('data-theme-mode="dark"'));
    assert.ok(profilePhp.includes('data-theme-mode="system"'));
    assert.ok(accountCss.includes('.account-appearance .theme-toggle'));
    assert.ok(accountCss.includes('.account-login-box .theme-toggle'));
    assert.ok(!dashboardPhp.includes('theme-toggle'));
    assert.ok(!headerPhp.includes('theme-toggle'));

    const ctx = loadTheme({
        stored: { [STORAGE_KEY]: 'light' },
        withSync: true,
        sync: { url: '/account/theme-save.php', csrf: 'csrf-2' }
    });
    ctx.click(ctx.buttons.dark);
    assert.strictEqual(ctx.theme.getMode(), 'dark');
    assert.strictEqual(ctx.stored[STORAGE_KEY], 'dark');
    assert.ok(ctx.fetches.some(function (item) {
        return item.init.body.pairs.theme === 'dark';
    }));
});

test('logout destroys the session and does not write or delete the account preference', () => {
    assert.ok(logoutPhp.includes('customer_logout()'));
    assert.ok(logoutPhp.includes("redirect('login.php')"));
    assert.ok(!logoutPhp.includes('theme_preference'));
    assert.ok(!logoutPhp.includes('customer_theme_save'));
    assert.ok(customerAuthPhp.includes('destroy_session()'));
    assert.ok(!customerAuthPhp.includes('DELETE FROM customers'));
    assert.ok(!customerFunctionsPhp.includes('theme_preference = NULL'));
});

test('re-login restores the saved account preference over guest localStorage', () => {
    assert.ok(customerAuthPhp.includes('customer_theme_apply_login'));
    const result = runBoot({ stored: { [STORAGE_KEY]: 'light' }, authTheme: 'dark' });
    assert.strictEqual(result.attrs['data-theme'], 'dark');
    const seeded = runSeed({ stored: { [STORAGE_KEY]: 'light' }, authTheme: 'dark' });
    assert.strictEqual(seeded[STORAGE_KEY], 'dark');
});

test('theme-save is authenticated, CSRF-protected, and uses the session customer id', () => {
    assert.ok(themeSavePhp.includes('csrf_verify()'));
    assert.ok(themeSavePhp.includes('is_customer_logged_in()'));
    assert.ok(themeSavePhp.includes("$_SESSION['customer_id']"));
    assert.ok(!themeSavePhp.includes('$_GET[\'customer_id\']'));
    assert.ok(!themeSavePhp.includes('$_POST[\'customer_id\']'));
    assert.ok(!themeSavePhp.includes('$_POST[\'id\']'));
    assert.ok(themeSavePhp.includes('customer_theme_save($customerId, $mode)'));
    assert.ok(customerFunctionsPhp.includes('UPDATE customers SET theme_preference = ? WHERE id = ?'));
    assert.ok(themeSavePhp.indexOf('csrf_verify()') < themeSavePhp.indexOf('customer_theme_save'));
});

test('guests and GET requests cannot persist a theme preference', () => {
    assert.ok(themeSavePhp.includes("$_SERVER['REQUEST_METHOD'] !== 'POST'"));
    assert.ok(themeSavePhp.includes("redirect('login.php')"));
    assert.ok(themeSavePhp.includes('401'));
    assert.ok(themeSavePhp.includes("'Not authenticated'"));
    assert.ok(!themeSavePhp.includes('password'));
    assert.ok(!themeSavePhp.includes('password_hash'));
    assert.ok(!themeSavePhp.includes('recovery'));
});

console.log('\nPart E - No-Flash + manager regression');

test('No-Flash boot remains inline, synchronous, and does not fetch', () => {
    const boot = extractBootJs();
    assert.ok(boot.length < 600, 'boot script must stay tiny, got ' + boot.length);
    assert.ok(!/src=/.test(boot));
    assert.ok(!/\bfetch\s*\(/.test(boot));
    assert.ok(functionsPhp.includes('id="moonaura-theme-boot"'));
    assert.ok(functionsPhp.includes('background-color:#160e22'));
    assert.ok(functionsPhp.includes('function theme_boot(): void'));
    assert.ok(!boot.includes('localStorage.setItem'));
});

test('theme.js remains the only theme manager; sync is a thin authenticated layer', () => {
    assert.ok(footerPhp.includes("versioned_asset('assets/js/theme.js')"));
    assert.strictEqual(footerPhp.split('assets/js/theme.js').length - 1, 1);
    assert.ok(footerPhp.includes("versioned_asset('assets/js/theme-sync.js')"));
    assert.ok(themeSyncJs.includes('originalSetMode'));
    assert.ok(themeSyncJs.includes('theme.setMode = function'));
    assert.ok(!themeJs.includes('theme-save.php'));
    assert.ok(!themeJs.includes('moonauraThemeSync'));
});

test('login toggle stays top-right and Light Mode brand tokens are unchanged', () => {
    assert.ok(loginPhp.includes('class="theme-toggle"'));
    assert.ok(accountCss.includes('.account-login-box .theme-toggle'));
    assert.ok(accountCss.includes('position: absolute;'));
    assert.ok(accountCss.includes('top: 12px;'));
    assert.ok(accountCss.includes('right: 12px;'));
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-page-bg: #160e22;'));
});

test('helpers never infer an account preference from browser state on login', () => {
    const applyFn = customerFunctionsPhp.split('function customer_theme_apply_login')[1].split('function ')[0];
    assert.ok(applyFn.includes('customer_theme_load($customerId)'));
    assert.ok(!/localStorage|moonaura_theme|COOKIE|_COOKIE|POST\['theme'\]/.test(applyFn));
    assert.ok(!customerAuthPhp.includes('moonaura_theme'));
});

test('live PHP helpers persist, reject invalid values, and leave NULL as no preference', () => {
    const out = execFileSync('php', ['tests/theme-phase5.php'], { cwd: ROOT, encoding: 'utf8' });
    assert.ok(out.includes('OK persist'));
    assert.ok(out.includes('OK invalid'));
    assert.ok(out.includes('OK null'));
    assert.ok(out.includes('OK identity'));
});

test('existing Phase 1 through Phase 4 and Phase 6 suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3b.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3c.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3d.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase4.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase6.test.js'], { cwd: ROOT, stdio: 'pipe' });
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
console.log('All Dark Mode Phase 5 account-sync checks passed.');
