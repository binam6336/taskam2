<div class="page-head"><h2>فایل‌های پروژه</h2><a class="btn" href="<?= e(url('/profile#ai')) ?>">میزان مصرف</a></div>
<div class="card table-wrap"><table class="table table-cards"><thead><tr><th>فایل</th><th>حجم</th><th>تسک</th><th></th></tr></thead><tbody>
<?php foreach ($files as $f): ?><tr><td data-label="فایل"><?= e($f['name']) ?></td><td data-label="حجم"><?= e($f['size']) ?></td><td data-label="تسک"><?= e($f['task']) ?></td><td><?php if ($f['url']): ?><a class="btn btn-sm" href="<?= e($f['url']) ?>">ورود به تسک</a><?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$files): ?><tr><td colspan="4">فایل قابل مشاهده‌ای وجود ندارد.</td></tr><?php endif; ?>
</tbody></table></div>
