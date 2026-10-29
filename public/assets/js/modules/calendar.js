/* تقویم تسک‌ها: ماه شمسی، تعداد تسک هر روز، پنجره‌ی تسک‌های روز و جابه‌جایی سررسید با کشیدن.
   مشاهده/ویرایش/تسک جدید با همان پنجره‌های صفحه‌ی تسک‌ها (TK.tasks از tasks.js). */
document.addEventListener('tk:ready', () => {
  const grid = TK.qs('#cal-grid');
  if (!grid) return;
  const J = TK.jalali, E = TK.esc, F = TK.fa;
  const WD = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
  const PR = { low: 'کم', medium: 'متوسط', high: 'زیاد' };
  const pad = (n) => String(n).padStart(2, '0');
  const isoOf = (jy, jm, jd) => J.iso(...J.j2g(jy, jm, jd));
  const today = J.today(), todayIso = isoOf(...today);

  // وضعیت از آدرس (?m=1405-8&scope=mine&done=1) تا رفرش/برگشت همان ماه را نشان دهد
  const qs = new URLSearchParams(location.search);
  const [qy, qm] = (qs.get('m') || '').split('-').map(Number);
  const st = { y: qy > 1300 && qm >= 1 && qm <= 12 ? qy : today[0], m: qy > 1300 && qm >= 1 && qm <= 12 ? qm : today[1], scope: ['mine', 'others'].includes(qs.get('scope')) ? qs.get('scope') : 'all', done: qs.get('done') === '1' };
  let items = [], byDay = {}, openDay = null, range = null;

  const syncUrl = () => {
    const p = new URLSearchParams();
    if (st.y !== today[0] || st.m !== today[1]) p.set('m', st.y + '-' + st.m);
    if (st.scope !== 'all') p.set('scope', st.scope);
    if (st.done) p.set('done', '1');
    history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : ''));
  };

  // شبکه‌ی ۶ هفته‌ای: از شنبه‌ی هفته‌ی اول ماه
  const cells = () => {
    const first = J.weekday(st.y, st.m, 1);
    let y = st.y, m = st.m - 1;
    if (m < 1) { m = 12; y--; }
    const prevLen = J.monthLen(y, m), len = J.monthLen(st.y, st.m);
    const out = [];
    for (let i = first - 1; i >= 0; i--) out.push([y, m, prevLen - i, false]);
    for (let d = 1; d <= len; d++) out.push([st.y, st.m, d, true]);
    let ny = st.y, nm = st.m + 1;
    if (nm > 12) { nm = 1; ny++; }
    for (let d = 1; out.length < 42; d++) out.push([ny, nm, d, false]);
    return out;
  };

  const load = async () => {
    const c = cells();
    range = [isoOf(...c[0].slice(0, 3)), isoOf(...c[41].slice(0, 3))];
    grid.classList.add('loading');
    const r = await TK.get('/calendar/data', { from: range[0], to: range[1], scope: st.scope, done: st.done ? 1 : 0 });
    grid.classList.remove('loading');
    if (!TK.ok(r)) return TK.toast(r.message || 'خطا در دریافت تقویم', 'error');
    items = r.items || [];
    byDay = {};
    items.forEach((t) => (byDay[t.date] = byDay[t.date] || []).push(t));
    render(c);
    if (openDay) showDay(openDay, true);
  };
  // بعد از ویرایش/تکمیل/یادداشت در پنجره‌های تسک، به‌جای رفرش صفحه فقط تقویم تازه شود
  TK.reload = load;

  const chip = (t) => `<div class="cal-chip${t.done ? ' done' : ''}${t.overdue ? ' late' : ''} p-${E(t.priority)}" ${t.can_move ? 'draggable="true"' : ''} data-task="${t.id}" title="${E(t.title)}">
      <i></i>${t.time ? `<b>${F(t.time)}</b>` : ''}<span>${E(t.title)}</span></div>`;

  const render = (c) => {
    TK.qs('#cal-title').textContent = J.MONTHS[st.m - 1] + ' ' + F(st.y);
    grid.innerHTML = c.map(([y, m, d, cur], i) => {
      const iso = isoOf(y, m, d), list = byDay[iso] || [], open = list.filter((t) => !t.done).length;
      const heat = open >= 5 ? 'h3' : open >= 3 ? 'h2' : open ? 'h1' : '';
      const g = iso.split('-');
      return `<div class="cal-day${cur ? '' : ' out'}${iso === todayIso ? ' today' : ''}${i % 7 === 6 ? ' fri' : ''}${iso < todayIso ? ' past' : ''}" role="gridcell" tabindex="0" data-day="${iso}" aria-label="${F(d)} ${J.MONTHS[m - 1]}${list.length ? '، ' + F(list.length) + ' تسک' : ''}">
        <div class="cal-day-head"><span class="cal-num">${F(d)}</span>${iso === todayIso ? '<span class="cal-today-tag">امروز</span>' : ''}<span class="cal-g ltr">${+g[2]}/${+g[1]}</span>
          ${list.length ? `<span class="cal-count ${heat}" title="${F(list.length)} تسک${open !== list.length ? '، ' + F(list.length - open) + ' انجام‌شده' : ''}">${F(list.length)}</span>` : ''}</div>
        <div class="cal-chips">${list.slice(0, 3).map(chip).join('')}${list.length > 3 ? `<div class="cal-more">+${F(list.length - 3)} تسک دیگر</div>` : ''}</div>
        <div class="cal-dots">${list.slice(0, 6).map((t) => `<i class="p-${E(t.priority)}${t.done ? ' done' : ''}"></i>`).join('')}</div>
      </div>`;
    }).join('');
    // آمار ماه جاری
    const inMonth = items.filter((t) => { const [jy, jm] = J.g2j(...t.date.split('-').map(Number)); return jy === st.y && jm === st.m; });
    const late = inMonth.filter((t) => t.overdue).length, done = inMonth.filter((t) => t.done).length, tod = (byDay[todayIso] || []).filter((t) => !t.done).length;
    const busiest = Object.entries(byDay).filter(([d]) => inMonth.some((t) => t.date === d)).sort((a, b) => b[1].length - a[1].length)[0];
    TK.qs('#cal-stats').innerHTML = `<span><i class="fa-solid fa-list-check"></i> ${F(inMonth.length)} تسک در این ماه</span>
      <span class="${tod ? 'warn' : ''}"><i class="fa-solid fa-sun"></i> ${F(tod)} تسک امروز</span>
      <span class="${late ? 'danger' : ''}"><i class="fa-solid fa-hourglass-end"></i> ${F(late)} عقب‌افتاده</span>
      ${st.done ? `<span class="ok"><i class="fa-solid fa-circle-check"></i> ${F(done)} انجام‌شده</span>` : ''}
      ${busiest && busiest[1].length > 1 ? `<span><i class="fa-solid fa-fire"></i> شلوغ‌ترین روز: ${E(dayName(busiest[0]))} (${F(busiest[1].length)})</span>` : ''}`;
    TK.qsa('[data-cal-scope]').forEach((b) => b.classList.toggle('on', b.dataset.calScope === st.scope));
    TK.qs('#cal-done').checked = st.done;
  };

  const dayName = (iso, full) => {
    const [jy, jm, jd] = J.g2j(...iso.split('-').map(Number));
    return (full ? WD[J.weekday(jy, jm, jd)] + ' ' : '') + F(jd) + ' ' + J.MONTHS[jm - 1] + (full ? ' ' + F(jy) : '');
  };

  /* ───── پنجره‌ی تسک‌های یک روز ───── */
  const prioTxt = (t) => (t.priority_type === 'percent' && t.percent !== null ? F(t.percent) + '٪' : PR[t.priority] || '');
  const showDay = (iso, keep) => {
    openDay = iso;
    const list = byDay[iso] || [];
    TK.qs('#cal-day-title').textContent = dayName(iso, true) + (list.length ? ' — ' + F(list.length) + ' تسک' : '');
    const tomorrow = (() => { const d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + 1); return J.iso(d.getFullYear(), d.getMonth() + 1, d.getDate()); })();
    TK.qs('#cal-day-list').innerHTML = list.length ? list.map((t) => `<div class="cal-task${t.done ? ' done' : ''}${t.overdue ? ' late' : ''}">
        <div class="cal-task-time">${t.time ? F(t.time) : '<span class="faint">بدون ساعت</span>'}</div>
        <div class="cal-task-main">
          <a href="#" class="cal-task-title" data-open-task="${t.id}">${t.done ? '<i class="fa-solid fa-circle-check"></i> ' : ''}${E(t.title)}</a>
          <div class="cal-task-meta">
            <span class="badge p-${E(t.priority)}">${E(prioTxt(t))}</span>
            ${t.label ? `<span><i class="fa-solid fa-tag" style="color:${E(t.label_color || 'var(--muted)')}"></i> ${E(t.label)}</span>` : ''}
            ${t.project ? `<span><i class="fa-solid fa-diagram-project"></i> ${E(t.project)}</span>` : ''}
            ${t.assignee ? `<span class="cal-who"><span class="avatar xs">${t.assignee_avatar ? `<img src="${E(t.assignee_avatar)}" alt="">` : E(TK.initial(t.assignee))}</span>${E(t.assignee)}</span>` : ''}
            ${t.overdue ? '<span class="cal-late-tag">عقب‌افتاده</span>' : ''}
          </div>
        </div>
        <div class="cal-task-act">
          ${t.can_move && !t.done ? `<button type="button" class="btn btn-sm" data-cal-move="${t.id}" data-to="${tomorrow}" title="انتقال سررسید به روز بعد"><i class="fa-solid fa-forward"></i> فردا</button>` : ''}
          ${t.can_edit ? `<button type="button" class="btn btn-sm btn-icon" data-cal-edit="${t.id}" title="ویرایش و زمان‌بندی دوباره" aria-label="ویرایش"><i class="fa-solid fa-pen"></i></button>` : ''}
        </div>
      </div>`).join('') : `<div class="cal-empty"><i class="fa-regular fa-calendar-check"></i><p>برای این روز تسکی ثبت نشده است.</p></div>`;
    if (!keep) TK.modal.open('m-cal-day');
  };
  TK.qs('#m-cal-day').addEventListener('tk:close', () => (openDay = null));

  grid.addEventListener('click', (e) => {
    const day = e.target.closest('.cal-day');
    if (day) showDay(day.dataset.day);
  });
  grid.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('.cal-day')) { e.preventDefault(); showDay(e.target.dataset.day); }
  });

  /* ───── جابه‌جایی سررسید ───── */
  const move = async (id, date) => {
    const t = items.find((x) => x.id === +id);
    if (!t || t.date === date) return;
    const r = await TK.post('/tasks/' + id + '/update', { due_date: date });
    TK.toast(TK.ok(r) ? `سررسید «${t.title}» به ${dayName(date, true)} منتقل شد.` : r.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) load();
  };
  TK.on('click', '[data-cal-move]', (e, b) => move(b.dataset.calMove, b.dataset.to));
  TK.on('click', '[data-cal-edit]', (e, b) => { const t = items.find((x) => x.id === +b.dataset.calEdit); if (t?.edit) TK.tasks?.openEdit(t.edit); });

  let dragId = null;
  grid.addEventListener('dragstart', (e) => {
    const c = e.target.closest('.cal-chip[draggable]');
    if (!c) return;
    dragId = c.dataset.task;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', dragId);
    c.classList.add('dragging');
  });
  grid.addEventListener('dragend', () => { dragId = null; TK.qsa('.cal-day.drop, .cal-chip.dragging', grid).forEach((x) => x.classList.remove('drop', 'dragging')); });
  grid.addEventListener('dragover', (e) => {
    const d = e.target.closest('.cal-day');
    if (!d || !dragId) return;
    e.preventDefault();
    TK.qsa('.cal-day.drop', grid).forEach((x) => x !== d && x.classList.remove('drop'));
    d.classList.add('drop');
  });
  grid.addEventListener('drop', (e) => {
    const d = e.target.closest('.cal-day');
    if (!d || !dragId) return;
    e.preventDefault();
    const id = dragId;
    dragId = null;
    d.classList.remove('drop');
    move(id, d.dataset.day);
  });

  /* ───── نوار ابزار ───── */
  const go = (delta) => { st.m += delta; if (st.m > 12) { st.m = 1; st.y++; } if (st.m < 1) { st.m = 12; st.y--; } syncUrl(); load(); };
  TK.on('click', '[data-cal-prev]', () => go(-1));
  TK.on('click', '[data-cal-next]', () => go(1));
  TK.on('click', '[data-cal-today]', () => { st.y = today[0]; st.m = today[1]; syncUrl(); load(); });
  TK.on('click', '[data-cal-scope]', (e, b) => { st.scope = b.dataset.calScope; syncUrl(); load(); });
  TK.qs('#cal-done').addEventListener('change', (e) => { st.done = e.target.checked; syncUrl(); load(); });
  TK.on('click', '[data-cal-new]', () => TK.tasks?.openNew());
  TK.on('click', '[data-cal-day-new]', () => TK.tasks?.openNew({ date: openDay || todayIso }));
  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select') || TK.qs('.modal.open')) return;
    if (e.key === 'ArrowRight') go(-1);
    if (e.key === 'ArrowLeft') go(1);
  });

  load();
});
