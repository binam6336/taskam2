<?php
/** دکمه‌ی «مسدودشده‌ها» (آیکون مسدود) که پنجره‌ی فهرست مسدودشده‌ها را باز می‌کند (modules/blocked.js، partials/blocked_modal.php).
 * @var string $kind tasks|projects|labels|calls @var int $count @var string $label
 * @var string|null $text اگر داده شود: چیپ متنی (آیکون + متن + شمارنده داخل متن) به‌جای دکمه‌ی فقط‌آیکون — صفحه‌ی تسک‌ها (v5.7) */
$text = $text ?? null;
?>
<button type="button" class="<?= $text ? 'blk-btn blk-chip' : 'btn btn-icon blk-btn' ?>" data-blocked-list="<?= e($kind) ?>" data-blocked-title="<?= e($label) ?>" title="<?= e($label) ?>" aria-label="<?= e($label . ($count > 0 ? ' (' . fa_num($count) . ')' : '')) ?>"><i class="fa-solid fa-ban" aria-hidden="true"></i><?php if ($text): ?> <span><?= e($text) ?></span><?php endif; ?><?php if ($count > 0): ?><span class="badge-count blk-count"><?= e(fa_num($count)) ?></span><?php endif; ?></button>
