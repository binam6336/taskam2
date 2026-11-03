<?php /* «ثبت سریع تسک»: فقط عنوان؛ @ = انتخاب مسئول، # = انتخاب پروژه (modules/quicktask.js) */ ?>
<div class="modal" id="m-quick" data-guard><div class="modal-box sm"><form id="f-quick" autocomplete="off">
    <div class="modal-head"><h3><i class="fa-solid fa-bolt"></i> ثبت سریع تسک</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="field qk-field"><label for="qk-title">عنوان</label>
            <input class="input" id="qk-title" name="title" maxlength="255" required placeholder="<?= e(ph('quick_task_title')) ?>" aria-describedby="qk-hint" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="qk-menu">
            <div class="qk-menu" id="qk-menu" role="listbox" hidden></div></div>
        <div class="qk-chips" id="qk-chips"></div>
        <p class="hint" id="qk-hint"><b>@</b> انتخاب مسئول • <b>#</b> انتخاب پروژه • بدون انتخاب: مسئول خودتان و پروژه همان آخرین پروژه‌ای که در آن تسک ساخته‌اید.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary" id="qk-submit"><i class="fa-solid fa-check"></i> ثبت</button></div>
</form></div></div>
