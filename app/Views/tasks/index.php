<?php
/** @var array $tasks @var int $total @var int $page @var int $pages @var string $scope @var bool $done @var string $q @var array $counts @var array $subjects @var array $projects @var array $assignees @var ?array $shared @var array $filters @var array $dueDates @var ?array $projScope تسک‌های یک پروژه (از تحلیل پروژه) */
use App\Models\TaskModel;
$status = $projScope['status'] ?? (!empty($statusAll) ? 'all' : ($done ? 'done' : 'open'));
$pq = $projScope ? array_filter(['project' => $projScope['id'], 'assignee' => $projScope['assignee'] ?: null]) : [];
$lq = $filters['label'] ? ['label' => $filters['label']] : [];   // فیلتر لیبل بین تب‌ها می‌ماند
$tabUrl = static fn(string $sc, string $st) => url('/tasks') . '?' . http_build_query(['scope' => $sc, 'status' => $st] + ($sc === 'project' ? $pq : []) + $lq);
$fCount = ($filters['when'] !== '' ? 1 : 0) + ($filters['prio'] !== '' ? 1 : 0) + ($filters['project'] > 0 ? 1 : 0) + ($filters['label'] > 0 ? 1 : 0);
$keep = array_filter(['scope' => $scope, 'status' => $status, 'q' => $q, 'sort' => $filters['sort'] !== 'smart' ? $filters['sort'] : '', 'flow' => $filters['flow'], 'when' => $filters['when'], 'prio' => $filters['prio'], 'project' => $filters['project'] ?: '', 'assignee' => $filters['assignee'] ?: '', 'label' => $filters['label'] ?: '']);
?>
<div class="page-head">
    <?php if ($projScope): ?>
    <div class="t-proj-head">
        <a class="small muted" href="<?= e(url('/projects/' . $projScope['id'] . '/analytics')) ?>"><i class="fa-solid fa-arrow-right"></i> تحلیل پروژه</a>
        <h2><i class="fa-solid fa-diagram-project" data-css="color:var(--g-400)"></i> تسک‌های پروژه «<?= e($projScope['title']) ?>»<?php if ($projScope['assignee_name']): ?> <span class="chip"><i class="fa-solid fa-user"></i> <?= e($projScope['assignee_name']) ?></span><?php endif; ?>
            <?php if ($filters['when'] === 'overdue'): ?> <span class="badge red">عقب‌افتاده</span><?php endif; ?></h2>
    </div>
    <?php else: ?>
    <div class="tabs" data-live="t-scope">
        <a class="tab <?= $scope === 'mine' ? 'active' : '' ?>" href="<?= e($tabUrl('mine', $status)) ?>"><i class="fa-solid fa-user-check"></i> وظایف من <span class="badge-count"><?= e(fa_num($counts['mine_open'])) ?></span></a>
        <a class="tab <?= $scope === 'others' ? 'active' : '' ?>" href="<?= e($tabUrl('others', $status)) ?>"><i class="fa-solid fa-share-nodes"></i> واگذارشده به دیگران <span class="badge-count"><?= e(fa_num($counts['others_open'])) ?></span></a>
    </div>
    <?php endif; ?>
    <button class="btn btn-primary" id="btn-new-task"><i class="fa-solid fa-plus"></i> تسک جدید</button>
</div>

<div class="flex between items-center wrap gap-2 mb-2 t-bar">
    <div class="flex items-center gap-2 t-bar-start">
    <div class="tabs" data-live="t-status">
        <?php if ($projScope): $pc = $projScope['counts']; ?>
        <a class="tab <?= $status === 'all' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'all')) ?>">همه <span class="badge-count"><?= e(fa_num($pc['all'])) ?></span></a>
        <a class="tab <?= $status === 'open' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'open')) ?>">باز <span class="badge-count"><?= e(fa_num($pc['open'])) ?></span></a>
        <a class="tab <?= $status === 'done' ? 'active' : '' ?>" href="<?= e($tabUrl('project', 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($pc['done'])) ?></span></a>
        <?php else: ?>
        <a class="tab <?= $status === 'all' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'all')) ?>">همه <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'] + $counts[$scope . '_done'])) ?></span></a>
        <a class="tab <?= $status === 'open' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'open')) ?>">در جریان <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'])) ?></span></a>
        <a class="tab <?= $status === 'done' ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($counts[$scope . '_done'])) ?></span></a>
        <?php endif; ?>
    </div>
    <?php $this->partial('blocked_button', ['kind' => 'tasks', 'count' => $blockedCount ?? 0, 'label' => 'تسک‌های مسدودشده']); ?>
    </div>
    <form method="get" class="t-toolbar" id="t-toolbar">
        <?php foreach (['scope' => $scope, 'status' => $status] as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <?php foreach (['when', 'prio', 'project', 'assignee', 'label', 'flow'] as $k): if ($filters[$k] !== '' && $filters[$k] !== 0): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $filters[$k]) ?>"><?php endif; endforeach; ?>
        <div class="search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e(ph('tasks_search')) ?>"></div>
        <?php $sCount = $fCount + ($filters['sort'] !== 'smart' ? 1 : 0); ?>
        <a class="btn t-cal-btn" href="<?= e(url('/calendar')) ?>" title="تقویم تسک‌ها"><i class="fa-regular fa-calendar-days"></i> تقویم</a>
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
                        <option value="">همه‌ی لیبل‌ها</option><?php foreach ($subjects as $sb): ?><option value="<?= (int) $sb['id'] ?>" <?= $filters['label'] === (int) $sb['id'] ? 'selected' : '' ?>><?= e($sb['title']) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label><i class="fa-solid fa-diagram-project"></i> بر اساس پروژه</label><select class="input" data-f="project" data-logo-select>
                        <option value="">همه‌ی پروژه‌ها</option><?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" data-img="<?= e((string) project_image_url($p['profile_image'] ?? null)) ?>" <?= $filters['project'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="t-drawer-foot"><button type="button" class="btn btn-primary grow" data-f-apply><i class="fa-solid fa-check"></i> اعمال</button><?php if ($sCount): ?><button type="button" class="btn" data-f-clear><i class="fa-solid fa-rotate-left"></i> حذف همه</button><?php endif; ?></div>
        </aside>
    </form>
</div>

<?php // فیلترهای پرکاربرد به‌صورت چیپ (کلیک دوباره = برداشتن فیلتر)
$chipUrl = static function (array $set) use ($keep): string { $q = array_filter(array_merge($keep, $set), static fn($v) => $v !== '' && $v !== null); unset($q['page']); return url('/tasks') . '?' . http_build_query($q); };
$chips = [
    ['when', 'today', 'fa-regular fa-calendar-check', 'امروز', null],
    ['when', 'overdue', 'fa-solid fa-triangle-exclamation', 'عقب‌افتاده', null],
    ['flow', 'review', 'fa-solid fa-magnifying-glass', 'منتظر تأیید من', $flowCounts['review'] ?? 0],
    ['flow', 'rework', 'fa-solid fa-rotate-left', 'برگشت‌خورده برای اصلاح', $flowCounts['rework'] ?? 0],
]; ?>
<div class="t-chips mb-2" data-live="t-chips" role="group" aria-label="فیلترهای سریع">
    <?php foreach ($chips as [$k, $v, $ic, $lbl, $n]): $on = $filters[$k] === $v; if ($k === 'flow' && !$n && !$on) continue; ?>
    <a class="chip t-chip<?= $on ? ' on' : '' ?>" href="<?= e($chipUrl([$k => $on ? '' : $v] + ($k === 'flow' ? ['when' => ''] : ['flow' => '']))) ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><i class="<?= e($ic) ?>"></i> <?= e($lbl) ?><?php if ($n): ?> <span class="badge-count"><?= e(fa_num($n)) ?></span><?php endif; ?><?php if ($on): ?> <i class="fa-solid fa-xmark xs"></i><?php endif; ?></a>
    <?php endforeach; ?>
</div>

<div data-live="t-list">
<?php if (!$tasks): ?>
    <div class="card empty"><i class="fa-regular fa-clipboard"></i><p><?= $q !== '' || $fCount ? 'تسکی با این جستجو/فیلتر پیدا نشد.' : 'هنوز تسکی در این بخش وجود ندارد.' ?></p></div>
<?php else: ?>
<div class="list" id="task-list">
    <?php $grpPrev = null; $grpNames = ['عقب‌افتاده', 'امروز', 'پیش رو', 'پیش رو', 'بدون تاریخ', 'انجام‌شده'];
    foreach ($tasks as $t):
        $isDone = (int) $t['is_completed'] === 1;
        if ($filters['sort'] === 'smart') {   // سرتیتر گروه‌های مرتب‌سازی هوشمند (همان TaskModel::GROUP_SQL)
            $today = date('Y-m-d');
            $g = $isDone ? 5 : (!$t['due_date'] ? 4 : ($t['due_date'] < $today ? 0 : ($t['due_date'] === $today ? 1 : 2)));
            if ($grpNames[$g] !== $grpPrev) { $grpPrev = $grpNames[$g]; echo '<div class="t-group t-group-' . $g . '">' . e($grpPrev) . '</div>'; }
        }
        $can = $t['can'];
        // عقب‌افتاده فقط روزهای قبل؛ امروز با ساعت گذشته = «ساعت گذشته» (تعریف واحد v4.1)
        $overdue = !$isDone && $t['due_date'] && $t['due_date'] < date('Y-m-d');
        $hourPast = !$isDone && !$overdue && $t['due_date'] === date('Y-m-d') && $t['due_time'] && strtotime($t['due_date'] . ' ' . $t['due_time']) < time();
        $edit = [
            'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null, 'due_date' => $t['due_date'],
            'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : '', 'subject_id' => $t['subject_id'], 'project_id' => $t['project_id'], 'assignee_id' => $t['assignee_id'], '_id' => (int) $t['id'], '_can' => $can,
        ];
        ?>
    <div class="item p-<?= e($t['priority']) ?> <?= $isDone ? 'done' : '' ?>" id="task-<?= (int) $t['id'] ?>">
        <?php // تیک (v4.1): برای همه = تحویل برای «بررسی سرپرست»؛ تسک منتظر تأیید = ساعت شنی (تحویل‌دهنده با کلیک پس می‌گیرد)
        $inReview = $can['status'] === 'review';
        $tickTitle = $isDone ? 'بازگشت به بررسی سرپرست' : ($inReview ? ($can['withdraw'] ? 'منتظر تأیید سرپرست — کلیک: پس گرفتن تحویل' : 'منتظر تأیید سرپرست') : 'انجام شد (ارسال برای بررسی سرپرست)');
        $tickLock = $isDone ? 'بازکردن تسک انجام‌شده فقط با دسترسی تأیید (تکمیل تسک) در این پروژه ممکن است.' : ($inReview ? 'این تسک منتظر تأیید سرپرست است؛ تأیید یا برگرداندن برای اصلاح از جزئیات تسک انجام می‌شود.' : 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را برای بررسی بفرستد.'); ?>
        <button class="tick <?= $isDone ? 'on' : '' ?><?= $inReview ? ' review' : '' ?><?= $can['tick'] ? '' : ' locked' ?>" <?= $can['tick'] ? 'data-post="' . e(url('/tasks/' . $t['id'] . '/toggle')) . '"' . ($isDone ? ' data-confirm="تسک انجام‌شده دوباره به «بررسی سرپرست» برگردد؟" data-danger="0"' : '') : 'data-lock-reason="' . e($tickLock) . '" data-lock-title="انجام شد" aria-disabled="true"' ?> title="<?= e($tickTitle) ?>" aria-label="<?= e($tickTitle) ?>"><i class="fa-solid <?= $inReview ? 'fa-hourglass-half' : 'fa-check' ?>"></i></button>
        <div class="grow">
            <div class="item-title" data-open-task="<?= (int) $t['id'] ?>"><?= e($t['title']) ?></div>
            <div class="item-meta">
                <?= priority_badge($t) ?>
                <?php if ($can['status'] !== null && !$isDone): $bs = \App\Services\BoardService::STATUSES[$can['status']] ?? null; if ($bs): ?><span class="badge board-st tone-<?= e($bs['tone']) ?>" title="وضعیت در بورد پروژه"><i class="fa-solid <?= e($bs['icon']) ?>"></i> <?= e($bs['label']) ?></span><?php endif; endif; ?>
                <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i><?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $t['due_time'] ? ' - ' . e(fa_num(substr((string) $t['due_time'], 0, 5))) : '' ?><?= $overdue ? ' (عقب‌افتاده)' : '' ?><?php if ($hourPast): ?> <span class="hour-past">ساعت گذشته</span><?php endif; ?></span><?php endif; ?>
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
            <button class="btn btn-sm btn-icon" data-open-task="<?= (int) $t['id'] ?>" title="جزئیات" aria-label="جزئیات"><i class="fa-regular fa-eye"></i><span class="m-lbl">جزئیات</span></button>
            <?php if ($can['edit']): ?><button class="btn btn-sm btn-icon" data-edit-task="<?= e(json_html($edit)) ?>" title="ویرایش" aria-label="ویرایش"><i class="fa-solid fa-pen"></i><span class="m-lbl">ویرایش</span></button><?php endif; ?>
            <?php if ($can['block']): ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/block/tasks/' . $t['id'])) ?>" data-confirm="این تسک مسدود شود؟ از همه‌ی فهرست‌ها کنار می‌رود و از «تسک‌های مسدودشده» برمی‌گردد." title="مسدود کردن" aria-label="مسدود کردن"><i class="fa-solid fa-ban"></i><span class="m-lbl">مسدود کردن</span></button><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php $this->partial('pagination', ['pg' => $page, 'pages' => $pages, 'query' => $keep]); ?>
<?php endif; ?>
</div>

<?php $this->partial('task_modals', ['subjects' => $subjects, 'projects' => $projects, 'assignees' => $assignees, 'shared' => $shared]); ?>
