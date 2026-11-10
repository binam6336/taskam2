<?php
/** @var array $tasks @var int $total @var int $page @var int $pages @var string $scope @var bool $done @var string $q @var array $counts @var array $subjects @var array $projects @var array $assignees @var ?array $shared @var array $filters @var array $dueDates @var ?array $projScope تسک‌های یک پروژه (از تحلیل پروژه) */
use App\Models\TaskModel;
$status = $projScope['status'] ?? (!empty($statusAll) ? 'all' : ($done ? 'done' : 'open'));
$pq = $projScope ? array_filter(['project' => $projScope['id'], 'assignee' => $projScope['assignee'] ? pid('users', $projScope['assignee']) : null]) : [];
$lq = $filters['label'] ? ['label' => pid('subjects', $filters['label'])] : [];   // فیلتر لیبل بین تب‌ها می‌ماند
$tabUrl = static fn(string $sc, string $st) => url('/tasks') . '?' . http_build_query(['scope' => $sc, 'status' => $st] + ($sc === 'project' ? $pq : []) + $lq);
$fCount = ($filters['when'] !== '' ? 1 : 0) + ($filters['prio'] !== '' ? 1 : 0) + ($filters['project'] > 0 ? 1 : 0) + ($filters['label'] > 0 ? 1 : 0);
$keep = array_filter(['scope' => $scope, 'status' => $status, 'q' => $q, 'sort' => $filters['sort'] !== 'smart' ? $filters['sort'] : '', 'flow' => $filters['flow'], 'when' => $filters['when'], 'prio' => $filters['prio'], 'project' => $filters['project'] ? pid('projects', $filters['project']) : '', 'assignee' => $filters['assignee'] ? pid('users', $filters['assignee']) : '', 'label' => $filters['label'] ? pid('subjects', $filters['label']) : '']);
?>
<section class="t-ctrl" aria-label="ابزار تسک‌ها">
<div class="t-ctrl-row t-ctrl-scope">
    <?php if ($projScope): ?>
    <div class="t-proj-head">
        <a class="small muted" href="<?= e(url('/projects/' . $projScope['id'] . '/analytics')) ?>"><i class="fa-solid fa-arrow-right"></i> تحلیل پروژه</a>
        <h2><i class="dc-961bcb fa-solid fa-diagram-project"></i> تسک‌های پروژه «<?= e($projScope['title']) ?>»<?php if ($projScope['assignee_name']): ?> <span class="chip"><i class="fa-solid fa-user"></i> <?= e($projScope['assignee_name']) ?></span><?php endif; ?>
            <?php if ($filters['when'] === 'overdue'): ?> <span class="badge red">عقب‌افتاده</span><?php endif; ?></h2>
    </div>
    <?php else: ?>
    <div class="tabs" data-live="t-scope">
        <a class="tab <?= $scope === 'mine' ? 'active' : '' ?>" href="<?= e($tabUrl('mine', $status)) ?>"><i class="fa-solid fa-user-check"></i> وظایف من <span class="badge-count"><?= e(fa_num($counts['mine_open'])) ?></span></a>
        <a class="tab <?= $scope === 'others' ? 'active' : '' ?>" href="<?= e($tabUrl('others', $status)) ?>"><i class="fa-solid fa-share-nodes"></i> واگذارشده به دیگران <span class="badge-count"><?= e(fa_num($counts['others_open'])) ?></span></a>
    </div>
    <?php endif; ?>
    <button type="button" class="btn btn-primary" id="btn-new-task" aria-label="تسک جدید"><i class="fa-solid fa-plus" aria-hidden="true"></i> <span class="btn-t">تسک جدید</span></button>
</div>

<div class="t-ctrl-row t-ctrl-search">
    <form method="get" class="t-toolbar" id="t-toolbar">
        <?php foreach (['scope' => $scope, 'status' => $status] as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <?php foreach (['when', 'prio', 'project', 'assignee', 'label', 'flow'] as $k): if ($filters[$k] !== '' && $filters[$k] !== 0): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $filters[$k]) ?>"><?php endif; endforeach; ?>
        <div class="search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('tasks_search')) ?>"></div>
        <?php $sCount = $fCount + ($filters['sort'] !== 'smart' ? 1 : 0); ?>
        <button type="button" class="btn t-bulk-btn" data-bulk-toggle aria-pressed="false" title="انتخاب چند تسک و تغییر هم‌زمان" aria-label="تغییر گروهی"><i class="fa-solid fa-list-check" aria-hidden="true"></i> <span class="btn-t">تغییر گروهی</span></button>
        <a class="btn t-cal-btn" href="<?= e(url('/calendar')) ?>" title="تقویم تسک‌ها" aria-label="تقویم تسک‌ها"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> <span class="btn-t">تقویم</span></a>
        <button type="button" class="btn btn-icon t-drawer-btn<?= $sCount ? ' btn-outline' : '' ?>" data-drawer-open aria-controls="t-drawer" aria-expanded="false" title="مرتب‌سازی و فیلتر" aria-label="مرتب‌سازی و فیلتر"><i class="fa-solid fa-filter"></i><?php if ($sCount): ?><span class="badge-count t-drawer-count"><?= e(fa_num($sCount)) ?></span><?php endif; ?></button>
        <!-- سایدبار مخفی مرتب‌سازی و فیلتر (سمت چپ) -->
        <div class="t-drawer-back" data-drawer-close hidden></div>
        <aside class="t-drawer" id="t-drawer" aria-label="مرتب‌سازی و فیلتر" aria-hidden="true">
            <div class="t-drawer-head"><b><i class="fa-solid fa-sliders"></i> مرتب‌سازی و فیلتر</b><button type="button" class="btn btn-ghost btn-icon btn-sm" data-drawer-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="t-drawer-body">
                <div class="t-drawer-sec"><h4><i class="fa-solid fa-arrow-down-wide-short"></i> مرتب‌سازی</h4>
                    <div class="t-sort-list"><?php foreach (TaskModel::SORTS as $k => $label): ?><label class="t-sort-opt"><input type="radio" name="sort" value="<?= e($k) ?>" <?= $filters['sort'] === $k ? 'checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div></div>
                <div class="t-drawer-sec"><h4><i class="fa-solid fa-filter"></i> فیلتر</h4>
                    <div class="field"><label><i class="fa-regular fa-clock"></i> بر اساس زمان</label><select class="input" data-f="when">
                        <option value="">همه</option><option value="overdue" <?= $filters['when'] === 'overdue' ? 'selected' : '' ?>>تسک‌های عقب‌افتاده</option>
                        <option value="today" <?= $filters['when'] === 'today' ? 'selected' : '' ?>>تسک‌های امروز</option><option value="tomorrow" <?= $filters['when'] === 'tomorrow' ? 'selected' : '' ?>>تسک‌های فردا</option>
                        <?php $tod = date('Y-m-d'); $tom = date('Y-m-d', strtotime('+1 day')); foreach ($dueDates as $d): if ($d === $tod || $d === $tom) continue; ?>
                            <option value="<?= e($d) ?>" <?= $filters['when'] === $d ? 'selected' : '' ?>>تسک‌های <?= e(jdate($d . ' 00:00:00', false)) ?></option>
                        <?php endforeach; ?></select></div>
                    <div class="field"><label><i class="fa-solid fa-flag"></i> بر اساس اولویت</label><select class="input" data-f="prio">
                        <option value="">همه</option><option value="high" <?= $filters['prio'] === 'high' ? 'selected' : '' ?>>اولویت بیشتر</option><option value="low" <?= $filters['prio'] === 'low' ? 'selected' : '' ?>>اولویت کمتر</option></select></div>
                    <div class="field"><label><i class="fa-solid fa-tag"></i> بر اساس لیبل</label><select class="input" data-f="label">
                        <option value="">همه‌ی لیبل‌ها</option><?php foreach ($subjects as $sb): ?><option value="<?= e(pid('subjects', $sb)) ?>" <?= $filters['label'] === (int) $sb['id'] ? 'selected' : '' ?>><?= e($sb['title']) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label><i class="fa-solid fa-diagram-project"></i> بر اساس پروژه</label><select class="input" data-f="project" data-logo-select>
                        <option value="">همه‌ی پروژه‌ها</option><?php foreach ($projects as $p): ?><option value="<?= e(pid('projects', $p)) ?>" data-img="<?= e((string) project_image_url($p['profile_image'] ?? null)) ?>" <?= $filters['project'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="t-drawer-foot"><span class="xs faint grow" data-f-note>تغییرها همان لحظه اعمال می‌شوند.</span><button type="button" class="btn" data-f-clear<?= $sCount ? '' : ' hidden' ?>><i class="fa-solid fa-rotate-left"></i> حذف همه</button></div>
        </aside>
    </form>
</div>
<div class="t-ctrl-sep" aria-hidden="true"></div>
<div class="t-ctrl-row t-ctrl-status">
    <div class="tabs" data-live="t-status">
        <?php if ($projScope): $pc = $projScope['counts']; ?>
        <a class="tab <?= $status === 'all' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'all')) ?>">همه <span class="badge-count"><?= e(fa_num($pc['all'])) ?></span></a>
        <a class="tab <?= $status === 'open' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'open')) ?>">باز <span class="badge-count"><?= e(fa_num($pc['open'])) ?></span></a>
        <a class="tab <?= $status === 'done' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($pc['done'])) ?></span></a>
        <?php else: ?>
        <a class="tab <?= $status === 'all' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'all')) ?>">همه <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'] + $counts[$scope . '_done'])) ?></span></a>
        <?php $rv = ($counts[$scope . '_review'] ?? 0); ?>
        <a class="tab <?= $status === 'open' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'open')) ?>" title="<?= $rv ? e(fa_num($counts[$scope . '_open'] - $rv) . ' تسک در جریان و ' . fa_num($rv) . ' تسک تحویل‌داده‌شده که منتظر تأیید سرپرست است') : '' ?>">در جریان <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'] - $rv)) ?></span><?php if ($rv): ?> <span class="badge-count t-rv-count">+ <?= e(fa_num($rv)) ?> منتظر تأیید</span><?php endif; ?></a>
        <a class="tab <?= $status === 'done' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($counts[$scope . '_done'])) ?></span></a>
        <?php endif; ?>
    </div>
</div>

<?php // فیلترهای پرکاربرد به‌صورت چیپ (کلیک دوباره = برداشتن فیلتر)
$chipUrl = static function (array $set) use ($keep): string { $q = array_filter(array_merge($keep, $set), static fn($v) => $v !== '' && $v !== null); unset($q['page']); return url('/tasks') . '?' . http_build_query($q); };
$chips = [
    ['when', 'today', 'fa-regular fa-calendar-check', 'امروز', null],
    ['when', 'overdue', 'fa-solid fa-triangle-exclamation', 'عقب‌افتاده', null],
    ['flow', 'review', 'fa-solid fa-magnifying-glass', 'منتظر تأیید من', $flowCounts['review'] ?? 0],
    ['flow', 'rework', 'fa-solid fa-rotate-left', 'برگشت‌خورده برای اصلاح', $flowCounts['rework'] ?? 0],
]; ?>
<div class="t-fgrp" role="group" aria-label="فیلترهای سریع">
<span class="t-fgrp-lbl">فیلتر سریع:</span>
<div class="t-chips" data-live="t-chips">
    <?php foreach ($chips as [$k, $v, $ic, $lbl, $n]): $on = $filters[$k] === $v; if ($k === 'flow' && !$n && !$on) continue; ?>
    <a class="chip t-chip<?= $on ? ' on' : '' ?>" href="<?= e($chipUrl([$k => $on ? '' : $v] + ($k === 'flow' ? ['when' => ''] : ['flow' => '']))) ?>" <?= $on ? 'aria-current="true"' : '' ?>><i class="<?= e($ic) ?>"></i> <?= e($lbl) ?><?php if ($n): ?> <span class="badge-count"><?= e(fa_num($n)) ?></span><?php endif; ?><?php if ($on): ?> <i class="fa-solid fa-xmark xs"></i><?php endif; ?></a>
    <?php endforeach; ?>
</div>
<?php $this->partial('blocked_button', ['kind' => 'tasks', 'count' => $blockedCount ?? 0, 'label' => 'تسک‌های مسدودشده', 'text' => 'مسدودشده']); ?>
</div>
</section>

<div data-live="t-list">
<?php if (!$tasks): ?>
    <div class="card empty"><i class="fa-regular fa-clipboard"></i><p><?= $q !== '' || $fCount ? 'تسکی با این جستجو/فیلتر پیدا نشد.' : 'هنوز تسکی در این بخش وجود ندارد.' ?></p></div>
<?php else: ?>
<div class="list" id="task-list">
    <?php $grpPrev = null; $grpNames = \App\Models\TaskModel::GROUP_NAMES;
    $smart = $filters['sort'] === 'smart';
    // تعداد هر گروه در همین صفحه (شمارنده‌ی سرگروه)
    $grpCount = array_count_values(array_map([\App\Models\TaskModel::class, 'groupOf'], $tasks));
    // سرگروه‌های تاشو (v5.7): دکمه با aria-expanded + بدنه‌ی گروه؛ «منتظر تأیید سرپرست» پیش‌فرض بسته (وضعیت در localStorage، tasks.js)
    $grpKeys = ['rework', 'overdue', 'today', 'nodate', 'upcoming', 'review', 'done'];
    foreach ($tasks as $t):
        $isDone = (int) $t['is_completed'] === 1;
        $g = \App\Models\TaskModel::groupOf($t);
        if ($smart && $g !== $grpPrev) {
            if ($grpPrev !== null) { echo '</div></div>'; }
            $grpPrev = $g; $gk = $grpKeys[$g] ?? ('g' . $g); $gOpen = $g !== 5;   // منتظر تأیید: جمع‌شده
            $gIco = [0 => 'fa-rotate-left', 5 => 'fa-hourglass-half'][$g] ?? '';
            echo '<div class="t-grp t-grp-' . e($gk) . '"><button type="button" class="t-gh t-gh-' . $g . ($g === 1 ? ' late' : '') . '" data-tgh="' . e($gk) . '" data-tgh-default="' . ($gOpen ? '1' : '0') . '" aria-expanded="' . ($gOpen ? 'true' : 'false') . '" aria-controls="grp-' . e($gk) . '">'
                . '<span>' . ($gIco ? '<i class="fa-solid ' . $gIco . '" aria-hidden="true"></i> ' : '') . e($grpNames[$g]) . '</span><span class="t-gh-cnt">' . e(fa_num($grpCount[$g] ?? 0)) . ' تسک</span>'
                . '<i class="fa-solid fa-chevron-down t-gh-chev" aria-hidden="true"></i></button>'
                . '<div id="grp-' . e($gk) . '" class="t-grp-body"' . ($gOpen ? '' : ' hidden') . '>';
        }
        $can = $t['can'];
        // عقب‌افتاده فقط روزهای قبل؛ امروز با ساعت گذشته = «ساعت گذشته» (تعریف واحد v4.1)
        // v7.7: تحویل‌شده‌ی منتظر تأیید عقب‌افتاده نیست (TaskModel::OVERDUE)
        $overdue = !$isDone && $t['due_date'] && $t['due_date'] < date('Y-m-d') && !($t['project_id'] !== null && empty($t['archived_at']) && ($t['board_status'] ?? '') === 'review');
        $hourPast = !$isDone && !$overdue && $t['due_date'] === date('Y-m-d') && $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time();
        $edit = [
            'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null, 'due_date' => $t['due_date'],
            'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : '', 'subject_id' => $t['subject_id'], 'project_id' => $t['project_id'], 'assignee_id' => $t['assignee_id'],
            '_id' => pid('tasks', $t), '_can' => $can,
        ];
        ?>
    <div class="item p-<?= e($t['priority']) ?> <?= $isDone ? 'done' : '' ?><?= $smart && $g === 5 ? ' t-in-review' : '' ?><?= $g === 0 ? ' t-rework' : '' ?>" id="task-<?= e(pid('tasks', $t)) ?>">
        <?php // تیک (v4.1): برای همه = تحویل برای «بررسی سرپرست»؛ تسک منتظر تأیید = ساعت شنی (تحویل‌دهنده با کلیک پس می‌گیرد)
        $inReview = $can['status'] === 'review';
        $tickTitle = ($isDone ? 'بازگشت به بررسی سرپرست' : ($inReview ? (!empty($can['approve']) ? 'تأیید و بستن (شما دسترسی تأیید دارید)' : ($can['withdraw'] ? 'منتظر تأیید سرپرست — کلیک: پس گرفتن تحویل' : 'منتظر تأیید سرپرست')) : (!empty($can['approve_direct']) ? 'انجام شد (مستقیم؛ شما دسترسی تأیید دارید)' : 'انجام شد (ارسال برای بررسی سرپرست)')));
        $tickLock = $isDone ? 'بازکردن تسک انجام‌شده فقط با دسترسی تأیید (تکمیل تسک) در این پروژه ممکن است.' : ($inReview ? 'این تسک منتظر تأیید سرپرست است؛ تأیید یا برگرداندن برای اصلاح از جزئیات تسک انجام می‌شود.' : 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را برای بررسی بفرستد.'); ?>
        <button type="button" class="tick <?= $isDone ? 'on' : '' ?><?= $inReview ? ' review' : '' ?><?= $can['tick'] ? '' : ' locked' ?>" <?= $can['tick'] ? 'data-post="' . e(url('/tasks/' . pid('tasks', $t) . '/toggle')) . '"' . ($isDone ? ' data-confirm="تسک انجام‌شده دوباره به «بررسی سرپرست» برگردد؟" data-danger="0"' : '') : 'data-lock-reason="' . e($tickLock) . '" data-lock-title="انجام شد" aria-disabled="true"' ?> title="<?= e($tickTitle) ?>" aria-label="<?= e($tickTitle) ?>"><i class="fa-solid <?= $inReview ? 'fa-hourglass-half' : 'fa-check' ?>"></i></button>
        <div class="grow">
            <button type="button" class="item-title" data-open-task="<?= e(pid('tasks', $t)) ?>"><?= e($t['title']) ?></button>
            <?php if ($g === 0 && trim((string) ($t['blocked_reason'] ?? '')) !== ''): ?><div class="t-rework-why"><i class="fa-solid fa-rotate-left"></i> علت برگشت: <?= e(mb_strimwidth((string) $t['blocked_reason'], 0, 160, '…')) ?></div><?php endif; ?>
            <div class="item-meta">
                <?= priority_badge($t) ?>
                <?php if ($can['status'] !== null && !$isDone): $bs = \App\Services\BoardConfig::info(isset($t['project_id']) ? (int) $t['project_id'] : null, (string) $can['status']); if ($bs): ?><span class="badge board-st tone-<?= e($bs['tone']) ?>" title="وضعیت در بورد پروژه"><i class="fa-solid <?= e($bs['icon']) ?>"></i> <?= e($bs['label']) ?></span><?php endif; endif; ?>
                <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i><?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $t['due_time'] ? ' - ' . e(fa_num(substr((string) $t['due_time'], 0, 5))) : '' ?><?= $overdue && !($smart && $g === 1) ? ' (عقب‌افتاده)' : '' ?><?php if ($hourPast): ?> <span class="hour-past">ساعت گذشته</span><?php endif; ?></span><?php endif; ?>
                <?php if ($t['subject_title']): ?><span class="t-label"><i class="fa-solid fa-tag" data-css="color:<?= e($t['subject_color']) ?>"></i> <?= e($t['subject_title']) ?></span><?php endif; ?>
                <?php if ($t['project_title']): ?><span><i class="fa-solid fa-diagram-project"></i><?= e($t['project_title']) ?></span><?php endif; ?>
                <?php if ($scope === 'others' && $t['assignee_id']): ?><span><i class="fa-solid fa-user"></i><?= e(full_name($t, 'assignee_')) ?></span><?php endif; ?>
                <?php if ($scope === 'mine' && (int) $t['user_id'] !== (int) $currentUser['id']): ?><span><i class="fa-solid fa-user-pen"></i>از طرف <?= e(full_name($t, 'creator_')) ?></span><?php endif; ?>
                <?php if ((int) $t['notes_count'] > 0): ?><span><i class="fa-regular fa-comment"></i><?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                <?php if ((int) $t['attachments_count'] > 0): ?><span><i class="fa-solid fa-paperclip"></i><?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="item-actions">
            <!-- موبایل: دکمه‌ها در منوی ⋮ (دسکتاپ: ردیف آیکون‌ها) -->
            <button type="button" class="btn btn-sm btn-icon item-more-btn" data-item-more aria-expanded="false" aria-label="گزینه‌های تسک" title="گزینه‌ها"><i class="fa-solid fa-ellipsis-vertical"></i></button>
            <div class="item-acts">
            <button type="button" class="btn btn-sm btn-icon" data-open-task="<?= e(pid('tasks', $t)) ?>" title="جزئیات" aria-label="جزئیات"><i class="fa-regular fa-eye"></i><span class="m-lbl">جزئیات</span></button>
            <?php if ($can['edit']): ?><button type="button" class="btn btn-sm btn-icon" data-edit-task="<?= e(json_html($edit)) ?>" title="ویرایش" aria-label="ویرایش"><i class="fa-solid fa-pen"></i><span class="m-lbl">ویرایش</span></button><?php endif; ?>
            <?php if ($can['block']): ?><button type="button" class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/block/tasks/' . pid('tasks', $t))) ?>" data-confirm="این تسک مسدود شود؟ از همه‌ی فهرست‌ها کنار می‌رود و از «تسک‌های مسدودشده» برمی‌گردد." title="مسدود کردن" aria-label="مسدود کردن"><i class="fa-solid fa-ban"></i><span class="m-lbl">مسدود کردن</span></button><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; if ($smart && $grpPrev !== null) { echo '</div></div>'; } ?>
</div>
<?php $this->partial('pagination', ['pg' => $page, 'pages' => $pages, 'query' => $keep]); ?>
<?php endif; ?>
</div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => $shared]); ?>
<?php $this->partial('bulk_edit'); ?>
