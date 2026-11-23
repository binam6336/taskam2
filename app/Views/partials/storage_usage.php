<?php /** @var array $storage */ ?>
<h3><i class="fa-solid fa-hard-drive" aria-hidden="true"></i> مصرف فضای ذخیره‌سازی</h3>
<div class="card aiu-card">
    <div class="aiu-num"><b><?= e($storage['used_text']) ?></b> / <?= e($storage['limit_text']) ?></div>
    <?php if ($storage['unlimited']): ?><p class="small muted">فضای ذخیره‌سازی نامحدود است.</p>
    <?php else: ?><progress max="100" value="<?= (int) $storage['percent'] ?>" aria-label="درصد مصرف فضای ذخیره‌سازی"></progress><p class="small muted"><?= e(fa_num($storage['percent'])) ?>٪ مصرف شده</p><?php endif; ?>
    <p class="small muted">پیوست‌ها، فایل‌های چت و پشتیبانی و تصاویر؛ حذف فایل فضا را آزاد می‌کند.</p>
</div>
