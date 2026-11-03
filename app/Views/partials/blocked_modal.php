<?php /* پنجره‌ی «مسدودشده‌ها» (تسک، پروژه، لیبل، درخواست تماس) با «رفع مسدودی» — modules/blocked.js، دکمه: partials/blocked_button.php */ ?>
<div class="modal" id="m-blocked"><div class="modal-box">
    <div class="modal-head"><h3><i class="fa-solid fa-ban"></i> <span id="blk-title">مسدودشده‌ها</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <p class="hint">موارد مسدودشده حذف نمی‌شوند؛ از فهرست‌ها کنار می‌روند و با «رفع مسدودی» مثل قبل برمی‌گردند.</p>
        <div id="blk-list" class="list blk-list" aria-live="polite"></div>
    </div>
</div></div>
