<?php
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => (int) $u['id']];
$menu = [
    ['admin-analysis', '/admin/analysis', 'fa-gauge-high', 'پیشخوان'],
    ['group' => 'users', 'icon' => 'fa-user-shield', 'label' => 'مدیریت کاربران', 'items' => [
        ['admin-users', '/admin', 'fa-users-gear', 'لیست کاربران'],
    ]],
    ['group' => 'content', 'icon' => 'fa-pen-to-square', 'label' => 'محتوا', 'items' => [
        ['admin-content-sidebar', '/admin/content/sidebar', 'fa-bars-staggered', 'سایدبار'],
        ['admin-content-tour', '/admin/content/tour', 'fa-route', 'تور آموزشی'],
        ['admin-content-ph', '/admin/content/placeholders', 'fa-i-cursor', 'Placeholder'],
        ['admin-content-errors', '/admin/content/errors', 'fa-triangle-exclamation', 'خطاها'],
    ]],
    ['group' => 'api', 'icon' => 'fa-code', 'label' => 'وب سرویس', 'items' => [
        ['docs', '/docs', 'fa-book', 'مستندات API'],
    ]],
    ['group' => 'settings', 'icon' => 'fa-gear', 'label' => 'تنظیمات', 'items' => [
        ['profile', '/profile', 'fa-user-gear', 'پروفایل من'],
        ['admin-install', '/install', 'fa-download', 'نصب'],
        ['admin-panel-docs', '/admin/docs', 'fa-book-open', 'مستندات پنل'],
    ]],
    ['group' => 'support', 'icon' => 'fa-life-ring', 'label' => 'پشتیبانی', 'items' => [
        ['admin-support', '/admin/support', 'fa-headset', 'تیکت‌ها'],
    ]],
];
if (!\App\Core\Auth::adminCan('edit_content')) {
    $menu = array_values(array_filter($menu, static fn($m) => ($m['group'] ?? '') !== 'content'));
}
$badges = ['admin-support' => (int) ($supportUnread ?? 0)];
$badgeAs = ['admin-support' => 'support'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e(user_theme($currentUser ?? null)) ?>">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>" data-layout="admin">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="<?= e(url('/admin')) ?>" class="brand"><span class="logo"><?= logo_svg() ?></span><span>پنل مدیریت<small>تسکام</small></span></a>
        <?php require __DIR__ . '/../partials/sidebar_nav.php'; ?>
        <div class="sidebar-foot">
            <div class="flex items-center gap-2 mb-1">
                <div class="avatar sm"><?php if ($u['avatar']): ?><img src="<?= e(avatar_url($u['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($name)) ?><?php endif; ?></div>
                <div class="grow"><div class="truncate bold small"><?= e($name) ?></div><div class="xs muted">مدیر</div></div>
            </div>
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-block"><i class="fa-solid fa-right-from-bracket"></i> خروج</button></form>
        </div>
    </aside>
    <div class="overlay"></div>
    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
        </header>
        <main class="content"><?= $content ?></main>
    </div>
</div>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
