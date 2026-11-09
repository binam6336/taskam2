document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().install; if (!cfg) return;
  const btn = TK.qs('#pwa-install'), state = TK.qs('#pwa-state'), badge = TK.qs('#push-badge'), devs = TK.qs('#push-devices');
  const os = TK.pwa.ios() ? 'ios' : /android/i.test(navigator.userAgent) ? 'android' : 'desktop';
  TK.qsa('.pwa-steps').forEach((s) => s.classList.toggle('current', s.dataset.os === os));

  /* ───── دکمه‌ی نصب ───── */
  const paint = () => {
    btn.disabled = true;
    if (TK.pwa.standalone()) { btn.querySelector('span').textContent = 'نصب شده است'; state.textContent = 'همین الان از نسخه‌ی نصب‌شده استفاده می‌کنید. ✅'; return; }
    if (TK.pwa.installEvt) { btn.disabled = false; btn.querySelector('span').textContent = 'نصب'; state.textContent = 'آماده‌ی نصب؛ دکمه را بزنید و در پنجره‌ی مرورگر تأیید کنید.'; return; }
    if (!cfg.https) { state.textContent = 'نصب فقط روی HTTPS ممکن است.'; return; }
    if (os === 'ios') { state.textContent = 'در آیفون از منوی اشتراک‌گذاری سافاری ← «افزودن به صفحه‌ی اصلی» نصب کنید (راهنما پایین صفحه).'; return; }
    state.textContent = 'اگر قبلاً نصب کرده‌اید از صفحه‌ی اصلی دستگاه بازش کنید؛ وگرنه از منوی مرورگر «نصب برنامه / Install app» را بزنید.';
  };
  paint();
  setTimeout(paint, 1500);
  document.addEventListener('tk:installable', paint);
  document.addEventListener('tk:installed', () => { btn.disabled = true; btn.querySelector('span').textContent = 'نصب شد'; state.textContent = 'تسکام نصب شد؛ از صفحه‌ی اصلی دستگاه بازش کنید.'; });
  btn.addEventListener('click', async () => {
    const r = await TK.pwa.install();
    if (r === 'accepted') TK.toast('در حال نصب تسکام…', 'success');
    else if (r === 'dismissed') TK.toast('نصب لغو شد؛ هر وقت خواستید دوباره دکمه را بزنید.', 'info');
    paint();
  });

  /* ───── اعلان‌ها ───── */
  // دکمه‌ها فقط وقتی معنی دارند نمایش داده می‌شوند: فعال‌سازی وقتی فعال نیست و مسدود نیست، آزمایش/غیرفعال‌سازی فقط با اشتراک فعال
  const show = (en, rest, denied) => {
    TK.qs('#push-enable').hidden = !en; TK.qs('#push-test').hidden = !rest; TK.qs('#push-disable').hidden = !rest;
    TK.qs('#push-denied').hidden = !denied;
  };
  const paintPush = async () => {
    if (!cfg.pushReady) { badge.textContent = 'غیرفعال در سرور'; badge.className = 'badge red'; show(false, false, false); return; }
    if (!TK.pwa.pushSupported()) { badge.textContent = 'پشتیبانی نمی‌شود'; badge.className = 'badge yellow'; show(false, false, false); return; }
    const sub = await TK.pwa.subscription();
    const on = Notification.permission === 'granted' && !!sub;
    show(!on && Notification.permission !== 'denied', on, Notification.permission === 'denied');
    badge.textContent = on ? 'فعال' : Notification.permission === 'denied' ? 'مسدود' : 'غیرفعال';
    badge.className = 'badge ' + (on ? 'green' : Notification.permission === 'denied' ? 'red' : 'yellow');
  };
  paintPush();
  const act = (id, fn) => TK.qs(id)?.addEventListener('click', async (e) => {
    const b = e.currentTarget; TK.busy(b, true);
    try { await fn(); } finally { TK.busy(b, false); paintPush(); }
  });
  act('#push-enable', async () => {
    const r = await TK.pwa.enablePush();
    TK.toast(r.msg || (r.ok ? 'فعال شد' : 'خطا'), r.ok ? 'success' : 'warning', 7000);
    if (r.ok && r.devices !== undefined) devs.textContent = TK.fa(r.devices);
  });
  act('#push-test', async () => { const r = await TK.post('/push/test'); TK.toast(r.message, TK.ok(r) ? 'success' : 'warning', 6000); });
  act('#push-disable', async () => { const r = await TK.pwa.disablePush(); TK.toast(r.msg, 'info'); });
});

// نرم‌افزار اندروید (v5.14): پیش از دانلود وجود فایل روی سرور بررسی می‌شود؛ نبود فایل = پیام خطا به‌جای صفحه‌ی خالی
document.addEventListener('tk:ready', () => {
  const a = TK.qs('#apk-dl'); if (!a) return;
  a.addEventListener('click', async (e) => {
    e.preventDefault();
    if (a.dataset.busy) return;
    a.dataset.busy = '1'; TK.busy(a, true);
    const r = await TK.get('/install/android/check');
    TK.busy(a, false); delete a.dataset.busy;
    if (!TK.ok(r)) { TK.toast('خطا در دریافت نرم‌افزار، لطفاً بعداً تلاش کنید.', 'error'); return; }
    const l = document.createElement('a'); l.href = a.href; l.download = 'taskam.apk'; l.setAttribute('data-no-nav', ''); document.body.appendChild(l); l.click(); l.remove();
    TK.toast('دریافت فایل شروع شد (' + (r.size_fa || r.data?.size_fa || '') + ').', 'success');
  });
});
