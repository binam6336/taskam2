<?php $this->scripts[] = 'admin_ai'; ?>
<div class="page-head"><h2><i class="fa-solid fa-hard-drive"></i> فضای ذخیره‌سازی</h2></div>
<?php if ($mode === 'users'): ?>
<?php $default = \App\Services\StorageQuota::defaultLimit(); ?>
<form class="card mb-2" method="post" action="<?= e(url('/admin/storage/defaults')) ?>" data-ajax><?= csrf_field() ?><div class="aiq-lim field"><label>سقف پیش‌فرض مشترک هر کاربر (مگابایت)</label><input class="input" name="storage" data-ai-lim value="<?= $default === null ? '' : (int) $default ?>"<?= $default === null ? ' disabled' : '' ?>><label><input type="checkbox" name="storage_unlimited" value="1" data-ai-unl<?= $default === null ? ' checked' : '' ?>> نامحدود</label></div><button class="btn">ذخیره پیش‌فرض</button></form>

<p class="small muted">کاربر را انتخاب کنید تا پروژه‌هایی که ساخته است نمایش داده شوند. سقف هر کاربر بین مجموع پروژه‌های ساخته‌شده او مشترک است.</p>
<form method="get" class="flex gap-2 mb-2"><input class="input" name="q" value="<?= e($q) ?>" aria-label="جستجوی کاربر"><button class="btn">جستجو</button></form>
<div class="card table-wrap"><table class="table table-cards"><thead><tr><th>کاربر</th><th>تعداد پروژه‌های ساخته‌شده</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td data-label="کاربر"><?= e(full_name($row)) ?><div class="small muted"><?= e(fa_num($row['mobile'])) ?></div></td><td data-label="پروژه‌ها"><?= e(fa_num($row['project_count'])) ?></td><td><a class="btn btn-sm" href="<?= e(url('/admin/storage/users/' . $row['pid'])) ?>">پروژه‌ها</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="3">کاربری یافت نشد.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => ['q' => $q]]); ?>
<?php elseif ($mode === 'projects'): ?>
<?php $effective = $owner['storage_custom'] ? ($owner['storage_limit_mb'] === null ? null : (int) $owner['storage_limit_mb']) : \App\Services\StorageQuota::defaultLimit(); ?>
<form class="card mb-2" method="post" action="<?= e(url('/admin/storage/users/' . pid('users', $owner) . '/quota')) ?>" data-ajax><?= csrf_field() ?>
<div class="aiq-lim field"><label>سقف مشترک همه پروژه‌های این کاربر (مگابایت)</label><input class="input" name="storage" data-ai-lim value="<?= $effective === null ? '' : $effective ?>"<?= $effective === null ? ' disabled' : '' ?> data-current="1"><label><input type="checkbox" name="storage_unlimited" data-ai-unl value="1"<?= $effective === null ? ' checked' : '' ?>> نامحدود</label></div><label><input type="checkbox" name="reset" value="1"> پیروی از سقف پیش‌فرض</label><button class="btn">ذخیره سقف کاربر</button></form>

<a class="btn btn-sm mb-2" href="<?= e(url('/admin/storage')) ?>">کاربران</a><h3 class="mb-2">پروژه‌های ساخته‌شده توسط <?= e(full_name($owner)) ?></h3>
<div class="card table-wrap"><table class="table table-cards"><thead><tr><th>پروژه</th><th>مصرف</th><th>سقف</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td data-label="پروژه"><a href="<?= e(url('/admin/storage/projects/' . $row['pid'])) ?>"><?= e($row['title']) ?></a><?= $row['blocked_at'] ? ' (مسدود)' : '' ?></td><td data-label="مصرف"><?= e($row['storage']['used_text']) ?></td><td data-label="سقف"><?= e($row['storage']['limit_text']) ?></td><td><a class="btn btn-sm" href="<?= e(url('/admin/storage/projects/' . $row['pid'])) ?>">سقف مشترک سازنده</a></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="4">این کاربر پروژه‌ای نساخته است.</td></tr><?php endif; ?>
</tbody></table></div>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => []]); ?>
<?php else: ?>
<a class="btn btn-sm mb-2" href="<?= e(url('/admin/storage/users/' . pid('users', (int) $project['user_id']))) ?>">پروژه‌های سازنده</a>
<h3><?= e($project['title']) ?></h3>
<div class="aiu-wrap mb-2"><?php $this->partial('storage_usage', ['storage' => [['title' => $project['title']] + $storage]]); ?></div>
<form method="post" action="<?= e(url('/admin/storage/projects/' . pid('projects', $project) . '/quota')) ?>" data-ajax class="card"><?= csrf_field() ?>
<div class="aiq-lim field"><label for="storage-limit">سقف مشترک پروژه‌های سازنده (مگابایت)</label><input class="input ltr" id="storage-limit" name="storage" inputmode="numeric" data-ai-lim value="<?= $storage['limit_mb'] === null ? '' : (int) $storage['limit_mb'] ?>"<?= $storage['unlimited'] ? ' disabled' : '' ?>>
<label class="check"><input type="checkbox" name="storage_unlimited" value="1" data-ai-unl<?= $storage['unlimited'] ? ' checked' : '' ?>> نامحدود</label></div>
<p class="small muted">صفر = توقف آپلود جدید. کاهش سقف، فایل‌های فعلی را حذف نمی‌کند. هر مگابایت برابر ۱۰۴۸۵۷۶ بایت است.</p><button class="btn btn-primary">ذخیره</button>
</form>
<?php endif; ?>
