<?php
/** @var string $status */
$map = [
    'pending' => ['fa-hourglass-half', 'در انتظار تأیید', 'ثبت‌نام شما انجام شد. پس از تأیید مدیر می‌توانید از پنل استفاده کنید. این صفحه به‌صورت خودکار بررسی می‌شود.', 'yellow'],
    'deactivated' => ['fa-user-slash', 'حساب غیرفعال است', 'حساب شما غیرفعال شده است. برای فعال‌سازی با مدیر تماس بگیرید.', 'red'],
    'blocked' => ['fa-ban', 'حساب مسدود است', 'دسترسی شما به سامانه مسدود شده است.', 'red'],
];
[$icon, $title, $text, $color] = $map[$status] ?? $map['pending'];
$this->boot['pendingPoll'] = $status === 'pending';
$this->scripts[] = 'auth';
?>
<div class="card center">
    <div class="stat" style="justify-content:center;margin-bottom:1rem"><div class="ico <?= $color === 'red' ? 'danger' : 'warn' ?>" style="width:72px;height:72px;font-size:1.8rem"><i class="fa-solid <?= e($icon) ?>"></i></div></div>
    <h2><?= e($title) ?></h2>
    <p class="muted"><?= e($text) ?></p>
    <form method="post" action="<?= e(url('/logout')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn"><i class="fa-solid fa-right-from-bracket"></i> خروج</button></form>
</div>
