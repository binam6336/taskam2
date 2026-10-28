<?php
/** @var array $tasks @var int $total @var int $page @var int $pages @var string $scope @var bool $done @var string $q @var array $counts @var array $subjects @var array $projects @var array $assignees @var ?array $shared @var array $filters @var array $dueDates */
use App\Models\TaskModel;
$status = $done ? 'done' : 'open';
$tabUrl = static fn(string $sc, string $st) => url('/tasks') . '?' . http_build_query(['scope' => $sc, 'status' => $st]);
$fCount = ($filters['when'] !== '' ? 1 : 0) + ($filters['prio'] !== '' ? 1 : 0) + ($filters['project'] > 0 ? 1 : 0);
$keep = array_filter(['scope' => $scope, 'status' => $status, 'q' => $q, 'sort' => $filters['sort'] !== 'created' ? $filters['sort'] : '', 'when' => $filters['when'], 'prio' => $filters['prio'], 'project' => $filters['project'] ?: '']);
$this->boot['tasks'] = [
    'subjects' => $subjects,
    'projects' => array_map(static fn($p) => ['id' => (int) $p['id'], 'title' => $p['title'], 'image' => project_image_url($p['profile_image']), 'priority_type' => $p['priority_type'] ?? 'category'], $projects),
    'me_name' => full_name($currentUser),
    'assignees' => $assignees,
    'shared' => $shared,
    'me' => (int) $currentUser['id'],
];
$this->scripts[] = 'tasks';
?>
<div class="page-head">
    <div class="tabs" data-live="t-scope">
        <a class="tab <?= $scope === 'mine' ? 'active' : '' ?>" href="<?= e($tabUrl('mine', $status)) ?>"><i class="fa-solid fa-user-check"></i> وظایف من <span class="badge-count"><?= e(fa_num($counts['mine_open'])) ?></span></a>
        <a class="tab <?= $scope === 'others' ? 'active' : '' ?>" href="<?= e($tabUrl('others', $status)) ?>"><i class="fa-solid fa-share-nodes"></i> واگذارشده به دیگران <span class="badge-count"><?= e(fa_num($counts['others_open'])) ?></span></a>
    </div>
    <button class="btn btn-primary" id="btn-new-task"><i class="fa-solid fa-plus"></i> تسک جدید</button>
</div>

<div class="flex between items-center wrap gap-2 mb-2 t-bar">
    <div class="tabs" data-live="t-status">
        <a class="tab <?= !$done ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'open')) ?>">در جریان <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'])) ?></span></a>
        <a class="tab <?= $done ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($counts[$scope . '_done'])) ?></span></a>
    </div>
    <form method="get" class="t-toolbar" id="t-toolbar">
        <?php foreach (['scope' => $scope, 'status' => $status] as $k => $v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <?php foreach (['when', 'prio', 'project'] as $k): if ($filters[$k] !== '' && $filters[$k] !== 0): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $filters[$k]) ?>"><?php endif; endforeach; ?>
        <div class="search grow"><i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در عنوان و توضیحات…"></div>
        <div class="dropdown t-filter">
            <button type="button" class="btn <?= $fCount ? 'btn-outline' : '' ?>" data-toggle><i class="fa-solid fa-filter"></i> فیلتر<?php if ($fCount): ?> <span class="badge-count"><?= e(fa_num($fCount)) ?></span><?php endif; ?></button>
            <div class="dropdown-menu t-filter-menu">
                <div class="field"><label><i class="fa-regular fa-clock"></i> بر اساس زمان</label><select class="input" data-f="when">
                    <option value="">همه</option><option value="overdue" <?= $filters['when'] === 'overdue' ? 'selected' : '' ?>>تسک‌های عقب‌افتاده</option>
                    <option value="today" <?= $filters['when'] === 'today' ? 'selected' : '' ?>>تسک‌های امروز</option><option value="tomorrow" <?= $filters['when'] === 'tomorrow' ? 'selected' : '' ?>>تسک‌های فردا</option>
                    <?php $tod = date('Y-m-d'); $tom = date('Y-m-d', strtotime('+1 day')); foreach ($dueDates as $d): if ($d === $tod || $d === $tom) continue; ?>
                        <option value="<?= e($d) ?>" <?= $filters['when'] === $d ? 'selected' : '' ?>>تسک‌های <?= e(jdate($d . ' 00:00:00', false)) ?></option>
                    <?php endforeach; ?></select></div>
                <div class="field"><label><i class="fa-solid fa-flag"></i> بر اساس اولویت</label><select class="input" data-f="prio">
                    <option value="">همه</option><option value="high" <?= $filters['prio'] === 'high' ? 'selected' : '' ?>>اولویت بیشتر</option><option value="low" <?= $filters['prio'] === 'low' ? 'selected' : '' ?>>اولویت کمتر</option></select></div>
                <div class="field"><label><i class="fa-solid fa-diagram-project"></i> بر اساس پروژه</label><select class="input" data-f="project">
                    <option value="">همه‌ی پروژه‌ها</option><?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $filters['project'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></div>
                <div class="flex gap-1"><button type="button" class="btn btn-primary btn-sm grow" data-f-apply>اعمال فیلتر</button><?php if ($fCount): ?><button type="button" class="btn btn-sm" data-f-clear>حذف فیلترها</button><?php endif; ?></div>
            </div>
        </div>
        <label class="t-sort"><i class="fa-solid fa-arrow-down-wide-short"></i><select class="input" name="sort" data-autosubmit aria-label="مرتب‌سازی">
            <?php foreach (TaskModel::SORTS as $k => $label): ?><option value="<?= e($k) ?>" <?= $filters['sort'] === $k ? 'selected' : '' ?>>مرتب‌سازی: <?= e($label) ?></option><?php endforeach; ?></select></label>
    </form>
</div>

<div data-live="t-list">
<?php if (!$tasks): ?>
    <div class="card empty"><i class="fa-regular fa-clipboard"></i><p><?= $q !== '' || $fCount ? 'تسکی با این جستجو/فیلتر پیدا نشد.' : 'هنوز تسکی در این بخش وجود ندارد.' ?></p></div>
<?php else: ?>
<div class="list" id="task-list">
    <?php foreach ($tasks as $t):
        $isDone = (int) $t['is_completed'] === 1;
        $can = $t['can'];
        $overdue = !$isDone && $t['due_date'] && strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59')) < time();
        $edit = [
            'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null, 'due_date' => $t['due_date'],
            'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : '', 'subject_id' => $t['subject_id'], 'project_id' => $t['project_id'], 'assignee_id' => $t['assignee_id'], '_id' => (int) $t['id'], '_can' => $can,
        ];
        ?>
    <div class="item p-<?= e($t['priority']) ?> <?= $isDone ? 'done' : '' ?>" id="task-<?= (int) $t['id'] ?>">
        <button class="tick <?= $isDone ? 'on' : '' ?>" <?= $can['complete'] ? 'data-post="' . e(url('/tasks/' . $t['id'] . '/toggle')) . '"' : 'disabled' ?> title="<?= $isDone ? 'بازگشت به انجام‌نشده' : 'انجام شد' ?>"><i class="fa-solid fa-check"></i></button>
        <div class="grow">
            <div class="item-title" data-open-task="<?= (int) $t['id'] ?>"><?= e($t['title']) ?></div>
            <div class="item-meta">
                <?= priority_badge($t) ?>
                <?php if ($t['due_date']): ?><span class="<?= $overdue ? 'prio-high' : '' ?>"><i class="fa-regular fa-calendar"></i><?= e(jdate($t['due_date'] . ' 00:00:00', false)) ?><?= $t['due_time'] ? ' - ' . e(fa_num(substr((string) $t['due_time'], 0, 5))) : '' ?><?= $overdue ? ' (گذشته)' : '' ?></span><?php endif; ?>
                <?php if ($t['subject_title']): ?><span><span class="color-dot" style="background:<?= e($t['subject_color']) ?>"></span> <?= e($t['subject_title']) ?></span><?php endif; ?>
                <?php if ($t['project_title']): ?><span><i class="fa-solid fa-diagram-project"></i><?= e($t['project_title']) ?></span><?php endif; ?>
                <?php if ($scope === 'others' && $t['assignee_id']): ?><span><i class="fa-solid fa-user"></i><?= e(full_name($t, 'assignee_')) ?></span><?php endif; ?>
                <?php if ($scope === 'mine' && (int) $t['user_id'] !== (int) $currentUser['id']): ?><span><i class="fa-solid fa-user-pen"></i>از طرف <?= e(full_name($t, 'creator_')) ?></span><?php endif; ?>
                <?php if ((int) $t['notes_count'] > 0): ?><span><i class="fa-regular fa-comment"></i><?= e(fa_num($t['notes_count'])) ?></span><?php endif; ?>
                <?php if ((int) $t['attachments_count'] > 0): ?><span><i class="fa-solid fa-paperclip"></i><?= e(fa_num($t['attachments_count'])) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="item-actions">
            <button class="btn btn-sm btn-icon" data-open-task="<?= (int) $t['id'] ?>" title="جزئیات"><i class="fa-regular fa-eye"></i></button>
            <?php if ($can['edit']): ?><button class="btn btn-sm btn-icon" data-edit-task="<?= e(json_html($edit)) ?>" title="ویرایش"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            <?php if ($can['delete']): ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/tasks/' . $t['id'] . '/delete')) ?>" data-confirm="این تسک و تمام یادداشت‌ها و فایل‌های آن حذف شود؟" title="حذف"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php $this->partial('pagination', ['pg' => $page, 'pages' => $pages, 'query' => $keep]); ?>
<?php endif; ?>
</div>

<!-- ایجاد / ویرایش -->
<div class="modal" id="m-task">
    <div class="modal-box">
        <form method="post" action="<?= e(url('/tasks')) ?>" enctype="multipart/form-data" data-ajax id="f-task">
            <?= csrf_field() ?>
            <div class="modal-head"><h3><i class="fa-solid fa-list-check"></i> <span id="task-modal-title">تسک جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
            <div class="modal-body">
                <div class="field"><label>عنوان *</label><input class="input" name="title" maxlength="255" required></div>
                <div class="field"><label>توضیحات</label><textarea class="input" name="description" rows="3" data-linkify placeholder="می‌توانید لینک (http/https) هم وارد کنید؛ برایش عنوان می‌پرسیم."></textarea></div>
                <?php if (!$projects): ?>
                <div class="alert warning"><i class="fa-solid fa-triangle-exclamation"></i><div>برای ثبت تسک، پروژه اجباری است. <a href="<?= e(url('/projects')) ?>">ساخت پروژه</a></div></div>
                <?php endif; ?>
                <div class="row">
                    <div class="field" id="prio-cat"><label>اولویت</label><select class="input" name="priority"><option value="low">کم</option><option value="medium" selected>متوسط</option><option value="high">زیاد</option></select></div>
                    <div class="field hidden" id="prio-pct"><label>اولویت <b class="prio-pct-val" id="prio-pct-val">۵۰٪</b></label>
                        <input type="range" class="prio-range" name="priority_percent" min="0" max="100" step="5" value="50" disabled aria-label="اولویت درصدی">
                        <div class="prio-scale"><span>کم</span><span>متوسط</span><span>زیاد</span></div></div>
                    <div class="field"><label>موضوع *</label>
                        <div class="subj-pick" id="subj-pick"><select class="input" name="subject_id" required><option value="">— انتخاب موضوع —</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach; ?><option value="__new">＋ افزودن موضوع جدید</option></select>
                            <div class="subj-new hidden"><input class="input" maxlength="150" placeholder="عنوان موضوع جدید" aria-label="عنوان موضوع جدید"><button type="button" class="btn btn-primary btn-icon" data-subj-save title="افزودن"><i class="fa-solid fa-check"></i></button><button type="button" class="btn btn-icon" data-subj-cancel title="انصراف"><i class="fa-solid fa-xmark"></i></button></div></div></div>
                </div>
                <div class="row">
                    <div class="field"><label>تاریخ سررسید</label><div data-jdate="due_date"></div></div>
                    <div class="field"><label>ساعت سررسید</label><div data-jtime="due_time"></div></div>
                </div>
                <div class="row">
                    <div class="field"><label>پروژه *</label><div data-rselect="project_id"></div></div>
                    <div class="field"><label>مسئول انجام</label><div data-rselect="assignee_id"></div></div>
                </div>
                <div class="field" id="task-files-field"><label>پیوست‌ها <span class="faint">(حداکثر ۱۰ مگابایت برای هر فایل)</span></label><input class="input" type="file" name="attachments[]" multiple></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div>
        </form>
    </div>
</div>

<!-- جزئیات -->
<div class="modal" id="m-detail"><div class="modal-box lg"><div class="modal-body" id="detail-body"><div class="center muted">در حال بارگذاری…</div></div></div></div>
