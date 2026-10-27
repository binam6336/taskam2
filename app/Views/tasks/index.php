<?php
/** @var array $tasks @var int $total @var int $page @var int $pages @var string $scope @var bool $done @var string $q @var array $counts @var array $subjects @var array $projects @var array $assignees @var ?array $shared */
$status = $done ? 'done' : 'open';
$tabUrl = static fn(string $sc, string $st) => url('/tasks') . '?' . http_build_query(['scope' => $sc, 'status' => $st]);
$prio = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
$this->boot['tasks'] = [
    'subjects' => $subjects,
    'projects' => array_map(static fn($p) => ['id' => (int) $p['id'], 'title' => $p['title'], 'image' => project_image_url($p['profile_image'])], $projects),
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

<div class="flex between items-center wrap gap-2 mb-2">
    <div class="tabs" data-live="t-status">
        <a class="tab <?= !$done ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'open')) ?>">در جریان <span class="badge-count"><?= e(fa_num($counts[$scope . '_open'])) ?></span></a>
        <a class="tab <?= $done ? 'active' : '' ?>" href="<?= e($tabUrl($scope, 'done')) ?>">انجام‌شده <span class="badge-count"><?= e(fa_num($counts[$scope . '_done'])) ?></span></a>
    </div>
    <form method="get" class="search" style="min-width:260px">
        <input type="hidden" name="scope" value="<?= e($scope) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
        <i class="fa-solid fa-magnifying-glass"></i><input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجو در عنوان و توضیحات…">
    </form>
</div>

<div data-live="t-list">
<?php if (!$tasks): ?>
    <div class="card empty"><i class="fa-regular fa-clipboard"></i><p><?= $q !== '' ? 'نتیجه‌ای برای جستجوی شما پیدا نشد.' : 'هنوز تسکی در این بخش وجود ندارد.' ?></p></div>
<?php else: ?>
<div class="list" id="task-list">
    <?php foreach ($tasks as $t):
        $isDone = (int) $t['is_completed'] === 1;
        $can = $t['can'];
        $overdue = !$isDone && $t['due_date'] && strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59')) < time();
        $edit = [
            'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'due_date' => $t['due_date'],
            'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : '', 'subject_id' => $t['subject_id'], 'project_id' => $t['project_id'], 'assignee_id' => $t['assignee_id'], '_id' => (int) $t['id'], '_can' => $can,
        ];
        ?>
    <div class="item p-<?= e($t['priority']) ?> <?= $isDone ? 'done' : '' ?>" id="task-<?= (int) $t['id'] ?>">
        <button class="tick <?= $isDone ? 'on' : '' ?>" <?= $can['complete'] ? 'data-post="' . e(url('/tasks/' . $t['id'] . '/toggle')) . '"' : 'disabled' ?> title="<?= $isDone ? 'بازگشت به انجام‌نشده' : 'انجام شد' ?>"><i class="fa-solid fa-check"></i></button>
        <div class="grow">
            <div class="item-title" data-open-task="<?= (int) $t['id'] ?>"><?= e($t['title']) ?></div>
            <div class="item-meta">
                <span class="badge <?= e($prio[$t['priority']][1]) ?>"><?= e($prio[$t['priority']][0]) ?></span>
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
            <?php if ($can['edit'] || $can['reassign'] || $can['change_project']): ?><button class="btn btn-sm btn-icon" data-edit-task="<?= e(json_html($edit)) ?>" title="ویرایش"><i class="fa-solid fa-pen"></i></button><?php endif; ?>
            <?php if ($can['delete']): ?><button class="btn btn-sm btn-icon btn-danger" data-post="<?= e(url('/tasks/' . $t['id'] . '/delete')) ?>" data-confirm="این تسک و تمام یادداشت‌ها و فایل‌های آن حذف شود؟" title="حذف"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php $this->partial('pagination', ['pg' => $page, 'pages' => $pages, 'query' => ['scope' => $scope, 'status' => $status, 'q' => $q]]); ?>
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
                <div class="field"><label>توضیحات</label><textarea class="input" name="description" rows="3"></textarea></div>
                <?php if (!$subjects || !$projects): ?>
                <div class="alert warning"><i class="fa-solid fa-triangle-exclamation"></i><div>برای ثبت تسک، موضوع و پروژه اجباری است.
                    <?php if (!$subjects): ?><a href="<?= e(url('/subjects')) ?>">ساخت موضوع</a><?php endif; ?>
                    <?php if (!$subjects && !$projects): ?> و <?php endif; ?>
                    <?php if (!$projects): ?><a href="<?= e(url('/projects')) ?>">ساخت پروژه</a><?php endif; ?></div></div>
                <?php endif; ?>
                <div class="row">
                    <div class="field"><label>اولویت</label><select class="input" name="priority"><option value="low">کم</option><option value="medium" selected>متوسط</option><option value="high">زیاد</option></select></div>
                    <div class="field"><label>موضوع *</label><select class="input" name="subject_id" required><option value="">— انتخاب موضوع —</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach; ?></select></div>
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
