<?php
use App\Core\Session;

$this->boot['flash'] = Session::pullFlash();
$authDark = (themes()[user_theme($currentUser ?? null)]['mode'] ?? 'light') === 'dark';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" <?= theme_attrs($currentUser ?? null) ?>>
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="auth-body">
<?php /* v7.16 (دموی UI): دوستونه — پنل برند تیره (فقط دسکتاپ) + فرم */ ?>
<main class="auth" id="main-content">
    <section class="auth-brand" aria-hidden="true">
        <div class="ab-inner">
            <div class="ab-head"><span class="logo ab-logo"><?= logo_svg() ?></span><span class="ab-name"><?= e(app_name()) ?></span></div>
            <h2 class="ab-title">کارها را مرتب ببینید،<br>تیم را هماهنگ نگه دارید.</h2>
            <ul class="ab-points">
                <li><i class="fa-solid fa-circle-check"></i>بورد اختصاصی هر پروژه با گردش کار تأیید سرپرست</li>
                <li><i class="fa-solid fa-chart-line"></i>تحلیل عملکرد و بار کاری اعضا</li>
                <li><i class="fa-solid fa-bell"></i>یادآوری سررسید و اعلان روی همه‌ی دستگاه‌ها</li>
            </ul>
        </div>
    </section>
    <section class="auth-form">
        <div class="auth-card">
            <div class="auth-top">
                <span class="auth-mini"><span class="logo"><?= logo_svg() ?></span><b><?= e(app_name()) ?></b></span>
                <button type="button" class="tb-ib" data-theme-toggle aria-label="<?= $authDark ? 'حالت روشن' : 'حالت تیره' ?>" title="<?= $authDark ? 'حالت روشن' : 'حالت تیره' ?>"><i class="fa-solid <?= $authDark ? 'fa-sun' : 'fa-moon' ?>" aria-hidden="true"></i></button>
            </div>
            <?= $content ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
