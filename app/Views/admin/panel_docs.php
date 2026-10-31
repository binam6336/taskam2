<?php
/** @var array $cards [شناسه، آیکون، عنوان، توضیح، ردیف‌ها[[عنوان، مقدار، توضیح، تنظیم .env]]] */
?>
<div class="callout mb-2"><i class="fa-solid fa-circle-info"></i> محدودیت‌ها و قواعد امنیتی مهم پنل. عددها مستقیم از تنظیمات فعلی برنامه (و فایل <code class="ltr">.env</code>) خوانده می‌شوند؛ مواردی که نام تنظیم دارند از <code class="ltr">.env</code> قابل تغییرند. نسخه‌ی برنامه: <b class="ltr"><?= e(trim((string) @file_get_contents(BASE_PATH . '/VERSION'))) ?></b></div>
<nav class="pd-nav mb-2"><?php foreach ($cards as [$id, $icon, $title]): ?><a class="chip" href="#pd-<?= e($id) ?>"><i class="fa-solid <?= e($icon) ?>"></i> <?= e($title) ?></a><?php endforeach; ?></nav>
<div class="pd-grid">
<?php foreach ($cards as [$id, $icon, $title, $desc, $rows]): ?>
    <section class="card pd-card" id="pd-<?= e($id) ?>">
        <div class="card-head"><h3><i class="fa-solid <?= e($icon) ?>"></i> <?= e($title) ?></h3></div>
        <p class="small muted pd-desc"><?= e($desc) ?></p>
        <div class="pd-rows">
        <?php foreach ($rows as [$label, $value, $note, $envKey]): ?>
            <div class="pd-row">
                <div class="pd-label"><?= e($label) ?></div>
                <div class="pd-value"><?= e($value) ?></div>
                <?php if ($note !== '' || $envKey): ?><div class="pd-note xs faint"><?= e($note) ?><?php if ($envKey): ?> <code class="ltr pd-env"><?= e($envKey) ?></code><?php endif; ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
