/* یادداشت‌ها (v6.0؛ قبلاً «متن‌های آماده»): یادداشت چسبان رنگی + جستجو + کپی + مرتب‌سازی با کشیدن */
document.addEventListener('tk:ready', () => {
  const grid = TK.qs('#text-grid'); if (!grid) return;
  const E = TK.esc; let items = TK.boot().texts || [];
  const COLORS = TK.boot().noteColors || { yellow: 'زرد' };
  const form = TK.qs('#f-text'), search = TK.qs('#text-search');

  const card = (t) => `
    <article class="note-card note-c-${E(t.color || 'yellow')}" draggable="true" data-id="${E(t.id)}" aria-label="${E(t.title || 'یادداشت')}">
      <header class="note-head"><i class="fa-solid fa-grip-vertical note-grip" aria-hidden="true" title="برای جابه‌جایی بکشید"></i>
        ${t.title ? `<h3 class="note-title">${E(t.title)}</h3>` : '<span class="grow"></span>'}
        <div class="note-acts">
          <button type="button" class="note-btn" data-copy-text="${E(t.id)}" title="کپی متن" aria-label="کپی متن"><i class="fa-regular fa-copy"></i></button>
          <button type="button" class="note-btn" data-edit-text="${E(t.id)}" title="ویرایش" aria-label="ویرایش یادداشت"><i class="fa-solid fa-pen"></i></button>
          <button type="button" class="note-btn danger" data-del-text="${E(t.id)}" title="حذف" aria-label="حذف یادداشت"><i class="fa-solid fa-trash"></i></button>
        </div></header>
      <div class="note-body" data-edit-text="${E(t.id)}">${E(t.content)}</div>
      <footer class="note-foot"><div class="note-dots" role="group" aria-label="رنگ یادداشت">${Object.entries(COLORS).map(([k, l]) =>
        `<button type="button" class="note-dot note-c-${E(k)}${k === (t.color || 'yellow') ? ' on' : ''}" data-note-color="${E(k)}" data-id="${E(t.id)}" title="${E(l)}" aria-label="رنگ ${E(l)}" aria-pressed="${k === (t.color || 'yellow')}"></button>`).join('')}</div>
</footer>
    </article>`;
  const draw = () => {
    TK.qs('#text-empty').classList.toggle('hidden', items.length > 0);
    TK.qs('#text-empty-msg').textContent = search?.value.trim() ? 'یادداشتی با این جستجو پیدا نشد.' : 'هنوز یادداشتی ندارید. «یادداشت جدید» را بزنید.';
    grid.innerHTML = items.map(card).join('');
  };
  draw();

  const load = async (q = '') => { const r = await TK.get('/notes/data', q ? { search: q } : {}); if (r.success) { items = r.data; draw(); } };
  search?.addEventListener('input', TK.debounce((e) => load(e.target.value.trim()), 300));
  // از جستجوی سراسری (v7.13): /notes?q=… همان جستجو را پر و اجرا می‌کند
  const q0 = new URLSearchParams(location.search).get('q');
  if (q0 && search) { search.value = q0.slice(0, 100); load(search.value.trim()); }

  const openForm = (t) => {
    form.reset(); form.id.value = t ? t.id : '';
    if (t) { form.title.value = t.title || ''; form.content.value = t.content; }
    const c = form.querySelector(`input[name=color][value="${(t && t.color) || 'yellow'}"]`); if (c) c.checked = true;
    TK.qs('#text-modal-title').textContent = t ? 'ویرایش یادداشت' : 'یادداشت جدید';
    TK.modal.open('m-text'); setTimeout(() => form.content.focus(), 60);
  };
  TK.qs('#btn-new-text').addEventListener('click', () => openForm(null));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = form.id.value, fd = new FormData(form);
    const r = await TK.http(id ? '/notes/item/' + id + '/update' : '/notes', { method: 'POST', form: fd });
    if (r.success) { TK.toast(r.message); TK.modal.close('m-text'); load(search.value.trim()); } else TK.toast(r.error || 'خطا', 'error');
  });

  grid.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copy-text],[data-edit-text],[data-del-text],[data-note-color]'); if (!b) return;
    const find = (id) => items.find((x) => x.id === id);
    if (b.dataset.copyText) { TK.copy(find(b.dataset.copyText).content); return; }
    if (b.dataset.noteColor) {
      const t = find(b.dataset.id); if (!t || t.color === b.dataset.noteColor) return;
      const prev = t.color; t.color = b.dataset.noteColor; draw();               // فوری؛ اگر ذخیره نشد برمی‌گردد
      const r = await TK.post('/notes/item/' + t.id + '/update', { color: t.color });
      if (!r.success) { t.color = prev; draw(); TK.toast(r.error || 'خطا', 'error'); }
      return;
    }
    if (b.dataset.editText) { if (getSelection().toString()) return; openForm(find(b.dataset.editText)); return; }
    if (b.dataset.delText && await TK.confirm('این یادداشت حذف شود؟')) { const r = await TK.post('/notes/item/' + b.dataset.delText + '/delete'); if (r.success) { TK.toast(r.message); items = items.filter((x) => x.id !== b.dataset.delText); draw(); } else TK.toast(r.error, 'error'); }
  });

  // جابه‌جایی با کشیدن
  let drag = null;
  grid.addEventListener('dragstart', (e) => { drag = e.target.closest('.note-card'); drag?.classList.add('dragging'); });
  grid.addEventListener('dragend', () => { drag?.classList.remove('dragging'); TK.qsa('.drag-over', grid).forEach((x) => x.classList.remove('drag-over')); });
  grid.addEventListener('dragover', (e) => { e.preventDefault(); const t = e.target.closest('.note-card'); TK.qsa('.drag-over', grid).forEach((x) => x.classList.remove('drag-over')); if (t && t !== drag) t.classList.add('drag-over'); });
  grid.addEventListener('drop', async (e) => {
    e.preventDefault(); const t = e.target.closest('.note-card'); if (!t || !drag || t === drag) return;
    const nodes = TK.qsa('.note-card', grid), from = nodes.indexOf(drag), to = nodes.indexOf(t);
    const [m] = items.splice(from, 1); items.splice(to, 0, m); draw();
    const r = await TK.http('/notes/reorder', { method: 'POST', data: { order: items.map((x) => x.id) } });
    if (!r.success) TK.toast(r.error || 'ذخیره‌ی ترتیب ناموفق بود', 'error');
  });
});
