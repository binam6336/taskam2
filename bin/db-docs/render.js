// HTML → PDF با Chromium (Playwright). فراخوانی از build.py: node render.js in.html out.pdf version
const { chromium } = require('playwright');
(async () => {
  const [, , input, output, ver] = process.argv;
  const b = await chromium.launch(process.env.CHROMIUM_PATH ? {executablePath: process.env.CHROMIUM_PATH, args: ['--no-sandbox']} : {});
  const p = await b.newPage();
  // محتوای محلی با فونت جاسازی‌شده؛ در مرورگرهایی که file:// را می‌بندند هم قابل ساخت است.
  const fs = require('fs');
  let html = fs.readFileSync(input, 'utf8');
  html = html.replace(/file:\/\/([^'"\s)]+\.woff2)/g, (_, path) => 'data:font/woff2;base64,' + fs.readFileSync(decodeURI(path)).toString('base64'));
  await p.setContent(html, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  await p.pdf({
    path: output, format: 'A4', printBackground: true, preferCSSPageSize: true,
    displayHeaderFooter: true, headerTemplate: '<span></span>',
    footerTemplate: `<div style="font-size:7pt;width:100%;text-align:center;color:#6b8577;font-family:sans-serif"><span class="pageNumber"></span> / <span class="totalPages"></span> — Karlix DB v${ver}</div>`,
  });
  await b.close();
})();
