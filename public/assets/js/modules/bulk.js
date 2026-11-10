/* «تغییر گروهی» تسک‌ها (v7.8) — صفحه‌ی تسک‌ها (.item#task-…) و بورد (.kb-card[data-id]).
   حالت انتخاب: کلیک روی ردیف/کارت = انتخاب (به‌جای باز شدن جزئیات)؛ نوار پایین؛ پنجره‌ی #m-bulk ← POST /tasks/bulk */
document.addEventListener('tk:ready', () => {
  const bar = TK.qs('#bulk-bar'); if (!bar) return;
  const html = document.documentElement, sel = new Set();
  const items = () => TK.qsa('.item[id^="task-"], .kb-card[data-id]');
  const idOf = (el) => el.dataset.id || el.id.replace(/^task-/, '');
  const paint = () => {
    items().forEach((el) => {
      let ck = el.querySelector(':scope > .bulk-ck');
      if (html.classList.contains('bulk-on') && !ck) { ck = document.createElement('span'); ck.className = 'bulk-ck'; ck.setAttribute('aria-hidden', 'true'); ck.innerHTML = '<i class="fa-solid fa-check"></i>'; el.prepend(ck); }
      const on = sel.has(idOf(el)); el.classList.toggle('bulk-sel', on); el.setAttribute('aria-selected', String(on));
    });
    bar.querySelector('.bulk-n b').textContent = TK.fa(sel.size);
    bar.querySelector('[data-bulk-edit]').disabled = !sel.size;
  };
  const setMode = (on) => {
    html.classList.toggle('bulk-on', on); bar.hidden = !on; sel.clear();
    if (!on) TK.qsa('.bulk-ck').forEach((c) => c.remove());
    TK.qsa('[data-bulk-toggle]').forEach((b) => b.setAttribute('aria-pressed', String(on)));
    paint();
  };
  TK.on('click', '[data-bulk-toggle]', () => setMode(!html.classList.contains('bulk-on')));
  TK.on('click', '[data-bulk-cancel]', () => setMode(false));
  TK.on('click', '[data-bulk-all]', () => { const all = items().map(idOf); const full = all.every((i) => sel.has(i)); all.forEach((i) => (full ? sel.delete(i) : sel.add(i))); paint(); });
  // در حالت انتخاب، کلیک روی ردیف/کارت فقط انتخاب می‌کند (فاز capture تا جزئیات/تیک/جابه‌جایی اجرا نشود)
  document.addEventListener('click', (e) => {
    if (!html.classList.contains('bulk-on')) return;
    const el = e.target.closest('.item[id^="task-"], .kb-card[data-id]'); if (!el) return;
    e.preventDefault(); e.stopPropagation();
    const id = idOf(el); sel.has(id) ? sel.delete(id) : sel.add(id); paint();
  }, true);
  document.addEventListener('dragstart', (e) => { if (html.classList.contains('bulk-on')) e.preventDefault(); }, true);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && html.classList.contains('bulk-on') && !TK.qs('.modal.open')) setMode(false); });
  document.addEventListener('tk:live', paint);

  /* پنجره‌ی تغییر */
  const m = TK.qs('#m-bulk'), f = TK.qs('#f-bulk');
  const due = TK.datepicker(TK.qs('[data-bulk-date]', f), { name: 'due_date', placeholder: 'بدون تغییر' });
  const fill = (name, list, map) => {
    const s = f.elements[name]; TK.qsa('option', s).forEach((o, i) => { if (i > (name === 'subject_id' ? 1 : 0)) o.remove(); });
    list.forEach((x) => { const o = document.createElement('option'); [o.value, o.textContent] = map(x); s.appendChild(o); });
  };
  TK.on('click', '[data-bulk-edit]', async () => {
    if (!sel.size) return;
    const r = await TK.get('/tasks/bulk-options', { ids: [...sel].join(',') });
    if (!TK.ok(r)) return TK.toast(r.message || 'خطا', 'error');
    const d = r.data || r;
    f.reset(); due.set(''); TK.qs('[data-bulk-fails]', f).hidden = true;
    fill('status', d.statuses || [], (x) => [x.key, x.label]);
    f.elements.status.disabled = !d.statuses; TK.qs('[data-bulk-status-note]', f).hidden = !!d.statuses;
    fill('assignee_id', d.assignees || [], (x) => [x.id, x.name]);
    fill('subject_id', d.labels || [], (x) => [x.id, x.title]);
    fill('project_id', d.projects || [], (x) => [x.id, x.title]);
    TK.qs('[data-bulk-count]', f).textContent = TK.fa(d.count);
    TK.qs('[data-bulk-sub]', f).textContent = TK.fa(d.count) + ' تسک انتخاب شده؛ فقط مواردی که انتخاب کنید عوض می‌شوند';
    TK.modal.open(m);
  });
  f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(f), data = { ids: [...sel].join(',') };
    for (const [k, v] of fd) if (v !== '') data[k] = v;
    if (data.subject_id === '__none') { delete data.subject_id; data.subject_none = '1'; }
    if (Object.keys(data).length < 2) return TK.toast('هیچ موردی برای تغییر انتخاب نشده است.', 'warning');
    const btn = f.querySelector('[type=submit]'); TK.busy?.(btn, true);
    const r = await TK.post('/tasks/bulk', data);
    TK.busy?.(btn, false);
    const fails = (r.data || r).failed || [];
    TK.toast(r.message || 'خطا', TK.ok(r) ? (fails.length ? 'warning' : 'success') : 'error', fails.length ? 7000 : 4200);
    if (fails.length) {
      const box = TK.qs('[data-bulk-fails]', f); box.hidden = false;
      box.innerHTML = '<b>این تسک‌ها تغییر نکردند:</b><ul>' + fails.slice(0, 12).map((x) => `<li><span>${TK.esc(x.title)}</span><small>${TK.esc(x.message)}</small></li>`).join('') + '</ul>';
    }
    if (TK.ok(r)) { if (!fails.length) { TK.modal.close(m); setMode(false); } TK.live?.refresh ? TK.live.refresh() : TK.reload(); }
  });
});
