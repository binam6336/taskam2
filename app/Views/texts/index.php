<?php /** @var array $texts */
// یادداشت‌ها (v6.0؛ قبلاً «متن‌های آماده»): یادداشت چسبان رنگی، مرتب‌سازی با کشیدن
$colors = \App\Models\TextModel::COLORS;
$this->boot['texts'] = $texts;
$this->boot['noteColors'] = $colors;
$this->scripts[] = 'texts'; ?>
<div class="page-head">
    <div class="dc-ae18a0 search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="text-search" placeholder="<?= e(ph('texts_search')) ?>" aria-label="جستجو در یادداشت‌ها"></div>
    <button class="btn btn-primary" id="btn-new-text"><i class="fa-solid fa-plus"></i> یادداشت جدید</button>
</div>
<p class="muted small mb-2"><i class="fa-solid fa-circle-info"></i> یادداشت‌ها فقط برای خودتان است. با کشیدن کارت ترتیب را عوض کنید و با دایره‌های رنگی پایین هر یادداشت رنگش را تغییر دهید.</p>
<div class="notes-grid" id="text-grid"></div>
<div class="card empty hidden" id="text-empty"><i class="fa-solid fa-note-sticky"></i><p id="text-empty-msg">هنوز یادداشتی ندارید.</p></div>

<div class="modal" id="m-text" data-guard><div class="modal-box">
    <form id="f-text"><div class="modal-head"><h3><i class="fa-solid fa-note-sticky"></i> <span id="text-modal-title">یادداشت جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body"><input type="hidden" name="id">
            <div class="field"><label for="note-title">عنوان <span class="faint xs">(اختیاری)</span></label><input class="input" id="note-title" name="title" maxlength="255"></div>
            <div class="field"><label for="note-content">متن <span class="req" aria-hidden="true">*</span></label><textarea class="input" id="note-content" name="content" rows="7" required></textarea></div>
            <fieldset class="field note-colors"><legend>رنگ</legend>
                <div class="note-swatches">
                <?php foreach ($colors as $k => $label): ?>
                    <label class="note-sw note-c-<?= e($k) ?>" title="<?= e($label) ?>"><input type="radio" name="color" value="<?= e($k) ?>" <?= $k === \App\Models\TextModel::DEFAULT_COLOR ? 'checked' : '' ?> aria-label="<?= e($label) ?>"><i class="fa-solid fa-check" aria-hidden="true"></i></label>
                <?php endforeach; ?>
                </div>
            </fieldset></div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div></form></div></div>
