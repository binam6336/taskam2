<?php /** @var bool $pushReady @var int $devices @var bool $https @var array $checks */ $this->scripts[] = 'install'; $this->boot['install'] = ['pushReady' => $pushReady, 'devices' => $devices, 'https' => $https]; ?>
<div class="pwa-hero card mb-2">
    <div class="pwa-logo"><?= logo_svg() ?></div>
    <div class="grow">
        <h2>نصب نرم‌افزار تسکام</h2>
        <p class="muted">تسکام را مثل یک برنامه روی گوشی (اندروید و آیفون) یا کامپیوتر نصب کنید: آیکن جدا روی صفحه‌ی اصلی، اجرای تمام‌صفحه بدون نوار مرورگر و اعلان پیام‌ها و یادآوری‌ها حتی وقتی برنامه بسته است.</p>
        <div class="flex gap-1 wrap items-center mt-1">
            <button type="button" class="btn btn-primary pwa-install-btn" id="pwa-install" disabled><i class="fa-solid fa-download"></i> <span>نصب</span></button>
            <span class="small muted" id="pwa-state">در حال بررسی…</span>
        </div>
        <?php if (!$https): ?><div class="alert warning mt-2"><i class="fa-solid fa-triangle-exclamation"></i><div>نصب و اعلان‌ها فقط روی <b>HTTPS</b> کار می‌کنند. سایت را با https باز کنید.</div></div><?php endif; ?>
    </div>
</div>

<div class="grid grid-2 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bell"></i> اعلان‌ها روی این دستگاه</h3><span class="badge" id="push-badge">—</span></div>
        <p class="small muted">بعد از فعال‌سازی، پیام‌های جدید چت، واگذاری تسک، منشن‌ها و یادآوری مهلت تسک‌ها مثل اعلان یک برنامه‌ی اندرویدی (با صدا، لرزش و آیکن تسکام) می‌رسند؛ حتی وقتی برنامه بسته است.</p>
        <div class="flex gap-1 wrap">
            <button type="button" class="btn btn-primary" id="push-enable"><i class="fa-solid fa-bell"></i> فعال‌سازی اعلان‌ها</button>
            <button type="button" class="btn" id="push-test"><i class="fa-solid fa-paper-plane"></i> ارسال اعلان آزمایشی</button>
            <button type="button" class="btn btn-ghost" id="push-disable"><i class="fa-solid fa-bell-slash"></i> غیرفعال‌سازی</button>
        </div>
        <p class="hint mt-1">دستگاه‌های فعال حساب شما: <b id="push-devices"><?= e(fa_num($devices)) ?></b></p>
        <?php if (!$pushReady): ?>
        <div class="alert error mt-1"><i class="fa-solid fa-triangle-exclamation"></i><div><b>اعلان‌ها روی سرور هنوز آماده نیست.</b> وضعیت پیش‌نیازها:
            <ul class="push-checks">
            <?php foreach ($checks as [$label, $ok, $fix]): ?>
                <li class="<?= $ok ? 'ok' : 'bad' ?>"><i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i> <?= e($label) ?><?php if (!$ok): ?><div class="small"><?= e($fix) ?></div><?php endif; ?></li>
            <?php endforeach; ?>
            </ul></div></div>
        <?php endif; ?>
    </div>
    <div class="card" id="pwa-help"><div class="card-head"><h3><i class="fa-solid fa-circle-info"></i> راهنمای نصب</h3></div>
        <div class="pwa-steps" data-os="android"><b><i class="fa-brands fa-android"></i> اندروید (کروم)</b>
            <ol><li>دکمه‌ی «نصب» بالای همین صفحه را بزنید و در پنجره‌ی کروم «نصب» را تأیید کنید.</li><li>آیکن تسکام به صفحه‌ی اصلی گوشی اضافه می‌شود.</li><li>برای اعلان‌های به‌موقع: تنظیمات گوشی ← برنامه‌ها ← Chrome ← باتری را روی «بدون محدودیت» بگذارید.</li></ol></div>
        <div class="pwa-steps" data-os="ios"><b><i class="fa-brands fa-apple"></i> آیفون (سافاری)</b>
            <ol><li>دکمه‌ی اشتراک‌گذاری <i class="fa-solid fa-arrow-up-from-bracket"></i> پایین سافاری را بزنید.</li><li>«Add to Home Screen / افزودن به صفحه‌ی اصلی» را انتخاب کنید.</li><li>برنامه را از صفحه‌ی اصلی باز کنید و همین‌جا «فعال‌سازی اعلان‌ها» را بزنید (iOS 16.4 به بعد).</li></ol></div>
        <div class="pwa-steps" data-os="desktop"><b><i class="fa-solid fa-desktop"></i> کامپیوتر (کروم / اج)</b>
            <ol><li>دکمه‌ی «نصب» را بزنید، یا آیکن نصب <i class="fa-solid fa-display"></i> کنار نوار آدرس را.</li><li>تسکام در پنجره‌ی جدا و از منوی Start/Launchpad باز می‌شود.</li></ol></div>
    </div>
</div>
