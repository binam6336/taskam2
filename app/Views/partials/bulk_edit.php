<?php
/**
 * «تغییر گروهی» (v7.8) — صفحه‌ی تسک‌ها و بورد: حالت انتخاب (دکمه‌ی [data-bulk-toggle])، نوار پایین و پنجره‌ی تغییر.
 * گزینه‌ها از GET /tasks/bulk-options و اعمال با POST /tasks/bulk (modules/bulk.js).
 */
$this->scripts[] = 'bulk';
?>
<div class="bulk-bar" id="bulk-bar" hidden role="region" aria-label="تغییر گروهی تسک‌ها">
    <span class="bulk-n" aria-live="polite"><b>۰</b> تسک انتخاب شد</span>
    <button type="button" class="btn btn-sm" data-bulk-all><i class="fa-solid fa-check-double" aria-hidden="true"></i> انتخاب همه</button>
    <button type="button" class="btn btn-sm btn-primary" data-bulk-edit disabled><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> تغییر</button>
    <button type="button" class="btn btn-sm btn-ghost" data-bulk-cancel><i class="fa-solid fa-xmark" aria-hidden="true"></i> لغو</button>
</div>
<div class="modal" id="m-bulk">
    <div class="modal-box tf-box">
        <form id="f-bulk" autocomplete="off">
            <div class="modal-head tf-head"><span class="td-chip" aria-hidden="true"><i class="fa-solid fa-pen-to-square"></i></span><div class="td-ttl"><h3>تغییر گروهی</h3><small data-bulk-sub>فقط مواردی که انتخاب کنید عوض می‌شوند</small></div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
            <div class="modal-body tf-body">
                <div class="field" data-bulk-f="status"><label for="bk-status">وضعیت بورد</label><select class="input" id="bk-status" name="status"><option value="">— بدون تغییر —</option></select>
                    <small class="xs faint" data-bulk-status-note hidden>تغییر وضعیت فقط وقتی همه‌ی تسک‌های انتخاب‌شده در یک پروژه‌اند.</small></div>
                <div class="field"><label for="bk-priority">اولویت</label><select class="input" id="bk-priority" name="priority"><option value="">— بدون تغییر —</option><option value="high">زیاد</option><option value="medium">متوسط</option><option value="low">کم</option></select></div>
                <div class="field"><label for="bk-assignee">مسئول انجام</label><select class="input" id="bk-assignee" name="assignee_id"><option value="">— بدون تغییر —</option></select>
                    <small class="xs faint">فقط اعضای مشترک پروژه‌ی همه‌ی تسک‌های انتخاب‌شده.</small></div>
                <div class="field"><label for="bk-label">لیبل</label><select class="input" id="bk-label" name="subject_id"><option value="">— بدون تغییر —</option><option value="__none">بدون لیبل (حذف لیبل)</option></select></div>
                <div class="field"><label for="bk-project">پروژه</label><select class="input" id="bk-project" name="project_id"><option value="">— بدون تغییر —</option></select></div>
                <div class="field"><label>سررسید</label><div class="flex gap-1 items-center wrap"><div class="grow" data-bulk-date></div><label class="check xs"><input type="checkbox" name="clear_due" value="1"> حذف سررسید</label></div></div>
                <div class="bulk-fails" data-bulk-fails hidden></div>
            </div>
            <div class="modal-foot tf-foot"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> اعمال روی <span data-bulk-count>۰</span> تسک</button><button type="button" class="btn" data-close>انصراف</button></div>
        </form>
    </div>
</div>
