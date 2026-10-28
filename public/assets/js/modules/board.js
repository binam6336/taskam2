document.addEventListener('tk:ready', () => {
  if (!TK.qs('#kb-board') && !TK.qs('[data-live="board-body"]')) return;
  const statuses = (() => { try { return JSON.parse(TK.qs('#kb-data').textContent).statuses; } catch { return {}; } })();
  const E = TK.esc;

  /* شمارنده‌ی ستون‌ها بعد از هر جابه‌جایی */
  const recount = () => TK.qsa('.kb-col:not([data-col="archived"])').forEach((c) => { c.querySelector('.kb-count').textContent = TK.fa(c.querySelectorAll('.kb-card').length); });

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

  /* مقصدهای مجاز هر کارت (طبق دسترسی برد تیمی) از سرور می‌آید؛ «مسدودشده» علت توقف می‌پرسد */
  const allowed = (card, status) => (card.dataset.moves || '').split(' ').includes(status);
  let pending = null; const bf = TK.qs('#f-kb-block');
  const request = (card, status) => {
    if (card.dataset.status === status) return;
    if (!allowed(card, status)) { TK.toast(`اجازه‌ی انتقال این تسک به «${statuses[status]?.label || status}» را ندارید.`, 'error'); return; }
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
    TK.qsa('.kb-col').forEach((col) => col.classList.toggle('kb-deny', col.dataset.col !== c.dataset.status && !allowed(c, col.dataset.col)));
    e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.id);
  });
  document.addEventListener('dragend', () => { dragged?.classList.remove('kb-dragging'); dragged = null; TK.qsa('.kb-col.kb-over, .kb-col.kb-deny').forEach((c) => c.classList.remove('kb-over', 'kb-deny')); });
  document.addEventListener('dragover', (e) => {
    const col = e.target.closest?.('.kb-col'); if (!col || !dragged || col.classList.contains('kb-deny')) return;
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
    m.innerHTML = '<div class="kb-menu-h">انتقال به</div>' + Object.entries(statuses).map(([k, s]) => `<button type="button" role="menuitem" data-kb-to="${E(k)}" ${k === card.dataset.status || !allowed(card, k) ? 'disabled' : ''}><i class="fa-solid ${E(s.icon)}"></i> ${E(s.label)}${k !== card.dataset.status && !allowed(card, k) ? ' <i class="fa-solid fa-lock kb-menu-lock"></i>' : ''}</button>`).join('');
    card.appendChild(m); m.querySelector('button:not([disabled])')?.focus();
  });
  TK.on('click', '[data-kb-to]', (e, b) => { const card = b.closest('.kb-card'); closeMenu(); request(card, b.dataset.kbTo); });
  document.addEventListener('click', (e) => { if (!e.target.closest('.kb-menu, [data-kb-menu]')) closeMenu(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  /* آمدن از جزئیات تسک (?task=): کارت برجسته در وسط دید */
  const f = TK.qs('.kb-card.kb-focus');
  if (f) setTimeout(() => f.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }), 150);

  /* ───── آرشیو ───── */
  const filters = () => { const u = new URLSearchParams(location.search); return { project: u.get('project') || '', mine: u.get('mine') || '', q: u.get('q') || '' }; };

  // آیکون آرشیو در ستون «انجام‌شده»: همه‌ی انجام‌شده‌های این برد (با فیلترهای فعلی)
  TK.on('click', '[data-kb-archive-all]', async (e, b) => {
    const n = Number(b.dataset.kbArchiveAll) || 0;
    if (!(await TK.confirm(`${TK.fa(n)} تسک انجام‌شده به آرشیو برد منتقل شود؟ روی خود تسک‌ها اثری ندارد و از آرشیو قابل بازگردانی است.`, { okText: 'انتقال به آرشیو', danger: false }))) return;
    TK.busy(b, true);
    const r = await TK.post('/board/archive-done', filters());
    TK.busy(b, false);
    TK.toast(r?.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) TK.reload();
  });

  // «مشاهده بیشتر»: پاپ‌آپ همه‌ی آرشیو
  let archItems = [];
  const archRender = () => {
    const q = (TK.qs('#kb-arch-q').value || '').trim().toLowerCase();
    const rows = archItems.filter((t) => !q || t.title.toLowerCase().includes(q) || (t.project || '').toLowerCase().includes(q));
    TK.qs('#kb-arch-count').textContent = TK.fa(archItems.length);
    TK.qs('#kb-arch-list').innerHTML = rows.map((t) => `<div class="item kb-arch-item">
        <span class="avatar sm" title="${E(t.assignee || 'بدون مسئول')}">${t.assignee_avatar ? `<img src="${E(t.assignee_avatar)}" alt="">` : E(TK.initial(t.assignee || '؟'))}</span>
        <div class="grow"><a class="bold" href="${E(t.url)}">${E(t.title)}</a>
          <div class="xs muted">${t.project ? `<i class="fa-solid fa-diagram-project"></i> ${E(t.project)} • ` : ''}آرشیو: ${E(t.archived_fa)}${t.completed_fa ? ` • انجام: ${E(t.completed_fa)}` : ''}</div></div>
        ${t.can_restore ? `<button type="button" class="btn btn-sm" data-kb-restore="${t.id}" title="بازگرداندن به انجام‌شده"><i class="fa-solid fa-rotate-left"></i> بازگردانی</button>` : ''}
      </div>`).join('') || '<div class="empty small">موردی در آرشیو نیست.</div>';
  };
  const archLoad = async () => {
    TK.qs('#kb-arch-list').innerHTML = '<div class="empty small"><span class="spin"></span></div>';
    const r = await TK.get('/board/archive', filters());
    archItems = TK.ok(r) ? r.items : [];
    if (!TK.ok(r)) TK.toast(r?.message || 'خطا در دریافت آرشیو', 'error');
    archRender();
  };
  TK.on('click', '[data-kb-archive-more]', () => { TK.qs('#kb-arch-q').value = ''; TK.modal.open('m-kb-archive'); archLoad(); });
  TK.qs('#kb-arch-q')?.addEventListener('input', archRender);
  TK.on('click', '[data-kb-restore]', async (e, b) => {
    TK.busy(b, true);
    const r = await TK.post('/board/tasks/' + b.dataset.kbRestore + '/move', { status: 'done' });
    TK.toast(r?.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) { archItems = archItems.filter((t) => t.id !== Number(b.dataset.kbRestore)); archRender(); TK.reload(); } else TK.busy(b, false);
  });
});
