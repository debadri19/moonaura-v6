/* ===================================================================
   Dark Mode Phase 6 - Chromium hard-reload no-flash verification
   -------------------------------------------------------------------
   Delays storefront CSS so first sampled paint is the boot stylesheet
   plus html[data-theme], not the later theme.js / style.css apply.
   Run with:  node tests/theme-phase6-browser.js
=================================================================== */

'use strict';

const assert = require('assert');
const puppeteer = require('/usr/local/lib/node_modules/puppeteer-core');

const BASE = process.env.SITE_URL || 'http://127.0.0.1:8000';
const DARK_RGB = 'rgb(22, 14, 34)';
const CSS_DELAY_MS = 1200;

let passed = 0;
const failures = [];

function test(name, fn) {
    return Promise.resolve()
        .then(fn)
        .then(function () {
            passed++;
            console.log('  ok  - ' + name);
        })
        .catch(function (err) {
            failures.push({ name: name, err: err });
            console.log('  FAIL- ' + name + '\n        ' + (err && err.message));
        });
}

async function launch(scheme, viewport) {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium',
        headless: true,
        args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage']
    });
    const page = await browser.newPage();
    const vp = viewport || { width: 1280, height: 800, deviceScaleFactor: 1 };
    await page.setViewport(vp);
    if (scheme) {
        await page.emulateMediaFeatures([
            { name: 'prefers-color-scheme', value: scheme }
        ]);
    }
    page.__consoleErrors = [];
    page.on('pageerror', function (err) {
        page.__consoleErrors.push(String(err));
    });
    return { browser: browser, page: page };
}

async function prepare(page, storedMode, delayCss) {
    await page.evaluateOnNewDocument(function (mode) {
        try {
            if (window.sessionStorage.getItem('__moonaura_seeded') === '1') {
                return;
            }
            window.sessionStorage.setItem('__moonaura_seeded', '1');
            if (mode === null) {
                localStorage.removeItem('moonaura_theme');
            } else {
                localStorage.setItem('moonaura_theme', mode);
            }
        } catch (ignore) {}
    }, storedMode);

    if (!delayCss) {
        return;
    }

    await page.setRequestInterception(true);
    page.on('request', function (req) {
        const url = req.url();
        if (/\.css(\?|$)/.test(url) && url.indexOf(BASE) === 0) {
            setTimeout(function () {
                req.continue().catch(function () {});
            }, CSS_DELAY_MS);
            return;
        }
        req.continue().catch(function () {});
    });
}

function snap(page) {
    return page.evaluate(function () {
        var html = document.documentElement;
        var body = document.body;
        var htmlBg = html ? getComputedStyle(html).backgroundColor : null;
        var bodyBg = body ? getComputedStyle(body).backgroundColor : null;
        var boot = document.getElementById('moonaura-theme-boot-js');
        var manager = window.moonauraTheme;
        return {
            theme: html ? html.getAttribute('data-theme') : null,
            resolved: html ? html.getAttribute('data-theme-resolved') : null,
            htmlBg: htmlBg,
            bodyBg: bodyBg,
            hasBoot: !!boot,
            manager: manager ? manager.getMode() : null,
            managerResolved: manager ? manager.getResolvedTheme() : null,
            stored: (function () {
                try { return localStorage.getItem('moonaura_theme'); } catch (e) { return 'blocked'; }
            })(),
            ready: document.readyState
        };
    });
}

function isDark(s) {
    return s.htmlBg === DARK_RGB || s.bodyBg === DARK_RGB;
}

function isNotDark(s) {
    return s.htmlBg !== DARK_RGB && s.bodyBg !== DARK_RGB;
}

async function earlyAndSettled(page, path) {
    const nav = page.goto(BASE + path, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await new Promise(function (r) { setTimeout(r, 250); });
    const early = await snap(page);
    await nav;
    await new Promise(function (r) { setTimeout(r, CSS_DELAY_MS + 400); });
    const settled = await snap(page);
    return { early: early, settled: settled };
}

async function main() {
    console.log('\nPhase 6 Chromium no-flash verification  ' + BASE);

    await test('dark persisted: paint is dark before CSS arrives and stays dark', async function () {
        const ctx = await launch('light');
        await prepare(ctx.page, 'dark', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.hasBoot, true);
        assert.strictEqual(result.early.theme, 'dark');
        assert.strictEqual(result.early.resolved, 'dark');
        assert.ok(isDark(result.early), 'early paint ' + result.early.htmlBg + '/' + result.early.bodyBg);
        assert.strictEqual(result.early.manager, null, 'theme.js must not have applied the theme yet');
        assert.strictEqual(result.settled.theme, 'dark');
        assert.strictEqual(result.settled.manager, 'dark');
        assert.ok(isDark(result.settled), 'settled paint ' + result.settled.bodyBg);
        assert.deepStrictEqual(ctx.page.__consoleErrors, []);
    });

    await test('light persisted: no dark flash before or after CSS', async function () {
        const ctx = await launch('dark');
        await prepare(ctx.page, 'light', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, 'light');
        assert.strictEqual(result.early.resolved, 'light');
        assert.ok(isNotDark(result.early), 'early dark leak ' + result.early.htmlBg + '/' + result.early.bodyBg);
        assert.strictEqual(result.settled.manager, 'light');
        assert.ok(isNotDark(result.settled), 'settled dark leak ' + result.settled.bodyBg);
    });

    await test('system + OS dark starts dark and keeps mode=system', async function () {
        const ctx = await launch('dark');
        await prepare(ctx.page, 'system', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, 'system');
        assert.strictEqual(result.early.resolved, 'dark');
        assert.ok(isDark(result.early), 'system/dark early ' + result.early.htmlBg + '/' + result.early.bodyBg);
        assert.strictEqual(result.settled.manager, 'system');
        assert.strictEqual(result.settled.managerResolved, 'dark');
    });

    await test('system + OS light starts light and keeps mode=system', async function () {
        const ctx = await launch('light');
        await prepare(ctx.page, 'system', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, 'system');
        assert.strictEqual(result.early.resolved, 'light');
        assert.ok(isNotDark(result.early), 'system/light early ' + result.early.htmlBg);
        assert.strictEqual(result.settled.manager, 'system');
        assert.strictEqual(result.settled.managerResolved, 'light');
    });

    await test('missing preference keeps Light default', async function () {
        const ctx = await launch('dark');
        await prepare(ctx.page, null, true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, null);
        assert.ok(isNotDark(result.early), 'missing pref early ' + result.early.htmlBg);
        assert.strictEqual(result.settled.manager, 'light');
        assert.ok(isNotDark(result.settled));
    });

    await test('invalid stored value keeps Light default', async function () {
        const ctx = await launch('light');
        await prepare(ctx.page, 'sepia', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, null);
        assert.ok(isNotDark(result.early));
        assert.strictEqual(result.settled.manager, 'light');
    });

    const navPages = [
        ['/index.php', 'Home'],
        ['/shop.php', 'Shop'],
        ['/cart.php', 'Cart'],
        ['/checkout.php', 'Checkout'],
        ['/account/login.php', 'Login'],
        ['/support.php', 'Support']
    ];

    await test('dark is already applied on each storefront navigation', async function () {
        const ctx = await launch('light');
        await prepare(ctx.page, 'dark', false);
        for (let i = 0; i < navPages.length; i++) {
            await ctx.page.goto(BASE + navPages[i][0], { waitUntil: 'networkidle0', timeout: 30000 });
            const after = await snap(ctx.page);
            assert.strictEqual(after.theme, 'dark', navPages[i][1] + ' data-theme');
            assert.strictEqual(after.manager, 'dark', navPages[i][1] + ' manager');
            assert.ok(isDark(after), navPages[i][1] + ' bg ' + after.bodyBg + '/' + after.htmlBg);
        }
        await ctx.browser.close();
    });

    await test('login toggle still switches, persists, and survives hard reload', async function () {
        const ctx = await launch('light');
        await prepare(ctx.page, 'light', false);
        await ctx.page.goto(BASE + '/account/login.php', { waitUntil: 'networkidle0', timeout: 30000 });
        await ctx.page.click('[data-theme-mode="dark"]');
        let after = await snap(ctx.page);
        assert.strictEqual(after.manager, 'dark');
        assert.strictEqual(after.stored, 'dark');
        assert.ok(isDark(after), 'login dark bg ' + after.bodyBg);
        const pressed = await ctx.page.$eval('[data-theme-mode="dark"]', function (el) {
            return el.getAttribute('aria-pressed');
        });
        assert.strictEqual(pressed, 'true');

        await ctx.page.reload({ waitUntil: 'networkidle0', timeout: 30000 });
        after = await snap(ctx.page);
        assert.strictEqual(after.theme, 'dark');
        assert.strictEqual(after.manager, 'dark');
        assert.ok(isDark(after), 'login reload bg ' + after.bodyBg);
        await ctx.browser.close();
    });

    await test('mobile viewport dark hard reload is dark before CSS', async function () {
        const ctx = await launch('light', { width: 390, height: 844, deviceScaleFactor: 2 });
        await prepare(ctx.page, 'dark', true);
        const result = await earlyAndSettled(ctx.page, '/index.php');
        await ctx.browser.close();
        assert.strictEqual(result.early.theme, 'dark');
        assert.ok(isDark(result.early), 'mobile early ' + result.early.htmlBg + '/' + result.early.bodyBg);
        assert.strictEqual(result.settled.manager, 'dark');
        assert.ok(isDark(result.settled));
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
    console.log('Chromium no-flash verification passed.');
}

main().catch(function (err) {
    console.error(err);
    process.exit(1);
});
