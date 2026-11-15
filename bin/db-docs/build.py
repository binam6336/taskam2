#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ساخت docs/database.pdf از database/schema.sql + توضیحات bin/db-docs/describe.py.
اجرا از ریشه‌ی پروژه:  python3 bin/db-docs/build.py   (نیاز: Node + Playwright با Chromium)"""
import html, os, re, subprocess, sys, datetime, json

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
sys.path.insert(0, os.path.dirname(__file__))
from describe import TABLES, GROUPS, COMMON  # noqa: E402

sql = open(os.path.join(ROOT, 'database', 'schema.sql'), encoding='utf-8').read()
version = open(os.path.join(ROOT, 'VERSION'), encoding='utf-8').read().strip()
schema_ver = re.search(r"VERSION = '(\d+)'", open(os.path.join(ROOT, 'app', 'Core', 'Schema.php'), encoding='utf-8').read()).group(1)

def parse():
    out = {}
    for m in re.finditer(r"CREATE TABLE `(\w+)` \((.*?)\n\) ENGINE=(\w+)[^;]*;", sql, re.S):
        name, body, engine = m.group(1), m.group(2), m.group(3)
        cols, pk, uniq, keys, fks = [], [], [], [], []
        for line in body.split('\n'):
            line = line.strip().rstrip(',')
            if not line:
                continue
            cm = re.match(r"`(\w+)`\s+(.+)$", line)
            if cm and not line.startswith(('PRIMARY', 'UNIQUE', 'KEY', 'CONSTRAINT')):
                col, rest = cm.group(1), cm.group(2)
                comment = ''
                cmt = re.search(r"COMMENT '((?:[^'\\]|\\.)*)'", rest)
                if cmt:
                    comment = cmt.group(1)
                    rest = rest[:cmt.start()].strip()
                tm = re.match(r"((?:ENUM|SET)\([^)]*\)|[A-Z]+(?:\([^)]*\))?(?: UNSIGNED)?)", rest)
                typ = tm.group(1) if tm else rest.split(' ')[0]
                after = rest[len(typ):]
                null = 'NOT NULL' not in after
                dm = re.search(r"DEFAULT ('(?:[^']*)'|\S+)", after)
                default = dm.group(1) if dm else None
                extra = []
                if 'AUTO_INCREMENT' in after:
                    extra.append('AUTO_INCREMENT')
                if 'ON UPDATE CURRENT_TIMESTAMP' in after:
                    extra.append('ON UPDATE')
                cols.append(dict(name=col, type=typ, null=null, default=default, extra=extra, comment=comment))
                continue
            if line.startswith('PRIMARY KEY'):
                pk = re.findall(r"`(\w+)`", line)
            elif line.startswith('UNIQUE KEY'):
                n, *c = re.findall(r"`(\w+)`", line); uniq.append((n, c))
            elif line.startswith('KEY'):
                n, *c = re.findall(r"`(\w+)`", line); keys.append((n, c))
            elif line.startswith('CONSTRAINT'):
                fm = re.search(r"CONSTRAINT `(\w+)`\s+FOREIGN KEY \(`(\w+)`\)\s+REFERENCES `(\w+)` \(`(\w+)`\)(?:\s+ON DELETE (CASCADE|SET NULL|RESTRICT))?", line)
                if fm:
                    fks.append(dict(name=fm.group(1), col=fm.group(2), ref=fm.group(3), refcol=fm.group(4), on=fm.group(5) or 'RESTRICT'))
        out[name] = dict(cols=cols, pk=pk, uniq=uniq, keys=keys, fks=fks, engine=engine)
    return out

S = parse()
e = html.escape

# بررسی کامل بودن توضیحات
missing = []
grouped = [t for _, ts in GROUPS for t in ts]
for t, d in S.items():
    if t not in TABLES or t not in grouped:
        missing.append('جدول ' + t)
        continue
    for c in d['cols']:
        if c['name'] not in TABLES[t]['cols'] and c['name'] not in COMMON and not c['comment']:
            missing.append(t + '.' + c['name'])
if missing:
    print('توضیح ندارند:', ', '.join(missing)); sys.exit(1)

ON_FA = {'CASCADE': 'حذف ردیف‌های وابسته', 'SET NULL': 'مقدار NULL می‌شود', 'RESTRICT': 'جلوگیری از حذف'}

def type_fa(t):
    u = t.upper()
    if u.startswith('ENUM'):
        return 'یکی از مقادیر ثابت'
    if u.startswith(('INT', 'BIGINT', 'SMALLINT', 'TINYINT')):
        if u.startswith('TINYINT(1)'):
            return 'بولی (۰/۱)'
        return 'عدد صحیح' + (' بدون علامت' if 'UNSIGNED' in u else '')
    if u.startswith(('VARCHAR', 'CHAR')):
        n = re.search(r"\((\d+)\)", u)
        return ('متن تا ' if u.startswith('VARCHAR') else 'متن با طول ثابت ') + (n.group(1) if n else '') + ' نویسه'
    if u.startswith(('TEXT', 'MEDIUMTEXT')):
        return 'متن بلند'
    if u.startswith(('MEDIUMBLOB', 'BLOB')):
        return 'داده‌ی دودویی'
    return {'TIMESTAMP': 'زمان (تاریخ و ساعت)', 'DATETIME': 'تاریخ و ساعت', 'DATE': 'تاریخ', 'TIME': 'ساعت'}.get(u, '')

def col_row(t, c, d):
    desc = TABLES[t]['cols'].get(c['name']) or c['comment'] or COMMON.get(c['name'], '')
    if c['name'] == 'user_id' and c['name'] not in TABLES[t]['cols'] and c['comment']:
        desc = c['comment']
    badges = []
    if c['name'] in d['pk']:
        badges.append('<span class="b pk">PK</span>')
    for n, cs in d['uniq']:
        if c['name'] in cs:
            badges.append('<span class="b uq">UNIQUE</span>'); break
    for fk in d['fks']:
        if fk['col'] == c['name']:
            badges.append(f'<span class="b fk">FK → {e(fk["ref"])}</span>')
    if 'AUTO_INCREMENT' in c['extra']:
        badges.append('<span class="b ai">AUTO</span>')
    dflt = '—' if c['default'] is None else ('NULL' if c['default'] == 'NULL' else c['default'].strip("'") or "''")
    upd = '<div class="tf">به‌روز با هر تغییر</div>' if 'ON UPDATE' in c['extra'] else ''
    typ = c['type']
    if typ.upper().startswith('ENUM'):
        vals = re.findall(r"'([^']*)'", typ)
        typ_html = '<code>ENUM</code><div class="enum">' + ' · '.join(f'<code>{e(v)}</code>' for v in vals) + '</div>'
    else:
        typ_html = f'<code>{e(typ)}</code>'
    return (f'<tr><td class="cn"><code>{e(c["name"])}</code><div class="badges">{"".join(badges)}</div></td>'
            f'<td>{typ_html}<div class="tf">{e(type_fa(c["type"]))}</div></td>'
            f'<td class="nl">{"بله" if c["null"] else "خیر"}</td><td class="df"><code>{e(dflt)}</code>{upd}</td>'
            f'<td class="ds">{e(desc)}</td></tr>')

parts = []
num = 0
toc = []
for gname, ts in GROUPS:
    parts.append(f'<h2 class="grp">{e(gname)}</h2>')
    toc.append(f'<li class="tg">{e(gname)}</li>')
    for t in ts:
        num += 1
        d, info = S[t], TABLES[t]
        toc.append(f'<li><a href="#t-{t}"><span>{num}. {e(info["title"])}</span><code>{t}</code></a></li>')
        flow = ''.join(f'<li>{e(f)}</li>' for f in info.get('flow', []))
        rows = ''.join(col_row(t, c, d) for c in d['cols'])
        idx = []
        if d['pk']:
            idx.append(f'<li><b>کلید اصلی:</b> <code>{e(", ".join(d["pk"]))}</code></li>')
        for n, cs in d['uniq']:
            idx.append(f'<li><b>یکتا</b> <code>{e(n)}</code>: <code>{e(", ".join(cs))}</code> — ترکیب این ستون‌ها تکراری ثبت نمی‌شود.</li>')
        for n, cs in d['keys']:
            idx.append(f'<li><b>ایندکس</b> <code>{e(n)}</code>: <code>{e(", ".join(cs))}</code></li>')
        fks = ''.join(f'<li><code>{e(f["col"])}</code> ← <code>{e(f["ref"])}.{e(f["refcol"])}</code> — با حذف ردیف مرجع: {ON_FA.get(f["on"], f["on"])}</li>' for f in d['fks'])
        refs = [f'<code>{e(o)}.{e(f["col"])}</code>' for o, od in S.items() for f in od['fks'] if f['ref'] == t]
        parts.append(f'''<section class="tbl" id="t-{t}">
  <div class="th"><h3>{num}. {e(info["title"])}</h3><code class="tn">{t}</code><span class="cnt">{len(d["cols"])} ستون</span></div>
  <p class="desc">{e(info["desc"])}</p>
  {f'<div class="flow"><h4>فلو و قواعد</h4><ul>{flow}</ul></div>' if flow else ''}
  <table class="cols"><colgroup><col class="c1"><col class="c2"><col class="c3"><col class="c4"><col class="c5"></colgroup><thead><tr><th>ستون</th><th>نوع داده</th><th>NULL</th><th>پیش‌فرض</th><th>توضیح و کاربرد</th></tr></thead><tbody>{rows}</tbody></table>
  <div class="meta">
    <div><h4>کلیدها و ایندکس‌ها</h4><ul>{"".join(idx)}</ul></div>
    <div><h4>روابط</h4><ul>{fks or '<li>کلید خارجی ندارد.</li>'}{('<li>ارجاع‌دهنده‌ها: ' + '، '.join(refs) + '</li>') if refs else ''}</ul></div>
  </div>
</section>''')

total_cols = sum(len(d['cols']) for d in S.values())
font = 'file://' + os.path.join(ROOT, 'public/assets/vendor/vazirmatn/fonts/webfonts')
today = datetime.date.today().isoformat()
doc = f'''<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>مستندات دیتابیس کارلیکس</title>
<style>
@font-face {{ font-family: V; src: url('{font}/Vazirmatn-Regular.woff2'); font-weight: 400; }}
@font-face {{ font-family: V; src: url('{font}/Vazirmatn-Medium.woff2'); font-weight: 500; }}
@font-face {{ font-family: V; src: url('{font}/Vazirmatn-Bold.woff2'); font-weight: 700; }}
@font-face {{ font-family: V; src: url('{font}/Vazirmatn-ExtraBold.woff2'); font-weight: 800; }}
@page {{ size: A4; margin: 14mm 12mm 16mm; }}
* {{ box-sizing: border-box; }}
body {{ font-family: V, sans-serif; color: #1c2b23; font-size: 9.6pt; line-height: 1.75; margin: 0; }}
code {{ font-family: ui-monospace, 'DejaVu Sans Mono', monospace; font-size: .86em; direction: ltr; unicode-bidi: embed; background: #eef5f0; border-radius: 4px; padding: 0 .3em; color: #14532d; }}
.cover {{ height: 255mm; display: flex; flex-direction: column; justify-content: center; padding: 0 10mm; border-right: 6px solid #16a34a; page-break-after: always; background: linear-gradient(160deg, #f0fdf4, #fff 60%); }}
.cover .k {{ color: #16a34a; font-weight: 700; font-size: 12pt; }}
.cover h1 {{ font-size: 30pt; margin: .2em 0; color: #14532d; }}
.cover p {{ font-size: 11pt; color: #3f5a4a; }}
.stats {{ display: flex; gap: 10px; margin-top: 18px; }}
.stats div {{ border: 1px solid #d5e9dc; border-radius: 10px; padding: 10px 16px; background: #fff; text-align: center; }}
.stats b {{ display: block; font-size: 18pt; color: #15803d; }}
h2 {{ color: #14532d; border-bottom: 2px solid #86efac; padding-bottom: 4px; margin: 0 0 10px; font-size: 15pt; }}
h2.grp {{ page-break-before: always; font-size: 17pt; }}
.toc {{ page-break-before: always; }}
.intro li {{ margin-bottom: 4px; }}
.toc ul {{ list-style: none; padding: 0; columns: 2; column-gap: 24px; font-size: 10.5pt; line-height: 2.1; }}
.toc li {{ break-inside: avoid; }}
.toc li.tg {{ font-weight: 800; color: #15803d; margin-top: 8px; }}
.toc a {{ display: flex; justify-content: space-between; gap: 8px; color: inherit; text-decoration: none; border-bottom: 1px dotted #cfe3d6; padding: 2px 0; }}
section.tbl {{ margin: 0 0 18px; break-inside: auto; }}
.th {{ display: flex; align-items: center; gap: 10px; background: #14532d; color: #fff; border-radius: 8px 8px 0 0; padding: 6px 12px; }}
.th h3 {{ margin: 0; font-size: 12.5pt; }}
.th .tn {{ background: #fff; color: #14532d; font-size: 10pt; }}
.th .cnt {{ margin-right: auto; font-size: 8.5pt; opacity: .85; }}
.desc {{ background: #f0fdf4; border: 1px solid #d5e9dc; border-top: 0; margin: 0; padding: 6px 12px; }}
.flow {{ border: 1px solid #d5e9dc; border-top: 0; padding: 4px 12px 2px; }}
.flow h4, .meta h4 {{ margin: 4px 0; color: #15803d; font-size: 9.5pt; }}
.flow ul, .meta ul {{ margin: 0 0 4px; padding-right: 18px; }}
table.cols {{ width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 8.6pt; table-layout: fixed; }}
table.cols col.c1 {{ width: 20%; }} table.cols col.c2 {{ width: 15%; }} table.cols col.c3 {{ width: 6%; }} table.cols col.c4 {{ width: 13%; }} table.cols col.c5 {{ width: 46%; }}
table.cols th {{ background: #dcfce7; color: #14532d; text-align: right; padding: 4px 6px; border: 1px solid #c7e3cf; }}
table.cols td {{ border: 1px solid #dfece3; padding: 4px 6px; vertical-align: top; }}
table.cols tr {{ break-inside: avoid; }}
table.cols tbody tr:nth-child(even) td {{ background: #fafdfb; }}
td.cn code {{ font-weight: 700; word-break: break-all; }} td.df code {{ word-break: break-all; }}
td.nl {{ text-align: center; }}
.tf {{ color: #6b8577; font-size: 7.8pt; }}
.enum {{ margin-top: 2px; line-height: 1.9; }}
.badges {{ margin-top: 2px; }}
.b {{ display: inline-block; font-size: 6.8pt; border-radius: 4px; padding: 0 4px; margin: 1px 0 1px 3px; direction: ltr; font-weight: 700; }}
.pk {{ background: #fde68a; color: #78350f; }} .uq {{ background: #e0e7ff; color: #3730a3; }} .fk {{ background: #fee2e2; color: #991b1b; }} .ai {{ background: #e5e7eb; color: #374151; }}
.meta {{ display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 6px; font-size: 8.4pt; break-inside: avoid; }}
.meta > div {{ border: 1px solid #e3eee7; border-radius: 6px; padding: 2px 10px; }}
</style></head><body>
<div class="cover">
  <div class="k">مستندات فنی</div>
  <h1>دیتابیس کارلیکس (Karlix، نام قبلی: تسکام)</h1>
  <p>همه‌ی جدول‌ها و ستون‌ها: نوع داده، مقدار پیش‌فرض، کلیدها و روابط، و فلو و کاربرد هر بخش در برنامه.</p>
  <div class="stats"><div><b>{len(S)}</b>جدول</div><div><b>{total_cols}</b>ستون</div><div><b>{sum(len(d["fks"]) for d in S.values())}</b>کلید خارجی</div></div>
  <p style="margin-top:22px;font-size:9.5pt">نسخه‌ی برنامه: <code>{e(version)}</code> — نسخه‌ی ساختار دیتابیس (Schema): <code>{e(schema_ver)}</code> — تاریخ ساخت: <code>{today}</code><br>
  منبع: <code>database/schema.sql</code> و <code>bin/db-docs/describe.py</code>؛ ساخت دوباره: <code>python3 bin/db-docs/build.py</code></p>
</div>
<div class="intro">
  <h2>قواعد کلی</h2>
  <ul>
    <li><b>موتور و نویسه‌ها:</b> همه‌ی جدول‌ها InnoDB با <code>utf8mb4_unicode_ci</code> (پشتیبانی کامل فارسی و ایموجی). سازگار با MySQL 5.7+ و MariaDB 10.3+.</li>
    <li><b>شناسه‌ها:</b> <code>INT UNSIGNED AUTO_INCREMENT</code> (جدول‌های پرحجم مثل پیام‌ها و رویدادها <code>BIGINT</code>). استثنا: <code>texts.id</code> رشته‌ای و <code>user_sessions.id_hash</code> هش.</li>
    <li><b>زمان‌ها:</b> ستون‌های <code>TIMESTAMP/DATETIME</code> به وقت سرور (DB_TIMEZONE)؛ چند ستون Unix time (عدد ثانیه) هستند و در توضیح ستون مشخص شده‌اند. نمایش در رابط کاربری شمسی است.</li>
    <li><b>بولی‌ها:</b> <code>TINYINT(1)</code> با ۰/۱. <b>JSON:</b> در ستون‌های <code>TEXT</code> (مثل دسترسی‌ها) ذخیره و در PHP اعتبارسنجی می‌شود.</li>
    <li><b>روابط:</b> کلیدهای خارجی رفتار حذف مشخص دارند: <code>CASCADE</code> (حذف وابسته‌ها) یا <code>SET NULL</code> (مثلاً مسئول تسک وقتی کاربر حذف می‌شود).</li>
    <li><b>امنیت داده:</b> رمز عبور با Argon2id؛ توکن‌های API، شناسه‌ی نشست‌ها و کلیدهای محدودیت نرخ فقط به‌صورت SHA-256؛ متن پیام‌های چت و نام فایل‌هایشان با AES-256-GCM رمزشده؛ فایل‌های آپلودی بیرون از پوشه‌ی عمومی با نام تصادفی.</li>
    <li><b>تغییرات ساختار:</b> فایل‌های <code>database/migrations/NNN_*.sql</code>؛ <code>App\\Core\\Schema</code> تغییرات افزایشی (ستون، جدول، مقدار ENUM و اصلاح داده‌ی یک‌باره) را خودکار اعمال می‌کند (<code>SCHEMA_AUTO</code>) یا با <code>php bin/migrate.php</code>. نسخه‌ی اعمال‌شده با فایل <code>storage/cache/schema-NNN</code> مشخص می‌شود.</li>
    <li><b>نام‌گذاری:</b> لیبل‌ها در دیتابیس <code>subjects</code> و <code>subject_id</code> نام دارند (سازگاری با نسخه‌ی قدیم).</li>
    <li><b>راهنمای نشانه‌ها در جدول ستون‌ها:</b> <span class="b pk">PK</span> کلید اصلی، <span class="b uq">UNIQUE</span> یکتا، <span class="b fk">FK</span> کلید خارجی، <span class="b ai">AUTO</span> خودافزا.</li>
  </ul>
  <div class="toc"><h2>فهرست جدول‌ها</h2><ul>{"".join(toc)}</ul></div>
</div>
{"".join(parts)}
</body></html>'''

out_html = os.path.join('/tmp', 'karlix-db-docs.html')
open(out_html, 'w', encoding='utf-8').write(doc)
pdf = os.path.join(ROOT, 'docs', 'database.pdf')
subprocess.run(['node', os.path.join(os.path.dirname(__file__), 'render.js'), out_html, pdf, version], check=True)
print('ok:', pdf, len(S), 'tables', total_cols, 'columns')
