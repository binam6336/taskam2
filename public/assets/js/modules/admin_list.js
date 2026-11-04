/* پنل مدیر ← فهرست پروژه‌ها: کلیک روی ردیف = پنجره‌ی جزئیات (سازنده، اعضا، تسک‌ها، تاریخ ایجاد)؛
   با کلیک روی «اعضا» یا «تسک‌ها» فهرست همان، با صفحه‌بندی (پیش‌فرض ۱۰، قابل انتخاب) در همان پنجره. */
document.addEventListener('tk:ready', () => {
  const E = TK.esc, F = TK.fa;
  const PER = TK.boot().adminList?.per || [10, 20, 50, 100];
  usersPart(E, F, PER);
  const m = TK.qs('#m-aproj'); if (!m) return;
  const info = TK.qs('#ap-info'), part = TK.qs('#ap-part');
  let pid = 0, cur = { kind: '', page: 1, per: PER[0] };

  const av = (name, src) => `<span class="avatar sm">${src ? `<img src="${E(src)}" alt="">` : E(TK.initial(name))}</span>`;
  const pager = (d) => {
    if (d.pages <= 1 && d.total <= PER[0]) return '';
    const btn = (p, label, on = false, dis = false) => `<button type="button" class="btn btn-xs${on ? ' btn-primary' : ''}" data-ap-page="${p}"${dis ? ' disabled' : ''}>${label}</button>`;
    let h = btn(d.page - 1, '<i class="fa-solid fa-chevron-right"></i>', false, d.page <= 1);
    const from = Math.max(1, d.page - 2), to = Math.min(d.pages, d.page + 2);
    if (from > 1) h += btn(1, F(1)) + (from > 2 ? '<span class="faint">…</span>' : '');
    for (let i = from; i <= to; i++) h += btn(i, F(i), i === d.page);
    if (to < d.pages) h += (to < d.pages - 1 ? '<span class="faint">…</span>' : '') + btn(d.pages, F(d.pages));
    h += btn(d.page + 1, '<i class="fa-solid fa-chevron-left"></i>', false, d.page >= d.pages);
    return `<div class="flex between items-center wrap gap-2 mt-1 ap-pager"><div class="flex gap-1 items-center wrap">${h}</div>
      <label class="flex items-center gap-1 small muted users-per">نمایش در هر صفحه <select class="input" data-ap-per>${PER.map((n) => `<option value="${n}"${n === d.per ? ' selected' : ''}>${F(n)}</option>`).join('')}</select></label></div>`;
  };

  const loadPart = async () => {
    part.innerHTML = '<div class="muted small"><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری…</div>';
    const r = await TK.get(`/admin/list/projects/${pid}/${cur.kind}`, { page: cur.page, per: cur.per });
    if (!TK.ok(r)) { part.innerHTML = `<div class="text-danger small">${E(r.message || 'خطا')}</div>`; return; }
    const head = cur.kind === 'members' ? `<h4><i class="fa-solid fa-users"></i> اعضای پروژه <span class="badge">${F(r.total)}</span></h4>` : `<h4><i class="fa-solid fa-list-check"></i> تسک‌های پروژه <span class="badge">${F(r.total)}</span></h4>`;
    const rows = cur.kind === 'members'
      ? r.items.map((x) => `<tr><td><div class="flex items-center gap-1">${av(x.name, x.avatar)}<b>${E(x.name)}</b></div></td><td class="ltr small">${E(x.mobile)}</td>
          <td><span class="badge${x.is_creator ? ' green' : ''}">${E(x.role)}</span></td><td class="small">${F(x.tasks)}</td><td class="small muted nowrap">${E(x.added_fa)}</td></tr>`).join('')
      : r.items.map((t) => `<tr><td class="bold">${E(t.title)}</td><td class="small">${E(t.assignee)}</td><td><span class="badge board-st tone-${E(t.tone)}">${E(t.status)}</span></td>
          <td class="small">${E(t.priority)}</td><td class="small${t.late ? ' prio-high' : ''}">${E(t.due_fa)}</td><td class="small muted nowrap">${E(t.created_fa)}</td></tr>`).join('');
    const th = cur.kind === 'members' ? '<th>عضو</th><th>موبایل</th><th>نقش</th><th>تسک</th><th>عضویت</th>' : '<th>تسک</th><th>مسئول</th><th>وضعیت</th><th>اولویت</th><th>سررسید</th><th>ایجاد</th>';
    part.innerHTML = head + `<div class="table-wrap"><table class="table"><thead><tr>${th}</tr></thead><tbody>${rows || '<tr><td colspan="6" class="center muted">موردی وجود ندارد.</td></tr>'}</tbody></table></div>` + pager(r);
    TK.qsa('[data-ap-show]', info).forEach((b) => b.classList.toggle('on', b.dataset.apShow === cur.kind));
  };

  const open = async (id) => {
    pid = id; cur = { kind: '', page: 1, per: PER[0] }; part.innerHTML = '';
    info.innerHTML = '<div class="muted small"><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری…</div>';
    TK.modal.open(m);
    const r = await TK.get('/admin/list/projects/' + id);
    if (!TK.ok(r)) { info.innerHTML = `<div class="text-danger small">${E(r.message || 'خطا')}</div>`; return; }
    const p = r.project;
    TK.qs('#ap-title').textContent = p.title;
    info.innerHTML = `${p.is_blocked ? '<div class="alert error mb-2"><i class="fa-solid fa-ban"></i> این پروژه مسدود شده است.</div>' : ''}
      ${p.description ? `<p class="muted small mb-2">${E(p.description)}</p>` : ''}
      <div class="grid ap-cards">
        <div class="card flat ap-card"><div class="xs muted">ایجادکننده</div><div class="flex items-center gap-1 mt-1">${av(p.creator, p.creator_avatar)}<div><b>${E(p.creator)}</b><div class="xs muted ltr">${E(p.creator_mobile || '')}</div></div></div></div>
        <button type="button" class="card flat ap-card ap-link" data-ap-show="members"><div class="xs muted">تعداد اعضا</div><div class="ap-num">${F(p.members)} <span class="xs faint">از ${F(p.max_members)}</span></div><div class="xs ap-more">نمایش اعضا <i class="fa-solid fa-arrow-left"></i></div></button>
        <button type="button" class="card flat ap-card ap-link" data-ap-show="tasks"><div class="xs muted">تعداد تسک‌ها</div><div class="ap-num">${F(p.tasks)}</div><div class="xs muted">${F(p.tasks_done)} انجام‌شده${p.tasks_blocked ? ' • ' + F(p.tasks_blocked) + ' مسدود' : ''}</div><div class="xs ap-more">نمایش تسک‌ها <i class="fa-solid fa-arrow-left"></i></div></button>
        <div class="card flat ap-card"><div class="xs muted">تاریخ ایجاد</div><div class="bold mt-1">${E(p.created_fa)}</div><div class="xs muted mt-1">اولویت: ${E(p.priority_type)}</div></div>
      </div>`;
  };

  TK.on('click', 'tr[data-aproj]', (e, tr) => open(+tr.dataset.aproj));
  TK.on('keydown', 'tr[data-aproj]', (e, tr) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(+tr.dataset.aproj); } });
  m.addEventListener('click', (e) => {
    const s = e.target.closest('[data-ap-show]'); if (s) { cur = { kind: s.dataset.apShow, page: 1, per: cur.per }; loadPart(); return; }
    const pg = e.target.closest('[data-ap-page]'); if (pg && !pg.disabled) { cur.page = +pg.dataset.apPage; loadPart(); }
  });
  m.addEventListener('change', (e) => { if (e.target.matches('[data-ap-per]')) { cur.per = +e.target.value; cur.page = 1; loadPart(); } });
});

/* صفحه‌بندی مشترک پنجره‌ها: data-ap-page / data-ap-per (همان ظاهر بالا) */
function apPager(d, PER, F) {
  if (d.pages <= 1 && d.total <= PER[0]) return '';
  const btn = (p, label, on = false, dis = false) => `<button type="button" class="btn btn-xs${on ? ' btn-primary' : ''}" data-ap-page="${p}"${dis ? ' disabled' : ''}>${label}</button>`;
  let h = btn(d.page - 1, '<i class="fa-solid fa-chevron-right"></i>', false, d.page <= 1);
  const from = Math.max(1, d.page - 2), to = Math.min(d.pages, d.page + 2);
  if (from > 1) h += btn(1, F(1)) + (from > 2 ? '<span class="faint">…</span>' : '');
  for (let i = from; i <= to; i++) h += btn(i, F(i), i === d.page);
  if (to < d.pages) h += (to < d.pages - 1 ? '<span class="faint">…</span>' : '') + btn(d.pages, F(d.pages));
  h += btn(d.page + 1, '<i class="fa-solid fa-chevron-left"></i>', false, d.page >= d.pages);
  return `<div class="flex between items-center wrap gap-2 mt-1 ap-pager"><div class="flex gap-1 items-center wrap">${h}</div>
    <label class="flex items-center gap-1 small muted users-per">نمایش در هر صفحه <select class="input" data-ap-per>${PER.map((n) => `<option value="${n}"${n === d.per ? ' selected' : ''}>${F(n)}</option>`).join('')}</select></label></div>`;
}

/* پنل مدیر ← مدیریت کاربران: منوی ⋮ هر ردیف + پنجره‌ی «جزئیات» کاربر */
function usersPart(E, F, PER) {
  const dm = TK.qs('#m-udetail'); if (!dm) return;
  const tm = TK.qs('#m-utasks'), body = TK.qs('#ud-body'), tbody = TK.qs('#ut-body');
  // منوی ⋮: بیرون از جدول (position: fixed) تا در اسکرول جدول بریده نشود
  const closeMenus = () => TK.qsa('.u-menu.open').forEach((x) => { x.classList.remove('open'); x.querySelector('[data-umenu]')?.setAttribute('aria-expanded', 'false'); });
  TK.on('click', '[data-umenu]', (e, b) => {
    e.stopPropagation();
    const box = b.closest('.u-menu'), was = box.classList.contains('open'); closeMenus(); if (was) return;
    box.classList.add('open'); b.setAttribute('aria-expanded', 'true');
    const list = box.querySelector('.u-menu-list'), r = b.getBoundingClientRect();
    const h = list.offsetHeight, up = r.bottom + h + 8 > innerHeight;
    list.style.top = (up ? r.top - h - 6 : r.bottom + 6) + 'px'; list.style.left = Math.max(8, r.left) + 'px';
  });
  document.addEventListener('click', (e) => { if (e.target.closest('[data-umenu]')) return; if (!e.target.closest('.u-menu-list')) closeMenus(); else setTimeout(closeMenus, 0); });
  document.addEventListener('scroll', closeMenus, true);

  let user = null, tq = { page: 1, per: PER[0] };
  const enter = async (to, why) => {
    if (!user.can_enter) { TK.info(user.enter_why || why || 'ورود ممکن نیست.', { title: 'ورود به پنل کاربر', icon: 'fa-lock' }); return; }
    if (!(await TK.confirm(`با نقش مدیر وارد پنل «${user.name}» می‌شوید و همان صفحه باز می‌شود. ادامه می‌دهید؟`, { okText: 'ورود به پنل', danger: false }))) return;
    const r = await TK.post(`/admin/users/${user.id}/impersonate`, { to });
    if (TK.ok(r) && r.redirect) { location.href = r.redirect; return; }       // لایه‌ی پنل عوض می‌شود: بارگذاری کامل
    TK.toast(r.message || 'خطا', 'error');
  };
  const open = async (id) => {
    body.innerHTML = '<div class="muted small"><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری…</div>';
    TK.modal.open(dm);
    const r = await TK.get(`/admin/users/${id}/details`);
    if (!TK.ok(r)) { body.innerHTML = `<div class="text-danger small">${E(r.message || 'خطا')}</div>`; return; }
    user = r.user; const u = user;
    TK.qs('#ud-title').textContent = u.name;
    const projects = r.projects.length ? r.projects.map((p) => `<button type="button" class="ud-proj" data-ud-proj="${E(p.to)}" title="ورود به پنل کاربر و فهرست تسک‌های این پروژه">
        <span class="avatar sm sq"><i class="fa-solid fa-diagram-project"></i></span><span class="grow min0"><b class="truncate">${E(p.title)}</b><span class="xs muted">${E(p.role)} • ${F(p.members)} عضو • ${F(p.my_tasks)} تسک او${p.blocked ? ' • مسدود' : ''}</span></span>
        <i class="fa-solid fa-arrow-left faint"></i></button>`).join('') : '<div class="muted small">عضو هیچ پروژه‌ای نیست.</div>';
    body.innerHTML = `<div class="flex items-center gap-2 mb-2"><span class="avatar lg">${u.avatar ? `<img src="${E(u.avatar)}" alt="">` : E(TK.initial(u.name))}</span>
        <div><b>${E(u.name)}</b><div class="xs muted ltr">${E(u.mobile)}${u.email ? ' • ' + E(u.email) : ''}</div><span class="badge mt-1">${E(u.role)}</span></div></div>
      <div class="grid ap-cards mb-2">
        <div class="card flat ap-card"><div class="xs muted">پروژه‌ها</div><div class="ap-num">${F(r.projects.length)}</div></div>
        <button type="button" class="card flat ap-card ap-link" data-ud-tasks><div class="xs muted">تعداد تسک‌ها</div><div class="ap-num">${F(u.tasks)}</div>
          <div class="xs muted">${F(u.tasks_done)} انجام‌شده • ${F(u.tasks_review)} منتظر تأیید${u.tasks_late ? ' • ' + F(u.tasks_late) + ' عقب‌افتاده' : ''}</div><div class="xs ap-more">نمایش تسک‌ها <i class="fa-solid fa-arrow-left"></i></div></button>
        <div class="card flat ap-card"><div class="xs muted">تاریخ ایجاد</div><div class="bold mt-1">${E(u.created_fa)}</div></div>
        <div class="card flat ap-card"><div class="xs muted">آخرین بازدید</div><div class="bold mt-1">${u.last_active_ago ? E(u.last_active_ago) : 'هنوز وارد نشده'}</div>${u.last_active_fa ? `<div class="xs muted mt-1">${E(u.last_active_fa)}</div>` : ''}</div>
      </div>
      <h4><i class="fa-solid fa-diagram-project"></i> پروژه‌ها <span class="xs faint">(کلیک: ورود به پنل کاربر و تسک‌های همان پروژه)</span></h4>
      <div class="ud-projs">${projects}</div>`;
  };
  const loadTasks = async () => {
    tbody.innerHTML = '<div class="muted small"><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری…</div>';
    const r = await TK.get(`/admin/users/${user.id}/tasks`, tq);
    if (!TK.ok(r)) { tbody.innerHTML = `<div class="text-danger small">${E(r.message || 'خطا')}</div>`; return; }
    const rows = r.items.map((t) => `<tr class="al-row" data-ud-task="${E(t.to)}" tabindex="0" title="ورود به پنل کاربر و باز کردن این تسک"><td class="bold">${E(t.title)}</td><td class="small">${E(t.project)}</td>
        <td><span class="badge board-st tone-${E(t.tone)}">${E(t.status)}</span></td><td class="small">${E(t.priority)}</td><td class="small${t.late ? ' prio-high' : ''}">${E(t.due_fa)}</td><td class="small muted nowrap">${E(t.created_fa)}</td></tr>`).join('');
    tbody.innerHTML = `<p class="hint">تسک‌هایی که مسئولشان «${E(user.name)}» است؛ با کلیک روی هر تسک با نقش مدیر وارد پنل او می‌شوید و همان تسک باز می‌شود.</p>
      <div class="table-wrap"><table class="table"><thead><tr><th>تسک</th><th>پروژه</th><th>وضعیت</th><th>اولویت</th><th>سررسید</th><th>ایجاد</th></tr></thead>
      <tbody>${rows || '<tr><td colspan="6" class="center muted">تسکی ندارد.</td></tr>'}</tbody></table></div>` + apPager(r, PER, F);
  };
  TK.on('click', '[data-user-details]', (e, b) => { closeMenus(); open(+b.dataset.userDetails); });
  dm.addEventListener('click', (e) => {
    const p = e.target.closest('[data-ud-proj]'); if (p) { enter(p.dataset.udProj); return; }
    if (e.target.closest('[data-ud-tasks]')) { tq = { page: 1, per: tq.per }; TK.qs('#ut-title').textContent = 'تسک‌های ' + user.name; TK.modal.open(tm); loadTasks(); }
  });
  tm.addEventListener('click', (e) => {
    const t = e.target.closest('[data-ud-task]'); if (t) { enter(t.dataset.udTask); return; }
    const pg = e.target.closest('[data-ap-page]'); if (pg && !pg.disabled) { tq.page = +pg.dataset.apPage; loadTasks(); }
  });
  tm.addEventListener('keydown', (e) => { const t = e.target.closest('[data-ud-task]'); if (t && e.key === 'Enter') enter(t.dataset.udTask); });
  tm.addEventListener('change', (e) => { if (e.target.matches('[data-ap-per]')) { tq.per = +e.target.value; tq.page = 1; loadTasks(); } });
}
