<?php
/** @var array $u @var array $links @var array $selected @var array $stats @var array|null $sessions */
$name = full_name($u);
$this->scripts[] = 'profile';
?>
<div class="card mb-2"><div class="profile-head">
    <label class="avatar lg av-pick" id="av-preview" for="av-file" title="انتخاب تصویر پروفایل"><?php if ($u['avatar']): ?><img src="<?= e(avatar_url($u['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($name)) ?><?php endif; ?></label>
    <div class="grow"><h2 style="margin:0"><?= e($name) ?></h2><div class="muted ltr" style="text-align:right"><?= e($u['mobile']) ?></div>
        <div class="flex gap-1 mt-1 wrap"><span class="badge green"><?= $u['role'] === 'admin' ? 'مدیر' : 'کاربر' ?></span><span class="chip"><i class="fa-solid fa-list-check"></i> <?= e(fa_num($stats['tasks'])) ?> تسک</span><span class="chip"><i class="fa-solid fa-users"></i> <?= e(fa_num($stats['colleagues'])) ?> همکار</span><span class="chip"><i class="fa-solid fa-diagram-project"></i> <?= e(fa_num($stats['projects'])) ?> پروژه</span></div></div>
    <?php // انتخاب تصویر با آیکون (یا کلیک روی خود آواتار)؛ بعد از انتخاب، خودکار بارگذاری می‌شود ?>
    <form method="post" action="<?= e(url('/profile/avatar')) ?>" enctype="multipart/form-data" data-ajax class="flex gap-1 wrap items-center" id="f-avatar"><?= csrf_field() ?>
        <label class="btn btn-sm file-pick av-pick-btn" title="انتخاب تصویر پروفایل" aria-label="انتخاب تصویر پروفایل"><i class="fa-solid fa-camera"></i> تصویر پروفایل<input type="file" name="avatar" accept="image/*" id="av-file" hidden></label>
        <?php if ($u['avatar']): ?><button type="button" class="btn btn-sm btn-danger" data-post="<?= e(url('/profile/avatar/delete')) ?>" data-confirm="تصویر حذف شود؟">حذف</button><?php endif; ?></form>
</div></div>

<div class="card mb-2" id="appearance"><div class="card-head"><h3><i class="fa-solid fa-palette"></i> تنظیمات ظاهری</h3><span class="hint" style="margin:0">تم در حساب شما ذخیره می‌شود و روی همه‌ی دستگاه‌ها یکسان است.</span></div>
    <form method="post" action="<?= e(url('/profile/appearance')) ?>" id="f-theme" data-favicons="<?= e(json_html(array_combine(array_keys(themes()), array_map('favicon_uri', array_keys(themes()))))) ?>" data-icon-base="<?= e(url('assets/img/apple-touch-icon')) ?>"><?= csrf_field() ?>
        <div class="theme-grid" role="radiogroup" aria-label="تم ظاهری">
        <?php $cur = user_theme($u); foreach (themes() as $key => $th): [$pBg, $pSide, $pCard, $pAcc] = $th['preview']; ?>
            <label class="theme-opt"><input type="radio" name="theme" value="<?= e($key) ?>" <?= $cur === $key ? 'checked' : '' ?>>
                <span class="theme-card">
                    <span class="theme-prev" style="background:<?= e($pBg) ?>" aria-hidden="true">
                        <span class="tp-main"><span class="tp-bar" style="background:<?= e($pAcc) ?>;width:55%"></span><span class="tp-cards"><i style="background:<?= e($pCard) ?>;box-shadow:inset 0 0 0 1px rgba(127,127,127,.25)"></i><i style="background:<?= e($pCard) ?>;box-shadow:inset 0 0 0 1px rgba(127,127,127,.25)"></i></span></span>
                        <span class="tp-side" style="background:<?= e($pSide) ?>"><i style="background:<?= e($pAcc) ?>"></i><i style="background:rgba(127,127,127,.35)"></i><i style="background:rgba(127,127,127,.35)"></i></span>
                    </span>
                    <span class="theme-meta"><span><b><?= e($th['title']) ?></b><br><span><?= e($th['desc']) ?></span></span><span class="theme-check"><i class="fa-solid fa-check"></i></span></span>
                </span></label>
        <?php endforeach; ?>
        </div>
    </form>
</div>

<div class="grid grid-2 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-id-card"></i> اطلاعات حساب</h3><span class="dirty-tag" hidden>تغییرات ذخیره نشده</span></div>
        <form method="post" action="<?= e(url('/profile/info')) ?>" data-ajax data-keep-open data-dirty><?= csrf_field() ?>
            <div class="row"><div class="field"><label>نام</label><input class="input" name="first_name" value="<?= e($u['first_name']) ?>" maxlength="100" required></div><div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" value="<?= e($u['last_name']) ?>" maxlength="100"></div></div>
            <div class="field"><label for="pf-mobile">شماره موبایل <i class="fa-solid fa-lock faint xs" title="غیرقابل ویرایش" aria-hidden="true"></i></label><input class="input ltr" id="pf-mobile" value="<?= e($u['mobile']) ?>" disabled aria-describedby="pf-mobile-hint"><div class="hint" id="pf-mobile-hint">برای تغییر شماره با مدیر تماس بگیرید.</div></div>
            <div class="field"><label>ایمیل</label><input class="input ltr" type="email" name="email" value="<?= e($u['email'] ?? '') ?>" maxlength="150"></div>
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></form></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-lock"></i> تغییر رمز عبور</h3><span class="dirty-tag" hidden>تغییرات ذخیره نشده</span></div>
        <form method="post" action="<?= e(url('/profile/password')) ?>" data-ajax data-dirty><?= csrf_field() ?>
            <div class="field"><label>رمز فعلی</label><input class="input ltr" type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="field"><label>رمز جدید</label><input class="input ltr" type="password" name="new_password" autocomplete="new-password" minlength="8" data-pw-meter required><div class="hint">حداقل ۸ نویسه شامل حرف و عدد. بعد از تغییر، سایر دستگاه‌ها خارج می‌شوند.</div></div>
            <div class="field"><label>تکرار رمز جدید</label><input class="input ltr" type="password" name="confirm_password" autocomplete="new-password" data-pw-match="new_password" required></div>
            <button class="btn btn-primary"><i class="fa-solid fa-key"></i> تغییر رمز</button></form></div>
</div>

<?php if ($sessions !== null): $others = count(array_filter($sessions, static fn($s) => !$s['current'])); ?>
<div class="card mb-2" id="sessions"><div class="card-head"><h3><i class="fa-solid fa-desktop"></i> نشست‌های فعال</h3>
    <?php if ($others > 0): ?><button class="btn btn-sm btn-danger" data-post="<?= e(url('/profile/sessions/revoke-others')) ?>" data-confirm="همه‌ی دستگاه‌های دیگر (<?= e(fa_num($others)) ?> دستگاه) از حساب شما خارج شوند؟" data-after="reload"><i class="fa-solid fa-right-from-bracket"></i> خروج همه‌ی دستگاه‌های دیگر</button><?php endif; ?></div>
    <p class="hint mb-1">دستگاه‌هایی که الان با حساب شما وارد هستند. اگر دستگاهی را نمی‌شناسید، خارجش کنید و رمز عبور را عوض کنید.</p>
    <div class="list">
    <?php foreach ($sessions as $s): ?><div class="item">
        <span class="avatar sm"><i class="fa-solid <?= preg_match('/Android|iPhone|iPad|Mobile/', (string) $s['user_agent']) ? 'fa-mobile-screen' : 'fa-laptop' ?>"></i></span>
        <div class="grow"><div class="bold"><?= e(device_name($s['user_agent'])) ?> <?php if ($s['current']): ?><span class="badge green">همین دستگاه</span><?php endif; ?></div>
            <div class="xs muted"><span class="ltr"><?= e((string) $s['ip']) ?></span> • ورود: <?= e(jdate((string) $s['created_at'])) ?> • آخرین فعالیت: <?= e(time_ago(date('Y-m-d H:i:s', (int) $s['last_activity']))) ?></div></div>
        <?php if (!$s['current']): ?><button class="btn btn-sm" data-post="<?= e(url('/profile/sessions/revoke')) ?>" data-data='<?= e(json_encode(['id' => $s['id_hash']])) ?>' data-confirm="این دستگاه از حساب شما خارج شود؟" data-after="reload" aria-label="خروج این دستگاه"><i class="fa-solid fa-right-from-bracket"></i> خروج</button><?php endif; ?>
    </div><?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-user-shield"></i> حریم خصوصی</h3></div>
        <form method="post" action="<?= e(url('/profile/privacy')) ?>" data-ajax data-keep-open data-autosave data-after="none"><?= csrf_field() ?>
            <label class="check mb-1"><input type="checkbox" name="allow_colleague_requests" value="1" <?= (int) $u['allow_colleague_requests'] === 1 ? 'checked' : '' ?>><div><b>دریافت درخواست همکاری</b><span>دیگران بتوانند شما را با شماره موبایل پیدا و اضافه کنند.</span></div></label>
            <label class="check mb-2"><input type="checkbox" name="project_join_requires_approval" value="1" <?= (int) $u['project_join_requires_approval'] === 1 ? 'checked' : '' ?>><div><b>تأیید دستی عضویت</b><span>افزودن به پروژه فقط پس از تأیید شما انجام شود (درخواست همکاری همیشه نیاز به تأیید شما دارد).</span></div></label>
            <p class="hint autosave-hint"><i class="fa-solid fa-cloud-arrow-up"></i> تغییرات خودکار ذخیره می‌شود.</p></form></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-lock"></i> امنیت تسک‌ها</h3></div>
        <form method="post" action="<?= e(url('/profile/task-security')) ?>" data-ajax data-keep-open data-autosave data-after="none"><?= csrf_field() ?>
            <div class="field"><label for="task-link-access">دسترسی به مشاهده‌ی تسک از طریق لینک برای</label>
                <div class="flex items-center gap-1 wrap"><select class="input" id="task-link-access" name="task_link_access" style="max-width:240px">
                    <?php foreach (\App\Services\TaskService::LINK_ACCESS as $k => [$label]): ?><option value="<?= e($k) ?>" <?= ($u['task_link_access'] ?? 'members') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select><span class="muted">فعال است.</span></div></div>
            <ul class="hint mb-2" style="padding-inline-start:1.1rem;line-height:2">
                <?php foreach (\App\Services\TaskService::LINK_ACCESS as [$label, $desc]): ?><li><b><?= e($label) ?>:</b> <?= e($desc) ?></li><?php endforeach; ?>
            </ul>
            <p class="hint mb-2">سازنده، مسئول و همکارانی که به تسک‌هایتان دسترسی داده‌اید همیشه تسک را می‌بینند. اگر کسی بدون دسترسی لینک را باز کند، پیام خطا می‌گیرد و به صفحه‌ی تسک‌های خودش برمی‌گردد.</p>
            <p class="hint autosave-hint"><i class="fa-solid fa-cloud-arrow-up"></i> تغییرات خودکار ذخیره می‌شود.</p></form></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bolt"></i> دسترسی سریع</h3></div>
        <form method="post" action="<?= e(url('/profile/quick-links')) ?>" data-ajax data-keep-open data-autosave data-after="none"><?= csrf_field() ?>
            <div class="perm-grid mb-2"><?php foreach ($links as $l): ?><label class="check"><input type="checkbox" name="links[]" value="<?= e($l['id']) ?>" <?= in_array($l['id'], $selected, true) ? 'checked' : '' ?>><div><b><i class="fa-solid <?= e($l['icon']) ?>"></i> <?= e($l['title']) ?></b></div></label><?php endforeach; ?></div>
            <p class="hint autosave-hint"><i class="fa-solid fa-cloud-arrow-up"></i> تغییرات خودکار ذخیره می‌شود.</p></form></div>
</div>
