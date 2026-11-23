<?php
/**
 * تنظیمات ← مصرف AI (v7.21): مصرف توکن کاربران و سهمیه‌ی روزانه/ماهانه/کلی (Karlix\Quota).
 * @var array $rows @var int $total @var int $pg @var int $pages @var int $per @var array $perOptions @var string $f @var array $filters
 * @var string $q @var array $defaults @var array $sum @var string $month
 */
$this->scripts[] = 'admin';
$this->scripts[] = 'admin_ai';
$num = static fn($n) => fa_num(number_format((int) $n));
$lim = static fn($l) => $l === null ? 'نامحدود' : $num($l);
$query = array_filter(['q' => $q, 'f' => $f !== 'all' ? $f : null, 'per' => $per !== $perOptions[1] ? $per : null]);
/** نوار و درصد یک دوره */
$cell = static function ($used, $l) use ($num): string {
    $used = (int) $used;
    if ($l === null) {
        return '<div class="aiq-cell"><b>' . e($num($used)) . '</b><small>از نامحدود</small></div>';
    }
    $pct = (int) $l > 0 ? min(100, (int) floor($used * 100 / (int) $l)) : 100;
    $tone = $pct >= 100 ? ' is-full' : ($pct >= 80 ? ' is-near' : '');
    return '<div class="aiq-cell"><b>' . e($num($used)) . '</b> <span class="aiq-bar' . $tone . '" title="' . e(fa_num($pct)) . '٪"><span data-css="width:' . $pct . '%"></span></span><small>از ' . e($num($l)) . ' — ' . e(fa_num($pct)) . '٪</small></div>';
};
$state = static function (array $r): array {
    $full = $near = [];
    foreach (['d' => 'روزانه', 'm' => 'ماهانه', 't' => 'کلی'] as $k => $lbl) {
        $l = $r[$k . '_lim'];
        if ($l === null) {
            continue;
        }
        $u = (int) $r[$k . '_used'];
        if ($u >= (int) $l) {
            $full[] = $lbl;
        } elseif ((int) $l > 0 && $u >= $l * 0.8) {
            $near[] = $lbl;
        }
    }
    return $full ? ['is-full', 'تمام: ' . implode('، ', $full)] : ($near ? ['is-near', 'نزدیک سقف: ' . implode('، ', $near)] : ['', 'عادی']);
};
?>
<div class="page-head"><div><h2><i class="dc-961bcb fa-solid fa-chart-pie"></i> میزان مصرف <span class="badge"><?= e(fa_num($total)) ?></span></h2>
    <p class="small muted mt-1">مصرف بر اساس توکن واقعی گزارش‌شده‌ی سرویس (ورودی + خروجی) در همه‌ی بخش‌های هوش مصنوعی (کارلیکس در یادداشت تسک و دستیار بورد). روز طبق ساعت تهران، ماه طبق تقویم شمسی (<?= e($month) ?>). سقف فضای ذخیره‌سازی بر حسب مگابایت، مستقل از توکن و سهمیه‌ی روزانه‌ی آپلود است؛ صفر مانع آپلود می‌شود. «نامحدود» یعنی بدون سقف. فایل‌های قبلی هم حساب می‌شوند.</p></div></div>

<div class="grid grid-4 mb-2">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-sun"></i></div><div><div class="num"><?= e($num($sum['d'] ?? 0)) ?></div><div class="lbl">توکن امروز</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-calendar"></i></div><div><div class="num"><?= e($num($sum['m'] ?? 0)) ?></div><div class="lbl">توکن این ماه</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-layer-group"></i></div><div><div class="num"><?= e($num($sum['t'] ?? 0)) ?></div><div class="lbl">توکن کل</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-users"></i></div><div><div class="num"><?= e(fa_num((int) ($sum['active'] ?? 0))) ?></div><div class="lbl">کاربر فعال امروز</div></div></div>
</div>

<div class="card mb-2 aiq-defaults"><div class="card-head"><h3><i class="fa-solid fa-sliders"></i> سهمیه‌ی پیش‌فرض</h3></div>
    <p class="small muted">برای کاربران جدید (از لحظه‌ی ساخت حساب) و همه‌ی کاربرانی که سهمیه‌ی اختصاصی ندارند. سوابق مصرف با تغییر سقف پاک نمی‌شود.</p>
    <form method="post" action="<?= e(url('/admin/ai-usage/defaults')) ?>" data-ajax id="f-ai-defaults"><?= csrf_field() ?>
        <div class="aiq-lims">
        <?php foreach (['daily' => 'سقف روزانه (توکن)', 'monthly' => 'سقف ماهانه (توکن)', 'total' => 'سقف کلی (توکن)', 'storage' => 'سقف فضای ذخیره‌سازی (مگابایت)'] as $k => $lbl): $v = $defaults[$k]; ?>
            <div class="aiq-lim field"><label for="ai-def-<?= e($k) ?>"><?= e($lbl) ?></label>
                <input class="input ltr" id="ai-def-<?= e($k) ?>" name="<?= e($k) ?>" inputmode="numeric" value="<?= $v === null ? '' : (int) $v ?>" data-ai-lim<?= $v === null ? ' disabled' : '' ?>>
                <label class="check"><input type="checkbox" name="<?= e($k) ?>_unlimited" value="1" data-ai-unl<?= $v === null ? ' checked' : '' ?>> نامحدود</label></div>
        <?php endforeach; ?>
        </div>
        <div class="flex gap-2 mt-2"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> ذخیره‌ی پیش‌فرض</button></div>
    </form>
</div>

<div class="flex between items-center wrap gap-2 mb-2">
    <div class="tabs" aria-label="فیلتر مصرف هوش مصنوعی"><span class="small muted">هوش مصنوعی:</span><?php foreach ($filters as $k => $l): ?><a class="tab <?= $f === $k ? 'active' : '' ?>" href="<?= e(url('/admin/ai-usage') . '?' . http_build_query(array_filter(['q' => $q, 'f' => $k !== 'all' ? $k : null]))) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
    <form method="get" class="flex gap-2 items-center wrap" role="search">
        <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= e($f) ?>"><?php endif; ?>
        <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="جستجوی نام، موبایل یا ایمیل" aria-label="جستجوی کاربر">
        <label class="flex items-center gap-1 small muted">در هر صفحه
            <select class="input" name="per" data-autosubmit aria-label="تعداد در هر صفحه"><?php foreach ($perOptions as $n): ?><option value="<?= (int) $n ?>" <?= $n === $per ? 'selected' : '' ?>><?= e(fa_num($n)) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-sm"><i class="fa-solid fa-magnifying-glass"></i> جستجو</button>
    </form>
</div>

<div class="card"><div class="table-wrap"><table class="table table-cards">
    <thead><tr><th>کاربر</th><th>امروز</th><th>ماه جاری</th><th>کل</th><th>فضای ذخیره‌سازی</th><th>وضعیت هوش مصنوعی</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted center">کاربری با این شرایط نیست.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): [$tone, $st] = $state($r); $name = trim($r['first_name'] . ' ' . $r['last_name']); ?>
        <tr>
            <td data-label="کاربر"><b><?= e($name !== '' ? $name : 'بدون نام') ?></b><div class="small muted ltr"><?= e(fa_num((string) $r['mobile'])) ?><?= $r['email'] ? ' — ' . e($r['email']) : '' ?></div></td>
            <td data-label="امروز"><?= $cell($r['d_used'], $r['d_lim']) ?></td>
            <td data-label="ماه جاری"><?= $cell($r['m_used'], $r['m_lim']) ?></td>
            <td data-label="کل"><?= $cell($r['t_used'], $r['t_lim']) ?></td>
            <td data-label="فضای ذخیره‌سازی"><b><?= e($r['storage']['used_text']) ?></b><div class="small muted">از <?= e($r['storage']['limit_text']) ?></div><?php if ($r['storage']['custom']): ?><span class="badge purple">اختصاصی</span><?php endif; ?></td>
            <td data-label="وضعیت"><span class="aiq-st <?= e($tone) ?>"><?= e($st) ?></span><?php if ((int) $r['is_custom'] === 1): ?> <span class="badge purple" title="سهمیه‌ی اختصاصی؛ از پیش‌فرض پیروی نمی‌کند">اختصاصی</span><?php endif; ?></td>
            <td><button type="button" class="btn btn-sm" data-ai-edit data-user="<?= e($r['pid']) ?>" data-name="<?= e($name) ?>" data-custom="<?= (int) $r['is_custom'] ?>"
                data-storage="<?= $r['storage']['limit_mb'] === null ? '' : (int) $r['storage']['limit_mb'] ?>" data-daily="<?= $r['d_lim'] === null ? '' : (int) $r['d_lim'] ?>" data-monthly="<?= $r['m_lim'] === null ? '' : (int) $r['m_lim'] ?>" data-total="<?= $r['t_lim'] === null ? '' : (int) $r['t_lim'] ?>"><i class="fa-solid fa-pen"></i> سهمیه</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => $query]); ?></div>

<div class="modal" id="m-ai-quota"><div class="modal-box"><form method="post" action="" data-ajax id="f-ai-quota"><?= csrf_field() ?>
    <div class="modal-head"><h3><i class="fa-solid fa-chart-pie"></i> سهمیه‌ی <span data-ai-name></span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <p class="small muted">هر سقف مستقل است. با ذخیره، سهمیه «اختصاصی» می‌شود و دیگر از پیش‌فرض پیروی نمی‌کند. مصرف تا این لحظه حفظ می‌شود و وضعیت با سقف جدید دوباره حساب می‌شود.</p>
        <?php foreach (['daily' => 'سقف روزانه (توکن)', 'monthly' => 'سقف ماهانه (توکن)', 'total' => 'سقف کلی (توکن)', 'storage' => 'سقف فضای ذخیره‌سازی (مگابایت)'] as $k => $lbl): ?>
        <div class="aiq-lim field"><label for="ai-q-<?= e($k) ?>"><?= e($lbl) ?></label>
            <input class="input ltr" id="ai-q-<?= e($k) ?>" name="<?= e($k) ?>" inputmode="numeric" data-ai-lim>
            <label class="check"><input type="checkbox" name="<?= e($k) ?>_unlimited" value="1" data-ai-unl> نامحدود</label></div>
        <?php endforeach; ?>
        <input type="hidden" name="reset" value="0" data-ai-reset>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button type="button" class="btn btn-ghost" data-ai-default title="حذف سهمیه‌ی اختصاصی؛ کاربر دوباره از پیش‌فرض پیروی می‌کند">بازگشت به پیش‌فرض</button><button class="btn btn-primary">ذخیره</button></div>
</form></div></div>
