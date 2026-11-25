<?php
$this->scripts[] = 'meeting';
?>
<script type="application/json" id="meeting-data"><?= json_html($meeting) ?></script>
<section class="meeting-room <?= $meeting['mode'] === 'audio' ? 'meeting-audio' : 'meeting-video' ?>" id="meeting-room" aria-label="جلسهٔ پروژه">
    <header class="meeting-header"><div><a href="<?= e(url('/projects')) ?>" data-no-nav class="small muted">پروژه‌ها</a><h2><?= e($meeting['project_title']) ?></h2><span class="badge"><?= $meeting['mode'] === 'audio' ? 'جلسهٔ صوتی' : 'جلسهٔ تصویری' ?></span></div>
        <div class="meeting-connection" role="status" id="meeting-connection">پیش از ورود</div>
        <button type="button" class="btn btn-sm" id="meeting-layout">تغییر چیدمان</button></header>
    <p class="meeting-alert" role="alert" id="meeting-alert" hidden></p>
    <div class="card meeting-prejoin" id="meeting-prejoin">
        <div class="meeting-preview"><video id="meeting-preview-video" autoplay muted playsinline></video><span id="meeting-preview-label">بررسی میکروفن و دوربین</span><meter id="meeting-mic-level" min="0" max="1" value="0" aria-label="سطح صدای میکروفن"></meter></div>
        <div class="meeting-prejoin-options"><h3>ورود با انتخاب شما</h3><p class="muted small">میکروفن و دوربین فقط با اجازهٔ شما روشن می‌شوند. اعضای تازه ابتدا شنونده هستند؛ میزبان می‌تواند مجوز صحبت بدهد. ورود با دستگاه دیگر، اتصال قبلی شما را جایگزین می‌کند.</p>
            <label class="check"><input type="checkbox" id="meeting-pre-mic"> میکروفن</label>
            <?php if ($meeting['mode'] === 'video'): ?><label class="check"><input type="checkbox" id="meeting-pre-camera"> دوربین</label><?php endif; ?>
            <label class="field">میکروفن<select class="input" id="meeting-audio-device" aria-label="انتخاب میکروفن"><option value="">دستگاه پیش‌فرض</option></select></label>
            <?php if ($meeting['mode'] === 'video'): ?><label class="field">دوربین<select class="input" id="meeting-video-device" aria-label="انتخاب دوربین"><option value="">دستگاه پیش‌فرض</option></select></label><?php endif; ?>
            <div class="flex wrap gap-1"><button type="button" class="btn" id="meeting-test">بررسی دستگاه‌ها</button><button type="button" class="btn btn-primary" id="meeting-join">ورود به جلسه</button><?php if ($meeting['is_host']): ?><button type="button" class="btn btn-danger" id="meeting-cancel">پایان جلسه</button><?php endif; ?></div>
        </div>
    </div>
    <div class="meeting-workspace" id="meeting-workspace" hidden>
        <div class="meeting-stage"><div class="meeting-grid" id="meeting-grid" aria-label="شرکت‌کنندگان"></div><div id="meeting-audio-tracks" hidden></div>
            <div class="meeting-controls" aria-label="کنترل جلسه">
                <button type="button" class="btn" id="meeting-mic" aria-pressed="false"><i class="fa-solid fa-microphone-slash" aria-hidden="true"></i> میکروفن</button>
                <?php if ($meeting['mode'] === 'video'): ?><button type="button" class="btn" id="meeting-camera" aria-pressed="false"><i class="fa-solid fa-video" aria-hidden="true"></i> دوربین</button><?php endif; ?>
                <button type="button" class="btn" id="meeting-screen" aria-pressed="false">اشتراک صفحه</button>
                <button type="button" class="btn" id="meeting-hand" aria-pressed="false">درخواست صحبت</button>
                <button type="button" class="btn" id="meeting-play-audio" hidden>پخش صدا</button>
                <button type="button" class="btn" id="meeting-panels">اعضا و چت</button>
                <button type="button" class="btn btn-danger" id="meeting-leave">خروج</button>
                <button type="button" class="btn btn-danger" id="meeting-end" <?= $meeting['is_host'] ? '' : 'hidden' ?>>پایان برای همه</button>
            </div>
        </div>
        <aside class="meeting-side" id="meeting-side" aria-label="اعضا و چت جلسه">
            <section class="card"><div class="card-head"><h3>اعضای جلسه</h3><button type="button" class="btn btn-sm" id="meeting-invite" <?= $meeting['is_host'] ? '' : 'hidden' ?>>دعوت اعضا</button></div><div id="meeting-members"></div></section>
            <section class="card meeting-chat"><h3>گفتگوی جلسه</h3><p class="xs muted">پیام‌ها در کارلیکس ذخیره می‌شوند.</p><button type="button" class="btn btn-xs" id="meeting-chat-older">پیام‌های قبلی</button><ol id="meeting-chat-list" aria-live="polite" aria-label="پیام‌ها"></ol>
                <form id="meeting-chat-form"><label for="meeting-message" class="sr-only">پیام جلسه</label><textarea id="meeting-message" class="input" maxlength="4000" rows="2" placeholder="<?= e(ph('meeting_message')) ?>" required></textarea><button type="submit" class="btn btn-primary btn-sm">ارسال</button></form>
            </section>
        </aside>
    </div>
    <section class="card" id="meeting-ended" hidden><h3>جلسه پایان یافته است</h3><p class="muted">ورود دوباره امکان‌پذیر نیست. تاریخچهٔ گفتگو برای اعضای مجاز حفظ شده است.</p><button type="button" class="btn" id="meeting-history-chat">نمایش گفتگو</button><div id="meeting-ended-chat"></div></section>
</section>
<div class="modal" id="m-meeting-invite"><div class="modal-box"><div class="modal-head"><h3>دعوت اعضای همین پروژه</h3><button type="button" class="btn btn-icon btn-sm" data-close aria-label="بستن">×</button></div><div class="modal-body" id="meeting-invite-members"></div><div class="modal-foot"><button type="button" class="btn btn-primary" id="meeting-invite-send">ارسال دعوت</button></div></div></div>
