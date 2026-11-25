// تست با DB واقعی و سرور موقت LiveKit محلی؛ این تست جای اتصال واقعی Cloud نیست.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const assert = require('assert');
const path = require('path');
const php = process.env.PHP_BIN || 'php';
const base = process.env.LARAVEL_TEST_URL || 'http://127.0.0.1:8082';
const uiOnly = process.env.KARLIX_MEETING_UI_ONLY === '1';
const driver = path.join(__dirname, '../tests/meetings/local-driver.php');
const run = (args) => JSON.parse(execFileSync(php, [driver, JSON.stringify(args)], { maxBuffer: 2e6 }).toString());
const f = run({ action: 'fixture' });
const createdMeetings = [f.meeting];
assert(f.testDatabase?.endsWith('_test'));
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox', '--disable-features=LocalNetworkAccessChecks,LocalNetworkAccessChecksWebRTC,PrivateNetworkAccessSendPreflights', '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--autoplay-policy=no-user-gesture-required', '--auto-select-desktop-capture-source=Entire screen', '--enable-usermedia-screen-capturing'] });
  const errors = [], contexts = [];
  const login = async (user) => {
    const ctx = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1365, height: 950 }, permissions: ['microphone', 'camera'] }); contexts.push(ctx);
    const p = await ctx.newPage(); p.on('pageerror', (e) => errors.push(e.message));
    p.on('console', (m) => { if(m.type()==='error') console.log('BROWSER', m.text().replace(/(?:https?|wss?):\/\/[^\s]+/g,'[test-url]').slice(0,200)); });
    await p.goto(base + '/login');
    await p.locator('[name=mobile]').first().fill(user.mobile); await p.locator('[name=password]').first().fill(user.password);
    await p.locator('form[action$="/login"] button[type=submit]').click(); await p.waitForURL('**/dashboard');
    await p.route('**/*', async (route) => {
      const req = route.request(), url = new URL(req.url());
      if (uiOnly && url.pathname.includes('/assets/vendor/livekit/livekit-client-')) {
        await route.fulfill({path:path.join(__dirname,'../tests/meetings/browser-sdk-stub.js'),contentType:'application/javascript'}); return;
      }
      const match = url.pathname.match(/^\/meetings\/([a-z0-9]{12})\/(join|heartbeat|moderate|members|consent|leave|end|invite)$/);
      const create = url.pathname.match(/^\/projects\/([a-z0-9]{12})\/meetings$/);
      if (match || (create && req.method() === 'POST')) {
        const data = {};
        const raw = req.postData() || '';
        if ((req.headers()['content-type'] || '').includes('multipart/form-data')) {
          for (const m of raw.matchAll(/name="([^"\r\n]+)"\r\n\r\n([^\r\n]*)/g)) {
            if (m[1].endsWith('[]')) (data[m[1].slice(0,-2)] ||= []).push(m[2]); else data[m[1]] = m[2];
          }
        } else if (raw) Object.assign(data, JSON.parse(raw));
        if (create) data.project = create[1];
        const out = run({ action: match ? match[2] : 'create', uid: user.pid, meeting: match ? match[1] : f.meeting, data });
        await route.fulfill({ status: out.http || 200, contentType: 'application/json', body: JSON.stringify(out) }); return;
      }
      if (req.isNavigationRequest() && /^\/meetings\/[a-z0-9]{12}$/.test(url.pathname)) {
        const response = await route.fetch(), headers = { ...response.headers() };
        assert(headers['permissions-policy'].includes('camera=(self)'), 'meeting camera header');
        assert(headers['content-security-policy'].includes('wss://*.livekit.cloud'), 'Cloud CSP');
        headers['content-security-policy'] = headers['content-security-policy'].replace("connect-src 'self'", "connect-src 'self' ws://127.0.0.1:7880 http://127.0.0.1:7880");
        await route.fulfill({ response, headers }); return;
      }
      await route.continue();
    });
    return p;
  };
  try {
    const host = await login(f.users[0]), member = await login(f.users[1]), stranger = await login(f.users[2]);
    const denied = await stranger.request.get(base + '/meetings/' + f.meeting + '/state'); assert.equal(denied.status(), 404);
    const noCsrf = await host.request.post(base + '/meetings/' + f.meeting + '/hand', { form: { raised: '1' } }); assert.equal(noCsrf.status(), 419);
    const tokenRoute = await stranger.request.post(base + '/api/v1/meetings/' + f.meeting + '/join', { data: {} }); assert.equal(tokenRoute.status(), 419);
    const readOnly = await fetch(base + '/api/v1/meetings/' + f.meeting + '/join', {method:'POST',headers:{Authorization:'Bearer '+f.readToken,'Content-Type':'application/json'},body:'{}'});
    assert.equal(readOnly.status,403); assert.equal((await readOnly.json()).error_code,'TOKEN_READ_ONLY');
    const bearerRead = await fetch(base + '/api/v1/meetings/' + f.meeting + '/state', {headers:{Authorization:'Bearer '+f.readToken}}); assert.equal(bearerRead.status,200);
    const unauth = await fetch(base + '/api/v1/meetings/' + f.meeting + '/state'); assert.equal(unauth.status,401);
    const invalidHook = await fetch(base+'/integrations/livekit/webhook',{method:'POST',headers:{'Content-Type':'application/webhook+json'},body:'{}'}); assert.equal(invalidHook.status,401);
    console.log('PASS real HTTP project isolation, CSRF, Bearer read-only and signed-webhook requirement');
    await host.goto(base + '/projects'); await host.locator('[data-meeting-project="' + f.project + '"] [data-meeting-start=video]').click(); await host.waitForURL('**/meetings/' + f.meeting);
    await host.locator('#meeting-pre-mic').check(); await host.locator('#meeting-pre-camera').check(); await host.locator('#meeting-test').click();
    await host.waitForFunction(() => document.querySelector('#meeting-preview-video').srcObject?.getVideoTracks().length === 1);
    await host.locator('#meeting-join').click(); await host.waitForFunction(() => document.querySelector('#meeting-connection').textContent === 'متصل');
    await member.goto(base + '/meetings/' + f.meeting); await member.locator('#meeting-join').click();
    await member.waitForFunction(() => document.querySelector('#meeting-connection').textContent === 'متصل');
    if (!uiOnly) await member.waitForFunction(() => [...document.querySelectorAll('#meeting-grid video')].some((v) => v.videoWidth > 0));
    assert(await member.locator('#meeting-mic').isDisabled());
    console.log(uiOnly ? 'PASS UI: two sessions, prejoin device preview, listener cannot publish (media mocked)' : 'PASS two actual WebRTC participants; remote video rendered; listener cannot publish');
    await host.locator('[data-meeting-moderate=speaker][data-member="' + f.users[1].pid + '"]').evaluate((b) => b.click());
    await member.waitForFunction(() => !document.querySelector('#meeting-mic').disabled); await member.locator('#meeting-mic').click();
    if (!uiOnly) await host.waitForFunction(() => document.querySelectorAll('#meeting-audio-tracks audio').length > 0);
    console.log(uiOnly ? 'PASS UI speaker permission and own microphone control (media mocked)' : 'PASS promotion through real LiveKit UpdateParticipant and remote microphone publication');
    await member.locator('#meeting-message').fill('پیام فارسی <img src=x onerror=alert(1)>'); await member.locator('#meeting-chat-form button').click();
    await host.waitForFunction(() => document.querySelector('#meeting-chat-list').textContent.includes('پیام فارسی'));
    assert.equal(await host.locator('#meeting-chat-list img').count(), 0);
    const stored = await member.request.get(base + '/meetings/' + f.meeting + '/messages'); assert((await stored.json()).data.some((m) => m.body.includes('پیام فارسی')));
    console.log('PASS real DB chat delivery, history and escaped XSS payload');
    await host.locator('[data-meeting-moderate=mute][data-member="' + f.users[1].pid + '"]').evaluate((b) => b.click());
    await member.waitForFunction(() => document.querySelector('#meeting-mic').disabled);
    await member.waitForFunction(() => document.querySelector('#meeting-mic').getAttribute('aria-pressed') === 'false');
    console.log('PASS host restriction stops local microphone');
    await host.locator('[data-meeting-moderate=request_microphone][data-member="' + f.users[1].pid + '"]').evaluate((b) => b.click());
    await member.locator('#tk-confirm.open [data-ok]').click();
    await member.waitForFunction(() => document.querySelector('#meeting-mic').getAttribute('aria-pressed') === 'true');
    console.log('PASS explicit member consent before requested microphone activation' + (uiOnly ? ' (media mocked)' : ''));
    if (!uiOnly) {
    await host.locator('#meeting-screen').click();
    await host.waitForFunction(() => document.querySelectorAll('.meeting-screen-tile video').length > 0, { timeout: 10000 });
    await member.waitForFunction(() => [...document.querySelectorAll('.meeting-screen-tile video')].some((v) => v.videoWidth > 0));
    console.log('PASS real browser screen-sharing track reaches second participant');
    } else {
      await host.evaluate(() => window.__meetingTestRoom.emit('Reconnecting'));
      assert((await host.locator('#meeting-connection').textContent()).includes('اتصال مجدد'));
      await host.evaluate(() => window.__meetingTestRoom.emit('Reconnected'));
      console.log('PASS UI reconnect event handling (simulated; no actual network media)');
    }
    await host.setViewportSize({ width: 390, height: 844 });
    assert(await host.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await host.screenshot({ path: '/tmp/kx-meeting-mobile.png', fullPage: true });
    console.log('PASS mobile layout without horizontal overflow');
    await host.setViewportSize({width:1365,height:950});
    await host.locator('.topbar [data-theme-toggle]').click();
    await host.waitForFunction(()=>document.documentElement.dataset.mode==='dark');
    await host.screenshot({path:'/tmp/kx-meeting-dark.png',fullPage:true});
    console.log('PASS theme toggle while meeting remains open');
    await host.locator('#meeting-end').click(); await host.locator('#tk-confirm [data-ok]').click();
    await member.waitForFunction(() => document.querySelector('#meeting-ended').hidden === false);
    const afterEnd = await host.request.post(base + '/api/v1/meetings/' + f.meeting + '/join', { headers: { 'X-CSRF-Token': await host.locator('meta[name=csrf-token]').getAttribute('content') }, data: {} });
    assert.equal(afterEnd.status(), 409);
    await host.locator('#meeting-history-chat').click(); await host.waitForFunction(() => document.querySelector('#meeting-chat-list').textContent.includes('پیام فارسی'));
    await host.waitForTimeout(2800); assert(await host.locator('#meeting-chat-list').isVisible());
    console.log('PASS end for all, no new token after end, permanent chat history remains visible after polling');
    if (uiOnly) {
      const audio = run({action:'create',uid:f.users[0].pid,meeting:f.meeting,data:{project:f.project,mode:'audio'}}).data;
      createdMeetings.push(audio.id); await host.goto(base+'/projects');
      await host.locator('[data-meeting-project="'+f.project+'"] [data-meeting-start=audio]').click();
      await host.waitForURL('**/meetings/'+audio.id); assert(await host.locator('#meeting-room.meeting-audio').count());
      assert.equal(await host.locator('#meeting-pre-camera').count(),0);
      await host.locator('#meeting-join').click(); await host.waitForFunction(()=>document.querySelector('#meeting-connection').textContent==='متصل');
      assert.equal(await host.locator('#meeting-camera').count(),0);
      await host.locator('#meeting-leave').click();
      await host.waitForFunction(()=>!document.querySelector('#meeting-prejoin').hidden);
      console.log('PASS dedicated audio UI, no camera control and voluntary leave (media mocked)');
    }
    assert.deepEqual(errors, []);
    console.log('PASS no browser JavaScript errors');
    if (uiOnly) console.log('LIMITATION: UI media was mocked; no WebRTC/Cloud audio, video or screen-share acceptance claimed.');
  } catch (err) {
    for (const c of contexts) for(const page of c.pages()) console.log('STATE', await page.locator('#meeting-connection,#meeting-alert').allTextContents());
    throw err;
  } finally {
    for (const c of contexts) await c.close(); await browser.close();
    for(const meeting of createdMeetings) { try { run({ action: 'end', uid: f.users[0].pid, meeting }); } catch {} }
    run({ action: 'cleanup', uid: f.users[0].pid, meeting: f.meeting, users: f.users });
  }
})().catch((e) => { console.error(e); process.exit(1); });
