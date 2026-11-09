<?php
/**
 * ویرایشگر ستون‌های بورد (v6.1) — مشترک بین «تنظیمات بورد» هر پروژه (پنجره، مجوز «مدیریت بورد») و «محتوا ← بورد» در پنل مدیر (بورد پیش‌فرض).
 * رفتار در modules/board_settings.js. @var string $scope project|template @var string $project شناسه‌ی عمومی پروژه (فقط project) @var bool $custom
 */
$isProject = $scope === 'project';
$this->scripts[] = 'board_settings';
$editor = function () use ($isProject, $project, $custom) { ?>
    <div class="bs-editor" data-board-editor data-scope="<?= $isProject ? 'project' : 'template' ?>" data-project="<?= e($project ?? '') ?>"
         data-load="<?= e($isProject ? url('/board/settings') : url('/admin/content/board/data')) ?>" data-save="<?= e($isProject ? url('/board/settings') : url('/admin/content/board')) ?>">
        <?php if ($isProject): ?>
        <p class="small muted bs-intro"><i class="fa-solid fa-circle-info"></i> این بورد مخصوص همین پروژه است.
            <span data-bs-state><?= $custom ? 'بورد اختصاصی این پروژه فعال است.' : 'این پروژه الان از بورد پیش‌فرض استفاده می‌کند؛ با ذخیره، بورد اختصاصی می‌شود.' ?></span></p>
        <?php else: ?>
        <p class="small muted bs-intro"><i class="fa-solid fa-circle-info"></i> بورد پیش‌فرض: کاربر یا پروژه‌ی جدید، پروژه‌ای که بوردش را تغییر نداده و پروژه‌ای که در تنظیمات بورد «بازگشت به پیش‌فرض» را بزند، همین چیدمان را می‌بیند.</p>
        <?php endif; ?>
        <ol class="bs-cols" data-bs-cols aria-label="ستون‌های بورد"><li class="bs-loading"><span class="spin"></span></li></ol>
        <button type="button" class="btn btn-sm bs-add" data-bs-add><i class="fa-solid fa-plus"></i> ستون جدید</button>
        <div class="field bs-start-field">
            <label for="bs-start-<?= $isProject ? 'p' : 't' ?>"><i class="fa-solid fa-flag-checkered"></i> وضعیت اول بعد از ایجاد تسک</label>
            <select class="input" id="bs-start-<?= $isProject ? 'p' : 't' ?>" data-bs-start></select>
            <div class="hint">تسک تازه‌ی <?= $isProject ? 'این پروژه' : 'پروژه‌ها' ?> بعد از ثبت در این ستون قرار می‌گیرد.</div>
        </div>
        <div class="bs-foot">
            <button type="button" class="btn btn-ghost" data-bs-reset><i class="fa-solid fa-rotate-left"></i> <?= $isProject ? 'بازگشت به بورد پیش‌فرض' : 'بازگشت به چیدمان اولیه' ?></button>
            <span class="grow"></span>
            <?php if ($isProject): ?><button type="button" class="btn" data-close>انصراف</button><?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-save><i class="fa-solid fa-floppy-disk"></i> ذخیره</button>
        </div>
    </div>
<?php };
if ($isProject): ?>
<div class="modal" id="m-board-settings" data-guard><div class="modal-box lg">
    <div class="modal-head"><h3><i class="fa-solid fa-gear"></i> تنظیمات بورد</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><?php $editor(); ?></div>
</div></div>
<?php else: $editor(); endif; ?>
