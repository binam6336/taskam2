/* صندوق بررسی سرپرست (v7.9) — پیشخوان: «نمایش همه» در فهرست کوتاه + مودال تمام‌صفحه‌ی #ri-inbox.
   تأیید/برگشت با تأخیر ۵ ثانیه‌ای و «بازگردانی» (تا پایان مهلت هیچ درخواستی فرستاده نمی‌شود)؛ شکست سرور = بازگشت مورد به فهرست.
   بدون میانبر کیبورد: فقط Esc (اول پنل برگشت، بعد مودال). پیشوند کلاس‌ها و data-attributeها: ri- */
document.addEventListener('tk:ready', () => {
  const F = TK.fa, E = TK.esc;
  const UNDO_MS = 5000;

  /* ── فهرست کوتاه پیشخوان: نمایش همه / کمتر ── */
  TK.on('click', '[data-ri-more]', (e, b) => {
    const open = b.getAttribute('aria-expanded') !== 'true';
    TK.qsa('#ri-sec .ri-extra').forEach((x) => { x.hidden = !open; });
    b.setAttribute('aria-expanded', String(open));
    b.textContent = open ? 'نمایش کمتر' : `نمایش همه (${b.dataset.riN} مورد دیگر)`;
  });

  const ov = TK.qs('#ri-inbox'); if (!ov) return;
  const box = TK.qs('.ri-box', ov), list = TK.qs('[data-ri-list]', ov), detail = TK.qs('[data-ri-detail]', ov);
  const CATS = (() => { try { return JSON.parse(ov.dataset.riCats || '{}'); } catch { return {}; } })();
  const st = { items: [], sla: 24, filter: 'all', project: '', current: null, checked: new Set(), opener: null };
  const pending = new Map();        // id → {kind, item, index, timer, payload}

  /* ── قالب‌ها ── */
  const num = (v) => `<span class="ri-num">${E(String(v))}</span>`;
  const avatar = (a) => `<span class="avatar sm" aria-hidden="true">${a.avatar_url ? `<img src="${E(a.avatar_url)}" alt="" loading="lazy">` : E(a.initial || '؟')}</span>`;
  const dueTxt = (i) => (i.due.state === 'late' ? `${num(F(i.due.late_days))} روز دیر` : i.due.state === 'on_time' ? 'به‌موقع' : 'بدون سررسید');
  const visible = () => st.items.filter((i) => !pending.has(i.id)
    && (st.filter === 'all' || (st.filter === 'over' && i.over_sla) || (st.filter === 'late' && i.due.state === 'late'))
    && (!st.project || (i.project && i.project.id === st.project)));

  const rowHtml = (i) => `<li class="ri-item${i.id === st.current ? ' is-cur' : ''}" role="option" aria-selected="${i.id === st.current}" data-ri-id="${E(i.id)}" tabindex="${i.id === st.current ? 0 : -1}">
      <label class="ri-ck" title="انتخاب برای تأیید گروهی"><input type="checkbox" data-ri-check="${E(i.id)}" ${st.checked.has(i.id) ? 'checked' : ''} aria-label="انتخاب «${E(i.title)}»"></label>
      ${avatar(i.assignee)}
      <div class="ri-main"><b class="ri-ttl">${E(i.title)}</b>
        <span class="ri-sub">${E(i.assignee.name)}${i.project ? ' • ' + E(i.project.title) : ''}</span>
        <span class="ri-tags"><span class="ri-tag${i.over_sla ? ' is-warn' : ''}"><i class="fa-regular fa-clock" aria-hidden="true"></i> ${num(i.wait_label)}</span>${i.due.state === 'late' ? `<span class="ri-tag is-late">${num(F(i.due.late_days))} روز دیر</span>` : ''}</span></div>
    </li>`;

  const detailHtml = (i) => `<div class="ri-d-scroll">
      <h3 class="ri-d-ttl">${E(i.title)}</h3>
      <p class="ri-d-meta">${E(i.assignee.name)}${i.project ? ' • ' + E(i.project.title) : ''} • تحویل: ${num(i.wait_label)} پیش • <span class="${i.due.state === 'late' ? 'ri-late' : ''}">${dueTxt(i)}</span> • ${i.attachments ? num(F(i.attachments)) + ' پیوست' : 'بدون پیوست'}</p>
      ${i.note ? `<blockquote class="ri-note">${E(i.note)}</blockquote>` : '<p class="ri-nonote">مسئول یادداشتی ننوشته است</p>'}
      <a href="#" class="ri-full" data-open-task="${E(i.id)}">مشاهده‌ی کامل تسک</a>
      <div class="ri-rw-slot"></div>
    </div>
    <div class="ri-acts">
      <button type="button" class="btn btn-primary ri-ok" data-ri-approve><i class="fa-solid fa-check" aria-hidden="true"></i> تأیید</button>
      <button type="button" class="btn" data-ri-rework-open><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> برگشت برای اصلاح</button>
      <button type="button" class="btn btn-ghost" data-ri-next>بعدی <i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
    </div>`;

  /* ── رندر ── */
  function render() {
    const all = st.items.filter((i) => !pending.has(i.id));
    const over = all.filter((i) => i.over_sla).length, late = all.filter((i) => i.due.state === 'late').length;
    TK.qs('[data-ri-stat-wait]', ov).innerHTML = `${num(F(all.length))} در انتظار`;
    const so = TK.qs('[data-ri-stat-over]', ov); so.hidden = !over; so.innerHTML = `${num(F(over))} بیش از مهلت`;
    TK.qs('[data-ri-c="all"]', ov).textContent = F(all.length); TK.qs('[data-ri-c="over"]', ov).textContent = F(over); TK.qs('[data-ri-c="late"]', ov).textContent = F(late);
    // select پروژه فقط وقتی موارد از بیش از یک پروژه‌اند
    const projs = [...new Map(all.filter((i) => i.project).map((i) => [i.project.id, i.project.title])).entries()];
    const ps = TK.qs('[data-ri-project]', ov); ps.hidden = projs.length < 2;
    if (projs.length >= 2) {
      if (st.project && !projs.some(([id]) => id === st.project)) st.project = '';
      ps.innerHTML = '<option value="">همه‌ی پروژه‌ها</option>' + projs.map(([id, t]) => `<option value="${E(id)}" ${id === st.project ? 'selected' : ''}>${E(t)}</option>`).join('');
    } else st.project = '';
    const vis = visible();
    // انتخاب‌ها فقط از موارد موجود
    [...st.checked].forEach((id) => { if (!all.some((i) => i.id === id)) st.checked.delete(id); });
    if (!vis.some((i) => i.id === st.current)) st.current = vis[0]?.id || null;
    list.innerHTML = vis.map(rowHtml).join('');
    const cur = vis.find((i) => i.id === st.current);
    if (cur) { if (detail.dataset.riFor !== cur.id) { detail.innerHTML = detailHtml(cur); detail.dataset.riFor = cur.id; } }
    else { detail.innerHTML = vis.length || !all.length ? '' : '<p class="ri-nonote ri-center">در این فیلتر موردی نیست.</p>'; detail.dataset.riFor = ''; }
    // پیشنهاد کم‌ریسک: موارد به‌موقع
    const onTime = vis.filter((i) => i.due.state === 'on_time');
    const hint = TK.qs('[data-ri-hint]', ov); hint.hidden = onTime.length < 2;
    if (onTime.length >= 2) hint.innerHTML = `${num(F(onTime.length))} مورد به‌موقع تحویل شده‌اند — <button type="button" class="ri-link" data-ri-pick-ontime>انتخاب همه‌ی این‌ها</button>`;
    const n = st.checked.size;
    TK.qs('[data-ri-bulk-n]', ov).textContent = F(n); TK.qs('[data-ri-bulk]', ov).disabled = !n;
    const allCk = TK.qs('[data-ri-all]', ov); allCk.checked = vis.length > 0 && vis.every((i) => st.checked.has(i.id)); allCk.indeterminate = !allCk.checked && vis.some((i) => st.checked.has(i.id));
    const empty = !all.length;
    TK.qs('[data-ri-empty]', ov).hidden = !empty; TK.qs('.ri-body', ov).hidden = empty; TK.qs('.ri-bar', ov).hidden = empty;
    if (empty) hint.hidden = true;
  }

  const select = (id, focus = false) => {
    st.current = id; detail.dataset.riFor = ''; render();
    if (focus) TK.qs(`.ri-item[data-ri-id="${CSS.escape(id)}"]`, list)?.focus();
  };

  /* ── باز و بسته کردن، تله‌ی فوکوس ── */
  async function open(opener) {
    st.opener = opener || document.activeElement;
    ov.hidden = false; document.body.classList.add('ri-lock');
    list.innerHTML = '<li class="ri-loading">در حال بارگذاری…</li>'; detail.innerHTML = '';
    box.focus();
    const r = await TK.get('/tasks/review/pending');
    if (!TK.ok(r)) { TK.toast(r.message || 'خطا', 'error'); close(); return; }
    st.items = r.items || []; st.sla = r.sla_hours || 24; st.checked.clear(); st.current = null; detail.dataset.riFor = '';
    render();
    (TK.qs('.ri-item.is-cur', list) || TK.qs('[data-ri-close]', ov)).focus();
  }
  function close() {
    if (ov.hidden) return;
    ov.hidden = true; document.body.classList.remove('ri-lock');
    (st.opener && document.contains(st.opener) ? st.opener : TK.qs('[data-ri-open]'))?.focus();
    refreshDash();
  }
  const focusables = () => TK.qsa('button:not([disabled]), [href], input:not([disabled]), select:not([hidden]), textarea, [tabindex="0"]', box).filter((x) => x.offsetParent !== null);
  document.addEventListener('keydown', (e) => {
    if (ov.hidden) return;
    if (e.key === 'Escape') {
      if (TK.qs('.modal.open')) return;                    // مودال جزئیات تسک روی صندوق باز است: Esc مال آن است
      e.preventDefault(); e.stopPropagation();
      const rw = TK.qs('[data-ri-rework]', detail);
      if (rw) { rw.remove(); TK.qs('[data-ri-rework-open]', detail)?.focus(); } else close();
      return;
    }
    if (e.key === 'Tab') {
      const f = focusables(); if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (!box.contains(document.activeElement)) { e.preventDefault(); first.focus(); }
      else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  TK.on('click', '[data-ri-open]', (e, b) => open(b));
  ov.addEventListener('click', (e) => { if (e.target === ov) close(); });
  TK.on('click', '[data-ri-close]', () => close());

  /* ── فیلتر، پروژه، انتخاب ── */
  TK.on('click', '[data-ri-filter]', (e, b) => {
    st.filter = b.dataset.riFilter;
    TK.qsa('[data-ri-filter]', ov).forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    render();
  });
  TK.qs('[data-ri-project]', ov).addEventListener('change', (e) => { st.project = e.target.value; render(); });
  list.addEventListener('click', (e) => {
    if (e.target.closest('.ri-ck')) return;
    const li = e.target.closest('.ri-item'); if (li) select(li.dataset.riId);
  });
  list.addEventListener('change', (e) => {
    const c = e.target.closest('[data-ri-check]'); if (!c) return;
    c.checked ? st.checked.add(c.dataset.riCheck) : st.checked.delete(c.dataset.riCheck); render();
  });
  TK.qs('[data-ri-all]', ov).addEventListener('change', (e) => { const vis = visible(); vis.forEach((i) => (e.target.checked ? st.checked.add(i.id) : st.checked.delete(i.id))); render(); });
  TK.on('click', '[data-ri-pick-ontime]', () => { visible().filter((i) => i.due.state === 'on_time').forEach((i) => st.checked.add(i.id)); render(); });

  /* ── خروج مورد از فهرست و انتخاب بعدی ── */
  const nextAfter = (ids) => {
    const vis = visible(); const curIdx = Math.max(0, st.items.findIndex((i) => i.id === st.current));
    const after = st.items.slice(curIdx).find((i) => !ids.includes(i.id) && vis.includes(i));
    const before = [...st.items.slice(0, curIdx)].reverse().find((i) => !ids.includes(i.id) && vis.includes(i));
    return (after || before)?.id || null;
  };
  const removeAnimated = (ids, cb) => {
    const els = ids.map((id) => TK.qs(`.ri-item[data-ri-id="${CSS.escape(id)}"]`, list)).filter(Boolean);
    els.forEach((el) => el.classList.add('is-out'));
    setTimeout(cb, els.length ? 220 : 0);
  };

  /** عملیات با تأخیر: حذف فوری از فهرست، toast با «بازگردانی»، ارسال به سرور بعد از ۵ ثانیه */
  function schedule(kind, ids, payload, label) {
    const next = nextAfter(ids);
    removeAnimated(ids, () => {
      ids.forEach((id) => pending.set(id, { kind }));
      st.checked = new Set([...st.checked].filter((x) => !ids.includes(x)));
      st.current = next; detail.dataset.riFor = ''; render();
      (TK.qs('.ri-item.is-cur', list) || TK.qs('[data-ri-close]', ov)).focus({ preventScroll: true });
    });
    const job = { ids, kind, payload, done: false };
    job.timer = setTimeout(() => send(job), UNDO_MS);
    jobs.add(job);
    TK.toast(label, 'success', UNDO_MS, { action: { label: 'بازگردانی', onClick: () => {
      if (job.done) return; clearTimeout(job.timer); jobs.delete(job); job.done = true;
      ids.forEach((id) => pending.delete(id)); st.current = ids[0]; detail.dataset.riFor = ''; render();
    } } });
  }
  const jobs = new Set();
  async function send(job, beacon = false) {
    if (job.done) return; job.done = true; jobs.delete(job);
    let r;
    if (job.kind === 'approve') {
      r = beacon ? null : await TK.post('/tasks/review/bulk-approve', { ids: job.ids.join(',') });
      if (beacon) return keepalive('/tasks/review/bulk-approve', { ids: job.ids.join(',') });
    } else {
      if (beacon) return keepalive('/tasks/' + job.ids[0] + '/status', job.payload);
      r = await TK.post('/tasks/' + job.ids[0] + '/status', job.payload);
    }
    const failed = job.kind === 'approve' ? ((r.data || r).failed || []).map((f) => f.id) : (TK.ok(r) ? [] : job.ids);
    const okIds = job.ids.filter((id) => !failed.includes(id) && (job.kind !== 'approve' || TK.ok(r) || false));
    // موفق‌ها از صف حذف؛ شکست‌خورده‌ها به فهرست برمی‌گردند
    st.items = st.items.filter((i) => !okIds.includes(i.id) || failed.includes(i.id));
    job.ids.forEach((id) => pending.delete(id));
    if (failed.length || !TK.ok(r)) {
      const msg = job.kind === 'approve' ? ((r.data || r).failed?.[0]?.message || r.message) : r.message;
      TK.toast((failed.length > 1 ? F(failed.length) + ' مورد انجام نشد: ' : '') + (msg || 'خطا در ارتباط با سرور.'), 'error', 6000);
    }
    if (!ov.hidden) render(); else refreshDash();
  }
  /** خروج از صفحه پیش از پایان مهلت: درخواست با keepalive همان لحظه فرستاده می‌شود (بازگردانی دیگر ممکن نیست) */
  function keepalive(url, data) {
    const fd = new FormData(); Object.entries(data).forEach(([k, v]) => fd.append(k, v));
    try { fetch(TK.url(url), { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin', headers: { 'X-CSRF-Token': TK.csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }); } catch { /* */ }
  }
  window.addEventListener('pagehide', () => { [...jobs].forEach((j) => { clearTimeout(j.timer); send(j, true); }); });

  /* ── اقدام‌ها ── */
  const cur = () => st.items.find((i) => i.id === st.current);
  TK.on('click', '[data-ri-approve]', () => { const i = cur(); if (i) schedule('approve', [i.id], null, `«${i.title}» تأیید شد`); });
  TK.on('click', '[data-ri-next]', () => {
    const vis = visible(); const k = vis.findIndex((i) => i.id === st.current);
    if (vis.length > 1) select(vis[(k + 1) % vis.length].id, true);
  });
  TK.on('click', '[data-ri-bulk]', () => {
    const ids = [...st.checked].filter((id) => st.items.some((i) => i.id === id) && !pending.has(id)); if (!ids.length) return;
    schedule('approve', ids, null, `${F(ids.length)} تسک تأیید شد`);
  });
  TK.on('click', '[data-ri-rework-open]', () => {
    const slot = TK.qs('.ri-rw-slot', detail); if (!slot || TK.qs('[data-ri-rework]', slot)) return;
    slot.appendChild(TK.qs('#ri-rework-tpl').content.cloneNode(true));
    TK.qs('[data-ri-cat]', slot)?.focus();
  });
  TK.on('click', '[data-ri-cat]', (e, b) => b.setAttribute('aria-pressed', String(b.getAttribute('aria-pressed') !== 'true')));
  TK.on('click', '[data-ri-rw-cancel]', () => { TK.qs('[data-ri-rework]', detail)?.remove(); TK.qs('[data-ri-rework-open]', detail)?.focus(); });
  TK.on('click', '[data-ri-rw-send]', () => {
    const i = cur(); const rw = TK.qs('[data-ri-rework]', detail); if (!i || !rw) return;
    const cats = TK.qsa('[data-ri-cat][aria-pressed="true"]', rw).map((b) => b.dataset.riCat);
    const reason = TK.qs('textarea', rw).value.trim();
    const why = reason || cats.map((c) => CATS[c]).join('، ');
    schedule('rework', [i.id], { status: 'rework', reason: why, reason_category: cats.join(',') }, `«${i.title}» برای اصلاح برگشت`);
  });

  /* ── به‌روزرسانی کارت «منتظر اقدام» پیشخوان بدون رفرش ── */
  let refreshT = 0;
  function refreshDash() {
    clearTimeout(refreshT);
    refreshT = setTimeout(async () => {
      if (jobs.size) return refreshDash();                 // اول عملیات‌های در انتظار تمام شوند
      try {
        const html = await (await fetch(location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch-partial' } })).text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const fresh = doc.getElementById('dash-wait'), old = document.getElementById('dash-wait');
        const hadFocus = !!document.activeElement?.closest('#dash-wait');
        if (fresh && old) old.replaceWith(document.importNode(fresh, true));
        if (hadFocus) (TK.qs('#dash-wait [data-ri-open]') || TK.qs('#dash-wait'))?.focus();
        const chipNew = doc.querySelector('[data-dash-jump="dash-wait"]'), chipOld = document.querySelector('[data-dash-jump="dash-wait"]');
        if (chipOld && chipNew) chipOld.replaceWith(document.importNode(chipNew, true)); else if (chipOld && !chipNew) chipOld.remove();
      } catch { /* */ }
    }, 300);
  }
});
