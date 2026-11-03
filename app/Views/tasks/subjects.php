<?php /** @var array $subjects */ ?>
<div class="page-head"><h2><i class="fa-solid fa-tags" data-css="color:var(--g-400)"></i> لیبل‌ها</h2>
    <div class="flex gap-2 items-center"><?php $this->partial('blocked_button', ['kind' => 'labels', 'count' => $blockedCount ?? 0, 'label' => 'لیبل‌های مسدودشده']); ?>
    <button class="btn btn-primary" data-modal="m-subject" id="btn-new-subject"><i class="fa-solid fa-plus"></i> لیبل جدید</button></div></div>
<div data-live="subjects">
<?php if (!$subjects): ?><div class="card empty"><i class="fa-solid fa-tags"></i><p>هنوز لیبلی نساخته‌اید. لیبل‌ها برای دسته‌بندی تسک‌ها هستند.</p></div>
<?php else: ?>
<div class="grid grid-auto">
    <?php foreach ($subjects as $s): ?>
    <div class="card hover flex items-center gap-2">
        <i class="fa-solid fa-tag label-icon" data-css="color:<?= e($s['color']) ?>"></i>
        <div class="grow"><div class="bold"><?= e($s['title']) ?></div><a class="xs lbl-tasks" href="<?= e(url('/tasks?scope=mine&status=all&label=' . (int) $s['id'])) ?>" title="نمایش تسک‌های این لیبل"><?= e(fa_num($s['tasks_count'])) ?> تسک <i class="fa-solid fa-arrow-left"></i></a></div>
        <button class="btn btn-sm btn-icon" title="ویرایش لیبل" aria-label="ویرایش لیبل «<?= e($s['title']) ?>»" data-edit='<?= e(json_html(['title' => $s['title'], 'color' => $s['color']])) ?>' data-target="m-subject" data-action="/labels/<?= (int) $s['id'] ?>/update"><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-sm btn-icon btn-danger" title="مسدود کردن لیبل" aria-label="مسدود کردن لیبل «<?= e($s['title']) ?>»" data-post="<?= e(url('/block/labels/' . $s['id'])) ?>" data-confirm="لیبل «<?= e($s['title']) ?>» مسدود شود؟ برای تسک جدید انتخاب نمی‌شود ولی روی تسک‌های فعلی می‌ماند. از «لیبل‌های مسدودشده» برمی‌گردد."><i class="fa-solid fa-ban"></i></button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<div class="modal" id="m-subject"><div class="modal-box sm">
    <form method="post" action="<?= e(url('/labels')) ?>" data-ajax id="f-subject"><?= csrf_field() ?>
        <div class="modal-head"><h3><i class="fa-solid fa-tag"></i> لیبل</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <div class="field"><label>عنوان *</label><input class="input" name="title" maxlength="150" required></div>
            <div class="field"><label>رنگ</label>
                <div class="swatches" role="radiogroup" aria-label="رنگ لیبل">
                <?php foreach (\App\Models\SubjectModel::COLORS as $i => $c): ?><label class="swatch" data-css="--sw:<?= e($c) ?>" title="<?= e($c) ?>"><input type="radio" name="color" value="<?= e($c) ?>" <?= $i === 5 ? 'checked' : '' ?> aria-label="<?= e($c) ?>"><span></span></label><?php endforeach; ?>
                </div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div>
    </form></div></div>
<?php $this->scripts[] = 'subjects'; ?>
