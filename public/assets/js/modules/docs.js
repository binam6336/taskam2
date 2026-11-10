document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().docs; if (!cfg) return;
  const E = TK.esc;

  /* منوی موبایل */
  TK.on('click', '[data-dx-menu]', () => TK.qs('#dx-side')?.classList.toggle('open'));

  /* تب‌ها */
  TK.on('click', '[data-tabbed] .code-tabs button', (e, b) => {
    const box = b.closest('[data-tabbed]');
    TK.qsa('.code-tabs button', box).forEach((x) => x.classList.toggle('active', x === b));
    TK.qsa(':scope > .dx-pane', box).forEach((p) => (p.hidden = p.dataset.pane !== b.dataset.pane));
  });

  /* کپی بلوک کد (متن خام بدون شماره‌ی خط) */
  TK.on('click', '[data-copy-raw]', (e, b) => { const pre = b.closest('.codebox').querySelector('pre'); TK.copy(pre.dataset.raw || pre.textContent); });

  /* ───── جستجو ───── */
  const q = TK.qs('#dx-q'), box = TK.qs('#dx-results'), idx = cfg.index || [];
  const hide = () => { box.hidden = true; };
  q?.addEventListener('input', () => {
    const t = q.value.trim().toLowerCase(); if (!t) return hide();
    const hits = idx.filter((x) => (x.t + ' ' + x.p + ' ' + x.g + ' ' + x.m).toLowerCase().includes(t)).slice(0, 12);
    box.innerHTML = hits.length ? hits.map((x) => `<a href="${E(x.u)}"><span class="dc-fe732f dx-method m-${x.m.toLowerCase()}">${x.m}</span><span class="truncate grow">${E(x.t)}</span><code class="xs ltr">${E(x.p)}</code></a>`).join('') : '<div class="dc-a482db faint small">نتیجه‌ای پیدا نشد.</div>';
    box.hidden = false;
  });
  q?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { const a = box.querySelector('a'); if (a) location.href = a.href; } if (e.key === 'Escape') { hide(); q.blur(); } });
  document.addEventListener('click', (e) => { if (!e.target.closest('.dx-search')) hide(); });
  document.addEventListener('keydown', (e) => { if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); q?.focus(); } });

});
