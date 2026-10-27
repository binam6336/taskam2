<?php
/** @var int $pg @var int $pages @var array $query */
if ($pages <= 1) {
    return;
}
$link = static function (int $p) use ($query): string {
    return '?' . http_build_query(array_merge($query, ['page' => $p]));
};
$from = max(1, $pg - 2);
$to = min($pages, $pg + 2);
?>
<nav class="pagination" aria-label="صفحه‌بندی">
    <?php if ($pg > 1): ?><a href="<?= e($link($pg - 1)) ?>" aria-label="قبلی"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
    <?php if ($from > 1): ?><a href="<?= e($link(1)) ?>">۱</a><?php if ($from > 2): ?><span>…</span><?php endif; endif; ?>
    <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $pg): ?><span class="cur"><?= e(fa_num($i)) ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= e(fa_num($i)) ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($to < $pages): ?><?php if ($to < $pages - 1): ?><span>…</span><?php endif; ?><a href="<?= e($link($pages)) ?>"><?= e(fa_num($pages)) ?></a><?php endif; ?>
    <?php if ($pg < $pages): ?><a href="<?= e($link($pg + 1)) ?>" aria-label="بعدی"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
</nav>
