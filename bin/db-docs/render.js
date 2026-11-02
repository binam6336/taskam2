// HTML → PDF با Chromium (Playwright). فراخوانی از build.py: node render.js in.html out.pdf version
const { chromium } = require('playwright');
(async () => {
  const [, , input, output, ver] = process.argv;
  const b = await chromium.launch();
  const p = await b.newPage();
  await p.goto('file://' + input, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  await p.pdf({
    path: output, format: 'A4', printBackground: true, preferCSSPageSize: true,
    displayHeaderFooter: true, headerTemplate: '<span></span>',
    footerTemplate: `<div style="font-size:7pt;width:100%;text-align:center;color:#6b8577;font-family:sans-serif"><span class="pageNumber"></span> / <span class="totalPages"></span> — Taskam DB v${ver}</div>`,
  });
  await b.close();
})();
