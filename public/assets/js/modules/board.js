document.addEventListener('tk:ready', () => {
  if (!TK.qs('#kb-board') && !TK.qs('[data-live="board-body"]')) return;
  const statuses = (() => { try { return JSON.parse(TK.qs('#kb-data').textContent).statuses; } catch { return {}; } })();
  const E = TK.esc;

  /* اسکرول افقی برد با کشیدن موس روی هر جای برد (v6.1) — حتی روی کارت‌ها و متن‌ها؛ فقط دکمه‌ی «جابه‌جایی» کارت،
     دکمه‌ها، فیلدها و پاپ‌آپ‌ها مستثنا هستند. کلیکِ پایان کشیدن روی لینک/کارت اثر نمی‌گذارد. */
  let pan = null;
  const NO_PAN = '.kb-handle, button, input, select, textarea, label, [data-kb-limit], .kb-limit-pop, .kb-menu';
  document.addEventListener('mousedown', (e) => {
    const board = e.target.closest?.('#kb-board');
    if (!board || e.button !== 0 || e.target.closest(NO_PAN) || board.scrollWidth <= board.clientWidth) return;
    pan = { board, x: e.clientX, y: e.clientY, left: board.scrollLeft, moved: false };
  });
  document.addEventListener('mousemove', (e) => {
    if (!pan) return;
    const dx = e.clientX - pan.x;
    if (!pan.moved) {
      if (Math.abs(dx) < 6 || Math.abs(dx) < Math.abs(e.clientY - pan.y)) return;   // کلیک ساده یا حرکت عمودی اسکرول حساب نمی‌شود
      pan.moved = true; pan.board.classList.add('kb-panning'); getSelection()?.removeAllRanges();
    }
    pan.board.scrollLeft = pan.left - dx;
    e.preventDefault();                                   // انتخاب متن هنگام کشیدن
  });
  const endPan = () => {
    if (!pan) return;
    if (pan.moved) {
      const b = pan.board;
      b.classList.remove('kb-panning');
      const eat = (ev) => { ev.stopPropagation(); ev.preventDefault(); };
      window.addEventListener('click', eat, { capture: true, once: true });
      setTimeout(() => window.removeEventListener('click', eat, { capture: true }), 0);
    }
    pan = null;
  };
  document.addEventListener('mouseup', endPan);
  window.addEventListener('blur', endPan);
  // کشیدن لینک/متن کارت = اسکرول، نه drag مرورگر
  document.addEventListener('dragstart', (e) => { if (e.target.closest?.('#kb-board') && !e.target.closest('.kb-handle')) e.preventDefault(); });

  /* پروژه: جابه‌جایی بین بورد پروژه‌ها */
  TK.qs('[data-kb-project]')?.addEventListener('change', (e) => TK.visit(TK.url('/board') + '?project=' + encodeURIComponent(e.target.value)));

  /* شمارنده‌ی ستون‌ها بعد از هر جابه‌جایی */
  const recount = () => TK.qsa('.kb-col:not([data-col="archived"])').forEach((c) => {
    const n = c.querySelectorAll('.kb-card').length;
    c.querySelectorAll('.kb-count').forEach((x) => { x.textContent = TK.fa(n); });
    if (n) c.classList.remove('kb-collapsed');
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

  /* مقصدهای مجاز هر کارت (طبق دسترسی بورد پروژه) از سرور می‌آید؛ «بازبینی مسئول» علت برگشت را می‌پرسد */
  const allowed = (card, status) => (card.dataset.moves || '').split(' ').includes(status);
  let pending = null;
  const request = (card, status) => {
    if (card.dataset.status === status) return;
    if (!allowed(card, status)) { TK.toast(`اجازه‌ی انتقال این تسک به «${statuses[status]?.label || status}» را ندارید.`, 'error'); return; }
    const f = forms[status];
    if (!f) return move(card, status);
    pending = card; f.reset(); TK.modal.open('m-kb-rework');
  };
  // «بازبینی مسئول» علت برگشت را می‌پرسد (اختیاری)
  const forms = { rework: TK.qs('#f-kb-rework') };
  Object.entries(forms).forEach(([status, f]) => f?.addEventListener('submit', (e) => {
    e.preventDefault();
    const card = pending; pending = null; TK.modal.close(f.closest('.modal').id);
    if (card) move(card, status, f.reason.value.trim());
  }));

  /* کشیدن و رها کردن (دسکتاپ) */
  let dragged = null;
  document.addEventListener('dragstart', (e) => {
    const h = e.target.closest?.('.kb-handle[draggable="true"]'); if (!h) return;
    const c = h.closest('.kb-card');
    dragged = c; c.classList.add('kb-dragging');
    try { const r = c.getBoundingClientRect(); e.dataTransfer.setDragImage(c, e.clientX - r.left, e.clientY - r.top); } catch { /* */ }
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

  /* ───── ستون‌ها (v7.18، مثل کانبان دمو): جمع/باز کردن هر ستون با دکمه‌ی سربرگ، به تفکیک پروژه در localStorage
     (پیش‌فرض: ستون خالی جمع است، جز ستون شروع)؛ بعد از تازه‌سازی زنده (tk:live) دوباره اعمال می‌شود ───── */
  const kbRtl = () => getComputedStyle(TK.qs('#kb-board')).direction === 'rtl';
  const colKey = (c) => 'kx.kb.col.' + (TK.qs('#kb-board')?.dataset.project || '') + '.' + c.dataset.col;
  const pref = (c) => { try { return localStorage.getItem(colKey(c)); } catch { return null; } };
  const setPref = (c, v) => { try { localStorage.setItem(colKey(c), v ? '1' : '0'); } catch { /* حالت خصوصی */ } };
  const applyCols = () => TK.qsa('#kb-board > .kb-col').forEach((c) => {
    const p = pref(c);
    if (p !== null) c.classList.toggle('kb-collapsed', p === '1');
    c.querySelector('.kb-col-strip')?.setAttribute('aria-expanded', String(!c.classList.contains('kb-collapsed')));
  });
  applyCols();
  document.addEventListener('tk:live', applyCols);
  TK.on('click', '[data-kb-collapse]', (e, b) => {
    const c = b.closest('.kb-col'); c.classList.add('kb-collapsed'); setPref(c, true);
    c.querySelector('[data-kb-expand]')?.focus();
  });
  TK.on('click', '[data-kb-expand]', (e, b) => {
    const c = b.closest('.kb-col'); c.classList.remove('kb-collapsed'); setPref(c, false);
    c.querySelector('[data-kb-collapse]')?.focus();
  });
  // کشیدن کارت روی ستون جمع‌شده آن را باز می‌کند (بدون ذخیره)
  document.addEventListener('dragenter', (e) => { const c = e.target.closest?.('.kb-col.kb-collapsed'); if (c && !c.classList.contains('kb-deny')) c.classList.remove('kb-collapsed'); });
  // پیمایش افقی با صفحه‌کلید روی خود بورد (فوکوس‌پذیر)
  document.addEventListener('keydown', (e) => {
    if (e.target.id !== 'kb-board' || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
    e.preventDefault();
    const b = e.target, step = 300;
    if (e.key === 'Home') return b.scrollTo({ left: 0, behavior: 'smooth' });
    if (e.key === 'End') return b.scrollTo({ left: kbRtl() ? -b.scrollWidth : b.scrollWidth, behavior: 'smooth' });
    b.scrollBy({ left: e.key === 'ArrowLeft' ? -step : step, behavior: 'smooth' });
  });
  // ستون شروع (مگر کارت برجسته‌ای باشد): تأییدکننده «بررسی سرپرست»، بقیه ستون شروع بورد پروژه؛ اگر خالی بود اولین ستون دارای کارت
  if (!TK.qs('.kb-card.kb-focus')) {
    const b = TK.qs('#kb-board');
    const want = b && TK.qs(`#kb-board > .kb-col[data-col="${b.dataset.kbStart}"]`);
    const first = want && want.querySelector('.kb-card') ? want : TK.qsa('#kb-board > .kb-col').find((c) => c.querySelector('.kb-card'));
    if (first && b && b.scrollWidth > b.clientWidth + 2) {
      const br = b.getBoundingClientRect(), fr = first.getBoundingClientRect();
      // RTL: ستون را به لبه‌ی شروع (راست) بیاور
      b.scrollLeft += kbRtl() ? fr.right - br.right : fr.left - br.left;
    }
  }

  /* منوی «انتقال به…» (لمسی و صفحه‌کلید) */
  const closeMenu = () => { TK.qsa('.kb-menu').forEach((m) => m.remove()); TK.qsa('.kb-handle[aria-expanded="true"]').forEach((b) => b.setAttribute('aria-expanded', 'false')); };
  TK.on('click', '[data-kb-menu]', (e, b) => {
    const card = b.closest('.kb-card'), open = card.querySelector('.kb-menu'); closeMenu();
    if (open) return;
    const m = document.createElement('div'); m.className = 'kb-menu'; m.setAttribute('role', 'menu');
    m.innerHTML = '<div class="kb-menu-h">انتقال به</div>' + Object.entries(statuses).map(([k, s]) => `<button type="button" role="menuitem" data-kb-to="${E(k)}" ${k === card.dataset.status || !allowed(card, k) ? 'disabled' : ''}><i class="fa-solid ${E(s.icon)}"></i> ${E(s.label)}${k !== card.dataset.status && !allowed(card, k) ? ' <i class="fa-solid fa-lock kb-menu-lock"></i>' : ''}</button>`).join('');
    card.appendChild(m); b.setAttribute('aria-expanded', 'true'); m.querySelector('button:not([disabled])')?.focus();
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
      new FormData(ff).forEach((v, k) => { if (typeof v === 'string' && v.trim() !== '') q.set(k, v.trim()); });
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
  const filters = () => { const u = new URLSearchParams(location.search); return { project: u.get('project') || TK.qs('#kb-board')?.dataset.project || '', mine: u.get('mine') || '', q: u.get('q') || '' }; };

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
        <span class="avatar sm" title="${E(t.assignee || 'بدون مسئول')}">${t.assignee_avatar ? `<img loading="lazy" decoding="async" src="${E(t.assignee_avatar)}" alt="">` : E(TK.initial(t.assignee || '؟'))}</span>
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
    if (TK.ok(r)) { archItems = archItems.filter((t) => String(t.id) !== b.dataset.kbRestore); archRender(); TK.reload(); } else TK.busy(b, false);
  });
});
