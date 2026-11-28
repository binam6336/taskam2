#!/usr/bin/env node
/* ساخت آیکن ثابت کارلیکس از SVG رسمی؛ نام‌های قدیمی تم‌ها فقط کپی سازگار همان آیکن‌اند.
   PHP_BIN و CHROMIUM_PATH برای محیط ساخت اختیاری هستند. خروجی maskable حاشیه امن دارد.
   نشان نوار وضعیت اندروید از همان شکل به صورت تک‌رنگ ساخته می‌شود. */
const { execFileSync } = require('child_process'), path = require('path'), fs = require('fs'), { chromium } = require('playwright');
const ROOT = path.resolve(__dirname, '..'), OUT = path.join(ROOT, 'public/assets/img');
const php = code => execFileSync(process.env.PHP_BIN || 'php', ['-r', `require ${JSON.stringify(ROOT + '/app/bootstrap.php')}; ${code}`], { cwd: ROOT }).toString();
const themes = JSON.parse(php('echo json_encode(array_keys(themes()));'));
const icon = php("echo logo_svg('icon');"), full = php("echo logo_svg('icon', null, true);");
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage({ deviceScaleFactor: 1 });
    const shot = async (markup, size, name) => {
      await page.setViewportSize({ width: size, height: size });
      await page.setContent(`<html><body style="margin:0;background:transparent"><div style="width:${size}px;height:${size}px">${markup}</div></body></html>`);
      await page.locator('div').screenshot({ path: path.join(OUT, name + '.png'), omitBackground: true });
      console.log(name + '.png');
    };
    for (const size of [192, 512]) { await shot(icon, size, 'icon-' + size); await shot(full, size, 'icon-maskable-' + size); }
    await shot(full, 180, 'apple-touch-icon');
    const badge = await page.evaluate(markup => {
      const svg = new DOMParser().parseFromString(markup, 'image/svg+xml').documentElement;
      svg.querySelector('rect')?.remove();
      svg.querySelectorAll('[fill]').forEach(el => { if (el.getAttribute('fill') !== 'none') { el.setAttribute('fill', 'none'); el.setAttribute('stroke', '#FFFFFF'); el.setAttribute('stroke-width', '1.2'); } });
      svg.querySelectorAll('[stroke]').forEach(el => el.setAttribute('stroke', '#FFFFFF'));
      return new XMLSerializer().serializeToString(svg);
    }, icon);
    await shot(badge, 96, 'badge-96');
    fs.copyFileSync(path.join(OUT, 'brand/karlix-favicon.svg'), path.join(OUT, 'brand/karlix-icon.svg'));
    for (const theme of themes) for (const name of ['icon-192', 'icon-512', 'icon-maskable-192', 'icon-maskable-512', 'apple-touch-icon']) {
      fs.copyFileSync(path.join(OUT, name + '.png'), path.join(OUT, name + '-' + theme + '.png'));
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
