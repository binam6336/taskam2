document.addEventListener('tk:ready', () => {
  // تب ورود / ثبت‌نام
  TK.qsa('[data-auth-tab]').forEach((b) => b.addEventListener('click', () => {
    const reg = b.dataset.authTab === 'register';
    TK.qs('#form-login')?.classList.toggle('hidden', reg);
    TK.qs('#form-register')?.classList.toggle('hidden', !reg);
    TK.qsa('[data-auth-tab]').forEach((x) => x.classList.toggle('active', x === b));
  }));
  // قدرت رمز عبور
  const p = TK.qs('#r-pass'), bar = TK.qs('#pw-bar');
  p?.addEventListener('input', () => {
    const v = p.value; let s = 0;
    if (v.length >= 8) s++; if (v.length >= 12) s++; if (/[A-Za-z\u0600-\u06FF]/.test(v) && /\d/.test(v)) s++; if (/[^\w\s]/.test(v)) s++;
    bar.style.width = (s * 25) + '%'; bar.style.background = ['#f87171', '#f87171', '#fbbf24', '#4ade80', '#22c55e'][s];
  });
  // صفحه‌ی انتظار: بررسی خودکار فعال‌شدن
  if (TK.boot().pendingPoll) setInterval(async () => { const r = await TK.get('/pending/status'); if (r.active) location.href = TK.url('/'); }, 8000);
});
