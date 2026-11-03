<?php
/** دکمه‌ی «مسدودشده‌ها» (آیکون مسدود) که پنجره‌ی فهرست مسدودشده‌ها را باز می‌کند (modules/blocked.js، partials/blocked_modal.php).
 * @var string $kind tasks|projects|labels|calls @var int $count @var string $label */
?>
<button type="button" class="btn btn-icon blk-btn" data-blocked-list="<?= e($kind) ?>" data-blocked-title="<?= e($label) ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><i class="fa-solid fa-ban"></i><?php if ($count > 0): ?><span class="badge-count blk-count"><?= e(fa_num($count)) ?></span><?php endif; ?></button>
