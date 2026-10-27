<?php
/**
 * منوی سایدبار (پنل کاربر و ادمین).
 * @var array  $menu   آیتم‌ها: [key, path, icon, label] یا ['group' => id, 'icon' => …, 'label' => …, 'items' => [...]]
 *                     آیتم «به‌زودی» داخل گروه: ['soon' => true, 'icon' => …, 'label' => …]
 * @var string $active کلید صفحه‌ی فعلی
 * @var array  $badges کلیدهایی که نشان شمارنده دارند => مقدار اولیه
 * @var array  $badgeAs (اختیاری) کلید صفحه => کلید شمارنده در /live/poll، وقتی با هم فرق دارند
 */
$badgeAs = $badgeAs ?? [];
$badge = static function (string $key) use ($badges, $badgeAs): string {
    if (!array_key_exists($key, $badges)) {
        return '';
    }
    $n = (int) $badges[$key];
    return '<span class="badge-count ' . ($n > 0 ? '' : 'zero') . '" data-badge="' . e($badgeAs[$key] ?? $key) . '">' . e(fa_num($n)) . '</span>';
};
?>
<nav class="nav" aria-label="منوی اصلی">
<?php foreach ($menu as $item): ?>
<?php if (!isset($item['group'])): [$key, $path, $icon, $label] = $item; ?>
    <a href="<?= e(url($path)) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span><?= $badge($key) ?></a>
<?php else:
    $keys = array_column(array_filter($item['items'], static fn($c) => !isset($c['soon'])), 0);
    $sumKeys = array_values(array_filter($keys, static fn($k) => array_key_exists($k, $badges)));
    $sumInit = array_sum(array_map(static fn($k) => (int) $badges[$k], $sumKeys));
    $sum = array_map(static fn($k) => $badgeAs[$k] ?? $k, $sumKeys);
    $current = in_array($active, $keys, true);
    $gid = 'nav-g-' . $item['group'];
?>
    <div class="nav-group<?= $current ? ' current' : '' ?>" data-nav-group>
        <button type="button" class="nav-parent" aria-expanded="false" aria-controls="<?= e($gid) ?>">
            <i class="fa-solid <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
            <?php if ($sum): ?><span class="badge-count <?= $sumInit > 0 ? '' : 'zero' ?>" data-badge-sum="<?= e(implode(',', $sum)) ?>"><?= e(fa_num($sumInit)) ?></span><?php endif; ?>
            <i class="fa-solid fa-chevron-left nav-caret" aria-hidden="true"></i>
        </button>
        <div class="nav-flyout" id="<?= e($gid) ?>" role="menu" aria-label="<?= e($item['label']) ?>"><div class="nav-flyout-wrap">
            <div class="nav-flyout-head">
                <span><i class="fa-solid <?= e($item['icon']) ?>"></i><?= e($item['label']) ?></span>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-nav-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <?php foreach ($item['items'] as $i => $c): ?>
                <?php if (isset($c['soon'])): ?>
                    <span class="nav-sub soon" role="menuitem" aria-disabled="true" style="--i:<?= $i ?>"><span class="nav-ico"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><span><?= e($c['label']) ?></span><span class="soon-chip">به‌زودی</span></span>
                <?php else: [$key, $path, $icon, $label] = $c; ?>
                    <a href="<?= e(url($path)) ?>" class="nav-sub<?= $active === $key ? ' active' : '' ?>" role="menuitem" style="--i:<?= $i ?>"><span class="nav-ico"><i class="fa-solid <?= e($icon) ?>"></i></span><span><?= e($label) ?></span><?= $badge($key) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div></div>
    </div>
<?php endif; ?>
<?php endforeach; ?>
</nav>
