document.addEventListener('tk:ready', () => {
  const f = TK.qs('#f-call'); if (!f) return;
  TK.qs('#btn-new-call')?.addEventListener('click', () => { TK.resetForm(f); f.setAttribute('action', TK.url('/calls')); TK.modal.open('m-call'); });
  TK.on('click', '[data-share-call]', async (e, t) => { const r = await TK.get('/calls/' + t.dataset.shareCall + '/share'); TK.ok(r) ? TK.copy(r.url) : TK.toast(r.message, 'error'); });
});
