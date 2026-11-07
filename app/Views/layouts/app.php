<?php
use App\Core\Auth;
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$menu = \App\Models\SidebarModel::nav(); // چیدمان از «محتوا ← سایدبار» در پنل ادمین (پیش‌فرض: config/sidebar.php)
$badges = array_fill_keys(['tasks', 'colleagues', 'projects', 'chat', 'support'], 0);
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => pid('users', $u)];
// تور آموزشی: تا وقتی کاربر رد یا تمامش نکرده (NULL = هنوز پرسیده نشده)
$tour = array_key_exists('tour', $u) ? $u['tour'] : 'done';
if (($_GET['tour'] ?? '') === 'start') {
    $tour = null;                                     // «آموزش» در سایدبار: پرسش شروع تور؛ وضعیت فقط با POST /tour ذخیره می‌شود
}
if (!in_array($tour, ['skip', 'done'], true) && !Auth::isImpersonating()) {
    $this->boot['tour'] = ['state' => $tour, 'page' => $active, 'texts' => \App\Models\ContentModel::tour()];
    $this->scripts[] = 'tour';
}
$this->scripts[] = 'quicktask';                       // «ثبت سریع تسک» (دکمه‌ی صاعقه، همه‌ی صفحه‌ها)
$this->scripts[] = 'blocked';                         // پنجره‌ی «مسدودشده‌ها» (مسدود کردن به‌جای حذف)
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e(user_theme($currentUser ?? null)) ?>">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>" data-layout="app">
<div class="app">
    <?php $sb = [
        'home' => url('/dashboard'), 'title' => 'تسکام', 'sub' => 'مدیریت وظایف و تیم', 'user' => $u, 'user_sub' => $u['mobile'], 'user_sub_ltr' => true,
        'logout' => Auth::isImpersonating() ? 'بستن این پنجره' : 'خروج از حساب', 'desc' => [],   // توضیح‌ها داخل خود $menu (SidebarModel::nav)
    ];
    require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="overlay"></div>
    <div class="main">
        <?php if ($impCtx = \App\Core\Impersonation::current()): ?>
        <div class="impersonate" role="status">
    <span><i class="fa-solid fa-user-secret"></i> پنل «<?= e($name) ?>» با نقش مدیر «<?= e(full_name($impCtx['admin'])) ?>»</span>
    <span class="xs faint" title="همه‌ی کارهای این پنجره به نام مدیر در لاگ ثبت می‌شود">بسته شدن خودکار: <?= e(fa_num(intdiv(\App\Core\Impersonation::idleSeconds(), 60))) ?> دقیقه بی‌فعالیتی یا <?= e(jdate((string) $impCtx['expires_at'])) ?></span>
    <form method="post" action="<?= e(url('/admin/impersonate/exit')) ?>" data-no-nav data-imp-exit><?= csrf_field() ?><button class="btn btn-sm btn-primary"><i class="fa-solid fa-right-from-bracket"></i> پایان و بستن این پنجره</button></form>
</div>
<?php endif; ?>
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
            <div class="grow"></div>
            <button type="button" class="btn btn-ghost btn-icon" id="notif-btn" aria-label="اعلان مرورگر"><i class="fa-regular fa-bell"></i></button>
            <a class="btn btn-ghost btn-icon" href="<?= e(url('/chat')) ?>" title="گفتگو" data-css="position:relative"><i class="fa-regular fa-comments"></i><span class="badge-count zero" data-badge="chat" data-css="position:absolute;top:-2px;left:-2px">0</span></a>
        </header>
        <main class="content" id="main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<div class="qa">
    <button type="button" class="btn btn-primary qa-fab" aria-label="ثبت سریع تسک" title="ثبت سریع تسک" data-quick-task><i class="fa-solid fa-bolt"></i></button>
</div>
<?php require __DIR__ . '/../partials/quick_task.php'; ?>
<?php require __DIR__ . '/../partials/blocked_modal.php'; ?>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
