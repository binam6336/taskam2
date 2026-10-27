document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().tasks || {};
  const E = TK.esc, F = TK.fa;
  const PR = { low: ['کم', 'green'], medium: ['متوسط', 'yellow'], high: ['زیاد', 'red'] };
  const modal = TK.qs('#m-task'), form = TK.qs('#f-task'), detail = TK.qs('#detail-body');
  if (!modal) return;
  let currentId = null;

  /* انتخابگر مسئول (با آواتار) و پروژه (با لوگو) */
  const mk = (name, options, placeholder, square) => TK.rselect(TK.qs(`[data-rselect="${name}"]`, form), { name, options, placeholder, square });
  const rs = {
    assignee_id: mk('assignee_id', (cfg.assignees || []).map((a) => ({ value: a.id, label: a.name, img: a.avatar })), 'انتخاب مسئول', false),
    project_id: mk('project_id', (cfg.projects || []).map((p) => ({ value: p.id, label: p.title, img: p.image })), 'انتخاب پروژه', true),
  };
  const dp = TK.datepicker(TK.qs('[data-jdate="due_date"]', form), { name: 'due_date', placeholder: 'انتخاب تاریخ (شمسی)' });
  const tp = TK.timepicker(TK.qs('[data-jtime="due_time"]', form), { name: 'due_time', placeholder: 'انتخاب ساعت' });
  const canCreate = (cfg.subjects || []).length > 0 && (cfg.projects || []).length > 0;

  const pk = { due_date: dp, due_time: tp };
  const lock = (names, off) => names.forEach((n) => { if (rs[n]) rs[n].disable(off); else if (pk[n]) pk[n].disable(off); else { const el = form.elements[n]; if (el) el.disabled = off; } });
  const setupForm = (title, action, files) => {
    TK.qs('#task-modal-title').textContent = title;
    form.setAttribute('action', TK.url(action));
    TK.qs('#task-files-field').classList.toggle('hidden', !files);
    lock(['title', 'description', 'priority', 'due_date', 'due_time', 'subject_id', 'project_id', 'assignee_id'], false);
  };

  TK.qs('#btn-new-task')?.addEventListener('click', () => {
    TK.resetForm(form); form.priority.value = 'medium'; form.subject_id.value = '';
    rs.assignee_id.set(cfg.me); rs.project_id.set('');
    dp.set(dp.today()); tp.set('');                 // پیش‌فرض: تاریخ امروز
    setupForm('تسک جدید', '/tasks', true); TK.modal.open(modal);
  });

  TK.on('click', '[data-edit-task]', (e, t) => {
    const d = JSON.parse(t.dataset.editTask);
    TK.resetForm(form);
    setupForm('ویرایش تسک', '/tasks/' + d._id + '/update', false);
    TK.fillForm(form, { title: d.title, description: d.description, priority: d.priority, subject_id: d.subject_id ?? '' });
    dp.set(d.due_date || ''); tp.set(d.due_time || '');
    rs.assignee_id.set(d.assignee_id ?? cfg.me); rs.project_id.set(d.project_id ?? '');
    const c = d._can || {};
    if (!c.edit) lock(['title', 'description', 'priority', 'due_date', 'due_time', 'subject_id'], true);
    if (!c.reassign) lock(['assignee_id'], true);
    if (!c.change_project) lock(['project_id'], true);
    TK.modal.open(modal);
  });

  /* اعتبارسنجی سمت کاربر: موضوع و پروژه اجباری‌اند (قبل از ارسال AJAX) */
  form.addEventListener('submit', (e) => {
    const missing = [];
    if (tp.get() && !dp.get() && !dp.input.disabled) dp.set(dp.today());   // ساعت بدون تاریخ → امروز
    if (!form.subject_id.disabled && !form.subject_id.value) missing.push('موضوع');
    if (!rs.project_id.input.disabled && !rs.project_id.get()) { rs.project_id.invalid(); missing.push('پروژه'); }
    if (!canCreate && form.getAttribute('action') === TK.url('/tasks')) missing.push('موضوع و پروژه (ابتدا بسازید)');
    if (missing.length) { e.preventDefault(); e.stopPropagation(); TK.toast('انتخاب ' + missing.join(' و ') + ' الزامی است.', 'error'); }
  });

  /* ───── جزئیات ───── */
  const fileChip = (f, kind) => `<span class="file-chip"><a href="${E(f.url)}" target="_blank" rel="noopener" class="flex items-center gap-1"><i class="fa-solid ${E(f.icon)}"></i>${E(f.file_name)} <span class="faint xs">${E(f.size_fa)}</span></a>${f.can_delete ? `<button class="btn btn-xs btn-ghost" data-del-file="${kind}/${f.id}" title="حذف"><i class="fa-solid fa-xmark"></i></button>` : ''}</span>`;

  const noteHtml = (n) => `
    <div class="note" id="note-${n.id}">
      <span class="avatar sm">${n.avatar_url ? `<img src="${E(n.avatar_url)}" alt="">` : E(TK.initial(n.author))}</span>
      <div class="grow">
        <div class="flex between gap-2"><b class="small">${E(n.author)}</b><span class="xs faint">${E(n.created_fa)}${n.updated_at ? ' (ویرایش‌شده)' : ''}</span></div>
        <div class="note-body" data-note-text>${E(n.note)}</div>
        <div>${(n.attachments || []).map((f) => fileChip(f, 'note')).join('')}</div>
        <div class="flex gap-1 mt-1">${n.can_edit ? `<button class="btn btn-xs btn-ghost" data-edit-note="${n.id}"><i class="fa-solid fa-pen"></i> ویرایش</button>` : ''}${n.can_delete ? `<button class="btn btn-xs btn-ghost" data-del-note="${n.id}"><i class="fa-solid fa-trash"></i> حذف</button>` : ''}</div>
      </div>
    </div>`;

  const render = (t) => {
    currentId = t.id;
    const [pl, pc] = PR[t.priority] || PR.medium;
    detail.innerHTML = `
      <div class="flex between gap-2 items-center mb-2">
        <h3 class="grow" style="margin:0">${E(t.title)}</h3>
        <button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="flex wrap gap-1 mb-2">
        <span class="badge ${pc}">${pl}</span>
        <span class="badge ${t.is_completed ? 'green' : 'yellow'}">${t.is_completed ? 'انجام‌شده' : 'در جریان'}</span>
        ${t.subject_title ? `<span class="chip">${E(t.subject_title)}</span>` : ''}${t.project_title ? `<span class="chip"><i class="fa-solid fa-diagram-project"></i> ${E(t.project_title)}</span>` : ''}
        ${t.due_fa ? `<span class="chip"><i class="fa-regular fa-calendar"></i> ${E(t.due_fa)}</span>` : ''}
      </div>
      ${t.description ? `<div class="card flat mb-2 note-body">${E(t.description)}</div>` : ''}
      <dl class="kv small mb-2">
        <dt>سازنده</dt><dd>${E(t.creator_name)}</dd><dt>مسئول</dt><dd>${E(t.assignee_name || '—')}</dd>
        <dt>ایجاد</dt><dd>${E(t.created_fa)}</dd>${t.completed_fa ? `<dt>تکمیل</dt><dd>${E(t.completed_fa)}</dd>` : ''}
      </dl>
      <div class="flex gap-1 wrap mb-2">
        ${t.can.complete ? `<button class="btn btn-sm ${t.is_completed ? '' : 'btn-primary'}" data-detail-toggle><i class="fa-solid fa-check"></i> ${t.is_completed ? 'بازگشت به انجام‌نشده' : 'انجام شد'}</button>` : ''}
        <button class="btn btn-sm" data-detail-share><i class="fa-solid fa-link"></i> کپی لینک تسک</button>
      </div>
      <h4><i class="fa-solid fa-paperclip" style="color:var(--g-400)"></i> پیوست‌های تسک</h4>
      <div id="task-files">${t.files.length ? t.files.map((f) => fileChip(f, 'task')).join('') : '<span class="faint small">پیوستی وجود ندارد.</span>'}</div>
      ${t.can.notes ? `<form id="f-task-upload" class="flex gap-1 mt-1 mb-2" enctype="multipart/form-data"><input class="input" type="file" name="attachments[]" multiple required><button class="btn btn-sm"><i class="fa-solid fa-upload"></i> آپلود</button></form>` : ''}
      <hr>
      <h4><i class="fa-regular fa-comments" style="color:var(--g-400)"></i> یادداشت‌ها و گزارش پیشرفت <span class="badge">${F(t.notes.length)}</span></h4>
      <div id="notes">${t.notes.length ? t.notes.map(noteHtml).join('') : '<div class="faint small mb-2">هنوز یادداشتی ثبت نشده است.</div>'}</div>
      ${t.can.notes ? `<form id="f-note" enctype="multipart/form-data"><textarea class="input" name="note" rows="2" placeholder="یادداشت یا گزارش پیشرفت…"></textarea>
        <div class="flex gap-1 mt-1 wrap"><input class="input grow" type="file" name="attachments[]" multiple><button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ثبت یادداشت</button></div></form>` : ''}`;
  };

  const open = async (id) => {
    detail.innerHTML = '<div class="center muted">در حال بارگذاری…</div>';
    TK.modal.open('m-detail');
    const r = await TK.get('/tasks/' + id);
    if (!TK.ok(r)) { TK.modal.close('m-detail'); return TK.toast(r.message || 'خطا', 'error'); }
    render(r.task);
  };
  const refresh = async () => { const r = await TK.get('/tasks/' + currentId); if (TK.ok(r)) render(r.task); };

  TK.on('click', '[data-open-task]', (e, t) => open(t.dataset.openTask));
  if (cfg.shared) { render(cfg.shared); TK.modal.open('m-detail'); }

  const submit = async (f, url, after) => {
    const btn = f.querySelector('button'); TK.busy(btn, true);
    const r = await TK.http(url, { method: 'POST', form: new FormData(f) });
    TK.busy(btn, false);
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) { const errs = r.upload_errors || r.data?.upload_errors; if (errs?.length) TK.toast(errs.join('، '), 'warning', 7000); await after(); TK.reload(); }
  };
  detail.addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.id === 'f-note') submit(e.target, '/tasks/' + currentId + '/notes', refresh);
    if (e.target.id === 'f-task-upload') submit(e.target, '/tasks/' + currentId + '/attachments', refresh);
  });
  detail.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.hasAttribute('data-close')) return TK.modal.close('m-detail');
    if (b.hasAttribute('data-detail-toggle')) { const r = await TK.post('/tasks/' + currentId + '/toggle'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { await refresh(); TK.reload(); } }
    if (b.hasAttribute('data-detail-share')) { const r = await TK.get('/tasks/' + currentId + '/share'); if (TK.ok(r)) TK.copy(r.url); else TK.toast(r.message, 'error'); }
    if (b.dataset.delNote) { if (!(await TK.confirm('این یادداشت و فایل‌های آن حذف شود؟'))) return; const r = await TK.post('/notes/' + b.dataset.delNote + '/delete'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { refresh(); TK.reload(); } }
    if (b.dataset.delFile) { if (!(await TK.confirm('این فایل حذف شود؟'))) return; const r = await TK.post('/files/' + b.dataset.delFile + '/delete'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) refresh(); }
    if (b.dataset.editNote) {
      const box = TK.qs('#note-' + b.dataset.editNote), p = box.querySelector('[data-note-text]'), old = p.textContent;
      p.innerHTML = `<textarea class="input" rows="3">${E(old)}</textarea><div class="flex gap-1 mt-1"><button class="btn btn-sm btn-primary" data-save-note="${b.dataset.editNote}">ذخیره</button><button class="btn btn-sm" data-cancel-note>انصراف</button></div>`;
    }
    if (b.dataset.saveNote) { const ta = b.closest('.note').querySelector('textarea'); const r = await TK.post('/notes/' + b.dataset.saveNote + '/update', { note: ta.value }); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) refresh(); }
    if (b.hasAttribute('data-cancel-note')) refresh();
  });
});
