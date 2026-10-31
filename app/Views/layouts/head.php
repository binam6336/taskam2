<?php $th = user_theme($currentUser ?? null); ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(\App\Core\Session::isActive() || $this->bodyClass !== 'docs' ? csrf_token() : '') ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<meta name="theme-color" content="<?= e(themes()[$th]['meta']) ?>">
<meta name="robots" content="noindex, nofollow">
<title><?= e($this->title) ?> • <?= e(env('APP_NAME', 'تسکام')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri($th)) ?>">
<link rel="manifest" href="<?= e(url('manifest')) ?>" crossorigin="use-credentials">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/apple-touch-icon' . icon_suffix($th) . '.png')) ?>">
<meta name="application-name" content="<?= e(env('APP_NAME', 'تسکام')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= e(env('APP_NAME', 'تسکام')) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="sw-url" content="<?= e(url('service-worker')) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
