document.addEventListener('tk:ready', () => {
  /* تنظیمات ظاهری: با انتخاب تم، بلافاصله اعمال و در حساب کاربر ذخیره می‌شود */
  const tf = TK.qs('#f-theme');
  tf?.addEventListener('change', async (e) => {
    if (e.target.name !== 'theme') return;
    const html = document.documentElement, prev = html.dataset.theme, next = e.target.value;
    html.dataset.theme = next;
    const r = await TK.post('/profile/appearance', { theme: next });
    TK.toast(r.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (!TK.ok(r)) { html.dataset.theme = prev; const old = tf.querySelector(`input[value="${prev}"]`); if (old) old.checked = true; return; }
    TK.qs('meta[name=theme-color]')?.setAttribute('content', getComputedStyle(html).getPropertyValue('--bg').trim());
  });
  TK.qs('#av-file')?.addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const url = URL.createObjectURL(f); TK.qs('#av-preview').innerHTML = `<img src="${url}" alt="">`;
  });
});
