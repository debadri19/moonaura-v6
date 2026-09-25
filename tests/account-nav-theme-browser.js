'use strict';

const assert = require('assert');
const puppeteer = require('/usr/local/lib/node_modules/puppeteer-core');

const BASE = process.env.SITE_URL || 'http://127.0.0.1:8000';
const EMAIL = process.env.ACCOUNT_EMAIL || 'theme-phase5-live@example.test';
const PASSWORD = process.env.ACCOUNT_PASSWORD || 'Phase5Live!ok';

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

async function launch(viewport) {
    const browser = await puppeteer.launch({
        executablePath: '/usr/bin/chromium',
        headless: true,
        args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage']
    });
    const page = await browser.newPage();
    await page.setViewport(viewport || { width: 1280, height: 800 });
    page.__consoleErrors = [];
    page.on('pageerror', function (err) {
        page.__consoleErrors.push(String(err));
    });
    return { browser: browser, page: page };
}

async function login(page) {
    await page.goto(BASE + '/account/login.php', { waitUntil: 'networkidle0', timeout: 20000 });
    await page.type('#identifier', EMAIL, { delay: 10 });
    await page.type('#password', PASSWORD, { delay: 10 });
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 20000 }),
        page.click('.account-login-box button[type="submit"]')
    ]);
}

async function waitForMenuOpen(page, open) {
    await page.waitForFunction(function (wantOpen) {
        const menu = document.querySelector('.account-nav-menu');
        const panel = document.querySelector('.account-nav-panel');
        if (!menu || !panel) return false;
        const isOpen = menu.classList.contains('is-open');
        const opacity = parseFloat(getComputedStyle(panel).opacity);
        return wantOpen ? (isOpen && opacity > 0.9) : (!isOpen && opacity < 0.1);
    }, { timeout: 5000 }, open);
}

async function waitForThemeOpen(page, open) {
    await page.waitForFunction(function (wantOpen) {
        const theme = document.querySelector('.account-nav-theme');
        const panel = document.querySelector('.account-nav-theme-panel');
        if (!theme || !panel) return false;
        const isOpen = theme.classList.contains('is-open');
        const opacity = parseFloat(getComputedStyle(panel).opacity);
        return wantOpen ? (isOpen && opacity > 0.9) : (!isOpen && opacity < 0.1);
    }, { timeout: 5000 }, open);
}

async function ensureMenuOpen(page) {
    const isOpen = await page.$eval('.account-nav-menu', function (el) {
        return el.classList.contains('is-open');
    });
    if (!isOpen) {
        await page.click('.account-nav-toggle');
    }
    await waitForMenuOpen(page, true);
}

async function ensureMenuClosed(page) {
    const isOpen = await page.$eval('.account-nav-menu', function (el) {
        return el.classList.contains('is-open');
    });
    if (isOpen) {
        await page.click('.account-nav-toggle');
    }
    await waitForMenuOpen(page, false);
}

async function measure(page) {
    return page.evaluate(function () {
        const pill = document.querySelector('.account-nav-toggle');
        const menu = document.querySelector('.account-nav-menu');
        const panel = document.querySelector('.account-nav-panel');
        const header = document.querySelector('.site-header');
        const h1 = document.querySelector('.account-page h1');
        const pb = pill.getBoundingClientRect();
        const mb = menu.getBoundingClientRect();
        const pan = panel.getBoundingClientRect();
        const hb = header ? header.getBoundingClientRect() : { height: 0 };
        const styles = getComputedStyle(panel);
        return {
            pill: { x: Math.round(pb.x), y: Math.round(pb.y), w: Math.round(pb.width), h: Math.round(pb.height) },
            menuH: Math.round(mb.height),
            panel: {
                opacity: styles.opacity,
                visibility: styles.visibility,
                pointer: styles.pointerEvents,
                y: Math.round(pan.y)
            },
            headerH: Math.round(hb.height),
            headingY: h1 ? Math.round(h1.getBoundingClientRect().y) : 0,
            overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            open: menu.classList.contains('is-open')
        };
    });
}

(async function () {
    const viewports = [
        { width: 1440, height: 900 },
        { width: 1280, height: 800 },
        { width: 1024, height: 768 },
        { width: 768, height: 1024 },
        { width: 576, height: 800 },
        { width: 390, height: 844 }
    ];

    const { browser, page } = await launch(viewports[1]);
    try {
        await login(page);

        await test('account page loads with pill menu', async function () {
            assert.ok(page.url().indexOf('/account/') !== -1);
            assert.ok(await page.$('.account-nav-toggle'));
            assert.ok(await page.$('.account-nav-theme-btn'));
            const closed = await page.$eval('.account-nav-menu', function (el) {
                return !el.classList.contains('is-open') && !el.open;
            });
            assert.ok(closed);
        });

        const before = await measure(page);

        await test('closed panel is visually hidden and out of flow', async function () {
            assert.strictEqual(before.panel.opacity, '0');
            assert.ok(before.panel.visibility === 'hidden');
            assert.strictEqual(before.pill.h, 44);
            assert.ok(before.menuH <= 50);
        });

        await test('opening is smooth with no one-frame full opacity flash', async function () {
            const samples = [];
            await page.evaluate(function () {
                window.__accNavSamples = [];
                const panel = document.querySelector('.account-nav-panel');
                function tick() {
                    const s = getComputedStyle(panel);
                    window.__accNavSamples.push(parseFloat(s.opacity));
                    if (window.__accNavSamples.length < 18) {
                        requestAnimationFrame(tick);
                    }
                }
                requestAnimationFrame(tick);
            });
            await page.click('.account-nav-toggle');
            await waitForMenuOpen(page, true);
            const samplesOut = await page.evaluate(function () { return window.__accNavSamples || []; });
            samples.push.apply(samples, samplesOut);
            const firstVisible = samples.findIndex(function (v) { return v > 0.02; });
            if (firstVisible !== -1 && firstVisible + 1 < samples.length) {
                assert.ok(samples[firstVisible] < 0.98, 'opened at full opacity immediately: ' + samples.join(','));
            }
            const after = await measure(page);
            assert.strictEqual(after.pill.x, before.pill.x);
            assert.strictEqual(after.pill.y, before.pill.y);
            assert.strictEqual(after.pill.w, before.pill.w);
            assert.strictEqual(after.pill.h, before.pill.h);
            assert.strictEqual(after.headerH, before.headerH);
            assert.strictEqual(after.headingY, before.headingY);
            assert.ok(after.open);
        });

        await test('Theme option opens Light/Dark/System and persistence works', async function () {
            await ensureMenuOpen(page);
            await page.click('.account-nav-theme-btn');
            await waitForThemeOpen(page, true);
            const labels = await page.$$eval('.account-nav-theme-panel [data-theme-mode]', function (els) {
                return els.map(function (el) { return el.textContent.replace(/\s+/g, ' ').trim(); });
            });
            assert.deepStrictEqual(labels, ['Light', 'Dark', 'System']);

            await page.click('.account-nav-theme-panel [data-theme-mode="dark"]');
            await page.waitForFunction(function () {
                return document.documentElement.getAttribute('data-theme') === 'dark'
                    && localStorage.getItem('moonaura_theme') === 'dark';
            });
            let pressed = await page.$eval('.account-nav-theme-panel [data-theme-mode="dark"]', function (el) {
                return el.getAttribute('aria-pressed');
            });
            assert.strictEqual(pressed, 'true');

            await page.click('.account-nav-theme-panel [data-theme-mode="light"]');
            await page.waitForFunction(function () {
                return localStorage.getItem('moonaura_theme') === 'light';
            });
            await page.click('.account-nav-theme-panel [data-theme-mode="system"]');
            await page.waitForFunction(function () {
                return localStorage.getItem('moonaura_theme') === 'system';
            });
            pressed = await page.$eval('.account-nav-theme-panel [data-theme-mode="system"]', function (el) {
                return el.getAttribute('aria-pressed');
            });
            assert.strictEqual(pressed, 'true');
        });

        await test('outside click and Escape close the menu', async function () {
            await ensureMenuOpen(page);
            await page.click('.account-page h1');
            await waitForMenuOpen(page, false);

            await page.click('.account-nav-toggle');
            await waitForMenuOpen(page, true);
            await page.keyboard.press('Escape');
            await waitForMenuOpen(page, false);
        });

        await test('account links remain present and logout still exists', async function () {
            await ensureMenuOpen(page);
            const hrefs = await page.$$eval('.account-nav-panel a', function (els) {
                return els.map(function (el) { return el.getAttribute('href'); });
            });
            assert.ok(hrefs.indexOf('dashboard.php') !== -1);
            assert.ok(hrefs.indexOf('orders.php') !== -1);
            assert.ok(hrefs.indexOf('addresses.php') !== -1);
            assert.ok(hrefs.indexOf('profile.php') !== -1);
            assert.ok(hrefs.indexOf('change-password.php') !== -1);
            assert.ok(hrefs.indexOf('logout.php') !== -1);
            await ensureMenuClosed(page);
        });

        await test('theme persists after refresh', async function () {
            await ensureMenuOpen(page);
            await page.click('.account-nav-theme-btn');
            await waitForThemeOpen(page, true);
            await page.click('.account-nav-theme-panel [data-theme-mode="dark"]');
            await page.waitForFunction(function () {
                return localStorage.getItem('moonaura_theme') === 'dark';
            });
            await page.reload({ waitUntil: 'networkidle0' });
            const mode = await page.evaluate(function () {
                return {
                    stored: localStorage.getItem('moonaura_theme'),
                    attr: document.documentElement.getAttribute('data-theme')
                };
            });
            assert.strictEqual(mode.stored, 'dark');
            assert.strictEqual(mode.attr, 'dark');
        });

        await test('no console errors', async function () {
            assert.deepStrictEqual(page.__consoleErrors, []);
        });

        for (let i = 0; i < viewports.length; i++) {
            const vp = viewports[i];
            await test('viewport ' + vp.width + 'px: no overflow, pill unmoved, theme usable', async function () {
                await page.setViewport(vp);
                await new Promise(function (resolve) { setTimeout(resolve, 80); });
                await ensureMenuClosed(page);
                const closed = await measure(page);
                await page.click('.account-nav-toggle');
                await waitForMenuOpen(page, true);
                await page.click('.account-nav-theme-btn');
                await waitForThemeOpen(page, true);
                const open = await measure(page);
                const themeBox = await page.$eval('.account-nav-theme-panel', function (el) {
                    const r = el.getBoundingClientRect();
                    return {
                        left: r.left,
                        right: r.right,
                        opacity: getComputedStyle(el).opacity,
                        overflow: r.right > window.innerWidth + 2 || r.left < -2
                    };
                });
                assert.strictEqual(open.overflow, false, 'page overflow at ' + vp.width);
                assert.strictEqual(themeBox.overflow, false, 'theme panel clipped at ' + vp.width);
                assert.ok(parseFloat(themeBox.opacity) > 0.9);
                assert.strictEqual(open.pill.x, closed.pill.x);
                assert.strictEqual(open.pill.y, closed.pill.y);
                assert.strictEqual(open.headerH, closed.headerH);
                await page.keyboard.press('Escape');
                await waitForThemeOpen(page, false);
                await page.keyboard.press('Escape');
                await waitForMenuOpen(page, false);
            });
        }
    } finally {
        await browser.close();
    }

    console.log('\nPassed: ' + passed + '   Failed: ' + failures.length);
    if (failures.length) {
        failures.forEach(function (item) {
            console.error('  - ' + item.name + ': ' + item.err.message);
        });
        process.exit(1);
    }
})().catch(function (err) {
    console.error(err);
    process.exit(1);
});
