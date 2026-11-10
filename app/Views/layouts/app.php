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
$this->boot['clock'] = ['now' => time(), 'tz' => (int) date('Z')];   // v7.7: «امروز» در JS = تاریخ سرور (TK.jalali.now)
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
// نوار بالا (v7.1): چهار همکار اخیر (همکاری تأییدشده، مسدودنشده) + «دعوت»
$tbAll = array_values(array_filter(\App\Services\ColleagueService::listFor((int) $u['id'], '', 30), static fn($c) => $c['approved'] && !$c['is_blocked_by_me'] && !$c['has_blocked_me']));
$tbColsN = count($tbAll);
$tbCols = array_slice($tbAll, 0, 4);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" <?= theme_attrs($currentUser ?? null) ?>>
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>" data-layout="app">
<div class="app">
    <?php $sb = [
        'home' => url('/dashboard'), 'title' => 'کارلیکس', 'sub' => 'مدیریت وظایف و تیم', 'user' => $u, 'user_sub' => fa_num((string) $u['mobile']), 'user_sub_ltr' => true,
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
        <?php if (\App\Core\Auth::isReadOnly($u)): // v7.8: حساب غیرفعال = فقط مشاهده ?>
        <div class="ro-bar" role="status"><i class="fa-solid fa-eye" aria-hidden="true"></i> <span>حساب شما <b>غیرفعال</b> است: همه‌چیز را می‌بینید ولی ثبت، ارسال پیام یا تغییر وضعیت ممکن نیست. برای فعال‌سازی با مدیر تماس بگیرید.</span></div>
        <?php endif; ?>
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
            <?php require __DIR__ . '/../partials/topbar_search.php'; ?>
            <div class="tb-team">
                <?php if ($tbCols): ?><a class="tb-stack" href="<?= e(url('/colleagues')) ?>" aria-label="همکاران (<?= e(fa_num($tbColsN)) ?> نفر)" title="همکاران"><?php foreach ($tbCols as $c): $cn = full_name($c); ?><span class="avatar sm" title="<?= e($cn) ?>"><?php if (!empty($c['avatar'])): ?><img loading="lazy" decoding="async" src="<?= e(avatar_url($c['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($cn)) ?><?php endif; ?></span><?php endforeach; ?><?php if ($tbColsN > count($tbCols)): ?><span class="avatar sm tb-more">+<?= e(fa_num($tbColsN - count($tbCols))) ?></span><?php endif; ?></a><?php endif; ?>
                <a class="btn btn-sm tb-invite" href="<?= e(url('/colleagues?add=1')) ?>" title="دعوت همکار"><span>دعوت</span> <i class="fa-solid fa-plus" aria-hidden="true"></i></a>
            </div>
            <button type="button" class="btn btn-ghost btn-icon" id="notif-btn" aria-label="اعلان مرورگر"><i class="fa-regular fa-bell"></i></button>
            <a class="btn btn-ghost btn-icon tb-chat" href="<?= e(url('/chat')) ?>" title="گفتگو" aria-label="گفتگو"><i class="fa-regular fa-comments"></i><span class="dc-d19037 badge-count zero" data-badge="chat">0</span></a>
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
