<?php
/**
 * لیبل‌ها (v7.12): لیبل‌ها مال پروژه‌اند و به تفکیک پروژه نمایش داده می‌شوند. ساخت، ویرایش و مسدود کردن فقط برای سازنده‌ی پروژه
 * یا عضو دارای «دسترسی ایجاد لیبل» (can_manage)؛ بقیه فقط می‌بینند و روی تسک‌ها می‌گذارند. لیبل‌های شخصی قدیمی (بدون پروژه)
 * فقط برای صاحبشان با امکان انتقال به پروژه.
 * @var array $subjects @var array $manageable
 */
$groups = [];
foreach ($subjects as $s) {
    $k = $s['project_id'] === null ? '_legacy' : (string) $s['project_id'];
    $groups[$k] ??= ['title' => $s['project_title'] ?? '', 'legacy' => $s['project_id'] === null, 'rows' => [], 'can' => false, 'pid' => $s['project_id']];
    $groups[$k]['rows'][] = $s;
    $groups[$k]['can'] = $groups[$k]['can'] || !empty($s['can_manage']);
}
$canCreate = !empty($manageable);
?>
<div class="page-head"><h2><i class="dc-961bcb fa-solid fa-tags"></i> لیبل‌ها</h2>
    <div class="flex gap-2 items-center"><?php $this->partial('blocked_button', ['kind' => 'labels', 'count' => $blockedCount ?? 0, 'label' => 'لیبل‌های مسدودشده']); ?>
    <?php if ($canCreate): ?><button class="btn btn-primary" data-modal="m-subject" id="btn-new-subject"><i class="fa-solid fa-plus"></i> لیبل جدید</button><?php endif; ?></div></div>
<p class="small muted lbl-intro"><i class="fa-solid fa-circle-info"></i> لیبل‌ها مال هر پروژه‌اند و همه‌ی اعضای پروژه از آن‌ها استفاده می‌کنند.
    <?= $canCreate ? 'شما در ' . e(fa_num(count($manageable))) . ' پروژه اجازه‌ی ساخت لیبل دارید.' : 'ساخت لیبل جدید «دسترسی ایجاد لیبل» در پروژه می‌خواهد (سازنده‌ی پروژه، نقش مدیر کل یا مدیر واحد)؛ شما از لیبل‌های موجود استفاده می‌کنید.' ?></p>
<div data-live="subjects">
<?php if (!$subjects): ?><div class="card empty"><i class="fa-solid fa-tags"></i><p>هنوز در پروژه‌های شما لیبلی نیست. لیبل‌ها برای دسته‌بندی تسک‌ها هستند.</p></div>
<?php else: foreach ($groups as $g): ?>
<section class="lbl-group" aria-label="<?= e($g['legacy'] ? 'لیبل‌های شخصی قدیمی' : 'لیبل‌های پروژه ' . $g['title']) ?>">
    <h3 class="lbl-gh"><?php if ($g['legacy']): ?><i class="fa-solid fa-box-archive"></i> لیبل‌های شخصی قدیمی <span class="xs faint">(بدون پروژه؛ روی تسک تازه گذاشته نمی‌شوند — به یک پروژه منتقلشان کنید)</span>
        <?php else: ?><i class="fa-solid fa-diagram-project"></i> <?= e($g['title']) ?> <span class="xs faint"><?= e(fa_num(count($g['rows']))) ?> لیبل<?= $g['can'] ? '' : '، فقط استفاده' ?></span><?php endif; ?></h3>
    <div class="grid grid-auto">
    <?php foreach ($g['rows'] as $s): $sp = pid('subjects', $s); ?>
    <div class="card hover flex items-center gap-2">
        <i class="fa-solid fa-tag label-icon" data-css="color:<?= e($s['color']) ?>"></i>
        <div class="grow"><div class="bold"><?= e($s['title']) ?></div><a class="xs lbl-tasks" href="<?= e(url('/tasks?scope=all&status=all&label=' . $sp)) ?>" title="نمایش تسک‌های این لیبل"><?= e(fa_num($s['tasks_count'])) ?> تسک <i class="fa-solid fa-arrow-left"></i></a></div>
        <?php if (!empty($s['can_manage'])): ?>
        <button class="btn btn-sm btn-icon" title="<?= $g['legacy'] ? 'ویرایش و انتقال به پروژه' : 'ویرایش لیبل' ?>" aria-label="ویرایش لیبل «<?= e($s['title']) ?>»" data-edit='<?= e(json_html(['title' => $s['title'], 'color' => $s['color'], 'project_id' => $s['project_id'] !== null ? pid('projects', $s['project_id']) : '', 'legacy' => $g['legacy']])) ?>' data-target="m-subject" data-action="/labels/<?= e($sp) ?>/update"><i class="fa-solid fa-<?= $g['legacy'] ? 'right-to-bracket' : 'pen' ?>"></i></button>
        <button class="btn btn-sm btn-icon btn-danger" title="مسدود کردن لیبل" aria-label="مسدود کردن لیبل «<?= e($s['title']) ?>»" data-post="<?= e(url('/block/labels/' . $sp)) ?>" data-confirm="لیبل «<?= e($s['title']) ?>» مسدود شود؟ برای تسک جدید انتخاب نمی‌شود ولی روی تسک‌های فعلی می‌ماند. از «لیبل‌های مسدودشده» برمی‌گردد."><i class="fa-solid fa-ban"></i></button>
        <?php else: ?><i class="fa-solid fa-lock faint" title="ساخت و ویرایش لیبل این پروژه «دسترسی ایجاد لیبل» می‌خواهد" aria-label="فقط استفاده"></i><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; endif; ?>
</div>
<?php if ($canCreate || isset($groups['_legacy'])): ?>
<div class="modal" id="m-subject"><div class="modal-box sm">
    <form method="post" action="<?= e(url('/labels')) ?>" data-ajax id="f-subject"><?= csrf_field() ?>
        <div class="modal-head"><h3><i class="fa-solid fa-tag"></i> لیبل</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="field" id="subj-project"><label for="subj-project-sel">پروژه *</label>
                <select class="input" name="project_id" id="subj-project-sel" required><option value="">— انتخاب پروژه —</option>
                <?php foreach ($manageable as $p): ?><option value="<?= e(pid('projects', $p)) ?>"><?= e($p['title']) ?></option><?php endforeach; ?></select>
                <small class="xs faint" id="subj-project-note">فقط پروژه‌هایی که در آن‌ها «دسترسی ایجاد لیبل» دارید.</small></div>
            <div class="field"><label>عنوان *</label><input class="input" name="title" maxlength="150" required></div>
            <div class="field"><label>رنگ</label>
                <div class="swatches" role="radiogroup" aria-label="رنگ لیبل">
                <?php foreach (\App\Models\SubjectModel::COLORS as $i => $c): ?><label class="swatch" data-css="--sw:<?= e($c) ?>" title="<?= e($c) ?>"><input type="radio" name="color" value="<?= e($c) ?>" <?= $i === 5 ? 'checked' : '' ?> aria-label="<?= e($c) ?>"><span></span></label><?php endforeach; ?>
                </div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div>
    </form></div></div>
<?php endif; ?>
<?php $this->scripts[] = 'subjects'; ?>
