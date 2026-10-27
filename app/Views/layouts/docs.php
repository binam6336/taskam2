<?php
/** @var array $spec @var string $current @var string $baseUrl @var bool $loggedIn @var string $content */
$this->scripts[] = 'docs';
$isActive = static fn(string $k): string => $current === $k ? ' active' : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="<?= e(user_theme($currentUser ?? null)) ?>">
<head><?php require __DIR__ . '/head.php'; ?></head>
<body class="docs">
<header class="dx-nav">
    <button class="btn btn-ghost btn-icon dx-burger" data-dx-menu aria-label="منو"><i class="fa-solid fa-bars"></i></button>
    <a class="dx-brand" href="<?= e(url('/docs')) ?>"><span class="logo"><?= logo_svg() ?></span><b>تسکام</b><span class="dx-tag">API v1</span></a>
    <nav class="dx-links">
        <a href="<?= e(url('/docs')) ?>" class="<?= $current === 'home' ? 'on' : '' ?>"><i class="fa-solid fa-house"></i> خانه</a>
        <a href="<?= e(url('/docs/authentication')) ?>" class="<?= $current === 'auth' ? 'on' : '' ?>"><i class="fa-solid fa-key"></i> ارسال توکن</a>
        <a href="<?= e(url('/docs/errors')) ?>" class="<?= $current === 'errors' ? 'on' : '' ?>"><i class="fa-solid fa-triangle-exclamation"></i> کد خطاها</a>
    </nav>
    <div class="dx-search"><i class="fa-solid fa-magnifying-glass"></i><input id="dx-q" class="input" placeholder="جستجو در مستندات…  ( / )" autocomplete="off"><div class="dx-results" id="dx-results" hidden></div></div>
    <a class="btn btn-sm btn-primary dx-panel" href="<?= e(url($loggedIn ? '/tokens' : '/login')) ?>"><i class="fa-solid fa-key"></i> <?= $loggedIn ? 'ساخت توکن' : 'ورود به پنل' ?></a>
</header>
<div class="dx-wrap">
    <aside class="dx-side" id="dx-side">
        <a class="dx-item<?= $isActive('home') ?>" href="<?= e(url('/docs')) ?>"><i class="fa-solid fa-house"></i> خانه</a>
        <a class="dx-item<?= $isActive('auth') ?>" href="<?= e(url('/docs/authentication')) ?>"><span class="dx-get">GET</span> نحوه دریافت و ارسال توکن</a>
        <a class="dx-item<?= $isActive('errors') ?>" href="<?= e(url('/docs/errors')) ?>"><i class="fa-solid fa-triangle-exclamation"></i> قالب پاسخ و کد خطاها</a>
        <?php foreach ($spec['groups'] as $g):
            $open = $current === 'g:' . $g['id'] || str_starts_with($current, 'e:' . $g['id'] . '/'); ?>
        <details class="dx-group" <?= $open ? 'open' : '' ?>>
            <summary><i class="fa-solid <?= e($g['icon']) ?>"></i><span><?= e($g['title']) ?></span><i class="fa-solid fa-chevron-down dx-chev"></i></summary>
            <a class="dx-sub<?= $isActive('g:' . $g['id']) ?>" href="<?= e(url('/docs/' . $g['id'])) ?>">نمای کلی</a>
            <?php foreach ($g['endpoints'] as $ep): $s = \App\Services\DocsHelper::slug($ep); ?>
                <a class="dx-sub<?= $isActive('e:' . $g['id'] . '/' . $s) ?>" href="<?= e(\App\Services\DocsHelper::url($g['id'], $ep)) ?>"><span class="m m-<?= strtolower($ep['method']) ?>"><?= e($ep['method']) ?></span><span class="truncate"><?= e($ep['summary']) ?></span></a>
            <?php endforeach; ?>
        </details>
        <?php endforeach; ?>
    </aside>
    <main class="dx-main-wrap"><?= $content ?></main>
</div>
<div class="toasts"></div>
<?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
