document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().tasks || {};
  const E = TK.esc, F = TK.fa;
  const PR = { low: ['کم', 'green'], medium: ['متوسط', 'yellow'], high: ['زیاد', 'red'] };
  const PCT = { low: 25, medium: 50, high: 75 };
  const modal = TK.qs('#m-task'), form = TK.qs('#f-task'), detail = TK.qs('#detail-body');
  if (!modal) return;
  let currentId = null, current = null;

  /* ───── رنگ و نشان اولویت ───── */
  const prioColor = (p) => { p = Math.max(0, Math.min(100, p)); const h = p <= 50 ? 142 - (p / 50) * 97 : 45 - ((p - 50) / 50) * 45; return `hsl(${Math.round(h)}, 85%, 60%)`; };
  const prioBadge = (t) => {
    if (t.priority_type === 'percent') { const p = t.priority_percent ?? PCT[t.priority] ?? 50; return `<span class="badge prio-pct" style="--pc:${prioColor(p)}"><i class="fa-solid fa-signal"></i> ${F(p)}٪</span>`; }
    const [l, c] = PR[t.priority] || PR.medium; return `<span class="badge ${c}">${l}</span>`;
  };

  /* ───── متن غنی: لینک‌های عنوان‌دار [عنوان](url)، لینک خام و منشن ───── */
  const LINK_RE = /\[([^\]\n]{1,200})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>"']+)/g;
  const rich = (text, names = []) => {
    const src = String(text || ''); let out = '', last = 0, m;
    LINK_RE.lastIndex = 0;
    while ((m = LINK_RE.exec(src))) {
      out += E(src.slice(last, m.index));
      const url = m[2] || m[3], label = m[1] || m[3];
      out += `<a href="${E(url)}" target="_blank" rel="noopener noreferrer" class="rich-link" title="${E(url)}"><i class="fa-solid fa-arrow-up-right-from-square"></i>${E(label)}</a>`;
      last = m.index + m[0].length;
    }
    out += E(src.slice(last));
    const list = [...new Set(names.filter(Boolean).map(E))].sort((a, b) => b.length - a.length);
    if (list.length) out = out.replace(new RegExp('@(' + list.map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')', 'g'), '<span class="mention">@$1</span>');
    return out;
  };

  /* ───── انتخابگرها ───── */
  const mk = (name, options, placeholder, square) => TK.rselect(TK.qs(`[data-rselect="${name}"]`, form), { name, options, placeholder, square });
  const rs = {
    assignee_id: mk('assignee_id', (cfg.assignees || []).map((a) => ({ value: a.id, label: a.name, img: a.avatar })), TK.ph('task_assignee', 'انتخاب مسئول'), false),
    project_id: mk('project_id', (cfg.projects || []).map((p) => ({ value: p.id, label: p.title, img: p.image })), TK.ph('task_project', 'انتخاب پروژه'), true),
  };
  const dp = TK.datepicker(TK.qs('[data-jdate="due_date"]', form), { name: 'due_date', placeholder: TK.ph('task_due_date', 'انتخاب تاریخ (شمسی)') });
  const tp = TK.timepicker(TK.qs('[data-jtime="due_time"]', form), { name: 'due_time', placeholder: TK.ph('task_due_time', 'انتخاب ساعت') });
  const canCreate = (cfg.projects || []).length > 0;

  /* ───── اولویت: دسته‌بندی یا درصدی (بر اساس نوع اولویت پروژه) ───── */
  const range = form.priority_percent, rangeVal = TK.qs('#prio-pct-val');
  const paintRange = () => { const v = +range.value; rangeVal.textContent = F(v) + '٪'; rangeVal.style.color = prioColor(v); range.style.setProperty('--pos', v + '%'); };
  range.addEventListener('input', paintRange);
  const projType = (id) => (cfg.projects || []).find((p) => String(p.id) === String(id))?.priority_type || 'category';
  const setPrioMode = (type) => {
    const pct = type === 'percent';
    if (pct && TK.qs('#prio-pct').classList.contains('hidden')) range.value = PCT[form.priority.value] ?? 50;   // کم ۲۵، متوسط ۵۰، زیاد ۷۵
    if (!pct && !TK.qs('#prio-pct').classList.contains('hidden')) form.priority.value = +range.value < 38 ? 'low' : +range.value < 63 ? 'medium' : 'high';
    TK.qs('#prio-pct').classList.toggle('hidden', !pct); TK.qs('#prio-cat').classList.toggle('hidden', pct);
    if (!form.priority.dataset.locked) { range.disabled = !pct; form.priority.disabled = pct; }
    paintRange();
  };
  rs.project_id.input.addEventListener('change', () => setPrioMode(projType(rs.project_id.get())));

  /* ───── افزودن سریع لیبل ───── */
  const subjSel = form.subject_id, subjNew = TK.qs('#subj-pick .subj-new'), subjIn = subjNew.querySelector('input');
  const subjMode = (adding) => { subjSel.classList.toggle('hidden', adding); subjNew.classList.toggle('hidden', !adding); if (adding) { subjIn.value = ''; subjIn.focus(); } };
  subjSel.addEventListener('change', () => { if (subjSel.value === '__new') { subjSel.value = ''; subjMode(true); } });
  const COLORS = cfg.label_colors?.length ? cfg.label_colors : ['#22c55e'];
  const saveSubject = async () => {
    const title = subjIn.value.trim();
    if (!title) { subjIn.focus(); return TK.toast('عنوان لیبل را بنویسید.', 'warning'); }
    const btn = TK.qs('[data-subj-save]', subjNew); TK.busy(btn, true);
    const r = await TK.post('/labels', { title, color: COLORS[Math.floor(Math.random() * COLORS.length)] });
    TK.busy(btn, false);
    if (!TK.ok(r) || !r.id) return TK.toast(r.message || 'خطا در ایجاد لیبل', 'error');
    const opt = new Option(title, r.id);
    subjSel.insertBefore(opt, subjSel.querySelector('option[value="__new"]'));
    (cfg.subjects = cfg.subjects || []).push({ id: r.id, title });
    subjSel.value = String(r.id); subjMode(false);
    TK.toast('لیبل «' + title + '» اضافه شد.', 'success');
  };
  TK.qs('[data-subj-save]', subjNew).addEventListener('click', saveSubject);
  TK.qs('[data-subj-cancel]', subjNew).addEventListener('click', () => subjMode(false));
  subjIn.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); saveSubject(); } if (e.key === 'Escape') { e.stopPropagation(); subjMode(false); } });

  /* ───── لینک در توضیحات: پاپ‌اور ثابت بالای لینک برای گرفتن عنوان ───── */
  const linkify = (ta) => {
    const pop = document.createElement('div');
    pop.className = 'link-pop'; pop.hidden = true;
    pop.innerHTML = `<div class="link-pop-head"><i class="fa-solid fa-link"></i><span>برای این لینک عنوان بگذارید</span></div>
      <div class="link-pop-url ltr"></div>
      <div class="flex gap-1"><input class="input" maxlength="200" placeholder="${TK.esc(TK.ph('task_link_title', 'مثلاً: صفحه‌ی تیکت در میزیتو'))}"><button type="button" class="btn btn-primary btn-sm" data-lp-ok><i class="fa-solid fa-check"></i> ثبت</button></div>
      <button type="button" class="btn btn-ghost btn-xs mt-1" data-lp-skip>بدون عنوان بماند</button>`;
    ta.parentElement.style.position = 'relative';
    ta.parentElement.appendChild(pop);
    const inp = pop.querySelector('input');
    const skipped = new Set();
    let target = null, timer = null;
    // لینک‌های خامی که هنوز عنوان ندارند
    const pending = () => {
      const v = ta.value, out = []; let m;
      const re = /\[[^\]\n]*\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>"'\])]+)/g;
      while ((m = re.exec(v))) if (m[2] && !skipped.has(m[2] + '@' + m.index)) out.push({ url: m[2], start: m.index, end: m.index + m[2].length });
      return out;
    };
    // مختصات یک نویسه داخل textarea (با div آینه)
    const caretXY = (pos) => {
      const cs = getComputedStyle(ta), mir = document.createElement('div');
      ['fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'boxSizing', 'direction', 'textAlign'].forEach((k) => (mir.style[k] = cs[k]));
      Object.assign(mir.style, { position: 'absolute', visibility: 'hidden', whiteSpace: 'pre-wrap', wordWrap: 'break-word', overflowWrap: 'anywhere', width: ta.clientWidth + 'px', top: 0, left: 0 });
      mir.textContent = ta.value.slice(0, pos);
      const mark = document.createElement('span'); mark.textContent = '​'; mir.appendChild(mark);
      ta.parentElement.appendChild(mir);
      const y = mark.offsetTop - ta.scrollTop;
      mir.remove();
      return { y };
    };
    const place = () => {
      const { y } = caretXY(target.start);
      const top = ta.offsetTop + Math.max(0, y) - pop.offsetHeight - 8;
      pop.style.top = top + 'px';
      pop.classList.toggle('below', false);
    };
    const open = (link) => {
      target = link;
      pop.querySelector('.link-pop-url').textContent = link.url;
      pop.hidden = false; inp.value = '';
      place();
      inp.focus();
    };
    const close = () => { pop.hidden = true; target = null; };
    const check = (immediate) => {
      clearTimeout(timer);
      const run = () => {
        if (!pop.hidden) return;
        const caret = ta.selectionStart, list = pending();
        // هنگام تایپ، فقط وقتی کاربر از انتهای لینک رد شده (فاصله/خط جدید) یا مکث کرده
        const link = list.find((l) => immediate || caret > l.end || caret < l.start) || (immediate ? list[0] : list.find((l) => caret === l.end));
        if (link) open(link);
      };
      if (immediate) setTimeout(run, 0); else timer = setTimeout(run, 900);
    };
    ta.addEventListener('paste', () => check(true));
    ta.addEventListener('input', (e) => { if (e.inputType === 'insertFromPaste') return; const ch = ta.value[ta.selectionStart - 1]; check(ch === ' ' || ch === '\n'); });
    const apply = () => {
      const title = inp.value.trim().replace(/[[\]]/g, '');
      if (!title) { inp.focus(); return TK.toast('عنوان لینک را بنویسید یا «بدون عنوان» را بزنید.', 'warning'); }
      const v = ta.value;
      if (v.slice(target.start, target.end) === target.url) {
        const rep = `[${title}](${target.url})`;
        ta.value = v.slice(0, target.start) + rep + v.slice(target.end);
        ta.selectionStart = ta.selectionEnd = target.start + rep.length;
      }
      close(); ta.focus(); check(true);
    };
    pop.querySelector('[data-lp-ok]').addEventListener('click', apply);
    pop.querySelector('[data-lp-skip]').addEventListener('click', () => { skipped.add(target.url + '@' + target.start); close(); ta.focus(); });
    inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); apply(); } if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); skipped.add(target.url + '@' + target.start); close(); ta.focus(); } });
    ta.addEventListener('scroll', () => { if (target) place(); });
    return { reset: () => { skipped.clear(); close(); } };
  };
  const descLinks = linkify(form.description);

  /* ───── فرم ایجاد / ویرایش ───── */
  const pk = { due_date: dp, due_time: tp };
  const lock = (names, off) => names.forEach((n) => {
    if (rs[n]) rs[n].disable(off); else if (pk[n]) pk[n].disable(off);
    else if (n === 'priority') { form.priority.dataset.locked = off ? '1' : ''; form.priority.disabled = off; range.disabled = off; }
    else { const el = form.elements[n]; if (el) el.disabled = off; }
  });
  const setupForm = (title, action, files) => {
    TK.qs('#task-modal-title').textContent = title;
    form.setAttribute('action', TK.url(action));
    TK.qs('#task-files-field').classList.toggle('hidden', !files);
    lock(['title', 'description', 'priority', 'due_date', 'due_time', 'subject_id', 'project_id', 'assignee_id'], false);
    subjMode(false); descLinks.reset();
  };

  // بدون دسترسی «تکمیل تسک»: دکمه دیده می‌شود ولی فقط پیام می‌دهد
  TK.on('click', '[data-no-complete]', (e) => { e.preventDefault(); e.stopPropagation(); TK.toast('شما دسترسی لازم برای تکمیل تسک را ندارید.', 'error'); });

  TK.qs('#btn-new-task')?.addEventListener('click', () => {
    TK.resetForm(form); form.priority.value = 'medium'; form.subject_id.value = '';
    // پروژه‌ی پیش‌فرض: فیلتر پروژه‌ی فعلی، یا تنها پروژه‌ی کاربر — تا نوع اولویت (دسته‌ای/درصدی) از همان اول درست باشد
    const fp = new URLSearchParams(location.search).get('project'), ps = cfg.projects || [];
    const pid = ps.some((p) => String(p.id) === fp) ? fp : ps.length === 1 ? String(ps[0].id) : '';
    rs.assignee_id.set(cfg.me); rs.project_id.set(pid);
    dp.set(dp.today()); tp.set('');                 // پیش‌فرض: تاریخ امروز
    setupForm('تسک جدید', '/tasks', true);
    TK.qs('#prio-pct').classList.add('hidden'); setPrioMode(projType(pid));
    TK.modal.open(modal);
  });

  const openEdit = (d) => {
    TK.resetForm(form);
    setupForm('ویرایش تسک', '/tasks/' + d._id + '/update', false);
    TK.fillForm(form, { title: d.title, description: d.description, priority: d.priority, subject_id: d.subject_id ?? '' });
    dp.set(d.due_date || ''); tp.set(d.due_time || '');
    rs.assignee_id.set(d.assignee_id ?? cfg.me); rs.project_id.set(d.project_id ?? '');
    TK.qs('#prio-pct').classList.add('hidden'); setPrioMode(projType(d.project_id));
    if (projType(d.project_id) === 'percent') { range.value = d.priority_percent ?? PCT[d.priority] ?? 50; paintRange(); }
    const c = d._can || {};
    // هر گروه فیلد با دسترسی خودش: اطلاعات (عنوان/توضیحات)، زمان انجام، اولویت/لیبل (فقط سازنده)
    if (!c.edit_info) lock(['title', 'description'], true);
    if (!c.edit_due) lock(['due_date', 'due_time'], true);
    if (!c.edit_meta) lock(['priority', 'subject_id'], true);
    if (!c.reassign) lock(['assignee_id'], true);
    if (!c.change_project) lock(['project_id'], true);
    TK.modal.open(modal);
  };
  TK.on('click', '[data-edit-task]', (e, t) => openEdit(JSON.parse(t.dataset.editTask)));

  /* اعتبارسنجی سمت کاربر: لیبل و پروژه اجباری‌اند (قبل از ارسال AJAX) */
  form.addEventListener('submit', (e) => {
    const missing = [];
    if (tp.get() && !dp.get() && !dp.input.disabled) dp.set(dp.today());   // ساعت بدون تاریخ → امروز
    if (!subjNew.classList.contains('hidden')) { e.preventDefault(); e.stopPropagation(); return TK.toast('لیبل جدید را با دکمه‌ی تیک ثبت کنید یا انصراف دهید.', 'warning'); }
    if (!form.subject_id.disabled && !form.subject_id.value) missing.push('لیبل');
    if (!rs.project_id.input.disabled && !rs.project_id.get()) { rs.project_id.invalid(); missing.push('پروژه'); }
    if (!canCreate && form.getAttribute('action') === TK.url('/tasks')) missing.push('پروژه (ابتدا بسازید)');
    if (missing.length) { e.preventDefault(); e.stopPropagation(); TK.toast('انتخاب ' + missing.join(' و ') + ' الزامی است.', 'error'); }
  });

  /* ───── فیلتر و مرتب‌سازی ───── */
  const tb = TK.qs('#t-toolbar');
  if (tb) {
    const setHidden = (name, v) => { let i = tb.querySelector(`input[type=hidden][name="${name}"]`); if (!v) { i?.remove(); return; } if (!i) { i = document.createElement('input'); i.type = 'hidden'; i.name = name; tb.appendChild(i); } i.value = v; };
    TK.qs('[data-f-apply]', tb)?.addEventListener('click', () => { TK.qsa('[data-f]', tb).forEach((s) => setHidden(s.dataset.f, s.value)); tb.submit(); });
    TK.qs('[data-f-clear]', tb)?.addEventListener('click', () => { ['when', 'prio', 'project'].forEach((n) => setHidden(n, '')); tb.submit(); });
  }

  /* ───── جزئیات ───── */
  const fileChip = (f, kind) => `<span class="file-chip"><a href="${E(f.url)}" target="_blank" rel="noopener" class="flex items-center gap-1"><i class="fa-solid ${E(f.icon)}"></i>${E(f.file_name)} <span class="faint xs">${E(f.size_fa)}</span></a>${f.can_delete ? `<button class="btn btn-xs btn-ghost" data-del-file="${kind}/${f.id}" title="حذف"><i class="fa-solid fa-xmark"></i></button>` : ''}</span>`;
  const names = () => [...(current?.mentionable || []).map((m) => m.name), cfg.me_name, current?.creator_name, current?.assignee_name];

  const noteHtml = (n) => `
    <div class="note" id="note-${n.id}">
      <span class="avatar sm">${n.avatar_url ? `<img src="${E(n.avatar_url)}" alt="">` : E(TK.initial(n.author))}</span>
      <div class="grow">
        <div class="flex between gap-2"><b class="small">${E(n.author)}</b><span class="xs faint">${E(n.created_fa)}${n.updated_at ? ' (ویرایش‌شده)' : ''}</span></div>
        <div class="note-body" data-note-text data-raw="${E(n.note)}">${rich(n.note, names())}</div>
        <div>${(n.attachments || []).map((f) => fileChip(f, 'note')).join('')}</div>
        <div class="flex gap-1 mt-1">${n.can_edit ? `<button class="btn btn-xs btn-ghost" data-edit-note="${n.id}"><i class="fa-solid fa-pen"></i> ویرایش</button>` : ''}${n.can_delete ? `<button class="btn btn-xs btn-ghost" data-del-note="${n.id}"><i class="fa-solid fa-trash"></i> حذف</button>` : ''}</div>
      </div>
    </div>`;

  const editPayload = (t) => ({ title: t.title, description: t.description, priority: t.priority, priority_percent: t.priority_percent, due_date: t.due_date, due_time: t.due_time, subject_id: t.subject_id, project_id: t.project_id, assignee_id: t.assignee_id, _id: t.id, _can: t.can });

  const render = (t) => {
    currentId = t.id; current = t;
    const canEdit = t.can.edit;
    detail.innerHTML = `
      <div class="flex between gap-2 items-center mb-2">
        <h3 class="grow" style="margin:0">${E(t.title)}</h3>
        ${t.on_board ? `<a class="btn btn-ghost btn-icon btn-sm" href="${TK.url('/board?task=' + t.id)}" title="نمایش در برد تیمی" aria-label="نمایش در برد تیمی"><i class="fa-solid fa-table-columns"></i></a>` : ''}
        ${canEdit ? '<button class="btn btn-ghost btn-icon btn-sm" data-detail-edit title="ویرایش تسک" aria-label="ویرایش تسک"><i class="fa-solid fa-pen"></i></button>' : ''}
        <button class="btn btn-ghost btn-icon btn-sm" data-close title="بستن" aria-label="بستن"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="flex wrap gap-1 mb-2">
        ${prioBadge(t)}
        <span class="badge ${t.is_completed ? 'green' : 'yellow'}">${t.is_completed ? 'انجام‌شده' : 'در جریان'}</span>
        ${t.subject_title ? `<span class="chip" title="لیبل"><i class="fa-solid fa-tag" style="color:${E(t.subject_color || 'var(--muted)')}"></i> ${E(t.subject_title)}</span>` : ''}${t.project_title ? `<span class="chip"><i class="fa-solid fa-diagram-project"></i> ${E(t.project_title)}</span>` : ''}
        ${t.due_fa ? `<span class="chip"><i class="fa-regular fa-calendar"></i> ${E(t.due_fa)}</span>` : ''}
      </div>
      ${t.description ? `<div class="card flat mb-2 note-body">${rich(t.description)}</div>` : ''}
      <dl class="kv small mb-2">
        <dt>سازنده</dt><dd>${E(t.creator_name)}</dd><dt>مسئول</dt><dd>${E(t.assignee_name || '—')}</dd>
        <dt>ایجاد</dt><dd>${E(t.created_fa)}</dd>${t.completed_fa ? `<dt>تکمیل</dt><dd>${E(t.completed_fa)}</dd>` : ''}
      </dl>
      <div class="flex gap-1 wrap mb-2">
        ${t.can.complete ? `<button class="btn btn-sm ${t.is_completed ? '' : 'btn-primary'}" data-detail-toggle><i class="fa-solid fa-check"></i> ${t.is_completed ? 'بازگشت به انجام‌نشده' : 'انجام شد'}</button>`
          : `<button class="btn btn-sm locked" data-no-complete aria-disabled="true"><i class="fa-solid fa-lock"></i> ${t.is_completed ? 'بازگشت به انجام‌نشده' : 'انجام شد'}</button>`}
        <button class="btn btn-sm" data-detail-share><i class="fa-solid fa-link"></i> کپی لینک تسک</button>
      </div>
      <h4><i class="fa-solid fa-paperclip" style="color:var(--g-400)"></i> پیوست‌های تسک</h4>
      <div id="task-files">${t.files.length ? t.files.map((f) => fileChip(f, 'task')).join('') : '<span class="faint small">پیوستی وجود ندارد.</span>'}</div>
      ${t.can.edit_info ? `<form id="f-task-upload" class="flex gap-1 mt-1 mb-2" enctype="multipart/form-data"><input class="input" type="file" name="attachments[]" multiple required><button class="btn btn-sm"><i class="fa-solid fa-upload"></i> آپلود</button></form>` : ''}
      <hr>
      <h4><i class="fa-regular fa-comments" style="color:var(--g-400)"></i> یادداشت‌ها و گزارش پیشرفت <span class="badge">${F(t.notes.length)}</span> <span class="xs faint">(جدیدترین بالا)</span></h4>
      ${t.can.notes ? `<form id="f-note" class="mb-2" enctype="multipart/form-data"><textarea class="input" name="note" rows="2" placeholder="${E(TK.ph('task_note', 'یادداشت یا گزارش پیشرفت… (برای منشن از دکمه‌ی @ استفاده کنید)'))}"></textarea>
        <div class="flex gap-1 mt-1 wrap items-center"><input class="input grow" type="file" name="attachments[]" multiple>
          <div class="dropdown mention-dd"><button type="button" class="btn btn-icon mention-btn" data-mention title="منشن کردن اعضای پروژه" aria-label="منشن"><i class="fa-solid fa-at"></i></button>
            <div class="dropdown-menu mention-menu">${(t.mentionable || []).length ? `<div class="xs faint" style="padding:.2rem .5rem .4rem">اعضای پروژه‌ی «${E(t.project_title || '')}»</div>` + t.mentionable.map((m) => `<button type="button" data-mention-id="${m.id}" data-mention-name="${E(m.name)}"><span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span>${E(m.name)}</button>`).join('') : '<div class="faint small" style="padding:.5rem">عضو دیگری در پروژه‌ی این تسک نیست.</div>'}</div></div>
          <button class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> ثبت یادداشت</button></div></form>` : ''}
      <div id="notes">${t.notes.length ? t.notes.map(noteHtml).join('') : '<div class="faint small mb-2">هنوز یادداشتی ثبت نشده است.</div>'}</div>`;
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
    const btn = f.querySelector('button.btn-primary') || f.querySelector('button'); TK.busy(btn, true);
    const fd = new FormData(f);
    if (f.id === 'f-note') {
      const text = String(fd.get('note') || '');
      [...mentioned].filter(([, n]) => text.includes('@' + n)).forEach(([id]) => fd.append('mentions[]', id));
    }
    const r = await TK.http(url, { method: 'POST', form: fd });
    TK.busy(btn, false);
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) { mentioned.clear(); const errs = r.upload_errors || r.data?.upload_errors; if (errs?.length) TK.toast(errs.join('، '), 'warning', 7000); await after(); TK.reload(); }
  };

  /* منشن: درج «@نام» در محل نشانگر */
  const mentioned = new Map();
  const insertMention = (id, name) => {
    const ta = TK.qs('#f-note textarea', detail); if (!ta) return;
    const s = ta.selectionStart ?? ta.value.length, e = ta.selectionEnd ?? s;
    const before = ta.value.slice(0, s), pre = before && !/\s$/.test(before) ? ' ' : '';
    const ins = `${pre}@${name} `;
    ta.value = before + ins + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + ins.length; ta.focus();
    mentioned.set(String(id), name);
  };

  detail.addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.id === 'f-note') submit(e.target, '/tasks/' + currentId + '/notes', refresh);
    if (e.target.id === 'f-task-upload') submit(e.target, '/tasks/' + currentId + '/attachments', refresh);
  });
  detail.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.hasAttribute('data-close')) return TK.modal.close('m-detail');
    if (b.hasAttribute('data-detail-edit')) { TK.modal.close('m-detail'); return openEdit(editPayload(current)); }
    if (b.hasAttribute('data-mention')) { e.stopPropagation(); b.parentElement.classList.toggle('open'); return; }
    if (b.dataset.mentionId) { b.closest('.dropdown').classList.remove('open'); return insertMention(b.dataset.mentionId, b.dataset.mentionName); }
    if (b.hasAttribute('data-detail-toggle')) { const r = await TK.post('/tasks/' + currentId + '/toggle'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { await refresh(); TK.reload(); } }
    if (b.hasAttribute('data-detail-share')) { const r = await TK.get('/tasks/' + currentId + '/share'); if (TK.ok(r)) TK.copy(r.url); else TK.toast(r.message, 'error'); }
    if (b.dataset.delNote) { if (!(await TK.confirm('این یادداشت و فایل‌های آن حذف شود؟'))) return; const r = await TK.post('/notes/' + b.dataset.delNote + '/delete'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { refresh(); TK.reload(); } }
    if (b.dataset.delFile) { if (!(await TK.confirm('این فایل حذف شود؟'))) return; const r = await TK.post('/files/' + b.dataset.delFile + '/delete'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) refresh(); }
    if (b.dataset.editNote) {
      const box = TK.qs('#note-' + b.dataset.editNote), p = box.querySelector('[data-note-text]'), old = p.dataset.raw;
      p.innerHTML = `<textarea class="input" rows="3">${E(old)}</textarea><div class="flex gap-1 mt-1"><button class="btn btn-sm btn-primary" data-save-note="${b.dataset.editNote}">ذخیره</button><button class="btn btn-sm" data-cancel-note>انصراف</button></div>`;
    }
    if (b.dataset.saveNote) { const ta = b.closest('.note').querySelector('textarea'); const r = await TK.post('/notes/' + b.dataset.saveNote + '/update', { note: ta.value }); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) refresh(); }
    if (b.hasAttribute('data-cancel-note')) refresh();
  });
});
