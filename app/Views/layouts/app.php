<?php
use App\Core\Auth;
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$menu = [
    ['dashboard', '/dashboard', 'fa-gauge-high', 'پیشخوان'],
    ['group' => 'tasks', 'icon' => 'fa-layer-group', 'label' => 'وظایف', 'items' => [
        ['tasks', '/tasks', 'fa-list-check', 'تسک‌ها'],
        ['soon' => true, 'icon' => 'fa-table-columns', 'label' => 'برد تیمی'],
        ['subjects', '/labels', 'fa-tags', 'لیبل‌ها'],
    ]],
    ['group' => 'requests', 'icon' => 'fa-inbox', 'label' => 'درخواست‌ها', 'items' => [
        ['calls', '/calls', 'fa-phone-volume', 'درخواست تماس'],
        ['texts', '/texts', 'fa-message', 'متن‌های آماده'],
    ]],
    ['group' => 'team', 'icon' => 'fa-people-group', 'label' => 'مدیریت تیم', 'items' => [
        ['colleagues', '/colleagues', 'fa-users', 'همکاران'],
        ['projects', '/projects', 'fa-diagram-project', 'پروژه‌ها'],
        ['chat', '/chat', 'fa-comments', 'گفتگو'],
    ]],
    ['group' => 'api', 'icon' => 'fa-code', 'label' => 'وب سرویس', 'items' => [
        ['tokens', '/tokens', 'fa-key', 'توکن API'],
        ['docs', '/docs', 'fa-book', 'مستندات API'],
    ]],
    ['group' => 'settings', 'icon' => 'fa-gear', 'label' => 'تنظیمات', 'items' => [
        ['profile', '/profile', 'fa-user-gear', 'پروفایل'],
        ['install', '/install', 'fa-download', 'نصب'],
    ]],
    ['group' => 'support', 'icon' => 'fa-life-ring', 'label' => 'پشتیبانی', 'items' => [
        ['support', '/support', 'fa-headset', 'تیکت'],
    ]],
];
$badges = array_fill_keys(['tasks', 'colleagues', 'projects', 'chat', 'support'], 0);
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => (int) $u['id']];
// تور آموزشی: تا وقتی کاربر رد یا تمامش نکرده (NULL = هنوز پرسیده نشده)
$tour = array_key_exists('tour', $u) ? $u['tour'] : 'done';
if (!in_array($tour, ['skip', 'done'], true) && !Auth::isImpersonating()) {
    $this->boot['tour'] = ['state' => $tour, 'page' => $active, 'texts' => \App\Models\ContentModel::tour()];
    $this->scripts[] = 'tour';
}
$allLinks = require BASE_PATH . '/config/quick_links.php';
$sel = json_decode((string) ($u['quick_links'] ?? ''), true);
$quick = is_array($sel) ? array_values(array_filter($allLinks, static fn($l) => in_array($l['id'], $sel, true))) : [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e(user_theme($currentUser ?? null)) ?>">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>">
<?php if (Auth::isImpersonating()): ?>
<div class="impersonate">
    <span><i class="fa-solid fa-user-secret"></i> شما در حال مشاهده‌ی پنل «<?= e($name) ?>» هستید.</span>
    <form method="post" action="<?= e(url('/admin/impersonate/exit')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">بازگشت به پنل مدیریت</button></form>
</div>
<?php endif; ?>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="<?= e(url('/')) ?>" class="brand"><span class="logo"><?= logo_svg() ?></span><span>تسکام<small>مدیریت وظایف و تیم</small></span></a>
        <?php require __DIR__ . '/../partials/sidebar_nav.php'; ?>
        <div class="sidebar-foot">
            <div class="flex items-center gap-2 mb-1">
                <div class="avatar sm"><?php if ($u['avatar']): ?><img src="<?= e(avatar_url($u['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($name)) ?><?php endif; ?></div>
                <div class="grow"><div class="truncate bold small"><?= e($name) ?></div><div class="xs muted ltr"><?= e($u['mobile']) ?></div></div>
            </div>
            <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm btn-block"><i class="fa-solid fa-right-from-bracket"></i> خروج</button></form>
        </div>
    </aside>
    <div class="overlay"></div>
    <div class="main">
        <header class="topbar">
            <button class="btn btn-ghost btn-icon menu-btn" data-sidebar aria-label="منو"><i class="fa-solid fa-bars"></i></button>
            <h1><?= e($this->title) ?></h1>
            <div class="grow"></div>
            <button type="button" class="btn btn-ghost btn-icon" id="notif-btn" aria-label="اعلان مرورگر"><i class="fa-regular fa-bell"></i></button>
            <a class="btn btn-ghost btn-icon" href="<?= e(url('/chat')) ?>" title="گفتگو" style="position:relative"><i class="fa-regular fa-comments"></i><span class="badge-count zero" data-badge="chat" style="position:absolute;top:-2px;left:-2px">0</span></a>
        </header>
        <main class="content"><?= $content ?></main>
    </div>
</div>
<div class="qa">
    <button class="btn btn-primary qa-fab" aria-label="دسترسی سریع" <?= $quick ? '' : 'data-qa-empty' ?>><i class="fa-solid fa-bolt"></i></button>
    <?php if ($quick): ?><div class="qa-panel"><?php foreach ($quick as $l): ?><a href="<?= e(url($l['path'])) ?>"><i class="fa-solid <?= e($l['icon']) ?>"></i><?= e($l['title']) ?></a><?php endforeach; ?></div><?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/confirm.php'; ?>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
