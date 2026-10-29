<?php
/** @var array $menu @var array $pages @var bool $custom @var string $linkIcon */
$this->scripts[] = 'sidebar_editor';
$this->boot['sb'] = ['menu' => $menu, 'pages' => $pages, 'linkIcon' => $linkIcon];
?>
<div class="callout mb-2"><i class="fa-solid fa-circle-info"></i> چیدمان سایدبار پنل کاربران. گزینه‌ها را با <i class="fa-solid fa-grip-vertical"></i> بکشید و جابه‌جا کنید (داخل گروه‌ها یا بیرون از آن‌ها). هر گزینه یا یک <b>صفحه</b>‌ی پنل است یا یک <b>لینک</b> خارجی که در تب جدید باز می‌شود. تغییرات بعد از «ذخیره» برای همه‌ی کاربران اعمال می‌شود.</div>
<div class="card">
    <div class="card-head">
        <h3><i class="fa-solid fa-bars-staggered"></i> گزینه‌های سایدبار</h3>
        <?php if ($custom): ?><span class="badge yellow">چیدمان سفارشی</span><?php else: ?><span class="badge">چیدمان پیش‌فرض</span><?php endif; ?>
    </div>
    <ul class="sb-list sb-root" id="sb-root" aria-label="گزینه‌های سایدبار"></ul>
    <div class="flex gap-1 mt-2 sb-actions">
        <button type="button" class="btn" id="sb-add"><i class="fa-solid fa-plus"></i> افزودن گزینه</button>
        <div class="grow"></div>
        <?php if ($custom): ?><button type="button" class="btn" data-post="<?= e(url('/admin/content/sidebar/reset')) ?>" data-confirm="چیدمان سایدبار به حالت پیش‌فرض برگردد؟ تغییرات ذخیره‌شده پاک می‌شوند." data-danger="0"><i class="fa-solid fa-rotate-left"></i> چیدمان پیش‌فرض</button><?php endif; ?>
        <button type="button" class="btn btn-primary" id="sb-save"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button>
    </div>
</div>
