<?php
/**
 * «بار کاری» پروژه (v7.10): ساختار همان رابط مرجع؛ داده را workload.js از data-endpoint (Accept: application/json) می‌گیرد.
 * @var array $project @var array $subjects @var array $projects @var array $assignees
 */
$ppid = pid('projects', $project);
$this->scripts[] = 'workload';
?>
<section class="wl" id="wl" data-endpoint="<?= e(url('/projects/' . $ppid . '/workload')) ?>" data-csrf="<?= e(csrf_token()) ?>" aria-labelledby="wl-title">
  <header class="wl-head">
    <nav class="wl-crumb" aria-label="مسیر">
      <a href="<?= e(url('/projects')) ?>">پروژه‌ها</a><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      <span data-wl="project-title"><?= e($project['title']) ?></span><i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
      <span>بار کاری</span>
    </nav>
    <div class="wl-titlebar">
      <div class="wl-title">
        <span class="wl-ico" aria-hidden="true"><i class="fa-solid fa-gauge-high"></i></span>
        <div>
          <h1 id="wl-title">بار کاری تیم</h1>
          <p>چه کسی پربار است و چه کسی جا دارد؛ فقط اعضا و تسک‌های همین پروژه</p>
        </div>
      </div>
      <div class="wl-tabs">
        <a class="wl-tab" data-wl="link-board" href="<?= e(url('/board?project=' . $ppid)) ?>"><i class="fa-solid fa-table-columns"></i> بورد</a>
        <a class="wl-tab" data-wl="link-analytics" href="<?= e(url('/projects/' . $ppid . '/analytics')) ?>"><i class="fa-solid fa-chart-line"></i> تحلیل</a>
        <a class="wl-tab is-active" aria-current="page" href="<?= e(url('/projects/' . $ppid . '/workload')) ?>"><i class="fa-solid fa-gauge-high"></i> بار کاری</a>
      </div>
    </div>
  </header>

  <div class="wl-controls">
    <div class="wl-cap">
      <label for="wl-cap-range">ظرفیت هفتگی هر نفر</label>
      <input type="range" id="wl-cap-range" min="<?= \App\Services\WorkloadService::CAP_MIN ?>" max="<?= \App\Services\WorkloadService::CAP_MAX ?>" step="1" data-wl="cap-range">
      <output for="wl-cap-range" data-wl="cap-value"></output>
      <div class="wl-info">
        <button type="button" class="wl-info-btn" aria-expanded="false" aria-controls="wl-info-pop" aria-label="ظرفیت هفتگی و درصد بار چطور حساب می‌شود؟" data-wl="info-btn">
          <i class="fa-solid fa-info" aria-hidden="true"></i>
        </button>
        <div class="wl-pop" id="wl-info-pop" role="dialog" aria-label="توضیح ظرفیت و درصد بار" hidden data-wl="info-pop">
          <div class="wl-pop-head"><b>ظرفیت و درصد بار چیست؟</b><button type="button" class="wl-pop-x" aria-label="بستن" data-wl="info-close"><i class="fa-solid fa-xmark"></i></button></div>
          <p><b>ظرفیت هفتگی</b> مقدار کاری است که از هر نفر در ۷ روز انتظار می‌رود، به «امتیاز».</p>
          <p><b>امتیاز هر تسک</b> از اولویتش می‌آید: <span class="wl-k p3">زیاد ۳</span> <span class="wl-k p2">متوسط ۲</span> <span class="wl-k p1">کم ۱</span></p>
          <p><b>بار هر نفر</b> = جمع امتیاز تسک‌های بازِ او در این پروژه که <u>عقب‌افتاده‌اند</u>، <u>تا ۷ روز آینده سررسید دارند</u> یا <u>بدون سررسیدند</u>.</p>
          <div class="wl-formula">درصد بار = بار ÷ ظرفیت × ۱۰۰</div>
          <p class="wl-pop-ex" data-wl="info-example"></p>
          <ul>
            <li><span class="wl-dot ok"></span>زیر ۷۰٪: ظرفیت آزاد</li>
            <li><span class="wl-dot wr"></span>۷۰ تا ۱۰۰٪: متعادل</li>
            <li><span class="wl-dot bd"></span>بیش از ۱۰۰٪: پربار؛ کارش از توان یک هفته بیشتر است</li>
          </ul>
          <p class="wl-muted">در بار حساب نمی‌شوند: تسک‌های «بررسی سرپرست»، انجام‌شده، آرشیو، مسدود و سررسیدهای دورتر از ۷ روز.</p>
        </div>
      </div>
    </div>
    <span class="wl-saved" data-wl="cap-saved" hidden><i class="fa-solid fa-check"></i> ذخیره شد</span>
  </div>

  <div class="wl-summary" data-wl="summary"></div>
  <div class="wl-sug" data-wl="suggestions" hidden></div>
  <div class="wl-list" data-wl="list" aria-live="polite">
    <div class="wl-loading"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> در حال بارگذاری…</div>
  </div>

  <footer class="wl-legend">
    <span><i class="wl-sw od"></i>عقب‌افتاده</span>
    <span><i class="wl-sw wk"></i>سررسید ۷ روز آینده</span>
    <span><i class="wl-sw nd"></i>بدون سررسید</span>
    <span><i class="wl-sw cap"></i>خط ظرفیت</span>
    <span class="wl-muted" data-wl="drag-hint" hidden>برای تغییر مسئول، تسک را روی کارت نفر دیگر بکشید.</span>
  </footer>
</section>
<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => null, 'context' => 'workload']); ?>
