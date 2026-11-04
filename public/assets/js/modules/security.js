/* تنظیمات ← امنیت: دکمه‌ی «جزئیات» هر رکورد لاگ، پارامترها (قبل/بعد) را در پنجره نشان می‌دهد */
document.addEventListener('tk:ready', () => {
  const m = TK.qs('#m-sl-detail'); if (!m) return;
  TK.on('click', '[data-sl-detail]', (e, b) => {
    const item = b.closest('.sl-item'), tpl = item?.querySelector('template[data-sl-body]'); if (!tpl) return;
    const t = item.querySelector('.sl-text').cloneNode(true); t.querySelectorAll('.avatar, i').forEach((x) => x.remove());
    TK.qs('#sl-detail-title').textContent = t.textContent.replace(/\s+/g, ' ').trim();
    const body = TK.qs('#sl-detail-body'); body.innerHTML = ''; body.appendChild(tpl.content.cloneNode(true));
    TK.modal.open('m-sl-detail');
  });
});
