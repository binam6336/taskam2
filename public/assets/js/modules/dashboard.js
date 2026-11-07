document.addEventListener('tk:ready', () => {
  // «منتظر اقدام» ← برگرداندن تسک «بررسی سرپرست» برای اصلاح، بدون باز کردن تسک
  TK.on('click', '[data-dash-rework]', async (e, b) => {
    e.preventDefault();
    const why = await TK.prompt('چه چیزی باید اصلاح شود؟ (اختیاری؛ به‌صورت یادداشت روی تسک ثبت و برای مسئول فرستاده می‌شود)', { title: 'برگرداندن برای اصلاح', okText: 'برگرداندن', multiline: true });
    if (why === null) return;
    const r = await TK.post('/tasks/' + b.dataset.dashRework + '/status', { status: 'rework', reason: why });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) TK.reload();
  });
  const d = TK.boot().d; if (!d) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: d.trend.labels, series: [{ name: 'تسک‌های جدید', data: d.trend.tasks }] });
  const ratio = d.stats.total ? Math.round((d.stats.completed / d.stats.total) * 100) : 0;
  TK.charts.donut(TK.qs('#ch-ratio'), { items: [{ label: 'انجام‌شده', value: d.stats.completed, color: TK.charts.colors[0] }, { label: 'در جریان', value: d.stats.pending, color: '#fbbf24' }], center: TK.fa(ratio) + '٪' });
  const po = d.prio_open || d.stats;   // پایه‌ی شمارش: فقط تسک‌های باز (همان تب «در جریان»)
  TK.charts.bars(TK.qs('#ch-prio'), { height: 190, items: [{ label: 'زیاد', value: po.high, color: '#f87171' }, { label: 'متوسط', value: po.medium, color: '#fbbf24' }, { label: 'کم', value: po.low, color: TK.charts.colors[0] }] });
});

// پیشخوان v5.6: فیلتر اولویت، بخش‌های تاشو، چیپ‌های پرش، انیمیشن ورود، کیبورد (N و ↑/↓)
document.addEventListener('tk:ready', () => {
  if (!TK.qs('.dash-main')) return;
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { /* حالت خصوصی */ } },
  };
  const PK = 'tk.dash.prio', SK = 'tk.dash.sec.';
  const NAMES = { high: 'بالا', medium: 'متوسط', low: 'پایین' };
  const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let prio = store.get(PK); if (!['all', 'high', 'medium', 'low'].includes(prio)) prio = 'all';

  const applyFilter = (announce) => {
    TK.qsa('[data-dash-prio]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.dashPrio === prio)));
    let shown = 0;
    TK.qsa('.dash-focus .dash-sec-body').forEach((body) => {
      const rows = TK.qsa('.dash-task', body); if (!rows.length) return;
      let n = 0;
      rows.forEach((r) => { const ok = prio === 'all' || r.dataset.prio === prio; r.hidden = !ok; if (ok) n++; });
      const empty = TK.qs('.dash-empty', body); if (empty) empty.hidden = n > 0;
      shown += n;
    });
    const live = TK.qs('#dash-filter-live');
    if (live && announce) live.textContent = TK.fa(shown) + ' تسک نمایش داده می‌شود' + (prio === 'all' ? '' : ' (اولویت ' + NAMES[prio] + ')');
  };
  const applySections = () => TK.qsa('[data-dash-sec]').forEach((b) => {
    const open = store.get(SK + b.dataset.dashSec) !== '0';
    b.setAttribute('aria-expanded', String(open));
    const body = document.getElementById(b.getAttribute('aria-controls')); if (body) body.hidden = !open;
  });
  const animate = () => {
    if (reduced()) return;
    TK.qsa('.dash-focus .dash-tasks').forEach((list) => TK.qsa('.dash-task', list).forEach((r, i) => {
      r.style.setProperty('animation-delay', (i * 35) + 'ms'); r.classList.add('dash-in');
    }));
  };
  const init = (anim) => { applySections(); applyFilter(false); if (anim) animate(); };
  init(true);
  // به‌روزرسانی زنده‌ی بخش بالای پیشخوان (data-live) HTML را عوض می‌کند → وضعیت دوباره اعمال شود
  document.addEventListener('tk:live', () => init(false));

  TK.on('click', '[data-dash-prio]', (e, b) => { prio = b.dataset.dashPrio; store.set(PK, prio); applyFilter(true); });
  TK.on('click', '[data-dash-sec]', (e, b) => {
    const open = b.getAttribute('aria-expanded') !== 'true';
    store.set(SK + b.dataset.dashSec, open ? '1' : '0'); applySections();
  });
  // چیپ‌های خلاصه: پرش به بخش و فوکوس روی اولین لینک/دکمه‌ی قابل‌مشاهده‌ی آن
  TK.on('click', '[data-dash-jump]', (e, b) => {
    const sec = document.getElementById(b.dataset.dashJump); if (!sec) return;
    const tog = TK.qs('[data-dash-sec]', sec);
    if (tog && tog.getAttribute('aria-expanded') === 'false') tog.click();
    sec.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
    const target = TK.qsa('.dash-sec-body a:not([hidden]), .dash-sec-body button:not([hidden]), .list a, .list button', sec).find((x) => x.offsetParent !== null)
      || TK.qs('a, button', sec);
    if (target) target.focus({ preventScroll: true });
  });
  // کیبورد: N/ی = ثبت سریع؛ ↑/↓ روی ردیف تسک = ردیف قابل‌مشاهده‌ی بعدی/قبلی (Enter خود لینک را باز می‌کند)
  document.addEventListener('keydown', (e) => {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const el = document.activeElement;
    if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && el && el.classList.contains('dash-task')) {
      const rows = TK.qsa('.dash-focus .dash-task').filter((r) => !r.hidden && r.offsetParent !== null);
      const i = rows.indexOf(el), next = rows[i + (e.key === 'ArrowDown' ? 1 : -1)];
      if (next) { e.preventDefault(); next.focus(); }
      return;
    }
    if (e.key !== 'n' && e.key !== 'N' && e.key !== 'ی') return;
    if (el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName))) return;
    if (TK.qs('.modal.open')) return;
    const fab = TK.qs('.qa-fab'); if (fab) { e.preventDefault(); fab.click(); }
  });
});
