<?php
/** @var array $colleagues @var array $incoming @var array $outgoing @var array $blocked */
$this->scripts[] = 'colleagues';
$tab = in_array($_GET['tab'] ?? '', ['requests', 'blocked'], true) ? $_GET['tab'] : 'list';
$tabUrl = static fn(string $t) => url('/colleagues') . '?tab=' . $t;
?>
<div class="page-head">
    <div class="tabs" data-live="col-tabs">
        <a class="tab <?= $tab === 'list' ? 'active' : '' ?>" href="<?= e($tabUrl('list')) ?>">همکاران <span class="badge-count"><?= e(fa_num(count($colleagues))) ?></span></a>
        <a class="tab <?= $tab === 'requests' ? 'active' : '' ?>" href="<?= e($tabUrl('requests')) ?>">درخواست‌ها <span class="badge-count <?= $incoming ? '' : 'zero' ?>"><?= e(fa_num(count($incoming))) ?></span></a>
        <a class="tab <?= $tab === 'blocked' ? 'active' : '' ?>" href="<?= e($tabUrl('blocked')) ?>">مسدودها <span class="badge-count"><?= e(fa_num(count($blocked))) ?></span></a>
    </div>
    <button class="btn btn-primary" data-modal="m-add-col"><i class="fa-solid fa-user-plus"></i> افزودن همکار</button>
</div>

<div data-live="col-body">
<?php if ($tab === 'list'): ?>
    <?php if (!$colleagues): ?><div class="card empty"><i class="fa-solid fa-users"></i><p>هنوز همکاری اضافه نکرده‌اید. با شماره موبایل جستجو کنید.</p></div>
    <?php else: ?><div class="grid grid-auto">
        <?php foreach ($colleagues as $c): $nm = full_name($c); ?>
        <div class="card hover" id="col-<?= (int) $c['user_id'] ?>">
            <div class="flex items-center gap-2 mb-1">
                <?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($c['avatar'])]); ?>
                <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted ltr"><?= e($c['mobile']) ?></div></div>
                <?php if ($c['is_blocked_by_me']): ?><span class="badge red">مسدود</span><?php elseif ($c['has_blocked_me']): ?><span class="badge yellow">شما را مسدود کرده</span><?php endif; ?>
            </div>
            <div class="flex gap-1 wrap">
                <a class="btn btn-sm" href="<?= e(url('/chat') . '?user=' . (int) $c['user_id']) ?>"><i class="fa-regular fa-comment"></i></a>
                <?php if (!$c['is_blocked_by_me']): ?><button class="btn btn-sm btn-icon" data-post="<?= e(url('/colleagues/' . $c['user_id'] . '/block')) ?>" data-confirm="«<?= e($nm) ?>» مسدود شود؟" title="مسدود"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
                <button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/colleagues/' . $c['user_id'] . '/remove')) ?>" data-confirm="«<?= e($nm) ?>» از لیست همکاران حذف شود؟" title="حذف"><i class="fa-solid fa-user-minus"></i></button>
            </div>
        </div>
        <?php endforeach; ?></div><?php endif; ?>

<?php elseif ($tab === 'requests'): ?>
    <h3 class="mb-1">دریافتی</h3>
    <?php if (!$incoming): ?><div class="card empty"><i class="fa-regular fa-envelope-open"></i><p>درخواست جدیدی ندارید.</p></div><?php else: ?><div class="list mb-3">
        <?php foreach ($incoming as $r): $nm = full_name($r); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($r['avatar'])]); ?>
            <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted"><span class="ltr"><?= e($r['mobile']) ?></span> • <?= e(time_ago($r['created_at'])) ?></div></div>
            <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/colleagues/requests/' . $r['id'] . '/accept')) ?>">پذیرفتن</button><button class="btn btn-sm btn-danger" data-post="<?= e(url('/colleagues/requests/' . $r['id'] . '/reject')) ?>">رد</button></div></div><?php endforeach; ?></div><?php endif; ?>
    <h3 class="mb-1">ارسالی (در انتظار تأیید)</h3>
    <?php if (!$outgoing): ?><p class="muted">درخواست ارسالی در انتظاری ندارید.</p><?php else: ?><div class="list">
        <?php foreach ($outgoing as $r): $nm = full_name($r); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($r['avatar'])]); ?><div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted"><?= e(time_ago($r['created_at'])) ?></div></div><span class="badge yellow">در انتظار</span></div><?php endforeach; ?></div><?php endif; ?>

<?php else: ?>
    <?php if (!$blocked): ?><div class="card empty"><i class="fa-solid fa-ban"></i><p>کاربر مسدودی ندارید.</p></div><?php else: ?><div class="list">
        <?php foreach ($blocked as $b): $nm = full_name($b); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($b['avatar'])]); ?><div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted ltr"><?= e($b['mobile']) ?></div></div>
            <button class="btn btn-sm" data-post="<?= e(url('/colleagues/' . $b['user_id'] . '/unblock')) ?>">رفع مسدودی</button></div><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>
</div>

<div class="modal" id="m-add-col"><div class="modal-box sm"><div class="modal-head"><h3><i class="fa-solid fa-user-plus"></i> افزودن همکار</h3><button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><form id="f-search" class="flex gap-1"><input class="input ltr" name="mobile" data-mobile inputmode="numeric" placeholder="<?= e(ph('colleague_mobile')) ?>" required><button class="btn btn-primary">جستجو</button></form><div id="search-result" class="mt-2"></div></div></div></div>
