<?php  ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<meta name="theme-color" content="#060908">
<meta name="robots" content="noindex, nofollow">
<title><?= e($this->title) ?> • <?= e(env('APP_NAME', 'تسکام')) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri()) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
