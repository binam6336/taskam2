/* «ثبت سریع تسک» (دکمه‌ی صاعقه‌ی پایین صفحه): فقط عنوان؛ @ = انتخاب مسئول، # = انتخاب پروژه.
   بدون انتخاب: مسئول = خود کاربر، پروژه = پروژه‌ی پیش‌فرض (آخرین پروژه‌ای که در آن تسک ساخته). */
document.addEventListener('tk:ready', () => {
  const m = TK.qs('#m-quick'); if (!m) return;
  const f = TK.qs('#f-quick'), inp = TK.qs('#qk-title'), menu = TK.qs('#qk-menu'), chips = TK.qs('#qk-chips'), E = TK.esc;
  let opt = null, sel = { person: null, project: null }, ac = null;

  const load = async () => {
    if (opt) return opt;
    const r = await TK.get('/tasks/quick-options');
    if (r.status === 'success') opt = r;
    return opt;
  };
  const projOf = (id) => (opt?.projects || []).find((p) => p.id === id) || null;
  const project = () => sel.project || projOf(opt?.default_project);
  const me = () => (opt?.people || []).find((p) => p.me) || null;
  /** کسانی که در پروژه‌ی فعلی می‌توان مسئولشان کرد (هر عضو جز نقش «بدون دسترسی»؛ v5.5) */
  const assignable = () => {
    const p = project(); const all = opt?.people || [];
    if (!p) return all.filter((x) => x.me);
    return [...all.filter((x) => x.me || (p.can_assign && p.members.includes(x.id)))];
  };

  const chip = (icon, label, value, kind, extra = '') => `<span class="qk-chip ${extra}"><i class="fa-solid ${icon}"></i> ${label}: <b>${E(value)}</b>${kind ? `<button type="button" class="qk-x" data-qk-clear="${kind}" aria-label="حذف ${label}"><i class="fa-solid fa-xmark"></i></button>` : ''}</span>`;
  const drawChips = () => {
    const p = project(), who = sel.person || me();
    let h = '';
    if (p) h += chip('fa-diagram-project', 'پروژه', p.title + (sel.project ? '' : ' (پیش‌فرض)'), sel.project ? 'project' : '');
    else if (opt) h += '<span class="qk-chip warn"><i class="fa-solid fa-triangle-exclamation"></i> پروژه‌ای ندارید؛ اول یک پروژه بسازید.</span>';
    if (who) {
      const bad = sel.person && p && !assignable().some((x) => x.id === sel.person.id);
      h += chip('fa-user', 'مسئول', who.name, sel.person ? 'person' : '', bad ? 'warn' : '');
      if (bad) h += '<span class="qk-chip warn"><i class="fa-solid fa-circle-info"></i> این فرد در پروژه‌ی انتخاب‌شده قابل انتخاب به‌عنوان مسئول نیست.</span>';
    }
    chips.innerHTML = h;
  };

  /* ───── پیشنهاد @ / # ───── */
  const closeMenu = () => { ac = null; menu.hidden = true; menu.innerHTML = ''; inp.setAttribute('aria-expanded', 'false'); };
  const drawMenu = () => {
    if (!ac || !ac.items.length) {
      if (ac) {
        // نقش «بدون دسترسی» در پروژه‌ی انتخاب‌شده: علت را بگو، نه «کسی پیدا نشد» (v5.4)
        const p = project(), noAssign = ac.type === '@' && p && !p.can_assign;
        const msg = ac.type === '#' ? 'پروژه‌ای پیدا نشد.' : noAssign ? `در پروژه‌ی «${E(p.title)}» فقط می‌توانید برای خودتان تسک بسازید (نقش شما «بدون دسترسی» است).` : 'کسی پیدا نشد.';
        menu.hidden = false; menu.innerHTML = `<div class="qk-empty${noAssign ? ' warn' : ''}">${msg}</div>`;
      }
      return;
    }
    menu.innerHTML = ac.items.map((it, i) => `<div class="qk-opt${i === ac.idx ? ' active' : ''}" role="option" id="qk-o${i}" aria-selected="${i === ac.idx}" data-i="${i}">`
      + (ac.type === '@' ? (`<span class="avatar xs">${it.avatar ? `<img loading="lazy" decoding="async" src="${E(it.avatar)}" alt="">` : E(TK.initial(it.name))}</span>`) : '<i class="fa-solid fa-diagram-project"></i>')
      + `<span>${E(ac.type === '@' ? it.name : it.title)}${it.note ? ` <small class="faint">${E(it.note)}</small>` : ''}</span></div>`).join('');
    menu.hidden = false; inp.setAttribute('aria-expanded', 'true'); inp.setAttribute('aria-activedescendant', 'qk-o' + ac.idx);
  };
  const detect = async () => {
    const pos = inp.selectionStart ?? inp.value.length, before = inp.value.slice(0, pos);
    const mt = before.match(/(^|\s)([@#])([^@#\n]{0,30})$/);
    if (!mt) { closeMenu(); return; }
    await load(); if (!opt) return;
    const type = mt[2], q = mt[3].trim().toLowerCase();
    const src = type === '@' ? assignable() : opt.projects;
    const items = src.filter((x) => !q || (type === '@' ? x.name : x.title).toLowerCase().includes(q)).slice(0, 8);
    if (!items.length && /\s/.test(mt[3])) { closeMenu(); return; }   // بعد از فاصله و بدون نتیجه = متن عادی
    ac = { type, start: pos - mt[3].length - 1, end: pos, items, idx: 0 };
    drawMenu();
  };
  const pick = (i) => {
    if (!ac || !ac.items[i]) return;
    const it = ac.items[i];
    inp.value = (inp.value.slice(0, ac.start) + inp.value.slice(ac.end)).replace(/\s{2,}/g, ' ');
    const caret = ac.start;
    if (ac.type === '@') sel.person = it; else sel.project = it;
    closeMenu(); drawChips();
    inp.focus(); inp.setSelectionRange(caret, caret);
  };
  inp.addEventListener('input', detect);
  inp.addEventListener('click', detect);
  inp.addEventListener('keydown', (e) => {
    if (!ac || menu.hidden) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); if (!ac.items.length) return;
      ac.idx = (ac.idx + (e.key === 'ArrowDown' ? 1 : -1) + ac.items.length) % ac.items.length; drawMenu();
    } else if ((e.key === 'Enter' || e.key === 'Tab') && ac.items.length) { e.preventDefault(); pick(ac.idx); }
    else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeMenu(); }
  });
  menu.addEventListener('mousedown', (e) => { const o = e.target.closest('.qk-opt'); if (o) { e.preventDefault(); pick(Number(o.dataset.i)); } });
  inp.addEventListener('blur', () => setTimeout(closeMenu, 150));
  chips.addEventListener('click', (e) => { const b = e.target.closest('[data-qk-clear]'); if (!b) return; sel[b.dataset.qkClear] = null; drawChips(); inp.focus(); });

  /* ───── باز کردن و ثبت ───── */
  const reset = () => { f.reset(); sel = { person: null, project: null }; closeMenu(); drawChips(); };
  TK.on('click', '[data-quick-task]', async () => {
    reset(); TK.modal.open('m-quick');
    setTimeout(() => inp.focus(), 60);
    await load(); drawChips();
  });
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (ac && !menu.hidden) return;
    const title = inp.value.replace(/\s+/g, ' ').trim();
    if (!title) { inp.reportValidity(); return; }
    // @ یا # حل‌نشده (به فرد یا پروژه‌ای وصل نشده) در عنوان: پیش از ذخیره بپرس (v5.4)
    const loose = title.match(/(^|\s)([@#][^\s@#]*)/);
    if (loose && !(await TK.confirm(`«${loose[2]}» در عنوان به ${loose[2][0] === '@' ? 'هیچ فردی' : 'هیچ پروژه‌ای'} وصل نشده و به‌صورت متن در عنوان ذخیره می‌شود${loose[2][0] === '@' ? ' و مسئول تسک خودتان می‌شوید' : ''}. همین‌طور ثبت شود؟`, { okText: 'ثبت همین‌طور', danger: false }))) { inp.focus(); return; }
    const btn = TK.qs('#qk-submit'); btn.disabled = true;
    const p = sel.project, who = sel.person;
    const r = await TK.post('/tasks/quick', { title, project_id: p ? p.id : '', assignee_id: who ? who.id : '' });
    btn.disabled = false;
    if (!TK.ok(r)) { TK.toast(r.message || 'خطا', 'error'); return; }
    if (p) opt.default_project = p.id;                    // پروژه‌ی پیش‌فرض بعدی = همین
    reset(); TK.modal.close(m);
    const tok = r.share_token || r.data?.share_token;
    TK.toast(r.message, 'success', 5000, tok ? { action: { label: 'مشاهده', onClick: () => TK.visit(TK.url('/tasks?task=' + tok)) } } : undefined);
    TK.live?.refresh?.();
  });
});
