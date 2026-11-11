document.addEventListener('tk:ready', () => {
  const E = TK.esc, res = TK.qs('#search-result');
  // «دعوت» نوار بالا: /colleagues?add=1 پنجره‌ی افزودن همکار را باز می‌کند
  if (new URLSearchParams(location.search).get('add') === '1' && TK.qs('#m-add-col')) { TK.modal.open('m-add-col'); history.replaceState(history.state, '', location.pathname); }
  TK.qs('#f-search')?.addEventListener('submit', async (e) => {
    e.preventDefault(); res.innerHTML = '<div class="muted center">در حال جستجو…</div>';
    const r = await TK.get('/colleagues/search', { mobile: e.target.mobile.value });
    if (!TK.ok(r)) { res.innerHTML = `<div class="alert error">${E(r.message)}</div>`; return; }
    const u = r.found; let act = '';
    if (u.already_added) act = '<span class="badge green">در لیست شما هست</span>';
    else if (u.is_blocked_by_me) act = '<span class="badge red">مسدود شده توسط شما</span>';
    else if (u.has_blocked_me) act = '<span class="badge red">شما را مسدود کرده</span>';
    else if (u.has_pending_request) act = '<span class="badge yellow">درخواست ارسال شده</span>';
    else if (!u.accepts_requests) act = '<span class="badge yellow">درخواست همکاری نمی‌پذیرد</span>';
    else act = `<button class="btn btn-primary btn-sm" data-add="${u.id}"><i class="fa-solid fa-plus"></i> افزودن</button>`;
    res.innerHTML = `<div class="item"><span class="avatar">${u.avatar_url ? `<img loading="lazy" decoding="async" src="${E(u.avatar_url)}" alt="">` : E(TK.initial(u.name))}</span><div class="grow"><b>${E(u.name)}</b><div class="xs muted ltr">${E(u.mobile)}</div></div>${act}</div>`;
  });
  res?.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-add]'); if (!b) return;
    TK.busy(b, true); const r = await TK.post('/colleagues', { colleague_user_id: b.dataset.add });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { TK.modal.close('m-add-col'); TK.qs('#search-result').innerHTML = ''; TK.reload(); } else TK.busy(b, false);
  });

  /* «افزودن به پروژه» روی کارت همکار: پروژه‌هایی که کاربر اجازه‌ی افزودن عضو دارد */
  const projs = (TK.boot().colleagues || {}).projects || [];
  let apUser = 0;
  const apDraw = () => {
    const list = TK.qs('#ap-list');
    list.innerHTML = projs.map((p) => {
      const st = p.members.includes(apUser) ? '<span class="badge green">عضو است</span>'
        : p.pending.includes(apUser) ? '<span class="badge yellow">در انتظار تأیید</span>'
        : p.full ? '<span class="badge red">ظرفیت تکمیل</span>'
        : `<button type="button" class="btn btn-sm btn-primary" data-ap-add="${p.id}"><i class="fa-solid fa-user-plus"></i> عضو کن</button>`;
      return `<div class="item"><span class="avatar sm sq">${p.image ? `<img loading="lazy" decoding="async" src="${E(p.image)}" alt="">` : E(TK.initial(p.title))}</span><div class="grow bold">${E(p.title)}</div>${st}</div>`;
    }).join('') || '<div class="empty small"><i class="fa-solid fa-diagram-project"></i><p>پروژه‌ای ندارید که در آن اجازه‌ی افزودن عضو داشته باشید.</p></div>';
  };
  TK.on('click', '[data-add-to-project]', (e, b) => {
    apUser = b.dataset.addToProject;
    TK.qs('#ap-name').textContent = '«' + (b.dataset.name || '') + '»';
    apDraw(); TK.modal.open('m-add-proj');
  });
  TK.on('click', '[data-ap-add]', async (e, b) => {
    const pid = b.dataset.apAdd;
    TK.busy(b, true);
    const r = await TK.post('/projects/' + pid + '/members/add', { user_id: apUser });
    TK.busy(b, false);
    TK.toast(r.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (!TK.ok(r)) return;
    const p = projs.find((x) => x.id === pid);
    if (p) (r.state === 'requested' ? p.pending : p.members).push(apUser);
    apDraw();
  });
});

/* ═════ تب «همکاران» (v7.14): جستجو/مرتب‌سازی/صفحه‌بندی سمت سرور، نمایش لیستی/کارتی، پیمایش با کیبورد ═════
   فقط بخش data-live="col-body" با fetch عوض می‌شود؛ URL همیشه وضعیت فعلی را دارد (TK.live.refresh هم همان را می‌گیرد).
   back/forward را ناوبری نرم پنل (TK.visit) با همان URL انجام می‌دهد. داده‌ی کاربر فقط با textContent. */
document.addEventListener('tk:ready', () => {
  const wrap = TK.qs('[data-col-wrap]');
  if (!wrap) return;
  const bar = TK.qs('[data-col-toolbar]', wrap), q = TK.qs('[data-col-search]', wrap), sortSel = TK.qs('[data-col-sort]', wrap), count = TK.qs('[data-col-count]', wrap);
  const body = () => TK.qs('[data-live="col-body"]', wrap);
  const PER = (TK.boot().colleagues || {}).per || [10, 20, 50, 100];
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { /* حالت خصوصی */ } },
  };
  const K_VIEW = 'kx.col.view', K_PER = 'kx.col.per';
  const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const meta = () => { const m = TK.qs('[data-col-meta]', wrap); return m ? { page: +m.dataset.page, pages: +m.dataset.pages, total: +m.dataset.total, per: +m.dataset.per, count: m.dataset.count } : null; };

  // ── رنگ ثابت آواتار هر نفر (--h از شناسه‌ی عمومی؛ با CSSOM چون style="" در CSP مسدود است)
  const hue = (s) => { let h = 0; for (const ch of String(s)) h = (h * 31 + ch.codePointAt(0)) % 360; return h; };
  // ── برجسته کردن کلمه‌های جستجو در نام و شماره (فقط گره‌های متنی؛ بدون innerHTML)
  const norm = (s) => String(s).replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, '').toLowerCase();
  const toFa = (s) => String(s).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
  const toEn = (s) => String(s).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  const mark = (el, words) => {
    const text = el.textContent, low = norm(text);
    const hits = [];
    words.forEach((w) => { let i = low.indexOf(w); while (w && i !== -1) { hits.push([i, i + w.length]); i = low.indexOf(w, i + w.length); } });
    if (!hits.length || low.length !== text.length) return;
    hits.sort((a, b) => a[0] - b[0]);
    const frag = document.createDocumentFragment(); let at = 0;
    hits.forEach(([s, e]) => { if (s < at) return; frag.append(text.slice(at, s)); const m = document.createElement('mark'); m.textContent = text.slice(s, e); frag.append(m); at = e; });
    frag.append(text.slice(at)); el.replaceChildren(frag);
  };
  const decorate = () => {
    const b = body(); if (!b) return;
    TK.qsa('[data-col-hue]', b).forEach((el) => el.style.setProperty('--h', String(hue(el.dataset.colHue))));
    const words = norm(toEn(q?.value || '')).split(/\s+/).filter(Boolean);
    if (words.length) {
      TK.qsa('.col-name', b).forEach((el) => mark(el, words));
      TK.qsa('.col-phone', b).forEach((el) => mark(el, words.map(toFa)));
    }
    const m = meta(); if (m && count) count.textContent = m.count;
  };

  // ── نمایش لیستی/کارتی (کلاس روی wrap بیرون از بخش زنده تا مقایسه‌ی live-refresh به هم نخورد)
  const setView = (v, save) => {
    wrap.classList.toggle('is-grid', v === 'grid');
    TK.qsa('[data-col-view]', wrap).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.colView === v)));
    if (save) store.set(K_VIEW, v);
  };
  setView(store.get(K_VIEW) === 'grid' ? 'grid' : 'list', false);
  TK.qsa('[data-col-view]', wrap).forEach((b) => b.addEventListener('click', () => setView(b.dataset.colView, true)));

  // ── ساختن URL از وضعیت (پیش‌فرض‌ها حذف می‌شوند)
  const build = (over = {}) => {
    const u = new URL(location.href), p = u.searchParams;
    const st = { q: q ? q.value.trim() : (p.get('q') || ''), sort: sortSel ? sortSel.value : (p.get('sort') || 'recent'), per: String(meta()?.per || PER[0]), page: String(meta()?.page || 1), ...over };
    ['q', 'sort', 'per_page', 'page'].forEach((k) => p.delete(k));
    p.set('tab', 'list');
    if (st.q) p.set('q', st.q);
    if (st.sort && st.sort !== 'recent') p.set('sort', st.sort);
    if (+st.per !== PER[0]) p.set('per_page', st.per);
    if (+st.page > 1) p.set('page', st.page);
    return u.pathname + '?' + p.toString();
  };

  // ── بارگذاری فقط col-body (درخواست قدیمی‌تر لغو می‌شود؛ پاسخ دیررسیده اعمال نمی‌شود)
  let ctl = null, seq = 0;
  const load = async (url, { push = true, focus = true } = {}) => {
    if (ctl) ctl.abort();
    ctl = new AbortController(); const my = ++seq;
    const b = body(); b?.setAttribute('aria-busy', 'true'); b?.classList.add('live-sync');
    try {
      const res = await fetch(url, { signal: ctl.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Live': '1', Accept: 'text/html' } });
      if (!res.ok || !(res.headers.get('content-type') || '').includes('text/html')) { location.href = url; return; }
      const html = await res.text();
      if (my !== seq) return;
      const next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-live="col-body"]');
      if (!next) { location.href = url; return; }
      body().innerHTML = next.innerHTML;
      history[push ? 'pushState' : 'replaceState']({ ...(history.state || {}), tk: 1 }, '', url);
      TK.pagePath = location.pathname + location.search;            // popstate ناوبری نرم همین URL را «بارشده» بداند
      syncUrl();
      decorate();
      document.dispatchEvent(new CustomEvent('tk:live', { detail: { regions: ['col-body'] } }));
      if (focus) {
        const m = meta();
        if (m && count) count.textContent = m.pages > 1 ? `صفحه‌ی ${TK.fa(m.page)} از ${TK.fa(m.pages)}، ${m.count}` : m.count;
        TK.qs('#col-h', wrap)?.focus({ preventScroll: true });
        const top = (bar || wrap).getBoundingClientRect().top + window.scrollY - 90;
        if (window.scrollY > top) window.scrollTo({ top, behavior: reduced() ? 'auto' : 'smooth' });
      }
    } catch (err) { if (err.name !== 'AbortError') TK.toast('بارگذاری لیست انجام نشد؛ دوباره تلاش کنید.', 'error'); } finally {
      if (my === seq) { const bb = body(); bb?.removeAttribute('aria-busy'); bb?.classList.remove('live-sync'); ctl = null; }
    }
  };
  // صفحه‌ی بیرون از بازه (مثلاً بعد از حذف آخرین نفر صفحه) را سرور به آخرین صفحه چسبانده: URL هم همان شود
  const syncUrl = () => {
    const m = meta(); if (!m) return;
    const cur = +(new URLSearchParams(location.search).get('page') || 1);
    if (cur !== m.page) { history.replaceState(history.state, '', build({ page: String(m.page) })); TK.pagePath = location.pathname + location.search; }
  };

  // ── لینک‌های صفحه‌بندی و «پاک کردن جستجو» داخل بخش زنده (پیش از ناوبری نرم سند گرفته می‌شوند)
  wrap.addEventListener('click', (e) => {
    const a = e.target.closest('[data-pager] a, [data-col-clear]');
    if (!a || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    if (a.getAttribute('aria-disabled') === 'true' || a.getAttribute('aria-current') === 'page') return;
    if (a.hasAttribute('data-col-clear')) { if (q) q.value = ''; load(build({ q: '', page: '1' })); return; }
    load(build({ page: a.dataset.page || '1' }));
  });
  wrap.addEventListener('change', (e) => {
    const s = e.target.closest('[data-pager-per]'); if (!s) return;
    const v = PER.includes(+s.value) ? s.value : String(PER[0]);
    store.set(K_PER, v);
    TK.qs('[data-col-per-hidden]', wrap)?.remove();
    load(build({ per: v, page: '1' }));
  });
  sortSel?.addEventListener('change', () => load(build({ sort: sortSel.value, page: '1' })));

  // ── جستجو: debounce ۳۰۰ms با replaceState؛ Enter فوری؛ Esc پاک می‌کند؛ «/» از هر جای صفحه فوکوس می‌کند
  let t = null, last = q ? q.value.trim() : '';
  const search = (now) => {
    clearTimeout(t);
    const run = () => { const v = q.value.trim(); if (v === last) return; last = v; load(build({ q: v, page: '1' }), { push: false, focus: false }); };
    now ? run() : (t = setTimeout(run, 300));
  };
  q?.addEventListener('input', () => search(false));
  bar?.addEventListener('submit', (e) => { e.preventDefault(); search(true); });
  q?.addEventListener('keydown', (e) => { if (e.key === 'Escape' && q.value) { e.preventDefault(); e.stopPropagation(); q.value = ''; search(true); } });
  document.addEventListener('keydown', (e) => {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || !q) return;
    const a = document.activeElement;
    if (a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable) || document.querySelector('.modal.open, dialog[open]')) return;
    e.preventDefault(); q.focus(); q.select();
  });

  // ── پیمایش ردیف‌ها: بالا/پایین و Home/End؛ ستون دکمه‌ی فعلی حفظ می‌شود
  wrap.addEventListener('keydown', (e) => {
    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(e.key) || e.altKey || e.ctrlKey || e.metaKey) return;
    const li = e.target.closest('.col-item'); if (!li || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    const rows = TK.qsa('.col-item', body()), i = rows.indexOf(li);
    const j = e.key === 'Home' ? 0 : e.key === 'End' ? rows.length - 1 : i + (e.key === 'ArrowDown' ? 1 : -1);
    if (j < 0 || j >= rows.length || j === i) { e.preventDefault(); return; }
    const foc = (r) => TK.qsa('a[href], button:not([disabled])', r);
    const col = Math.max(0, foc(li).indexOf(e.target)), target = foc(rows[j]);
    if (!target.length) return;
    e.preventDefault();
    target[Math.min(col, target.length - 1)].focus();
  });

  // ── live-refresh (بعد از حذف/مسدود یا تغییر داده): همان URL فعلی تازه شده؛ فقط تزئین و هماهنگی URL
  document.addEventListener('tk:live', (e) => { if ((e.detail?.regions || []).includes('col-body')) { syncUrl(); decorate(); } });

  decorate();
  // تعداد در صفحه‌ی ذخیره‌شده فقط برای ورود ساده به تب (بدون page/per_page در URL)؛ back/forward به صفحه‌ی مشخص دست نمی‌خورد
  const saved = +store.get(K_PER), m0 = meta(), sp = new URLSearchParams(location.search);
  if (m0 && !sp.has('per_page') && !sp.has('page') && PER.includes(saved) && saved !== m0.per && m0.total > Math.min(saved, m0.per)) {
    load(build({ per: String(saved), page: '1' }), { push: false, focus: false });
  }
});
