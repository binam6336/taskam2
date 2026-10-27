<?php
use App\Core\Session;

$u = $currentUser;
$name = full_name($u);
$active = $this->page;
$this->boot['flash'] = Session::pullFlash();
$this->boot['live'] = ['scope' => $active, 'uid' => (int) $u['id']];
$items = [
    ['admin-users', '/admin', 'fa-users-gear', 'مدیریت کاربران', 0],
    ['admin-support', '/admin/support', 'fa-headset', 'پشتیبانی', (int) ($supportUnread ?? 0)],
    ['admin-analysis', '/admin/analysis', 'fa-chart-pie', 'تحلیل سیستم', 0],
    ['docs', '/docs', 'fa-book', 'مستندات API', 0],
    ['profile', '/profile', 'fa-user-gear', 'پروفایل من', 0],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="<?= e($this->bodyClass) ?>">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="<?= e(url('/admin')) ?>" class="brand"><span class="logo"><i class="fa-solid fa-shield-halved"></i></span><span>پنل مدیریت<small>تسکام</small></span></a>
        <nav class="nav">
            <div class="nav-title">مدیریت</div>
            <?php foreach ($items as [$key, $path, $icon, $label, $cnt]): ?>
                <a href="<?= e(url($path)) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i class="fa-solid <?= $icon ?>"></i><span><?= e($label) ?></span><?php if ($key === 'admin-support'): ?><span class="badge-count <?= $cnt > 0 ? '' : 'zero' ?>" data-badge="support"><?= e(fa_num($cnt)) ?></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
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
