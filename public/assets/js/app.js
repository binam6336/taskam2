/* Taskam — هسته‌ی جاوااسکریپت یکپارچه (فضای نام TK). ماژول‌های هر صفحه در modules/ هستند. */
(() => {
  'use strict';
  const TK = (window.TK = {});
  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
  TK.base = meta('base-url');
  TK.csrf = meta('csrf-token');
  TK.url = (p) => TK.base + '/' + String(p).replace(/^\//, '');
  TK.qs = (s, r = document) => r.querySelector(s);
  TK.qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
  TK.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  TK.fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
  TK.boot = () => { try { return JSON.parse(TK.qs('#boot')?.textContent || '{}') || {}; } catch { return {}; } };
  TK.on = (ev, sel, fn) => document.addEventListener(ev, (e) => { const t = e.target.closest?.(sel); if (t) fn(e, t); });
  TK.debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  TK.initial = (n) => (String(n || '').trim()[0] || '؟');

  /* ───── HTTP ───── */
  TK.http = async (url, { method = 'GET', data = null, form = null } = {}) => {
    const headers = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'X-CSRF-Token': TK.csrf };
    const opt = { method, headers, credentials: 'same-origin' };
    if (form) opt.body = form;
    else if (data && method !== 'GET') {
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => (Array.isArray(v) ? v.forEach((x) => fd.append(k + '[]', x)) : v !== undefined && v !== null && fd.append(k, v)));
      opt.body = fd;
    }
    let res;
    try { res = await fetch(url.startsWith('http') || url.startsWith(TK.base + '/') ? url : TK.url(url), opt); }
    catch { return { status: 'error', message: 'ارتباط با سرور برقرار نشد.' }; }
    let json = null;
    try { json = await res.json(); } catch { json = { status: 'error', message: 'پاسخ نامعتبر از سرور (' + res.status + ')' }; }
    if (res.status === 419) { TK.toast(json.message || 'نشست منقضی شد؛ صفحه رفرش می‌شود.', 'warning'); setTimeout(() => location.reload(), 1400); }
    if (res.status === 401) { location.href = TK.url('/login'); }
    json._http = res.status;
    return json;
  };
  TK.get = (url, params = {}) => { const q = new URLSearchParams(params).toString(); return TK.http(url + (q ? (url.includes('?') ? '&' : '?') + q : '')); };
  TK.post = (url, data) => TK.http(url, { method: 'POST', data });
  TK.ok = (r) => r && (r.status === 'success' || r.success === true);

  /* ───── Toast ───── */
  TK.toast = (msg, type = 'success', ms = 4200) => {
    if (!msg) return;
    let box = TK.qs('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
    const icon = { success: 'fa-circle-check', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' }[type] || 'fa-circle-info';
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = `<i class="fa-solid ${icon}"></i><div>${TK.esc(msg)}</div>`;
    box.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, ms);
    return el;
  };

  /* ───── Modal ───── */
  TK.modal = {
    open(id) { const m = typeof id === 'string' ? document.getElementById(id) : id; if (!m) return; m.classList.add('open'); document.body.style.overflow = 'hidden'; m.querySelector('[autofocus],input:not([type=hidden]),textarea')?.focus({ preventScroll: true }); m.dispatchEvent(new CustomEvent('tk:open')); },
    close(id) { const m = typeof id === 'string' ? document.getElementById(id) : id; if (!m) return; m.classList.remove('open'); if (!TK.qs('.modal.open')) document.body.style.overflow = ''; m.dispatchEvent(new CustomEvent('tk:close')); },
  };
  TK.confirm = (message, { okText = 'تأیید', danger = true } = {}) => new Promise((resolve) => {
    const m = document.getElementById('tk-confirm');
    if (!m) return resolve(window.confirm(message));
    m.querySelector('[data-msg]').textContent = message;
    const ok = m.querySelector('[data-ok]'); ok.textContent = okText; ok.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
    const done = (v) => { TK.modal.close(m); ok.onclick = null; cancel.onclick = null; resolve(v); };
    const cancel = m.querySelector('[data-cancel]');
    ok.onclick = () => done(true); cancel.onclick = () => done(false);
    TK.modal.open(m);
  });

  /* ───── Form helpers ───── */
  TK.fillForm = (form, data = {}) => {
    TK.qsa('[name]', form).forEach((el) => {
      const n = el.name.replace(/\[\]$/, '');
      if (!(n in data)) return;
      const v = data[n];
      if (el.type === 'checkbox') { el.checked = Array.isArray(v) ? v.includes(el.value) : !!Number(v) || v === true; }
      else if (el.type === 'radio') el.checked = String(el.value) === String(v);
      else if (el.type !== 'file') el.value = v ?? '';
    });
  };
  TK.resetForm = (form) => { form.reset(); TK.qsa('input[type=hidden]:not([name=_csrf]):not([data-keep])', form).forEach((i) => (i.value = '')); };
  TK.busy = (btn, on) => {
    if (!btn) return;
    if (on) { btn.dataset.html = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spin"></span>'; }
    else { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; }
  };
  TK.copy = async (text) => {
    try { await navigator.clipboard.writeText(text); }
    catch { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
    TK.toast('کپی شد', 'success', 1600);
  };
  TK.after = (r, el) => {
    if (r && r.redirect) { setTimeout(() => (location.href = r.redirect), 350); return; }
    const mode = el?.dataset.after || 'reload';
    if (mode === 'reload') setTimeout(() => TK.reload(), 250);
    else if (mode.startsWith('redirect:')) setTimeout(() => (location.href = TK.url(mode.slice(9))), 450);
  };

  /* ───── انتخابگر با لوگو/آواتار (جایگزین <select> برای کاربر و پروژه) ───── */
  TK.rselect = (host, { name, options, placeholder = 'انتخاب کنید', square = false }) => {
    host.classList.add('rselect');
    host.innerHTML = `<input type="hidden" name="${TK.esc(name)}" value=""><button type="button" class="input rselect-btn"><span class="rselect-val"></span><i class="fa-solid fa-chevron-down xs faint"></i></button><div class="rselect-menu" role="listbox"></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('.rselect-btn'), val = host.querySelector('.rselect-val'), menu = host.querySelector('.rselect-menu');
    const av = (o) => `<span class="avatar sm ${square ? 'sq' : ''}">${o.img ? `<img src="${TK.esc(o.img)}" alt="" loading="lazy">` : TK.esc(TK.initial(o.label))}</span>`;
    menu.innerHTML = options.length ? options.map((o) => `<div class="rselect-opt" role="option" data-v="${TK.esc(o.value)}">${av(o)}<span class="truncate">${TK.esc(o.label)}</span></div>`).join('') : '<div class="faint small" style="padding:.6rem">موردی وجود ندارد</div>';
    const set = (v) => {
      v = v === null || v === undefined ? '' : String(v);
      inp.value = v;
      const o = options.find((x) => String(x.value) === v);
      val.innerHTML = o ? `${av(o)}<span class="truncate">${TK.esc(o.label)}</span>` : `<span class="faint">${TK.esc(placeholder)}</span>`;
      TK.qsa('.rselect-opt', menu).forEach((el) => el.classList.toggle('active', el.dataset.v === v));
      host.classList.remove('invalid');
    };
    btn.addEventListener('click', (e) => { e.stopPropagation(); if (btn.disabled) return; TK.qsa('.rselect.open').forEach((x) => x !== host && x.classList.remove('open')); host.classList.toggle('open'); });
    menu.addEventListener('click', (e) => { const o = e.target.closest('.rselect-opt'); if (!o) return; set(o.dataset.v); host.classList.remove('open'); inp.dispatchEvent(new Event('change', { bubbles: true })); });
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) host.classList.remove('open'); });
    set('');
    return { input: inp, host, set, get: () => inp.value, disable(off) { host.classList.toggle('disabled', !!off); btn.disabled = !!off; inp.disabled = !!off; }, invalid() { host.classList.add('invalid'); } };
  };

  /* ───── Global delegated behaviours ───── */
  TK.on('click', '[data-modal]', (e, t) => { e.preventDefault(); TK.modal.open(t.dataset.modal); });
  TK.on('click', '[data-close]', (e, t) => { e.preventDefault(); TK.modal.close(t.closest('.modal')); });
  TK.on('mousedown', '.modal', (e, t) => { if (e.target === t) TK.modal.close(t); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { const m = TK.qsa('.modal.open').pop(); if (m) TK.modal.close(m); TK.qsa('.dropdown.open,.qa.open').forEach((d) => d.classList.remove('open')); } });
  TK.on('change', '[data-autosubmit]', (e, t) => t.form.submit());
  TK.on('click', '[data-copy]', (e, t) => TK.copy(t.dataset.copy));
  TK.on('click', '[data-copy-target]', (e, t) => TK.copy(TK.qs(t.dataset.copyTarget)?.innerText || ''));
  TK.on('click', '.dropdown > [data-toggle]', (e, t) => { e.stopPropagation(); const d = t.parentElement; const was = d.classList.contains('open'); TK.qsa('.dropdown.open').forEach((x) => x.classList.remove('open')); if (!was) d.classList.add('open'); });
  document.addEventListener('click', (e) => { if (!e.target.closest('.dropdown')) TK.qsa('.dropdown.open').forEach((d) => d.classList.remove('open')); if (!e.target.closest('.qa')) TK.qs('.qa')?.classList.remove('open'); });
  TK.on('click', '.qa-fab', (e, t) => {
    if (t.hasAttribute('data-qa-empty')) { TK.toast('از تنظیمات (بخش پروفایل ← دسترسی سریع)، گزینه‌های دسترسی سریع را تنظیم کنید.', 'info', 5500); return; }
    t.closest('.qa').classList.toggle('open');
  });
  TK.on('click', '[data-sidebar]', () => { TK.qs('.sidebar')?.classList.toggle('open'); TK.qs('.overlay')?.classList.toggle('show'); });
  TK.on('click', '.overlay', () => { TK.qs('.sidebar')?.classList.remove('open'); TK.qs('.overlay')?.classList.remove('show'); });

  TK.on('submit', 'form[data-ajax]', async (e, form) => {
    e.preventDefault();
    if (form.dataset.confirm && !(await TK.confirm(form.dataset.confirm))) return;
    const btn = e.submitter || form.querySelector('[type=submit]:not([data-no-busy])');
    const fd = new FormData(form);
    // مقدار دکمه‌ی کلیک‌شده (name/value) به‌صورت خودکار در FormData نمی‌آید
    if (e.submitter && e.submitter.name && !fd.has(e.submitter.name)) fd.append(e.submitter.name, e.submitter.value);
    TK.busy(btn, true);
    const r = await TK.http(form.getAttribute('action') || location.href, { method: 'POST', form: fd });
    TK.busy(btn, false);
    TK.toast(r.message || (TK.ok(r) ? 'انجام شد' : 'خطا'), TK.ok(r) ? 'success' : 'error');
    form.dispatchEvent(new CustomEvent('tk:done', { detail: r, bubbles: true }));
    if (TK.ok(r)) { const m = form.closest('.modal'); if (m && form.dataset.keepOpen === undefined) TK.modal.close(m); TK.after(r, form); }
  });

  TK.on('click', '[data-post]', async (e, t) => {
    e.preventDefault();
    if (t.dataset.confirm && !(await TK.confirm(t.dataset.confirm, { danger: t.dataset.danger !== '0', okText: t.dataset.ok || 'تأیید' }))) return;
    TK.busy(t.classList.contains('btn') ? t : null, true);
    const extra = t.dataset.data ? JSON.parse(t.dataset.data) : {};
    const r = await TK.post(t.dataset.post, extra);
    TK.busy(t.classList.contains('btn') ? t : null, false);
    TK.toast(r.message || (TK.ok(r) ? 'انجام شد' : 'خطا'), TK.ok(r) ? 'success' : 'error');
    t.dispatchEvent(new CustomEvent('tk:done', { detail: r, bubbles: true }));
    if (TK.ok(r)) { if (t.dataset.remove) TK.qs(t.dataset.remove)?.remove(); TK.after(r, t); }
  });

  // data-edit: پر کردن فرم مودال و باز کردن آن
  TK.on('click', '[data-edit]', (e, t) => {
    e.preventDefault();
    const m = document.getElementById(t.dataset.target);
    if (!m) return;
    const data = JSON.parse(t.dataset.edit || '{}');
    const form = m.querySelector('form');
    TK.resetForm(form);
    TK.fillForm(form, data);
    if (t.dataset.action) form.setAttribute('action', TK.url(t.dataset.action));
    m.dispatchEvent(new CustomEvent('tk:fill', { detail: data }));
    TK.modal.open(m);
  });

  // فلش‌های سرور → toast
  const flash = TK.boot().flash || [];
  flash.forEach((f) => TK.toast(f.message, f.type));

  /* ───── Charts (SVG) ───── */
  const C = ['#4ade80', '#60a5fa', '#fbbf24', '#f87171', '#c084fc', '#2dd4bf'];
  TK.charts = {
    colors: C,
    line(el, { labels, series, height = 220 }) {
      if (!el) return;
      const W = 640, H = height, p = { l: 34, r: 12, t: 12, b: 26 };
      const max = Math.max(1, ...series.flatMap((s) => s.data));
      const step = Math.max(1, Math.ceil(max / 4)), top = step * Math.ceil(max / step);
      const x = (i) => p.l + (i * (W - p.l - p.r)) / Math.max(1, labels.length - 1);
      const y = (v) => H - p.b - (v / top) * (H - p.t - p.b);
      let g = '';
      for (let v = 0; v <= top; v += step) g += `<line class="axis" x1="${p.l}" x2="${W - p.r}" y1="${y(v)}" y2="${y(v)}" stroke-dasharray="3 4"/><text x="${p.l - 8}" y="${y(v) + 3}" text-anchor="end">${TK.fa(v)}</text>`;
      labels.forEach((l, i) => { if (labels.length <= 8 || i % 2 === 0) g += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle">${TK.esc(l)}</text>`; });
      series.forEach((s, k) => {
        const col = s.color || C[k % C.length];
        const pts = s.data.map((v, i) => `${x(i)},${y(v)}`).join(' ');
        g += `<defs><linearGradient id="lg${k}${el.id}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${col}" stop-opacity=".28"/><stop offset="1" stop-color="${col}" stop-opacity="0"/></linearGradient></defs>`;
        g += `<polygon points="${x(0)},${y(0)} ${pts} ${x(s.data.length - 1)},${y(0)}" fill="url(#lg${k}${el.id})"/>`;
        g += `<polyline points="${pts}" fill="none" stroke="${col}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>`;
        s.data.forEach((v, i) => (g += `<circle cx="${x(i)}" cy="${y(v)}" r="3.2" fill="#060908" stroke="${col}" stroke-width="2"><title>${TK.esc(s.name)}: ${TK.fa(v)}</title></circle>`));
      });
      el.innerHTML = `<svg viewBox="0 0 ${W} ${H}" role="img">${g}</svg><div class="legend">${series.map((s, k) => `<span><i style="background:${s.color || C[k % C.length]}"></i>${TK.esc(s.name)}</span>`).join('')}</div>`;
    },
    bars(el, { items, height = 220 }) {
      if (!el) return;
      const W = 640, H = height, p = { l: 10, r: 10, t: 14, b: 40 };
      const max = Math.max(1, ...items.map((i) => i.value));
      const bw = (W - p.l - p.r) / items.length;
      let g = '';
      items.forEach((it, i) => {
        const h = (it.value / max) * (H - p.t - p.b), x = p.l + i * bw + bw * 0.18, w = bw * 0.64, y = H - p.b - h, col = it.color || C[i % C.length];
        g += `<rect x="${x}" y="${y}" width="${w}" height="${Math.max(h, 2)}" rx="6" fill="${col}" opacity=".9"><title>${TK.esc(it.label)}: ${TK.fa(it.value)}</title></rect>`;
        g += `<text x="${x + w / 2}" y="${y - 6}" text-anchor="middle" style="fill:#e9f3ee">${TK.fa(it.value)}</text>`;
        g += `<text x="${x + w / 2}" y="${H - 18}" text-anchor="middle">${TK.esc(String(it.label).slice(0, 14))}</text>`;
      });
      el.innerHTML = `<svg viewBox="0 0 ${W} ${H}">${g}<line class="axis" x1="${p.l}" x2="${W - p.r}" y1="${H - p.b}" y2="${H - p.b}"/></svg>`;
    },
    donut(el, { items, center = '' }) {
      if (!el) return;
      const total = items.reduce((s, i) => s + i.value, 0) || 1, R = 54, S = 18, CIRC = 2 * Math.PI * R;
      let off = 0, g = `<circle cx="70" cy="70" r="${R}" fill="none" stroke="#1a2620" stroke-width="${S}"/>`;
      items.forEach((it, i) => {
        const len = (it.value / total) * CIRC;
        if (it.value > 0) g += `<circle cx="70" cy="70" r="${R}" fill="none" stroke="${it.color || C[i % C.length]}" stroke-width="${S}" stroke-dasharray="${len} ${CIRC - len}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"><title>${TK.esc(it.label)}: ${TK.fa(it.value)}</title></circle>`;
        off += len;
      });
      el.innerHTML = `<div class="donut"><svg viewBox="0 0 140 140">${g}</svg><div class="center-txt">${center}</div></div><div class="legend" style="justify-content:center">${items.map((it, i) => `<span><i style="background:${it.color || C[i % C.length]}"></i>${TK.esc(it.label)} (${TK.fa(it.value)})</span>`).join('')}</div>`;
    },
  };

  /* ═════════ سیستم زنده‌ی یکپارچه (AJAX) + اعلان مرورگر ═════════
     • بخش‌هایی با data-live بدون رفرش به‌روز می‌شوند (فقط وقتی داده‌ی صفحه تغییر کرده باشد)
     • نشان‌های سایدبار (پیام، پشتیبانی، ...) هر چند ثانیه تازه می‌شوند
     • پیام جدید و یادآوری تسک به‌صورت Notification مرورگر نمایش داده می‌شود (روی هر صفحه‌ی پنل) */
  TK.state = TK.state || {};
  const boot = TK.boot();
  const ICON = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%2322c55e'/%3E%3Cpath d='M18 33l9 9 19-21' stroke='%2304210f' stroke-width='6.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E";
  const mem = {};
  const ls = {
    get(k) { try { return localStorage.getItem(k); } catch { return mem[k] ?? null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { mem[k] = v; } },
  };

  TK.notify = ({ title, body = '', url = '', tag = '' }) => {
    const go = () => { window.focus(); if (url) location.href = url; };
    if ('Notification' in window && Notification.permission === 'granted') {
      try {
        const n = new Notification(title, { body, tag: tag || undefined, icon: ICON, dir: 'rtl', lang: 'fa' });
        n.onclick = () => { n.close(); go(); };
        return;
      } catch { /* برخی مرورگرهای موبایل: ادامه با toast */ }
    }
    const el = TK.toast(title + (body ? ' — ' + body.slice(0, 90) : ''), 'info', 9000);
    if (el && url) { el.style.cursor = 'pointer'; el.addEventListener('click', go); }
  };

  TK.live = {
    sig: null, busy: false, skip: false,
    has: () => !!TK.qs('[data-live]'),
    async refresh() {
      if (TK.live.busy) return;
      TK.live.busy = true;
      try {
        const res = await fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Live': '1', Accept: 'text/html' }, credentials: 'same-origin' });
        if (!res.ok || !(res.headers.get('content-type') || '').includes('text/html')) return;
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const changed = [];
        TK.qsa('[data-live]').forEach((el) => {
          const next = doc.querySelector(`[data-live="${el.dataset.live}"]`);
          if (!next) return;
          const a = document.activeElement;
          const typing = a && el.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName);
          if (typing || el.querySelector('.dropdown.open')) return;     // وسط کار کاربر دست نمی‌زنیم
          if (next.innerHTML !== el.innerHTML) { el.innerHTML = next.innerHTML; changed.push(el.dataset.live); }
        });
        if (changed.length) document.dispatchEvent(new CustomEvent('tk:live', { detail: { regions: changed } }));
      } catch { /* شبکه */ } finally { TK.live.busy = false; }
    },
    async poll() {
      if (!boot.live) return;
      const KEY = 'tk_last_msg_' + (boot.live.uid || 0);
      const last = ls.get(KEY);
      const params = { scope: boot.live.scope || '' };
      if (last === null) params.baseline = 1; else params.last_msg = last;
      const r = await TK.get('/live/poll', params);
      if (!r || r.status !== 'success') return;
      Object.entries(r.counts || {}).forEach(([k, v]) => TK.qsa(`[data-badge="${k}"]`).forEach((b) => { b.textContent = TK.fa(v); b.classList.toggle('zero', !v); }));
      if (r.msg_base !== null && r.msg_base !== undefined) ls.set(KEY, String(r.msg_base));
      (r.messages || []).forEach((m) => {
        if (m.id <= Number(ls.get(KEY) || 0)) return;                    // تب دیگری قبلاً اعلان داده
        ls.set(KEY, String(m.id));
        if (TK.state.chatUser === m.sender && document.hasFocus() && !document.hidden) return;   // همین گفتگو باز است
        TK.notify({ title: m.title, body: m.body, url: m.url, tag: 'msg-' + m.sender });
      });
      (r.due || []).forEach((t) => TK.notify({ title: t.title, body: t.body, url: t.url, tag: 'task-' + t.id }));
      if (r.sig) {
        if (TK.live.sig !== null && r.sig !== TK.live.sig && !TK.live.skip) TK.live.refresh();
        TK.live.skip = false;
        TK.live.sig = r.sig;
      }
    },
  };
  /** بعد از هر عملیات موفق: اگر صفحه بخش زنده دارد بدون رفرش، وگرنه رفرش معمولی */
  TK.reload = async () => {
    if (!TK.live.has()) { location.reload(); return; }
    await TK.live.refresh();
    TK.live.skip = true;
    TK.live.poll();
  };

  if (boot.live) {
    const loop = async () => { try { await TK.live.poll(); } catch { /* ادامه */ } setTimeout(loop, document.hidden ? 15000 : 6000); };
    loop();
    document.addEventListener('visibilitychange', () => { if (!document.hidden) TK.live.poll(); });
  }

  /* مجوز اعلان مرورگر: دکمه‌ی زنگ بالای صفحه */
  const bell = TK.qs('#notif-btn');
  const paintBell = () => {
    if (!bell) return;
    const supported = 'Notification' in window, p = supported ? Notification.permission : 'denied';
    bell.dataset.state = p;
    bell.querySelector('i').className = p === 'granted' ? 'fa-solid fa-bell' : p === 'denied' ? 'fa-solid fa-bell-slash' : 'fa-regular fa-bell';
    bell.title = p === 'granted' ? 'اعلان‌های مرورگر فعال است (کلیک: اعلان آزمایشی)' : p === 'denied' ? 'اعلان‌ها مسدود است' : 'فعال‌سازی اعلان مرورگر';
  };
  paintBell();
  TK.on('click', '#notif-btn', async () => {
    if (!('Notification' in window)) return TK.toast('مرورگر شما از اعلان پشتیبانی نمی‌کند.', 'warning');
    if (Notification.permission === 'default') {
      const p = await Notification.requestPermission(); paintBell();
      return TK.toast(p === 'granted' ? 'اعلان‌های مرورگر فعال شد.' : 'اجازه‌ی اعلان داده نشد.', p === 'granted' ? 'success' : 'warning');
    }
    if (Notification.permission === 'denied') return TK.toast('اعلان‌ها در مرورگر مسدود است؛ از آیکن قفل کنار آدرس، اعلان‌ها را «مجاز» کنید.', 'warning', 7000);
    TK.notify({ title: 'پیام جدید – تسکام', body: 'اعلان‌های مرورگر فعال است ✅', url: '' });
  });
  if (boot.live && 'Notification' in window && Notification.permission === 'default' && !sessionStorage.getItem('tk_notif_hint')) {
    sessionStorage.setItem('tk_notif_hint', '1');
    setTimeout(() => TK.toast('برای دریافت اعلان پیام جدید و یادآوری تسک، روی زنگ بالای صفحه کلیک کنید.', 'info', 7000), 1500);
  }

  /* ───── Page module loader ─────
     ماژول‌های صفحه بعد از app.js لود می‌شوند؛ پس رویداد باید بعد از اجرای همه‌ی اسکریپت‌ها (DOMContentLoaded) ارسال شود. */
  const fireReady = () => document.dispatchEvent(new CustomEvent('tk:ready'));
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fireReady);
  else setTimeout(fireReady, 0);
})();
