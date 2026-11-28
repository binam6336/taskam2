// بررسی رابط واقعی، جستجوی رمزگذاری‌شده و کنار گذاشتن پاسخ درخواست قدیمی.
const {chromium}=require('playwright'),{execFileSync}=require('child_process'),assert=require('assert'),path=require('path');
const base=process.env.LARAVEL_TEST_URL||'http://127.0.0.1:8082',php=process.env.PHP_BIN||'php',driver=path.join(__dirname,'../tests/search/local-driver.php');
const run=(data)=>JSON.parse(execFileSync(php,[driver,JSON.stringify(data)]).toString()),f=run({action:'fixture'});assert(f.testDatabase.endsWith('_test'));
(async()=>{let browser;try{
 browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH||'/usr/bin/chromium',args:['--no-sandbox']});
 const ctx=await browser.newContext({serviceWorkers:'block',viewport:{width:1365,height:950}}),p=await ctx.newPage(),errors=[];p.on('pageerror',e=>errors.push(e.message));
 const login=async(page,u)=>{await page.goto(base+'/login');await page.locator('[name=mobile]').first().fill(u.mobile);await page.locator('[name=password]').first().fill(u.password);await page.locator('form[action$="/login"] button[type=submit]').click();await page.waitForURL('**/dashboard');};
 await login(p,f.users[0]);await p.goto(base+'/tasks');await p.waitForFunction(()=>!!window.TK?.tasks);await p.evaluate(id=>TK.tasks.open(id),f.task);
 const icon=p.locator('#m-detail [data-detail-history]');await icon.waitFor();assert.equal(await icon.count(),1);assert.equal(await icon.locator('span').textContent(),'تاریخچه تغییرات');assert.equal(await icon.evaluate(el=>el.parentElement.id),'m-detail');assert.equal(await p.locator('#m-detail .utab[data-detail-history],#m-detail .td-tools [data-detail-history]').count(),0);
 for(const [width,height] of [[1365,950],[390,844]]){
  await p.setViewportSize({width,height});await p.waitForTimeout(250);
  const box=await icon.boundingBox();assert(Math.abs(width-box.x-box.width-20)<3&&Math.abs(height-box.y-box.height-20)<3);
  await p.locator('#m-detail').evaluate(el=>{el.scrollTop=el.scrollHeight;});
  await p.locator('.td-scroll').evaluate(el=>{el.scrollTop=el.scrollHeight;});
  const scrolled=await icon.boundingBox();assert(Math.abs(scrolled.y-box.y)<1);assert(await icon.evaluate(el=>{const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}));
  await p.screenshot({path:'/tmp/kx-history-icon-'+width+'.png'});
  await icon.click();await p.locator('#m-history.open .hist-item').first().waitFor();assert((await p.locator('#history-body').textContent()).includes('عنوان قبلی سنجه'));
  const stamp=await p.locator('#history-body .hist-at').first().textContent();
 assert(stamp.match(process.env.HISTORY_TEST_SECONDS==='0'?/[۰-۹]{2}:[۰-۹]{2}/:/[۰-۹]{2}:[۰-۹]{2}:[۰-۹]{2}/));
  await p.screenshot({path:'/tmp/kx-history-popup-'+width+'.png'});await p.locator('#m-history [data-close]').click();assert(await p.locator('#m-detail').isVisible());
 }
 console.log('PASS single labeled history button outside clipped task card at viewport bottom right, stable after scrolling, desktop/mobile popup with dated full history');
 await p.locator('#m-detail [data-close]').first().click();assert(await icon.isHidden());await p.setViewportSize({width:1365,height:950});
 for(const theme of ['violet-light','violet-dark','green-light','green-dark']){
  await ctx.addCookies([{name:'kx_theme',value:theme,url:base}]);await p.goto(base+'/tasks');await p.waitForFunction(()=>!!window.TK?.tasks);await p.evaluate(id=>TK.tasks.open(id),f.task);await icon.waitFor();
  assert.equal(await p.locator('html').getAttribute('data-theme'),theme);await p.screenshot({path:'/tmp/kx-history-theme-'+theme+'.png'});await p.locator('#m-detail [data-close]').first().click();
 }
 console.log('PASS history icon renders in two light/dark palettes');
 const response=await p.request.get(base+'/search?q='+encodeURIComponent('سنجهواجهة')),web=(await response.json()).data;
 assert(web.groups.some(g=>g.items.some(i=>i.id===f.task)));
 const api=await p.request.get(base+'/api/v1/search?q='+encodeURIComponent('سنجهواجهة'),{headers:{Authorization:'Bearer '+f.token}});
 assert.deepEqual((await api.json()).data,web);assert(response.headers()['cache-control'].includes('no-store'));
 console.log('PASS live web and bearer API search return the same authorized encrypted content');
 await p.keyboard.press('Control+k');await p.locator('.sb-gsearch[open]').waitFor();
 await p.locator('.sb-gsearch .sb-pal-q').fill('سنجهواجهة');await p.locator('.sb-gsearch .sb-pal-t b').filter({hasText:'سنجهواجهة'}).waitFor();
 await p.screenshot({path:'/tmp/kx-search-results.png'});
 await p.locator('.sb-gsearch .sb-pal-t b').filter({hasText:'سنجهواجهة'}).click();await icon.waitFor();assert(await p.locator('#m-detail').isVisible());
 await p.locator('#m-detail [data-close]').first().click();
 console.log('PASS palette searches decrypted data and opens selected task');
 let release;const held=new Promise(r=>release=r);
 await p.route('**/search?q=*',async route=>{const q=new URL(route.request().url()).searchParams.get('q');if(q==='قدیمی'){await held;await route.fulfill({json:{success:true,data:{total:1,groups:[{key:'tasks',title:'تسک‌ها',icon:'fa-list-check',items:[{id:'old',title:'پاسخ منقضی',url:'/tasks'}]}]}}}).catch(()=>{});}else await route.continue();});
 await p.keyboard.press('Control+k');const input=p.locator('.sb-gsearch .sb-pal-q');const sent=p.waitForRequest(r=>r.url().includes('/search?q=')&&new URL(r.url()).searchParams.get('q')==='قدیمی');await input.fill('قدیمی');const old=await sent;
 const failed=p.waitForEvent('requestfailed',{predicate:r=>r===old});await input.fill('سنجهواجهة');await failed;
 await p.locator('.sb-gsearch .sb-pal-t b').filter({hasText:'سنجهواجهة'}).waitFor();release();await p.waitForTimeout(150);
 assert(!(await p.locator('.sb-gsearch').textContent()).includes('پاسخ منقضی'));
 console.log('PASS changing query aborts obsolete request and stale response cannot replace new results');
 const foreign=await browser.newContext({serviceWorkers:'block'}),fp=await foreign.newPage();await login(fp,f.users[1]);
 assert.equal((await fp.request.get(base+'/tasks/'+f.task+'/history')).status(),404);const noAccess=await fp.request.get(base+'/search?q='+encodeURIComponent('سنجهواجهة'));assert.equal((await noAccess.json()).data.total,0);
 console.log('PASS unrelated account cannot search task or fetch its history');assert.deepEqual(errors,[]);
}finally{await browser?.close();run({action:'cleanup',users:f.users});}})().catch(e=>{console.error(e);process.exitCode=1;});
