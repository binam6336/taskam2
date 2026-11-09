<?php
/**
 * سایدبار مشترک پنل کاربر و ادمین (v5.8، ظاهر کارتی v7.1): برند + جمع کردن (rail)، منوی ساده با پاپ‌آپ زیرمنو، کارت کاربر و خروج.
 * دکمه‌ی جستجو/پالت (Ctrl+K) از v7.1 در نوار بالاست (partials/topbar_search.php)؛ خود پالت همین‌جاست.
 * رفتار در app.js (بخش «سایدبار v5.8»)، کلاس sb-rail روی <html> را assets/js/rail.js پیش از رندر می‌گذارد.
 * @var array  $menu    آیتم‌ها: [key, path, icon, label] | ['group' => id, 'icon', 'label', 'items' => [...]] | ['soon' => true, …] | ['link' => url, …]
 * @var string $active  کلید صفحه‌ی فعلی
 * @var array  $badges  کلید => مقدار اولیه‌ی شمارنده @var array $badgeAs (اختیاری) کلید صفحه => کلید /live/poll
 * @var array  $sb      ['home' => url, 'title', 'sub', 'user' => ردیف کاربر, 'user_sub', 'user_sub_ltr' => bool, 'logout' => متن, 'desc' => [key|group:id => توضیح]]
 * توضیح زیر نام: کلید desc هر آیتم منو (پنل کاربر: از «محتوا ← سایدبار» در ادمین، SidebarModel::nav) و در غیر این صورت $sb['desc']
 */
$badgeAs = $badgeAs ?? [];
$desc = $sb['desc'] ?? [];
$su = $sb['user'];
$suName = full_name($su);
$cnt = static fn(int $n): string => $n > 0 ? '، ' . fa_num($n) . ' مورد' : '';
$badge = static function (string $key) use ($badges, $badgeAs, $cnt): string {
    if (!array_key_exists($key, $badges)) {
        return '';
    }
    $n = (int) $badges[$key];
    return '<span class="sb-badge' . ($n > 0 ? '' : ' zero') . '" data-badge="' . e($badgeAs[$key] ?? $key) . '" aria-hidden="true">' . e(fa_num($n)) . '</span><span class="sr-only sb-sr">' . e($cnt($n)) . '</span>';
};
$pops = [];
?>
<a class="skip-link" href="#main-content">پرش به محتوای اصلی</a>
<aside class="sidebar" id="sidebar">
    <div class="sb-top">
        <a class="brand" href="<?= e($sb['home']) ?>" aria-label="<?= e($sb['title']) ?> — صفحه اصلی"><span class="logo" aria-hidden="true"><?= logo_svg() ?></span><span class="sb-brand-t"><?= e($sb['title']) ?><small><?= e($sb['sub']) ?></small></span></a>
        <button type="button" class="sb-icon-btn sb-rail-btn" aria-pressed="false" aria-label="جمع کردن منو"><i class="fa-solid fa-angles-right" aria-hidden="true"></i></button>
    </div>

    <nav class="sb-nav" aria-label="منوی اصلی">
        <ul class="sb-list">
            <span class="sb-hl" aria-hidden="true"></span>
<?php foreach ($menu as $item): ?>
<?php if (isset($item['link'])): ?>
            <li><a class="sb-it" href="<?= e($item['link']) ?>" target="_blank" rel="noopener noreferrer"><span class="sb-ic"><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i></span><span class="sb-lbl"><?= e($item['label']) ?></span></a></li>
<?php elseif (isset($item['soon'])): ?>
            <li><span class="sb-it is-soon" aria-disabled="true"><span class="sb-ic"><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i></span><span class="sb-lbl"><?= e($item['label']) ?> <small class="soon-chip">به‌زودی</small></span></span></li>
<?php elseif (!isset($item['group'])): [$key, $path, $icon, $label] = $item; $on = $active === $key; ?>
            <li><a class="sb-it<?= $on ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $on ? ' aria-current="page"' : '' ?><?= $key === 'board' ? ' data-board-pick' : '' ?>><span class="sb-ic"><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i></span><span class="sb-lbl"><?= e($label) ?></span><?= $badge($key) ?></a></li>
<?php else:
    $keys = array_column(array_filter($item['items'], static fn($c) => isset($c[0])), 0);
    $sumKeys = array_values(array_filter($keys, static fn($k) => array_key_exists($k, $badges)));
    $sumInit = array_sum(array_map(static fn($k) => (int) $badges[$k], $sumKeys));
    $sum = array_map(static fn($k) => $badgeAs[$k] ?? $k, $sumKeys);
    $current = in_array($active, $keys, true);
    $gid = 'sb-pop-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $item['group']);
    $pops[] = ['id' => $gid, 'item' => $item];
?>
            <li><button type="button" class="sb-it<?= $current ? ' is-current-group' : '' ?>" aria-expanded="false" aria-haspopup="dialog" aria-controls="<?= e($gid) ?>"><span class="sb-ic"><i class="fa-solid <?= e($item['icon']) ?>" aria-hidden="true"></i></span><span class="sb-lbl"><?= e($item['label']) ?></span><?php if ($sum): ?><span class="sb-badge<?= $sumInit > 0 ? '' : ' zero' ?>" data-badge-sum="<?= e(implode(',', $sum)) ?>" aria-hidden="true"><?= e(fa_num($sumInit)) ?></span><span class="sr-only sb-sr"><?= e($cnt($sumInit)) ?></span><?php endif; ?><?php if ($current): ?><span class="sr-only">، بخش فعلی</span><?php endif; ?><i class="fa-solid fa-chevron-left sb-chev" aria-hidden="true"></i></button></li>
<?php endif; ?>
<?php endforeach; ?>
        </ul>
    </nav>

    <div class="sb-foot"><div class="sb-me">
        <span class="avatar sm sb-av"><?php if (!empty($su['avatar'])): ?><img loading="lazy" decoding="async" src="<?= e(avatar_url($su['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($suName)) ?><?php endif; ?></span>
        <div class="sb-me-t"><b class="truncate"><?= e($suName) ?></b><small<?= !empty($sb['user_sub_ltr']) ? ' dir="ltr"' : '' ?>><?= e((string) $sb['user_sub']) ?></small></div>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit" class="sb-icon-btn sb-out" aria-label="<?= e($sb['logout']) ?>"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></button></form>
    </div></div>

<?php foreach ($pops as ['id' => $gid, 'item' => $item]): ?>
    <div class="sb-pop" id="<?= e($gid) ?>" role="dialog" aria-modal="false" aria-labelledby="<?= e($gid) ?>-t" hidden>
        <div class="sb-pop-hd">
            <span class="sb-pop-hic" aria-hidden="true"><i class="fa-solid <?= e($item['icon']) ?>"></i></span>
            <div class="min0"><b id="<?= e($gid) ?>-t"><?= e($item['label']) ?></b><?php $gd = $item['desc'] ?? ($desc['group:' . $item['group']] ?? ''); if ($gd !== ''): ?><small><?= e($gd) ?></small><?php endif; ?></div>
            <button type="button" class="sb-pop-x" aria-label="بستن <?= e($item['label']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <ul class="sb-pop-ul">
        <?php foreach ($item['items'] as $c):
            if (isset($c['link'])): ?>
            <li><a class="sb-pop-a" href="<?= e($c['link']) ?>" target="_blank" rel="noopener noreferrer"><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><span class="sb-pop-t"><b><?= e($c['label']) ?></b><small><?= e(($c['desc'] ?? '') !== '' ? $c['desc'] : 'لینک خارجی (تب جدید)') ?></small></span><i class="fa-solid fa-arrow-left sb-pop-go" aria-hidden="true"></i></a></li>
            <?php elseif (isset($c['soon'])): ?>
            <li><span class="sb-pop-a is-soon" aria-disabled="true"><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><span class="sb-pop-t"><b><?= e($c['label']) ?></b><small>به‌زودی<?= ($c['desc'] ?? '') !== '' ? ' · ' . e($c['desc']) : '' ?></small></span></span></li>
            <?php else: [$key, $path, $icon, $label] = $c; $on = $active === $key; $cd = $c['desc'] ?? ($desc[$key] ?? ''); ?>
            <li><a class="sb-pop-a<?= $on ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $on ? ' aria-current="page"' : '' ?><?= $key === 'board' ? ' data-board-pick' : '' ?>><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></span><span class="sb-pop-t"><b><?= e($label) ?><?php if ($on): ?> <em class="sb-pop-now">صفحه فعلی</em><?php endif; ?></b><?php if ($cd !== ''): ?><small><?= e($cd) ?></small><?php endif; ?></span><?= $badge($key) ?><i class="fa-solid fa-arrow-left sb-pop-go" aria-hidden="true"></i></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>
    <div class="sb-tooltip" id="sb-tooltip" role="tooltip" hidden></div>
</aside>
<dialog class="sb-palette" aria-label="جستجو و پرش سریع">
    <div class="sb-pal-in"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="text" class="sb-pal-q" role="combobox" aria-expanded="true" aria-controls="sb-pal-list" aria-autocomplete="list" aria-activedescendant="" aria-label="جستجوی صفحه یا عملیات" placeholder="نام صفحه یا عملیات…" autocomplete="off" spellcheck="false"></div>
    <ul class="sb-pal-list" id="sb-pal-list" role="listbox" aria-label="نتایج"></ul>
    <p class="sb-pal-empty" hidden>چیزی پیدا نشد.</p>
</dialog>
