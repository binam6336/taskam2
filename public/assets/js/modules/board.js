document.addEventListener('tk:ready', () => {
  if (!TK.qs('#kb-board') && !TK.qs('[data-live="board-body"]')) return;
  const statuses = (() => { try { return JSON.parse(TK.qs('#kb-data').textContent).statuses; } catch { return {}; } })();
  const E = TK.esc;

  /* شمارنده‌ی ستون‌ها بعد از هر جابه‌جایی */
  const recount = () => TK.qsa('.kb-col').forEach((c) => { c.querySelector('.kb-count').textContent = TK.fa(c.querySelectorAll('.kb-card').length); });

  /* انتقال: جابه‌جایی فوری در صفحه، اگر سرور رد کرد برمی‌گردد */
  const move = async (card, status, reason = '') => {
    const from = card?.dataset.status;
    if (!card || from === status) return;
    const list = TK.qs(`[data-drop="${status}"]`), back = card.parentElement, next = card.nextElementSibling;
    list.prepend(card); card.dataset.status = status; recount();
    card.classList.add('kb-saving');
    const r = await TK.post('/board/tasks/' + card.dataset.id + '/move', { status, reason });
    card.classList.remove('kb-saving');
    if (!TK.ok(r)) {
      back.insertBefore(card, next); card.dataset.status = from; recount();
      TK.toast(r?.message || 'خطا در جابه‌جایی', 'error');
      return;
    }
    TK.toast(r.message, 'success', 1800);
    TK.reload();                               // علت توقف، مرتب‌سازی و شمارنده‌ها از سرور
  };

  /* «مسدودشده» علت توقف می‌پرسد */
  let pending = null; const bf = TK.qs('#f-kb-block');
  const request = (card, status) => {
    if (status !== 'blocked') return move(card, status);
    pending = card; bf.reset(); TK.modal.open('m-kb-block');
  };
  bf.addEventListener('submit', (e) => {
    e.preventDefault();
    const card = pending; pending = null; TK.modal.close('m-kb-block');
    if (card) move(card, 'blocked', bf.reason.value.trim());
  });

  /* کشیدن و رها کردن (دسکتاپ) */
  let dragged = null;
  document.addEventListener('dragstart', (e) => {
    const c = e.target.closest?.('.kb-card[draggable="true"]'); if (!c) return;
    dragged = c; c.classList.add('kb-dragging');
    e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.id);
  });
  document.addEventListener('dragend', () => { dragged?.classList.remove('kb-dragging'); dragged = null; TK.qsa('.kb-col.kb-over').forEach((c) => c.classList.remove('kb-over')); });
  document.addEventListener('dragover', (e) => {
    const col = e.target.closest?.('.kb-col'); if (!col || !dragged) return;
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    TK.qsa('.kb-col.kb-over').forEach((c) => c !== col && c.classList.remove('kb-over'));
    col.classList.add('kb-over');
  });
  document.addEventListener('drop', (e) => {
    const col = e.target.closest?.('.kb-col'); if (!col || !dragged) return;
    e.preventDefault(); col.classList.remove('kb-over');
    request(dragged, col.dataset.col);
  });

  /* منوی «انتقال به…» (لمسی و صفحه‌کلید) */
  const closeMenu = () => TK.qsa('.kb-menu').forEach((m) => m.remove());
  TK.on('click', '[data-kb-menu]', (e, b) => {
    const card = b.closest('.kb-card'), open = card.querySelector('.kb-menu'); closeMenu();
    if (open) return;
    const m = document.createElement('div'); m.className = 'kb-menu'; m.setAttribute('role', 'menu');
    m.innerHTML = '<div class="kb-menu-h">انتقال به</div>' + Object.entries(statuses).map(([k, s]) => `<button type="button" role="menuitem" data-kb-to="${E(k)}" ${k === card.dataset.status ? 'disabled' : ''}><i class="fa-solid ${E(s.icon)}"></i> ${E(s.label)}</button>`).join('');
    card.appendChild(m); m.querySelector('button:not([disabled])')?.focus();
  });
  TK.on('click', '[data-kb-to]', (e, b) => { const card = b.closest('.kb-card'); closeMenu(); request(card, b.dataset.kbTo); });
  document.addEventListener('click', (e) => { if (!e.target.closest('.kb-menu, [data-kb-menu]')) closeMenu(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
});
