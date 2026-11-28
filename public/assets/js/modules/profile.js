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

  /* مصرف هوش مصنوعی (v7.21): هر ۲۰ ثانیه وقتی بخش «مصرف هوش مصنوعی» باز است، بدون بارگذاری دوباره‌ی صفحه */
  const aiu = TK.qs('[data-aiu]');
  if (aiu) {
    const fa = (n) => Number(n || 0).toLocaleString('en-US').replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]).replace(/,/g, '٬');
    const ICON = { daily: 'fa-sun', monthly: 'fa-calendar', total: 'fa-layer-group' };
    const card = (k, x) => {
      const tone = x.unlimited ? '' : (x.percent >= 100 ? ' is-full' : (x.percent >= 80 ? ' is-near' : ''));
      return '<div class="card aiu-card' + tone + '" data-aiu-kind="' + k + '"><div class="aiu-head"><span class="aiu-ic"><i class="fa-solid ' + ICON[k] + '" aria-hidden="true"></i></span><h3>مصرف ' + TK.esc(x.label) + '</h3>'
        + '<span class="aiu-pct">' + (x.unlimited ? 'نامحدود' : fa(x.percent) + '٪') + '</span></div>'
        + '<div class="aiu-num"><b>' + fa(x.used) + '</b> / ' + (x.unlimited ? 'نامحدود' : fa(x.limit)) + ' توکن</div>'
        + (x.unlimited
          ? '<div class="aiu-foot small muted">برای این بازه سقفی تعیین نشده است.' + (x.reset ? ' شمارش از نو: ' + TK.esc(x.reset) : '') + '</div>'
          : '<div class="aiu-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + (x.percent | 0) + '" aria-label="درصد مصرف ' + TK.esc(x.label) + '"><span data-css="width:' + (x.percent | 0) + '%"></span></div>'
            + '<div class="aiu-foot small muted">باقی‌مانده: ' + fa(x.remaining) + ' توکن' + (x.reset ? ' — بازنشانی: ' + TK.esc(x.reset) : '') + '</div>')
        + '</div>';
    };
    const render = (u) => {
      const storage = '<h3><i class="fa-solid fa-hard-drive"></i> مصرف فضای ذخیره‌سازی پروژه‌ها</h3>'
        + (u.storage.length ? u.storage.map((s) => '<div class="card aiu-card"><h3>' + TK.esc(s.title) + '</h3><a class="btn btn-sm" href="' + TK.esc(TK.url('/profile/storage/' + s.pid)) + '">جزئیات فایل‌ها</a><div class="aiu-num"><b>' + TK.esc(s.used_text) + '</b> / ' + TK.esc(s.limit_text) + '</div>'
          + (s.unlimited ? '<p class="small muted">فضای ذخیره‌سازی این پروژه نامحدود است.</p>' : '<progress max="100" value="' + s.percent + '" aria-label="درصد مصرف فضای پروژه"></progress><p class="small muted">' + fa(s.percent) + '٪ مصرف شده</p>')
          + '<p class="small muted">پیوست‌های تسک‌ها، یادداشت‌های تسک و تصویر پروژه؛ حذف فایل فضا را آزاد می‌کند.</p></div>').join('') : '<p class="small muted">پروژه‌ای با دسترسی مشاهده فضای ذخیره‌سازی ندارید.</p>')
        + '<h3 class="mt-2"><i class="fa-solid fa-brain"></i> مصرف هوش مصنوعی</h3>';
      aiu.innerHTML = storage + (u.blocked ? '<div class="aiu-alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>' + TK.esc(u.blocked) + '</span></div>' : '')
        + '<div class="aiu-grid">' + ['daily', 'monthly', 'total'].map((k) => card(k, u[k])).join('') + '</div>'
        + '<div class="card aiu-feat"><h3 class="small">مصرف ' + TK.esc(u.month) + ' به تفکیک بخش</h3>'
        + (u.features.length ? '<ul>' + u.features.map((f) => '<li><span>' + TK.esc(f.feature) + '</span><span class="muted">' + fa(f.requests) + ' درخواست</span><b>' + fa(f.tokens) + ' توکن</b></li>').join('') + '</ul>' : '<p class="small muted">این ماه هنوز از هوش مصنوعی استفاده نکرده‌اید.</p>') + '</div>';
      TK.applyCss && TK.applyCss(aiu);
    };
    const tick = async () => {
      if (document.hidden || aiu.closest('[data-pf-pane]')?.hidden) return;
      const r = await TK.get('/profile/ai-usage');
      if (r.status === 'success' && r.usage) render(r.usage);
    };
    setInterval(tick, 20000);
    window.addEventListener('hashchange', () => location.hash === '#ai' && tick());
  }

  /* تنظیمات ظاهری (v7.0): تم بلافاصله اعمال و در کوکی همین دستگاه (kx_theme) ذخیره می‌شود */
  const tf = TK.qs('#f-theme');
  tf?.addEventListener('change', async (e) => {
    if (e.target.name !== 'theme') return;
    const html = document.documentElement, prev = html.dataset.theme, prevMode = html.dataset.mode, next = e.target.value;
    let modes = {}; try { modes = JSON.parse(tf.dataset.modes || '{}'); } catch { /* */ }
    html.dataset.theme = next; html.dataset.mode = modes[next] || 'dark';
    const r = await TK.post('/profile/appearance', { theme: next });
    TK.toast(r.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (!TK.ok(r)) { html.dataset.theme = prev; html.dataset.mode = prevMode; const old = tf.querySelector(`input[value="${prev}"]`); if (old) old.checked = true; return; }
    TK.qs('meta[name=theme-color]')?.setAttribute('content', getComputedStyle(html).getPropertyValue('--bg').trim());
    // لوگو، فاوآیکن و آیکن نصب مستقل از تم باقی می‌مانند.
  });
  // تصویر پروفایل: پیش‌نمایش و بارگذاری خودکار بعد از انتخاب (بدون دکمه‌ی جدا)
  TK.qs('#av-file')?.addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const url = URL.createObjectURL(f); TK.qs('#av-preview').innerHTML = `<img loading="lazy" decoding="async" src="${url}" alt="">`;
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
