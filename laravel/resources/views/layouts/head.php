<?php $th = user_theme($currentUser ?? null); $imp = \App\Core\Impersonation::current(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(\App\Core\Session::isActive() || $this->bodyClass !== 'docs' ? csrf_token() : '') ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<meta name="theme-color" content="<?= e(themes()[$th]['meta']) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= $imp ? '👤 ' . e(full_name($imp['target'])) . ' — ' : '' ?><?= e($this->title) ?> • <?= e(app_name()) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri($th)) ?>">
<link rel="manifest" href="<?= e(app_base() . '/manifest') ?>" crossorigin="use-credentials">
<link rel="apple-touch-icon" href="<?= e(app_base() . '/assets/img/apple-touch-icon' . icon_suffix($th) . '.png') ?>">
<meta name="application-name" content="<?= e(app_name()) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(app_name()) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?php if (!$imp): // پنجره‌ی ورود به پنل کاربر: بدون service worker و اشتراک اعلان (اعلان‌های کاربر به دستگاه مدیر نمی‌رسد) ?><meta name="sw-url" content="<?= e(url('service-worker')) ?>"><?php endif; ?>
<script src="<?= e(asset('js/rail.js')) ?>"></script>
<?php // v7.3 سرعت: فونت‌های پرکاربرد زودتر دریافت شوند (همان آدرس‌های CSS، بدون ?v تا با کش یکی باشند) ?>
<?php // v7.18: فونت سراسری Noto Sans Arabic (متغیر، یک فایل برای همه‌ی وزن‌ها؛ ارقام فارسی و لاتین از همین خانواده) ?>
<link rel="preload" href="<?= e(app_base() . '/assets/vendor/noto-sans-arabic/fonts/NotoSansArabic-arabic.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(app_base() . '/assets/vendor/noto-sans-arabic/fonts/NotoSansArabic-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('vendor/noto-sans-arabic/noto-sans-arabic.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/kx-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/kx.css')) ?>">

<link rel="stylesheet" href="<?= e(asset('css/meetings.css')) ?>">
