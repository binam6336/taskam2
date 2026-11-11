<?php
/**
 * @var string $tab @var ?array $list (ColleagueService::paged، فقط تب list) @var int $colTotal
 * @var array $incoming @var array $outgoing @var array $blocked @var array $addProjects @var array $followers
 */
$this->scripts[] = 'colleagues';
$this->boot['colleagues'] = ['projects' => $addProjects, 'per' => \App\Services\ColleagueService::PER_PAGE];
$tabUrl = static fn(string $t) => url('/colleagues') . '?tab=' . $t;
?>
<div class="page-head">
    <div class="tabs" data-live="col-tabs">
        <a class="tab <?= $tab === 'list' ? 'active' : '' ?>" href="<?= e($tabUrl('list')) ?>">همکاران <span class="badge-count"><?= e(fa_num($colTotal)) ?></span></a>
        <a class="tab <?= $tab === 'requests' ? 'active' : '' ?>" href="<?= e($tabUrl('requests')) ?>">درخواست‌ها <span class="badge-count <?= $incoming ? '' : 'zero' ?>"><?= e(fa_num(count($incoming))) ?></span></a>
        <a class="tab <?= $tab === 'followers' ? 'active' : '' ?>" href="<?= e($tabUrl('followers')) ?>" title="کاربرانی که شما را به لیست همکاران خود اضافه کرده‌اند">کاربرانی که مرا همکار کرده‌اند <span class="badge-count"><?= e(fa_num(count($followers))) ?></span></a>
        <a class="tab <?= $tab === 'blocked' ? 'active' : '' ?>" href="<?= e($tabUrl('blocked')) ?>">مسدودها <span class="badge-count"><?= e(fa_num(count($blocked))) ?></span></a>
    </div>
    <button class="btn btn-primary" data-modal="m-add-col"><i class="fa-solid fa-user-plus"></i> افزودن همکار</button>
</div>

<?php if ($tab === 'list'):
    $per = $list['per'];
    // پارامترهایی که در لینک‌های صفحه‌بندی می‌مانند (پیش‌فرض‌ها حذف)
    $keep = array_filter(['tab' => 'list', 'q' => $list['q'], 'sort' => $list['sort'] !== 'recent' ? $list['sort'] : null], static fn($v) => $v !== null && $v !== '');
    $countText = static fn(array $l): string => $l['q'] !== '' ? fa_num($l['total']) . ' نتیجه' : fa_num($l['total']) . ' همکار';
?>
<div class="col-wrap" data-col-wrap>
<?php if ($colTotal > 0): ?>
<form class="col-toolbar" method="get" action="<?= e(url('/colleagues')) ?>" data-col-toolbar>
    <input type="hidden" name="tab" value="list">
    <?php if ($per !== \App\Services\ColleagueService::PER_PAGE[0]): ?><input type="hidden" name="per_page" value="<?= (int) $per ?>" data-col-per-hidden><?php endif; ?>
    <div class="col-search" role="search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <label for="col-q" class="sr-only">جستجو در همکاران</label>
        <input id="col-q" name="q" type="text" inputmode="search" enterkeyhint="search" data-col-search autocomplete="off" maxlength="100" value="<?= e($list['q']) ?>"
               placeholder="<?= e(ph('colleague_search')) ?>" aria-describedby="col-count" aria-keyshortcuts="/">
        <kbd aria-hidden="true">/</kbd>
    </div>
    <label for="col-sort" class="sr-only">مرتب‌سازی</label>
    <select id="col-sort" name="sort" class="col-select" data-col-sort>
        <option value="recent" <?= $list['sort'] === 'recent' ? 'selected' : '' ?>>جدیدترین</option>
        <option value="alpha" <?= $list['sort'] === 'alpha' ? 'selected' : '' ?>>الفبایی</option>
    </select>
    <div class="col-seg" role="group" aria-label="نحوه‌ی نمایش">
        <button type="button" data-col-view="list" aria-pressed="true" title="نمایش لیستی"><i class="fa-solid fa-list" aria-hidden="true"></i><span class="sr-only">نمایش لیستی</span></button>
        <button type="button" data-col-view="grid" aria-pressed="false" title="نمایش کارتی"><i class="fa-solid fa-table-cells" aria-hidden="true"></i><span class="sr-only">نمایش کارتی</span></button>
    </div>
    <p class="col-count" id="col-count" data-col-count role="status" aria-live="polite"><?= e($countText($list)) ?></p>
    <noscript><button class="btn btn-sm">جستجو</button></noscript>
</form>
<?php endif; ?>

<div data-live="col-body">
    <div hidden data-col-meta data-page="<?= (int) $list['page'] ?>" data-pages="<?= (int) $list['pages'] ?>" data-total="<?= (int) $list['total'] ?>" data-per="<?= (int) $per ?>" data-count="<?= e($countText($list)) ?>"></div>
    <h2 class="sr-only" id="col-h" tabindex="-1">لیست همکاران<?= $list['pages'] > 1 ? '، صفحه‌ی ' . e(fa_num($list['page'])) . ' از ' . e(fa_num($list['pages'])) : '' ?></h2>
    <?php if ($colTotal === 0): ?>
    <div class="col-empty">
        <i class="fa-solid fa-user-group" aria-hidden="true"></i>
        <p class="m-0">هنوز همکاری اضافه نکرده‌اید. با شماره موبایل جستجو کنید و درخواست همکاری بفرستید.</p>
        <button type="button" class="btn btn-primary" data-modal="m-add-col"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> افزودن همکار</button>
    </div>
    <?php elseif (!$list['rows']): ?>
    <div class="col-empty">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <p class="m-0">همکاری با «<?= e($list['q']) ?>» پیدا نشد.</p>
        <a class="btn btn-sm" href="<?= e(url('/colleagues') . '?' . http_build_query(array_diff_key($keep, ['q' => 1]) + ($per !== \App\Services\ColleagueService::PER_PAGE[0] ? ['per_page' => $per] : []))) ?>" data-col-clear>پاک کردن جستجو</a>
    </div>
    <?php else: ?>
    <ul class="col-list" data-col-list aria-labelledby="col-h">
    <?php foreach ($list['rows'] as $c):
        $cid = pid('users', $c['user_id']);
        $nm = full_name($c);
        $mob = (string) ($c['mobile'] ?? '');
        $src = avatar_url($c['avatar']);
        $blockedAny = $c['is_blocked_by_me'] || $c['has_blocked_me'];
    ?>
        <li class="col-item" id="col-<?= e($cid) ?>">
            <span class="col-av" data-col-hue="<?= e($cid) ?>" aria-hidden="true"><?php if ($src): ?><img loading="lazy" decoding="async" src="<?= e($src) ?>" alt=""><?php else: ?><?= e(initial($nm)) ?><?php endif; ?></span>
            <div class="col-main">
                <h3 class="col-name"><?= e($nm) ?></h3>
                <div class="col-meta">
                    <?php if ($mob !== '' && $c['approved']): ?><a class="col-phone" href="tel:<?= e($mob) ?>" dir="ltr" aria-label="تماس با <?= e($nm) ?>، <?= e(fa_num($mob)) ?>"><?= e(fa_num($mob)) ?></a>
                    <?php elseif ($mob !== ''): ?><span class="col-phone" dir="ltr" title="شماره‌ی کامل فقط بعد از تأیید همکاری نمایش داده می‌شود"><?= e(fa_num($mob)) ?></span><?php endif; ?>
                    <?php if ($c['is_blocked_by_me']): ?><span class="col-tag danger">مسدود</span><?php elseif ($c['has_blocked_me']): ?><span class="col-tag warn">شما را مسدود کرده</span><?php endif; ?>
                </div>
            </div>
            <div class="col-actions" role="group" aria-label="عملیات برای <?= e($nm) ?>">
                <a class="btn btn-sm col-grow" href="<?= e(url('/chat') . '?user=' . $cid) ?>" title="گفتگو"><i class="fa-regular fa-comment" aria-hidden="true"></i><span class="lbl">گفتگو</span><span class="sr-only"> با <?= e($nm) ?></span></a>
                <?php if (!$blockedAny): ?><button type="button" class="btn btn-sm col-grow" data-add-to-project="<?= e($cid) ?>" data-name="<?= e($nm) ?>" title="عضو کردن این همکار در پروژه‌های شما"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><span class="lbl">افزودن به پروژه</span><span class="sr-only"> — <?= e($nm) ?></span></button><?php endif; ?>
                <span class="col-sep" aria-hidden="true"></span>
                <?php if (!$c['is_blocked_by_me']): ?><button type="button" class="btn btn-sm btn-icon col-warn" data-post="<?= e(url('/colleagues/' . $cid . '/block')) ?>" data-confirm="«<?= e($nm) ?>» مسدود شود؟" title="مسدود کردن" aria-label="مسدود کردن <?= e($nm) ?>"><i class="fa-solid fa-ban" aria-hidden="true"></i></button><?php endif; ?>
                <button type="button" class="btn btn-sm btn-icon col-danger" data-post="<?= e(url('/colleagues/' . $cid . '/remove')) ?>" data-confirm="«<?= e($nm) ?>» از لیست همکاران حذف شود؟" title="حذف از همکاران" aria-label="حذف <?= e($nm) ?> از همکاران"><i class="fa-solid fa-user-minus" aria-hidden="true"></i></button>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($colTotal > 0 && $list['total'] > 0) {
        $this->partial('pager', ['page' => $list['page'], 'pages' => $list['pages'], 'total' => $list['total'], 'per' => $per, 'perOptions' => \App\Services\ColleagueService::PER_PAGE,
            'query' => $keep, 'label' => 'صفحه‌بندی همکاران', 'noun' => 'همکار', 'id' => 'col-pg']);
    } ?>
</div>
</div>

<?php else: ?>
<div data-live="col-body">
<?php if ($tab === 'requests'): ?>
    <h3 class="mb-1">دریافتی</h3>
    <?php if (!$incoming): ?><div class="card empty"><i class="fa-regular fa-envelope-open"></i><p>درخواست جدیدی ندارید.</p></div><?php else: ?><div class="list mb-3">
        <?php foreach ($incoming as $r): $nm = full_name($r); ?><div class="item"><?php $this->partial('avatar', ['name' => $nm, 'src' => avatar_url($r['avatar'])]); ?>
            <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted"><span class="ltr"><?= e(fa_num((string) $r['mobile'])) ?></span> • <?= e(time_ago($r['created_at'])) ?></div></div>
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
                <div class="grow"><div class="bold"><?= e($nm) ?></div><div class="xs muted ltr"><?= e(fa_num((string) $f['mobile'])) ?></div></div>
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
                <?php else: ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/colleagues/' . $fid . '/block')) ?>" data-confirm="«<?= e($nm) ?>» مسدود شود؟" data-after="reload" title="مسدود" aria-label="مسدود"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
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
<?php endif; ?>

<div class="modal" id="m-add-col"><div class="modal-box sm"><div class="modal-head"><h3><i class="fa-solid fa-user-plus"></i> افزودن همکار</h3><button class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><form id="f-search" class="flex gap-1"><input class="input ltr" name="mobile" data-mobile inputmode="numeric" placeholder="<?= e(ph('colleague_mobile')) ?>" required><button class="btn btn-primary">جستجو</button></form><div id="search-result" class="mt-2"></div></div></div></div>

<div class="modal" id="m-add-proj"><div class="modal-box"><div class="modal-head"><h3><i class="fa-solid fa-diagram-project"></i> افزودن <span id="ap-name"></span> به پروژه</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><p class="xs muted mb-1">فقط پروژه‌هایی نمایش داده می‌شوند که در آن‌ها دسترسی «افزودن کاربر» دارید. عضو جدید نقش «عضو» می‌گیرد؛ اگر همکارتان تأیید دستی عضویت را فعال کرده باشد، برایش دعوت ارسال می‌شود.</p>
        <div id="ap-list" class="list"></div></div></div></div>
