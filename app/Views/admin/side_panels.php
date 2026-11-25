<?php $this->scripts[] = 'admin_side_panels'; ?>
<div class="callout mb-2"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> دفتر پنل‌های جانبی فقط برای مدیر کل است. اطلاعات ورود رمزگذاری می‌شوند و فقط با درخواست شما نمایش داده می‌شوند.</div>
<?php if (!$cryptoReady): ?><div class="callout mb-2">رمزگذاری آماده نیست؛ کلید فعلی رمزگذاری و OpenSSL را بررسی کنید.</div><?php endif; ?>
<details class="card mb-2" id="side-panel-create"><summary>افزودن پنل جانبی</summary>
<?php $panel = null; require __DIR__ . '/side_panel_form.php'; ?>
</details>
<div class="side-panels">
<?php foreach ($panels as $panel): ?>
<article class="card side-panel" data-panel="<?= e($panel['id']) ?>">
<?php if ($panel['unavailable']): ?>
    <h2>پنل غیرقابل خواندن</h2><p class="muted">اطلاعات پنل جانبی رمزگشایی نشد؛ کلید رمزگذاری را بررسی کنید.</p>
<?php else: ?>
    <h2><?= e($panel['title']) ?></h2>
    <p class="side-panel-description"><?= e($panel['description']) ?></p>
    <a class="btn btn-primary" href="<?= e($panel['url']) ?>" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">ورود به پنل <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
    <p class="small muted ltr side-panel-url"><?= e($panel['url']) ?></p>
    <p class="small muted">نام کاربری: <?= $panel['has_username'] ? 'ثبت شده' : 'ندارد' ?> · رمز عبور: <?= $panel['has_password'] ? 'ثبت شده' : 'ندارد' ?></p>
    <?php if ($panel['has_username'] || $panel['has_password']): ?>
    <button type="button" class="btn btn-sm" data-panel-reveal="<?= e(url('/admin/side-panels/' . $panel['id'] . '/credentials')) ?>" aria-expanded="false">نمایش اطلاعات ورود</button>
    <div class="side-panel-secrets mt-1" hidden aria-live="polite"></div>
    <?php endif; ?>
    <details class="mt-2"><summary>ویرایش پنل</summary><?php require __DIR__ . '/side_panel_form.php'; ?></details>
<?php endif; ?>
    <form class="mt-2" method="post" action="<?= e(url('/admin/side-panels/' . $panel['id'] . '/delete')) ?>" data-ajax data-confirm="این پنل جانبی حذف شود؟">
        <?= csrf_field() ?><button type="submit" class="btn btn-sm danger">حذف پنل</button>
    </form>
</article>
<?php endforeach; ?>
</div>
<?php if (!$panels): ?><p class="muted">هنوز پنل جانبی ثبت نشده است.</p><?php endif; ?>
