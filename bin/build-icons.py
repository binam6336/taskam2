#!/usr/bin/env python3
"""
آیکون‌های کارلیکس (v7.16، بازطراحی بر اساس دموی UI): آیکون‌های خطی ۲۴×۲۴ (هندسه‌ی Lucide، همان مجموعه‌ی دمو).

  python3 bin/build-icons.py

نام کلاس‌ها همان نام‌های Font Awesome قبلی است (fa-solid fa-xxx) تا هیچ view/JS ای عوض نشود؛ هر fa-xxx در
bin/icons/source.json به یک SVG نگاشت شده (src «d:نام» = آیکون خود دمو، بقیه = Lucide، مجوز ISC در LICENSE-lucide.txt).
خروجی public/assets/css/kx-icons.css است: هر آیکون یک mask با data: URI (CSP: img-src 'self' data:) و رنگ currentColor.
بعد از استفاده از یک fa-* تازه در کد: آن را به source.json اضافه کنید و این اسکریپت را اجرا کنید (نام بی‌نگاشت = خطا).
"""
import json, os, re, sys, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = json.load(open(os.path.join(ROOT, 'bin/icons/source.json'), encoding='utf-8'))
SCAN = ['app', 'config', 'public/assets/js']
SKIP = {'fa-solid', 'fa-regular', 'fa-brands', 'fa-spin', 'fa-fw', 'fa-lg', 'fa-xs', 'fa-sm', 'fa-2x', 'fa-spin-reverse', 'fa-pulse'}

used = set()
for d in SCAN:
    for base, _, files in os.walk(os.path.join(ROOT, d)):
        for f in files:
            if f.endswith(('.php', '.js')):
                used.update(re.findall(r'fa-[a-z0-9]+(?:-[a-z0-9]+)*', open(os.path.join(base, f), encoding='utf-8', errors='ignore').read()))
used -= SKIP
missing = sorted(n for n in used if n[3:] not in SRC)
if missing:
    print('بدون نگاشت در bin/icons/source.json:', ' '.join(missing))
    sys.exit(1)

def uri(inner):
    svg = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.8" '
           'stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>')
    return 'url("data:image/svg+xml,' + urllib.parse.quote(svg, safe=' /:=-.,') + '")'

out = ['/* Karlix icons (v7.16) — ساخته‌شده با bin/build-icons.py از bin/icons/source.json؛ دستی ویرایش نکنید. Lucide: ISC. */',
       '.fa, .fa-solid, .fa-regular, .fa-brands { display: inline-block; width: 1em; height: 1em; flex: none; font-style: normal; line-height: 1; vertical-align: -.14em; }',
       '.fa::before, .fa-solid::before, .fa-regular::before, .fa-brands::before { content: ""; display: block; width: 100%; height: 100%; background: currentColor;'
       ' -webkit-mask: var(--kx-ic) center / contain no-repeat; mask: var(--kx-ic) center / contain no-repeat; }',
       '.fa-spin { animation: kx-spin 1s linear infinite; } @keyframes kx-spin { to { transform: rotate(360deg); } }',
       '@media (prefers-reduced-motion: reduce) { .fa-spin { animation-duration: 3s; } }',
       '@media (forced-colors: active) { .fa::before, .fa-solid::before, .fa-regular::before, .fa-brands::before { background: CanvasText; } }']
for name in sorted(SRC):
    out.append('.fa-%s { --kx-ic: %s; }' % (name, uri(SRC[name]['svg'])))
dst = os.path.join(ROOT, 'public/assets/css/kx-icons.css')
open(dst, 'w', encoding='utf-8').write('\n'.join(out) + '\n')
print('kx-icons.css:', len(SRC), 'icons,', os.path.getsize(dst), 'bytes; used in code:', len(used))
