/* پنل مدیر ← فهرست پروژه‌ها: کلیک روی ردیف = پنجره‌ی جزئیات (سازنده، اعضا، تسک‌ها، تاریخ ایجاد)؛
   با کلیک روی «اعضا» یا «تسک‌ها» فهرست همان، با صفحه‌بندی (پیش‌فرض ۱۰، قابل انتخاب) در همان پنجره. */
document.addEventListener('tk:ready', () => {
  const m = TK.qs('#m-aproj'); if (!m) return;
  const E = TK.esc, F = TK.fa, info = TK.qs('#ap-info'), part = TK.qs('#ap-part');
  const PER = TK.boot().adminList?.per || [10, 20, 50, 100];
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
