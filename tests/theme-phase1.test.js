/* ===================================================================
   Dark Mode Phase 1 - Theme Foundation tests + static checks
   -------------------------------------------------------------------
   Run with:  node tests/theme-phase1.test.js
=================================================================== */

'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..');
const THEME_SRC = fs.readFileSync(path.join(ROOT, 'assets/js/theme.js'), 'utf8');

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

function loadTheme(opts) {
    opts = opts || {};
    const listeners = [];
    const attrs = Object.assign({}, opts.attrs || {});
    let matches = opts.systemDark === true;

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
            documentElement: root
        }
    };
    sandbox.window = sandbox;
    sandbox.window.matchMedia = function (query) {
        assert.strictEqual(query, '(prefers-color-scheme: dark)');
        return media;
    };

    vm.createContext(sandbox);
    vm.runInContext(THEME_SRC, sandbox);

    return {
        theme: sandbox.window.moonauraTheme,
        root: root,
        attrs: attrs,
        media: media,
        listeners: listeners
    };
}

console.log('\nPart A - theme manager behaviour');

test('exposes a single moonauraTheme API with light/dark/system', () => {
    const { theme } = loadTheme();
    assert.ok(theme);
    assert.strictEqual(JSON.stringify([].slice.call(theme.modes)), JSON.stringify(['light', 'dark', 'system']));
    assert.strictEqual(typeof theme.getMode, 'function');
    assert.strictEqual(typeof theme.setMode, 'function');
    assert.strictEqual(typeof theme.getResolvedTheme, 'function');
    assert.strictEqual(typeof theme.resolve, 'function');
    assert.strictEqual(typeof theme.apply, 'function');
});

test('unset data-theme does not write attributes on load (Light Mode fallback)', () => {
    const { attrs } = loadTheme();
    assert.strictEqual(attrs['data-theme'], undefined);
    assert.strictEqual(attrs['data-theme-resolved'], undefined);
});

test('unset data-theme reads as light and resolves to light', () => {
    const { theme } = loadTheme();
    assert.strictEqual(theme.getMode(), 'light');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(theme.resolve('light'), 'light');
});

test('light state applies data-theme=light and resolves light', () => {
    const { theme, attrs } = loadTheme();
    assert.strictEqual(theme.setMode('light'), 'light');
    assert.strictEqual(attrs['data-theme'], 'light');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
});

test('dark state applies data-theme=dark and resolves dark', () => {
    const { theme, attrs } = loadTheme();
    assert.strictEqual(theme.setMode('dark'), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
    assert.strictEqual(theme.getResolvedTheme(), 'dark');
});

test('system follows prefers-color-scheme: light', () => {
    const { theme, attrs } = loadTheme({ systemDark: false });
    theme.setMode('system');
    assert.strictEqual(attrs['data-theme'], 'system');
    assert.strictEqual(theme.getMode(), 'system');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
});

test('system follows prefers-color-scheme: dark', () => {
    const { theme, attrs } = loadTheme({ systemDark: true });
    theme.setMode('system');
    assert.strictEqual(attrs['data-theme'], 'system');
    assert.strictEqual(theme.getResolvedTheme(), 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('OS preference change updates resolved system theme while keeping mode=system', () => {
    const { theme, attrs, media } = loadTheme({ systemDark: false });
    theme.setMode('system');
    assert.strictEqual(theme.getResolvedTheme(), 'light');

    media.dispatch(true);
    assert.strictEqual(theme.getMode(), 'system');
    assert.strictEqual(theme.getResolvedTheme(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'system');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');

    media.dispatch(false);
    assert.strictEqual(theme.getMode(), 'system');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
});

test('OS preference change is ignored when mode is not system', () => {
    const { theme, attrs, media } = loadTheme({ systemDark: false });
    theme.setMode('light');
    media.dispatch(true);
    assert.strictEqual(theme.getMode(), 'light');
    assert.strictEqual(theme.getResolvedTheme(), 'light');
    assert.strictEqual(attrs['data-theme'], 'light');
    assert.strictEqual(attrs['data-theme-resolved'], 'light');
});

test('invalid setMode values are ignored', () => {
    const { theme, attrs } = loadTheme();
    theme.setMode('light');
    assert.strictEqual(theme.setMode('sepia'), 'light');
    assert.strictEqual(theme.setMode(''), 'light');
    assert.strictEqual(theme.setMode(null), 'light');
    assert.strictEqual(attrs['data-theme'], 'light');
});

test('existing data-theme on load is applied without persistence APIs', () => {
    const { theme, attrs } = loadTheme({
        attrs: { 'data-theme': 'dark' },
        systemDark: true
    });
    assert.strictEqual(theme.getMode(), 'dark');
    assert.strictEqual(attrs['data-theme'], 'dark');
    assert.strictEqual(attrs['data-theme-resolved'], 'dark');
});

test('theme.js persists only via localStorage and does not use sessionStorage, cookies, or fetch', () => {
    const withoutComments = THEME_SRC
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\/\/.*$/gm, '');
    assert.ok(/localStorage/.test(withoutComments));
    assert.ok(withoutComments.includes('moonaura_theme'));
    assert.ok(!/sessionStorage/.test(withoutComments));
    assert.ok(!/document\.cookie/.test(withoutComments));
    assert.ok(!/\bfetch\s*\(/.test(withoutComments));
});

console.log('\nPart B - CSS token foundation');

const styleCss = read('assets/css/style.css');
const SEMANTIC_TOKENS = [
    '--color-page-bg',
    '--color-surface',
    '--color-text',
    '--color-text-muted',
    '--color-border',
    '--color-input-bg',
    '--color-input-border',
    '--color-shadow',
    '--color-brand',
    '--color-brand-dark',
    '--color-accent'
];

test('existing Light Mode brand variables remain unchanged', () => {
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--primary-dark: #43206D;'));
    assert.ok(styleCss.includes('--gold: #D4AF37;'));
    assert.ok(styleCss.includes('--text: #222222;'));
    assert.ok(styleCss.includes('--text-light: #666666;'));
    assert.ok(styleCss.includes('--white: #ffffff;'));
    assert.ok(styleCss.includes('--bg: #faf8fc;'));
    assert.ok(styleCss.includes('--border: #ece7f5;'));
    assert.ok(styleCss.includes('--shadow: 0 15px 40px rgba(0, 0, 0, .08);'));
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-text: var(--text);'));
});

test('semantic Light Mode tokens exist on :root', () => {
    SEMANTIC_TOKENS.forEach(function (token) {
        assert.ok(styleCss.includes(token + ':'), 'missing light token ' + token);
    });
    assert.ok(styleCss.includes('--color-page-bg: #ffffff;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(styleCss.includes('--color-text: var(--text);'));
    assert.ok(styleCss.includes('--color-text-muted: var(--text-light);'));
    assert.ok(styleCss.includes('--color-border: var(--border);'));
    assert.ok(styleCss.includes('--color-input-bg: var(--white);'));
    assert.ok(styleCss.includes('--color-input-border: var(--border);'));
    assert.ok(styleCss.includes('--color-shadow: var(--shadow);'));
    assert.ok(styleCss.includes('--color-brand: var(--primary);'));
    assert.ok(styleCss.includes('--color-brand-dark: var(--primary-dark);'));
    assert.ok(styleCss.includes('--color-accent: var(--gold);'));
});

test('dark theme token block is scoped to html[data-theme="dark"]', () => {
    assert.ok(styleCss.includes('html[data-theme="dark"]'));
    const darkBlock = styleCss.split('html[data-theme="dark"]')[1].split('@media')[0];
    SEMANTIC_TOKENS.forEach(function (token) {
        assert.ok(darkBlock.includes(token + ':'), 'missing dark token ' + token);
    });
    assert.ok(darkBlock.includes('--color-page-bg: #160e22;'));
    assert.ok(darkBlock.includes('--color-surface: #221833;'));
    assert.ok(darkBlock.includes('--color-brand: var(--primary);'));
    assert.ok(darkBlock.includes('--color-accent: var(--gold);'));
});

test('system dark tokens live under prefers-color-scheme + data-theme=system', () => {
    assert.ok(styleCss.includes("@media (prefers-color-scheme: dark)"));
    assert.ok(styleCss.includes('html[data-theme="system"]'));
    const systemBlock = styleCss.split('@media (prefers-color-scheme: dark)')[1];
    SEMANTIC_TOKENS.forEach(function (token) {
        assert.ok(systemBlock.includes(token + ':'), 'missing system-dark token ' + token);
    });
});

test('Light Mode fallback still uses original brand paints on :root', () => {
    assert.ok(styleCss.includes('--primary: #5B2E91;'));
    assert.ok(styleCss.includes('--white: #ffffff;'));
    assert.ok(styleCss.includes('--color-surface: #ffffff;'));
    assert.ok(!/:root[\s\S]*--white:\s*var\(--color-/.test(styleCss));
});

console.log('\nPart C - shared wiring + scope');

test('shared footer loads theme.js once via versioned_asset', () => {
    const footer = read('includes/footer.php');
    assert.ok(footer.includes("versioned_asset('assets/js/theme.js')"));
    assert.strictEqual(footer.split("assets/js/theme.js").length - 1, 1);
});

test('theme.js exists and is the only theme manager', () => {
    assert.ok(fs.existsSync(path.join(ROOT, 'assets/js/theme.js')));
    const mainJs = read('assets/js/main.js');
    assert.ok(!/moonauraTheme|data-theme|prefers-color-scheme/.test(mainJs));
});

test('Phase 1 does not add theme UI controls', () => {
    const header = read('includes/header.php');
    const footer = read('includes/footer.php');
    assert.ok(!/theme-toggle|dark-mode-toggle|theme-switch/.test(header));
    assert.ok(!/theme-toggle|dark-mode-toggle|theme-switch/.test(footer));
    assert.ok(!/aria-label="Toggle theme"/.test(header));
});

test('Phase 1 does not touch checkout, payment, pixel, GA4, or admin logic', () => {
    const checkout = read('checkout.php');
    const payment = read('includes/payment-functions.php');
    const pixel = read('includes/meta-pixel-functions.php');
    const ga4 = read('assets/js/ga4.js');
    assert.ok(!/moonauraTheme|data-theme/.test(checkout));
    assert.ok(!/moonauraTheme|data-theme/.test(payment));
    assert.ok(!/moonauraTheme|data-theme/.test(pixel));
    assert.ok(!/moonauraTheme|data-theme/.test(ga4));
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
console.log('All Dark Mode Phase 1 foundation checks passed.');
