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
    if (t.priority_type === 'percent') { const p = t.priority_percent ?? PCT[t.priority] ?? 50; return `<span class="badge prio-pct" data-css="--pc:${prioColor(p)}"><i class="fa-solid fa-signal"></i> ${F(p)}٪</span>`; }
    const [l, c] = PR[t.priority] || PR.medium; return `<span class="badge ${c}">${l}</span>`;
  };

  /* ───── متن غنی: لینک‌های عنوان‌دار [عنوان](url)، لینک خام و منشن ───── */
  const LINK_RE = /\[([^\]\n]{1,200})\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s<>"']+)/g;
  const rich = (text, names = []) => {
    const src = String(text || ''); let out = '', last = 0, m;
    // منشن فقط روی بخش‌های متنی (نه داخل HTML لینک‌های ساخته‌شده) اعمال می‌شود
    const list = [...new Set(names.filter(Boolean).map(E))].sort((a, b) => b.length - a.length);
    const mentionRe = list.length ? new RegExp('@(' + list.map((n) => n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + ')', 'g') : null;
    const txt = (s) => (mentionRe ? E(s).replace(mentionRe, (m0, n) => `<span class="mention${n === 'کارلیکس' ? ' mention-ai' : ''}">@${n}</span>`) : E(s));
    LINK_RE.lastIndex = 0;
    while ((m = LINK_RE.exec(src))) {
      out += txt(src.slice(last, m.index));
      const url = m[2] || m[3], label = m[1] || m[3];
      out += `<a href="${E(url)}" target="_blank" rel="noopener noreferrer" class="rich-link" title="${E(url)}"><i class="fa-solid fa-arrow-up-right-from-square"></i>${E(label)}</a>`;
      last = m.index + m[0].length;
    }
    out += txt(src.slice(last));
    return out;
  };

  /* ───── انتخابگرها ───── */
  const mk = (name, options, placeholder, square) => TK.rselect(TK.qs(`[data-rselect="${name}"]`, form), { name, options, placeholder, square });
  const rs = {
    assignee_id: mk('assignee_id', (cfg.assignees || []).map((a) => ({ value: a.id, label: a.name, img: a.avatar })), TK.ph('task_assignee', 'انتخاب مسئول'), false),
    project_id: mk('project_id', (cfg.projects || []).map((p) => ({ value: p.id, label: p.title, img: p.image })), TK.ph('task_project', 'انتخاب پروژه'), true),
  };
  // تاریخ و ساعت سررسید در یک فیلد (امروز/فردا/پس‌فردا با ساعت‌های آماده + انتخاب دلخواه)
  const due = TK.duepicker(TK.qs('[data-jdue]', form), { placeholder: TK.ph('task_due', 'تعیین زمان انجام (اختیاری)'), recurrence: true, reminders: true });
  const dp = due.date, tp = due.time;
  const canCreate = (cfg.projects || []).length > 0;

  /* ───── اولویت: دسته‌بندی یا درصدی (بر اساس نوع اولویت پروژه) ───── */
  const range = form.priority_percent, rangeVal = TK.qs('#prio-pct-val');
  const paintRange = () => { const v = +range.value; rangeVal.textContent = F(v) + '٪'; rangeVal.style.color = prioColor(v); range.style.setProperty('--pos', v + '%'); };
  range.addEventListener('input', paintRange);
  /* «مسئول انجام» بر اساس پروژه: «اعضای این پروژه» و «همکاران غیرعضو» (با گزینه‌ی افزودن به پروژه و واگذاری) */
  const projOf = (id) => (cfg.projects || []).find((p) => String(p.id) === String(id)) || null;
  const assigneeOpts = (pid) => {
    const all = (cfg.assignees || []).map((a) => ({ value: a.id, label: a.name, img: a.avatar }));
    const p = projOf(pid); if (!p) return all;
    const mem = all.filter((a) => a.value === cfg.me || (p.members || []).includes(a.value));
    const others = all.filter((a) => !mem.includes(a));
    const why = !p.can_add ? 'برای افزودن این همکار به پروژه دسترسی «افزودن کاربر» لازم است؛ با مدیر پروژه هماهنگ کنید.'
      : !p.can_assign ? 'نقش شما در این پروژه «بدون دسترسی» است؛ فقط برای خودتان تسک می‌سازید.' : '';
    const memOpts = mem.map((a) => (a.value === cfg.me || p.can_assign ? a : { ...a, disabled: true, hint: 'نقش شما در این پروژه «بدون دسترسی» است؛ فقط برای خودتان تسک می‌سازید.' }));
    const out = [{ group: 'اعضای این پروژه' }, ...memOpts];
    if (others.length) out.push({ group: 'همکاران غیرعضو' }, ...others.map((a) => ({ ...a, note: why ? 'عضو پروژه نیست' : 'افزودن به پروژه و واگذاری', addTo: p.id, disabled: !!why, hint: why })));
    return out;
  };
  const projType = (id) => (cfg.projects || []).find((p) => String(p.id) === String(id))?.priority_type || 'category';
  const setPrioMode = (type) => {
    const pct = type === 'percent';
    if (pct && TK.qs('#prio-pct').classList.contains('hidden')) range.value = PCT[form.priority.value] ?? 50;   // کم ۲۵، متوسط ۵۰، زیاد ۷۵
    if (!pct && !TK.qs('#prio-pct').classList.contains('hidden')) form.priority.value = +range.value < 38 ? 'low' : +range.value < 63 ? 'medium' : 'high';
    TK.qs('#prio-pct').classList.toggle('hidden', !pct); TK.qs('#prio-cat').classList.toggle('hidden', pct);
    if (!form.priority.dataset.locked) { range.disabled = !pct; form.priority.disabled = pct; }
    paintRange();
  };
  /* ───── لیبل‌ها مال پروژه‌اند (v7.12): فقط لیبل‌های پروژه‌ی انتخاب‌شده؛ «افزودن لیبل جدید» فقط با «دسترسی ایجاد لیبل» ───── */
  const subjSel = form.subject_id, subjNew = TK.qs('#subj-pick .subj-new'), subjIn = subjNew.querySelector('input');
  const subjNewOpt = subjSel.querySelector('option[value="__new"]');
  const renderSubj = (projectId, keep) => {
    const cur = keep !== undefined ? String(keep ?? '') : subjSel.value;
    TK.qsa('option[data-project]', subjSel).forEach((o) => o.remove());
    (cfg.subjects || []).filter((x) => projectId && String(x.project_id) === String(projectId)).forEach((x) => {
      const o = new Option(x.title, x.id); o.dataset.project = x.project_id; subjSel.insertBefore(o, subjNewOpt);
    });
    subjNewOpt.hidden = subjNewOpt.disabled = !projOf(projectId)?.can_label;
    subjSel.value = [...subjSel.options].some((o) => o.value === cur && o.value !== '__new') ? cur : '';
  };
  renderSubj('');
  rs.project_id.input.addEventListener('change', () => { setPrioMode(projType(rs.project_id.get())); rs.assignee_id.setOptions(assigneeOpts(rs.project_id.get())); renderSubj(rs.project_id.get()); });

  /* ───── افزودن سریع لیبل (به پروژه‌ی انتخاب‌شده) ───── */
  const subjMode = (adding) => { subjSel.classList.toggle('hidden', adding); subjNew.classList.toggle('hidden', !adding); if (adding) { subjIn.value = ''; subjIn.focus(); } };
  subjSel.addEventListener('change', () => { if (subjSel.value === '__new') { subjSel.value = ''; subjMode(true); } });
  const COLORS = cfg.label_colors?.length ? cfg.label_colors : ['#22c55e'];
  const saveSubject = async () => {
    const title = subjIn.value.trim();
    if (!title) { subjIn.focus(); return TK.toast('عنوان لیبل را بنویسید.', 'warning'); }
    const btn = TK.qs('[data-subj-save]', subjNew); TK.busy(btn, true);
    const project = rs.project_id.get();
    if (!project) { TK.busy(btn, false); return TK.toast('اول پروژه‌ی تسک را انتخاب کنید؛ لیبل مال همان پروژه ساخته می‌شود.', 'warning'); }
    const r = await TK.post('/labels', { title, project_id: project, color: COLORS[Math.floor(Math.random() * COLORS.length)] });
    TK.busy(btn, false);
    if (!TK.ok(r) || !r.id) return TK.toast(r.message || 'خطا در ایجاد لیبل', 'error');
    (cfg.subjects = cfg.subjects || []).push({ id: r.id, title, color: r.color, project_id: project });
    renderSubj(project, r.id); subjMode(false);
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
  // گزینه‌های بیشتر (اولویت، لیبل، سررسید، پیوست): پیش‌فرض بسته؛ با کلیک روی فلش/متن باز می‌شود
  const moreBtn = TK.qs('[data-more-toggle]', form), moreBox = TK.qs('#task-more');
  const setMore = (on) => { moreBox.hidden = !on; moreBtn.setAttribute('aria-expanded', on ? 'true' : 'false'); moreBtn.classList.toggle('open', on); };
  // v7.16 (دمو): Ctrl/⌘+Enter فرم تسک را ثبت می‌کند؛ همان میان‌بر در یادداشت تسک
  form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); } });
  document.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.matches?.('#f-note textarea')) { e.preventDefault(); e.target.form.requestSubmit(); } });
  moreBtn.addEventListener('click', () => setMore(moreBox.hidden));
  const setupForm = (title, action, files) => {
    TK.qs('#task-modal-title').textContent = title;
    form.setAttribute('action', TK.url(action));
    TK.qs('#task-files-field').classList.toggle('hidden', !files);
    lock(['title', 'description', 'priority', 'due_date', 'due_time', 'subject_id', 'project_id', 'assignee_id'], false);
    subjMode(false); descLinks.reset();
  };

  // سرگروه‌های تاشوی لیست (v5.7): وضعیت هر گروه در localStorage؛ پیش‌فرض از data-tgh-default (منتظر تأیید = بسته)
  // delegation روی document + اعمال دوباره بعد از هر به‌روزرسانی AJAX (tk:live) — فقط وقتی #task-list هست
  const grpStore = {
    get(k) { try { return localStorage.getItem('tk.tgrp.' + k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem('tk.tgrp.' + k, v); } catch { /* حالت خصوصی */ } },
  };
  const grpSet = (b, open) => {
    b.setAttribute('aria-expanded', String(open));
    const body = document.getElementById(b.getAttribute('aria-controls')); if (body) body.hidden = !open;
  };
  const grpApply = () => {
    if (!TK.qs('#task-list')) return;
    TK.qsa('#task-list [data-tgh]').forEach((b) => { const v = grpStore.get(b.dataset.tgh); grpSet(b, (v ?? b.dataset.tghDefault) === '1'); });
  };
  grpApply();
  document.addEventListener('tk:live', grpApply);
  TK.on('click', '#task-list [data-tgh]', (e, b) => {
    const open = b.getAttribute('aria-expanded') !== 'true';
    grpSet(b, open); grpStore.set(b.dataset.tgh, open ? '1' : '0');
  });
  // بدون دسترسی «تکمیل تسک»: دکمه دیده می‌شود ولی فقط پیام می‌دهد
  TK.on('click', '[data-no-complete]', (e) => { e.preventDefault(); e.stopPropagation(); TK.toast('شما دسترسی لازم برای تکمیل تسک را ندارید.', 'error'); });

  const openNew = (opts = {}) => {
    TK.resetForm(form); form.priority.value = 'medium'; form.subject_id.value = '';
    // پروژه‌ی پیش‌فرض: فیلتر پروژه‌ی فعلی، یا تنها پروژه‌ی کاربر — تا نوع اولویت (دسته‌ای/درصدی) از همان اول درست باشد
    const fp = new URLSearchParams(location.search).get('project'), ps = cfg.projects || [];
    const pid = ps.some((p) => String(p.id) === fp) ? fp : ps.length === 1 ? String(ps[0].id) : '';
    rs.project_id.set(pid); rs.assignee_id.setOptions(assigneeOpts(pid)); rs.assignee_id.set(cfg.me); renderSubj(pid, '');
    dp.set(opts.date || ''); tp.set(''); due.recurrence.set(null); due.reminders.set(null);     // پیش‌فرض: بدون تاریخ (در تقویم: روز انتخاب‌شده)
    setupForm('تسک جدید', '/tasks', true); setMore(false);
    TK.qs('#prio-pct').classList.add('hidden'); setPrioMode(projType(pid));
    TK.modal.open(modal);
  };
  TK.qs('#btn-new-task')?.addEventListener('click', () => openNew());
  // موبایل: منوی ⋮ دکمه‌های هر کارت (جزئیات/ویرایش/حذف)
  const closeMore = (except) => TK.qsa('.item-actions.open').forEach((a) => { if (a !== except) { a.classList.remove('open'); a.querySelector('[data-item-more]')?.setAttribute('aria-expanded', 'false'); } });
  TK.on('click', '[data-item-more]', (e, b) => { e.stopPropagation(); const a = b.closest('.item-actions'); closeMore(a); const on = a.classList.toggle('open'); b.setAttribute('aria-expanded', on ? 'true' : 'false'); });
  document.addEventListener('click', (e) => { if (!e.target.closest('[data-item-more]')) closeMore(); });

  const openEdit = (d) => {
    TK.resetForm(form);
    setupForm('ویرایش تسک', '/tasks/' + d._id + '/update', false); setMore(true);
    TK.fillForm(form, { title: d.title, description: d.description, priority: d.priority, subject_id: d.subject_id ?? '' });
    dp.set(d.due_date || ''); tp.set(d.due_time || ''); due.recurrence.set(d.recurrence); due.reminders.set(d);
    rs.project_id.set(d.project_id ?? ''); rs.assignee_id.setOptions(assigneeOpts(d.project_id ?? '')); renderSubj(d.project_id ?? '', d.subject_id ?? '');
    rs.assignee_id.set(d.assignee_id ?? cfg.me);
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

  /* اعتبارسنجی سمت کاربر: پروژه اجباری است، لیبل اختیاری (قبل از ارسال AJAX) */
  form.addEventListener('submit', async (e) => {
    // همکار غیرعضو انتخاب شده: اول به پروژه اضافه شود (همان‌جا)، بعد فرم ارسال شود
    const ao = rs.assignee_id.option(rs.assignee_id.get());
    if (ao?.addTo && String(ao.addTo) === String(rs.project_id.get()) && !ao.added) {
      e.preventDefault(); e.stopImmediatePropagation();
      if (!(await TK.confirm(`«${ao.label}» عضو این پروژه نیست. به پروژه اضافه شود و تسک به او واگذار شود؟`, { okText: 'افزودن و واگذاری', danger: false }))) return;
      const r = await TK.post('/projects/' + ao.addTo + '/members/add', { user_id: ao.value });
      if (!TK.ok(r) || r.state !== 'added') { TK.toast(TK.ok(r) ? r.message + ' بعد از تأیید او می‌توانید تسک را واگذار کنید.' : r.message, TK.ok(r) ? 'warning' : 'error', 7000); return; }
      const p = projOf(ao.addTo); if (p) p.members = [...(p.members || []), ao.value];
      ao.added = true; TK.toast(r.message, 'success');
      form.requestSubmit ? form.requestSubmit() : form.submit();
      return;
    }
    if (!dp.input.disabled && !due.recurrence.valid()) {
      e.preventDefault(); e.stopPropagation(); setMore(true); due.recurrence.open();
      return TK.toast(dp.get() ? 'فاصله تکرار باید یک عدد صحیح بزرگ‌تر از صفر باشد.' : 'برای تکرار خودکار، تاریخ اولین نوبت تسک را انتخاب کنید.', 'error');
    }
    const missing = [];
    if (tp.get() && !dp.get() && !dp.input.disabled) dp.set(dp.today());   // ساعت بدون تاریخ → امروز
    if (!subjNew.classList.contains('hidden')) { e.preventDefault(); e.stopPropagation(); setMore(true); return TK.toast('لیبل جدید را با دکمه‌ی تیک ثبت کنید یا انصراف دهید.', 'warning'); }
    if (!rs.project_id.input.disabled && !rs.project_id.get()) { rs.project_id.invalid(); missing.push('پروژه'); }
    if (!canCreate && form.getAttribute('action') === TK.url('/tasks')) missing.push('پروژه (ابتدا بسازید)');
    if (missing.length) { e.preventDefault(); e.stopPropagation(); TK.toast('انتخاب ' + missing.join(' و ') + ' الزامی است.', 'error'); }
  });

  /* ───── فیلتر و مرتب‌سازی ───── */
  const tb = TK.qs('#t-toolbar');
  if (tb) {
    const setHidden = (name, v) => { let i = tb.querySelector(`input[type=hidden][name="${name}"]`); if (!v) { i?.remove(); return; } if (!i) { i = document.createElement('input'); i.type = 'hidden'; i.name = name; tb.appendChild(i); } i.value = v; };
    /* اعمال فوری فیلتر و مرتب‌سازی با AJAX (v7.3، بدون دکمه‌ی «اعمال»): آدرس صفحه عوض می‌شود و فقط فهرست و
       بخش‌های زنده‌ی صفحه تازه می‌شوند؛ کشوی فیلتر باز می‌ماند تا چند گزینه پشت سر هم عوض شود. */
    const clearBtn = TK.qs('[data-f-clear]', tb), fBtn = TK.qs('[data-drawer-open]', tb);
    const applyAjax = () => {
      TK.qsa('[data-f]', tb).forEach((x) => setHidden(x.dataset.f, x.value));
      const qs = new URLSearchParams(new FormData(tb));
      [...qs.keys()].forEach((k) => { if (k !== 'scope' && k !== 'status' && !qs.get(k)) qs.delete(k); });
      if (qs.get('sort') === 'smart') qs.delete('sort');
      history.replaceState(history.state, '', location.pathname + '?' + qs.toString());
      TK.pagePath = location.pathname + location.search;
      const active = TK.qsa('[data-f]', tb).some((x) => x.value) || qs.has('sort');
      clearBtn?.toggleAttribute('hidden', !active); fBtn?.classList.toggle('btn-outline', active);
      TK.live.refresh(true);
    };
    TK.qsa('[data-f], [name=sort]', tb).forEach((x) => x.addEventListener('change', applyAjax));
    clearBtn?.addEventListener('click', () => {
      TK.qsa('[data-f]', tb).forEach((x) => { if (!(tb.elements.scope?.value === 'project' && x.dataset.f === 'project')) { x.value = ''; x.dispatchEvent(new Event('tk:sync')); } });
      const sm = TK.qs('[name=sort][value=smart]', tb); if (sm) sm.checked = true;
      applyAjax();
    });
    // سایدبار مخفی مرتب‌سازی و فیلتر
    const drawer = TK.qs('#t-drawer'), back = TK.qs('.t-drawer-back', tb), openBtn = TK.qs('[data-drawer-open]', tb);
    const setDrawer = (on) => {
      drawer.classList.toggle('open', on); drawer.setAttribute('aria-hidden', on ? 'false' : 'true');
      back.hidden = !on; openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
      if (on) drawer.querySelector('input:checked, select')?.focus({ preventScroll: true });
    };
    openBtn?.addEventListener('click', () => setDrawer(true));
    TK.qsa('[data-drawer-close]', tb).forEach((b) => b.addEventListener('click', () => setDrawer(false)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drawer.classList.contains('open')) setDrawer(false); });
  }

  /* ───── جزئیات ───── */
  // v7.18: پیوست خود تسک با دسترسی edit_info دکمه‌ی حذف دارد (حذف واقعی در سرور: POST /tasks/{id}/attachments/{file}/delete)
  const fileChip = (f, kind) => `<span class="file-chip"><a href="${E(f.url)}" target="_blank" rel="noopener" class="flex items-center gap-1"><i class="fa-solid ${E(f.icon)}"></i>${E(f.file_name)} <span class="faint xs">${E(f.size_fa)}</span></a>${kind === 'task' && current?.can?.edit_info && !current?.is_blocked ? `<button type="button" class="file-chip-x" data-file-del="${E(f.id)}" data-file-name="${E(f.file_name)}" aria-label="حذف ${E(f.file_name)}" title="حذف پیوست"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>` : ''}</span>`;
  const names = () => [...(current?.mentionable || []).map((m) => m.name), cfg.me_name, current?.creator_name, current?.assignee_name, current?.assistant?.visible ? 'کارلیکس' : null];

  // کارلیکس (v7.19): پاسخ‌ها با آواتار مغز و نشان «دستیار هوش مصنوعی»؛ زیر یادداشتی که منشنش کرده تا پایان کار «در حال بررسی…»
  const AI_WAIT = { pending: 'در صف کارلیکس…', running: 'کارلیکس در حال بررسی و اجرای درخواست است…' };
  const noteHtml = (n) => `
    <div class="note${n.is_ai ? ' note-ai' : ''}" id="note-${n.id}">
      ${n.is_ai ? '<span class="avatar sm mn-ai-av" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>' : `<span class="avatar sm">${n.avatar_url ? `<img loading="lazy" decoding="async" src="${E(n.avatar_url)}" alt="">` : E(TK.initial(n.author))}</span>`}
      <div class="grow">
        <div class="flex between gap-2"><b class="small">${E(n.is_ai ? 'کارلیکس' : n.author)}${n.is_ai ? '<span class="ai-badge">دستیار هوش مصنوعی</span>' : ''}</b><span class="xs faint">${E(n.created_fa)}${n.updated_at ? ' (ویرایش‌شده)' : ''}</span></div>
        <div class="note-body" data-note-text data-raw="${E(n.note)}">${rich(n.note, names())}</div>
        <div>${(n.attachments || []).map((f) => fileChip(f, 'note')).join('')}</div>
        ${AI_WAIT[n.ai_state] ? `<div class="ai-state" role="status" data-ai-wait="${n.id}"><span class="avatar sm mn-ai-av" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>${AI_WAIT[n.ai_state]}<span class="ai-dots" aria-hidden="true"><span></span><span></span><span></span></span></div>` : ''}
        <div class="flex gap-1 mt-1">${n.can_edit ? `<button class="btn btn-xs btn-ghost" data-edit-note="${n.id}"><i class="fa-solid fa-pen"></i> ویرایش</button>` : ''}</div>
      </div>
    </div>`;

  const editPayload = (t) => ({ title: t.title, description: t.description, priority: t.priority, priority_percent: t.priority_percent, due_date: t.due_date, due_time: t.due_time, recurrence: t.recurrence, reminder_every: t.reminder_every, reminder_unit: t.reminder_unit, subject_id: t.subject_id, project_id: t.project_id, assignee_id: t.assignee_id,
    _id: t.id, _can: t.can });

  // «انتقال به مرحله‌ی بعد» در بورد (مرحله‌ی «انجام‌شده» همان دکمه‌ی «انجام شد» است)
  /* دکمه‌های انجام (v4.1): «انجام شد» = تحویل برای بررسی سرپرست (برای همه)؛ در بررسی: «تأیید و بستن» / «برگرداندن برای اصلاح» */
  const doneBtns = (t) => {
    const c = t.can || {}, lock = (txt, why) => `<button class="btn btn-sm locked" data-lock-reason="${E(why)}" data-lock-title="انجام شد" aria-disabled="true"><i class="fa-solid fa-lock"></i> ${txt}</button>`;
    if (t.is_blocked) return '';
    if (c.status === 'review') {
      if (c.approve) {
        return `<button class="btn btn-sm btn-primary" data-detail-approve><i class="fa-solid fa-check-double"></i> تأیید و بستن</button>`
          + (c.rework ? `<button class="btn btn-sm" data-detail-rework><i class="fa-solid fa-rotate-left"></i> برگرداندن برای اصلاح</button>` : '');
      }
      return c.withdraw ? '<button class="btn btn-sm" data-detail-toggle><i class="fa-solid fa-hourglass-half"></i> منتظر تأیید سرپرست — پس گرفتن تحویل</button>'
        : '<span class="badge board-st tone-purple"><i class="fa-solid fa-hourglass-half"></i> منتظر تأیید سرپرست</span>';
    }
    if (t.is_completed) return c.complete ? '<button class="btn btn-sm" data-detail-toggle><i class="fa-solid fa-rotate-left"></i> بازگشت به بررسی سرپرست</button>' : '';
    if (c.status === null || c.status === undefined) return c.complete ? '<button class="btn btn-sm btn-primary" data-detail-toggle><i class="fa-solid fa-check"></i> انجام شد</button>' : lock('انجام شد', 'شما دسترسی لازم برای تکمیل تسک را ندارید.');
    // برگشت‌خورده برای اصلاح (v5.4): دکمه‌ی اصلی «اصلاح شد، ارسال دوباره»؛ انتقال به «برنامه‌ریزی‌نشده» فقط از منوی چیپ وضعیت
    if (c.status === 'rework') {
      return c.deliver ? `<button class="btn btn-sm btn-primary" data-detail-toggle title="${c.approve_direct ? 'تکمیل تسک' : 'ارسال دوباره برای بررسی سرپرست'}"><i class="fa-solid fa-paper-plane"></i> اصلاح شد، ارسال دوباره</button>`
        : lock('اصلاح شد، ارسال دوباره', 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را دوباره برای بررسی بفرستد.');
    }
    return c.deliver ? `<button class="btn btn-sm btn-primary" data-detail-toggle title="${c.approve_direct ? 'تکمیل تسک (شما دسترسی تأیید دارید)' : 'ارسال برای بررسی سرپرست'}"><i class="fa-solid fa-check"></i> انجام شد</button>`
      : lock('انجام شد', 'فقط مسئول یا سازنده‌ی تسک (یا عضوی با دسترسی تکمیل) می‌تواند آن را برای بررسی بفرستد.');
  };
  const nextBtn = (b) => {
    if (!b || !b.next || b.next === 'done' || b.status === 'rework') return '';
    const txt = `انتقال به «${E(b.next_label)}»`;
    return b.can_next
      ? `<button class="btn btn-sm" data-detail-advance="${E(b.status)}"><i class="fa-solid fa-arrow-left"></i> ${txt}</button>`
      : `<button class="btn btn-sm locked" data-lock-reason="${E(b.lock_reason || '')}" aria-disabled="true"><i class="fa-solid fa-lock"></i> ${txt}</button>`;
  };

  /* ───── ویرایش سریع از روی نشان‌ها (اولویت، وضعیت بورد، لیبل، پروژه، سررسید) ───── */
  const qe = (key, can, html, title) => (can ? `<button type="button" class="qe-chip" data-qe="${key}" title="${E(title)} (برای تغییر کلیک کنید)">${html}<i class="fa-solid fa-pen qe-ic"></i></button>` : html);
  const chipsHtml = (t) => {
    const c = t.can || {}, b = t.board;
    const st = b ? `<span class="badge board-st tone-${E(b.tone)}" title="وضعیت در بورد پروژه"><i class="fa-solid ${E(b.icon)}"></i> ${E(b.label)}</span>`
      : `<span class="badge ${t.is_completed ? 'green' : 'yellow'}">${t.is_completed ? 'انجام‌شده' : 'در جریان'}</span>`;
    const lbl = t.subject_title ? `<span class="chip" title="لیبل"><i class="fa-solid fa-tag" data-css="color:${E(t.subject_color || 'var(--muted)')}"></i> ${E(t.subject_title)}</span>`
      : (c.edit_meta ? '<span class="chip faint"><i class="fa-solid fa-tag"></i> بدون لیبل</span>' : '');
    const prj = t.project_title ? `<span class="chip"><i class="fa-solid fa-diagram-project"></i> ${E(t.project_title)}</span>` : '';
    const due = t.due_fa ? `<span class="chip"><i class="fa-regular fa-calendar"></i> ${E(t.due_fa)}</span>`
      : (c.edit_due ? '<span class="chip faint"><i class="fa-regular fa-calendar"></i> بدون سررسید</span>' : '');
    // وضعیت: با مقصد مجاز قابل تغییر؛ بدون مقصد، کلیک دلیل قفل را نشان می‌دهد
    const stChip = b && b.targets?.length ? qe('status', true, st, 'وضعیت بورد')
      : b && b.lock && b.status !== 'archived' ? `<button type="button" class="qe-chip" data-lock-reason="${E(b.lock)}" title="چرا نمی‌توانم وضعیت را تغییر دهم؟">${st}<i class="fa-solid fa-lock qe-ic"></i></button>` : st;
    return qe('priority', c.edit_meta, prioBadge(t), 'اولویت') + stChip + (lbl ? qe('label', c.edit_meta, lbl, 'لیبل') : '')
      + (prj ? qe('project', c.change_project, prj, 'پروژه') : '') + (due ? qe('due', c.edit_due, due, 'تاریخ و ساعت سررسید') : '')
      // ساخته‌شده با «ثبت سریع»؛ با اولین تغییر وضعیت در بورد حذف می‌شود
      + (t.is_quick ? '<span class="qk-badge" tabindex="0" role="img" aria-label="ثبت سریع" data-tip="ثبت سریع"><i class="fa-solid fa-bolt"></i></span>' : '')
      + (t.confidential?.on ? `<span class="secret-badge" tabindex="0" role="img" aria-label="محرمانه" data-tip="محرمانه: فقط سازنده، مسئول${t.confidential.viewers.length ? ' و ' + TK.fa(t.confidential.viewers.length) + ' نفر انتخاب‌شده' : ''}"><i class="fa-solid fa-user-secret"></i></span>` : '');
  };

  let qePop = null;
  const qeClose = () => { qePop?.remove(); qePop = null; TK.qsa('.qe-chip.on', detail).forEach((x) => x.classList.remove('on')); };
  const qeSave = async (url, data, btn) => {
    TK.busy(btn, true);
    const r = await TK.post(url, data);
    TK.busy(btn, false);
    TK.toast(r.message || (TK.ok(r) ? 'ذخیره شد.' : 'خطا'), TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) { qeClose(); Promise.all([refresh(), TK.reload()]); }
  };
  const qeUpdate = (data, btn) => qeSave('/tasks/' + currentId + '/update', data, btn);
  const opt = (attrs, html, on = false) => `<button type="button" class="qe-opt${on ? ' on' : ''}" ${attrs}>${html}</button>`;
  const qeOpen = (chip) => {
    const t = current, key = chip.dataset.qe;
    if (qePop && chip.classList.contains('on')) return qeClose();
    qeClose();
    chip.classList.add('on');
    const row = chip.closest('.qe-row');
    qePop = document.createElement('div');
    qePop.className = 'qe-pop';
    let body = '';
    if (key === 'priority') {
      if (t.priority_type === 'percent') {
        const p = t.priority_percent ?? PCT[t.priority] ?? 50;
        body = `<div class="qe-h">اولویت <b data-qe-pv>${F(p)}٪</b></div><input type="range" class="prio-range" min="0" max="100" step="5" value="${p}" data-qe-range>
          <div class="qe-foot"><button type="button" class="btn btn-sm btn-primary" data-qe-save-pct>ذخیره</button></div>`;
      } else {
        body = '<div class="qe-h">اولویت</div>' + Object.entries(PR).map(([k, [l, cl]]) => opt(`data-qe-prio="${k}"`, `<span class="badge ${cl}">${l}</span>`, t.priority === k)).join('');
      }
    } else if (key === 'status') {
      body = `<div class="qe-h">انتقال در بورد از «${E(t.board.label)}» به:</div>` + t.board.targets.map((x) => opt(`data-qe-status="${E(x.key)}"`, `<span class="badge board-st tone-${E(x.tone)}"><i class="fa-solid ${E(x.icon)}"></i> ${E(x.label)}</span>`)).join('')
        + '<div class="qe-reason" hidden><input class="input" maxlength="255" placeholder="علت (اختیاری)" data-qe-reason><button type="button" class="btn btn-sm btn-primary" data-qe-block>ثبت</button></div>';
    } else if (key === 'label') {
      body = '<div class="qe-h">لیبل</div><div class="qe-list">' + opt('data-qe-label=""', '<i class="fa-solid fa-ban faint"></i> بدون لیبل', !t.subject_id)
        + (cfg.subjects || []).filter((x) => String(x.project_id) === String(t.project_id)).map((x) => opt(`data-qe-label="${x.id}"`, `<i class="fa-solid fa-tag" data-css="color:${E(x.color || 'var(--muted)')}"></i> ${E(x.title)}`, String(t.subject_id) === String(x.id))).join('') + '</div>';
    } else if (key === 'project') {
      body = '<div class="qe-h">پروژه</div><div class="qe-list">' + (cfg.projects || []).map((x) => opt(`data-qe-project="${x.id}"`, `<span class="avatar xs sq">${x.image ? `<img loading="lazy" decoding="async" src="${E(x.image)}" alt="">` : E(TK.initial(x.title))}</span> ${E(x.title)}`, String(t.project_id) === String(x.id))).join('') + '</div>';
    } else if (key === 'due') {
      body = `<div class="qe-h">تاریخ و ساعت سررسید</div><div class="qe-due"><div data-qe-due></div></div>
        <div class="qe-foot"><button type="button" class="btn btn-sm btn-primary" data-qe-save-due>ذخیره</button>${t.due_date ? '<button type="button" class="btn btn-sm" data-qe-clear-due>حذف سررسید</button>' : ''}</div>`;
    }
    qePop.innerHTML = body;
    row.appendChild(qePop);
    // زیر همان نشان (راست‌چین)، بدون بیرون زدن از ردیف
    const rw = row.clientWidth, pw = Math.min(qePop.offsetWidth, rw);
    const right = Math.max(0, Math.min(rw - (chip.offsetLeft + chip.offsetWidth), rw - pw));
    qePop.style.top = (chip.offsetTop + chip.offsetHeight + 6) + 'px';
    qePop.style.right = right + 'px';
    if (key === 'due') {
      const picker = TK.duepicker(TK.qs('[data-qe-due]', qePop), { dateName: 'qe_due_date', timeName: 'qe_due_time', recurrence: true, reminders: true });
      picker.date.set(t.due_date || ''); picker.time.set(t.due_time || ''); picker.recurrence.set(t.recurrence); picker.reminders.set(t);
      qePop.dataset.ready = '1';
      qePop._pick = picker;
    }
    if (key === 'priority' && t.priority_type === 'percent') {
      const rg = TK.qs('[data-qe-range]', qePop), pv = TK.qs('[data-qe-pv]', qePop);
      const paint = () => { pv.textContent = F(rg.value) + '٪'; rg.style.setProperty('--pc', prioColor(Number(rg.value))); };
      rg.addEventListener('input', paint); paint();
    }
  };
  detail.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-qe]');
    if (chip) { e.stopPropagation(); return qeOpen(chip); }
    const p = e.target.closest('.qe-pop'); if (!p) return;
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.qePrio) return qeUpdate({ priority: b.dataset.qePrio }, b);
    if (b.hasAttribute('data-qe-save-pct')) return qeUpdate({ priority_percent: TK.qs('[data-qe-range]', p).value }, b);
    if (b.dataset.qeLabel !== undefined) return qeUpdate({ subject_id: b.dataset.qeLabel }, b);
    if (b.dataset.qeProject) return qeUpdate({ project_id: b.dataset.qeProject }, b);
    if (b.hasAttribute('data-qe-save-due')) {
      const picker = p._pick, dv = picker.date.get(), hv = picker.time.get(), repeat = picker.recurrence.get();
      if (!picker.reminders.valid()) return TK.toast('بازه یادآوری تأخیر باید عدد صحیح ۱ تا ۱۰۰۰ و واحد معتبر باشد.', 'warning');
      if (!picker.recurrence.valid()) return TK.toast('تاریخ و فاصله تکرار خودکار را درست وارد کنید.', 'warning');
      if (hv && !dv) return TK.toast('اول تاریخ را انتخاب کنید.', 'warning');
      return qeUpdate({ due_date: dv, due_time: dv ? hv : '', repeat_enabled: repeat.enabled ? 1 : 0, repeat_kind: repeat.kind, repeat_days: repeat.days, ...picker.reminders.get() }, b);
    }
    if (b.hasAttribute('data-qe-clear-due')) return qeUpdate({ due_date: '', due_time: '', repeat_enabled: 0 }, b);
    if (b.dataset.qeStatus) {
      if (b.dataset.qeStatus === 'rework') {
        const r = TK.qs('.qe-reason', p); r.hidden = false; r.dataset.st = b.dataset.qeStatus;
        const inp = TK.qs('[data-qe-reason]', r); inp.placeholder = 'چه ایرادی باید اصلاح شود؟ (اختیاری)'; inp.focus(); return;
      }
      return qeSave('/tasks/' + currentId + '/status', { status: b.dataset.qeStatus }, b);
    }
    if (b.hasAttribute('data-qe-block')) return qeSave('/tasks/' + currentId + '/status', { status: TK.qs('.qe-reason', p).dataset.st || 'rework', reason: TK.qs('[data-qe-reason]', p).value }, b);
  });
  // بستن پنجره‌ی کوچک با کلیک بیرون یا Escape (لیست باز تاریخ/ساعت داخل خود پنجره است)
  document.addEventListener('click', (e) => { if (qePop && document.contains(e.target) && !e.target.closest('.qe-pop, [data-qe]')) qeClose(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && qePop) { e.stopPropagation(); qeClose(); } }, true);
  TK.qs('#m-detail')?.addEventListener('tk:close', qeClose);

  /* منوی ⋮ جزئیات تسک: مسدود کردن (به‌جای حذف) / رفع مسدودی */
  const moreMenu = (t) => {
    const item = t.is_blocked
      ? `<button type="button" data-detail-unblock${t.can.block ? '' : ' disabled'}><i class="fa-solid fa-lock-open"></i> رفع مسدودی</button>`
      : `<button type="button" class="text-danger" data-detail-block${t.can.block ? '' : ' disabled'}><i class="fa-solid fa-ban"></i> مسدود کردن</button>`;
    const hint = t.can.block ? '' : '<div class="xs faint dd-hint">دسترسی «مسدود کردن تسک» در این پروژه به شما داده نشده است.</div>';
    // تسک محرمانه (v6.1): فقط سازنده‌ی تسک
    const secret = t.confidential?.can_edit ? `<button type="button" data-detail-secret><i class="fa-solid fa-user-secret"></i> ${t.confidential.on ? 'محرمانه (ویرایش بیننده‌ها)' : 'محرمانه'}</button>` : '';
    return `<div class="dropdown detail-more"><button type="button" class="btn btn-ghost btn-icon btn-sm" data-detail-more title="گزینه‌های بیشتر" aria-label="گزینه‌های بیشتر" aria-haspopup="menu"><i class="fa-solid fa-ellipsis-vertical"></i></button>
      <div class="dropdown-menu" role="menu">${secret}${item}${hint}</div></div>`;
  };
  const render = (t) => {
    qeClose();
    currentId = t.id; current = t;
    const canEdit = t.can.edit;
    // چیدمان v7.2 (مثل نمونه‌ی «جزئیات تسک»): سربرگ با آیکون و ابزارها، ستون اصلی (برچسب‌ها، شرح، پیوست‌ها، یادداشت‌ها) و ستون کناری (وضعیت، جزئیات)
    const av = (name, img) => `<span class="avatar xs">${img ? `<img loading="lazy" decoding="async" src="${E(img)}" alt="">` : E(TK.initial(name || '؟'))}</span>`;
    const b = t.board, pct = b && b.steps ? Math.round((t.is_completed ? b.steps : b.step) * 100 / b.steps) : (t.is_completed ? 100 : 0);
    // مراحل بورد پروژه (UI-005 v7.3): نام هر مرحله در title و یک متن برای صفحه‌خوان
    const flow = (b && b.flow) || [];
    // v7.16 (دموی UI، «Task detail»): نوار بالا (پروژه · زمان ثبت + ابزارها)، عنوان بزرگ، ستون اصلی (شرح، پیوست‌ها، یادداشت‌ها) و ستون کناری
    // (کارت وضعیت با مراحل، کارت ویژگی‌ها با ویرایش سریع، سازنده/تاریخ) — همچنان پنجره‌ی وسط صفحه (#m-detail)
    const stepsHtml = flow.length ? `<ol class="td-steps" aria-hidden="true">${flow.map((lbl, i) => `<li class="${i + 1 < b.step || t.is_completed ? 'done' : ''}${i + 1 === b.step && !t.is_completed ? ' cur' : ''}" title="مرحله‌ی ${F(i + 1)}: ${E(lbl)}"><span>${F(i + 1)}</span></li>`).join('')}</ol>
      <p class="sr-only">${b.step ? `مرحله‌ی ${F(b.step)} از ${F(flow.length)}: ${E(b.label)}` : `${E(b.label)} (خارج از مراحل اصلی بورد)`}. مراحل: ${flow.map((l, i) => F(i + 1) + '. ' + E(l)).join('، ')}</p>` : '';
    const acts = doneBtns(t) + nextBtn(b);
    const c = t.can || {};
    const st = b ? `<span class="chip-s"><i class="fa-solid ${E(b.icon)}"></i>${E(b.label)}</span>` : `<span class="chip-s ${t.is_completed ? 'is-accent' : 'is-warn'}">${t.is_completed ? 'انجام‌شده' : 'در جریان'}</span>`;
    const stProp = b && b.targets?.length ? qe('status', true, st, 'وضعیت بورد')
      : b && b.lock && b.status !== 'archived' ? `<button type="button" class="qe-chip" data-lock-reason="${E(b.lock)}" title="چرا نمی‌توانم وضعیت را تغییر دهم؟">${st}<i class="fa-solid fa-lock qe-ic"></i></button>` : st;
    const lbl = t.subject_title ? `<span class="chip-s is-outline"><span class="dot" data-css="background:${E(t.subject_color || 'var(--muted)')}"></span>${E(t.subject_title)}</span>` : '<span class="faint">بدون لیبل</span>';
    const due = t.due_fa ? `<span class="chip-s${t.is_overdue ? ' is-danger' : ''}"><i class="fa-regular fa-calendar"></i>${E(t.due_fa)}</span>` : '<span class="faint">بدون سررسید</span>';
    const prj = t.project_title ? `<span class="tr-proj"><span class="proj-ic sm" aria-hidden="true">${E(TK.initial(t.project_title))}</span>${E(t.project_title)}</span>` : '<span class="faint">بدون پروژه</span>';
    const prop = (ic, name, val) => `<div class="td-prop"><span class="td-pl"><i class="fa-solid ${ic}" aria-hidden="true"></i>${name}</span><div class="td-pv">${val}</div></div>`;
    detail.innerHTML = `
      <button type="button" class="td-history-fab" data-detail-history title="تاریخچهٔ تغییرات تسک" aria-label="تاریخچهٔ تغییرات تسک" aria-haspopup="dialog" aria-controls="m-history"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></button>
      <div class="td-head">
        <div class="td-crumb">${t.project_title ? `<span class="proj-ic sm" aria-hidden="true">${E(TK.initial(t.project_title))}</span><span class="truncate">${E(t.project_title)}</span><span aria-hidden="true">·</span>` : ''}<span class="nowrap">ثبت ${E(t.created_fa)}</span>
          ${t.is_quick ? '<span class="qk-badge" tabindex="0" role="img" aria-label="ثبت سریع" data-tip="ثبت سریع"><i class="fa-solid fa-bolt"></i></span>' : ''}
          ${t.confidential?.on ? `<span class="secret-badge" tabindex="0" role="img" aria-label="محرمانه" data-tip="محرمانه: فقط سازنده، مسئول${t.confidential.viewers.length ? ' و ' + TK.fa(t.confidential.viewers.length) + ' نفر انتخاب‌شده' : ''}"><i class="fa-solid fa-user-secret"></i></span>` : ''}</div>
        <div class="td-tools">
          ${canEdit ? '<button class="btn btn-ghost btn-icon btn-sm" data-detail-edit title="ویرایش تسک" aria-label="ویرایش تسک"><i class="fa-solid fa-pen"></i></button>' : ''}
          ${t.on_board && cfg.context !== 'board' ? `<a class="btn btn-ghost btn-icon btn-sm" href="${TK.url('/board?task=' + t.id)}" title="نمایش در بورد پروژه" aria-label="نمایش در بورد پروژه"><i class="fa-solid fa-table-columns"></i></a>` : ''}
          <button class="btn btn-ghost btn-icon btn-sm" data-detail-share title="کپی لینک تسک" aria-label="کپی لینک تسک"><i class="fa-solid fa-link"></i></button>
          ${moreMenu(t)}
          <button class="btn btn-ghost btn-icon btn-sm" data-close title="بستن" aria-label="بستن"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <div class="td-scroll">
      <h2 class="td-title" id="td-title">${E(t.title)}</h2>
      ${t.is_blocked ? `<div class="alert error blk-alert td-banner"><i class="fa-solid fa-ban"></i> این تسک مسدود شده است${t.blocked_by_name ? ' (توسط ' + E(t.blocked_by_name) + ')' : ''} — ${E(t.blocked_fa || '')}. تا مسدودی برداشته نشود فقط قابل مشاهده است.</div>` : ''}
      <div class="td-grid">
      <div class="td-main">
      <section class="td-sec"><h3 class="td-h"><i class="fa-solid fa-file"></i> شرح تسک</h3>
        ${t.description ? `<div class="note-body td-desc">${rich(t.description)}</div>` : '<div class="faint small">توضیحی برای این تسک نوشته نشده است.</div>'}</section>
      <section class="td-sec"><div class="td-h-row"><h3 class="td-h"><i class="fa-solid fa-paperclip"></i> پیوست‌ها <span class="cnt">${F(t.files.length)}</span></h3>
        ${t.can.edit_info ? `<form id="f-task-upload" class="file-pick-row" enctype="multipart/form-data"><span class="file-pick-names faint small" data-file-names></span><label class="btn btn-ghost btn-sm file-pick" title="افزودن فایل به پیوست‌ها"><i class="fa-solid fa-upload"></i> افزودن فایل<input type="file" name="attachments[]" multiple hidden data-auto-upload></label></form>` : ''}</div>
        <div id="task-files" class="td-files">${t.files.length ? t.files.map((f) => fileChip(f, 'task')).join('') : '<span class="faint small">پیوستی وجود ندارد.</span>'}</div></section>
      <section class="td-sec">
        <h3 class="td-notes-heading">یادداشت‌ها و گزارش پیشرفت <span class="cnt">${F(t.notes.length)}</span></h3>
      ${t.can.notes ? `<form id="f-note" class="td-compose" enctype="multipart/form-data"><textarea class="input" name="note" rows="3" data-mention-ta placeholder="${E(TK.ph('task_note', 'یادداشت یا گزارش پیشرفت… (برای منشن @ بنویسید یا از دکمه‌ی @ استفاده کنید)'))}"></textarea>
        <div class="td-compose-bar file-pick-row"><button class="btn btn-primary btn-sm">ثبت یادداشت</button>
          <button type="button" class="btn btn-ghost btn-icon btn-sm mention-btn" data-mention title="منشن کردن اعضای پروژه یا کارلیکس" aria-label="منشن" aria-haspopup="listbox"><i class="fa-solid fa-at"></i></button>
          <label class="btn btn-ghost btn-icon btn-sm file-pick" title="پیوست فایل به یادداشت" aria-label="پیوست فایل"><i class="fa-solid fa-paperclip"></i><input type="file" name="attachments[]" multiple hidden></label>
          <span class="file-pick-names faint small grow" data-file-names></span><span class="td-kbd" aria-hidden="true"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> ثبت</span></div></form>` : ''}
      <div class="td-notes-bar"><span class="faint xs">${F(t.notes.length)} یادداشت</span><span class="faint xs">جدیدترین بالا</span></div>
      <div id="notes">${t.notes.length ? t.notes.map(noteHtml).join('') : '<div class="td-empty">هنوز یادداشتی ثبت نشده است.</div>'}</div></section>
      </div>
      <aside class="td-side">
        <section class="td-card"><div class="td-card-h"><b>وضعیت</b>${flow.length && b.step ? `<span class="faint xs">مرحله‌ی ${F(b.step)} از ${F(flow.length)}</span>` : `<span class="faint xs">${F(pct)}٪</span>`}</div>
          ${stepsHtml}
          <div class="td-st">${b ? `<i class="fa-solid ${E(b.icon)}" aria-hidden="true"></i> ${E(b.label)}` : (t.is_completed ? 'انجام‌شده' : 'در جریان')}</div>
          <div class="td-acts">${acts}</div>
        </section>
        <section class="td-card td-props qe-row">
          ${prop('fa-layer-group', 'وضعیت', stProp)}
          ${prop('fa-user', 'مسئول', t.assignee_name ? `<span class="td-who">${av(t.assignee_name, t.assignee_avatar)}${E(t.assignee_name)}</span>` : '—')}
          ${prop('fa-flag', 'اولویت', qe('priority', c.edit_meta, prioBadge(t), 'اولویت'))}
          ${prop('fa-calendar', 'سررسید', qe('due', c.edit_due, due, 'تاریخ و ساعت سررسید'))}
          ${t.reminder_every ? prop('fa-bell', 'یادآوری تأخیر', 'هر ' + F(t.reminder_every) + ' ' + E(({minute:'دقیقه',hour:'ساعت',day:'روز',week:'هفته',month:'ماه'})[t.reminder_unit] || '')) : ''}
          ${t.recurrence?.enabled ? prop('fa-rotate', 'تکرار خودکار', E(({daily:'روزانه',weekly:'هفتگی',monthly:'ماهانه',custom:'هر ' + F(t.recurrence.days) + ' روز'})[t.recurrence.kind] || 'فعال')) : ''}
          ${prop('fa-tag', 'لیبل', qe('label', c.edit_meta, lbl, 'لیبل'))}
          ${prop('fa-folder', 'پروژه', qe('project', c.change_project, prj, 'پروژه'))}
        </section>
        <dl class="kv td-meta">
          <dt>سازنده</dt><dd><span class="td-who">${av(t.creator_name, t.creator_avatar)}${E(t.creator_name)}</span></dd>
          <dt>تاریخ ایجاد</dt><dd>${E(t.created_fa)}</dd>
          ${t.completed_fa ? `<dt>تکمیل</dt><dd>${E(t.completed_fa)}</dd>` : ''}
        </dl>
      </aside>
      </div>
      </div>`;
    // لینک در یادداشت: همان پاپ‌اور عنوان‌گذاری توضیحات (نمایش [عنوان](لینک) به‌صورت لینک قابل کلیک با rich())
    const noteTa = TK.qs('#f-note textarea', detail);
    if (noteTa) linkify(noteTa);
  };

  /* ───── تاریخچه‌ی تغییرات ───── */
  const who = (u) => (u ? `<span class="hist-user"><span class="avatar xs">${u.avatar ? `<img loading="lazy" decoding="async" src="${E(u.avatar)}" alt="">` : E(TK.initial(u.name))}</span>${E(u.name)}</span>` : '<span class="hist-user muted">کاربر حذف‌شده</span>');
  const q = (v) => (v ? `«${E(v)}»` : '<span class="muted">(خالی)</span>');
  // منبع تغییر از صفحه‌ی «بار کاری» (v7.10): پیشنهاد / کشیدن و رها کردن / بازگردانی
  const wlSrc = (i) => ({ suggestion: ' (پیشنهاد «بار کاری»)', drag: ' (کشیدن در «بار کاری»)', undo: ' (بازگردانی در «بار کاری»)', karlix: ' (با کارلیکس)' })[i.data && i.data.source] || '';
  const HIST = {
    created: ['fa-circle-plus', 'ok', (i) => `تسک توسط ${who(i.actor)} ایجاد شد${i.data.assignee_user && i.data.assignee_user.name !== i.actor?.name ? ` و به ${who(i.data.assignee_user)} واگذار شد` : ''}.`],
    confidential_on: ['fa-user-secret', 'info', (i) => `${who(i.actor)} تسک را محرمانه کرد${i.data.count ? ' (' + TK.fa(i.data.count) + ' بیننده‌ی انتخابی)' : ''}`],
    confidential_off: ['fa-user-secret', 'info', (i) => `${who(i.actor)} تسک را از حالت محرمانه خارج کرد`],
    title: ['fa-heading', 'info', (i) => `${who(i.actor)} عنوان را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد${wlSrc(i)}.`],
    description: ['fa-align-right', 'info', (i) => `${who(i.actor)} توضیحات را ویرایش کرد${wlSrc(i)}.`, (i) => i.data.to ? `<div class="hist-box note-body">${rich(i.data.to)}</div>` : '<div class="hist-box muted">توضیحات پاک شد.</div>'],
    priority: ['fa-signal', 'warn', (i) => `${who(i.actor)} اولویت را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد${wlSrc(i)}.`],
    reminder: ['fa-bell', 'warn', (i) => `${who(i.actor)} یادآوری تأخیر را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد${wlSrc(i)}.`],
    due: ['fa-calendar-days', 'warn', (i) => `${who(i.actor)} سررسید را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد${wlSrc(i)}.`],
    label: ['fa-tag', 'info', (i) => `${who(i.actor)} لیبل را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد${wlSrc(i)}.`],
    project: ['fa-diagram-project', 'info', (i) => `${who(i.actor)} پروژه را از ${q(i.data.from)} به ${q(i.data.to)} تغییر داد.`],
    assignee: ['fa-user-pen', 'purple', (i) => `مسئول انجام تسک توسط ${who(i.actor)} از ${who(i.data.from_user)} به ${who(i.data.to_user)} تغییر کرد${wlSrc(i)}.`],
    completed: ['fa-circle-check', 'ok', (i) => (i.actor ? `${who(i.actor)} تسک را انجام‌شده ثبت کرد.` : 'تسک انجام‌شده ثبت شد.')],
    reopened: ['fa-rotate-left', 'warn', (i) => `${who(i.actor)} تسک را به حالت انجام‌نشده برگرداند.`],
    board: ['fa-table-columns', 'info', (i) => `${who(i.actor)} وضعیت برد را از ${q(i.data.from)} به ${q(i.data.to)} برد.`, (i) => (i.data.reason ? `<div class="hist-box">علت: ${E(i.data.reason)}</div>` : '')],
    archived: ['fa-box-archive', 'muted', (i) => `${who(i.actor)} تسک را در برد به آرشیو برد.`],
    unarchived: ['fa-box-open', 'muted', (i) => `${who(i.actor)} تسک را از آرشیو برد برگرداند.`],
    note: ['fa-comment', 'ok', (i) => `${who(i.actor)} یک یادداشت گذاشت${i.data.edited ? ' <span class="xs faint">(ویرایش‌شده)</span>' : ''}:`, (i) => `<div class="hist-box note-body">${rich(i.data.text, names())}${(i.data.files || []).length ? `<div class="hist-files">${i.data.files.map((f) => `<span class="chip"><i class="fa-solid fa-paperclip"></i> ${E(f)}</span>`).join('')}</div>` : ''}</div>`],
    note_edited: ['fa-pen', 'info', (i) => `${who(i.actor)} یک یادداشت را ویرایش کرد.`],
    note_deleted: ['fa-trash', 'danger', (i) => `${who(i.actor)} یک یادداشت را حذف کرد:`, (i) => `<div class="hist-box hist-del">${E(i.data.text || '')}</div>`],
    file: ['fa-paperclip', 'info', (i) => `${who(i.actor)} فایل ${q(i.data.name)} را پیوست کرد.`],
    blocked: ['fa-ban', 'danger', (i) => `${who(i.actor)} تسک را مسدود کرد.`],
    unblocked: ['fa-lock-open', 'ok', (i) => `${who(i.actor)} مسدودی تسک را برداشت.`],
    recurrence: ['fa-rotate', 'info', (i) => `${who(i.actor)} تکرار خودکار را ${i.data.enabled ? 'روی ' + q(({daily:'روزانه',weekly:'هفتگی',monthly:'ماهانه',custom:'هر ' + F(i.data.days) + ' روز'})[i.data.kind] || 'فعال') + ' تنظیم کرد' : 'غیرفعال کرد'}.`],
    recurrence_next: ['fa-rotate', 'info', (i) => `نوبت بعدی تسک با سررسید ${q(i.data.due_fa || i.data.due)} ساخته شد.`],
    recurrence_created: ['fa-rotate', 'info', () => 'این تسک به‌عنوان نوبت بعدی تکرار خودکار ساخته شد.'],
    confidential_on: ['fa-user-secret', 'warn', (i) => `${who(i.actor)} تسک را محرمانه کرد.`],
    confidential_off: ['fa-lock-open', 'info', (i) => `${who(i.actor)} محرمانگی تسک را برداشت.`],
    file_deleted: ['fa-file-circle-xmark', 'danger', (i) => `${who(i.actor)} فایل ${q(i.data.name)} را از ${i.data.note ? 'یک یادداشت' : 'پیوست‌های تسک'} حذف کرد.`],
  };
  const historyFields = (data) => {
    const labels = {from:'مقدار قبلی',to:'مقدار جدید',from_user:'مسئول قبلی',to_user:'مسئول جدید',assignee_user:'مسئول اولیه',author_user:'نویسنده',reason:'دلیل',text:'متن',files:'فایل‌ها',name:'نام فایل',enabled:'تکرار فعال',kind:'نوع تکرار',days:'فاصلهٔ روزها',due:'تاریخ ثبت‌شده',due_fa:'سررسید',count:'تعداد',source:'منبع تغییر',edited:'ویرایش‌شده',legacy:'ثبت قدیمی',categories:'دسته‌بندی‌ها',note:'یادداشت'};
    const values = {daily:'روزانه',weekly:'هفتگی',monthly:'ماهانه',custom:'بازهٔ دلخواه',karlix:'دستیار کارلیکس',suggestion:'پیشنهاد بار کاری',drag:'جابه‌جایی دستی',undo:'بازگردانی'};
    const value = (v) => v === null ? '—' : typeof v === 'boolean' ? (v ? 'بله' : 'خیر') : Array.isArray(v) ? v.map(value).join('، ') : typeof v === 'object' ? (v.name ? E(v.name) : historyFields(v)) : E(values[v] || String(v));
    return `<dl class="hist-fields">${Object.entries(data).filter(([k,v]) => !(['assignee','author'].includes(k) && typeof v !== 'object')).map(([k,v]) => `<div><dt>${E(labels[k] || k)}</dt><dd>${value(v)}</dd></div>`).join('')}</dl>`;
  };
  const openHistory = async () => {
    const body = TK.qs('#history-body');
    body.innerHTML = '<div class="center muted">در حال بارگذاری…</div>';
    TK.modal.open('m-history');
    const historyId = currentId;
    const r = await TK.get('/tasks/' + historyId + '/history' + (current?.share_token ? '?token=' + encodeURIComponent(current.share_token) : ''));
    if (currentId !== historyId) return;
    if (!TK.ok(r)) { TK.modal.close('m-history'); return TK.toast(r.message || 'خطا', 'error'); }
    TK.qs('#history-task-title').textContent = current?.title || '';
    const items = (r.items || []).slice().reverse();          // جدیدترین بالا
    body.innerHTML = items.length ? items.map((i) => {
      const [icon, tone, line, extra] = HIST[i.type] || ['fa-circle', 'muted', () => `${who(i.actor)} یک تغییر ثبت کرده است: ${E(i.type)}`];
      const details = i.data && Object.keys(i.data).length ? `<details class="hist-details"><summary>تمام جزئیات ثبت‌شده</summary>${historyFields(i.data)}</details>` : '';
      return `<div class="hist-item"><span class="hist-dot ${tone}"><i class="fa-solid ${icon}"></i></span>
        <div class="hist-main"><div class="hist-line">${line(i)}</div>${extra ? extra(i) : ''}${details}<div class="hist-at"><i class="fa-regular fa-clock"></i> ${E(i.at)}</div></div></div>`;
    }).join('') : '<div class="faint small">تاریخچه‌ای ثبت نشده است.</div>';
  };

  const open = async (id) => {
    detail.innerHTML = '<div class="center muted">در حال بارگذاری…</div>';
    TK.modal.open('m-detail');
    const r = await TK.get('/tasks/' + id);
    if (!TK.ok(r)) { TK.modal.close('m-detail'); return TK.toast(r.message || 'خطا', 'error'); }
    render(r.task);
    aiResume(r.task);
  };
  const refresh = async () => { const r = await TK.get('/tasks/' + currentId); if (TK.ok(r)) render(r.task); };

  TK.on('click', '[data-open-task]', (e, t) => { e.preventDefault(); open(t.dataset.openTask); });
  // برای صفحه‌های دیگر (تقویم): باز کردن فرم تسک جدید/ویرایش و جزئیات
  TK.tasks = { openNew, openEdit, open };   // عنوان کارت برد هم لینک است (باز کردن در تب جدید)
  if (cfg.shared) { render(cfg.shared); TK.modal.open('m-detail'); }
  // پیشخوان: بعد از بستن جزئیات، باکس‌ها (منشن دیده‌شده، تسک انجام‌شده) تازه شوند
  if (cfg.context === 'dashboard') TK.qs('#m-detail').addEventListener('tk:close', () => TK.reload());

  const submit = async (f, url, after) => {
    const btn = f.querySelector('button.btn-primary') || f.querySelector('button'); TK.busy(btn, true);
    const pick = btn ? null : f.querySelector('.file-pick'); pick?.classList.add('is-busy');   // آپلود خودکار پیوست
    const fd = new FormData(f);
    if (f.id === 'f-note') {
      const text = String(fd.get('note') || '');
      mentionIds(text).forEach((id) => fd.append('mentions[]', id));
    }
    const r = await TK.http(url, { method: 'POST', form: fd });
    TK.busy(btn, false); pick?.classList.remove('is-busy');
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) {
      const errs = r.upload_errors || r.data?.upload_errors; if (errs?.length) TK.toast(errs.join('، '), 'warning', 7000);
      if (r.assistant_message) TK.toast(r.assistant_message, 'warning', 7000);
      await after(); TK.reload();
      if (r.assistant) aiRun(currentId, r.assistant);
    }
  };

  /* کارلیکس: اجرای درخواست بعد از ثبت یادداشت. پاسخ در همان یادداشت‌های تسک ثبت می‌شود؛ تا آماده شدن، هر ۲ ثانیه دوباره می‌پرسد
     (سرور هر درخواست را فقط یک بار اجرا می‌کند، پس تکرار امن است). */
  const aiBusy = new Set();
  TK.karlixBusy = aiBusy;   // v7.23: نظرسنجی سراسری (app.js) درخواستی را که همین‌جا در حال پیگیری است دوباره صدا نمی‌زند
  const aiRun = async (taskId, noteId) => {
    if (aiBusy.has(String(noteId))) return;
    aiBusy.add(String(noteId));
    let attempt = 1, final = false;
    const until = Date.now() + 4 * 60 * 1000;      // v7.26 (AI-02): پیگیری تا ۴ دقیقه، بعد پیام «هنوز در صف است» (نه توقف بی‌صدا)
    try {
      while (Date.now() < until) {
        const r = await TK.post('/tasks/' + taskId + '/notes/' + noteId + '/karlix', {});
        if (TK.ok(r) && r.state === 'pending' && r.attempt > attempt) {
          attempt = r.attempt;
          TK.toast('کارلیکس با خطای موقت روبه‌رو شد؛ تلاش مجدد خودکار (' + TK.fa(attempt - 1) + ' از ' + TK.fa(2) + ')…', 'warning', 5000);
        }
        if (!TK.ok(r)) { if (r._http !== 429) { final = true; TK.toast(r.message || 'اجرای درخواست کارلیکس ناموفق بود.', 'error'); break; } }
        else if (r.state !== 'pending' && r.state !== 'running') {
          final = true;
          if (String(currentId) === String(taskId) && TK.qs('#m-detail.open')) await refresh();
          if (r.state === 'done') TK.toast('کارلیکس درخواست را انجام داد.', 'success');
          else if (r.state === 'partial') TK.toast('کارلیکس بخشی از درخواست را انجام داد؛ جزئیات در یادداشت‌ها.', 'warning', 6000);
          else if (r.state === 'failed' || r.state === 'error') TK.toast('کارلیکس نتوانست درخواست را انجام دهد؛ توضیح در یادداشت‌ها.', 'error', 6000);
          else TK.toast('کارلیکس پاسخ داد.', 'info');
          TK.live?.refresh?.();
          break;
        }
        // AI-08: فاصله‌ی پرسیدن با تعداد درخواست‌های در انتظار همین صفحه بیشتر می‌شود؛ 429 = کمی بیشتر صبر
        const base = r && r._http === 429 ? 6 : ((r && r.retry) || 2);
        await new Promise((ok) => setTimeout(ok, (base + Math.max(0, aiBusy.size - 1)) * 1000 + Math.random() * 400));
      }
      if (!final) TK.toast('درخواست کارلیکس هنوز در صف است؛ نتیجه بعداً در یادداشت‌های همین تسک ثبت می‌شود.', 'info', 7000);
    } finally { aiBusy.delete(String(noteId)); }
  };
  // جزئیات باز شد و درخواست خود کاربر هنوز تمام نشده (مثلاً صفحه وسط کار بسته شده بود): ادامه‌ی اجرا
  const aiResume = (t) => (t.notes || []).filter((n) => n.ai_mine && AI_WAIT[n.ai_state]).forEach((n) => aiRun(t.id, n.id));

  /* ───── منشن (v7.19) ─────
     یک فهرست شناور (#mn-pop، بیرون از ناحیه‌ی اسکرول جزئیات) برای هر textarea[data-mention-ta] (یادداشت جدید و ویرایش یادداشت):
     با تایپ «@» بعد از فاصله/ابتدای خط یا با دکمه‌ی @ باز می‌شود، با ادامه‌ی تایپ فیلتر می‌شود، ↑/↓ + Enter/Tab انتخاب و Esc بستن.
     «کارلیکس» (دستیار هوش مصنوعی) اولین گزینه است؛ شناسه‌ی منشن‌ها هنگام ثبت از روی متن (@نام کامل) ساخته می‌شود. */
  const AI_NAME = 'کارلیکس';
  const norm = (v) => String(v || '').replace(/[‌‎‏]/g, '').replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/\s+/g, ' ').trim().toLowerCase();
  const mnItems = () => {
    const out = [], a = current?.assistant;
    if (a?.visible) out.push({ id: 'ai', name: AI_NAME, ai: true, off: !a.enabled, hint: a.enabled ? 'دستیار هوش مصنوعی — درخواستتان را بنویسید' : (a.reason || 'فعلاً در دسترس نیست') });
    (current?.mentionable || []).forEach((m) => out.push({ id: String(m.id), name: m.name, avatar: m.avatar }));
    return out;
  };
  // شناسه‌ی اعضایی که «@نام کامل» آن‌ها در متن آمده (چه از فهرست انتخاب شده باشد، چه دستی تایپ شده)
  const mentionIds = (text) => (current?.mentionable || []).filter((m) => text.includes('@' + m.name)).map((m) => m.id);
  let mn = null;   // { ta, start, end, items, idx, btn }
  const mnPop = () => {
    let p = TK.qs('#mn-pop');
    if (!p) {
      p = document.createElement('div');
      p.id = 'mn-pop'; p.className = 'mn-pop'; p.setAttribute('role', 'listbox'); p.setAttribute('aria-label', 'افراد قابل منشن'); p.hidden = true;
      document.body.appendChild(p);
      p.addEventListener('mousedown', (e) => e.preventDefault());           // فوکوس در textarea بماند
      p.addEventListener('click', (e) => { const o = e.target.closest('[data-mn-i]'); if (o && mn) mnPick(+o.dataset.mnI); });
    }
    return p;
  };
  const mnClose = (undoBtn) => {
    if (!mn) return;
    const { ta, btn } = mn;
    // دکمه‌ی @ یک «@» در متن گذاشته بود و چیزی انتخاب نشد: همان را برمی‌داریم
    if (undoBtn && btn !== null && ta.value.slice(btn.at, btn.at + btn.len) === btn.text && ta.selectionStart === btn.at + btn.len) {
      ta.value = ta.value.slice(0, btn.at) + ta.value.slice(btn.at + btn.len); ta.setSelectionRange(btn.at, btn.at);
    }
    mnPop().hidden = true; ta.setAttribute('aria-expanded', 'false'); ta.removeAttribute('aria-activedescendant');
    mn = null;
  };
  const mnQuery = (ta) => {
    const s = ta.selectionStart;
    if (s == null || s !== ta.selectionEnd) return null;
    const m = /(^|[\s(،,])@([^\s@]{0,40}(?: [^\s@]{0,40})?)$/.exec(ta.value.slice(0, s));
    return m ? { start: s - m[2].length - 1, end: s, q: m[2] } : null;
  };
  const mnUpdate = (ta, btn = null) => {
    const hit = mnQuery(ta);
    if (!hit) return mnClose();
    const all = mnItems(), q = norm(hit.q);
    // بعد از انتخاب، «@نام » کامل دیگر فهرست را باز نمی‌کند
    if (/\s$/.test(hit.q) && all.some((i) => norm(i.name) === q)) return mnClose();
    const list = q ? all.filter((i) => norm(i.name).includes(q)) : all;
    if (!list.length && (q || !btn)) return mnClose();
    const keep = mn && mn.ta === ta ? mn.idx : 0;
    mn = { ta, start: hit.start, end: hit.end, items: list, idx: Math.min(keep, Math.max(0, list.length - 1)), btn: btn || (mn && mn.ta === ta ? mn.btn : null) };
    if (mn.items[mn.idx]?.off) mn.idx = Math.max(0, mn.items.findIndex((i) => !i.off));
    mnRender();
  };
  const mnRender = () => {
    const p = mnPop(), ta = mn.ta;
    const av = (i) => (i.ai ? '<span class="avatar sm mn-ai-av" aria-hidden="true"><i class="fa-solid fa-brain"></i></span>'
      : `<span class="avatar sm" aria-hidden="true">${i.avatar ? `<img loading="lazy" decoding="async" src="${E(i.avatar)}" alt="">` : E(TK.initial(i.name))}</span>`);
    let html = '', memberHead = false;
    mn.items.forEach((i, k) => {
      if (!i.ai && !memberHead) { memberHead = true; html += `<div class="mn-head">اعضای پروژه${current?.project_title ? '‌ی «' + E(current.project_title) + '»' : ''}</div>`; }
      html += `<div class="mn-opt${k === mn.idx ? ' is-on' : ''}${i.ai ? ' is-ai' : ''}${i.off ? ' is-off' : ''}" role="option" id="mn-o-${k}" data-mn-i="${k}" aria-selected="${k === mn.idx}"${i.off ? ' aria-disabled="true"' : ''}>${av(i)}<span class="mn-txt"><span class="mn-name">${E(i.name)}${i.ai ? ' <span class="mn-tag">AI</span>' : ''}</span>${i.hint ? `<small>${E(i.hint)}</small>` : ''}</span></div>`;
    });
    if (!mn.items.length) html = `<div class="mn-empty">${current?.project_id ? 'عضو دیگری در پروژه‌ی این تسک نیست.' : 'منشن فقط برای تسک‌های پروژه است.'}</div>`;
    else if (!memberHead && !mn.ta.value.slice(mn.start + 1, mn.end)) html += `<div class="mn-empty">${current?.project_id ? 'عضو دیگری در پروژه‌ی این تسک نیست.' : 'این تسک پروژه ندارد؛ فقط کارلیکس قابل منشن است.'}</div>`;
    p.innerHTML = html; p.hidden = false;
    ta.setAttribute('aria-expanded', 'true'); ta.setAttribute('aria-activedescendant', 'mn-o-' + mn.idx);
    // جای فهرست: زیر textarea اگر جا هست، وگرنه بالای آن؛ هم‌تراز لبه‌ی راست (RTL) و داخل صفحه
    const r = ta.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = window.innerHeight;
    const w = Math.min(320, vw - 16);
    p.style.width = w + 'px';
    const h = Math.min(p.scrollHeight, 300);
    const below = vh - r.bottom - 8, above = r.top - 8;
    p.style.top = (below >= h || below >= above ? Math.min(r.bottom + 4, vh - h - 8) : Math.max(8, r.top - h - 4)) + 'px';
    p.style.left = Math.max(8, Math.min(r.right - w, vw - w - 8)) + 'px';
    p.querySelector('.is-on')?.scrollIntoView({ block: 'nearest' });
  };
  const mnPick = (k) => {
    const it = mn?.items[k];
    if (!it) return;
    if (it.off) { TK.toast(it.hint, 'info'); return; }
    const ta = mn.ta, ins = '@' + it.name + ' ';
    ta.value = ta.value.slice(0, mn.start) + ins + ta.value.slice(mn.end);
    const pos = mn.start + ins.length;
    mn.btn = null; mnClose();
    ta.focus(); ta.setSelectionRange(pos, pos);
  };
  // دکمه‌ی @: یک «@» در محل نشانگر می‌گذارد و همان فهرست را باز می‌کند (با Esc یا کلیک بیرون برداشته می‌شود)
  const mnFromButton = () => {
    const ta = TK.qs('#f-note textarea', detail);
    if (!ta) return;
    if (mn && mn.ta === ta) return mnClose(true);
    ta.focus();
    const s = ta.selectionStart ?? ta.value.length, e = ta.selectionEnd ?? s, before = ta.value.slice(0, s);
    const text = (before && !/\s$/.test(before) ? ' ' : '') + '@';
    ta.value = before + text + ta.value.slice(e);
    ta.setSelectionRange(s + text.length, s + text.length);
    mnUpdate(ta, { at: s, len: text.length, text });
  };
  detail.addEventListener('input', (e) => { if (e.target.matches('textarea[data-mention-ta]') && !e.isComposing) mnUpdate(e.target); });
  detail.addEventListener('compositionend', (e) => { if (e.target.matches('textarea[data-mention-ta]')) mnUpdate(e.target); });
  detail.addEventListener('keydown', (e) => {
    if (!mn || e.target !== mn.ta || e.isComposing || e.ctrlKey || e.metaKey || e.altKey) return;
    const n = mn.items.length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!n) return;
      e.preventDefault();
      let i = mn.idx;
      for (let c = 0; c < n; c++) { i = (i + (e.key === 'ArrowDown' ? 1 : -1) + n) % n; if (!mn.items[i].off) break; }
      mn.idx = i; mnRender();
    } else if ((e.key === 'Enter' || e.key === 'Tab') && n && !e.shiftKey) {
      e.preventDefault(); e.stopPropagation(); mnPick(mn.idx);
    } else if (e.key === 'Escape') {
      e.preventDefault(); e.stopPropagation(); mnClose(true);
    }
  });
  detail.addEventListener('click', (e) => { if (mn && e.target === mn.ta) mnUpdate(mn.ta); });
  detail.addEventListener('focusout', (e) => { if (mn && e.target === mn.ta) setTimeout(() => { if (mn && document.activeElement !== mn.ta) mnClose(true); }, 120); });
  detail.addEventListener('scroll', () => { if (mn) mnRender(); }, true);
  window.addEventListener('resize', () => { if (mn) mnRender(); });
  TK.qs('#m-detail')?.addEventListener('tk:close', () => mnClose());
  // پیوست تسک: با انتخاب فایل از دکمه‌ی آیکونی، بلافاصله آپلود می‌شود
  detail.addEventListener('change', (e) => {
    if (e.target.matches('[data-auto-upload]') && e.target.files.length) submit(e.target.form, '/tasks/' + currentId + '/attachments', refresh);
  });
  detail.addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.id === 'f-note') submit(e.target, '/tasks/' + currentId + '/notes', refresh);
    if (e.target.id === 'f-task-upload') submit(e.target, '/tasks/' + currentId + '/attachments', refresh);
  });
  detail.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.hasAttribute('data-close')) return TK.modal.close('m-detail');
    if (b.hasAttribute('data-detail-history')) return openHistory();
    if (b.dataset.fileDel) {
      if (!(await TK.confirm(`پیوست «${b.dataset.fileName}» برای همیشه حذف شود؟`, { okText: 'حذف پیوست' }))) return;
      b.disabled = true;
      const r = await TK.post('/tasks/' + currentId + '/attachments/' + encodeURIComponent(b.dataset.fileDel) + '/delete', {});
      if (TK.ok(r)) { TK.toast(r.message, 'success'); refresh(); TK.live?.refresh?.(); } else { b.disabled = false; TK.toast(r.message || 'حذف پیوست ناموفق بود.', 'error'); }
      return;
    }
    if (b.hasAttribute('data-detail-edit')) { TK.modal.close('m-detail'); return openEdit(editPayload(current)); }
    if (b.hasAttribute('data-mention')) { e.stopPropagation(); return mnFromButton(); }
    if (b.dataset.detailAdvance) { TK.busy(b, true); const r = await TK.post('/tasks/' + currentId + '/advance', { from: b.dataset.detailAdvance }); TK.busy(b, false); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) Promise.all([refresh(), TK.reload()]); else refresh(); return; }
    if (b.hasAttribute('data-detail-toggle')) {
      const r = await TK.post('/tasks/' + currentId + '/toggle');
      const undo = TK.ok(r) && r.undo ? { label: 'بازگردانی', onClick: async () => { const u = await TK.post(r.undo); TK.toast(u.message, TK.ok(u) ? 'info' : 'error'); if (TK.ok(u)) { Promise.all([refresh(), TK.reload()]); } } } : null;
      TK.toast(r.message, TK.ok(r) ? 'success' : 'error', undo ? 5000 : 4200, { action: undo }); if (TK.ok(r)) { Promise.all([refresh(), TK.reload()]); }
    }
    if (b.hasAttribute('data-detail-approve')) { TK.busy(b, true); const r = await TK.post('/tasks/' + currentId + '/status', { status: 'done' }); TK.busy(b, false); TK.toast(TK.ok(r) ? 'تسک تأیید و بسته شد.' : r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { Promise.all([refresh(), TK.reload()]); } }
    if (b.hasAttribute('data-detail-rework')) { const why = await TK.prompt('چه چیزی باید اصلاح شود؟ (اختیاری؛ به‌صورت یادداشت روی تسک ثبت و برای مسئول فرستاده می‌شود)', { title: 'برگرداندن برای اصلاح', okText: 'برگرداندن', multiline: true }); if (why === null) return; const r = await TK.post('/tasks/' + currentId + '/status', { status: 'rework', reason: why }); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { Promise.all([refresh(), TK.reload()]); } }
    if (b.hasAttribute('data-detail-share')) { const r = await TK.get('/tasks/' + currentId + '/share'); if (TK.ok(r)) TK.copy(r.url); else TK.toast(r.message, 'error'); }
    if (b.hasAttribute('data-detail-more')) { e.stopPropagation(); b.parentElement.classList.toggle('open'); return; }
    if (b.hasAttribute('data-detail-secret')) {
      b.closest('.dropdown')?.classList.remove('open');
      const r0 = await TK.get('/tasks/' + currentId); if (!TK.ok(r0)) return;
      const c = r0.task.confidential;
      let m = TK.qs('#m-secret');
      if (!m) {
        m = document.createElement('div'); m.className = 'modal'; m.id = 'm-secret';
        m.innerHTML = `<div class="modal-box sm"><form id="f-secret"><div class="modal-head"><h3><i class="fa-solid fa-user-secret"></i> تسک محرمانه</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>
          <div class="modal-body"><label class="check mb-1"><input type="checkbox" name="on" value="1" id="secret-on"><div><b>این تسک محرمانه باشد</b><span>فقط شما، مسئول تسک و کسانی که پایین انتخاب می‌کنید آن را می‌بینند؛ بقیه — حتی مدیر پروژه — نه در فهرست و بورد، نه در جستجو و نه با لینک.</span></div></label>
            <div id="secret-people"><div class="small bold mb-1" id="secret-count"></div><div class="list secret-list" id="secret-list"></div></div></div>
          <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> ذخیره</button></div></form></div>`;
        document.body.appendChild(m);
        const sync = () => {
          const on = TK.qs('#secret-on', m).checked, picked = TK.qsa('#secret-list input:checked', m).length;
          TK.qs('#secret-people', m).classList.toggle('dim', !on);
          TK.qsa('#secret-list input', m).forEach((i) => { i.disabled = !on || (!i.checked && picked >= m._max); });
          TK.qs('#secret-count', m).textContent = `بیننده‌های انتخابی: ${TK.fa(picked)} از ${TK.fa(m._max)} نفر`;
        };
        m.addEventListener('change', sync); m._sync = sync;
        TK.qs('#f-secret', m).addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const on = TK.qs('#secret-on', m).checked;
          const r = await TK.post('/tasks/' + m._task + '/confidential', { on: on ? 1 : 0, viewers: TK.qsa('#secret-list input:checked', m).map((i) => i.value) });
          TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
          if (TK.ok(r)) { TK.modal.close('m-secret'); Promise.all([refresh(), TK.reload()]); }
        });
      }
      m._task = currentId; m._max = c.max;
      const sel = new Set(c.viewers.map((v) => String(v.id)));
      TK.qs('#secret-on', m).checked = c.on;
      TK.qs('#secret-list', m).innerHTML = c.candidates.map((u) => `<label class="check secret-item"><input type="checkbox" value="${E(u.id)}" ${sel.has(String(u.id)) ? 'checked' : ''}>
          <span class="avatar sm">${u.avatar ? `<img loading="lazy" decoding="async" src="${E(u.avatar)}" alt="">` : E(TK.initial(u.name))}</span><div><b>${E(u.name)}</b></div></label>`).join('') || '<div class="faint small">عضو دیگری در این پروژه نیست.</div>';
      m._sync(); TK.modal.open('m-secret');
      return;
    }
    if (b.hasAttribute('data-detail-block') || b.hasAttribute('data-detail-unblock')) {
      const on = b.hasAttribute('data-detail-block');
      b.closest('.dropdown')?.classList.remove('open');
      if (on && !(await TK.confirm('این تسک مسدود شود؟ از فهرست‌ها، بورد و تقویم کنار می‌رود و فقط از «تسک‌های مسدودشده» در صفحه‌ی تسک‌ها برمی‌گردد.', { okText: 'مسدود کن' }))) return;
      const r = await TK.post((on ? '/block/tasks/' : '/unblock/tasks/') + currentId);
      TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
      if (TK.ok(r)) { if (on) TK.modal.close('m-detail'); else Promise.all([refresh(), TK.reload()]); }
      return;
    }
    if (b.dataset.editNote) {
      const box = TK.qs('#note-' + b.dataset.editNote), p = box.querySelector('[data-note-text]'), old = p.dataset.raw;
      p.innerHTML = `<div class="note-edit"><textarea class="input" rows="3" data-mention-ta>${E(old)}</textarea></div><div class="flex gap-1 mt-1"><button class="btn btn-sm btn-primary" data-save-note="${b.dataset.editNote}">ذخیره</button><button class="btn btn-sm" data-cancel-note>انصراف</button></div>`;
      linkify(p.querySelector('textarea'));
    }
    if (b.dataset.saveNote) { const ta = b.closest('.note').querySelector('textarea'); const r = await TK.post('/notes/' + b.dataset.saveNote + '/update', { note: ta.value, mentions: mentionIds(ta.value) }); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) refresh(); }
    if (b.hasAttribute('data-cancel-note')) refresh();
  });
});
