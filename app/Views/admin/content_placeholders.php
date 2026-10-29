<?php
/** @var array $groups گروه => [شناسه => [path, url, default, value, changed]] */
$this->scripts[] = 'content';
$titles = [
    'auth'     => ['fa-right-to-bracket', 'ورود و ثبت‌نام', 'blue'],
    'tasks'    => ['fa-list-check', 'تسک‌ها', 'green'],
    'board'    => ['fa-table-columns', 'برد تیمی', 'blue'],
    'projects' => ['fa-diagram-project', 'پروژه‌ها و همکاران', 'purple'],
    'calls'    => ['fa-phone-volume', 'درخواست تماس و متن‌های آماده', 'yellow'],
    'chat'     => ['fa-comments', 'گفتگو', 'blue'],
    'support'  => ['fa-headset', 'پشتیبانی', 'yellow'],
    'api'      => ['fa-code', 'وب سرویس', 'purple'],
];
$total = array_sum(array_map('count', $groups));
?>
<div class="callout mb-2"><i class="fa-solid fa-circle-info"></i> متن کم‌رنگ داخل فیلدها (placeholder) در پنل کاربران، صفحه‌ی ورود و مستندات API. مسیر هر فیلد بالای کارتش نوشته شده؛ تغییر بلافاصله برای همه اعمال می‌شود.</div>
<div class="er-bar mb-2">
    <div class="search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="er-q" type="search" placeholder="جستجو در مسیر، متن یا شناسه…" autocomplete="off"></div>
    <label class="check er-only"><input type="checkbox" id="er-changed"><div><b>فقط ویرایش‌شده‌ها</b></div></label>
    <span class="badge"><?= e(fa_num($total)) ?> فیلد</span>
</div>
<div class="er-empty card empty hidden" id="er-none"><i class="fa-solid fa-magnifying-glass"></i><p>موردی با این جستجو پیدا نشد.</p></div>
<?php foreach ($titles as $g => [$icon, $title, $tone]): if (empty($groups[$g])) continue; ?>
<section class="er-section" data-er-group>
<h3 class="ct-group"><span class="er-ico <?= e($tone) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span> <?= e($title) ?> <span class="badge"><?= e(fa_num(count($groups[$g]))) ?></span></h3>
<div class="ct-grid mb-2">
    <?php foreach ($groups[$g] as $id => $t): ?>
    <form class="card ct-card er-card ph-card<?= $t['changed'] ? ' changed' : '' ?>" method="post" action="<?= e(url('/admin/content/placeholders/' . $id)) ?>" data-ajax data-after="none"
          data-search="<?= e(mb_strtolower($id . ' ' . $t['path'] . ' ' . $t['url'] . ' ' . $t['default'] . ' ' . $t['value'])) ?>">
        <?= csrf_field() ?>
        <div class="ct-head">
            <span class="ph-path"><i class="fa-solid fa-location-dot"></i> <?= e($t['path']) ?></span>
            <span class="badge yellow ct-badge">ویرایش‌شده</span>
        </div>
        <div class="ph-url xs faint ltr mb-1"><?= e($t['url']) ?></div>
        <input class="input" name="body" maxlength="150" required data-default="<?= e($t['default']) ?>" value="<?= e($t['value']) ?>" placeholder="<?= e($t['value']) ?>" aria-label="متن placeholder">
        <div class="ct-default xs faint mt-1">پیش‌فرض: <?= e($t['default']) ?></div>
        <div class="flex gap-1 mt-1 items-center">
            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button>
            <button class="btn btn-sm ct-reset" type="button" data-ct-reset><i class="fa-solid fa-rotate-left"></i> متن پیش‌فرض</button>
            <code class="ltr xs faint er-id"><?= e($id) ?></code>
        </div>
    </form>
    <?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
