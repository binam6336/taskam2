#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""فونت PDF گزارش‌های دستیار بورد (v7.20) از همان فونت پنل (Noto Sans Arabic، woff2 متغیر در public/assets/vendor):
زیرمجموعه‌های عربی + لاتین یکی و به دو وزن ثابت ۴۰۰ و ۷۰۰ تبدیل می‌شوند (resources/fonts/*.ttf، بیرون از webroot)
و جدول cmap + پهنای گلیف‌ها در یک JSON. شکل‌دهی فارسی در PHP با نویسه‌های Presentation Forms (FB50–FEFF) انجام می‌شود
(GSUB این فونت حروف را به اسکلت + نقطه می‌شکند که بدون GPOS قابل استفاده نیست؛ گلیف‌های Presentation Forms ترکیبی و کامل‌اند).
اجرا از ریشه‌ی پروژه:  python3 bin/build-pdf-fonts.py   (نیاز: fontTools + brotli)"""
import json, os, io
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer
from fontTools import merge

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SRC = os.path.join(ROOT, 'public', 'assets', 'vendor', 'noto-sans-arabic', 'fonts')
OUT = os.path.join(ROOT, 'resources', 'fonts')
os.makedirs(OUT, exist_ok=True)


def instance(path, wght, tmp):
    f = TTFont(path)
    f.flavor = None
    f = instancer.instantiateVariableFont(f, {'wght': wght})
    f.save(tmp)
    return tmp


def build(wght, name):
    a = instance(os.path.join(SRC, 'NotoSansArabic-arabic.woff2'), wght, '/tmp/_kx_a.ttf')
    l = instance(os.path.join(SRC, 'NotoSansArabic-latin.woff2'), wght, '/tmp/_kx_l.ttf')
    m = merge.Merger()
    font = m.merge([a, l])
    for t in ('DSIG', 'STAT', 'MVAR', 'HVAR', 'fvar', 'gvar', 'avar'):
        if t in font:
            del font[t]
    path = os.path.join(OUT, name + '.ttf')
    font.save(path)
    font = TTFont(path)
    order = font.getGlyphOrder()
    gid = {g: i for i, g in enumerate(order)}
    cmap = {str(cp): gid[g] for cp, g in font.getBestCmap().items() if g in gid}
    hmtx = font['hmtx']
    widths = [hmtx[g][0] for g in order]
    head, hhea, os2 = font['head'], font['hhea'], font['OS/2']
    meta = {
        'file': name + '.ttf', 'upm': head.unitsPerEm, 'ascent': hhea.ascent, 'descent': hhea.descent,
        'cap': getattr(os2, 'sCapHeight', 700) or 700, 'bbox': [head.xMin, head.yMin, head.xMax, head.yMax],
        'cmap': cmap, 'widths': widths,
    }
    with open(os.path.join(OUT, name + '.json'), 'w', encoding='utf-8') as fh:
        json.dump(meta, fh, separators=(',', ':'))
    print(name, len(order), 'glyphs', os.path.getsize(path) // 1024, 'KB')


build(400, 'NotoSansArabic-Regular')
build(700, 'NotoSansArabic-Bold')
