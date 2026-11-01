document.addEventListener('tk:ready', () => {
  if (!TK.qs('#kb-board') && !TK.qs('[data-live="board-body"]')) return;
  const statuses = (() => { try { return JSON.parse(TK.qs('#kb-data').textContent).statuses; } catch { return {}; } })();
  const E = TK.esc;

  /* اسکرول افقی برد با کشیدن موس روی فضای خالی (دسکتاپ). کارت‌ها، دکمه‌ها و فیلدها مستثنا هستند تا کشیدن کارت و کلیک‌ها مثل قبل کار کنند. */
  let pan = null;
  const NO_PAN = '.kb-card, button, a, input, select, textarea, label, [data-kb-limit], .kb-limit-pop, .kb-col-head';
  document.addEventListener('mousedown', (e) => {
    const board = e.target.closest?.('#kb-board');
    if (!board || arranging || e.button !== 0 || e.target.closest(NO_PAN) || board.scrollWidth <= board.clientWidth) return;
    pan = { board, x: e.clientX, left: board.scrollLeft, moved: false };
  });
  document.addEventListener('mousemove', (e) => {
    if (!pan) return;
    const dx = e.clientX - pan.x;
    if (!pan.moved) {
      if (Math.abs(dx) < 5) return;                       // کلیک ساده اسکرول حساب نمی‌شود
      pan.moved = true; pan.board.classList.add('kb-panning');
    }
    pan.board.scrollLeft = pan.left - dx;
    e.preventDefault();                                   // انتخاب متن هنگام کشیدن
  });
  const endPan = () => {
    if (!pan) return;
    if (pan.moved) {
      const b = pan.board;
      b.classList.remove('kb-panning');
      // کلیکِ پایان کشیدن روی چیزی اثر نگذارد (فقط همان کلیک بلافاصله بعد از رها کردن)
      const eat = (ev) => { ev.stopPropagation(); ev.preventDefault(); };
      window.addEventListener('click', eat, { capture: true, once: true });
      setTimeout(() => window.removeEventListener('click', eat, { capture: true }), 0);
    }
    pan = null;
  };
  document.addEventListener('mouseup', endPan);
  window.addEventListener('blur', endPan);

  /* شمارنده‌ی ستون‌ها بعد از هر جابه‌جایی */
  const recount = () => TK.qsa('.kb-col:not([data-col="archived"])').forEach((c) => {
    const n = c.querySelectorAll('.kb-card').length; c.querySelector('.kb-count').textContent = TK.fa(n);
    const tab = TK.qs(`[data-kb-goto="${c.dataset.col}"]`); if (tab) { tab.querySelector('span').textContent = TK.fa(n); tab.classList.toggle('empty', !n); }
  });

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

  /* مقصدهای مجاز هر کارت (طبق دسترسی بورد پروژه) از سرور می‌آید؛ «مسدودشده» علت توقف می‌پرسد */
  const allowed = (card, status) => (card.dataset.moves || '').split(' ').includes(status);
  let pending = null; const bf = TK.qs('#f-kb-block');
  const request = (card, status) => {
    if (card.dataset.status === status) return;
    if (!allowed(card, status)) { TK.toast(`اجازه‌ی انتقال این تسک به «${statuses[status]?.label || status}» را ندارید.`, 'error'); return; }
    const f = forms[status];
    if (!f) return move(card, status);
    pending = card; f.reset(); TK.modal.open('m-kb-' + (status === 'blocked' ? 'block' : 'rework'));
  };
  // «مسدودشده» علت توقف و «بازبینی مسئول» علت برگشت را می‌پرسد (هر دو اختیاری)
  const forms = { blocked: bf, rework: TK.qs('#f-kb-rework') };
  Object.entries(forms).forEach(([status, f]) => f?.addEventListener('submit', (e) => {
    e.preventDefault();
    const card = pending; pending = null; TK.modal.close(f.closest('.modal').id);
    if (card) move(card, status, f.reason.value.trim());
  }));

  /* کشیدن و رها کردن (دسکتاپ) */
  let dragged = null;
  document.addEventListener('dragstart', (e) => {
    const c = e.target.closest?.('.kb-card[draggable="true"]'); if (!c || arranging) return;
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

  /* ───── پیمایش افقی: سایه/فلش لبه‌ها وقتی ستون‌ها بیرون از دید هستند، نوار ستون‌ها با تعداد و پرش به هر ستون ───── */
  const kbRtl = () => getComputedStyle(TK.qs('#kb-board')).direction === 'rtl';
  const navState = () => {
    const b = TK.qs('#kb-board'), w = TK.qs('#kb-wrap'); if (!b || !w) return;
    const max = b.scrollWidth - b.clientWidth, pos = Math.abs(b.scrollLeft);
    const atStart = pos <= 2, atEnd = pos >= max - 2;
    w.classList.toggle('more-start', max > 2 && !atStart); w.classList.toggle('more-end', max > 2 && !atEnd);
    TK.qs('.kb-nav-start', w).hidden = max <= 2 || atStart; TK.qs('.kb-nav-end', w).hidden = max <= 2 || atEnd;
    // ستون‌هایی که الان دیده می‌شوند در نوار ستون‌ها پررنگ
    const r = b.getBoundingClientRect();
    TK.qsa('#kb-board > .kb-col').forEach((c) => {
      const cr = c.getBoundingClientRect(), vis = Math.min(cr.right, r.right) - Math.max(cr.left, r.left) > cr.width * 0.5;
      TK.qs(`[data-kb-goto="${c.dataset.col}"]`)?.classList.toggle('on', vis);
    });
  };
  document.addEventListener('scroll', (e) => { if (e.target.id === 'kb-board') navState(); }, true);
  window.addEventListener('resize', navState);
  document.addEventListener('tk:live', () => setTimeout(navState, 0));
  TK.on('click', '[data-kb-scroll]', (e, btn) => {
    const b = TK.qs('#kb-board'), step = b.clientWidth * 0.8 * Number(btn.dataset.kbScroll);
    b.scrollBy({ left: kbRtl() ? -step : step, behavior: 'smooth' });
  });
  TK.on('click', '[data-kb-goto]', (e, btn) => {
    const c = TK.qs(`#kb-board > .kb-col[data-col="${btn.dataset.kbGoto}"]`); if (!c) return;
    c.scrollIntoView({ inline: 'start', block: 'nearest', behavior: 'smooth' });
  });
  // موبایل: اگر ستون اول خالی است، از اولین ستونِ دارای کارت شروع شود (مگر کارت برجسته‌ای باشد)
  if (matchMedia('(max-width: 640px)').matches && !TK.qs('.kb-card.kb-focus')) {
    const first = TK.qsa('#kb-board > .kb-col').find((c) => c.querySelector('.kb-card'));
    const b = TK.qs('#kb-board');
    if (first && b && first !== b.firstElementChild) b.scrollLeft += first.getBoundingClientRect().right - b.getBoundingClientRect().right;
  }
  setTimeout(navState, 50);
  // موبایل: فیلترها پشت دکمه‌ی «فیلترها»
  TK.qs('#kb-filter-toggle')?.addEventListener('click', (e) => {
    const f = TK.qs('#kb-filters'), on = f.classList.toggle('open');
    e.currentTarget.setAttribute('aria-expanded', on ? 'true' : 'false');
  });

  /* ───── چیدمان ستون‌ها: دکمه‌ی کنار جستجو → کشیدن ستون‌ها (یا فلش‌ها در لمسی)؛ ترتیب برای همین کاربر ذخیره می‌شود ───── */
  let arranging = false, colDrag = null, saveT = null;
  const arrBtn = TK.qs('#kb-arrange'), arrBar = TK.qs('#kb-arrange-bar'), resetBtn = TK.qs('#kb-order-reset');
  const boardEl = () => TK.qs('#kb-board');
  const cols = () => TK.qsa('#kb-board > .kb-col');
  const keys = () => cols().map((c) => c.dataset.col);
  const rtl = () => getComputedStyle(boardEl()).direction === 'rtl';
  const decorate = () => {
    boardEl()?.classList.toggle('kb-arranging', arranging);
    cols().forEach((c, i, all) => {
      if (arranging) c.setAttribute('draggable', 'true'); else c.removeAttribute('draggable');
      c.querySelector('.kb-col-move')?.remove();
      if (!arranging) return;
      const m = document.createElement('div'); m.className = 'kb-col-move';
      m.innerHTML = `<button type="button" data-col-move="-1" ${i === 0 ? 'disabled' : ''} aria-label="انتقال به قبل"><i class="fa-solid fa-arrow-right"></i></button>`
        + '<span class="kb-col-grip" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>'
        + `<button type="button" data-col-move="1" ${i === all.length - 1 ? 'disabled' : ''} aria-label="انتقال به بعد"><i class="fa-solid fa-arrow-left"></i></button>`;
      c.prepend(m);
    });
  };
  const save = (reset = false) => {
    clearTimeout(saveT);
    saveT = setTimeout(async () => {
      const r = await TK.post('/board/order', reset ? { reset: 1 } : { order: keys().join(',') });
      if (!TK.ok(r)) { TK.toast(r?.message || 'ذخیره‌ی ترتیب ستون‌ها انجام نشد.', 'error'); return; }
      if (resetBtn) resetBtn.disabled = !!r.is_default;
      if (reset && r.order) { const b = boardEl(); r.order.forEach((k) => { const c = b.querySelector(`:scope > .kb-col[data-col="${k}"]`); if (c) b.appendChild(c); }); decorate(); }
      TK.toast(r.message, 'success', 1400);
    }, reset ? 0 : 350);
  };
  const setArrange = (on) => {
    arranging = on; arrBar.hidden = !on;
    arrBtn.classList.toggle('active', on); arrBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
    closeMenu(); decorate();
    if (on) arrBar.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  };
  arrBtn?.addEventListener('click', () => setArrange(!arranging));
  TK.qs('#kb-arrange-done')?.addEventListener('click', () => setArrange(false));
  resetBtn?.addEventListener('click', () => save(true));
  document.addEventListener('tk:live', () => { if (arranging) decorate(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && arranging) setArrange(false); });
  TK.on('click', '[data-col-move]', (e, b) => {
    const c = b.closest('.kb-col'), all = cols(), i = all.indexOf(c), j = i + Number(b.dataset.colMove);
    if (j < 0 || j >= all.length) return;
    const board = boardEl();
    board.insertBefore(c, j > i ? all[j].nextSibling : all[j]);
    decorate(); board.querySelector(`.kb-col[data-col="${c.dataset.col}"] [data-col-move="${b.dataset.colMove}"]:not([disabled])`)?.focus();
    c.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
    save();
  });
  let colStart = '';
  document.addEventListener('dragstart', (e) => {
    if (!arranging) return;
    const c = e.target.closest?.('#kb-board > .kb-col'); if (!c) return;
    colDrag = c; colStart = keys().join(); c.classList.add('kb-col-dragging');
    e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', c.dataset.col);
  });
  document.addEventListener('dragover', (e) => {
    if (!colDrag) return;
    const over = e.target.closest?.('#kb-board > .kb-col'); if (!over) return;
    e.preventDefault(); e.dataTransfer.dropEffect = 'move';
    if (over === colDrag) return;
    const r = over.getBoundingClientRect(), mid = r.left + r.width / 2;
    const before = rtl() ? e.clientX > mid : e.clientX < mid;
    const ref = before ? over : over.nextSibling;
    if (ref !== colDrag && colDrag.nextSibling !== ref) boardEl().insertBefore(colDrag, ref);
  });
  document.addEventListener('drop', (e) => { if (colDrag) e.preventDefault(); });
  document.addEventListener('dragend', () => {
    if (!colDrag) return;
    colDrag.classList.remove('kb-col-dragging'); colDrag = null;
    decorate();
    if (keys().join() !== colStart) save();
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

  /* ستون بیشتر از سقف: آیکون ! → توضیح */
  const closeLimit = (except) => TK.qsa('.kb-limit-pop:not([hidden])').forEach((p) => { if (p !== except) { p.hidden = true; p.previousElementSibling?.setAttribute('aria-expanded', 'false'); } });
  TK.on('click', '[data-kb-limit]', (e, b) => {
    const pop = b.nextElementSibling;
    closeLimit(pop);
    pop.hidden = !pop.hidden;
    b.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true');
  });
  document.addEventListener('click', (e) => { if (!e.target.closest('.kb-limit-pop, [data-kb-limit]')) closeLimit(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeLimit(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });

  /* آمدن از جزئیات تسک (?task=): کارت برجسته در وسط دید */
  const f = TK.qs('.kb-card.kb-focus');
  if (f) setTimeout(() => f.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' }), 150);

  /* ───── فیلترها بدون دکمه (AJAX): فقط بخش برد از سرور تازه می‌شود و آدرس صفحه هم به‌روز می‌ماند ───── */
  const ff = TK.qs('.kb-filters');
  if (ff) {
    let seq = 0;
    const apply = async () => {
      const q = new URLSearchParams();
      new FormData(ff).forEach((v, k) => { if (typeof v === 'string' && v.trim() !== '' && !(k === 'project' && v === '0')) q.set(k, v.trim()); });
      history.replaceState(history.state, '', TK.url('/board') + (q.toString() ? '?' + q : ''));   // کارت برجسته (?task=) با فیلتر جدید کنار می‌رود
      const my = ++seq;
      while (TK.live.busy) await new Promise((r) => setTimeout(r, 40));
      if (my !== seq) return;                                        // تغییر جدیدتری در راه است
      const body = TK.qs('[data-live="board-body"]');
      body?.classList.add('kb-loading'); TK.progress.start();
      await TK.live.refresh();
      body?.classList.remove('kb-loading'); TK.progress.done();
    };
    ff.addEventListener('submit', (e) => { e.preventDefault(); apply(); });
    ff.querySelector('[name=q]')?.addEventListener('input', TK.debounce(apply, 350));
  }

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
        <div class="grow"><a class="bold" href="${E(t.url)}" data-open-task="${t.id}">${E(t.title)}</a>
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
