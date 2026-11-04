/* تنظیمات ← امنیت: دکمه‌ی «جزئیات» هر رکورد لاگ، پارامترها (قبل/بعد) را در پنجره نشان می‌دهد */
document.addEventListener('tk:ready', () => {
  /* فیلتر کاربر با جستجو: select اصلی پنهان می‌ماند (همان name و ارسال خودکار)، جستجو در نام و موبایل */
  TK.qsa('select[data-sl-search]').forEach((sel) => {
    if (sel.dataset.slReady) return; sel.dataset.slReady = '1';
    const E = TK.esc, opts = [...sel.options].map((o) => ({ v: o.value, t: o.textContent }));
    const box = document.createElement('div'); box.className = 'sl-combo';
    box.innerHTML = '<div class="search"><i class="fa-solid fa-magnifying-glass"></i><input class="input" type="text" autocomplete="off" role="combobox" aria-expanded="false" aria-label="جستجوی کاربر (نام یا موبایل)"></div><div class="sl-combo-list dropdown-menu" role="listbox" hidden></div>';
    sel.hidden = true; sel.after(box);
    const inp = box.querySelector('input'), list = box.querySelector('.sl-combo-list');
    const cur = opts.find((o) => o.v === sel.value);
    inp.placeholder = 'جستجوی کاربر (نام یا موبایل)…'; inp.value = cur && cur.v ? cur.t : '';
    let idx = 0, shown = [];
    const fa = (s) => s.replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
    const draw = () => {
      const q = fa(inp.value.trim().toLowerCase());
      shown = opts.filter((o) => !q || (cur && cur.v && inp.value === cur.t) || fa(o.t.toLowerCase()).includes(q)).slice(0, 60);
      idx = Math.min(idx, Math.max(0, shown.length - 1));
      list.innerHTML = shown.length ? shown.map((o, i) => `<button type="button" class="sl-combo-opt${i === idx ? ' on' : ''}${o.v === sel.value ? ' cur' : ''}" data-v="${E(o.v)}" role="option">${E(o.t)}</button>`).join('') : '<div class="small muted sl-combo-empty">کاربری پیدا نشد.</div>';
      list.hidden = false; inp.setAttribute('aria-expanded', 'true');
    };
    const close = () => { list.hidden = true; inp.setAttribute('aria-expanded', 'false'); };
    const pick = (v) => { close(); if (v === sel.value) { inp.value = cur && cur.v ? cur.t : ''; return; } sel.value = v; sel.dispatchEvent(new Event('change', { bubbles: true })); };
    inp.addEventListener('focus', () => { inp.select(); draw(); });
    inp.addEventListener('input', () => { idx = 0; draw(); });
    inp.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (list.hidden) draw(); idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % Math.max(1, shown.length); draw(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (shown[idx]) pick(shown[idx].v); }
      else if (e.key === 'Escape') { close(); inp.blur(); }
    });
    list.addEventListener('mousedown', (e) => { const b = e.target.closest('.sl-combo-opt'); if (b) { e.preventDefault(); pick(b.dataset.v); } });
    inp.addEventListener('blur', () => setTimeout(() => { close(); if (!inp.value.trim() && sel.value) pick(''); else inp.value = cur && cur.v ? cur.t : ''; }, 150));
  });

  const m = TK.qs('#m-sl-detail'); if (!m) return;
  TK.on('click', '[data-sl-detail]', (e, b) => {
    const item = b.closest('.sl-item'), tpl = item?.querySelector('template[data-sl-body]'); if (!tpl) return;
    const t = item.querySelector('.sl-text').cloneNode(true); t.querySelectorAll('.avatar, i').forEach((x) => x.remove());
    TK.qs('#sl-detail-title').textContent = t.textContent.replace(/\s+/g, ' ').trim();
    const body = TK.qs('#sl-detail-body'); body.innerHTML = ''; body.appendChild(tpl.content.cloneNode(true));
    TK.modal.open('m-sl-detail');
  });
});
