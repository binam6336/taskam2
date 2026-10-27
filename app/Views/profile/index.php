<?php
/** @var array $u @var array $links @var array $selected @var array $stats */
$name = full_name($u);
$this->scripts[] = 'profile';
?>
<div class="card mb-2"><div class="profile-head">
    <div class="avatar lg" id="av-preview"><?php if ($u['avatar']): ?><img src="<?= e(avatar_url($u['avatar'])) ?>" alt=""><?php else: ?><?= e(initial($name)) ?><?php endif; ?></div>
    <div class="grow"><h2 style="margin:0"><?= e($name) ?></h2><div class="muted ltr" style="text-align:right"><?= e($u['mobile']) ?></div>
        <div class="flex gap-1 mt-1 wrap"><span class="badge green"><?= $u['role'] === 'admin' ? 'مدیر' : 'کاربر' ?></span><span class="chip"><i class="fa-solid fa-list-check"></i> <?= e(fa_num($stats['tasks'])) ?> تسک</span><span class="chip"><i class="fa-solid fa-users"></i> <?= e(fa_num($stats['colleagues'])) ?> همکار</span><span class="chip"><i class="fa-solid fa-diagram-project"></i> <?= e(fa_num($stats['projects'])) ?> پروژه</span></div></div>
    <form method="post" action="<?= e(url('/profile/avatar')) ?>" enctype="multipart/form-data" data-ajax class="flex gap-1 wrap"><?= csrf_field() ?>
        <input class="input" type="file" name="avatar" accept="image/*" id="av-file" required style="max-width:240px"><button class="btn btn-primary btn-sm">تغییر تصویر</button>
        <?php if ($u['avatar']): ?><button type="button" class="btn btn-sm btn-danger" data-post="<?= e(url('/profile/avatar/delete')) ?>" data-confirm="تصویر حذف شود؟">حذف</button><?php endif; ?></form>
</div></div>

<div class="grid grid-2 mb-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-id-card"></i> اطلاعات حساب</h3></div>
        <form method="post" action="<?= e(url('/profile/info')) ?>" data-ajax data-keep-open><?= csrf_field() ?>
            <div class="row"><div class="field"><label>نام</label><input class="input" name="first_name" value="<?= e($u['first_name']) ?>" maxlength="100" required></div><div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" value="<?= e($u['last_name']) ?>" maxlength="100"></div></div>
            <div class="field"><label>شماره موبایل</label><input class="input ltr" value="<?= e($u['mobile']) ?>" disabled><div class="hint">برای تغییر شماره با مدیر تماس بگیرید.</div></div>
            <div class="field"><label>ایمیل</label><input class="input ltr" type="email" name="email" value="<?= e($u['email'] ?? '') ?>" maxlength="150"></div>
            <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></form></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-lock"></i> تغییر رمز عبور</h3></div>
        <form method="post" action="<?= e(url('/profile/password')) ?>" data-ajax><?= csrf_field() ?>
            <div class="field"><label>رمز فعلی</label><input class="input ltr" type="password" name="current_password" autocomplete="current-password" required></div>
            <div class="field"><label>رمز جدید</label><input class="input ltr" type="password" name="new_password" autocomplete="new-password" minlength="8" required><div class="hint">حداقل ۸ نویسه شامل حرف و عدد. بعد از تغییر، سایر دستگاه‌ها خارج می‌شوند.</div></div>
            <div class="field"><label>تکرار رمز جدید</label><input class="input ltr" type="password" name="confirm_password" autocomplete="new-password" required></div>
            <button class="btn btn-primary"><i class="fa-solid fa-key"></i> تغییر رمز</button></form></div>
</div>

<div class="grid grid-2">
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-user-shield"></i> حریم خصوصی</h3></div>
        <form method="post" action="<?= e(url('/profile/privacy')) ?>" data-ajax data-keep-open><?= csrf_field() ?>
            <label class="check mb-1"><input type="checkbox" name="allow_colleague_requests" value="1" <?= (int) $u['allow_colleague_requests'] === 1 ? 'checked' : '' ?>><div><b>دریافت درخواست همکاری</b><span>دیگران بتوانند شما را با شماره موبایل پیدا و اضافه کنند.</span></div></label>
            <label class="check mb-2"><input type="checkbox" name="project_join_requires_approval" value="1" <?= (int) $u['project_join_requires_approval'] === 1 ? 'checked' : '' ?>><div><b>تأیید دستی عضویت</b><span>افزودن به پروژه یا لیست همکاران فقط پس از تأیید شما انجام شود.</span></div></label>
            <button class="btn btn-primary">ذخیره</button></form></div>
    <div class="card"><div class="card-head"><h3><i class="fa-solid fa-bolt"></i> دسترسی سریع</h3></div>
        <form method="post" action="<?= e(url('/profile/quick-links')) ?>" data-ajax data-keep-open><?= csrf_field() ?>
            <div class="perm-grid mb-2"><?php foreach ($links as $l): ?><label class="check"><input type="checkbox" name="links[]" value="<?= e($l['id']) ?>" <?= in_array($l['id'], $selected, true) ? 'checked' : '' ?>><div><b><i class="fa-solid <?= e($l['icon']) ?>"></i> <?= e($l['title']) ?></b></div></label><?php endforeach; ?></div>
            <button class="btn btn-primary">ذخیره</button></form></div>
</div>
