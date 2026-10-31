<?php
/** @var array $users @var array $counts @var array $myPerms @var bool $isSuper @var array $permDefs @var int $superId */
$can = static fn(string $p) => in_array($p, $myPerms, true);
$stLbl = ['pending' => ['در انتظار', 'yellow'], 'active' => ['فعال', 'green'], 'deactivated' => ['غیرفعال', ''], 'blocked' => ['مسدود', 'red']];
$this->scripts[] = 'admin';
$me = (int) $currentUser['id'];
?>
<?php if (!\App\Core\Crypto::configured()): ?><div class="alert warning"><i class="fa-solid fa-lock-open"></i><div><b>رمزگذاری پیام‌های چت فعال نیست.</b> پیام‌ها در دیتابیس به‌صورت متن‌ساده ذخیره می‌شوند. در فایل <code>.env</code> مقدار <code>ENCODE_PASSWORD</code> را (حداقل ۱۲ نویسه) تنظیم کنید و سپس <code>php bin/chat-crypto.php encrypt-all</code> را برای پیام‌های قبلی اجرا کنید.</div></div><?php endif; ?>
<div class="grid grid-4 mb-2" data-live="a-stats">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-users"></i></div><div><div class="num"><?= e(fa_num($counts['total'])) ?></div><div class="lbl">کل کاربران</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-user-check"></i></div><div><div class="num"><?= e(fa_num($counts['active'])) ?></div><div class="lbl">فعال</div></div></div>
    <div class="card stat"><div class="ico warn"><i class="fa-solid fa-user-clock"></i></div><div><div class="num"><?= e(fa_num($counts['pending'])) ?></div><div class="lbl">در انتظار تأیید</div></div></div>
    <div class="card stat"><div class="ico danger"><i class="fa-solid fa-user-slash"></i></div><div><div class="num"><?= e(fa_num($counts['blocked'] + $counts['deactivated'])) ?></div><div class="lbl">مسدود / غیرفعال</div></div></div>
</div>
<div class="page-head"><div class="search" style="min-width:280px"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="user-filter" placeholder="جستجوی نام یا موبایل…"></div>
    <?php if ($can('add_user')): ?><button class="btn btn-primary" id="btn-new-user"><i class="fa-solid fa-user-plus"></i> کاربر جدید</button><?php endif; ?></div>
<div class="table-wrap" data-live="a-users"><table class="table" id="users-table"><thead><tr><th>کاربر</th><th>موبایل</th><th>نقش</th><th>وضعیت</th><th>آخرین ورود</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u):
    $isAdmin = $u['role'] === 'admin'; $self = (int) $u['id'] === $me; $isSuperRow = (int) $u['id'] === $superId;
    $protected = $self || $isSuperRow || ($isAdmin && !$isSuper);
    $perms = \App\Services\Access::decode($u['admin_permissions'], array_keys($permDefs));
    $editData = ['first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'mobile' => $u['mobile'], 'email' => $u['email'], 'role' => $u['role'], 'admin_permissions' => $perms]; ?>
    <tr data-search="<?= e(mb_strtolower(full_name($u) . ' ' . $u['mobile'])) ?>">
        <td><div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($u), 'src' => avatar_url($u['avatar']), 'size' => 'sm']); ?><div><div class="bold"><?= e(full_name($u)) ?></div><div class="xs muted"><?= e($u['email'] ?? '') ?></div></div></div></td>
        <td class="ltr"><?= e($u['mobile']) ?></td>
        <td><?php if ($isSuperRow): ?><span class="badge green">مدیر کل</span><?php elseif ($isAdmin): ?><span class="badge blue">مدیر</span><?php else: ?><span class="badge">کاربر</span><?php endif; ?></td>
        <td><span class="badge <?= e($stLbl[$u['status']][1]) ?>"><?= e($stLbl[$u['status']][0]) ?></span></td>
        <td class="small muted"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : '—' ?></td>
        <td class="nowrap"><div class="flex gap-1">
            <?php if ($can('toggle_status') && !$protected): ?>
                <div class="dropdown"><button class="btn btn-xs" data-toggle>وضعیت <i class="fa-solid fa-chevron-down xs"></i></button><div class="dropdown-menu" style="min-width:150px">
                    <?php foreach ($stLbl as $k => [$l]): if ($k === $u['status']) { continue; } ?><button data-post="<?= e(url('/admin/users/' . $u['id'] . '/status')) ?>" data-data='<?= e(json_encode(['status' => $k])) ?>'><?= e($l) ?></button><?php endforeach; ?></div></div>
            <?php endif; ?>
            <?php if ($can('edit_user') && (!$isAdmin || $isSuper || $self)): ?><button class="btn btn-xs btn-icon" data-edit-user='<?= e(json_html($editData)) ?>' data-uid="<?= (int) $u['id'] ?>" title="ویرایش"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            <?php if ($can('impersonate') && !$isAdmin && $u['status'] === 'active'): ?><button class="btn btn-xs btn-icon" data-post="<?= e(url('/admin/users/' . $u['id'] . '/impersonate')) ?>" data-after="redirect:/dashboard" data-confirm="وارد پنل «<?= e(full_name($u)) ?>» شوید؟" data-danger="0" title="ورود به پنل کاربر"><i class="fa-solid fa-user-secret"></i></button><?php endif; ?>
            <?php if ($can('delete_user') && !$protected): ?><button class="btn btn-xs btn-icon btn-danger" data-post="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="کاربر «<?= e(full_name($u)) ?>» و تمام داده‌هایش برای همیشه حذف شود؟" title="حذف"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
        </div></td></tr>
<?php endforeach; ?></tbody></table></div>

<div class="modal" id="m-user"><div class="modal-box"><form method="post" action="<?= e(url('/admin/users')) ?>" data-ajax id="f-user"><?= csrf_field() ?>
    <div class="modal-head"><h3><i class="fa-solid fa-user-gear"></i> <span id="user-modal-title">کاربر جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="row"><div class="field"><label>نام *</label><input class="input" name="first_name" required maxlength="100"></div><div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" maxlength="100"></div></div>
        <div class="row"><div class="field"><label>موبایل *</label><input class="input ltr" name="mobile" data-mobile required></div><div class="field"><label>ایمیل</label><input class="input ltr" type="email" name="email"></div></div>
        <div class="field"><label>رمز عبور <span class="faint" id="pw-hint">(الزامی)</span></label><input class="input ltr" type="password" name="password" autocomplete="new-password" minlength="8"></div>
        <div class="row"><div class="field" id="status-field"><label>وضعیت</label><select class="input" name="status"><option value="active">فعال</option><option value="pending">در انتظار</option><option value="deactivated">غیرفعال</option><option value="blocked">مسدود</option></select></div>
            <?php if ($isSuper): ?><div class="field"><label>نقش</label><select class="input" name="role" id="role-select"><option value="user">کاربر</option><option value="admin">مدیر</option></select></div><?php endif; ?></div>
        <?php if ($isSuper): ?><div class="field hidden" id="perm-field"><label>دسترسی‌های مدیر</label><div class="perm-grid"><?php foreach ($permDefs as $k => $l): ?><label class="check"><input type="checkbox" name="admin_permissions[]" value="<?= e($k) ?>"><div><b><?= e($l) ?></b></div></label><?php endforeach; ?></div></div><?php endif; ?>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div></form></div></div>
