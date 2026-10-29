<?php
/** @var array $groups گروه => [شناسه => [default, desc, value, changed, vars]] */
$this->scripts[] = 'content';
$titles = [
    'auth'       => ['fa-right-to-bracket', 'ورود، ثبت‌نام و رمز عبور', 'blue'],
    'session'    => ['fa-shield-halved', 'نشست، دسترسی عمومی و ارتباط', 'purple'],
    'tasks'      => ['fa-list-check', 'تسک‌ها', 'green'],
    'notes'      => ['fa-note-sticky', 'یادداشت‌ها و پیوست‌ها', 'green'],
    'upload'     => ['fa-cloud-arrow-up', 'آپلود فایل و تصویر', 'yellow'],
    'labels'     => ['fa-tags', 'لیبل‌ها', 'green'],
    'board'      => ['fa-table-columns', 'برد تیمی', 'blue'],
    'projects'   => ['fa-diagram-project', 'پروژه‌ها و دسترسی اعضا', 'purple'],
    'colleagues' => ['fa-users', 'همکاران', 'blue'],
    'calls'      => ['fa-phone-volume', 'درخواست تماس', 'yellow'],
    'texts'      => ['fa-message', 'متن‌های آماده', 'green'],
    'chat'       => ['fa-comments', 'گفتگو', 'blue'],
    'support'    => ['fa-headset', 'پشتیبانی', 'yellow'],
    'profile'    => ['fa-user-gear', 'پروفایل و توکن API', 'purple'],
    'push'       => ['fa-bell', 'اعلان‌ها', 'yellow'],
];
$total = array_sum(array_map('count', $groups));
$changed = 0;
foreach ($groups as $g) { foreach ($g as $t) { $changed += $t['changed'] ? 1 : 0; } }
?>
<div class="callout mb-2"><i class="fa-solid fa-circle-info"></i> همه‌ی خطاهایی که کاربران ممکن است در پنل (و صفحه‌ی ورود) ببینند، با توضیح اینکه چه زمانی و چرا نمایش داده می‌شوند. متن هر خطا را می‌توانید عوض کنید؛ تغییر بلافاصله برای همه اعمال می‌شود. عبارت‌های <code class="ltr">{…}</code> با مقدار واقعی (مثلاً نام وضعیت یا عدد) پر می‌شوند و می‌توانید جایشان را در متن عوض کنید.</div>
<div class="er-bar mb-2">
    <div class="search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="er-q" type="search" placeholder="جستجو در متن، توضیح یا شناسه‌ی خطا…" autocomplete="off"></div>
    <label class="check er-only"><input type="checkbox" id="er-changed"><div><b>فقط ویرایش‌شده‌ها</b></div></label>
    <span class="badge"><?= e(fa_num($total)) ?> خطا</span>
    <?php if ($changed): ?><span class="badge yellow"><?= e(fa_num($changed)) ?> ویرایش‌شده</span><?php endif; ?>
</div>
<div class="er-empty card empty hidden" id="er-none"><i class="fa-solid fa-magnifying-glass"></i><p>خطایی با این جستجو پیدا نشد.</p></div>
<?php foreach ($titles as $g => [$icon, $title, $tone]): if (empty($groups[$g])) continue; ?>
<section class="er-section" data-er-group>
<h3 class="ct-group"><span class="er-ico <?= e($tone) ?>"><i class="fa-solid <?= e($icon) ?>"></i></span> <?= e($title) ?> <span class="badge"><?= e(fa_num(count($groups[$g]))) ?></span></h3>
<div class="ct-grid mb-2">
    <?php foreach ($groups[$g] as $id => $t): ?>
    <form class="card ct-card er-card<?= $t['changed'] ? ' changed' : '' ?>" method="post" action="<?= e(url('/admin/content/errors/' . $id)) ?>" data-ajax data-after="none"
          data-search="<?= e(mb_strtolower($id . ' ' . $t['default'] . ' ' . $t['value'] . ' ' . $t['desc'])) ?>">
        <?= csrf_field() ?>
        <div class="ct-head">
            <span class="er-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= e($t['value']) ?></span>
            <span class="badge yellow ct-badge">ویرایش‌شده</span>
        </div>
        <p class="er-why"><b>چرا نمایش داده می‌شود؟</b> <?= e($t['desc']) ?></p>
        <textarea class="input" name="body" rows="2" maxlength="300" required data-default="<?= e($t['default']) ?>" aria-label="متن خطا"><?= e($t['value']) ?></textarea>
        <?php if ($t['vars']): ?><div class="er-vars xs muted mt-1">متغیرها: <?php foreach ($t['vars'] as $v): ?><code class="ltr">{<?= e($v) ?>}</code> <?php endforeach; ?></div><?php endif; ?>
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
