/* ادمین ← محتوا (تور آموزشی، خطاها): ویرایش متن‌ها؛ برچسب «ویرایش‌شده» بعد از ذخیره به‌روز می‌شود */
document.addEventListener('tk:ready', () => {
  const sync = (card) => {
    const ta = card.querySelector('textarea');
    card.classList.toggle('changed', ta.value.trim() !== ta.dataset.default);
    const msg = card.querySelector('.er-msg');
    if (msg) msg.lastChild.textContent = ' ' + ta.value.trim();
  };
  TK.on('click', '[data-ct-reset]', (e, b) => { const ta = b.closest('.ct-card').querySelector('textarea'); ta.value = ta.dataset.default; ta.focus(); });
  document.addEventListener('tk:done', (e) => { const card = e.target.closest?.('.ct-card'); if (card && TK.ok(e.detail)) sync(card); });

  /* خطاها: جستجو و «فقط ویرایش‌شده‌ها» */
  const q = TK.qs('#er-q'), only = TK.qs('#er-changed');
  if (!q) return;
  const filter = () => {
    const s = q.value.trim().toLowerCase();
    let any = false;
    TK.qsa('[data-er-group]').forEach((sec) => {
      let n = 0;
      TK.qsa('.er-card', sec).forEach((c) => {
        const show = (!s || c.dataset.search.includes(s)) && (!only.checked || c.classList.contains('changed'));
        c.hidden = !show;
        if (show) n++;
      });
      sec.hidden = !n;
      any = any || n > 0;
    });
    TK.qs('#er-none').classList.toggle('hidden', any);
  };
  q.addEventListener('input', TK.debounce(filter, 150));
  only.addEventListener('change', filter);
});
