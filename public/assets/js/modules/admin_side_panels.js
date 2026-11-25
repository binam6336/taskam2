(() => {
  const timers = new Map();
  const hide = (card) => {
    clearTimeout(timers.get(card)); timers.delete(card);
    const box = card.querySelector('.side-panel-secrets'), btn = card.querySelector('[data-panel-reveal]');
    if (box) { box.replaceChildren(); box.hidden = true; }
    if (btn) { btn.textContent = 'نمایش اطلاعات ورود'; btn.setAttribute('aria-expanded', 'false'); }
  };
  TK.on('click', '[data-panel-reveal]', async (_, btn) => {
    const card = btn.closest('[data-panel]'), box = card.querySelector('.side-panel-secrets');
    if (!box.hidden) { hide(card); return; }
    btn.disabled = true;
    try {
      const result = await TK.post(btn.dataset.panelReveal, {});
      if (result.status !== 'success') { TK.toast(result.message || 'دریافت اطلاعات ورود ناموفق بود.', 'error'); return; }
      if (document.hidden || !card.isConnected) return;
      box.replaceChildren();
      for (const [key, label] of [['username', 'نام کاربری'], ['password', 'رمز عبور']]) {
        const row = document.createElement('p'), title = document.createElement('b'), value = document.createElement('code');
        title.textContent = label + ': '; value.dir = 'auto'; value.textContent = result.data[key] || 'ثبت نشده';
        row.append(title, value); box.append(row);
      }
      box.hidden = false; btn.textContent = 'پنهان کردن اطلاعات ورود'; btn.setAttribute('aria-expanded', 'true');
      timers.set(card, setTimeout(() => hide(card), 30000));
    } catch (e) { TK.toast(e.message, 'error'); }
    finally { btn.disabled = false; }
  });
  document.addEventListener('visibilitychange', () => { if (document.hidden) document.querySelectorAll('[data-panel]').forEach(hide); });
  window.addEventListener('pagehide', () => document.querySelectorAll('[data-panel]').forEach(hide));
})();
