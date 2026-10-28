/* ادمین ← محتوا: ویرایش متن‌ها (برچسب «ویرایش‌شده» بعد از ذخیره به‌روز می‌شود) */
document.addEventListener('tk:ready', () => {
  const sync = (card) => {
    const ta = card.querySelector('textarea');
    card.classList.toggle('changed', ta.value.trim() !== ta.dataset.default);
  };
  TK.on('click', '[data-ct-reset]', (e, b) => { const ta = b.closest('.ct-card').querySelector('textarea'); ta.value = ta.dataset.default; ta.focus(); });
  document.addEventListener('tk:done', (e) => { const card = e.target.closest?.('.ct-card'); if (card && TK.ok(e.detail)) sync(card); });
});
