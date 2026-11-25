<?php
use KarlixLaravel\Auth\Authentication as Auth;
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$menu = \KarlixLaravel\Repositories\SidebarModel::nav(); // چیدمان از «محتوا ← سایدبار» در پنل ادمین (پیش‌فرض: config/sidebar.php)
$badges = array_fill_keys(['tasks', 'colleagues', 'projects', 'chat', 'support'], 0);
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => pid('users', $u)];
$this->boot['clock'] = ['now' => time(), 'tz' => (int) date('Z')];   // v7.7: «امروز» در JS = تاریخ سرور (TK.jalali.now)
// تور آموزشی: تا وقتی کاربر رد یا تمامش نکرده (NULL = هنوز پرسیده نشده)
$tour = array_key_exists('tour', $u) ? $u['tour'] : 'done';
if (($_GET['tour'] ?? '') === 'start') {
    $tour = null;                                     // «آموزش» در سایدبار: پرسش شروع تور؛ وضعیت فقط با POST /tour ذخیره می‌شود
}
if (!in_array($tour, ['skip', 'done'], true) && !Auth::isImpersonating()) {
    $this->boot['tour'] = ['state' => $tour, 'page' => $active, 'texts' => \KarlixLaravel\Repositories\ContentModel::tour()];
    $this->scripts[] = 'tour';
}
$meetingInvitations = $meetingInvitations ?? (Auth::isImpersonating() ? [] : (new \KarlixLaravel\Services\Meetings\MeetingService())->invitations((int) $u['id']));
$this->boot['meetingInvitations'] = $meetingInvitations;
$this->scripts[] = 'meeting_invites';
$this->scripts[] = 'quicktask';                       // «ثبت سریع تسک» (دکمه‌ی صاعقه، همه‌ی صفحه‌ها)
$this->scripts[] = 'blocked';                         // پنجره‌ی «مسدودشده‌ها» (مسدود کردن به‌جای حذف)
// نوار بالا (v7.15): تغییر روشن/تیره‌ی همان پالت، زنگ اعلان‌ها (NotificationCenter)، گفتگو و منوی «ایجاد»
$tbTheme = user_theme($u);
$tbDark = (themes()[$tbTheme]['mode'] ?? 'light') === 'dark';
$tbNotifs = \KarlixLaravel\Services\NotificationCenter::unread((int) $u['id']);
$badges['notifs'] = $tbNotifs;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" <?= theme_attrs($currentUser ?? null) ?>>
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>" data-layout="app">
<div class="app">
    <?php $sb = [
        'home' => url('/dashboard'), 'title' => 'کارلیکس', 'sub' => 'مدیریت وظایف و تیم', 'user' => $u, 'user_sub' => fa_num((string) $u['mobile']), 'user_sub_ltr' => true,
        'logout' => Auth::isImpersonating() ? 'بستن این پنجره' : 'خروج از حساب', 'desc' => [],   // توضیح‌ها داخل خود $menu (SidebarModel::nav)
        'search' => url('/search'),                                // پالت = جستجوی سراسری محتوا (v7.13)
    ];
    $tbSearch = true;
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
        <?php if (\KarlixLaravel\Auth\Authentication::isReadOnly($u)): // v7.8: حساب غیرفعال = فقط مشاهده ?>
        <div class="ro-bar" role="status"><i class="fa-solid fa-eye" aria-hidden="true"></i> <span>حساب شما <b>غیرفعال</b> است: همه‌چیز را می‌بینید ولی ثبت، ارسال پیام یا تغییر وضعیت ممکن نیست. برای فعال‌سازی با مدیر تماس بگیرید.</span></div>
        <?php endif; ?>
        <header class="topbar">
            <button type="button" class="tb-ib menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="باز کردن منو"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            <div class="tb-title"><?php if (!empty($this->crumbs)): ?><div class="tb-crumbs"><?php foreach ($this->crumbs as [$cl, $ch]): ?><?php if ($ch): ?><a href="<?= e(url($ch)) ?>"><?= e($cl) ?></a><i class="fa-solid fa-chevron-left" aria-hidden="true"></i><?php else: ?><span><?= e($cl) ?></span><?php endif; ?><?php endforeach; ?></div><?php endif; ?><h1 class="tb-h"><?= e($this->title) ?></h1></div>
            <?php require __DIR__ . '/../partials/topbar_search.php'; ?>
            <div class="tb-acts">
                <button type="button" class="tb-ib tb-hide-sm" data-theme-toggle aria-pressed="<?= $tbDark ? 'true' : 'false' ?>" aria-label="<?= $tbDark ? 'حالت روشن' : 'حالت تیره' ?>" title="<?= $tbDark ? 'حالت روشن' : 'حالت تیره' ?>"><i class="fa-solid <?= $tbDark ? 'fa-sun' : 'fa-moon' ?>" aria-hidden="true"></i></button>
                <button type="button" class="tb-ib" id="nt-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="nt-panel" aria-label="اعلان‌ها<?= $tbNotifs ? '، ' . e(fa_num($tbNotifs)) . ' خوانده‌نشده' : '' ?>" title="اعلان‌ها"><i class="fa-regular fa-bell" aria-hidden="true"></i><span class="tb-ind badge-count<?= $tbNotifs ? '' : ' zero' ?>" data-badge="notifs" aria-hidden="true"><?= e(fa_num($tbNotifs)) ?></span></button>
                <div class="tb-create">
                    <button type="button" class="btn btn-primary tb-create-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="tb-create-menu"><i class="fa-solid fa-plus" aria-hidden="true"></i><span class="tb-hide-sm">ایجاد</span></button>
                    <div class="tb-menu" id="tb-create-menu" role="menu" aria-label="ایجاد" hidden>
                        <button type="button" role="menuitem" data-create="task"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>تسک جدید</span></button>
                        <button type="button" role="menuitem" data-quick-task><i class="fa-solid fa-bolt" aria-hidden="true"></i><span>ثبت سریع تسک</span></button>
                        <div class="menu-sep" role="separator"></div>
                        <a role="menuitem" href="<?= e(url('/projects?new=1')) ?>" data-create="project"><i class="fa-solid fa-folder" aria-hidden="true"></i><span>پروژه‌ی جدید</span></a>
                        <a role="menuitem" href="<?= e(url('/notes?new=1')) ?>"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i><span>یادداشت جدید</span></a>
                        <a role="menuitem" href="<?= e(url('/colleagues?add=1')) ?>"><i class="fa-solid fa-user-plus" aria-hidden="true"></i><span>دعوت همکار</span></a>
                        <a role="menuitem" href="<?= e(url('/support?new=1')) ?>"><i class="fa-solid fa-life-ring" aria-hidden="true"></i><span>تیکت پشتیبانی</span></a>
                    </div>
                </div>
            </div>
        </header>
        <main class="content" id="main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<div class="nt-panel" id="nt-panel" role="dialog" aria-modal="false" aria-labelledby="nt-title" hidden>
    <div class="nt-head"><b id="nt-title">اعلان‌ها</b><span class="nt-cnt" data-nt-cnt></span><button type="button" class="btn btn-ghost btn-sm" data-nt-all hidden>خواندن همه</button><button type="button" class="tb-ib nt-x" data-nt-close aria-label="بستن اعلان‌ها"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
    <div class="nt-req" data-nt-req hidden></div>
    <ul class="nt-list" data-nt-list aria-live="polite" aria-busy="true"></ul>
    <div class="nt-foot"><button type="button" class="btn btn-ghost btn-sm" data-nt-more hidden>اعلان‌های قدیمی‌تر</button><a class="btn btn-ghost btn-sm" data-nt-chat href="<?= e(url('/chat')) ?>">همه در گفتگوی ربات</a><button type="button" class="btn btn-ghost btn-sm" id="notif-btn" aria-label="اعلان مرورگر"><i class="fa-regular fa-bell" aria-hidden="true"></i> <span>اعلان مرورگر</span></button></div>
</div>
<div class="modal meeting-invite-modal" id="m-meeting-incoming" role="dialog" aria-modal="true" aria-labelledby="meeting-incoming-title"><div class="modal-box"><div class="modal-head"><h3 id="meeting-incoming-title">به جلسه دعوت شده‌اید</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="بستن">×</button></div><div class="modal-body" data-meeting-incoming-body></div></div></div>
<div id="meeting-invitations" class="meeting-invitations" aria-live="polite" hidden></div>
<div class="qa">
    <button type="button" class="btn btn-primary qa-fab" aria-label="ثبت سریع تسک" title="ثبت سریع تسک" data-quick-task><i class="fa-solid fa-bolt"></i></button>
</div>
<?php require __DIR__ . '/../partials/quick_task.php'; ?>
<?php require __DIR__ . '/../partials/blocked_modal.php'; ?>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
