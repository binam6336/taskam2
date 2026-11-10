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
        ['admin-content-board', '/admin/content/board', 'fa-table-columns', 'بورد'],
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
        ['admin-access', '/admin/access', 'fa-user-lock', 'دسترسی'],
    ]],
    ['group' => 'support', 'icon' => 'fa-life-ring', 'label' => 'پشتیبانی', 'items' => [
        ['admin-support', '/admin/support', 'fa-headset', 'تیکت‌ها'],
    ]],
];
// v7.4: هر آیتم فقط با دسترسی مربوطش دیده می‌شود (AdminAccess)؛ «دسترسی» فقط برای مدیر کل؛ گروه خالی حذف می‌شود
$need = ['admin-analysis' => 'view_analysis', 'admin-users' => 'view_users', 'admin-content-sidebar' => 'edit_sidebar', 'admin-content-tour' => 'edit_tour',
    'admin-content-board' => 'edit_board', 'admin-content-ph' => 'edit_placeholders', 'admin-content-errors' => 'edit_errors', 'admin-panel-docs' => 'view_panel_docs',
    'admin-security' => 'view_security', 'admin-support' => 'support_view'];
$okItem = static fn(array $i) => !isset($i[0]) || ($i[0] === 'admin-access' ? \App\Core\Auth::isSuperAdmin() : (!isset($need[$i[0]]) || \App\Core\Auth::adminCan($need[$i[0]])));
$menu = array_values(array_filter(array_map(static function ($m) use ($okItem) {
    if (isset($m['group'])) {
        $m['items'] = array_values(array_filter($m['items'], $okItem));
        return $m['items'] ? $m : null;
    }
    return $okItem($m) ? $m : null;
}, $menu)));
$badges = ['admin-support' => (int) ($supportUnread ?? 0)];
$badgeAs = ['admin-support' => 'support'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" <?= theme_attrs($currentUser ?? null) ?>>
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>" data-layout="admin">
<div class="app">
    <?php $sb = [
        'home' => url('/admin'), 'title' => 'پنل مدیریت', 'sub' => 'کارلیکس', 'user' => $u, 'user_sub' => \App\Core\Auth::ADMIN_ROLES[\App\Core\Auth::adminRole(\App\Core\Auth::user() ?? []) ?? 'internal']['label'],
        'logout' => 'خروج از حساب', 'desc' => [],
    ];
    require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="overlay"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
            <?php require __DIR__ . '/../partials/topbar_search.php'; ?>
        </header>
        <main class="content" id="main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
