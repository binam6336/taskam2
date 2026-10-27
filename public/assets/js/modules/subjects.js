document.addEventListener('tk:ready', () => {
  TK.qs('#btn-new-subject')?.addEventListener('click', () => {
    const f = TK.qs('#f-subject'); TK.resetForm(f); f.querySelector('[name=color]').value = '#22c55e'; f.setAttribute('action', TK.url('/subjects'));
  });
});
