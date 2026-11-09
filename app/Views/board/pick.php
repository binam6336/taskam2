<?php /** انتخاب پروژه برای بورد (v6.1: هر پروژه بورد خودش را دارد). @var array $projects */ ?>
<div class="page-head"><h2><i class="fa-solid fa-table-columns" data-css="color:var(--g-400)"></i> بورد پروژه</h2></div>
<p class="muted small mb-2">هر پروژه بورد خودش را دارد. پروژه را انتخاب کنید؛ داخل بورد هم می‌توانید بین پروژه‌ها جابه‌جا شوید.</p>
<?php if (!$projects): ?>
<div class="card empty"><i class="fa-solid fa-diagram-project"></i><p>هنوز عضو هیچ پروژه‌ای نیستید.</p><a class="btn btn-primary" href="<?= e(url('/projects')) ?>">ساخت پروژه</a></div>
<?php else: ?>
<div class="kb-pick-grid">
<?php foreach ($projects as $p): ?>
    <a class="card hover kb-pick" href="<?= e($p['url']) ?>">
        <span class="avatar sq"><?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt=""><?php else: ?><?= e(initial($p['title'])) ?><?php endif; ?></span>
        <span class="grow min0"><b class="truncate"><?= e($p['title']) ?></b><small class="muted"><?= e(fa_num($p['open'])) ?> تسک باز</small></span>
        <i class="fa-solid fa-chevron-left faint" aria-hidden="true"></i>
    </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
