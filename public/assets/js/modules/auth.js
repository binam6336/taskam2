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
  // کپچا: کیبورد فارسی هم کار می‌کند (هر حرف فارسی به حرف انگلیسیِ همان کلید تبدیل می‌شود)، حروف همیشه بزرگ و فقط A-Z/0-9
  const cap = TK.qs('[data-captcha]'), capImg = TK.qs('[data-captcha-img]');
  if (cap) {
    const FA = { 'ض': 'Q', 'ص': 'W', 'ث': 'E', 'ق': 'R', 'ف': 'T', 'غ': 'Y', 'ع': 'U', 'ه': 'I', 'خ': 'O', 'ح': 'P',
      'ش': 'A', 'س': 'S', 'ی': 'D', 'ي': 'D', 'ب': 'F', 'ل': 'G', 'ا': 'H', 'آ': 'H', 'ت': 'J', 'ن': 'K', 'م': 'L',
      'ظ': 'Z', 'ط': 'X', 'ز': 'C', 'ر': 'V', 'ذ': 'B', 'د': 'N', 'پ': 'M' };
    const norm = (v) => [...v].map((ch) => FA[ch] || ch).join('')
      .replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
      .toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, +cap.maxLength || 5);
    cap.addEventListener('input', () => { const v = norm(cap.value); if (v !== cap.value) cap.value = v; });
    const refresh = () => { capImg.src = TK.url('/captcha') + '?t=' + Date.now(); cap.value = ''; };
    TK.qs('[data-captcha-refresh]')?.addEventListener('click', () => { refresh(); cap.focus(); });
    capImg?.addEventListener('click', refresh);
    window.addEventListener('pageshow', (e) => { if (e.persisted) refresh(); });   // برگشت با دکمه‌ی Back: کد قبلی مصرف شده
  }
  // صفحه‌ی انتظار: بررسی خودکار فعال‌شدن
  if (TK.boot().pendingPoll) setInterval(async () => { const r = await TK.get('/pending/status'); if (r.active) location.href = TK.url('/'); }, 8000);
});
