<?php
/** @var int $status @var string $message @var ?string $detail */
$titles = [403 => 'دسترسی ممنوع', 404 => 'صفحه پیدا نشد', 405 => 'متد مجاز نیست', 419 => 'نشست منقضی شد', 500 => 'خطای سرور'];
$icons = [403 => 'fa-lock', 404 => 'fa-compass', 419 => 'fa-hourglass-end', 500 => 'fa-triangle-exclamation'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titles[$status] ?? 'خطا') ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri()) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="error-page"><div>
    <div class="code"><?= e(fa_num($status)) ?></div>
    <h2 class="mt-1"><i class="fa-solid <?= e($icons[$status] ?? 'fa-circle-exclamation') ?>" style="color:var(--g-400)"></i> <?= e($titles[$status] ?? 'خطا') ?></h2>
    <p class="muted"><?= e($message) ?></p>
    <?php if (!empty($detail)): ?><pre class="card flat ltr small" style="max-width:640px;margin:1rem auto;text-align:left;white-space:pre-wrap"><?= e($detail) ?></pre><?php endif; ?>
    <div class="flex gap-2" style="justify-content:center"><a class="btn btn-primary" href="<?= e(url('/')) ?>"><i class="fa-solid fa-house"></i> صفحه‌ی اصلی</a></div>
</div></div>
</body>
</html>
