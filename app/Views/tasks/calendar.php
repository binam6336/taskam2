<?php
/** تقویم تسک‌ها. @var array $subjects @var array $projects @var array $assignees */
$this->scripts[] = 'calendar';
?>
<div class="page-head"><h2><i class="fa-regular fa-calendar-days" style="color:var(--g-400)"></i> تقویم تسک‌ها</h2>
    <div class="flex gap-1">
        <a class="btn" href="<?= e(url('/tasks')) ?>"><i class="fa-solid fa-list-check"></i> لیست تسک‌ها</a>
        <button type="button" class="btn btn-primary" data-cal-new><i class="fa-solid fa-plus"></i> تسک جدید</button>
    </div>
</div>

<section class="card cal" id="cal" aria-label="تقویم تسک‌ها">
    <div class="cal-bar">
        <div class="cal-nav">
            <button type="button" class="btn btn-icon btn-sm" data-cal-prev title="ماه قبل" aria-label="ماه قبل"><i class="fa-solid fa-chevron-right"></i></button>
            <h3 id="cal-title" aria-live="polite"></h3>
            <button type="button" class="btn btn-icon btn-sm" data-cal-next title="ماه بعد" aria-label="ماه بعد"><i class="fa-solid fa-chevron-left"></i></button>
            <button type="button" class="btn btn-sm" data-cal-today><i class="fa-solid fa-location-crosshairs"></i> امروز</button>
        </div>
        <div class="cal-tools">
            <div class="cal-seg" role="group" aria-label="تسک‌های نمایش‌داده‌شده">
                <button type="button" data-cal-scope="mine" class="on">وظایف من</button>
                <button type="button" data-cal-scope="others">واگذارشده</button>
                <button type="button" data-cal-scope="all">همه</button>
            </div>
            <label class="check cal-done"><input type="checkbox" id="cal-done"><div><b>انجام‌شده‌ها</b></div></label>
        </div>
    </div>
    <div class="cal-stats" id="cal-stats"></div>
    <div class="cal-week" aria-hidden="true"><span>شنبه</span><span>یکشنبه</span><span>دوشنبه</span><span>سه‌شنبه</span><span>چهارشنبه</span><span>پنجشنبه</span><span>جمعه</span></div>
    <div class="cal-grid" id="cal-grid" role="grid"></div>
    <p class="cal-hint xs faint"><i class="fa-solid fa-hand-pointer"></i> روی هر روز بزنید تا تسک‌هایش را ببینید و زمان‌بندی‌شان را عوض کنید؛ روی دسکتاپ می‌توانید تسک را بکشید و روی روز دیگری رها کنید.</p>
</section>

<!-- تسک‌های یک روز -->
<div class="modal" id="m-cal-day"><div class="modal-box"><div class="modal-head"><h3><i class="fa-regular fa-calendar"></i> <span id="cal-day-title"></span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div id="cal-day-list" class="cal-day-list"></div></div>
    <div class="modal-foot"><button type="button" class="btn btn-primary" data-cal-day-new><i class="fa-solid fa-plus"></i> تسک جدید برای این روز</button></div>
</div></div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => null, 'context' => 'calendar']); ?>
