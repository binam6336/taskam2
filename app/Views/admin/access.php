<?php
/**
 * تنظیمات ← دسترسی (v7.4، SEC-001): فقط مدیر کل.
 * بخش ۱: جدول دسترسی نقش‌ها (مدیر کل همیشه همه؛ مدیر داخلی و پشتیبان قابل تنظیم). بخش ۲: هر مدیر = نقش + «طبق نقش» یا «اختصاصی».
 * @var array $admins @var array $roles @var array $defs @var array $groups @var array $defaults @var array $adminRoles @var int $superId
 */
$this->scripts[] = 'admin_access';
$editable = array_keys($defaults);          // internal, support
$byGroup = [];
foreach ($defs as $k => [$g, $label, $desc]) {
    $byGroup[$g][$k] = [$label, $desc];
}
?>
<div class="page-head"><h2><i class="fa-solid fa-user-lock" aria-hidden="true"></i> دسترسی</h2></div>
<p class="muted mb-2">چه کسی در پنل مدیر چه کاری می‌تواند بکند. <b>مدیر کل</b> همیشه همه‌ی دسترسی‌ها را دارد و فقط او این صفحه را می‌بیند. هر مدیر یا طبق نقشش دسترسی می‌گیرد یا دسترسی اختصاصی خودش را دارد. همه‌ی تغییرها در لاگ ثبت می‌شود.</p>

<form class="card mb-3 ac-card" method="post" action="<?= e(url('/admin/access/roles')) ?>" data-ajax data-after="reload" id="f-access-roles"><?= csrf_field() ?>
    <div class="card-head"><h3><i class="fa-solid fa-table-cells" aria-hidden="true"></i> دسترسی نقش‌ها</h3>
        <div class="flex gap-1"><button type="button" class="btn btn-sm" data-post="<?= e(url('/admin/access/roles/reset')) ?>" data-confirm="دسترسی نقش‌های «مدیر داخلی» و «پشتیبان» به پیش‌فرض برگردد؟" data-after="reload"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> پیش‌فرض</button>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> ذخیره</button></div></div>
    <div class="table-wrap ac-wrap"><table class="table ac-table">
        <thead><tr><th scope="col">دسترسی</th><?php foreach ($adminRoles as $rk => $r): ?><th scope="col" class="center"><span class="badge <?= e($r['tone']) ?>"><?= e($r['label']) ?></span></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($groups as $g => [$gi, $gl]): if (empty($byGroup[$g])) continue; ?>
            <tr class="ac-grp"><th colspan="<?= e((string) (count($adminRoles) + 1)) ?>" scope="colgroup"><i class="fa-solid <?= e($gi) ?>" aria-hidden="true"></i> <?= e($gl) ?></th></tr>
            <?php foreach ($byGroup[$g] as $k => [$label, $desc]): ?>
            <tr>
                <th scope="row" class="ac-perm"><b><?= e($label) ?></b><small><?= e($desc) ?></small></th>
                <?php foreach ($adminRoles as $rk => $r): $on = in_array($k, $roles[$rk] ?? [], true); ?>
                <td class="center"><?php if ($rk === 'super'): ?><i class="fa-solid fa-check ac-fixed" title="مدیر کل همیشه این دسترسی را دارد" aria-label="دارد"></i>
                <?php else: ?><label class="ac-chk"><input type="checkbox" name="perm_<?= e($rk) ?>[]" value="<?= e($k) ?>" <?= $on ? 'checked' : '' ?> aria-label="<?= e($label . ' — ' . $r['label']) ?>"><?php if (in_array($k, $defaults[$rk], true)): ?><span class="ac-def" title="پیش‌فرض این نقش">•</span><?php endif; ?></label><?php endif; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <tr class="ac-grp"><th colspan="<?= e((string) (count($adminRoles) + 1)) ?>" scope="colgroup"><i class="fa-solid fa-user-lock" aria-hidden="true"></i> فقط مدیر کل</th></tr>
        <tr><th scope="row" class="ac-perm"><b>مدیریت دسترسی‌ها</b><small>همین صفحه: دسترسی نقش‌ها و هر مدیر. به نقش دیگری داده نمی‌شود.</small></th>
            <?php foreach ($adminRoles as $rk => $r): ?><td class="center"><?= $rk === 'super' ? '<i class="fa-solid fa-check ac-fixed" aria-label="دارد"></i>' : '<i class="fa-solid fa-xmark faint" aria-label="ندارد"></i>' ?></td><?php endforeach; ?></tr>
        </tbody>
    </table></div>
    <p class="xs faint mt-1"><span class="ac-def">•</span> = جزو دسترسی پیش‌فرض آن نقش.</p>
</form>

<div class="card ac-card">
    <div class="card-head"><h3><i class="fa-solid fa-users-gear" aria-hidden="true"></i> مدیران و دسترسی هر نفر</h3><span class="xs faint"><?= e(fa_num(count($admins))) ?> مدیر</span></div>
    <div class="ac-admins">
    <?php foreach ($admins as $a): $nm = full_name($a); $isMain = (int) $a['id'] === $superId; $pidA = pid('users', $a); ?>
        <details class="ac-admin"<?= $isMain ? '' : '' ?>>
            <summary>
                <?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($a['avatar']), 'size' => 'sm']); ?>
                <span class="grow min0"><b class="truncate"><?= e($nm) ?></b><small class="ltr"><?= e(fa_num((string) $a['mobile'])) ?></small></span>
                <span class="badge <?= e($adminRoles[$a['ar']]['tone'] ?? '') ?>"><?= e($adminRoles[$a['ar']]['label'] ?? '') ?></span>
                <?php if ($a['custom'] !== null): ?><span class="badge yellow">اختصاصی</span><?php endif; ?>
                <?php if ($a['status'] !== 'active'): ?><span class="badge red">غیرفعال</span><?php endif; ?>
                <span class="chip ac-n" title="تعداد دسترسی‌ها"><?= e(fa_num(count($a['perms']))) ?> از <?= e(fa_num(count($defs))) ?></span>
                <i class="fa-solid fa-chevron-down ac-chev" aria-hidden="true"></i>
            </summary>
            <?php if ($isMain): ?>
                <p class="muted small ac-body">حساب مدیر کل اصلی (اولین مدیر) همیشه مدیر کل است و همه‌ی دسترسی‌ها را دارد؛ نقش و دسترسی‌اش تغییر نمی‌کند.</p>
            <?php else: ?>
            <form class="ac-body" method="post" action="<?= e(url('/admin/access/users/' . $pidA)) ?>" data-ajax data-after="reload"><?= csrf_field() ?>
                <div class="row">
                    <div class="field"><label for="ac-role-<?= e($pidA) ?>">نقش</label>
                        <select class="input" id="ac-role-<?= e($pidA) ?>" name="admin_role" data-ac-role>
                            <?php foreach ($adminRoles as $rk => $r): ?><option value="<?= e($rk) ?>" <?= $a['ar'] === $rk ? 'selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach; ?>
                            <option value="user">کاربر عادی (برداشتن دسترسی مدیریت)</option>
                        </select></div>
                    <div class="field"><label for="ac-mode-<?= e($pidA) ?>">دسترسی‌ها</label>
                        <select class="input" id="ac-mode-<?= e($pidA) ?>" name="mode" data-ac-mode>
                            <option value="role" <?= $a['custom'] === null ? 'selected' : '' ?>>طبق نقش</option>
                            <option value="custom" <?= $a['custom'] !== null ? 'selected' : '' ?>>اختصاصی این مدیر</option>
                        </select></div>
                </div>
                <div class="ac-perms" data-ac-perms data-role-perms="<?= e(json_html($roles)) ?>">
                <?php foreach ($groups as $g => [$gi, $gl]): if (empty($byGroup[$g])) continue; ?>
                    <fieldset class="ac-fs"><legend><i class="fa-solid <?= e($gi) ?>" aria-hidden="true"></i> <?= e($gl) ?></legend>
                    <?php foreach ($byGroup[$g] as $k => [$label, $desc]): ?>
                        <label class="check ac-p" title="<?= e($desc) ?>"><input type="checkbox" name="perms[]" value="<?= e($k) ?>" <?= in_array($k, $a['perms'], true) ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                    <?php endforeach; ?></fieldset>
                <?php endforeach; ?>
                </div>
                <p class="xs faint" data-ac-hint></p>
                <div class="flex gap-1 mt-1"><button class="btn btn-primary btn-sm"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> ذخیره‌ی «<?= e($nm) ?>»</button></div>
            </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
    </div>
    <p class="xs faint mt-1">برای مدیر کردن یک کاربر تازه، از «مدیریت کاربران» او را ویرایش کنید و نقش مدیر بدهید (دسترسی «مدیریت مدیران» لازم است)؛ بعد این‌جا دسترسی‌اش را تنظیم کنید.</p>
</div>
