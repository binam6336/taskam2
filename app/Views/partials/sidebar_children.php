<?php /* زیرمنوی مشترک؛ زیرگروه‌ها فقط در منوی مدیر تعریف می‌شوند. */ ?>
        <?php foreach ($sidebarChildren as $c):
            if (isset($c['section'])):
                $childActive = in_array($active, $sidebarKeys($c['items']), true); ?>
            <li><details class="sb-subgroup"<?= $childActive ? ' open' : '' ?>><summary><i class="fa-solid <?= e($c['icon']) ?>" aria-hidden="true"></i><span><?= e($c['label']) ?></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></summary><ul class="sb-pop-ul sb-sub-list">
                <?php $nestedChildren = $sidebarChildren; $sidebarChildren = $c['items']; require __DIR__ . '/sidebar_children.php'; $sidebarChildren = $nestedChildren; ?>
            </ul></details></li>
            <?php elseif (isset($c['link'])): ?>
            <li><a class="sb-pop-a" href="<?= e($c['link']) ?>" target="_blank" rel="noopener noreferrer"><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><span class="sb-pop-t"><b><?= e($c['label']) ?></b><small><?= e(($c['desc'] ?? '') !== '' ? $c['desc'] : 'لینک خارجی (تب جدید)') ?></small></span><i class="fa-solid fa-arrow-left sb-pop-go" aria-hidden="true"></i></a></li>
            <?php elseif (isset($c['soon'])): ?>
            <li><span class="sb-pop-a is-soon" aria-disabled="true"><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($c['icon']) ?>"></i></span><span class="sb-pop-t"><b><?= e($c['label']) ?></b><small>به‌زودی<?= ($c['desc'] ?? '') !== '' ? ' · ' . e($c['desc']) : '' ?></small></span></span></li>
            <?php else: [$key, $path, $icon, $label] = $c; $on = $active === $key; $cd = $c['desc'] ?? ($desc[$key] ?? ''); ?>
            <li><a class="sb-pop-a<?= $on ? ' active' : '' ?>" href="<?= e(url($path)) ?>"<?= $on ? ' aria-current="page"' : '' ?><?= $key === 'board' ? ' data-board-pick' : '' ?>><span class="sb-pop-ic" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></span><span class="sb-pop-t"><b><?= e($label) ?><?php if ($on): ?> <em class="sb-pop-now">صفحه فعلی</em><?php endif; ?></b><?php if ($cd !== ''): ?><small><?= e($cd) ?></small><?php endif; ?></span><?= $badge($key) ?><i class="fa-solid fa-arrow-left sb-pop-go" aria-hidden="true"></i></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
