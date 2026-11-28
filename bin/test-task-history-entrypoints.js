// ورود واقعی از فهرست، پیشخوان، بورد، تقویم و لینک تسک؛ Service Worker روشن است.
const { chromium } = require('playwright'), { execFileSync } = require('child_process'), assert = require('assert'), path = require('path'), fs = require('fs');
const base = process.env.LARAVEL_TEST_URL || 'http://127.0.0.1:8082', php = process.env.PHP_BIN || 'php';
const driver = path.join(__dirname, '../tests/search/local-driver.php');
const run = data => JSON.parse(execFileSync(php, [driver, JSON.stringify(data)]).toString());
const fixture = run({ action: 'fixture' }), version = fs.readFileSync(path.join(__dirname, '../VERSION'), 'utf8').trim();
assert(fixture.testDatabase.endsWith('_test'));
(async () => {
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
    const context = await browser.newContext({ serviceWorkers: 'allow', viewport: { width: 1365, height: 950 } }), page = await context.newPage(), errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/login'); await page.locator('[name=mobile]').first().fill(fixture.users[0].mobile); await page.locator('[name=password]').first().fill(fixture.users[0].password);
    await page.locator('form[action$="/login"] button[type=submit]').click(); await page.waitForURL('**/dashboard');
    await page.evaluate(async () => { await navigator.serviceWorker.register(document.querySelector('meta[name=sw-url]').content); await navigator.serviceWorker.ready; });
    await page.reload(); await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    const cached = await page.evaluate(async () => {
      const src = document.querySelector('script[src*="modules/tasks.js"]').src, previous = new URL(src); previous.searchParams.delete('release');
      const name = (await caches.keys()).find(name => name.startsWith('tk-static-')); if (!name) throw new Error('Service Worker cache missing');
      await (await caches.open(name)).put(previous.href, new Response('window.oldTaskModuleWasUsed=true;', { headers: { 'Content-Type': 'application/javascript' } }));
      return { src, previous: previous.href };
    });
    assert.equal(new URL(cached.src).searchParams.get('release'), version);
    for (const [name, url, direct] of [
      ['task list', '/tasks', false], ['dashboard', '/dashboard', false],
      ['project board', '/board?project=' + fixture.project, false], ['calendar', '/calendar', false],
      ['direct task link', '/tasks?task=' + fixture.share, true],
    ]) {
      await page.goto(base + url);
      if (name === 'calendar') await page.locator('#cal-grid .cal-day.today').click();
      if (!direct) await page.locator('[data-open-task="' + fixture.task + '"]').first().click();
      const button = page.locator('#detail-body .td-history-bar [data-detail-history]'); await button.waitFor();
      assert.equal(await button.innerText(), 'تاریخچه تغییرات'); assert.equal(await page.locator('#m-detail [data-detail-history]').count(), 1);
      await button.click(); await page.locator('#m-history.open .hist-item').first().waitFor();
      assert((await page.locator('#history-body').innerText()).includes('عنوان قبلی سنجه'));
      assert.equal(await page.evaluate(() => window.oldTaskModuleWasUsed), undefined);
      console.log('PASS task viewing page history via ' + name + ' with Service Worker active');
    }
    await page.locator('#m-history [data-close]').click(); await page.locator('#m-detail [data-close]').first().click();
    let navigations = 0; page.on('request', request => { if (request.isNavigationRequest() && request.frame() === page.mainFrame()) navigations++; });
    await page.locator('#sidebar a[href$="/dashboard"]').first().click(); await page.waitForURL('**/dashboard');
    await page.locator('[data-open-task="' + fixture.task + '"]').first().click(); await page.locator('#detail-body [data-detail-history]').click();
    await page.locator('#m-history.open .hist-item').first().waitFor(); assert.equal(navigations, 0);
    console.log('PASS task viewing history also opens after real sidebar soft navigation');
    assert.deepEqual(errors, []); console.log('PASS release-versioned task module bypasses old cached URL without browser errors');
  } finally { await browser?.close(); run({ action: 'cleanup', users: fixture.users }); }
})().catch(error => { console.error(error); process.exitCode = 1; });
