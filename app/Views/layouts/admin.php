<?php
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => pid('users', $u)];
$this->boot['clock'] = ['now' => time(), 'tz' => (int) date('Z')];   // v7.7: «امروز» در JS = تاریخ سرور (TK.jalali.now)
$menu = [
    ['admin-analysis', '/admin/analysis', 'fa-gauge-high', 'پیشخوان'],
    ['group' => 'users', 'icon' => 'fa-user-shield', 'label' => 'مدیریت کاربران', 'items' => [
        ['admin-users', '/admin', 'fa-users-gear', 'لیست کاربران'],
    ]],
    ['group' => 'content', 'icon' => 'fa-pen-to-square', 'label' => 'محتوا', 'items' => [
        ['section' => 'user-panel', 'icon' => 'fa-user', 'label' => 'پنل کاربری', 'items' => [
            ['admin-content-sidebar', '/admin/content/sidebar', 'fa-bars-staggered', 'سایدبار'],
            ['admin-content-tour', '/admin/content/tour', 'fa-route', 'تور آموزشی'],
            ['admin-content-board', '/admin/content/board', 'fa-table-columns', 'بورد'],
            ['admin-content-ph', '/admin/content/placeholders', 'fa-i-cursor', 'Placeholder'],
            ['admin-content-errors', '/admin/content/errors', 'fa-triangle-exclamation', 'خطاها'],
        ]],
    ]],
    ['group' => 'settings', 'icon' => 'fa-gear', 'label' => 'تنظیمات', 'items' => [
        ['section' => 'admin', 'icon' => 'fa-user-gear', 'label' => 'ادمین', 'items' => [
            ['profile', '/profile', 'fa-user-gear', 'پروفایل'],
        ]],
        ['section' => 'security', 'icon' => 'fa-shield-halved', 'label' => 'امنیت', 'items' => [
            ['admin-security', '/admin/security', 'fa-shield-halved', 'ورودها', 'desc' => 'گزارش ورود به حساب کاربران و تغییرات'],
            ['admin-access', '/admin/access', 'fa-user-lock', 'دسترسی'],
        ]],
        ['section' => 'usage', 'icon' => 'fa-chart-pie', 'label' => 'مدیریت مصرف', 'items' => [
            ['admin-ai-usage', '/admin/ai-usage', 'fa-chart-pie', 'مصرف هوش مصنوعی'],
            ['admin-storage', '/admin/storage', 'fa-hard-drive', 'فضای ذخیره‌سازی'],
        ]],
        ['admin-panel-docs', '/admin/docs', 'fa-book-open', 'درباره کارلیکس', 'desc' => 'مستندات و قواعد پنل'],
        ['admin-side-panels', '/admin/side-panels', 'fa-table-columns', 'پنل‌های جانبی'],
    ]],
    ['group' => 'support', 'icon' => 'fa-life-ring', 'label' => 'پشتیبانی', 'items' => [
        ['admin-support', '/admin/support', 'fa-headset', 'تیکت‌ها'],
    ]],
];
// v7.4: هر آیتم فقط با دسترسی مربوطش دیده می‌شود (AdminAccess)؛ «دسترسی» فقط برای مدیر کل؛ گروه خالی حذف می‌شود
$need = ['admin-analysis' => 'view_analysis', 'admin-users' => 'view_users', 'admin-content-sidebar' => 'edit_sidebar', 'admin-content-tour' => 'edit_tour',
    'admin-content-board' => 'edit_board', 'admin-content-ph' => 'edit_placeholders', 'admin-content-errors' => 'edit_errors', 'admin-panel-docs' => 'view_panel_docs',
    'admin-security' => 'view_security', 'admin-support' => 'support_view', 'admin-ai-usage' => 'manage_ai_usage', 'admin-storage' => 'manage_storage'];
$filterItems = static function (array $items) use (&$filterItems, $need): array {
    $out = [];
    foreach ($items as $i) {
        if (isset($i['items'])) {
            $i['items'] = $filterItems($i['items']);
            if ($i['items']) { $out[] = $i; }
        } elseif (in_array($i[0] ?? '', ['admin-access', 'admin-side-panels'], true)
            ? \App\Core\Auth::isSuperAdmin()
            : (!isset($need[$i[0] ?? '']) || \App\Core\Auth::adminCan($need[$i[0]]))) {
            $out[] = $i;
        }
    }
    return $out;
};
$menu = $filterItems($menu);
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
        'logout' => 'خروج از حساب', 'desc' => [], 'admin' => true,
    ];
    require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="overlay"></div>
    <div class="main">
        <header class="topbar">
            <button type="button" class="tb-ib menu-btn" data-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="باز کردن منو"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            <div class="tb-title"><h1 class="tb-h"><?= e($this->title) ?></h1></div>
            <?php require __DIR__ . '/../partials/topbar_search.php'; ?>
            <div class="tb-acts"><?php $admDark = (themes()[user_theme($u)]['mode'] ?? 'light') === 'dark'; ?><button type="button" class="tb-ib" data-theme-toggle aria-label="<?= $admDark ? 'حالت روشن' : 'حالت تیره' ?>" title="<?= $admDark ? 'حالت روشن' : 'حالت تیره' ?>"><i class="fa-solid <?= $admDark ? 'fa-sun' : 'fa-moon' ?>" aria-hidden="true"></i></button></div>
        </header>
        <main class="content" id="main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
