<?php /** @var array $storage پروژه‌های مجاز با آمار هر پروژه */ ?>
<h3><i class="fa-solid fa-hard-drive" aria-hidden="true"></i> مصرف فضای ذخیره‌سازی پروژه‌ها</h3>
<?php if (!$storage): ?><p class="small muted">پروژه‌ای با دسترسی مشاهده فضای ذخیره‌سازی ندارید.</p><?php endif; ?>
<?php foreach ($storage as $s): ?>
<div class="card aiu-card">
    <h3><?= e($s['title']) ?></h3>
    <?php if (!empty($s['pid'])): ?><a class="btn btn-sm" href="<?= e(url('/profile/storage/' . $s['pid'])) ?>">جزئیات فایل‌ها</a><?php endif; ?>
    <div class="aiu-num"><b><?= e($s['used_text']) ?></b> / <?= e($s['limit_text']) ?></div>
    <?php if ($s['unlimited']): ?><p class="small muted">فضای ذخیره‌سازی این پروژه نامحدود است.</p>
    <?php else: ?><progress max="100" value="<?= (int) $s['percent'] ?>" aria-label="درصد مصرف فضای پروژه"></progress><p class="small muted"><?= e(fa_num($s['percent'])) ?>٪ مصرف شده</p><?php endif; ?>
    <p class="small muted">سقف بین پروژه‌های سازنده مشترک است. پیوست‌های تسک‌ها، یادداشت‌های تسک و تصویر پروژه؛ حذف فایل فضا را آزاد می‌کند.</p>
</div>
<?php endforeach; ?>
