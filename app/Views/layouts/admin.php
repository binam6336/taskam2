<?php
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => pid('users', $u)];
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
        ['admin-security', '/admin/security', 'fa-shield-halved', 'امنیت'],
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
    <?php $sb = [
        'home' => url('/admin'), 'title' => 'پنل مدیریت', 'sub' => 'تسکام', 'user' => $u, 'user_sub' => \App\Core\Auth::ADMIN_ROLES[\App\Core\Auth::adminRole(\App\Core\Auth::user() ?? []) ?? 'internal']['label'],
        'logout' => 'خروج از حساب', 'desc' => [],
    ];
    require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="overlay"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
        </header>
        <main class="content" id="main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
