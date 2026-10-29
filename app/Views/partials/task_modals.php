<?php
/**
 * پنجره‌های تسک (ایجاد/ویرایش و جزئیات) + داده‌ی ماژول tasks.js؛ در صفحه‌ی تسک‌ها و برد تیمی.
 * @var array $subjects @var array $projects @var array $assignees @var ?array $shared
 * @var string $context (اختیاری) 'board' = داخل برد: دکمه‌ی «نمایش در برد» در جزئیات نیست
 */
$me = \App\Core\Auth::user() ?? [];
$this->boot['tasks'] = [
    'subjects' => $subjects,
    'label_colors' => \App\Models\SubjectModel::COLORS,
    'projects' => array_map(static fn($p) => ['id' => (int) $p['id'], 'title' => $p['title'], 'image' => project_image_url($p['profile_image']), 'priority_type' => $p['priority_type'] ?? 'category'], $projects),
    'me_name' => full_name($me),
    'assignees' => $assignees,
    'shared' => $shared ?? null,
    'me' => (int) ($me['id'] ?? 0),
    'context' => $context ?? 'tasks',
];
$this->scripts[] = 'tasks';
?>
<!-- ایجاد / ویرایش -->
<div class="modal" id="m-task">
    <div class="modal-box">
        <form method="post" action="<?= e(url('/tasks')) ?>" enctype="multipart/form-data" data-ajax id="f-task">
            <?= csrf_field() ?>
            <div class="modal-head"><h3><i class="fa-solid fa-list-check"></i> <span id="task-modal-title">تسک جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
            <div class="modal-body">
                <div class="field" data-tour="title"><label>عنوان *</label><input class="input" name="title" maxlength="255" required></div>
                <div class="field" data-tour="desc"><label>توضیحات</label><textarea class="input" name="description" rows="3" data-linkify placeholder="<?= e(ph('task_desc')) ?>"></textarea></div>
                <?php if (!$projects): ?>
                <div class="alert warning"><i class="fa-solid fa-triangle-exclamation"></i><div>برای ثبت تسک، پروژه اجباری است. <a href="<?= e(url('/projects')) ?>">ساخت پروژه</a></div></div>
                <?php endif; ?>
                <div class="row">
                    <div class="field" data-tour="project"><label>پروژه *</label><div data-rselect="project_id"></div></div>
                    <div class="field" data-tour="assignee"><label>مسئول انجام</label><div data-rselect="assignee_id"></div></div>
                </div>
                <div class="row">
                    <div class="field" id="prio-cat" data-tour="priority"><label>اولویت</label><select class="input" name="priority"><option value="low">کم</option><option value="medium" selected>متوسط</option><option value="high">زیاد</option></select></div>
                    <div class="field hidden" id="prio-pct" data-tour="priority"><label>اولویت <b class="prio-pct-val" id="prio-pct-val">۵۰٪</b></label>
                        <input type="range" class="prio-range" name="priority_percent" min="0" max="100" step="5" value="50" disabled aria-label="اولویت درصدی">
                        <div class="prio-scale"><span>کم</span><span>متوسط</span><span>زیاد</span></div></div>
                    <div class="field" data-tour="label"><label><i class="fa-solid fa-tag"></i> لیبل *</label>
                        <div class="subj-pick" id="subj-pick"><select class="input" name="subject_id" required><option value="">— انتخاب لیبل —</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['title']) ?></option><?php endforeach; ?><option value="__new">＋ افزودن لیبل جدید</option></select>
                            <div class="subj-new hidden"><input class="input" maxlength="150" placeholder="<?= e(ph('task_label_new')) ?>" aria-label="عنوان لیبل جدید"><button type="button" class="btn btn-primary btn-icon" data-subj-save title="افزودن"><i class="fa-solid fa-check"></i></button><button type="button" class="btn btn-icon" data-subj-cancel title="انصراف"><i class="fa-solid fa-xmark"></i></button></div></div></div>
                </div>
                <div class="row">
                    <div class="field" data-tour="due"><label>تاریخ سررسید</label><div data-jdate="due_date"></div></div>
                    <div class="field" data-tour="time"><label>ساعت سررسید</label><div data-jtime="due_time"></div></div>
                </div>
                <div class="field" id="task-files-field" data-tour="files"><label>پیوست‌ها <span class="faint">(حداکثر ۱۰ مگابایت برای هر فایل)</span></label><input class="input" type="file" name="attachments[]" multiple></div>
            </div>
            <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary" type="submit" data-tour="save"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div>
        </form>
    </div>
</div>

<!-- جزئیات -->
<div class="modal" id="m-detail"><div class="modal-box lg"><div class="modal-body" id="detail-body"><div class="center muted">در حال بارگذاری…</div></div></div></div>
