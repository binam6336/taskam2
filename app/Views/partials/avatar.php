<?php
/** @var string $name @var ?string $src @var string $size */
$size = $size ?? '';
?>
<span class="avatar <?= e($size) ?>"><?php if (!empty($src)): ?><img src="<?= e($src) ?>" alt="" loading="lazy"><?php else: ?><?= e(initial($name)) ?><?php endif; ?></span>
