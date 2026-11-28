// ذخیره واقعی در دیتابیس، ترتیب درخواست‌ها و شکست شبکه؛ بدون سرویس بیرونی.
const { chromium } = require('playwright'), { execFileSync } = require('child_process'), assert = require('assert'), path = require('path');
const base = process.env.LARAVEL_TEST_URL || 'http://127.0.0.1:8082';
const php = process.env.PHP_BIN || 'php', driver = path.join(__dirname, '../tests/reminders/local-driver.php');
const fixture = data => JSON.parse(execFileSync(php, [driver, JSON.stringify(data)]).toString());
const f = fixture({ action: 'fixture', extra: true }); assert(f.testDatabase.endsWith('_test'));
(async () => {
  let browser;
  try {
    browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
    const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1365, height: 1000 } });
    const p = await context.newPage(), errors = [];
    p.on('pageerror', e => errors.push(e.message));
    await p.goto(base + '/login'); await p.locator('[name=mobile]').first().fill(f.users[0].mobile); await p.locator('[name=password]').first().fill(f.users[0].password);
    await p.locator('form[action$="/login"] button[type=submit]').click(); await p.waitForURL('**/dashboard');
    await p.goto(base + '/tasks?project=' + f.project); await p.waitForFunction(() => !!TK.tasks);
    await p.evaluate(() => { TK.notify = async () => {}; });
    let documents = 0; p.on('request', r => { if (r.isNavigationRequest() && r.frame() === p.mainFrame()) documents++; });
    const task = async () => (await (await p.request.get(base + '/tasks/' + f.task)).json()).task;
    const until = async check => { for (let i = 0; i < 60; i++) { if (await check(await task())) return; await p.waitForTimeout(100); } throw new Error('database did not reach expected values'); };
    await p.evaluate(id => TK.tasks.open(id), f.task); await p.locator('[data-detail-edit]').click();
    const form = p.locator('#f-task'); assert(await form.locator('[type=submit]').isHidden());
    await form.locator('[name=title]').fill('ویرایش زنده و بدون تأیید'); await form.locator('[name=description]').fill('متن ذخیره‌شده به‌صورت خودکار');
    await form.locator('[name=priority]').selectOption('high');
    await until(t => t.title === 'ویرایش زنده و بدون تأیید' && t.description === 'متن ذخیره‌شده به‌صورت خودکار' && t.priority === 'high');
    await form.locator('[data-save-state][data-state=saved]').waitFor();
    console.log('PASS title, description and priority save automatically without form submit or navigation');

    const choose = async (name, value) => { const select = form.locator('[data-rselect="' + name + '"]'); await select.locator('.rselect-btn').click(); await select.locator('[data-v="' + value + '"]').click(); };
    await choose('assignee_id', f.users[1].pid); await form.locator('[name=subject_id]').selectOption(f.label);
    await until(t => t.assignee_id === f.users[1].pid && t.subject_id === f.label);
    await choose('project_id', f.otherProject); await until(t => t.project_id === f.otherProject && !t.subject_id);
    await form.locator('[name=priority_percent]').fill('85'); await until(t => t.priority_percent === 85);
    await form.locator('[data-save-state][data-state=saved]').waitFor();
    await choose('project_id', f.project); await choose('assignee_id', f.users[0].pid);
    await until(t => t.project_id === f.project && t.assignee_id === f.users[0].pid); await form.locator('[data-save-state][data-state=saved]').waitFor();
    console.log('PASS assignee, label, project and percent priority save automatically with dependent fields kept consistent');

    await form.locator('[name=title]').fill('');
    await form.locator('[name=reminder_every]').fill('4'); await form.locator('[name=reminder_unit]').selectOption('hour');
    await until(t => t.reminder_every === 4 && t.reminder_unit === 'hour');
    await form.locator('[data-save-state][data-state=invalid]').waitFor();
    assert.equal((await task()).title, 'ویرایش زنده و بدون تأیید');
    await p.evaluate(() => { window.closeQuestions = 0; TK.confirm = async () => { window.closeQuestions++; return false; }; });
    await form.locator('.tf-foot [data-close]').click(); assert(await p.locator('#m-task').evaluate(el => el.classList.contains('open')));
    assert.equal(await p.evaluate(() => closeQuestions), 1);
    await form.locator('[name=title]').fill('عنوان معتبر'); await until(t => t.title === 'عنوان معتبر');
    await form.locator('[data-save-state][data-state=saved]').waitFor(); await form.locator('.tf-foot [data-close]').click();
    assert.equal(await p.evaluate(() => closeQuestions), 1);
    console.log('PASS invalid draft is not saved or mistaken for saved while other fields save; valid close requires no confirmation');

    await p.evaluate(id => TK.tasks.open(id), f.task);
    await p.locator('#f-note textarea').fill('پیش‌نویس یادداشت حفظ شود');
    await p.locator('[data-qe=due]').click(); let panel = p.locator('.qe-schedule');
    assert.equal(await panel.locator('[data-qe-save-due], [data-ok]').count(), 0);
    await panel.locator('.dp-day[data-day="1"]').click(); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    await panel.locator('[data-custom]').click();
    const dateButton = panel.locator('.jcal-day:not(.sel)').first(); const day = await dateButton.getAttribute('data-d');
    const expectedDate = await p.evaluate(day => { const [jy, jm] = TK.jalali.g2j(...TK.jalali.parseIso(document.querySelector('.qe-schedule [name=qe_due_date]').value)); return TK.jalali.iso(...TK.jalali.j2g(jy, jm, Number(day))); }, day);
    await dateButton.click(); await panel.locator('[data-clock-hour]').fill('18'); await panel.locator('[data-clock-minute]').fill('37');
    await until(t => t.due_date === expectedDate && t.due_time === '18:37');
    await panel.locator('[data-save-state][data-state=saved]').waitFor();
    assert.equal(await p.locator('#f-note textarea').inputValue(), 'پیش‌نویس یادداشت حفظ شود');
    await p.waitForFunction(() => document.querySelector('.td-props [data-qe=due]')?.textContent.includes('۱۸:۳۷'));
    console.log('PASS quick date, calendar day and precise 24-hour time save without confirmation and preserve note draft');

    await panel.locator('[data-clock-hour]').fill('25'); await panel.locator('[data-save-state][data-state=invalid]').waitFor();
    await p.waitForTimeout(650); assert.equal((await task()).due_time, '18:37');
    await panel.locator('[data-clock-hour]').fill('23'); await panel.locator('[data-clock-minute]').fill('59'); await until(t => t.due_time === '23:59');
    await panel.locator('[data-clock-clear]').click(); await until(t => !t.due_time);
    await panel.locator('[data-clock-mode=h]').click(); await panel.locator('[data-clock-value="18"]').click(); await panel.locator('[data-clock-value="35"]').click();
    await until(t => t.due_time === '18:35'); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    console.log('PASS invalid clock value is rejected; minute 59, clearing and clock grid commit without confirmation');

    let active = 0, maxActive = 0, requests = 0, started;
    const firstStarted = new Promise(resolve => { started = resolve; });
    const endpoint = '**/tasks/' + f.task + '/update';
    await p.route(endpoint, async route => {
      active++; maxActive = Math.max(maxActive, active); requests++; started();
      await new Promise(resolve => setTimeout(resolve, 450));
      try { const response = await route.fetch(); await route.fulfill({ response }); } finally { active--; }
    });
    await panel.locator('[name=reminder_every]').fill('5'); await firstStarted;
    await panel.locator('[name=reminder_every]').fill('12'); await panel.locator('[name=reminder_unit]').selectOption('hour');
    await until(t => t.reminder_every === 12 && t.reminder_unit === 'hour'); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    assert.equal(maxActive, 1); assert(requests >= 2); await p.unroute(endpoint);
    console.log('PASS rapid edits during slow response are serialized; latest number and unit win');

    let fail = true;
    await p.route(endpoint, async route => { if (fail) { fail = false; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ status: 'error', message: 'ارتباط با سرور برقرار نشد.' }) }); } return route.continue(); });
    await panel.locator('[name=reminder_every]').fill('13'); await panel.locator('[data-save-state][data-state=error]').waitFor();
    assert.equal((await task()).reminder_every, 12); assert.equal(await panel.locator('[name=reminder_every]').inputValue(), '13');
    await panel.locator('[data-save-retry]').click(); await until(t => t.reminder_every === 13); await panel.locator('[data-save-state][data-state=saved]').waitFor(); await p.unroute(endpoint);
    console.log('PASS failed save keeps draft and shows error; explicit retry persists it');

    await panel.locator('[name=reminder_every]').fill('1001'); await panel.locator('[data-save-state][data-state=invalid]').waitFor();
    await p.waitForTimeout(700); assert.equal((await task()).reminder_every, 13);
    await panel.locator('[name=reminder_every]').fill('24'); await until(t => t.reminder_every === 24); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    await panel.locator('[name=reminder_every]').fill('25'); await panel.locator('[data-qe-close]').click(); await until(t => t.reminder_every === 25);
    assert.equal(await panel.count(), 0);
    console.log('PASS invalid interval is not sent; closing during debounce flushes the last valid change');

    active = 0; maxActive = 0; let begin;
    const began = new Promise(resolve => { begin = resolve; });
    await p.route(endpoint, async route => { active++; maxActive = Math.max(maxActive, active); begin(); await new Promise(resolve => setTimeout(resolve, 450)); try { const response = await route.fetch(); await route.fulfill({ response }); } finally { active--; } });
    await p.locator('[data-qe=due]').click(); panel = p.locator('.qe-schedule'); await panel.locator('[name=reminder_every]').fill('26'); await began;
    await panel.locator('[data-qe-close]').click(); await p.locator('[data-qe=due]').click(); panel = p.locator('.qe-schedule');
    await panel.locator('[name=reminder_every]').fill('27'); await until(t => t.reminder_every === 27); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    assert.equal(maxActive, 1); await p.unroute(endpoint);
    console.log('PASS closing and reopening during in-flight save shares the same task write queue');
    await panel.locator('.dp-day[data-day="1"]').click(); await panel.locator('[data-repeat-toggle]').check();
    await until(t => t.recurrence?.enabled); await panel.locator('[data-repeat-kind]').selectOption('custom'); await panel.locator('[data-repeat-days]').fill('3');
    await until(t => t.recurrence?.kind === 'custom' && Number(t.recurrence.days) === 3);
    await panel.locator('[data-repeat-toggle]').uncheck(); await until(t => !t.recurrence?.enabled); await panel.locator('[data-save-state][data-state=saved]').waitFor();
    await panel.locator('[data-qe-close]').click();
    console.log('PASS task recurrence toggle, kind and custom interval also save automatically');

    for (const theme of ['violet-light', 'violet-dark', 'green-light', 'green-dark']) {
      await p.evaluate(theme => { document.documentElement.dataset.theme = theme; document.documentElement.dataset.mode = theme.endsWith('dark') ? 'dark' : 'light'; }, theme);
      for (const width of [1365, 390]) {
        await p.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
        await p.locator('[data-qe=due]').click(); panel = p.locator('.qe-schedule'); await panel.locator('[data-custom]').click(); await p.waitForTimeout(200);
        const box = await panel.boundingBox(); assert(box.x >= -1 && box.y >= -1 && box.x + box.width <= width + 1 && box.y + box.height <= (width === 390 ? 844 : 1000) + 1);
        assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await p.screenshot({ path: '/tmp/kx-task-schedule-' + theme + '-' + width + '.png' });
        await panel.locator('[data-qe-close]').click();
      }
    }
    for (const size of [{ width: 320, height: 568 }, { width: 768, height: 650 }]) {
      await p.setViewportSize(size); await p.locator('[data-qe=due]').click(); panel = p.locator('.qe-schedule'); await panel.locator('[data-custom]').click();
      await p.waitForTimeout(150); const box = await panel.boundingBox(); assert(box.x >= -1 && box.y >= -1 && box.x + box.width <= size.width + 1 && box.y + box.height <= size.height + 1);
      await panel.locator('[data-qe-close]').click();
    }
    console.log('PASS calendar and time layouts fit desktop, tablet, 390px and 320px screens in four light/dark themes');
    await p.setViewportSize({ width: 1365, height: 1000 }); await p.evaluate(id => TK.tasks.open(id), f.task); await p.locator('[data-detail-edit]').click();
    await form.locator('[data-jdue] > .picker-btn').click(); const custom = form.locator('[data-jdue] > .dp-pop'); await custom.locator('[data-custom]').click();
    await custom.locator('[data-clock-hour]').fill('8'); await custom.locator('[data-clock-minute]').fill('7'); await until(t => t.due_time === '08:07');
    await form.locator('[data-save-state][data-state=saved]').waitFor(); await p.waitForTimeout(180);
    assert(await custom.evaluate(el => el.scrollWidth <= el.clientWidth + 1)); await p.screenshot({ path: '/tmp/kx-task-edit-desktop.png' });
    await p.setViewportSize({ width: 390, height: 844 }); await p.waitForTimeout(150); const customBox = await custom.boundingBox();
    assert(customBox.x >= -1 && customBox.x + customBox.width <= 391 && customBox.y >= -1 && customBox.y + customBox.height <= 845);
    await p.screenshot({ path: '/tmp/kx-task-edit-mobile.png' }); await form.locator('#task-modal-title').click(); await form.locator('.tf-foot [data-close]').click();
    console.log('PASS full edit form custom calendar and time also autosave and fit both desktop and mobile');
    await p.evaluate(id => TK.tasks.open(id), f.task); await p.locator('[data-detail-edit]').click();
    const previousDescription = (await task()).description; let discardedRequests = 0;
    await p.route(endpoint, route => { discardedRequests++; return route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ status: 'error', message: 'ارتباط با سرور برقرار نشد.' }) }); });
    await form.locator('[name=description]').fill('این پیش‌نویس ناموفق باید کنار گذاشته شود'); await form.locator('[data-save-state][data-state=error]').waitFor();
    await p.evaluate(() => { TK.confirm = async () => true; }); await form.locator('.tf-foot [data-close]').click(); await p.waitForTimeout(750);
    assert.equal(discardedRequests, 1); assert.equal((await task()).description, previousDescription); assert.equal(await p.evaluate(() => TK.leaveGuard()), false);
    await p.unroute(endpoint); await p.evaluate(id => TK.tasks.open(id), f.task); await p.locator('[data-detail-edit]').click(); assert.equal(await form.locator('[name=description]').inputValue(), previousDescription);
    await form.locator('.tf-foot [data-close]').click();
    console.log('PASS explicitly discarded failed draft is never retried after close or reopening');
    assert.equal(documents, 0); assert.deepEqual(errors, []);
    console.log('PASS no document navigation and no browser JavaScript errors throughout edits');
  } finally { await browser?.close(); fixture({ action: 'cleanup', users: f.users }); }
})().catch(e => { console.error(e); process.exitCode = 1; });
