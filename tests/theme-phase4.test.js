/* ===================================================================
   Dark Mode Phase 4 - Browser localStorage persistence
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase4.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const THEME_SRC = fs.readFileSync(path.join(ROOT, 'assets/js/theme.js'), 'utf8');
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

function makeStorage(initial) {
    const data = Object.assign({}, initial || {});
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

function makeButtons() {
    function button(mode) {
        const attrs = {
            'data-theme-mode': mode,
            'aria-pressed': 'false'
        };
        const el = {
            getAttribute: function (name) {
                return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
            },
            setAttribute: function (name, value) {
                attrs[name] = String(value);
            },
            closest: function (sel) {
                if (sel === '[data-theme-mode]') {
                    return el;
                }
                if (sel === '.theme-toggle') {
                    return { className: 'theme-toggle' };
                }
                return null;
            },
            attrs: attrs
        };
        return el;
    }

    return {
        light: button('light'),
        dark: button('dark'),
        system: button('system')
    };
}

function loadTheme(opts) {
    opts = opts || {};
    const listeners = [];
    const clickListeners = [];
    const attrs = Object.assign({}, opts.attrs || {});
    let matches = opts.systemDark === true;
    const buttons = opts.buttons || makeButtons();
    const buttonList = [buttons.light, buttons.dark, buttons.system];

    const media = {
        get matches() {
            return matches;
        },
        addEventListener: function (type, fn) {
            if (type === 'change') {
                listeners.push(fn);
            }
        },
        removeEventListener: function (type, fn) {
            if (type !== 'change') {
                return;
            }
            const index = listeners.indexOf(fn);
            if (index !== -1) {
                listeners.splice(index, 1);
            }
        },
        dispatch: function (nextMatches) {
            matches = nextMatches === true;
            listeners.slice().forEach(function (fn) {
                fn({ matches: matches, media: '(prefers-color-scheme: dark)' });
            });
        }
    };

    const root = {
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        setAttribute: function (name, value) {
            attrs[name] = String(value);
        },
        hasAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name);
        },
        removeAttribute: function (name) {
            delete attrs[name];
        }
    };

    const sandbox = {
        window: null,
        document: {
            documentElement: root,
            addEventListener: function (type, fn) {
                if (type === 'click') {
                    clickListeners.push(fn);
                }
            },
            querySelectorAll: function (sel) {
                if (opts.querySelectorThrows) {
                    throw new Error('query failed');
                }
                if (sel === '.theme-toggle [data-theme-mode]') {
                    return buttonList;
                }
                return [];
            }
        }
    };
    sandbox.window = sandbox;
    sandbox.window.matchMedia = function (query) {
        assert.strictEqual(query, '(prefers-color-scheme: dark)');
        return media;
    };

    if (opts.storage === undefined) {
        sandbox.window.localStorage = makeStorage(opts.stored);
    } else if (opts.storage !== false) {
        sandbox.window.localStorage = opts.storage;
    }

    vm.createContext(sandbox);
    vm.runInContext(THEME_SRC, sandbox);

    return {
        theme: sandbox.window.moonauraTheme,
        root: root,
        attrs: attrs,
        media: media,
        listeners: listeners,
        storage: sandbox.window.localStorage,
        buttons: buttons,
        click: function (el) {
            clickListeners.slice().forEach(function (fn) {
                fn({
                    target: el,
                    preventDefault: function () {}
                });
            });
        }
    };
}

const themeJs = read('assets/js/theme.js');
const loginPhp = read('account/login.php');
const accountCss = read('assets/css/account.css');
const headerPhp = read('includes/header.php');
const footerPhp = read('includes/footer.php');
const styleCss = read('assets/css/style.css');
const withoutComments = stripComments(themeJs);

console.log('\nPart A - persistence behaviour');

test('setMode writes only moonaura_theme with light|dark|system', () => {
    const { theme, storage } = loadTheme();
    theme.setMode('dark');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'dark');
    assert.deepStrictEqual(Object.keys(storage._data), [STORAGE_KEY]);
    theme.setMode('system');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'system');
    theme.setMode('light');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'light');
});

test('stored dark mode is restored on a fresh load', () => {
    const first = loadTheme();
    first.theme.setMode('dark');
    const stored = first.storage._data;

    const second = loadTheme({ stored: stored });
    assert.strictEqual(second.theme.getMode(), 'dark');
    assert.strictEqual(second.attrs['data-theme'], 'dark');
    assert.strictEqual(second.attrs['data-theme-resolved'], 'dark');
    assert.strictEqual(second.storage.getItem(STORAGE_KEY), 'dark');
});

test('stored system mode is restored and still follows OS preference', () => {
    const first = loadTheme({ systemDark: false });
    first.theme.setMode('system');

    const restored = loadTheme({ stored: first.storage._data, systemDark: true });
    assert.strictEqual(restored.theme.getMode(), 'system');
    assert.strictEqual(restored.attrs['data-theme'], 'system');
    assert.strictEqual(restored.theme.getResolvedTheme(), 'dark');
    assert.strictEqual(restored.attrs['data-theme-resolved'], 'dark');
});

test('no stored preference leaves Light Mode fallback unset', () => {
    const { theme, attrs, storage } = loadTheme();
    assert.strictEqual(theme.getMode(), 'light');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(attrs['data-theme'], undefined);
    assert.strictEqual(attrs['data-theme-resolved'], undefined);
    assert.strictEqual(storage.getItem(STORAGE_KEY), null);
});

test('invalid setMode values are ignored and do not write storage', () => {
    const { theme, attrs, storage } = loadTheme();
    theme.setMode('light');
    assert.strictEqual(theme.setMode('sepia'), 'light');
    assert.strictEqual(theme.setMode(''), 'light');
    assert.strictEqual(theme.setMode(null), 'light');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'light');
    assert.strictEqual(attrs['data-theme'], 'light');
});

test('malformed stored values are ignored and keep Light fallback', () => {
    ['sepia', '', '{dark}', 'DARK', 'null', '1'].forEach(function (value) {
        const { theme, attrs } = loadTheme({ stored: { [STORAGE_KEY]: value } });
        assert.strictEqual(theme.getMode(), 'light');
        assert.strictEqual(attrs['data-theme'], undefined);
        assert.strictEqual(attrs['data-theme-resolved'], undefined);
    });
});

test('missing localStorage does not throw and keeps Light fallback', () => {
    const { theme, attrs } = loadTheme({ storage: false });
    assert.strictEqual(theme.getMode(), 'light');
    assert.doesNotThrow(function () {
        theme.setMode('dark');
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
});

test('SecurityError on localStorage does not break setMode or load', () => {
    const blocked = {
        getItem: function () {
            throw new Error('SecurityError');
        },
        setItem: function () {
            throw new Error('SecurityError');
        }
    };
    const { theme, attrs } = loadTheme({ storage: blocked });
    assert.strictEqual(theme.getMode(), 'light');
    assert.doesNotThrow(function () {
        theme.setMode('dark');
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('quota errors on write still apply the selected mode', () => {
    const quota = {
        getItem: function () {
            return null;
        },
        setItem: function () {
            const err = new Error('QuotaExceededError');
            err.name = 'QuotaExceededError';
            throw err;
        }
    };
    const { theme, attrs } = loadTheme({ storage: quota });
    assert.doesNotThrow(function () {
        theme.setMode('system');
    });
    assert.strictEqual(theme.getMode(), 'system');
    assert.strictEqual(attrs['data-theme'], 'system');
});

test('apply does not persist; only setMode writes storage', () => {
    const { theme, storage } = loadTheme();
    theme.apply('dark');
    assert.strictEqual(storage.getItem(STORAGE_KEY), null);
    theme.setMode('dark');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'dark');
});

test('existing data-theme is applied when storage is empty', () => {
    const { theme, attrs, storage } = loadTheme({
        attrs: { 'data-theme': 'dark' },
        systemDark: true
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
    assert.strictEqual(storage.getItem(STORAGE_KEY), null);
});

test('stored preference wins over a leftover data-theme attribute', () => {
    const { theme, attrs } = loadTheme({
        attrs: { 'data-theme': 'light' },
        stored: { [STORAGE_KEY]: 'dark' }
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
});

console.log('\nPart B - login verification toggle');

test('login page has a Light/Dark/System theme-toggle', () => {
    assert.ok(loginPhp.includes('class="theme-toggle"'));
    assert.ok(loginPhp.includes('data-theme-mode="light"'));
    assert.ok(loginPhp.includes('data-theme-mode="dark"'));
    assert.ok(loginPhp.includes('data-theme-mode="system"'));
    assert.ok(loginPhp.includes('aria-label="Color theme"'));
    assert.strictEqual((loginPhp.match(/theme-toggle-btn/g) || []).length, 3);
});

test('login toggle click calls setMode and persists', () => {
    const { theme, attrs, storage, buttons, click } = loadTheme();
    click(buttons.dark);
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'dark');
    assert.strictEqual(buttons.dark.attrs['aria-pressed'], 'true');
    assert.strictEqual(buttons.light.attrs['aria-pressed'], 'false');
    assert.strictEqual(buttons.system.attrs['aria-pressed'], 'false');

    click(buttons.system);
    assert.strictEqual(theme.getMode(), 'system');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'system');
    assert.strictEqual(buttons.system.attrs['aria-pressed'], 'true');
    assert.strictEqual(buttons.dark.attrs['aria-pressed'], 'false');
});

test('restored mode updates toggle pressed state', () => {
    const { buttons } = loadTheme({ stored: { [STORAGE_KEY]: 'dark' } });
    assert.strictEqual(buttons.dark.attrs['aria-pressed'], 'true');
    assert.strictEqual(buttons.light.attrs['aria-pressed'], 'false');
    assert.strictEqual(buttons.system.attrs['aria-pressed'], 'false');
});

test('toggle DOM errors do not break setMode', () => {
    const { theme, attrs } = loadTheme({ querySelectorThrows: true });
    assert.doesNotThrow(function () {
        theme.setMode('dark');
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
});

test('theme-toggle styles use semantic tokens only', () => {
    assert.ok(accountCss.includes('.account-login-box .theme-toggle'));
    assert.ok(accountCss.includes('.account-login-box .theme-toggle-btn'));
    assert.ok(accountCss.includes('background: var(--color-input-bg);'));
    assert.ok(accountCss.includes('background: var(--primary);'));
    assert.ok(accountCss.includes('color: var(--white);'));
});

test('header, footer, and other storefront pages do not gain a toggle', () => {
    const pages = [
        headerPhp,
        footerPhp,
        read('index.php'),
        read('shop.php'),
        read('cart.php'),
        read('checkout.php'),
        read('account/register.php'),
        read('account/dashboard.php')
    ];
    pages.forEach(function (src) {
        assert.ok(!/theme-toggle|data-theme-mode/.test(src));
    });
});

console.log('\nPart C - scope + Light Mode safety');

test('theme.js uses only the moonaura_theme localStorage key', () => {
    assert.ok(withoutComments.includes("STORAGE_KEY = 'moonaura_theme'"));
    assert.ok(/localStorage/.test(withoutComments));
    assert.ok(!/sessionStorage/.test(withoutComments));
    assert.ok(!/document\.cookie/.test(withoutComments));
    assert.ok(!/\bfetch\s*\(/.test(withoutComments));
    assert.ok(!/XMLHttpRequest/.test(withoutComments));
    assert.ok(!/indexedDB/.test(withoutComments));
});

test('Phase 4 does not add account sync or no-flash preload', () => {
    assert.ok(!/customer|account_id|user_id|Authorization/.test(withoutComments));
    assert.ok(!/preload|no-flash|FOUC/.test(withoutComments));
    assert.ok(!footerPhp.includes('no-flash'));
    assert.ok(!headerPhp.includes('moonaura_theme'));
});

test('shared footer still loads theme.js once', () => {
    assert.ok(footerPhp.includes("versioned_asset('assets/js/theme.js')"));
    assert.strictEqual(footerPhp.split('assets/js/theme.js').length - 1, 1);
});

test('Light Mode brand tokens remain unchanged', () => {
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--text: #222222;'));
});

test('OS preference change is still ignored when mode is not system', () => {
    const { theme, attrs, media, storage } = loadTheme({ systemDark: false });
    theme.setMode('light');
    media.dispatch(true);
    assert.strictEqual(theme.getMode(), 'light');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(attrs['data-theme'], 'light');
    assert.strictEqual(storage.getItem(STORAGE_KEY), 'light');
});

test('existing Phase 1 through Phase 3C suites still pass', () => {
    execFileSync('node', ['tests/theme-phase1.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase2.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3a.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3b.test.js'], { cwd: ROOT, stdio: 'pipe' });
    execFileSync('node', ['tests/theme-phase3c.test.js'], { cwd: ROOT, stdio: 'pipe' });
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
console.log('All Dark Mode Phase 4 persistence checks passed.');
