<?php
/** @var array $colleagues @var array $incoming @var array $outgoing @var array $blocked @var array $addProjects @var array $followers */
$this->scripts[] = 'colleagues';
$this->boot['colleagues'] = ['projects' => $addProjects];
$tab = in_array($_GET['tab'] ?? '', ['requests', 'followers', 'blocked'], true) ? $_GET['tab'] : 'list';
$tabUrl = static fn(string $t) => url('/colleagues') . '?tab=' . $t;
?>
<div class="page-head">
    <div class="tabs" data-live="col-tabs">
        <a class="tab <?= $tab === 'list' ? 'active' : '' ?>" href="<?= e($tabUrl('list')) ?>">همکاران <span class="badge-count"><?= e(fa_num(count($colleagues))) ?></span></a>
        <a class="tab <?= $tab === 'requests' ? 'active' : '' ?>" href="<?= e($tabUrl('requests')) ?>">درخواست‌ها <span class="badge-count <?= $incoming ? '' : 'zero' ?>"><?= e(fa_num(count($incoming))) ?></span></a>
        <a class="tab <?= $tab === 'followers' ? 'active' : '' ?>" href="<?= e($tabUrl('followers')) ?>" title="کاربرانی که شما را به لیست همکاران خود اضافه کرده‌اند">کاربرانی که مرا همکار کرده‌اند <span class="badge-count"><?= e(fa_num(count($followers))) ?></span></a>
        <a class="tab <?= $tab === 'blocked' ? 'active' : '' ?>" href="<?= e($tabUrl('blocked')) ?>">مسدودها <span class="badge-count"><?= e(fa_num(count($blocked))) ?></span></a>
    </div>
    <button class="btn btn-primary" data-modal="m-add-col"><i class="fa-solid fa-user-plus"></i> افزودن همکار</button>
</div>

<div data-live="col-body">
<?php if ($tab === 'list'): ?>
    <?php if (!$colleagues): ?><div class="card empty"><i class="fa-solid fa-users"></i><p>هنوز همکاری اضافه نکرده‌اید. با شماره موبایل جستجو کنید.</p></div>
    <?php else: ?><div class="grid grid-auto">
        <?php foreach ($colleagues as $c): $nm = full_name($c); ?>
        <div class="card hover" id="col-<?= e(pid('users', $c['user_id'])) ?>">
            <div class="flex items-center gap-2 mb-1">
                <?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($c['avatar'])]); ?>
                <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted ltr"><?= e($c['mobile']) ?></div></div>
                <?php if ($c['is_blocked_by_me']): ?><span class="badge red">مسدود</span><?php elseif ($c['has_blocked_me']): ?><span class="badge yellow">شما را مسدود کرده</span><?php endif; ?>
            </div>
            <div class="flex gap-1 wrap">
                <a class="btn btn-sm" href="<?= e(url('/chat') . '?user=' . pid('users', $c['user_id'])) ?>" title="گفتگو" aria-label="گفتگو با <?= e(full_name($c)) ?>"><i class="fa-regular fa-comment"></i></a>
                <?php if (!$c['is_blocked_by_me'] && !$c['has_blocked_me']): ?><button class="btn btn-sm" data-add-to-project="<?= e(pid('users', $c['user_id'])) ?>" data-name="<?= e($nm) ?>" title="عضو کردن این همکار در پروژه‌های شما"><i class="fa-solid fa-diagram-project"></i> افزودن به پروژه</button><?php endif; ?>
                <?php if (!$c['is_blocked_by_me']): ?><button class="btn btn-sm btn-icon" data-post="<?= e(url('/colleagues/' . pid('users', $c['user_id']) . '/block')) ?>" data-confirm="«<?= e($nm) ?>» مسدود شود؟" title="مسدود"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
                <button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/colleagues/' . pid('users', $c['user_id']) . '/remove')) ?>" data-confirm="«<?= e($nm) ?>» از لیست همکاران حذف شود؟" title="حذف"><i class="fa-solid fa-user-minus"></i></button>
            </div>
        </div>
        <?php endforeach; ?></div><?php endif; ?>

<?php elseif ($tab === 'requests'): ?>
    <h3 class="mb-1">دریافتی</h3>
    <?php if (!$incoming): ?><div class="card empty"><i class="fa-regular fa-envelope-open"></i><p>درخواست جدیدی ندارید.</p></div><?php else: ?><div class="list mb-3">
        <?php foreach ($incoming as $r): $nm = full_name($r); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($r['avatar'])]); ?>
            <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted"><span class="ltr"><?= e($r['mobile']) ?></span> • <?= e(time_ago($r['created_at'])) ?></div></div>
            <div class="item-actions"><button class="btn btn-sm btn-primary" data-post="<?= e(url('/colleagues/requests/' . pid('colleague_requests', $r) . '/accept')) ?>">پذیرفتن</button><button class="btn btn-sm btn-danger" data-post="<?= e(url('/colleagues/requests/' . pid('colleague_requests', $r) . '/reject')) ?>">رد</button></div></div><?php endforeach; ?></div><?php endif; ?>
    <h3 class="mb-1">ارسالی (در انتظار تأیید)</h3>
    <?php if (!$outgoing): ?><p class="muted">درخواست ارسالی در انتظاری ندارید.</p><?php else: ?><div class="list">
        <?php foreach ($outgoing as $r): $nm = full_name($r); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($r['avatar'])]); ?><div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted"><?= e(time_ago($r['created_at'])) ?></div></div><span class="badge yellow">در انتظار</span></div><?php endforeach; ?></div><?php endif; ?>

<?php elseif ($tab === 'followers'): ?>
    <p class="muted small mb-2">این افراد شما را به لیست همکاران خود اضافه کرده‌اند؛ یعنی می‌توانند به شما تسک واگذار کنند، شما را به پروژه‌هایشان اضافه کنند و با شما گفتگو کنند. اگر نمی‌خواهید، از لیستشان خارج شوید یا مسدودشان کنید.</p>
    <?php if (!$followers): ?><div class="card empty"><i class="fa-solid fa-user-group"></i><p>هنوز کسی شما را به لیست همکارانش اضافه نکرده است.</p></div>
    <?php else: ?><div class="grid grid-auto">
        <?php foreach ($followers as $f): $nm = full_name($f); $fid = pid('users', $f['user_id']); ?>
        <div class="card hover" id="fol-<?= $fid ?>">
            <div class="flex items-center gap-2 mb-1">
                <?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($f['avatar'])]); ?>
                <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted ltr"><?= e($f['mobile']) ?></div></div>
            </div>
            <div class="flex gap-1 wrap mb-1">
                <?php if ($f['mutual']): ?><span class="badge green"><i class="fa-solid fa-arrow-right-arrow-left"></i> همکار دوطرفه</span><?php else: ?><span class="badge">فقط او شما را همکار کرده</span><?php endif; ?>
                <?php if ($f['is_blocked_by_me']): ?><span class="badge red">مسدود</span><?php endif; ?>
                <span class="badge" title="زمان افزودن"><?= e(time_ago($f['added_at'])) ?></span>
            </div>
            <div class="flex gap-1 wrap">
                <?php if (!$f['is_blocked_by_me']): ?><a class="btn btn-sm" href="<?= e(url('/chat') . '?user=' . $fid) ?>" title="گفتگو" aria-label="گفتگو با <?= e(full_name($f)) ?>"><i class="fa-regular fa-comment"></i></a><?php endif; ?>
                <?php if (!$f['mutual'] && !$f['is_blocked_by_me']): ?>
                    <?php if ($f['my_pending']): ?><span class="badge yellow">درخواست شما در انتظار</span>
                    <?php else: ?><button class="btn btn-sm btn-primary" data-post="<?= e(url('/colleagues')) ?>" data-data='<?= e(json_encode(['colleague_user_id' => $fid])) ?>' data-after="reload" title="او را هم به لیست همکاران خودتان اضافه کنید"><i class="fa-solid fa-user-plus"></i> افزودن به همکاران من</button><?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-sm" data-post="<?= e(url('/colleagues/followers/' . $fid . '/remove')) ?>" data-confirm="از لیست همکاران «<?= e($nm) ?>» خارج می‌شوید؟ دیگر نمی‌تواند به شما تسک واگذار کند یا شما را به پروژه‌اش اضافه کند (عضویت‌ها و تسک‌های فعلی تغییری نمی‌کنند)." data-after="reload" title="خارج شدن از لیست همکاران او"><i class="fa-solid fa-right-from-bracket"></i> خروج از لیستش</button>
                <?php if ($f['is_blocked_by_me']): ?><button class="btn btn-sm" data-post="<?= e(url('/colleagues/' . $fid . '/unblock')) ?>" data-after="reload">رفع مسدودی</button>
                <?php else: ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/colleagues/' . $fid . '/block')) ?>" data-confirm="«<?= e($nm) ?>» مسدود شود؟" data-after="reload" title="مسدود"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?></div><?php endif; ?>

<?php else: ?>
    <?php if (!$blocked): ?><div class="card empty"><i class="fa-solid fa-ban"></i><p>کاربر مسدودی ندارید.</p></div><?php else: ?><div class="list">
        <?php foreach ($blocked as $b): $nm = full_name($b); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($b['avatar'])]); ?><div class="grow"><div class="bold"><?= e($nm) ?></div><?php if (!empty($b['hidden'])): ?><div class="xs faint">اطلاعات کاربری که همکار شما نیست نمایش داده نمی‌شود.</div><?php else: ?><div class="xs muted ltr"><?= e($b['mobile']) ?></div><?php endif; ?></div>
            <?php if (!empty($b['hidden'])): ?><button class="btn btn-sm" data-post="<?= e(url('/colleagues/unblock-key')) ?>" data-data='<?= e(json_encode(['key' => $b['block_key']])) ?>' data-after="reload">رفع مسدودی</button>
            <?php else: ?><button class="btn btn-sm" data-post="<?= e(url('/colleagues/' . pid('users', $b['user_id']) . '/unblock')) ?>" data-after="reload">رفع مسدودی</button><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
<?php endif; ?>
</div>

<div class="modal" id="m-add-col"><div class="modal-box sm"><div class="modal-head"><h3><i class="fa-solid fa-user-plus"></i> افزودن همکار</h3><button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><form id="f-search" class="flex gap-1"><input class="input ltr" name="mobile" data-mobile inputmode="numeric" placeholder="<?= e(ph('colleague_mobile')) ?>" required><button class="btn btn-primary">جستجو</button></form><div id="search-result" class="mt-2"></div></div></div></div>

<div class="modal" id="m-add-proj"><div class="modal-box"><div class="modal-head"><h3><i class="fa-solid fa-diagram-project"></i> افزودن <span id="ap-name"></span> به پروژه</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><p class="xs muted mb-1">فقط پروژه‌هایی نمایش داده می‌شوند که در آن‌ها دسترسی «افزودن کاربر» دارید. عضو جدید نقش «عضو» می‌گیرد؛ اگر همکارتان تأیید دستی عضویت را فعال کرده باشد، برایش دعوت ارسال می‌شود.</p>
        <div id="ap-list" class="list"></div></div></div></div>
