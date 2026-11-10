#!/usr/bin/env node
/*
 * ساخت آیکن‌های PNG کارلیکس برای هر تم (v7.5) از همان logo_svg() در app/helpers.php:
 *   node bin/build-brand-icons.js
 * خروجی در public/assets/img:
 *   icon-192-<تم>.png، icon-512-<تم>.png (کاشی گرد)، icon-maskable-192/512-<تم>.png و apple-touch-icon-<تم>.png (تمام‌صفحه)
 *   icon-192.png / icon-512.png / apple-touch-icon.png = رنگ اصلی برند (violet-light) برای اعلان‌ها و پیش‌فرض
 *   badge-96.png = نشان تک‌رنگ سفید (نوار وضعیت اندروید)
 * نیاز: php (CLI) و playwright (کرومیوم).
 */
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

const ROOT = path.resolve(__dirname, '..');
const OUT = path.join(ROOT, 'public/assets/img');
const php = (code) => execFileSync('php', ['-r', `require '${ROOT}/app/bootstrap.php'; ${code}`], { cwd: ROOT }).toString();
const themes = JSON.parse(php('echo json_encode(array_keys(themes()));'));
const svg = (theme, bleed) => php(`echo logo_svg('i', ${JSON.stringify(theme)}, ${bleed ? 'true' : 'false'});`);
// نشان تک‌رنگ: فقط کمان، خطوط و تیک به رنگ سفید روی زمینه‌ی شفاف
const BADGE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="14 14 72 72" width="100%" height="100%">'
  + '<g fill="none" stroke="#fff" stroke-linecap="round"><path d="M33.1 38.5 A21 21 0 1 1 33.1 61.5" stroke-width="5"/>'
  + '<path d="M22 44.5 H29 M18 50 H31 M22 55.5 H29" stroke-width="4.6"/><path d="M43 45 L50 52 L62 39" stroke-width="5.4" stroke-linejoin="round"/></g></svg>';

(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ deviceScaleFactor: 1 });
  const shot = async (markup, size, file) => {
    await p.setViewportSize({ width: size, height: size });
    await p.setContent(`<html><body style="margin:0;background:transparent"><div style="width:${size}px;height:${size}px">${markup}</div></body></html>`);
    await p.locator('div').screenshot({ path: path.join(OUT, file), omitBackground: true });
    console.log(file);
  };
  for (const f of fs.readdirSync(OUT)) if (/^(icon|apple-touch-icon).*\.png$/.test(f)) fs.unlinkSync(path.join(OUT, f));
  const all = [...themes.map((t) => [t, '-' + t]), ['violet-light', '']];
  for (const [t, sx] of all) {
    const tile = svg(t, false), full = svg(t, true);
    await shot(tile, 192, `icon-192${sx}.png`);
    await shot(tile, 512, `icon-512${sx}.png`);
    await shot(full, 180, `apple-touch-icon${sx}.png`);
    if (sx) { await shot(full, 192, `icon-maskable-192${sx}.png`); await shot(full, 512, `icon-maskable-512${sx}.png`); }
  }
  await shot(BADGE, 96, 'badge-96.png');
  await b.close();
})();
