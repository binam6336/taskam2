<?php /* جستجوی نوار بالا (v7.1): همان پالت Ctrl/⌘+K سایدبار (کلاس sb-search در app.js) */ ?>
<?php $tbG = !empty($tbSearch); /* پنل کاربر: جستجوی سراسری محتوا (v7.13)؛ پنل مدیر: پرش به صفحه */ ?>
<button type="button" class="sb-search tb-search" aria-haspopup="dialog" aria-keyshortcuts="Control+K Meta+K" aria-label="<?= $tbG ? 'جستجوی سراسری' : 'جستجو و پرش سریع' ?>"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><span><?= $tbG ? 'جستجو در تسک‌ها، یادداشت‌ها، پروژه‌ها…' : 'جستجو و پرش سریع…' ?></span><kbd dir="ltr">Ctrl K</kbd></button>
