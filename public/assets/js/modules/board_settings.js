/* ویرایشگر ستون‌های بورد (v6.1): تنظیمات بورد هر پروژه (چرخ‌دنده) و «محتوا ← بورد» در پنل مدیر.
   ستون‌های ثابت (بازبینی مسئول، بررسی سرپرست، انجام‌شده) حذف نمی‌شوند؛ فقط نام، آیکون و رنگشان عوض می‌شود.
   حذف ستونی که تسک دارد: ستون مقصد همان‌جا پرسیده می‌شود (moves). */
document.addEventListener('tk:ready', () => {
  const E = TK.esc;
  const setup = (ed) => {
    if (!ed || ed.dataset.ready) return;
    ed.dataset.ready = '1';
    const list = TK.qs('[data-bs-cols]', ed), startSel = TK.qs('[data-bs-start]', ed);
    const isProject = ed.dataset.scope === 'project';
    let st = null;             // {columns, start, icons, tones, max, max_label}
    let removed = [];          // ستون‌های حذف‌شده: {col, to}
    let seq = 0, dirty = false;

    const freeCols = () => st.columns.filter((c) => !c.fixed);
    const touch = () => { dirty = true; };
    const toneDot = (t) => `<span class="bs-tone tone-${E(t)}" aria-hidden="true"></span>`;

    const draw = () => {
      list.innerHTML = st.columns.map((c, i) => `
        <li class="bs-row tone-${E(c.tone)}${c.fixed ? ' fixed' : ''}" data-i="${i}" draggable="false">
          <button type="button" class="bs-grip" draggable="true" title="برای جابه‌جایی بکشید" aria-label="جابه‌جایی ستون «${E(c.label)}»"><i class="fa-solid fa-grip-vertical"></i></button>
          <div class="bs-pick-wrap"><button type="button" class="bs-icon-btn" data-bs-icon="${i}" aria-haspopup="true" aria-expanded="false" title="آیکون و رنگ" aria-label="آیکون و رنگ ستون «${E(c.label)}»"><i class="fa-solid ${E(c.icon)}"></i></button></div>
          <div class="grow min0">
            <input class="input bs-label" value="${E(c.label)}" maxlength="${st.max_label}" data-bs-label="${i}" aria-label="نام ستون" required>
            ${c.fixed ? `<div class="bs-role"><i class="fa-solid fa-lock"></i> ثابت — ${E(c.role)}</div>` : ''}
          </div>
          ${c.count ? `<span class="badge bs-count" title="تسک‌های فعلی این ستون">${TK.fa(c.count)} تسک</span>` : ''}
          <div class="bs-move">
            <button type="button" class="btn btn-ghost btn-icon btn-xs" data-bs-up="${i}" ${i === 0 ? 'disabled' : ''} title="بالاتر (قبل)" aria-label="انتقال به قبل"><i class="fa-solid fa-arrow-up"></i></button>
            <button type="button" class="btn btn-ghost btn-icon btn-xs" data-bs-down="${i}" ${i === st.columns.length - 1 ? 'disabled' : ''} title="پایین‌تر (بعد)" aria-label="انتقال به بعد"><i class="fa-solid fa-arrow-down"></i></button>
          </div>
          ${c.fixed ? '<span class="bs-del-ph"></span>' : `<button type="button" class="btn btn-ghost btn-icon btn-xs bs-del" data-bs-del="${i}" title="حذف ستون" aria-label="حذف ستون «${E(c.label)}»"><i class="fa-solid fa-trash"></i></button>`}
        </li>`).join('') + removed.map((r, j) => `
        <li class="bs-row removed">
          <i class="fa-solid fa-trash bs-rm-ic" aria-hidden="true"></i>
          <div class="grow min0"><b>«${E(r.col.label)}»</b> حذف می‌شود${r.col.count ? `؛ ${TK.fa(r.col.count)} تسکش منتقل شود به:` : '.'}
            ${r.col.count ? `<select class="input bs-target" data-bs-target="${j}" aria-label="ستون مقصد تسک‌های «${E(r.col.label)}»">${st.columns.filter((c) => c.key !== 'done').map((c) => `<option value="${E(c.key)}" ${c.key === r.to ? 'selected' : ''}>${E(c.label)}</option>`).join('')}</select>` : ''}</div>
          <button type="button" class="btn btn-sm" data-bs-undo="${j}"><i class="fa-solid fa-rotate-left"></i> برگرداندن</button>
        </li>`).join('');
      const cur = st.start;
      startSel.innerHTML = freeCols().map((c) => `<option value="${E(c.key)}" ${c.key === cur ? 'selected' : ''}>${E(c.label)}</option>`).join('');
      if (!freeCols().some((c) => c.key === cur)) st.start = freeCols()[0]?.key || '';
      TK.qs('[data-bs-add]', ed).disabled = st.columns.length >= st.max;
    };

    const load = async () => {
      const my = ++seq;
      list.innerHTML = '<li class="bs-loading"><span class="spin"></span></li>';
      const r = await TK.get(ed.dataset.load, isProject ? { project: ed.dataset.project } : {});
      if (my !== seq) return;
      if (!TK.ok(r)) { list.innerHTML = `<li class="bs-loading">${E(r?.message || 'خطا در دریافت تنظیمات')}</li>`; return; }
      st = { columns: r.columns, start: r.start, icons: r.icons, tones: r.tones, max: r.max, max_label: r.max_label };
      removed = []; dirty = false; draw();
    };

    /* آیکون و رنگ: پاپ‌آپ کوچک */
    const closePop = () => { TK.qsa('.bs-pop', ed).forEach((p) => p.remove()); TK.qsa('[data-bs-icon][aria-expanded="true"]', ed).forEach((b) => b.setAttribute('aria-expanded', 'false')); };
    ed.addEventListener('click', (e) => {
      const ib = e.target.closest('[data-bs-icon]');
      if (ib) {
        const i = +ib.dataset.bsIcon, c = st.columns[i], open = ib.getAttribute('aria-expanded') === 'true';
        closePop(); if (open) return;
        const p = document.createElement('div'); p.className = 'bs-pop'; p.setAttribute('role', 'dialog'); p.setAttribute('aria-label', 'آیکون و رنگ');
        p.innerHTML = `<div class="bs-pop-h">آیکون</div><div class="bs-icons">${st.icons.map((ic) => `<button type="button" data-set-icon="${E(ic)}" class="${ic === c.icon ? 'on' : ''}" aria-label="${E(ic)}" aria-pressed="${ic === c.icon}"><i class="fa-solid ${E(ic)}"></i></button>`).join('')}</div>
          <div class="bs-pop-h">رنگ</div><div class="bs-tones">${Object.entries(st.tones).map(([k, l]) => `<button type="button" data-set-tone="${E(k)}" class="${k === c.tone ? 'on' : ''}" title="${E(l)}" aria-label="${E(l)}" aria-pressed="${k === c.tone}">${toneDot(k)}</button>`).join('')}</div>`;
        p.dataset.i = String(i);
        ib.parentElement.appendChild(p); ib.setAttribute('aria-expanded', 'true');
        p.querySelector('button.on, button')?.focus();
        return;
      }
      const si = e.target.closest('[data-set-icon]'), stn = e.target.closest('[data-set-tone]');
      if (si || stn) {
        const i = +e.target.closest('.bs-pop').dataset.i;
        if (si) st.columns[i].icon = si.dataset.setIcon; else st.columns[i].tone = stn.dataset.setTone;
        touch(); closePop(); draw(); TK.qs(`[data-bs-icon="${i}"]`, ed)?.focus(); return;
      }
      if (!e.target.closest('.bs-pop')) closePop();
      const up = e.target.closest('[data-bs-up]'), down = e.target.closest('[data-bs-down]');
      if (up || down) {
        const i = +(up || down).dataset[up ? 'bsUp' : 'bsDown'], j = up ? i - 1 : i + 1;
        if (j < 0 || j >= st.columns.length) return;
        [st.columns[i], st.columns[j]] = [st.columns[j], st.columns[i]]; touch(); draw();
        TK.qs(`[data-bs-${up ? 'up' : 'down'}="${j}"]:not([disabled])`, ed)?.focus(); return;
      }
      const del = e.target.closest('[data-bs-del]');
      if (del) {
        const i = +del.dataset.bsDel, [col] = st.columns.splice(i, 1);
        if (!col.key.startsWith('new')) removed.push({ col, to: col.count ? (st.start !== col.key ? st.start : freeCols()[0]?.key) : '' });
        if (st.start === col.key) st.start = freeCols()[0]?.key || '';
        touch(); draw(); return;
      }
      const undo = e.target.closest('[data-bs-undo]');
      if (undo) {
        const [r] = removed.splice(+undo.dataset.bsUndo, 1);
        if (st.columns.length >= st.max) { removed.splice(+undo.dataset.bsUndo, 0, r); TK.toast('حداکثر ' + TK.fa(st.max) + ' ستون.', 'error'); return; }
        const di = st.columns.findIndex((c) => c.key === 'done');
        st.columns.splice(di < 0 ? st.columns.length : di, 0, r.col); touch(); draw(); return;
      }
    });
    ed.addEventListener('keydown', (e) => { if (e.key === 'Escape' && TK.qs('.bs-pop', ed)) { e.stopPropagation(); closePop(); } });
    ed.addEventListener('input', (e) => {
      const l = e.target.closest('[data-bs-label]');
      if (l) { st.columns[+l.dataset.bsLabel].label = l.value; touch(); const o = startSel.querySelector(`option[value="${CSS.escape(st.columns[+l.dataset.bsLabel].key)}"]`); if (o) o.textContent = l.value; }
    });
    ed.addEventListener('change', (e) => {
      const t = e.target.closest('[data-bs-target]');
      if (t) { removed[+t.dataset.bsTarget].to = t.value; touch(); }
      if (e.target === startSel) { st.start = startSel.value; touch(); }
    });
    TK.qs('[data-bs-add]', ed).addEventListener('click', () => {
      if (st.columns.length >= st.max) return;
      const di = st.columns.findIndex((c) => c.key === 'review');
      const col = { key: 'new' + Date.now().toString(36), label: 'ستون جدید', icon: 'fa-list-ul', tone: 'info', desc: '', fixed: false, role: '', count: 0 };
      st.columns.splice(di < 0 ? st.columns.length : di, 0, col); touch(); draw();
      const inp = TK.qs(`[data-bs-label="${st.columns.indexOf(col)}"]`, ed); inp?.focus(); inp?.select();
    });

    /* جابه‌جایی با کشیدن دستگیره‌ی ردیف */
    let dragI = null;
    list.addEventListener('dragstart', (e) => { const g = e.target.closest('.bs-grip'); if (!g) return; dragI = +g.closest('.bs-row').dataset.i; g.closest('.bs-row').classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(dragI)); });
    list.addEventListener('dragover', (e) => { if (dragI === null) return; const row = e.target.closest('.bs-row:not(.removed)'); if (!row) return; e.preventDefault(); TK.qsa('.bs-row.over', list).forEach((x) => x.classList.remove('over')); row.classList.add('over'); });
    list.addEventListener('drop', (e) => {
      if (dragI === null) return; e.preventDefault();
      const row = e.target.closest('.bs-row:not(.removed)'); if (!row) return;
      const to = +row.dataset.i, [c] = st.columns.splice(dragI, 1); st.columns.splice(to, 0, c); dragI = null; touch(); draw();
    });
    list.addEventListener('dragend', () => { dragI = null; TK.qsa('.bs-row.dragging, .bs-row.over', list).forEach((x) => x.classList.remove('dragging', 'over')); });

    const send = async (data) => {
      const btn = TK.qs('[data-bs-save]', ed); TK.busy(btn, true);
      const r = await TK.post(ed.dataset.save, data);
      TK.busy(btn, false);
      TK.toast(r?.message || 'خطا', TK.ok(r) ? 'success' : 'error', TK.ok(r) ? 3500 : 6000);
      if (!TK.ok(r)) return;
      dirty = false;
      if (isProject) { TK.modal.close('m-board-settings'); TK.visit(location.href); } else load();
    };
    TK.qs('[data-bs-save]', ed).addEventListener('click', () => {
      if (st.columns.some((c) => !String(c.label).trim())) { TK.toast('نام همه‌ی ستون‌ها را وارد کنید.', 'error'); TK.qs('.bs-label:invalid', ed)?.focus(); return; }
      const moves = {}; removed.forEach((r) => { if (r.col.count) moves[r.col.key] = r.to; });
      const config = { columns: st.columns.map((c) => ({ key: c.key, label: c.label.trim(), icon: c.icon, tone: c.tone, desc: c.desc || '' })), start: st.start };
      send({ project: ed.dataset.project, config: JSON.stringify(config), moves: JSON.stringify(moves) });
    });
    TK.qs('[data-bs-reset]', ed).addEventListener('click', async () => {
      const msg = isProject ? 'بورد این پروژه به چیدمان پیش‌فرض برگردد؟ تسک‌های ستون‌هایی که در پیش‌فرض نیستند به ستون شروع منتقل می‌شوند.'
        : 'بورد پیش‌فرض به چیدمان اولیه‌ی تسکام برگردد؟ پروژه‌هایی که بورد اختصاصی ندارند همان را می‌بینند.';
      if (!(await TK.confirm(msg, { okText: 'بازگشت به پیش‌فرض' }))) return;
      send({ project: ed.dataset.project, reset: 1 });
    });
    ed.isDirty = () => dirty;
    ed.reload = load;
    if (!isProject) load();
  };

  TK.qsa('[data-board-editor]').forEach(setup);
  // پنجره‌ی تنظیمات بورد پروژه: با هر بار باز شدن از سرور (تعداد تسک‌های ستون‌ها تازه باشد)
  TK.on('click', '#kb-settings', () => {
    const ed = TK.qs('#m-board-settings [data-board-editor]'); if (!ed) return;
    setup(ed); ed.reload(); TK.modal.open('m-board-settings');
  });
});
