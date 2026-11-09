<?php $th = user_theme($currentUser ?? null); $imp = \App\Core\Impersonation::current(); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(\App\Core\Session::isActive() || $this->bodyClass !== 'docs' ? csrf_token() : '') ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<meta name="theme-color" content="<?= e(themes()[$th]['meta']) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= $imp ? '👤 ' . e(full_name($imp['target'])) . ' — ' : '' ?><?= e($this->title) ?> • <?= e(env('APP_NAME', 'تسکام')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri($th)) ?>">
<link rel="manifest" href="<?= e(app_base() . '/manifest') ?>" crossorigin="use-credentials">
<link rel="apple-touch-icon" href="<?= e(app_base() . '/assets/img/apple-touch-icon' . icon_suffix($th) . '.png') ?>">
<meta name="application-name" content="<?= e(env('APP_NAME', 'تسکام')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(env('APP_NAME', 'تسکام')) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?php if (!$imp): // پنجره‌ی ورود به پنل کاربر: بدون service worker و اشتراک اعلان (اعلان‌های کاربر به دستگاه مدیر نمی‌رسد) ?><meta name="sw-url" content="<?= e(url('service-worker')) ?>"><?php endif; ?>
<script src="<?= e(asset('js/rail.js')) ?>"></script>
<?php // v7.3 سرعت: فونت‌های پرکاربرد زودتر دریافت شوند (همان آدرس‌های CSS، بدون ?v تا با کش یکی باشند) ?>
<link rel="preload" href="<?= e(app_base() . '/assets/vendor/vazirmatn/fonts/webfonts/Vazirmatn-Regular.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(app_base() . '/assets/vendor/vazirmatn/fonts/webfonts/Vazirmatn-Bold.woff2') ?>" as="font" type="font/woff2" crossorigin>
<?php foreach (glob(BASE_PATH . '/public/assets/vendor/fontawesome/webfonts/taskam-solid.*.woff2') ?: [] as $fa): ?><link rel="preload" href="<?= e(app_base() . '/assets/vendor/fontawesome/webfonts/' . basename($fa)) ?>" as="font" type="font/woff2" crossorigin><?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('vendor/vazirmatn/Vazirmatn-font-face.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/taskam-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
