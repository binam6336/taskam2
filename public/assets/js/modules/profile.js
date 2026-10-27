document.addEventListener('tk:ready', () => {
  TK.qs('#av-file')?.addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const url = URL.createObjectURL(f); TK.qs('#av-preview').innerHTML = `<img src="${url}" alt="">`;
  });
});
