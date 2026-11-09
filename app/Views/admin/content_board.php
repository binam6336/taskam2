<?php /** محتوا ← بورد (v6.1): بورد پیش‌فرض. @var bool $custom */ ?>
<div class="page-head"><h2><i class="fa-solid fa-table-columns" data-css="color:var(--g-400)"></i> بورد پیش‌فرض</h2>
    <span class="badge <?= $custom ? 'green' : '' ?>"><?= $custom ? 'تنظیم‌شده توسط مدیر' : 'چیدمان اولیه‌ی تسکام' ?></span></div>
<div class="card bs-admin">
    <?php $this->partial('board_settings', ['scope' => 'template', 'project' => '', 'custom' => $custom]); ?>
</div>
<div class="card mt-2 small muted">
    <b><i class="fa-solid fa-circle-info"></i> قواعد</b>
    <ul class="bs-rules">
        <li>«بازبینی مسئول»، «بررسی سرپرست» و «انجام‌شده» ماهیت ثابت دارند و حذف نمی‌شوند؛ فقط نام، آیکون و رنگشان عوض می‌شود.</li>
        <li>هر پروژه می‌تواند از چرخ‌دنده‌ی بالای بورد خودش بورد اختصاصی بسازد (با دسترسی «مدیریت بورد»). پروژه‌هایی که این کار را نکرده‌اند همین بورد را می‌بینند.</li>
        <li>اگر ستونی را از این‌جا حذف کنید، تسک‌های آن ستون در پروژه‌های دارای بورد پیش‌فرض به «وضعیت اول بعد از ایجاد تسک» منتقل می‌شوند.</li>
    </ul>
</div>
