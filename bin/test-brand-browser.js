// نمایش واقعی فایل‌های SVG و ثبات برند با تغییر هر شش تم.
const { chromium } = require('playwright'), { execFileSync } = require('child_process'), assert = require('assert'), path = require('path'), crypto = require('crypto');
const base = process.env.LARAVEL_TEST_URL || 'http://127.0.0.1:8082', php = process.env.PHP_BIN || 'php', driver = path.join(__dirname, '../tests/brand/local-driver.php');
const run = data => JSON.parse(execFileSync(php, [driver, JSON.stringify(data)]).toString()), fixture = run({ action: 'fixture' }); assert(fixture.testDatabase.endsWith('_test'));
(async () => {
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1365, height: 950 } }), page = await context.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/login');
    assert.equal(await page.locator('html').getAttribute('data-theme'), 'violet-dark');
    await page.waitForFunction(() => document.querySelector('.ab-head img')?.naturalWidth === 520);
    const initialFav = await page.locator('link[rel=icon]').getAttribute('href'), initialApple = await page.locator('link[rel=apple-touch-icon]').getAttribute('href');
    assert(initialFav.includes('karlix-favicon.svg')); assert(initialApple.includes('apple-touch-icon.png?brand='));
    await page.screenshot({ path: '/tmp/kx-brand-login-desktop.png' });
    await page.locator('[data-theme-toggle]').click(); assert.equal(await page.locator('html').getAttribute('data-theme'), 'violet-light');
    assert.equal(await page.locator('link[rel=icon]').getAttribute('href'), initialFav);
    console.log('PASS guest default is violet-dark; supplied Persian logo and fixed favicon render; toggle preserves brand');
    const login = async (target, user) => {
      await target.locator('[name=mobile]').first().fill(user.mobile); await target.locator('[name=password]').first().fill(user.password);
      await target.locator('form[action$="/login"] button[type=submit]').click(); await target.waitForURL(url => !url.pathname.endsWith('/login'));
    };
    await login(page, fixture.users[0]); await page.goto(base + '/profile#appearance');
    await page.waitForFunction(() => document.querySelector('#sidebar .brand-wordmark')?.naturalWidth === 520);
    const image = page.locator('#sidebar .brand-wordmark'), source = await image.getAttribute('src');
    const digest = crypto.createHash('sha256').update(await (await page.request.get(new URL(source, base).href)).body()).digest('hex');
    let iconUrls;
    for (const theme of ['violet-dark', 'violet-light', 'green-dark', 'green-light', 'momentum-light', 'momentum-dark']) {
      const response = page.waitForResponse(response => response.url().endsWith('/profile/appearance') && response.request().method() === 'POST');
      await page.locator('.theme-opt').filter({ has: page.locator('input[value="' + theme + '"]') }).click();
      assert.equal((await (await response).json()).status, 'success');
      assert.equal(await page.locator('html').getAttribute('data-theme'), theme);
      assert.equal(await image.getAttribute('src'), source); assert.equal(await image.evaluate(el => getComputedStyle(el).filter), 'none');
      assert.equal(await page.locator('link[rel=icon]').getAttribute('href'), initialFav); assert.equal(await page.locator('link[rel=apple-touch-icon]').getAttribute('href'), initialApple);
      const manifest = await (await page.request.get(base + '/manifest')).json();
      if (!iconUrls) iconUrls = manifest.icons.map(icon => icon.src); else assert.deepEqual(manifest.icons.map(icon => icon.src), iconUrls);
      for (const icon of manifest.icons) { const response = await page.request.get(new URL(icon.src, base).href); assert.equal(response.status(), 200); assert((await response.body()).length > 500); }
    }
    assert.equal(crypto.createHash('sha256').update(await (await page.request.get(new URL(source, base).href)).body()).digest('hex'), digest);
    console.log('PASS all six real theme changes preserve logo bytes, favicon, Apple icon and all PWA icon URLs');
    await page.reload(); assert.equal(await page.locator('html').getAttribute('data-theme'), 'momentum-dark');
    assert.equal(await page.locator('#sidebar .brand-wordmark').getAttribute('src'), source);
    await page.locator('.sb-rail-btn').click(); await image.waitFor({ state: 'hidden' });
    await page.locator('.sb-rail-btn').click(); await image.waitFor({ state: 'visible' });
    console.log('PASS saved theme survives reload and sidebar collapse/expand preserves the same brand');
    for (const theme of ['violet-dark', 'violet-light', 'green-dark', 'green-light']) {
      await context.addCookies([{ name: 'kx_theme', value: theme, url: base }]); await page.goto(base + '/dashboard');
      await page.waitForFunction(() => document.querySelector('#sidebar .brand-wordmark')?.naturalWidth === 520);
      await page.screenshot({ path: '/tmp/kx-brand-dashboard-' + theme + '.png' });
    }
    await page.setViewportSize({ width: 390, height: 844 }); await page.locator('[data-sidebar]').click();
    await page.waitForFunction(() => { const r = document.querySelector('#sidebar').getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1; });
    assert(await page.locator('#sidebar .brand-wordmark').isVisible()); assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    assert(await image.evaluate(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; }));
    await page.screenshot({ path: '/tmp/kx-brand-sidebar-mobile.png' });
    const guest = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } }), mobile = await guest.newPage();
    await mobile.goto(base + '/login'); await mobile.waitForFunction(() => document.querySelector('.auth-mini img')?.naturalWidth === 520);
    assert.equal(await mobile.locator('html').getAttribute('data-theme'), 'violet-dark'); await mobile.screenshot({ path: '/tmp/kx-brand-login-mobile.png' });
    console.log('PASS Persian branding fits dashboard, mobile sidebar and mobile login across light/dark palettes');
    await page.setViewportSize({ width: 1365, height: 950 });
    for (const url of ['/docs', '/docs/swagger']) {
      await page.goto(base + url); await page.waitForFunction(() => [...document.querySelectorAll('img.brand-wordmark')].some(img => img.naturalWidth === 520 && img.src.includes('karlix-en.svg')));
      await page.screenshot({ path: '/tmp/kx-brand-' + (url.includes('swagger') ? 'swagger' : 'docs') + '.png' });
    }
    console.log('PASS supplied English wordmark renders in documentation and Swagger');
    const adminContext = await browser.newContext({ serviceWorkers: 'block' }), admin = await adminContext.newPage();
    await admin.goto(base + '/login'); await login(admin, fixture.users[1]); await admin.goto(base + '/admin');
    await admin.waitForFunction(() => document.querySelector('#sidebar .brand-wordmark')?.naturalWidth === 520);
    assert((await admin.locator('#sidebar .brand-wordmark').getAttribute('src')).includes('karlix-fa.svg'));
    assert.equal(await admin.locator('link[rel=icon]').getAttribute('href'), initialFav);
    console.log('PASS legacy administrator panel uses the same fixed supplied brand');
    const sw = await (await page.request.get(base + '/service-worker')).text();
    assert(!sw.includes('__BRAND__')); assert(sw.includes("const BRAND = '")); assert(sw.includes('brandIcon(\'icon-192.png\')'));
    await page.goto(base + '/offline'); assert((await page.locator('.logo').innerHTML()).includes('M14 8')); assert.equal(await page.locator('link[rel=icon]').getAttribute('href'), initialFav);
    const offlineContext = await browser.newContext({ serviceWorkers: 'allow' }), offline = await offlineContext.newPage();
    await offline.goto(base + '/offline');
    await offline.evaluate(async () => { await navigator.serviceWorker.register('/service-worker'); await navigator.serviceWorker.ready; });
    await offline.reload(); await offline.waitForFunction(() => !!navigator.serviceWorker.controller);
    const offlineFav = await offline.locator('link[rel=icon]').getAttribute('href');
    assert(await offline.evaluate(async url => !!(await caches.match(url)), new URL(offlineFav, base).href));
    await offlineContext.setOffline(true); await offline.goto(base + '/offline');
    assert((await offline.locator('.logo').innerHTML()).includes('M14 8')); await offline.screenshot({ path: '/tmp/kx-brand-offline.png' });
    assert.deepEqual(errors, []); console.log('PASS offline and Push service worker use the fixed versioned logo without JavaScript errors');
  } finally { await browser?.close(); run({ action: 'cleanup', users: fixture.users }); }
})().catch(error => { console.error(error); process.exitCode = 1; });
