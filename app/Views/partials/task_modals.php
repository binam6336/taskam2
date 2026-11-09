<?php
/**
 * پنجره‌های تسک (ایجاد/ویرایش و جزئیات) + داده‌ی ماژول tasks.js؛ در صفحه‌ی تسک‌ها و بورد پروژه.
 * @var array $subjects @var array $projects @var array $assignees @var ?array $shared
 * @var string $context (اختیاری) 'board' = داخل برد: دکمه‌ی «نمایش در برد» در جزئیات نیست
 */
$me = \App\Core\Auth::user() ?? [];
$meId = (int) ($me['id'] ?? 0);
// اعضای هر پروژه + اجازه‌ی افزودن عضو/واگذاری برای گروه‌بندی فهرست «مسئول انجام» (اعضای پروژه / همکاران غیرعضو)
$pIds = array_map(static fn($p) => (int) $p['id'], $projects);
$pMembers = [];
if ($pIds) {
    foreach (\App\Core\DB::all('SELECT project_id, user_id FROM project_members WHERE project_id IN (' . \App\Core\DB::in($pIds) . ') UNION SELECT id, user_id FROM projects WHERE id IN (' . \App\Core\DB::in($pIds) . ')', array_merge($pIds, $pIds)) as $m) {
        $pMembers[(int) $m['project_id']][] = (int) $m['user_id'];
    }
}
$this->boot['tasks'] = [
    'subjects' => $subjects,
    'label_colors' => \App\Models\SubjectModel::COLORS,
    'projects' => array_map(static fn($p) => ['id' => pid('projects', $p), 'title' => $p['title'], 'image' => project_image_url($p['profile_image']), 'priority_type' => $p['priority_type'] ?? 'category',
        'members' => $pMembers[(int) $p['id']] ?? [], 'can_add' => !empty($p['is_creator']) || in_array('add_member', $p['perms'] ?? [], true),
        // انتخاب مسئول هنگام ساخت (v5.5): هر عضو جز «بدون دسترسی»؛ تغییر مسئول تسک ثبت‌شده با can.reassign خود تسک قفل می‌شود
        'can_assign' => \App\Services\Access::canAssignOnCreate((int) $p['id'], $meId)], $projects),
    'me_name' => full_name($me),
    'assignees' => $assignees,
    'shared' => $shared ?? null,
    'me' => (int) ($me['id'] ?? 0),
    'context' => $context ?? 'tasks',
];
$this->scripts[] = 'tasks';
?>
<!-- ایجاد / ویرایش -->
<div class="modal" id="m-task" data-guard>
    <div class="modal-box tf-box">
        <form method="post" action="<?= e(url('/tasks')) ?>" enctype="multipart/form-data" data-ajax id="f-task">
            <?= csrf_field() ?>
            <div class="modal-head tf-head"><span class="td-chip" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span><div class="td-ttl"><h3 id="task-modal-title">تسک جدید</h3><small>اطلاعات تسک را وارد کنید</small></div><button type="button" class="btn btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="modal-body tf-body">
                <div class="field" data-tour="title"><label>عنوان <span class="req" aria-hidden="true">*</span></label><input class="input" name="title" maxlength="255" required placeholder="<?= e(ph('task_title')) ?>"></div>
                <div class="field" data-tour="desc"><label>توضیحات</label><textarea class="input" name="description" rows="3" data-linkify placeholder="<?= e(ph('task_desc')) ?>"></textarea></div>
                <?php if (!$projects): ?>
                <div class="alert warning"><i class="fa-solid fa-triangle-exclamation"></i><div>برای ثبت تسک، پروژه اجباری است. <a href="<?= e(url('/projects')) ?>">ساخت پروژه</a></div></div>
                <?php endif; ?>
                <div class="row">
                    <div class="field" data-tour="project"><label>پروژه <span class="req" aria-hidden="true">*</span></label><div data-rselect="project_id"></div></div>
                    <div class="field" data-tour="assignee"><label>مسئول انجام</label><div data-rselect="assignee_id"></div>
                    </div>
                </div>
                <div class="tf-more"><button type="button" class="more-toggle" data-more-toggle data-tour="more" aria-expanded="false" aria-controls="task-more">گزینه‌های بیشتر <i class="fa-solid fa-chevron-down"></i></button></div>
                <div class="task-more" id="task-more" hidden>
                <div class="row">
                    <div class="field" id="prio-cat" data-tour="priority"><label><i class="fa-regular fa-flag"></i> اولویت</label><select class="input" name="priority"><option value="low">کم</option><option value="medium" selected>متوسط</option><option value="high">زیاد</option></select></div>
                    <div class="field hidden" id="prio-pct" data-tour="priority"><label><i class="fa-regular fa-flag"></i> اولویت <b class="prio-pct-val" id="prio-pct-val">۵۰٪</b></label>
                        <input type="range" class="prio-range" name="priority_percent" min="0" max="100" step="5" value="50" disabled aria-label="اولویت درصدی">
                        <div class="prio-scale"><span>کم</span><span>متوسط</span><span>زیاد</span></div></div>
                    <div class="field" data-tour="label"><label><i class="fa-solid fa-tag"></i> لیبل <span class="faint">(اختیاری)</span></label>
                        <div class="subj-pick" id="subj-pick"><select class="input" name="subject_id"><option value="">— بدون لیبل —</option><?php foreach ($subjects as $s): ?><option value="<?= e(pid('subjects', $s)) ?>"><?= e($s['title']) ?></option><?php endforeach; ?><option value="__new">＋ افزودن لیبل جدید</option></select>
                            <div class="subj-new hidden"><input class="input" maxlength="150" placeholder="<?= e(ph('task_label_new')) ?>" aria-label="عنوان لیبل جدید"><button type="button" class="btn btn-primary btn-icon" data-subj-save title="افزودن"><i class="fa-solid fa-check"></i></button><button type="button" class="btn btn-icon" data-subj-cancel title="انصراف"><i class="fa-solid fa-xmark"></i></button></div></div></div>
                </div>
                <div class="field" data-tour="due"><label><i class="fa-regular fa-bell"></i> زمان انجام و یادآوری</label><div data-jdue></div></div>
                <div class="field" id="task-files-field"><label><i class="fa-solid fa-paperclip"></i> پیوست‌ها <span class="faint">(حداکثر ۱۰ مگابایت برای هر فایل)</span></label>
                    <div class="file-pick-row tf-drop"><span class="tf-drop-ic" aria-hidden="true"><i class="fa-solid fa-paperclip"></i></span><span class="file-pick-names small grow" data-file-names data-empty="فایل را انتخاب کنید (می‌توانید چند فایل بردارید)"></span><label class="btn btn-sm file-pick" data-tour="files" title="افزودن فایل" aria-label="افزودن فایل"><i class="fa-solid fa-paperclip"></i> انتخاب فایل<input type="file" name="attachments[]" multiple hidden></label></div></div>
                </div>
            </div>
            <div class="modal-foot tf-foot"><button class="btn btn-primary" type="submit" data-tour="save"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button><button type="button" class="btn" data-close>انصراف</button></div>
        </form>
    </div>
</div>

<!-- جزئیات -->
<div class="modal" id="m-detail"><div class="modal-box lg td-box"><div class="modal-body" id="detail-body"><div class="center muted">در حال بارگذاری…</div></div></div></div>

<!-- تاریخچه‌ی تغییرات -->
<div class="modal" id="m-history"><div class="modal-box"><div class="modal-head"><h3><i class="fa-solid fa-clock-rotate-left"></i> تاریخچه‌ی تسک</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body"><div id="history-body" class="hist"></div></div></div></div>
