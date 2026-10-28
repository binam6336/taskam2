<?php /** @var array $subjects */ ?>
<div class="page-head"><h2><i class="fa-solid fa-tags" style="color:var(--g-400)"></i> لیبل‌ها</h2>
    <button class="btn btn-primary" data-modal="m-subject" id="btn-new-subject"><i class="fa-solid fa-plus"></i> لیبل جدید</button></div>
<div data-live="subjects">
<?php if (!$subjects): ?><div class="card empty"><i class="fa-solid fa-tags"></i><p>هنوز لیبلی نساخته‌اید. لیبل‌ها برای دسته‌بندی تسک‌ها هستند.</p></div>
<?php else: ?>
<div class="grid grid-auto">
    <?php foreach ($subjects as $s): ?>
    <div class="card hover flex items-center gap-2">
        <i class="fa-solid fa-tag label-icon" style="color:<?= e($s['color']) ?>"></i>
        <div class="grow"><div class="bold"><?= e($s['title']) ?></div><div class="xs muted"><?= e(fa_num($s['tasks_count'])) ?> تسک</div></div>
        <button class="btn btn-sm btn-icon" data-edit='<?= e(json_html(['title' => $s['title'], 'color' => $s['color']])) ?>' data-target="m-subject" data-action="/labels/<?= (int) $s['id'] ?>/update"><i class="fa-solid fa-pen"></i></button>
        <button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/labels/' . $s['id'] . '/delete')) ?>" data-confirm="لیبل «<?= e($s['title']) ?>» حذف شود؟ تسک‌های آن بدون لیبل می‌شوند."><i class="fa-solid fa-trash"></i></button>
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
                <?php foreach (\App\Models\SubjectModel::COLORS as $i => $c): ?><label class="swatch" style="--sw:<?= e($c) ?>" title="<?= e($c) ?>"><input type="radio" name="color" value="<?= e($c) ?>" <?= $i === 5 ? 'checked' : '' ?> aria-label="<?= e($c) ?>"><span></span></label><?php endforeach; ?>
                </div></div>
        </div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div>
    </form></div></div>
<?php $this->scripts[] = 'subjects'; ?>
