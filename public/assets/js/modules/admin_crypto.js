document.addEventListener('tk:ready', () => {
  const button = document.querySelector('[data-crypto-start]');
  const progress = document.querySelector('[data-crypto-progress]');
  if (!button || !progress) return;
  let timer, busy = false;
  const render = (s) => {
    button.disabled = s.state === 'running';
    const fa = (n) => Number(n || 0).toLocaleString('fa-IR');
    const remaining = document.querySelector('[data-crypto-remaining]');
    if (remaining && s.state !== 'idle') remaining.textContent = fa(s.remaining);
    progress.textContent = (s.state === 'done' ? 'رمزگذاری کامل شد. ' : s.state === 'error' ? 'صف به علت خطا متوقف شد؛ پس از رفع خطا دوباره شروع کنید. ' : s.state === 'running' ? 'در حال رمزگذاری… ' : '')
      + 'انجام‌شده: ' + fa(s.done) + '؛ باقی‌مانده: ' + fa(s.remaining);
    clearTimeout(timer);
    if (s.state === 'running') timer = setTimeout(tick, 3500);
  };
  const tick = async () => {
    if (!button.isConnected || busy) return;
    busy = true;
    try {
      const r = await TK.post('/admin/data/encrypt/tick');
      if (r.queue) render(r.queue);
      else { progress.textContent = r.message || 'ارتباط با صف قطع شد؛ صفحه را دوباره باز کنید.'; button.disabled = false; }
    } catch (e) { progress.textContent = 'ارتباط قطع شد؛ تلاش مجدد…'; timer = setTimeout(tick, 15000); }
    finally { busy = false; }
  };
  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const r = await TK.post('/admin/data/encrypt');
      if (r.queue) render(r.queue);
      else { progress.textContent = r.message; button.disabled = false; }
    } catch (e) { progress.textContent = 'شروع صف ممکن نشد؛ دوباره تلاش کنید.'; button.disabled = false; }
  });
  TK.get('/admin/data/encrypt/status').then((r) => { if (button.isConnected && r.queue) render(r.queue); });
});
