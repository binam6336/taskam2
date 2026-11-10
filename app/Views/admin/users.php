<?php
/** @var array $adminRoles @var array $users @var int $total @var int $pg @var int $pages @var int $per @var string $q @var array $perOptions @var array $counts @var array $myPerms @var bool $isSuper @var array $permDefs @var int $superId */
$can = static fn(string $p) => in_array($p, $myPerms, true);
$stLbl = ['pending' => ['در انتظار', 'yellow'], 'active' => ['فعال', 'green'], 'deactivated' => ['غیرفعال', ''], 'blocked' => ['مسدود', 'red']];
$this->scripts[] = 'admin';
$this->scripts[] = 'admin_list';   // صفحه‌بندی مشترک پنجره‌ها
$this->boot['adminList'] = ['per' => $perOptions];
$me = (int) $currentUser['id'];
?>
<?php if (!\App\Core\Crypto::configured()): ?><div class="alert warning"><i class="fa-solid fa-lock-open"></i><div><b>رمزگذاری پیام‌های چت فعال نیست.</b> پیام‌ها در دیتابیس به‌صورت متن‌ساده ذخیره می‌شوند. در فایل <code>.env</code> مقدار <code>ENCODE_PASSWORD</code> را (حداقل ۱۲ نویسه) تنظیم کنید و سپس <code>php bin/chat-crypto.php encrypt-all</code> را برای پیام‌های قبلی اجرا کنید.</div></div><?php endif; ?>
<div class="grid grid-4 mb-2" data-live="a-stats">
    <div class="card stat"><div class="ico"><i class="fa-solid fa-users"></i></div><div><div class="num"><?= e(fa_num($counts['total'])) ?></div><div class="lbl">کل کاربران</div></div></div>
    <div class="card stat"><div class="ico"><i class="fa-solid fa-user-check"></i></div><div><div class="num"><?= e(fa_num($counts['active'])) ?></div><div class="lbl">فعال</div></div></div>
    <div class="card stat"><div class="ico warn"><i class="fa-solid fa-user-clock"></i></div><div><div class="num"><?= e(fa_num($counts['pending'])) ?></div><div class="lbl">در انتظار تأیید</div></div></div>
    <div class="card stat"><div class="ico danger"><i class="fa-solid fa-user-slash"></i></div><div><div class="num"><?= e(fa_num($counts['blocked'] + $counts['deactivated'])) ?></div><div class="lbl">مسدود / غیرفعال</div></div></div>
</div>
<?php // جستجو سمت سرور (همه‌ی صفحه‌ها) + تعداد در هر صفحه؛ با تغییر، صفحه از اول ?>
<div class="page-head"><form method="get" class="flex gap-2 items-center wrap users-tools" id="users-tools">
        <div class="dc-0ee125 search"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('admin_users_search')) ?>" aria-label="جستجوی کاربران"></div>
        <label class="flex items-center gap-1 small muted users-per">نمایش در هر صفحه
            <select class="input" name="per" data-autosubmit aria-label="تعداد کاربر در هر صفحه"><?php foreach ($perOptions as $n): ?><option value="<?= (int) $n ?>" <?= $n === $per ? 'selected' : '' ?>><?= e(fa_num($n)) ?></option><?php endforeach; ?></select></label>
        <span class="small faint"><?= e(fa_num($total)) ?> کاربر<?= $q !== '' ? ' پیدا شد' : '' ?></span>
    </form>
    <?php if ($can('add_user')): ?><button class="btn btn-primary" id="btn-new-user"><i class="fa-solid fa-user-plus"></i> کاربر جدید</button><?php endif; ?></div>
<?php if ($can('impersonate')): ?>
<div class="card mb-2 imp-open" id="imp-open" hidden>
    <div class="card-head"><h3><i class="fa-solid fa-user-secret"></i> پنجره‌های باز ورود به پنل کاربر <span class="badge" id="imp-open-n"></span></h3>
        <button type="button" class="btn btn-sm btn-danger" data-imp-end-all><i class="fa-solid fa-xmark"></i> بستن همه</button></div>
    <div class="list" id="imp-open-list"></div>
    <p class="xs faint mt-1">هر پنجره بعد از <?= e(fa_num(intdiv(\App\Core\Impersonation::idleSeconds(), 60))) ?> دقیقه بی‌فعالیتی یا <?= e(fa_num(intdiv(\App\Core\Impersonation::maxSeconds(), 3600))) ?> ساعت خودکار بسته می‌شود؛ با خروج از پنل مدیر هم همه بسته می‌شوند.</p>
</div>
<?php endif; ?>
<div class="table-wrap" data-live="a-users"><table class="table table-cards" id="users-table"><thead><tr><th>کاربر</th><th>موبایل</th><th>نقش</th><th>وضعیت</th><th>آخرین ورود</th><th></th></tr></thead><tbody>
<?php foreach ($users as $u):
    $isAdmin = $u['role'] === 'admin'; $self = (int) $u['id'] === $me; $isSuperRow = (int) $u['id'] === $superId;
    $protected = $self || $isSuperRow || ($isAdmin && !$isSuper);
    $ar = \App\Core\Auth::adminRole($u);       // super | internal | support | null
    $editData = ['first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'mobile' => $u['mobile'], 'email' => $u['email'], 'role' => $ar ?? 'user']; ?>
    <tr data-search="<?= e(mb_strtolower(full_name($u) . ' ' . $u['mobile'])) ?>">
        <td><div class="flex items-center gap-1"><?php $this->partial('avatar', ['name' => full_name($u), 'src' => avatar_url($u['avatar']), 'size' => 'sm']); ?><div><div class="bold"><?= e(full_name($u)) ?></div><div class="xs muted"><?= e($u['email'] ?? '') ?></div></div></div></td>
        <td class="ltr" data-label="موبایل"><?= e(fa_num((string) $u['mobile'])) ?></td>
        <td data-label="نقش"><?php if ($ar !== null): ?><span class="badge <?= e($adminRoles[$ar]['tone']) ?>"><?= e($adminRoles[$ar]['label']) ?></span><?php else: ?><span class="badge">کاربر</span><?php endif; ?></td>
        <td data-label="وضعیت"><span class="badge <?= e($stLbl[$u['status']][1]) ?>"><?= e($stLbl[$u['status']][0]) ?></span></td>
        <td class="small muted" data-label="آخرین ورود"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : '—' ?></td>
        <td class="nowrap tc-acts"><div class="flex gap-1">
            <?php if ($can('toggle_status') && !$protected): ?>
                <div class="st-btns" role="group" aria-label="تغییر وضعیت">
                    <?php foreach ([['active', 'fa-check', 'ok', 'تأیید / فعال کردن', 'فعال'], ['deactivated', 'fa-xmark', 'off', 'غیرفعال کردن (رد)', 'غیرفعال'], ['blocked', 'fa-ban', 'block', 'مسدود کردن', 'مسدود']] as [$k, $ic, $tone, $tt, $short]): $cur = $u['status'] === $k; ?>
                    <button type="button" class="btn btn-xs st-btn st-<?= e($tone) ?><?= $cur ? ' on' : '' ?>" <?= $cur ? 'disabled aria-pressed="true"' : 'aria-pressed="false" data-post="' . e(url('/admin/users/' . pid('users', $u) . '/status')) . '" data-data=\'' . e(json_encode(['status' => $k])) . '\' data-after="reload"' ?> title="<?= e($cur ? 'وضعیت فعلی: ' . $stLbl[$k][0] : $tt) ?>" aria-label="<?= e($tt) ?>"><i class="fa-solid <?= e($ic) ?>" aria-hidden="true"></i> <span><?= e($short) ?></span></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($can('impersonate') && !$isAdmin && $u['status'] === 'active'): ?><button type="button" class="btn btn-xs btn-icon" data-impersonate="<?= e(pid('users', $u)) ?>" title="ورود به پنل کاربر (در تب جدید)" aria-label="ورود به پنل «<?= e(full_name($u)) ?>» در تب جدید"><i class="fa-solid fa-user-secret"></i></button><?php endif; ?>
            <?php // منوی ⋮: جزئیات، ویرایش، حذف ?>
            <div class="u-menu"><button type="button" class="btn btn-xs btn-icon" data-umenu aria-haspopup="menu" aria-expanded="false" title="گزینه‌های بیشتر" aria-label="گزینه‌های بیشتر «<?= e(full_name($u)) ?>»"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                <div class="dropdown-menu u-menu-list" role="menu">
                    <button type="button" role="menuitem" data-user-details="<?= e(pid('users', $u)) ?>"><i class="fa-solid fa-circle-info"></i> جزئیات</button>
                    <?php if ($can('edit_user') && (!$isAdmin || $isSuper || $self)): ?><button type="button" role="menuitem" data-edit-user='<?= e(json_html($editData)) ?>' data-uid="<?= e(pid('users', $u)) ?>"><i class="fa-solid fa-pen"></i> ویرایش</button><?php endif; ?>
                    <?php if ($can('delete_user') && !$protected): ?><button type="button" role="menuitem" class="text-danger" data-post="<?= e(url('/admin/users/' . pid('users', $u) . '/delete')) ?>" data-confirm="کاربر «<?= e(full_name($u)) ?>» و تمام داده‌هایش برای همیشه حذف شود؟"><i class="fa-solid fa-trash"></i> حذف</button><?php endif; ?>
                </div></div>
        </div></td></tr>
<?php endforeach; ?><?php if (!$users): ?><tr><td colspan="6" class="center muted"><?= $q !== '' ? 'کاربری با این جستجو پیدا نشد.' : 'کاربری وجود ندارد.' ?></td></tr><?php endif; ?></tbody></table>
<?php $this->partial('pagination', ['pg' => $pg, 'pages' => $pages, 'query' => array_filter(['q' => $q, 'per' => $per !== $perOptions[0] ? $per : null])]); ?></div>

<?php // جزئیات کاربر: پروژه‌ها (ورود به پنل ← تسک‌های پروژه)، تسک‌ها (پنجره‌ی فهرست ← ورود به پنل ← لینک تسک)، تاریخ ایجاد، آخرین بازدید ?>
<?php $this->partial('admin_user_modals'); ?>
<div class="modal" id="m-user"><div class="modal-box"><form method="post" action="<?= e(url('/admin/users')) ?>" data-ajax id="f-user"><?= csrf_field() ?>
    <div class="modal-head"><h3><i class="fa-solid fa-user-gear"></i> <span id="user-modal-title">کاربر جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body">
        <div class="row"><div class="field"><label>نام *</label><input class="input" name="first_name" required maxlength="100"></div><div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" maxlength="100"></div></div>
        <div class="row"><div class="field"><label>موبایل *</label><input class="input ltr" name="mobile" data-mobile required></div><div class="field"><label>ایمیل</label><input class="input ltr" type="email" name="email"></div></div>
        <div class="field"><label>رمز عبور <span class="faint" id="pw-hint">(الزامی)</span></label><input class="input ltr" type="password" name="password" autocomplete="new-password" minlength="8"></div>
        <div class="field hidden" id="reauth-field"><label><i class="fa-solid fa-lock"></i> رمز عبور فعلی خودتان <span class="faint">(فقط برای تغییر رمز یا موبایل کاربر)</span></label><input class="input ltr" type="password" name="admin_password" autocomplete="current-password"></div>
        <div class="row"><div class="field" id="status-field"><label>وضعیت</label><select class="input" name="status"><option value="active">فعال</option><option value="pending">در انتظار</option><option value="deactivated">غیرفعال</option><option value="blocked">مسدود</option></select></div>
            <?php if ($isSuper): ?><div class="field"><label>نقش</label><select class="input" name="role" id="role-select"><option value="user">کاربر</option><?php foreach ($adminRoles as $k => $r): ?><option value="<?= e($k) ?>"><?= e($r['label']) ?></option><?php endforeach; ?></select></div><?php endif; ?></div>
        <?php if ($isSuper): ?><p class="hint hidden" id="perm-field"><i class="fa-solid fa-circle-info"></i> دسترسی هر نقش مدیر (و دسترسی اختصاصی هر مدیر) را مدیر کل در «تنظیمات ← دسترسی» تعیین می‌کند؛ مدیر کل همیشه همه‌ی دسترسی‌ها را دارد.</p><?php endif; ?>
    </div>
    <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div></form></div></div>
