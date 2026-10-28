<?php
/** @var array $groups گروه => [شناسه => [label, default, value, changed]] */
$this->scripts[] = 'content';
$titles = [
    'ask'   => ['fa-hand-sparkles', 'پنجره‌ی خوش‌آمد'],
    'steps' => ['fa-route', 'مرحله‌های آموزش'],
    'ui'    => ['fa-sliders', 'دکمه‌ها و پیام‌ها'],
];
?>
<div class="callout mb-2"><i class="fa-solid fa-circle-info"></i> متن‌هایی که در تور آموزشی پنل کاربران نمایش داده می‌شوند. تغییر هر متن بلافاصله برای کاربرانی که تور را می‌بینند اعمال می‌شود.</div>
<?php foreach ($titles as $g => [$icon, $title]): if (empty($groups[$g])) continue; ?>
<h3 class="ct-group"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($title) ?></h3>
<div class="ct-grid mb-2">
    <?php foreach ($groups[$g] as $id => $t): ?>
    <form class="card ct-card<?= $t['changed'] ? ' changed' : '' ?>" method="post" action="<?= e(url('/admin/content/tour/' . $id)) ?>" data-ajax data-after="none">
        <?= csrf_field() ?>
        <div class="ct-head">
            <code class="ltr"><?= e($id) ?></code>
            <span class="badge yellow ct-badge">ویرایش‌شده</span>
        </div>
        <div class="small muted mb-1"><?= e($t['label']) ?></div>
        <textarea class="input" name="body" rows="3" maxlength="500" required data-default="<?= e($t['default']) ?>"><?= e($t['value']) ?></textarea>
        <div class="ct-default xs faint mt-1">پیش‌فرض: <?= e($t['default']) ?></div>
        <div class="flex gap-1 mt-1">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button>
            <button class="btn btn-sm ct-reset" type="button" data-ct-reset><i class="fa-solid fa-rotate-left"></i> متن پیش‌فرض</button>
        </div>
    </form>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
