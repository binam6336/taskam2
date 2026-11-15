<?php
/**
 * صندوق بررسی سرپرست (v7.9) — مودال تمام‌صفحه روی پیشخوان (modules/review_inbox.js).
 * داده از GET /tasks/review/pending؛ تأیید با POST /tasks/review/bulk-approve و برگشت با POST /tasks/{id}/status (rework).
 * همه‌ی کلاس‌ها و data-attributeها با پیشوند ri-؛ بدون میانبر کیبورد (فقط Esc).
 */
$this->scripts[] = 'review_inbox';
$cats = \App\Services\BoardService::REWORK_CATEGORIES;
?>
<div class="ri-overlay" id="ri-inbox" hidden data-ri-cats="<?= e(json_html($cats)) ?>">
    <div class="ri-box" role="dialog" aria-modal="true" aria-labelledby="ri-title" tabindex="-1">
        <header class="ri-head">
            <h2 id="ri-title"><i class="fa-solid fa-inbox" aria-hidden="true"></i> صندوق بررسی</h2>
            <span class="ri-stat" data-ri-stat-wait></span>
            <span class="ri-stat ri-warn" data-ri-stat-over hidden></span>
            <button type="button" class="ri-x" data-ri-close aria-label="بستن صندوق بررسی"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </header>
        <div class="ri-bar">
            <div class="ri-chips" role="group" aria-label="فیلتر تحویل‌ها">
                <button type="button" class="ri-chip" data-ri-filter="all" aria-pressed="true">همه <span class="ri-num" data-ri-c="all"></span></button>
                <button type="button" class="ri-chip" data-ri-filter="over" aria-pressed="false">بیش از مهلت <span class="ri-num" data-ri-c="over"></span></button>
                <button type="button" class="ri-chip" data-ri-filter="late" aria-pressed="false">تحویل با تأخیر <span class="ri-num" data-ri-c="late"></span></button>
                <label class="sr-only" for="ri-project">پروژه</label>
                <select class="input input-sm ri-proj" id="ri-project" data-ri-project hidden></select>
            </div>
            <div class="ri-bulk">
                <label class="ri-all"><input type="checkbox" data-ri-all> انتخاب همه</label>
                <button type="button" class="btn btn-sm btn-primary" data-ri-bulk disabled><i class="fa-solid fa-check-double" aria-hidden="true"></i> تأیید گروهی (<span class="ri-num" data-ri-bulk-n>۰</span>)</button>
            </div>
        </div>
        <p class="ri-hint" data-ri-hint hidden></p>
        <div class="ri-body">
            <ul class="ri-list" data-ri-list role="listbox" aria-label="تحویل‌های منتظر بررسی"></ul>
            <section class="ri-detail" data-ri-detail aria-label="جزئیات تحویل"></section>
        </div>
        <div class="ri-empty" data-ri-empty hidden><i class="fa-solid fa-circle-check" aria-hidden="true"></i><p>صندوق بررسی خالی است</p></div>
        <template id="ri-rework-tpl">
            <div class="ri-rework" data-ri-rework>
                <b class="ri-rw-t">علت برگشت</b>
                <div class="ri-rw-chips" role="group" aria-label="علت برگشت (چند انتخابی)">
                    <?php foreach ($cats as $k => $label): ?><button type="button" class="ri-chip" data-ri-cat="<?= e($k) ?>" aria-pressed="false"><?= e($label) ?></button><?php endforeach; ?>
                </div>
                <label class="sr-only" for="ri-rw-text">توضیح برای مسئول</label>
                <textarea class="input" id="ri-rw-text" rows="3" maxlength="255" placeholder="توضیح برای مسئول (اختیاری)"></textarea>
                <div class="ri-rw-acts"><button type="button" class="btn btn-sm btn-primary" data-ri-rw-send><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> ارسال</button><button type="button" class="btn btn-sm btn-ghost" data-ri-rw-cancel>انصراف</button></div>
            </div>
        </template>
    </div>
</div>
