<?php
/**
 * صفحه‌بندی قابل استفاده‌ی مجدد (v7.14؛ اول در تب «همکاران»): قبلی/بعدی (در صفحه‌ی اول/آخر aria-disabled، حذف نمی‌شوند)،
 * شماره‌ی صفحه‌ها با «…» (۱ … ۴ ۵ ۶ … ۲۰)، متن وضعیت «نمایش ۱۱–۲۰ از ۳۰ …» و انتخاب «تعداد در هر صفحه».
 * همه لینک/فرم GET واقعی‌اند (بدون JS هم کار می‌کنند)؛ JS صفحه می‌تواند کلیک‌ها را با [data-pager] بگیرد.
 * اگر همه‌ی ردیف‌ها در یک صفحه جا شوند، فقط وضعیت و انتخاب‌گر می‌مانند.
 *
 * @var int $page @var int $pages @var int $total @var int $per @var int[] $perOptions
 * @var array $query   پارامترهایی که در همه‌ی لینک‌ها می‌مانند (مثل tab, q, sort) — page/per_page را خود partial می‌گذارد
 * @var string $label  aria-label ناوبری، مثل «صفحه‌بندی همکاران»
 * @var string $noun   اسم شمارش‌شونده در متن وضعیت، مثل «همکار»
 * @var string $id     پیشوند شناسه‌ها (برای label/select یکتا)
 * @var string $perKey نام پارامتر تعداد در صفحه (پیش‌فرض per_page)
 */
$perKey = $perKey ?? 'per_page';
$perOptions = $perOptions ?? [10, 20, 50, 100];
$id = $id ?? 'pager';
$noun = $noun ?? 'مورد';
$base = $query;
if ($per !== $perOptions[0]) {
    $base[$perKey] = $per;
}
$link = static fn(int $p): string => '?' . http_build_query($base + ['page' => $p]);
// شماره‌ها: اولی، آخری و یکی قبل/بعد از صفحه‌ی جاری؛ فاصله‌ی یک‌صفحه‌ای با خود شماره پر می‌شود نه «…»
$nums = [];
if ($pages > 1) {
    $keep = array_unique([1, $pages, $page - 1, $page, $page + 1]);
    $keep = array_filter($keep, static fn($n) => $n >= 1 && $n <= $pages);
    sort($keep);
    $prev = 0;
    foreach ($keep as $n) {
        if ($n - $prev === 2) {
            $nums[] = $n - 1;
        } elseif ($n - $prev > 2) {
            $nums[] = null;
        }
        $nums[] = $n;
        $prev = $n;
    }
}
$from = $total ? ($page - 1) * $per + 1 : 0;
$to = min($total, $page * $per);
?>
<div class="pager" data-pager>
    <p class="pager-info" id="<?= e($id) ?>-info">
        <?php if ($total): ?>نمایش <b><?= e(fa_num($from)) ?>–<?= e(fa_num($to)) ?></b> از <b><?= e(fa_num($total)) ?></b> <?= e($noun) ?>
        <?php else: ?>۰ <?= e($noun) ?><?php endif; ?>
    </p>
    <?php if ($pages > 1): ?>
    <nav class="pager-nav" aria-label="<?= e($label ?? 'صفحه‌بندی') ?>">
        <?php if ($page > 1): ?><a class="pager-step" href="<?= e($link($page - 1)) ?>" rel="prev" data-page="<?= $page - 1 ?>"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> قبلی</a>
        <?php else: ?><a class="pager-step" role="link" aria-disabled="true"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> قبلی</a><?php endif; ?>
        <ul class="pager-pages">
        <?php foreach ($nums as $n): ?>
            <?php if ($n === null): ?><li class="pager-gap" aria-hidden="true">…</li>
            <?php elseif ($n === $page): ?><li><a class="pager-num" href="<?= e($link($n)) ?>" aria-current="page" data-page="<?= $n ?>" aria-label="صفحه‌ی <?= e(fa_num($n)) ?>، صفحه‌ی جاری"><?= e(fa_num($n)) ?></a></li>
            <?php else: ?><li><a class="pager-num" href="<?= e($link($n)) ?>" data-page="<?= $n ?>" aria-label="صفحه‌ی <?= e(fa_num($n)) ?>"><?= e(fa_num($n)) ?></a></li><?php endif; ?>
        <?php endforeach; ?>
        </ul>
        <?php if ($page < $pages): ?><a class="pager-step" href="<?= e($link($page + 1)) ?>" rel="next" data-page="<?= $page + 1 ?>">بعدی <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        <?php else: ?><a class="pager-step" role="link" aria-disabled="true">بعدی <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <form class="pager-per" method="get">
        <?php foreach ($query as $k => $v): ?><input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
        <label for="<?= e($id) ?>-per">تعداد در هر صفحه</label>
        <select id="<?= e($id) ?>-per" name="<?= e($perKey) ?>" data-pager-per>
            <?php foreach ($perOptions as $o): ?><option value="<?= (int) $o ?>" <?= $o === $per ? 'selected' : '' ?>><?= e(fa_num($o)) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm">اعمال</button></noscript>
    </form>
</div>
