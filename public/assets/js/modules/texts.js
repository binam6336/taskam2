document.addEventListener('tk:ready', () => {
  const grid = TK.qs('#text-grid'); if (!grid) return;
  const E = TK.esc; let items = TK.boot().texts || [];
  const form = TK.qs('#f-text');

  const draw = () => {
    TK.qs('#text-empty').classList.toggle('hidden', items.length > 0);
    grid.innerHTML = items.map((t) => `
      <div class="card hover text-card" draggable="true" data-id="${E(t.id)}">
        <div class="flex between items-center gap-1 mb-1"><b class="truncate"><i class="fa-solid fa-grip-vertical sort-handle"></i> ${E(t.title)}</b>
          <div class="flex gap-1"><button class="btn btn-xs" data-copy-text="${E(t.id)}"><i class="fa-regular fa-copy"></i> کپی</button>
          <button class="btn btn-xs btn-icon" data-edit-text="${E(t.id)}"><i class="fa-solid fa-pen"></i></button>
          <button class="btn btn-xs btn-icon btn-danger" data-del-text="${E(t.id)}"><i class="fa-solid fa-trash"></i></button></div></div>
        <pre>${E(t.content)}</pre></div>`).join('');
  };
  draw();

  const load = async (q = '') => { const r = await TK.get('/texts/data', q ? { search: q } : {}); if (r.success) { items = r.data; draw(); } };
  TK.qs('#text-search')?.addEventListener('input', TK.debounce((e) => load(e.target.value.trim()), 300));

  TK.qs('#btn-new-text').addEventListener('click', () => { form.reset(); form.id.value = ''; TK.qs('#text-modal-title').textContent = 'متن جدید'; TK.modal.open('m-text'); });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = form.id.value, fd = new FormData(form);
    const r = await TK.http(id ? '/texts/' + id + '/update' : '/texts', { method: 'POST', form: fd });
    if (r.success) { TK.toast(r.message); TK.modal.close('m-text'); load(TK.qs('#text-search').value.trim()); } else TK.toast(r.error || 'خطا', 'error');
  });

  grid.addEventListener('click', async (e) => {
    const b = e.target.closest('button'); if (!b) return;
    const find = (id) => items.find((x) => x.id === id);
    if (b.dataset.copyText) TK.copy(find(b.dataset.copyText).content);
    if (b.dataset.editText) { const t = find(b.dataset.editText); form.id.value = t.id; form.title.value = t.title; form.content.value = t.content; TK.qs('#text-modal-title').textContent = 'ویرایش متن'; TK.modal.open('m-text'); }
    if (b.dataset.delText && await TK.confirm('این متن حذف شود؟')) { const r = await TK.post('/texts/' + b.dataset.delText + '/delete'); if (r.success) { TK.toast(r.message); items = items.filter((x) => x.id !== b.dataset.delText); draw(); } else TK.toast(r.error, 'error'); }
  });

  // drag & drop reorder
  let drag = null;
  grid.addEventListener('dragstart', (e) => { drag = e.target.closest('.text-card'); drag?.classList.add('dragging'); });
  grid.addEventListener('dragend', () => { drag?.classList.remove('dragging'); TK.qsa('.drag-over', grid).forEach((x) => x.classList.remove('drag-over')); });
  grid.addEventListener('dragover', (e) => { e.preventDefault(); const t = e.target.closest('.text-card'); TK.qsa('.drag-over', grid).forEach((x) => x.classList.remove('drag-over')); if (t && t !== drag) t.classList.add('drag-over'); });
  grid.addEventListener('drop', async (e) => {
    e.preventDefault(); const t = e.target.closest('.text-card'); if (!t || !drag || t === drag) return;
    const nodes = TK.qsa('.text-card', grid), from = nodes.indexOf(drag), to = nodes.indexOf(t);
    const [m] = items.splice(from, 1); items.splice(to, 0, m); draw();
    const r = await TK.http('/texts/reorder', { method: 'POST', data: { order: items.map((x) => x.id) } });
    if (!r.success) TK.toast(r.error || 'ذخیره‌ی ترتیب ناموفق بود', 'error');
  });
});
