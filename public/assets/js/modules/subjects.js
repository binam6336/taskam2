document.addEventListener('tk:ready', () => {
  TK.qs('#btn-new-subject')?.addEventListener('click', () => {
    const f = TK.qs('#f-subject'); TK.resetForm(f); const c = f.querySelector('[name=color][value="#22c55e"]'); if (c) c.checked = true; f.setAttribute('action', TK.url('/labels'));
  });
});
