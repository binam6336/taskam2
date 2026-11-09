document.addEventListener('tk:ready', () => {
  /* بخش‌های پروفایل (v5.14): پروفایل / اطلاعات شخصی / تنظیمات ظاهری / امنیت — بخش فعال در hash آدرس (#security …) */
  const tabs = TK.qsa('[data-pf-tab]'), panes = TK.qsa('[data-pf-pane]');
  if (tabs.length) {
    const ALIAS = { sessions: 'security', privacy: 'security', password: 'personal', info: 'personal', avatar: 'profile' };
    const show = (k, focus) => {
      k = ALIAS[k] || k;
      if (!panes.some((p) => p.dataset.pfPane === k)) k = 'profile';
      tabs.forEach((t) => { const on = t.dataset.pfTab === k; t.classList.toggle('active', on); if (on) t.setAttribute('aria-current', 'page'); else t.removeAttribute('aria-current'); });
      panes.forEach((p) => { p.hidden = p.dataset.pfPane !== k; });
      if (focus) TK.qs('#pf-h-' + k)?.focus({ preventScroll: true });
    };
    TK.qsa('.pf-h').forEach((h) => h.setAttribute('tabindex', '-1'));
    tabs.forEach((t) => t.addEventListener('click', (e) => {
      e.preventDefault();
      history.replaceState(history.state, '', '#' + t.dataset.pfTab);
      show(t.dataset.pfTab, true);
      if (innerWidth < 760) TK.qs('.pf-panes')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }));
    window.addEventListener('hashchange', () => show(location.hash.slice(1)));
    show(location.hash.slice(1));
  }

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
    // فاوآیکن و آیکن اپل هم با تم عوض می‌شوند (لوگوهای داخل صفحه با متغیرهای CSS خودشان عوض شده‌اند)
    try { const fav = JSON.parse(tf.dataset.favicons || '{}')[next]; if (fav) TK.qs('link[rel=icon]')?.setAttribute('href', fav); } catch { /* */ }
    TK.qs('link[rel=apple-touch-icon]')?.setAttribute('href', tf.dataset.iconBase + (next === 'sage' ? '-light' : '') + '.png');
  });
  // تصویر پروفایل: پیش‌نمایش و بارگذاری خودکار بعد از انتخاب (بدون دکمه‌ی جدا)
  TK.qs('#av-file')?.addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const url = URL.createObjectURL(f); TK.qs('#av-preview').innerHTML = `<img src="${url}" alt="">`;
    TK.qs('#f-avatar').requestSubmit();
  });

  // گزینه‌های انتخابی (حریم خصوصی، امنیت تسک‌ها): ذخیره‌ی خودکار با هر تغییر
  TK.qsa('form[data-autosave]').forEach((f) => f.addEventListener('change', () => f.requestSubmit()));

  // فرم‌های متنی (اطلاعات حساب، رمز): نشان «تغییرات ذخیره نشده» و هشدار هنگام ترک صفحه
  const snap = (f) => [...new FormData(f)].filter(([k]) => k !== '_csrf').map(([k, v]) => k + '=' + v).join('&');
  const dirty = TK.qsa('form[data-dirty]').map((f) => ({ f, base: snap(f), tag: f.closest('.card')?.querySelector('.dirty-tag') }));
  const paint = () => dirty.forEach((d) => { if (d.tag) d.tag.hidden = snap(d.f) === d.base; });
  dirty.forEach((d) => {
    d.f.addEventListener('input', paint);
    d.f.addEventListener('tk:done', (e) => { if (TK.ok(e.detail)) { d.base = snap(d.f); paint(); } });
  });
  const isDirty = () => dirty.some((d) => snap(d.f) !== d.base);
  TK.leaveGuard = isDirty;
  window.addEventListener('beforeunload', (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ''; } });
});
