#!/usr/bin/env python3
"""
زیرمجموعه‌ی Font Awesome (UI-018، v7.3): فقط آیکون‌هایی که در کد به‌کار رفته‌اند.

  python3 bin/build-icons.py

کد (app/، config/، public/assets/js/) را برای نام‌های fa-* می‌گردد و از vendor/fontawesome/css/all.min.css
فایل vendor/fontawesome/css/karlix-icons.css و فونت‌های woff2 کوچک‌شده‌ی webfonts/karlix-*.woff2 را می‌سازد.
بعد از اضافه کردن آیکون تازه در کد، این اسکریپت را دوباره اجرا و خروجی‌ها را کامیت کنید (نیاز: pip install fonttools brotli).
"""
import os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FA = os.path.join(ROOT, 'public/assets/vendor/fontawesome')
SCAN = ['app', 'config', 'public/assets/js']
# آیکون‌هایی که فقط در داده (مثلاً محتوای ادمین) ممکن است بیایند — همیشه نگه داشته می‌شوند
EXTRA = {'fa-folder', 'fa-link', 'fa-circle', 'fa-question'}

used = set(EXTRA)
for d in SCAN:
    for base, _, files in os.walk(os.path.join(ROOT, d)):
        for f in files:
            if f.endswith(('.php', '.js')):
                used.update(re.findall(r'fa-[a-z0-9]+(?:-[a-z0-9]+)*', open(os.path.join(base, f), encoding='utf-8', errors='ignore').read()))

css = open(os.path.join(FA, 'css/all.min.css'), encoding='utf-8').read()
header = css[:css.index('*/') + 2] if css.startswith('/*') else ''
body = css[len(header):]
out, cps = [], {'solid': set(), 'regular': set(), 'brands': set()}
icon_cp = {}
for sel, decl in re.findall(r'([^{}]+)\{([^{}]*)\}', body):
    sel = sel.strip()
    if sel.startswith('@font-face') or '@font-face' in sel:
        continue
    parts = [p.strip() for p in sel.split(',')]
    icon_parts = [p for p in parts if re.fullmatch(r'\.fa-[a-z0-9-]+:(?:before|after)', p)]
    if icon_parts and len(icon_parts) == len(parts):
        keep = [p for p in parts if p.split(':')[0][1:] in used]
        if not keep:
            continue
        m = re.search(r'content:"\\\\?([0-9a-f]+)"', decl)
        if m:
            for p in keep:
                icon_cp[p.split(':')[0][1:]] = int(m.group(1), 16)
        out.append(','.join(keep) + '{' + decl + '}')
    else:
        out.append(sel + '{' + decl + '}')

# مستقیماً از محتوای CSS آیکون‌های نگه‌داشته؛ هر سه سبک (در کد fa-solid/fa-regular/fa-brands جدا مشخص می‌شود)
allcp = set(icon_cp.values())
faces = {
    'solid': ('fa-solid-900.woff2', '"Font Awesome 6 Free"', 900),
    'regular': ('fa-regular-400.woff2', '"Font Awesome 6 Free"', 400),
    'brands': ('fa-brands-400.woff2', '"Font Awesome 6 Brands"', 400),
}
try:
    from fontTools import subset
    from fontTools.ttLib import TTFont
except ImportError:
    sys.exit('fonttools لازم است: pip install fonttools brotli')
ff = []
for key, (src, fam, w) in faces.items():
    f = TTFont(os.path.join(FA, 'webfonts', src))
    have = set(f.getBestCmap().keys()) & allcp
    if not have:
        continue
    opts = subset.Options(); opts.flavor = 'woff2'; opts.layout_features = ['*']; opts.notdef_outline = True
    sub = subset.Subsetter(opts); sub.populate(unicodes=sorted(have)); sub.subset(f)
    # نام فایل با هش محتوا تا کش طولانی مرورگر (immutable) بعد از ساخت دوباره کهنه نماند
    import hashlib, glob, io
    f.flavor = 'woff2'; buf = io.BytesIO(); f.save(buf); data = buf.getvalue()
    for old in glob.glob(os.path.join(FA, 'webfonts', 'karlix-%s*.woff2' % key)):
        os.remove(old)
    name = 'karlix-%s.%s.woff2' % (key, hashlib.sha1(data).hexdigest()[:10])
    open(os.path.join(FA, 'webfonts', name), 'wb').write(data)
    ff.append('@font-face{font-family:%s;font-style:normal;font-weight:%d;font-display:block;src:url(../webfonts/%s) format("woff2")}' % (fam, w, name))

res = header + '\n/* Karlix subset — ساخته‌شده با bin/build-icons.py؛ دستی ویرایش نکنید. %d آیکون */\n' % len(icon_cp) + ''.join(out) + ''.join(ff) + '\n'
open(os.path.join(FA, 'css/karlix-icons.css'), 'w', encoding='utf-8').write(res)
print('icons:', len(icon_cp), 'css:', len(res), 'bytes')
import glob
for p in sorted(glob.glob(os.path.join(FA, 'webfonts', 'karlix-*.woff2'))):
    print(os.path.basename(p), os.path.getsize(p), 'bytes')
missing = sorted(n for n in used if n not in icon_cp and re.fullmatch(r'fa-[a-z0-9-]+', n))
print('names without glyph (helpers/sizes/typos):', ' '.join(missing[:60]))
