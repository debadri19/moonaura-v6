'use strict';

const assert = require('assert');
const puppeteer = require('/usr/local/lib/node_modules/puppeteer-core');

const BASE = process.env.SITE_URL || 'http://127.0.0.1:8000';
const LOGIN = BASE + '/account/login.php';

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
    await page.setViewport(viewport);
    page.__consoleErrors = [];
    page.on('pageerror', function (err) {
        page.__consoleErrors.push(String(err));
    });
    return { browser: browser, page: page };
}

async function layout(page) {
    return page.evaluate(function () {
        const h1 = document.querySelector('.account-login-box h1');
        const sub = document.querySelector('.account-auth-subtitle');
        const form = document.querySelector('.account-login-box form');
        const btn = document.querySelector('.login-theme-menu-btn');
        const card = document.querySelector('.account-login-box');
        const hb = h1.getBoundingClientRect();
        const sb = sub.getBoundingClientRect();
        const fb = form.getBoundingClientRect();
        const bb = btn.getBoundingClientRect();
        const cb = card.getBoundingClientRect();
        return {
            heading: { x: Math.round(hb.x), y: Math.round(hb.y), w: Math.round(hb.width), h: Math.round(hb.height), text: h1.textContent.trim() },
            sub: { x: Math.round(sb.x), y: Math.round(sb.y), gap: Math.round(sb.y - hb.bottom), text: sub.textContent.trim() },
            form: { y: Math.round(fb.y) },
            btn: { x: Math.round(bb.x), y: Math.round(bb.y), w: Math.round(bb.width), h: Math.round(bb.height) },
            overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            cardOverflow: Math.round(bb.right) > Math.round(cb.right) + 1 || Math.round(bb.left) < Math.round(cb.left) - 1,
            headingFont: getComputedStyle(h1).fontFamily
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
        await page.goto(LOGIN, { waitUntil: 'networkidle0', timeout: 20000 });

        await test('login page loads normally', async function () {
            const title = await page.title();
            assert.ok(/Log In/.test(title));
            const h1 = await page.$eval('.account-login-box h1', function (el) { return el.textContent.trim(); });
            const sub = await page.$eval('.account-auth-subtitle', function (el) { return el.textContent.trim(); });
            assert.strictEqual(h1, 'Welcome to MoonAura');
            assert.strictEqual(sub, 'Sign in to access your orders and account.');
            assert.ok(await page.$('form[action="login.php"]'));
            assert.ok(await page.$('#password'));
            assert.ok(await page.$('.pw-toggle'));
        });

        await test('round theme button is visible and segmented control is gone', async function () {
            const visible = await page.$eval('.login-theme-menu-btn', function (el) {
                const s = getComputedStyle(el);
                return s.display !== 'none' && s.visibility !== 'hidden' && el.offsetWidth > 0;
            });
            assert.ok(visible);
            const radius = await page.$eval('.login-theme-menu-btn', function (el) {
                return getComputedStyle(el).borderRadius;
            });
            assert.ok(radius === '50%' || parseFloat(radius) >= 16);
            const groupGone = await page.$('.theme-toggle[role="group"]');
            assert.strictEqual(groupGone, null);
            const panelHidden = await page.$eval('#login-theme-menu', function (el) {
                return el.hasAttribute('hidden') || getComputedStyle(el).display === 'none';
            });
            assert.ok(panelHidden);
        });

        await test('clicking the round button opens Light/Dark/System', async function () {
            await page.click('.login-theme-menu-btn');
            const open = await page.$eval('.login-theme-menu', function (el) {
                return el.classList.contains('is-open') && el.querySelector('.login-theme-menu-btn').getAttribute('aria-expanded') === 'true';
            });
            assert.ok(open);
            const labels = await page.$$eval('#login-theme-menu .theme-toggle-btn', function (els) {
                return els.map(function (el) { return el.textContent.replace(/\s+/g, ' ').trim(); });
            });
            assert.deepStrictEqual(labels, ['Light', 'Dark', 'System']);
        });

        await test('clicking Light/Dark/System works and selected state is visible', async function () {
            await page.click('[data-theme-mode="dark"]');
            await page.waitForFunction(function () {
                return document.documentElement.getAttribute('data-theme') === 'dark'
                    && localStorage.getItem('moonaura_theme') === 'dark';
            });
            let pressed = await page.$eval('[data-theme-mode="dark"]', function (el) { return el.getAttribute('aria-pressed'); });
            assert.strictEqual(pressed, 'true');
            let icon = await page.$eval('.login-theme-menu-btn i', function (el) { return el.className; });
            assert.ok(icon.indexOf('fa-moon') !== -1);
            const closed = await page.$eval('.login-theme-menu', function (el) { return !el.classList.contains('is-open'); });
            assert.ok(closed);

            await page.click('.login-theme-menu-btn');
            await page.click('[data-theme-mode="light"]');
            await page.waitForFunction(function () {
                return document.documentElement.getAttribute('data-theme') === 'light'
                    && localStorage.getItem('moonaura_theme') === 'light';
            });
            pressed = await page.$eval('[data-theme-mode="light"]', function (el) { return el.getAttribute('aria-pressed'); });
            assert.strictEqual(pressed, 'true');
            icon = await page.$eval('.login-theme-menu-btn i', function (el) { return el.className; });
            assert.ok(icon.indexOf('fa-sun') !== -1);

            await page.click('.login-theme-menu-btn');
            await page.click('[data-theme-mode="system"]');
            await page.waitForFunction(function () {
                return document.documentElement.getAttribute('data-theme') === 'system'
                    && localStorage.getItem('moonaura_theme') === 'system';
            });
            pressed = await page.$eval('[data-theme-mode="system"]', function (el) { return el.getAttribute('aria-pressed'); });
            assert.strictEqual(pressed, 'true');
            icon = await page.$eval('.login-theme-menu-btn i', function (el) { return el.className; });
            assert.ok(icon.indexOf('fa-circle-half-stroke') !== -1);
        });

        await test('outside click and Escape close the dropdown', async function () {
            await page.click('.login-theme-menu-btn');
            assert.ok(await page.$eval('.login-theme-menu', function (el) { return el.classList.contains('is-open'); }));
            await page.click('.account-auth-subtitle');
            assert.ok(await page.$eval('.login-theme-menu', function (el) { return !el.classList.contains('is-open'); }));

            await page.click('.login-theme-menu-btn');
            assert.ok(await page.$eval('.login-theme-menu', function (el) { return el.classList.contains('is-open'); }));
            await page.keyboard.press('Escape');
            assert.ok(await page.$eval('.login-theme-menu', function (el) { return !el.classList.contains('is-open'); }));
        });

        await test('theme persistence remains intact after reload', async function () {
            await page.click('.login-theme-menu-btn');
            await page.click('[data-theme-mode="dark"]');
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

        await test('password eye toggle still works and login form is intact', async function () {
            const before = await page.$eval('#password', function (el) { return el.type; });
            assert.strictEqual(before, 'password');
            await page.click('.pw-toggle');
            const after = await page.$eval('#password', function (el) { return el.type; });
            assert.strictEqual(after, 'text');
            await page.click('.pw-toggle');
            const hidden = await page.$eval('#password', function (el) { return el.type; });
            assert.strictEqual(hidden, 'password');
            const action = await page.$eval('.account-login-box form', function (el) { return el.getAttribute('action'); });
            assert.strictEqual(action, 'login.php');
            assert.ok(await page.$('.account-login-box input[name="identifier"]'));
            assert.ok(await page.$('.account-login-box button[type="submit"]'));
        });

        await test('no console errors', async function () {
            assert.deepStrictEqual(page.__consoleErrors, []);
        });

        const reference = await layout(page);
        await test('heading copy and Cormorant hierarchy remain', async function () {
            assert.strictEqual(reference.heading.text, 'Welcome to MoonAura');
            assert.strictEqual(reference.sub.text, 'Sign in to access your orders and account.');
            assert.ok(/Cormorant/i.test(reference.headingFont));
            assert.strictEqual(reference.sub.gap, 6);
        });

        for (let i = 0; i < viewports.length; i++) {
            const vp = viewports[i];
            await test('viewport ' + vp.width + 'px: no overflow, control in card, heading unmoved in flow', async function () {
                await page.setViewport(vp);
                await new Promise(function (resolve) { setTimeout(resolve, 80); });
                const info = await layout(page);
                assert.strictEqual(info.overflow, false, 'horizontal overflow at ' + vp.width);
                assert.strictEqual(info.cardOverflow, false, 'button overflowed card at ' + vp.width);
                assert.ok(info.btn.w >= 36 && info.btn.h >= 36);
                assert.strictEqual(info.heading.text, 'Welcome to MoonAura');
                assert.ok(/Cormorant/i.test(info.headingFont));
                assert.strictEqual(info.sub.gap, 6);
                assert.ok(info.btn.y < info.heading.y);
                assert.ok(info.form.y > info.sub.y);
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
