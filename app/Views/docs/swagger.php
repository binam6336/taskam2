<?php
/**
 * «تست API» (v7.8): Swagger UI روی سند /docs/openapi.json. صفحه‌ی مستقل با swagger-ui.css (بدون app.css برای جلوگیری از تداخل کلاس‌ها).
 * نوار بالا: وارد کردن توکن، یا ساخت توکن موقت با نشست پنل / موبایل + رمز (+ کپچا) ← POST /docs/swagger/token.
 * @var ?array $me @var bool $captcha @var string $csrf
 */
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e($csrf) ?>">
<title>تست API • <?= e(app_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri()) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/vazirmatn/Vazirmatn-font-face.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/swagger-ui/swagger-ui.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/swagger.css')) ?>">
</head>
<body>
<header class="sw-top">
    <a class="sw-brand" href="<?= e(url('/docs')) ?>"><span class="sw-logo"><?= logo_svg('', THEME_DEFAULT) ?></span><b>تست API</b><span class="sw-tag">Swagger</span></a>
    <a class="sw-back" href="<?= e(url('/docs')) ?>">بازگشت به مستندات ←</a>
</header>
<section class="sw-auth" id="sw-auth" data-openapi="<?= e(url('/docs/openapi.json')) ?>" data-token-url="<?= e(url('/docs/swagger/token')) ?>" data-captcha-url="<?= e($captcha ? url('/captcha') : '') ?>">
    <div class="sw-card">
        <h2>۱) توکن دارید؟</h2>
        <p>توکن API را از پنل ← «توکن‌های API» بسازید و این‌جا وارد کنید.</p>
        <form id="sw-tok" class="sw-row"><input id="sw-tok-in" type="password" dir="ltr" placeholder="tk_…" autocomplete="off" aria-label="توکن API"><button type="submit">اعمال توکن</button></form>
    </div>
    <div class="sw-card">
        <h2>۲) یا توکن موقت بسازید</h2>
        <?php if ($me): ?>
            <p>با حساب «<?= e(full_name($me)) ?>» وارد پنل هستید؛ یک توکن تست یک‌روزه بسازید.</p>
            <form id="sw-gen" class="sw-row"><button type="submit">ساخت توکن با حساب فعلی</button></form>
        <?php else: ?>
            <p>با موبایل و رمز پنل یک توکن تست یک‌روزه بگیرید (همان محدودیت‌های صفحه‌ی ورود).</p>
            <form id="sw-gen" class="sw-row sw-wrap">
                <input name="mobile" dir="ltr" inputmode="numeric" placeholder="09xxxxxxxxx" autocomplete="username" aria-label="موبایل" required>
                <input name="password" type="password" dir="ltr" placeholder="رمز عبور" autocomplete="current-password" aria-label="رمز عبور" required>
                <?php if ($captcha): ?><span class="sw-cap"><img id="sw-cap-img" alt="کد امنیتی" width="120" height="40"><button type="button" id="sw-cap-new" title="کد جدید" aria-label="کد امنیتی جدید">↻</button><input name="captcha" dir="ltr" placeholder="کد امنیتی" autocomplete="off" aria-label="کد امنیتی" required></span><?php endif; ?>
                <button type="submit">دریافت توکن</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="sw-state" id="sw-state" role="status" aria-live="polite">توکنی اعمال نشده است؛ درخواست‌ها بدون توکن خطای ۴۰۱ می‌گیرند.</div>
</section>
<main id="swagger" dir="ltr"></main>
<script src="<?= e(asset('vendor/swagger-ui/swagger-ui-bundle.js')) ?>"></script>
<script src="<?= e(asset('js/swagger-init.js')) ?>"></script>
</body>
</html>
