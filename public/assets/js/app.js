/* Taskam — هسته‌ی جاوااسکریپت یکپارچه (فضای نام TK). ماژول‌های هر صفحه در modules/ هستند. */
(() => {
  'use strict';
  const TK = (window.TK = {});
  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
  TK.base = meta('base-url');
  TK.csrf = meta('csrf-token');
  TK.afterSwap = [];                                       // کارهای هسته که بعد از هر جابه‌جایی نرم صفحه دوباره اجرا می‌شوند
  TK.url = (p) => TK.base + '/' + String(p).replace(/^\//, '');
  TK.qs = (s, r = document) => r.querySelector(s);
  TK.qsa = (s, r = document) => Array.from(r.querySelectorAll(s));
  /* ───── data-css: CSP بدون 'unsafe-inline' (ویژگی style درون HTML اجرا نمی‌شود) ─────
     استایل‌های پویا (رنگ، عرض، متغیر --pc و …) در data-css می‌آیند و این‌جا با CSSOM اعمال می‌شوند.
     فقط ویژگی‌های مجاز و مقادیر ساده؛ url()/image/expression رد می‌شود تا HTML تزریق‌شده نتواند CSS بیرونی بارگذاری
     کند یا داده نشت دهد، و position: fixed / z-index مجاز نیست (پوشاندن صفحه). */
  const CSS_OK = /^(--[a-z0-9-]+|color|background|background-color|border-color|box-shadow|fill|stroke|opacity|width|height|min-width|max-width|min-height|max-height|margin|margin-(top|bottom|left|right|inline-start|inline-end)|padding|padding-(top|bottom|left|right|inline-start|inline-end)|padding-inline-start|font-size|font-weight|line-height|text-align|text-decoration|white-space|justify-content|align-items|flex|flex-direction|gap|display|cursor|overflow|top|left|right|bottom|position)$/;
  const applyCss = (el) => {
    const raw = el.getAttribute('data-css');
    el.removeAttribute('data-css');
    if (!raw) return;
    raw.split(';').forEach((d) => {
      const i = d.indexOf(':'); if (i < 1) return;
      const prop = d.slice(0, i).trim().toLowerCase(), val = d.slice(i + 1).trim();
      if (!CSS_OK.test(prop) || !val || val.length > 200 || /url|image|expression|attr\(|[\\<>{};@]/i.test(val)) return;
      if (prop === 'position' && !/^(relative|absolute|static)$/.test(val)) return;
      el.style.setProperty(prop, val);
    });
  };
  TK.applyCss = (root = document) => {
    if (root.nodeType === 1 && root.hasAttribute('data-css')) applyCss(root);
    if (root.querySelectorAll) root.querySelectorAll('[data-css]').forEach(applyCss);
  };
  TK.applyCss();
  new MutationObserver((list) => list.forEach((m) => {
    if (m.type === 'attributes') { if (m.target.hasAttribute('data-css')) applyCss(m.target); return; }
    m.addedNodes.forEach((n) => { if (n.nodeType === 1) TK.applyCss(n); });
  })).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-css'] });

  TK.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  TK.fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
  TK.boot = () => { try { return JSON.parse(TK.qs('#boot')?.textContent || '{}') || {}; } catch { return {}; } };
  TK.on = (ev, sel, fn) => document.addEventListener(ev, (e) => { const t = e.target.closest?.(sel); if (t) fn(e, t); });
  TK.debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  // متن placeholder فیلدها که ادمین در «محتوا ← Placeholder» ویرایش کرده (boot.ph فقط ویرایش‌شده‌ها را دارد)
  TK.ph = (id, def) => { const m = TK.boot().ph; return (m && typeof m[id] === 'string') ? m[id] : def; };
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
    // پنجره‌ی ورود به پنل کاربر بسته شده (پایان زمان، بستن از پنل مدیر، …): صفحه‌ی توضیح سرور نمایش داده می‌شود
    if (json.code === 'IMPERSONATION_ENDED') { TK.toast(json.message || 'این پنجره بسته شده است.', 'warning', 6000); setTimeout(() => location.reload(), 1500); return json; }
    if (res.status === 401) { location.href = TK.url(json.error_code === 'REAUTH_REQUIRED' || json.code === 'REAUTH_REQUIRED' ? '/reauth' : '/login'); }
    json._http = res.status;
    return json;
  };
  TK.get = (url, params = {}) => { const q = new URLSearchParams(params).toString(); return TK.http(url + (q ? (url.includes('?') ? '&' : '?') + q : '')); };
  TK.post = (url, data) => TK.http(url, { method: 'POST', data });
  TK.ok = (r) => r && (r.status === 'success' || r.success === true);

  /* ───── Toast ───── */
  // متن خطاهایی که ادمین در «محتوا ← خطاها» ویرایش کرده ({name} = مقدار متغیر پیام اصلی)
  const errRules = (TK.boot().errs || []).map(([def, txt]) => {
    const vars = [...def.matchAll(/\{([a-z]+)\}/g)].map((m) => m[1]);
    const re = vars.length ? new RegExp('^' + def.split(/(\{[a-z]+\})/).map((p) => (/^\{[a-z]+\}$/.test(p) ? '(.+?)' : p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))).join('') + '$', 's') : null;
    return { def, txt, re, vars };
  });
  TK.errText = (msg) => {
    for (const r of errRules) {
      if (!r.re) { if (msg === r.def) return r.txt; continue; }
      const m = String(msg).match(r.re);
      if (m) return r.vars.reduce((t, v, i) => t.split('{' + v + '}').join(m[i + 1]), r.txt);
    }
    return msg;
  };
  TK.toast = (msg, type = 'success', ms = 4200, { action } = {}) => {
    if (!msg) return;
    if (type === 'error' || type === 'warning') msg = TK.errText(String(msg));
    let box = TK.qs('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
    const icon = { success: 'fa-circle-check', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' }[type] || 'fa-circle-info';
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = `<i class="fa-solid ${icon}"></i><div>${TK.esc(msg)}</div>`;
    // دکمه‌ی اقدام داخل پیام (مثلاً «بازگردانی» بعد از انجام شدن تسک)
    if (action) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'toast-act'; b.textContent = action.label;
      b.addEventListener('click', () => { el.remove(); action.onClick(); }, { once: true });
      el.appendChild(b);
    }
    box.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, ms);
    return el;
  };

  /* ───── Modal ───── */
  TK.modal = {
    open(id) {
      const m = typeof id === 'string' ? document.getElementById(id) : id; if (!m) return;
      m.classList.add('open'); document.body.style.overflow = 'hidden'; m.querySelector('[autofocus],input:not([type=hidden]),textarea')?.focus({ preventScroll: true }); m.dispatchEvent(new CustomEvent('tk:open'));
      if (m.hasAttribute('data-guard')) setTimeout(() => { m._snap = TK.modal.snap(m); }, 80);   // حالت اولیه‌ی فرم (بعد از پر شدن خودکار)
    },
    /** امضای مقادیر فرم‌های داخل پنجره (برای تشخیص تغییر ذخیره‌نشده) */
    snap(m) { const p = []; TK.qsa('form', m).forEach((f) => { for (const [k, v] of new FormData(f)) p.push(k + '=' + (v instanceof File ? v.name : v)); }); return p.join('&'); },
    /** بستن به‌دست کاربر (Esc، کلیک بیرون، انصراف): پنجره‌ی data-guard با تغییر ذخیره‌نشده اول تأیید می‌گیرد */
    async tryClose(m) {
      if (m.hasAttribute('data-guard') && m._snap !== undefined && TK.modal.snap(m) !== m._snap
        && !(await TK.confirm('تغییرات ذخیره نشده‌اند؛ پنجره بسته شود؟', { okText: 'بستن بدون ذخیره' }))) return;
      TK.modal.close(m);
    },
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

  // پنجره‌ی پرسیدن یک متن کوتاه (مثلاً علت برگرداندن تسک برای اصلاح)؛ انصراف = null
  TK.prompt = (message, { title = 'توضیح', okText = 'تأیید', multiline = false, value = '', password = false } = {}) => new Promise((resolve) => {
    let m = document.getElementById('tk-prompt');
    if (!m) {
      m = document.createElement('div'); m.className = 'modal'; m.id = 'tk-prompt';
      m.innerHTML = '<div class="modal-box sm"><form><div class="modal-head"><h3 data-title></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-cancel aria-label="بستن"><i class="fa-solid fa-xmark"></i></button></div>'
        + '<div class="modal-body"><div class="field"><label data-msg></label><div data-in></div></div></div>'
        + '<div class="modal-foot"><button type="button" class="btn" data-cancel>انصراف</button><button class="btn btn-primary" data-ok></button></div></form></div>';
      document.body.appendChild(m);
    }
    m.querySelector('[data-title]').textContent = title; m.querySelector('[data-msg]').textContent = message; m.querySelector('[data-ok]').textContent = okText;
    m.querySelector('[data-in]').innerHTML = multiline ? '<textarea class="input" rows="3" maxlength="255"></textarea>'
      : password ? '<input class="input ltr" type="password" maxlength="255" autocomplete="current-password" required>' : '<input class="input" maxlength="255">';
    const inp = m.querySelector('[data-in] .input'); inp.value = value;
    const form = m.querySelector('form');
    let settled = false;
    const done = (v) => { if (settled) return; settled = true; form.onsubmit = null; TK.modal.close(m); resolve(v); };
    form.onsubmit = (e) => { e.preventDefault(); done(password ? inp.value : inp.value.trim()); };
    m.querySelectorAll('[data-cancel]').forEach((b) => { b.onclick = () => done(null); });
    m.addEventListener('tk:close', () => done(null), { once: true });
    TK.modal.open(m); setTimeout(() => inp.focus(), 50);
  });

  /**
   * ورود مدیر به پنل کاربر در تب جدید (v5.1): نشست و تب پنل مدیر دست نمی‌خورد.
   * اگر مرورگر باز کردن تب را مسدود کند، پیام با دکمه‌ی «باز کردن پنل» نمایش داده می‌شود.
   */
  TK.impersonate = async (uid, to = '') => {
    const r = await TK.post('/admin/users/' + encodeURIComponent(uid) + '/impersonate', { to });
    if (!TK.ok(r) || !r.open) { TK.toast((r && r.message) || 'خطا', 'error'); return null; }
    const w = window.open(r.open, '_blank');
    if (w) { try { w.opener = null; } catch { /* */ } TK.toast(r.message, 'success'); }
    else TK.toast('مرورگر باز کردن تب جدید را مسدود کرد.', 'warning', 12000, { action: { label: 'باز کردن پنل', onClick: () => { const x = window.open(r.open, '_blank'); if (x) x.opener = null; } } });
    document.dispatchEvent(new CustomEvent('tk:impersonated'));
    return r;
  };
  TK.on('click', '[data-impersonate]', (e, b) => { e.preventDefault(); TK.impersonate(b.dataset.impersonate, b.dataset.to || ''); });
  // «پایان و بستن این پنجره» در نوار بالای پنل کاربر
  TK.on('submit', 'form[data-imp-exit]', async (e, f) => {
    e.preventDefault();
    const r = await TK.post(f.getAttribute('action'));
    if (r && r.close) { window.close(); setTimeout(() => { location.href = r.redirect; }, 300); }
  });

  // پنجره‌ی توضیح ساده (مثلاً دلیل قفل بودن تغییر وضعیت تسک)؛ عنصرش در صورت نبود (بعد از جابه‌جایی صفحه) ساخته می‌شود
  TK.info = (message, { title = 'توضیح', icon = 'fa-circle-info' } = {}) => {
    let m = document.getElementById('tk-info');
    if (!m) {
      m = document.createElement('div');
      m.className = 'modal'; m.id = 'tk-info';
      m.innerHTML = '<div class="modal-box sm"><div class="modal-body center"><div class="info-ico"><i class="fa-solid"></i></div><h3 class="mt-1" data-title></h3><p class="muted" data-msg data-css="line-height:1.9"></p><button type="button" class="btn btn-primary mt-1" data-close>متوجه شدم</button></div></div>';
      document.body.appendChild(m);
    }
    m.querySelector('.info-ico i').className = 'fa-solid ' + icon;
    m.querySelector('[data-title]').textContent = title;
    m.querySelector('[data-msg]').textContent = message;
    TK.modal.open(m);
  };
  TK.on('click', '[data-lock-reason]', (e, t) => { e.preventDefault(); e.stopPropagation(); TK.info(t.dataset.lockReason, { title: t.dataset.lockTitle || 'چرا نمی‌توانید وضعیت را تغییر دهید؟', icon: 'fa-lock' }); });

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
  // انتخاب فایل با دکمه‌ی آیکونی (.file-pick): نام فایل‌های انتخاب‌شده کنار دکمه
  const fileNames = (inp) => {
    const out = inp.closest('.file-pick-row')?.querySelector('[data-file-names]');
    if (!out) return;
    const f = [...(inp.files || [])];
    out.textContent = !f.length ? '' : f.length === 1 ? f[0].name : TK.fa(f.length) + ' فایل انتخاب شد';
    out.title = f.map((x) => x.name).join('\n');
    inp.closest('.file-pick')?.classList.toggle('has-files', f.length > 0);
  };
  TK.on('change', '.file-pick input[type=file]', (e, t) => fileNames(t));
  document.addEventListener('reset', (e) => setTimeout(() => TK.qsa('.file-pick input[type=file]', e.target).forEach(fileNames)), true);
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
    if (r && r.redirect) { setTimeout(() => TK.visit(r.redirect), 350); return; }
    const mode = el?.dataset.after || 'reload';
    if (mode === 'reload') setTimeout(() => TK.reload(), 250);
    else if (mode.startsWith('redirect:')) setTimeout(() => TK.visit(TK.url(mode.slice(9))), 450);
  };

  /* ───── انتخابگر با لوگو/آواتار (جایگزین <select> برای کاربر و پروژه) ───── */
  TK.rselect = (host, { name, options, placeholder = 'انتخاب کنید', square = false }) => {   // eslint-disable-line prefer-const
    host.classList.add('rselect');
    host.innerHTML = `<input type="hidden" name="${TK.esc(name)}" value=""><button type="button" class="input rselect-btn"><span class="rselect-val"></span><i class="fa-solid fa-chevron-down xs faint"></i></button><div class="rselect-menu" role="listbox"></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('.rselect-btn'), val = host.querySelector('.rselect-val'), menu = host.querySelector('.rselect-menu');
    const av = (o) => `<span class="avatar sm ${square ? 'sq' : ''}">${o.img ? `<img src="${TK.esc(o.img)}" alt="" loading="lazy">` : o.icon ? `<i class="fa-solid ${TK.esc(o.icon)}"></i>` : TK.esc(TK.initial(o.label))}</span>`;
    // گزینه‌ها: {value,label,img|icon} + اختیاری note (توضیح کوچک زیر نام)، disabled + hint (دلیل)، یا {group:'عنوان'} برای سرتیتر گروه
    const draw = () => {
      menu.innerHTML = options.length ? options.map((o) => (o.group !== undefined ? `<div class="rselect-group">${TK.esc(o.group)}</div>`
        : `<div class="rselect-opt${o.disabled ? ' disabled' : ''}" role="option" data-v="${TK.esc(o.value)}"${o.disabled ? ' aria-disabled="true"' : ''}>${av(o)}<span class="truncate grow">${TK.esc(o.label)}${o.note ? `<small class="rselect-note">${TK.esc(o.note)}</small>` : ''}</span></div>`)).join('')
        : '<div class="faint small" data-css="padding:.6rem">موردی وجود ندارد</div>';
    };
    draw();
    const set = (v) => {
      v = v === null || v === undefined ? '' : String(v);
      inp.value = v;
      const o = options.find((x) => x.group === undefined && String(x.value) === v);
      val.innerHTML = o ? `${av(o)}<span class="truncate">${TK.esc(o.label)}</span>` : `<span class="faint">${TK.esc(placeholder)}</span>`;
      TK.qsa('.rselect-opt', menu).forEach((el) => el.classList.toggle('active', el.dataset.v === v));
      host.classList.remove('invalid');
    };
    btn.addEventListener('click', (e) => { e.stopPropagation(); if (btn.disabled) return; TK.qsa('.rselect.open').forEach((x) => x !== host && x.classList.remove('open')); host.classList.toggle('open');
      // نزدیک پایین صفحه/کشو: منو رو به بالا باز شود
      if (host.classList.contains('open')) { const r = btn.getBoundingClientRect(), box = host.closest('.t-drawer, .modal-box')?.getBoundingClientRect(), bottom = Math.min(innerHeight, box ? box.bottom - 70 : innerHeight); host.classList.toggle('up', bottom - r.bottom < Math.min(250, menu.scrollHeight + 10) && r.top > bottom - r.bottom); } });
    menu.addEventListener('click', (e) => {
      const o = e.target.closest('.rselect-opt'); if (!o) return;
      if (o.classList.contains('disabled')) { const d = options.find((x) => String(x.value) === o.dataset.v); if (d?.hint) TK.info(d.hint, { title: d.label, icon: 'fa-lock' }); return; }
      set(o.dataset.v); host.classList.remove('open'); inp.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) host.classList.remove('open'); });
    set('');
    return { input: inp, host, set, get: () => inp.value, option: (v) => options.find((x) => x.group === undefined && String(x.value) === String(v)) || null,
      setOptions(list) { const cur = inp.value; options = list; draw(); set(options.some((x) => x.group === undefined && String(x.value) === cur && !x.disabled) ? cur : ''); },
      disable(off) { host.classList.toggle('disabled', !!off); btn.disabled = !!off; inp.disabled = !!off; }, invalid() { host.classList.add('invalid'); } };
  };

  /*
   * <select data-logo-select>: همان select (با name/data-f/data-autosubmit) پنهان می‌ماند و یک انتخابگر با لوگو جایش نمایش داده می‌شود؛
   * لوگوی هر گزینه از data-img و گزینه‌ی بدون مقدار («همه») با آیکون. تغییر روی select اصلی اعمال و رویداد change آن صدا زده می‌شود.
   */
  TK.logoSelects = (root = document) => TK.qsa('select[data-logo-select]:not([data-logo-ready])', root).forEach((sel) => {
    sel.dataset.logoReady = '1';
    const host = document.createElement('div');
    sel.after(host); sel.hidden = true;
    const options = [...sel.options].map((o) => ({ value: o.value, label: o.textContent.trim(), img: o.dataset.img || '', icon: o.value === '' ? 'fa-layer-group' : '' }));
    const rs = TK.rselect(host, { name: '', options, placeholder: options[0]?.label || '', square: true });
    rs.input.removeAttribute('name');
    rs.set(sel.value);
    rs.input.addEventListener('change', () => { sel.value = rs.get(); sel.dispatchEvent(new Event('change', { bubbles: true })); });
  });
  document.addEventListener('tk:ready', () => TK.logoSelects());
  document.addEventListener('tk:live', () => TK.logoSelects());

  /* ───── Global delegated behaviours ───── */
  TK.on('click', '[data-modal]', (e, t) => { e.preventDefault(); TK.modal.open(t.dataset.modal); });
  TK.on('click', '[data-close]', (e, t) => { e.preventDefault(); TK.modal.tryClose(t.closest('.modal')); });
  TK.on('mousedown', '.modal', (e, t) => { if (e.target === t) TK.modal.tryClose(t); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { const m = TK.qsa('.modal.open').pop(); if (m) TK.modal.tryClose(m); TK.qsa('.dropdown.open,.qa.open').forEach((d) => d.classList.remove('open')); } });
  TK.on('change', '[data-autosubmit]', (e, t) => (t.form.requestSubmit ? t.form.requestSubmit() : t.form.submit()));
  TK.on('click', '[data-copy]', (e, t) => TK.copy(t.dataset.copy));
  TK.on('click', '[data-copy-target]', (e, t) => TK.copy(TK.qs(t.dataset.copyTarget)?.innerText || ''));
  TK.on('click', '.dropdown > [data-toggle]', (e, t) => { e.stopPropagation(); const d = t.parentElement; const was = d.classList.contains('open'); TK.qsa('.dropdown.open').forEach((x) => x.classList.remove('open')); if (!was) d.classList.add('open'); });
  document.addEventListener('click', (e) => { if (!e.target.closest('.dropdown')) TK.qsa('.dropdown.open').forEach((d) => d.classList.remove('open')); if (!e.target.closest('.qa')) TK.qs('.qa')?.classList.remove('open'); });
  // دسترس‌پذیری: دکمه‌های فقط‌آیکون نام بگیرند (از title)، دکمه‌ی بستن پنجره «بستن»
  const a11y = () => {
    TK.qsa('[data-close]:not([aria-label]):not([title])').forEach((b) => { if (!b.textContent.trim()) { b.setAttribute('aria-label', 'بستن'); b.title = 'بستن'; } });
    TK.qsa('.btn-icon[title]:not([aria-label]), a.btn[title]:not([aria-label])').forEach((b) => { if (!b.textContent.trim()) b.setAttribute('aria-label', b.title); });
    // برچسب فرم ← فیلد: هر .field که یک برچسب بدون for و یک فیلد اصلی دارد، با id/for به هم وصل شوند (کلیک روی برچسب و صفحه‌خوان)
    TK.qsa('.field > label:not([for])').forEach((l) => {
      const f = l.parentElement.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea');
      if (f.length !== 1 || l.contains(f[0])) return;
      if (!f[0].id) f[0].id = 'f-' + Math.random().toString(36).slice(2, 9);
      l.htmlFor = f[0].id;
    });
    // فیلدهای جستجو و فیلدهای بی‌برچسب: نام از placeholder
    TK.qsa('input:not([type=hidden]):not([aria-label]), textarea:not([aria-label])').forEach((i) => {
      if (i.placeholder && !(i.id && document.querySelector(`label[for="${CSS.escape(i.id)}"]`)) && !i.closest('label')) i.setAttribute('aria-label', i.placeholder);
    });
  };
  a11y(); document.addEventListener('tk:ready', a11y); document.addEventListener('tk:live', a11y);

  // پیام‌های اعتبارسنجی مرورگر به فارسی (به‌جای «Please fill out this field» در مرورگر انگلیسی)
  document.addEventListener('invalid', (e) => {
    const el = e.target, v = el.validity; if (!v || !el.setCustomValidity) return;
    el.setCustomValidity('');
    const f = TK.fa;
    const msg = v.valueMissing ? (el.type === 'checkbox' ? 'برای ادامه این گزینه را علامت بزنید.' : el.tagName === 'SELECT' || el.type === 'file' ? 'یک گزینه را انتخاب کنید.' : 'این فیلد را پر کنید.')
      : v.typeMismatch ? (el.type === 'email' ? 'ایمیل معتبر وارد کنید (مثل name@example.com).' : el.type === 'url' ? 'آدرس معتبر وارد کنید (با http:// یا https://).' : 'مقدار واردشده معتبر نیست.')
      : v.tooShort ? `حداقل ${f(el.minLength)} نویسه وارد کنید (الان ${f(el.value.length)}).`
      : v.tooLong ? `حداکثر ${f(el.maxLength)} نویسه مجاز است.`
      : v.rangeUnderflow ? `مقدار باید حداقل ${f(el.min)} باشد.`
      : v.rangeOverflow ? `مقدار باید حداکثر ${f(el.max)} باشد.`
      : v.stepMismatch ? 'مقدار واردشده مجاز نیست.'
      : v.patternMismatch ? (el.title || 'قالب واردشده درست نیست.')
      : v.badInput ? 'مقدار واردشده معتبر نیست.' : '';
    if (msg) el.setCustomValidity(msg);
  }, true);
  document.addEventListener('input', (e) => { if (e.target.setCustomValidity && e.target.validity?.customError) e.target.setCustomValidity(''); }, true);
  document.addEventListener('change', (e) => { if (e.target.setCustomValidity && e.target.validity?.customError) e.target.setCustomValidity(''); }, true);

  // موبایل: دکمه‌ی شناور هنگام اسکرول به پایین کنار می‌رود تا روی کارت‌ها نیفتد؛ با اسکرول به بالا برمی‌گردد
  let qaY = window.scrollY;
  window.addEventListener('scroll', () => {
    const qa = TK.qs('.qa'), y = window.scrollY; if (!qa) return;
    if (Math.abs(y - qaY) < 8) return;
    qa.classList.toggle('qa-hide', window.innerWidth <= 640 && y > qaY && y > 80);
    qaY = y;
  }, { passive: true });
  /* ───── سایدبار v5.8 (partials/sidebar.php): موبایل off-canvas، پاپ‌آپ زیرمنو، هایلایت متحرک، حالت rail + tooltip، پالت Ctrl/⌘+K ─────
     همه با delegation روی document (سایدبار در هر جابه‌جایی نرم صفحه دوباره ساخته می‌شود) */
  const sbMobile = window.matchMedia('(max-width: 960px)');
  const sbReduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const sbStore = { get(k) { try { return localStorage.getItem(k); } catch { return null; } }, set(k, v) { try { localStorage.setItem(k, v); } catch { /* حالت خصوصی */ } } };
  const sbSide = (open) => {
    const sb = TK.qs('#sidebar'); if (!sb) return;
    sb.classList.toggle('open', open); TK.qs('.overlay')?.classList.toggle('show', open);
    TK.qsa('[data-sidebar]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
    if (!open) sbPopClose(false);
  };
  TK.on('click', '[data-sidebar]', () => sbSide(!TK.qs('#sidebar')?.classList.contains('open')));
  TK.on('click', '.overlay', () => sbSide(false));

  // پاپ‌آپ زیرمنو: هر بار یکی؛ موقعیت کنار سایدبار، فلش (--ay) دقیقاً روبه‌روی دکمه
  let sbPop = null, sbPopBtn = null;
  const sbPlace = () => {
    if (!sbPop || !sbPopBtn) return;
    const side = TK.qs('#sidebar').getBoundingClientRect(), r = sbPopBtn.getBoundingClientRect(), h = sbPop.offsetHeight;
    const mid = r.top + r.height / 2;
    const top = Math.max(12, Math.min(mid - 34, window.innerHeight - h - 12));
    sbPop.style.setProperty('--pop-top', top + 'px');
    if (!sbMobile.matches) sbPop.style.setProperty('--pop-right', (window.innerWidth - side.left + 12) + 'px');
    sbPop.style.setProperty('--ay', Math.max(18, Math.min(mid - top, h - 18)) + 'px');
  };
  const sbPopItems = () => TK.qsa('.sb-pop-a:not(.is-soon), .sb-pop-x', sbPop).filter((x) => !x.classList.contains('sb-pop-x'));
  function sbPopClose(focusBack = true) {
    if (!sbPop) return;
    sbPop.hidden = true; sbPopBtn?.setAttribute('aria-expanded', 'false');
    const b = sbPopBtn; sbPop = null; sbPopBtn = null;
    if (focusBack && b) b.focus();
  }
  const sbPopOpen = (btn) => {
    const pop = document.getElementById(btn.getAttribute('aria-controls')); if (!pop) return;
    sbTipHide(); sbPopClose(false);
    sbPop = pop; sbPopBtn = btn; pop.hidden = false; btn.setAttribute('aria-expanded', 'true');
    sbPlace();
    const items = sbPopItems();
    (items.find((a) => a.getAttribute('aria-current') === 'page') || items[0] || TK.qs('.sb-pop-x', pop))?.focus({ preventScroll: true });
  };
  TK.on('click', '.sb-it[aria-controls]', (e, btn) => { e.stopPropagation(); if (sbPopBtn === btn) sbPopClose(); else sbPopOpen(btn); });
  TK.on('click', '.sb-pop-x', () => sbPopClose());
  TK.on('click', '.sb-pop-a', () => { sbPopClose(false); if (sbMobile.matches) sbSide(false); });
  document.addEventListener('pointerdown', (e) => { if (sbPop && !e.target.closest('.sb-pop, .sb-it[aria-controls]')) sbPopClose(false); });
  document.addEventListener('keydown', (e) => {
    if (!sbPop || !sbPop.contains(document.activeElement)) return;
    const items = sbPopItems(), i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); sbPopClose(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); const n = items.length; if (n) items[((i < 0 ? -1 : i) + (e.key === 'ArrowDown' ? 1 : -1) + n) % n].focus(); return; }
    if (e.key === 'Home' || e.key === 'End') { e.preventDefault(); items[e.key === 'Home' ? 0 : items.length - 1]?.focus(); return; }
    if (e.key === 'Tab') {
      const all = TK.qsa('a, button', sbPop).filter((x) => !x.hidden), first = all[0], last = all[all.length - 1];
      if ((!e.shiftKey && document.activeElement === last) || (e.shiftKey && document.activeElement === first)) { e.preventDefault(); sbPopClose(); }
    }
  }, true);
  window.addEventListener('resize', () => { sbPlace(); sbTipHide(); }, { passive: true });
  const sbBind = () => {
    const nav = TK.qs('.sb-nav'); if (!nav) return;
    nav.addEventListener('scroll', () => { sbPopClose(false); sbTipHide(); }, { passive: true });
    // هایلایت متحرک: offsetTop/offsetHeight خود li
    const hl = TK.qs('.sb-hl', nav), list = TK.qs('.sb-list', nav); if (!hl || !list) return;
    const move = (li) => { hl.style.setProperty('transform', 'translateY(' + li.offsetTop + 'px)'); hl.style.setProperty('height', li.offsetHeight + 'px'); hl.classList.add('on'); };
    list.addEventListener('pointerenter', (e) => { const li = e.target.closest?.('.sb-list > li'); if (li) move(li); }, true);
    list.addEventListener('focusin', (e) => { const li = e.target.closest('.sb-list > li'); if (li) move(li); });
    list.addEventListener('pointerleave', () => { if (!list.contains(document.activeElement)) hl.classList.remove('on'); });
    list.addEventListener('focusout', (e) => { if (!list.contains(e.relatedTarget) && !list.matches(':hover')) hl.classList.remove('on'); });
    sbRailSync();
  };

  // حالت rail (فقط دسکتاپ؛ کلاس پیش از رندر با assets/js/rail.js)
  const sbRail = () => document.documentElement.classList.contains('sb-rail') && !sbMobile.matches;
  function sbRailSync() {
    const on = document.documentElement.classList.contains('sb-rail');
    TK.qsa('.sb-rail-btn').forEach((b) => {
      b.setAttribute('aria-pressed', String(on)); b.setAttribute('aria-label', on ? 'باز کردن کامل منو' : 'جمع کردن منو');
      const i = b.querySelector('i'); if (i) { i.classList.toggle('fa-angles-right', !on); i.classList.toggle('fa-angles-left', on); }
    });
  }
  const sbRailToggle = () => {
    const on = !document.documentElement.classList.contains('sb-rail');
    document.documentElement.classList.toggle('sb-rail', on); sbStore.set('tk.sb.rail', on ? '1' : '0');
    sbPopClose(false); sbTipHide(); sbRailSync();
  };
  TK.on('click', '.sb-rail-btn', sbRailToggle);
  // tooltip در حالت rail
  let sbTipEl = null;
  function sbTipHide() { const t = TK.qs('#sb-tooltip'); if (t) t.hidden = true; if (sbTipEl) { sbTipEl.removeAttribute('aria-describedby'); sbTipEl = null; } }
  const sbTipShow = (el) => {
    if (!sbRail() || el === sbPopBtn) return;
    const t = TK.qs('#sb-tooltip'); if (!t) return;
    const txt = (el.querySelector('.sb-lbl')?.textContent || el.getAttribute('aria-label') || '').trim(); if (!txt) return;
    const r = el.getBoundingClientRect();
    t.textContent = txt; t.hidden = false;
    t.style.setProperty('--tt-right', (window.innerWidth - r.left + 10) + 'px');
    t.style.setProperty('--tt-top', (r.top + r.height / 2) + 'px');
    sbTipEl = el; el.setAttribute('aria-describedby', 'sb-tooltip');
  };
  const SB_TIP = '#sidebar .sb-it, #sidebar .sb-search, #sidebar .sb-rail-btn, #sidebar .sb-out, #sidebar .brand';
  document.addEventListener('pointerover', (e) => { const el = e.target.closest?.(SB_TIP); if (el && el !== sbTipEl) sbTipShow(el); else if (!el && sbTipEl) sbTipHide(); });
  document.addEventListener('focusin', (e) => { const el = e.target.closest?.(SB_TIP); if (el && el.matches(':focus-visible')) sbTipShow(el); });
  document.addEventListener('focusout', (e) => { if (e.target === sbTipEl) sbTipHide(); });
  document.addEventListener('pointerdown', sbTipHide);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') sbTipHide(); });


  // پالت جستجو و پرش سریع (Ctrl/⌘+K)
  const sbNorm = (s) => String(s || '').toLowerCase().replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[‌‏‎]/g, '').replace(/\s+/g, ' ').trim();
  let sbPalBack = null, sbPalRes = [], sbPalSel = 0;
  const sbPalItems = () => {
    const out = [];
    TK.qsa('#sidebar .sb-list > li > a.sb-it').forEach((a) => out.push({ label: a.querySelector('.sb-lbl')?.textContent.trim(), icon: a.querySelector('.sb-ic i')?.className, href: a.href, blank: a.target === '_blank' }));
    TK.qsa('#sidebar .sb-pop').forEach((p) => {
      const g = p.querySelector('.sb-pop-hd b')?.textContent.trim();
      TK.qsa('a.sb-pop-a', p).forEach((a) => out.push({ label: a.querySelector('.sb-pop-t b')?.childNodes[0]?.textContent.trim(), group: g, icon: a.querySelector('.sb-pop-ic i')?.className, href: a.href, blank: a.target === '_blank' }));
    });
    if (TK.qs('.qa-fab')) out.push({ label: 'ثبت تسک جدید', group: 'عملیات', icon: 'fa-solid fa-plus', run: () => TK.qs('.qa-fab')?.click() });
    if (!sbMobile.matches) out.push({ label: document.documentElement.classList.contains('sb-rail') ? 'باز کردن کامل منو' : 'جمع کردن منو', group: 'عملیات', icon: 'fa-solid fa-angles-right', run: sbRailToggle });
    return out;
  };
  const sbPalRender = () => {
    const d = TK.qs('.sb-palette'), q = sbNorm(TK.qs('.sb-pal-q', d).value), list = TK.qs('.sb-pal-list', d);
    sbPalRes = sbPalItems().filter((x) => !q || sbNorm(x.label + ' ' + (x.group || '')).includes(q));
    sbPalSel = Math.min(sbPalSel, Math.max(0, sbPalRes.length - 1));
    list.innerHTML = sbPalRes.map((x, i) => `<li role="option" id="sb-pal-o${i}" aria-selected="${i === sbPalSel}" data-i="${i}"><i class="${TK.esc(x.icon || 'fa-solid fa-link')}" aria-hidden="true"></i><span>${TK.esc(x.label)}</span>${x.group ? `<small>${TK.esc(x.group)}</small>` : ''}</li>`).join('');
    TK.qs('.sb-pal-empty', d).hidden = sbPalRes.length > 0;
    TK.qs('.sb-pal-q', d).setAttribute('aria-activedescendant', sbPalRes.length ? 'sb-pal-o' + sbPalSel : '');
    TK.qs('#sb-pal-o' + sbPalSel, d)?.scrollIntoView({ block: 'nearest' });
  };
  const sbPalOpen = () => {
    const d = TK.qs('.sb-palette'); if (!d || d.open) return;
    sbPalBack = document.activeElement; sbPopClose(false); sbTipHide();
    TK.qs('.sb-pal-q', d).value = ''; sbPalSel = 0; sbPalRender();
    d.showModal(); TK.qs('.sb-pal-q', d).focus();
  };
  const sbPalClose = () => { const d = TK.qs('.sb-palette'); if (d?.open) d.close(); };
  const sbPalRun = (i) => {
    const x = sbPalRes[i]; if (!x) return;
    sbPalClose(); sbPalBack = null;
    if (x.run) { x.run(); return; }
    if (x.blank) window.open(x.href, '_blank', 'noopener'); else if (TK.visit) TK.visit(x.href); else location.href = x.href;
  };
  TK.on('click', '.sb-search', () => { if (sbMobile.matches) sbSide(false); sbPalOpen(); });
  TK.on('input', '.sb-pal-q', () => { sbPalSel = 0; sbPalRender(); });
  TK.on('keydown', '.sb-pal-q', (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); const n = sbPalRes.length; if (n) { sbPalSel = (sbPalSel + (e.key === 'ArrowDown' ? 1 : -1) + n) % n; sbPalRender(); } }
    else if (e.key === 'Enter') { e.preventDefault(); sbPalRun(sbPalSel); }
  });
  TK.on('click', '.sb-pal-list li', (e, li) => sbPalRun(Number(li.dataset.i)));
  TK.on('click', '.sb-palette', (e, d) => { if (e.target === d) sbPalClose(); });   // کلیک روی backdrop
  document.addEventListener('close', (e) => { if (e.target.matches?.('.sb-palette')) { const b = sbPalBack; sbPalBack = null; if (b && document.contains(b)) b.focus?.(); } }, true);
  document.addEventListener('keydown', (e) => {
    if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey) return;
    if (e.key !== 'k' && e.key !== 'K' && e.key !== 'ن' && e.code !== 'KeyK') return;
    if (!TK.qs('.sb-palette')) return;
    e.preventDefault();
    if (TK.qs('.sb-palette').open) sbPalClose(); else sbPalOpen();
  });
  const sbKbd = () => { if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) TK.qsa('.sb-kbd').forEach((k) => { k.textContent = '⌘ K'; }); };

  const sbSync = () => { sbPop = null; sbPopBtn = null; sbSide(false); sbBind(); sbKbd(); };
  sbMobile.addEventListener?.('change', () => { sbPopClose(false); sbTipHide(); sbRailSync(); });
  sbSync();
  TK.afterSwap.push(sbSync);

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
    const undo = TK.ok(r) && r.undo ? { label: 'بازگردانی', onClick: async () => { const u = await TK.post(r.undo); TK.toast(u.message, TK.ok(u) ? 'info' : 'error'); if (TK.ok(u)) TK.reload(); } } : null;
    TK.toast(r.message || (TK.ok(r) ? 'انجام شد' : 'خطا'), TK.ok(r) ? 'success' : 'error', undo ? 6500 : 4200, { action: undo });
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

  // فلش‌های سرور → toast (بعد از هر جابه‌جایی نرم صفحه هم)
  TK.showFlash = () => (TK.boot().flash || []).forEach((f) => TK.toast(f.message, f.type));
  TK.showFlash();
  TK.afterSwap.push(TK.showFlash);

  /* ───── Charts (SVG) ───── */
  // رنگ اول از تم فعال (--g-500) خوانده می‌شود تا نمودارها با تم روشن/تیره هماهنگ باشند
  const accent = getComputedStyle(document.documentElement).getPropertyValue('--g-500').trim() || '#4ade80';
  const C = [accent, '#60a5fa', '#fbbf24', '#f87171', '#c084fc', '#2dd4bf'];
  TK.leaveGuard = null;   // ماژول صفحه می‌تواند تابعی بگذارد که «تغییر ذخیره‌نشده هست؟» را برگرداند (با ترک صفحه خودکار برمی‌گردد)
  TK.charts = {
    colors: C,
    /** نمودار خطی؛ مقدار null = بدون داده (خط قطع می‌شود). با هاور روی هر ستون، مقادیر آن نقطه نمایش داده می‌شود. */
    // connect: نقاط هر سری با خط به هم وصل شوند حتی اگر بینشان داده نباشد (مقایسه‌ی سرعت اعضا)
    line(el, { labels, series, height = 220, max: fixedMax = null, suffix = '', connect = false }) {
      if (!el) return;
      // عرض viewBox = عرض واقعی نمودار تا متن‌ها با اندازه‌ی واقعی (نه کوچک‌شده) دیده شوند
      const W = Math.max(300, Math.round(el.clientWidth || 640)), H = height, p = { l: 38, r: 14, t: 14, b: 30 };
      const vals = series.flatMap((s) => s.data).filter((v) => v !== null && v !== undefined);
      const max = fixedMax || Math.max(4, ...vals);
      const step = Math.max(1, Math.ceil(max / 4)), top = fixedMax || step * Math.ceil(max / step);
      const n = labels.length;
      const x = (i) => p.l + (i * (W - p.l - p.r)) / Math.max(1, n - 1);
      const y = (v) => H - p.b - (v / top) * (H - p.t - p.b);
      const col = (s, k) => s.color || C[k % C.length];
      const every = Math.max(1, Math.ceil(n / Math.max(3, Math.floor(W / 70))));
      let g = '';
      for (let v = 0; v <= top; v += step) g += `<line class="axis" x1="${p.l}" x2="${W - p.r}" y1="${y(v)}" y2="${y(v)}" stroke-dasharray="3 4"/><text x="${p.l - 8}" y="${y(v) + 3}" text-anchor="end">${TK.fa(v)}</text>`;
      labels.forEach((l, i) => { if ((i % every === 0 && n - 1 - i >= every) || i === n - 1) g += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle">${TK.esc(l)}</text>`; });
      series.forEach((s, k) => {
        const c = col(s, k);
        // تکه‌های پیوسته (بدون null)
        const segs = []; let cur = [];
        s.data.forEach((v, i) => { if (v === null || v === undefined) { if (cur.length && !connect) { segs.push(cur); cur = []; } } else cur.push(i); });
        if (cur.length) segs.push(cur);
        g += `<g class="sr" data-k="${k}">`;
        g += `<defs><linearGradient id="lg${k}${el.id}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${c}" stop-opacity="${series.length > 2 ? '.12' : '.24'}"/><stop offset="1" stop-color="${c}" stop-opacity="0"/></linearGradient></defs>`;
        segs.forEach((idx) => {
          const pts = idx.map((i) => `${x(i)},${y(s.data[i])}`).join(' ');
          if (idx.length > 1 && !connect) g += `<polygon points="${x(idx[0])},${y(0)} ${pts} ${x(idx[idx.length - 1])},${y(0)}" fill="url(#lg${k}${el.id})"/>`;
          g += `<polyline points="${pts}" fill="none" stroke="${c}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>`;
        });
        const solo = s.data.filter((v) => v !== null && v !== undefined).length === 1;   // سری تک‌نقطه‌ای: نقطه‌ی پررنگ با نام
        s.data.forEach((v, i) => {
          if (v === null || v === undefined) return;
          g += solo ? `<circle class="pt" data-i="${i}" cx="${x(i)}" cy="${y(v)}" r="5.5" fill="${c}" stroke="var(--bg)" stroke-width="2"/>`
            + `<text class="pt-name" x="${x(i)}" y="${Math.max(12, y(v) - 10)}" text-anchor="middle" data-css="fill:${c}">${TK.esc(s.name)}</text>`
            : `<circle class="pt" data-i="${i}" cx="${x(i)}" cy="${y(v)}" r="3.2" data-css="fill:var(--bg)" stroke="${c}" stroke-width="2"/>`;
        });
        g += '</g>';
      });
      // ناحیه‌های هاور (یک ستون برای هر برچسب)
      const bw = (W - p.l - p.r) / Math.max(1, n - 1);
      g += `<line class="hover-line" x1="0" x2="0" y1="${p.t}" y2="${H - p.b}" data-css="display:none"/>`;
      labels.forEach((l, i) => { const x0 = Math.max(0, x(i) - bw / 2), x1 = Math.min(W, x(i) + bw / 2); g += `<rect class="hz" data-i="${i}" x="${x0}" y="0" width="${x1 - x0}" height="${H - p.b}" fill="transparent"/>`; });
      el.innerHTML = `<div class="chart-box"><svg viewBox="0 0 ${W} ${H}" role="img">${g}</svg><div class="chart-tip" hidden></div></div><div class="legend">${series.map((s, k) => `<span data-k="${k}"><i data-css="background:${col(s, k)}"></i>${TK.esc(s.name)}</span>`).join('')}</div>`;
      // هاور روی راهنما: بقیه‌ی سری‌ها کم‌رنگ شوند
      if (series.length > 1) TK.qsa('.legend [data-k]', el).forEach((lg) => {
        lg.addEventListener('mouseenter', () => TK.qsa('.sr', el).forEach((sr) => sr.classList.toggle('dim', sr.dataset.k !== lg.dataset.k)));
        lg.addEventListener('mouseleave', () => TK.qsa('.sr.dim', el).forEach((sr) => sr.classList.remove('dim')));
      });
      const svg = el.querySelector('svg'), tip = el.querySelector('.chart-tip'), hl = el.querySelector('.hover-line'), box = el.querySelector('.chart-box');
      const show = (i) => {
        TK.qsa('.pt', svg).forEach((c) => c.classList.toggle('on', +c.dataset.i === i));
        hl.setAttribute('x1', x(i)); hl.setAttribute('x2', x(i)); hl.style.display = '';
        tip.innerHTML = `<b>${TK.esc(labels[i])}</b>` + series.map((s, k) => `<div><i data-css="background:${col(s, k)}"></i>${TK.esc(s.name)}: <b>${s.data[i] === null || s.data[i] === undefined ? '—' : TK.fa(s.data[i]) + suffix}</b></div>`).join('');
        tip.hidden = false;
        const r = svg.getBoundingClientRect(), bx = box.getBoundingClientRect();
        const px = r.left - bx.left + (x(i) / W) * r.width;
        tip.style.left = Math.min(Math.max(px - tip.offsetWidth / 2, 0), bx.width - tip.offsetWidth) + 'px';
        tip.style.top = Math.max(0, (r.top - bx.top) + (p.t / H) * r.height - tip.offsetHeight - 6) + 'px';
      };
      const hide = () => { tip.hidden = true; hl.style.display = 'none'; TK.qsa('.pt.on', svg).forEach((c) => c.classList.remove('on')); };
      svg.addEventListener('mousemove', (e) => { const z = e.target.closest('.hz, .pt'); if (z) show(+z.dataset.i); });
      svg.addEventListener('mouseleave', hide);
      svg.addEventListener('touchstart', (e) => { const z = e.target.closest('.hz, .pt'); if (z) show(+z.dataset.i); }, { passive: true });
    },
    bars(el, { items, height = 220 }) {
      if (!el) return;
      const W = Math.max(260, Math.round(el.clientWidth || 640)), H = height, p = { l: 10, r: 10, t: 22, b: 34 };
      const max = Math.max(1, ...items.map((i) => i.value));
      const bw = (W - p.l - p.r) / items.length;
      let g = '';
      items.forEach((it, i) => {
        const h = (it.value / max) * (H - p.t - p.b), x = p.l + i * bw + bw * 0.18, w = bw * 0.64, y = H - p.b - h, col = it.color || C[i % C.length];
        g += `<rect x="${x}" y="${y}" width="${w}" height="${Math.max(h, 2)}" rx="6" fill="${col}" opacity=".9"><title>${TK.esc(it.label)}: ${TK.fa(it.value)}</title></rect>`;
        g += `<text class="bar-v" x="${x + w / 2}" y="${y - 7}" text-anchor="middle">${TK.fa(it.value)}</text>`;
        g += `<text x="${x + w / 2}" y="${H - 10}" text-anchor="middle">${TK.esc(String(it.label).slice(0, 14))}</text>`;
      });
      el.innerHTML = `<svg viewBox="0 0 ${W} ${H}">${g}<line class="axis" x1="${p.l}" x2="${W - p.r}" y1="${H - p.b}" y2="${H - p.b}"/></svg>`;
    },
    donut(el, { items, center = '', showPct = false }) {
      if (!el) return;
      // رنگ فقط #hex یا var(--…) پذیرفته می‌شود (دفاع لایه‌ای در برابر تزریق در صفت style/stroke)
      const col = (c, i) => (/^#[0-9a-fA-F]{3,8}$|^var\(--[a-z0-9-]+\)$/.test(String(c || '')) ? c : C[i % C.length]);
      const total = items.reduce((s, i) => s + i.value, 0) || 1, R = 54, S = 18, CIRC = 2 * Math.PI * R;
      let off = 0, g = `<circle cx="70" cy="70" r="${R}" fill="none" data-css="stroke:var(--surface-3)" stroke-width="${S}"/>`;
      items.forEach((it, i) => {
        const len = (it.value / total) * CIRC;
        if (it.value > 0) g += `<circle class="seg" data-i="${i}" cx="70" cy="70" r="${R}" fill="none" stroke="${col(it.color, i)}" stroke-width="${S}" stroke-dasharray="${len} ${CIRC - len}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"/>`;
        off += len;
      });
      const pct = (v) => Math.round((v / total) * 100);
      el.innerHTML = `<div class="donut"><svg viewBox="0 0 140 140">${g}</svg><div class="center-txt">${center}</div></div><div class="legend" data-css="justify-content:center">${items.map((it, i) => `<span><i data-css="background:${col(it.color, i)}"></i>${TK.esc(it.label)} (${showPct ? TK.fa(pct(it.value)) + '٪' : TK.fa(it.value)})</span>`).join('')}</div>`;
      const ctr = el.querySelector('.center-txt'), base = ctr.innerHTML;
      TK.qsa('.seg', el).forEach((c) => {
        c.addEventListener('mouseenter', () => { const it = items[+c.dataset.i]; c.classList.add('on'); ctr.innerHTML = `<div><div class="donut-v">${TK.fa(it.value)}<small> (${TK.fa(pct(it.value))}٪)</small></div><div class="donut-l">${TK.esc(it.label)}</div></div>`; });
        c.addEventListener('mouseleave', () => { c.classList.remove('on'); ctr.innerHTML = base; });
      });
    },
  };

  /* ═════════ سیستم زنده‌ی یکپارچه (AJAX) + اعلان مرورگر ═════════
     • بخش‌هایی با data-live بدون رفرش به‌روز می‌شوند (فقط وقتی داده‌ی صفحه تغییر کرده باشد)
     • نشان‌های سایدبار (پیام، پشتیبانی، ...) هر چند ثانیه تازه می‌شوند
     • پیام جدید و یادآوری تسک به‌صورت Notification مرورگر نمایش داده می‌شود (روی هر صفحه‌ی پنل) */
  TK.state = TK.state || {};
  const boot = TK.boot();
  const mem = {};
  const ls = {
    get(k) { try { return localStorage.getItem(k); } catch { return mem[k] ?? null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { mem[k] = v; } },
  };

  /* ═════════ PWA: service worker، نصب و Web Push ═════════ */
  const swUrl = TK.qs('meta[name="sw-url"]')?.content;
  TK.pwa = {
    reg: null, installEvt: null,
    standalone: () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,
    ios: () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1),
    pushSupported: () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    async ready() { if (!('serviceWorker' in navigator) || !swUrl) return null; try { return TK.pwa.reg || (TK.pwa.reg = await navigator.serviceWorker.ready); } catch { return null; } },
    async subscription() { const r = await TK.pwa.ready(); return r?.pushManager ? r.pushManager.getSubscription() : null; },
    /** اجازه‌ی اعلان + اشتراک push + ثبت در سرور */
    async enablePush() {
      if (!TK.pwa.pushSupported()) return { ok: false, msg: TK.pwa.ios() && !TK.pwa.standalone() ? 'در آیفون ابتدا تسکام را به صفحه‌ی اصلی اضافه کنید و از همان‌جا باز کنید.' : 'این مرورگر از اعلان‌های PWA پشتیبانی نمی‌کند.' };
      const perm = Notification.permission === 'default' ? await Notification.requestPermission() : Notification.permission;
      if (perm !== 'granted') return { ok: false, msg: perm === 'denied' ? 'اعلان‌ها مسدود است؛ از تنظیمات سایت در مرورگر (آیکن قفل کنار آدرس) آن را «مجاز» کنید.' : 'اجازه‌ی اعلان داده نشد.' };
      const reg = await TK.pwa.ready(); if (!reg) return { ok: false, msg: 'service worker فعال نشد (سایت باید HTTPS باشد).' };
      const k = await TK.get('/push/key'); if (!TK.ok(k)) return { ok: false, msg: k.message || 'کلید اعلان دریافت نشد.' };
      const key = Uint8Array.from(atob(k.key.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((k.key.length + 3) % 4)), (c) => c.charCodeAt(0));
      let sub = await reg.pushManager.getSubscription();
      const cur = sub?.options?.applicationServerKey ? new Uint8Array(sub.options.applicationServerKey) : null;
      if (sub && cur && cur.join() !== key.join()) { await sub.unsubscribe(); sub = null; }   // کلید سرور عوض شده
      try { sub = sub || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }); } catch (e) { return { ok: false, msg: 'ثبت اعلان در مرورگر ناموفق بود: ' + (e.message || e) }; }
      const j = sub.toJSON();
      const r = await TK.post('/push/subscribe', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
      if (TK.ok(r)) ls.set('tk_push_sync', j.endpoint + '|' + Date.now());
      return { ok: TK.ok(r), msg: r.message, devices: r.devices };
    },
    async disablePush() {
      const sub = await TK.pwa.subscription(); if (!sub) return { ok: true, msg: 'اعلان‌های این دستگاه از قبل غیرفعال بود.' };
      const r = await TK.post('/push/unsubscribe', { endpoint: sub.endpoint }); await sub.unsubscribe(); ls.set('tk_push_sync', '');
      return { ok: true, msg: r.message || 'غیرفعال شد.' };
    },
    /** همگام‌سازی روزانه‌ی اشتراک با سرور (اگر مرورگر اشتراک را عوض کرده باشد) */
    async sync() {
      if (!TK.boot().live || !TK.pwa.pushSupported() || Notification.permission !== 'granted') return;
      const sub = await TK.pwa.subscription(); if (!sub) return;
      const [ep, at] = String(ls.get('tk_push_sync') || '').split('|');
      if (ep === sub.endpoint && Date.now() - Number(at || 0) < 864e5) return;
      const j = sub.toJSON();
      const r = await TK.post('/push/subscribe', { endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
      if (TK.ok(r)) ls.set('tk_push_sync', j.endpoint + '|' + Date.now());
    },
    async install() {
      const e = TK.pwa.installEvt; if (!e) return 'unavailable';
      e.prompt(); const { outcome } = await e.userChoice; TK.pwa.installEvt = null;
      document.dispatchEvent(new CustomEvent('tk:installable', { detail: false }));
      return outcome;
    },
  };
  // خروج: اشتراک اعلان همین مرورگر هم از حساب جدا می‌شود (روی دستگاه مشترک، اعلان‌های حساب قبلی به نفر بعدی نرسد)
  document.addEventListener('submit', async (e) => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || !/\/logout$/.test(f.getAttribute('action') || '') || f.dataset.pushDone) return;
    e.preventDefault();
    f.dataset.pushDone = '1';
    try {
      const sub = TK.pwa.pushSupported() ? await Promise.race([TK.pwa.subscription(), new Promise((r) => setTimeout(() => r(null), 1500))]) : null;
      if (sub) { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'push_endpoint'; i.value = sub.endpoint; f.appendChild(i); }
      ls.set('tk_push_sync', '');
    } catch { /* بدون اشتراک */ }
    f.submit();
  });
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); TK.pwa.installEvt = e; document.dispatchEvent(new CustomEvent('tk:installable', { detail: true })); });
  window.addEventListener('appinstalled', () => { TK.pwa.installEvt = null; TK.toast('تسکام نصب شد؛ از صفحه‌ی اصلی دستگاه بازش کنید. 🎉', 'success', 6000); document.dispatchEvent(new CustomEvent('tk:installed')); });
  if ('serviceWorker' in navigator && swUrl && (location.protocol === 'https:' || /^(localhost|127\.0\.0\.1)$/.test(location.hostname))) {
    navigator.serviceWorker.register(swUrl, { scope: TK.base + '/' }).then((r) => { TK.pwa.reg = r; TK.pwa.sync(); }).catch(() => { /* بدون PWA ادامه */ });
    // پیام service worker: اعلانی که وقتی برنامه جلوی چشم است رسیده → toast داخل صفحه
    navigator.serviceWorker.addEventListener('message', (e) => {
      const m = e.data || {};
      if (m.type === 'tk-open' && m.url) location.href = m.url;
      if (m.type === 'tk-push' && m.payload) {
        const d = m.payload, url = d.url ? new URL(d.url, location.origin + TK.base + '/').href : '';
        if (d.kind === 'chat' && TK.state.chatUser && url.includes('user=' + TK.state.chatUser)) return;   // همین گفتگو باز است
        const el = TK.toast(d.title + (d.body ? ' — ' + String(d.body).slice(0, 90) : ''), 'info', 9000);
        if (el && url) { el.style.cursor = 'pointer'; el.addEventListener('click', () => (location.href = url)); }
      }
    });
  }

  /** اعلان از داخل صفحه: از طریق service worker (الزامی در کروم اندروید)، وگرنه Notification ساده، وگرنه toast */
  TK.notify = async ({ title, body = '', url = '', tag = '' }) => {
    const go = () => { window.focus(); if (url) location.href = url; };
    if ('Notification' in window && Notification.permission === 'granted') {
      const reg = await TK.pwa.ready();
      const opts = { body, tag: tag || undefined, renotify: !!tag, icon: TK.url('assets/img/icon-192.png'), badge: TK.url('assets/img/badge-96.png'), dir: 'rtl', lang: 'fa', vibrate: [120, 60, 120], data: { url: url ? new URL(url, location.href).href : location.href } };
      try {
        if (reg) { await reg.showNotification(title, opts); return; }
        const n = new Notification(title, opts);
        n.onclick = () => { n.close(); go(); };
        return;
      } catch { /* ادامه با toast */ }
    }
    const el = TK.toast(title + (body ? ' — ' + body.slice(0, 90) : ''), 'info', 9000);
    if (el && url) { el.style.cursor = 'pointer'; el.addEventListener('click', go); }
  };

  TK.live = {
    sig: null, busy: false, skip: false,
    has: () => !!TK.qs('[data-live]'),
    async refresh() {
      if (TK.live.busy) { TK.live.again = true; return; }    // درخواست هم‌زمان گم نشود (مثلاً تغییر وضعیت وسط poll): بعد از این یکی دوباره
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
      } catch { /* شبکه */ } finally {
        TK.live.busy = false;
        if (TK.live.again) { TK.live.again = false; TK.live.refresh(); }
      }
    },
    async poll() {
      const live = TK.boot().live;                       // بعد از جابه‌جایی نرم، scope صفحه‌ی جدید
      if (!live) return;
      const KEY = 'tk_lastmsg_' + (live.uid || '');
      const last = ls.get(KEY);
      const params = { scope: live.scope || '' };
      if (last === null) params.baseline = 1; else params.last_msg = last;
      const r = await TK.get('/live/poll', params);
      if (!r || r.status !== 'success') return;
      // متن صفحه‌خوان کنار شمارنده‌ی سایدبار («، n مورد»)
      const sbSr = (b, v) => { const sr = b.nextElementSibling; if (sr?.classList.contains('sb-sr')) sr.textContent = v ? '، ' + TK.fa(v) + ' مورد' : ''; };
      Object.entries(r.counts || {}).forEach(([k, v]) => TK.qsa(`[data-badge="${k}"]`).forEach((b) => { b.textContent = TK.fa(v); b.classList.toggle('zero', !v); sbSr(b, v); }));
      TK.qsa('[data-badge-sum]').forEach((b) => {
        const keys = b.dataset.badgeSum.split(',');
        if (!keys.some((k) => k in (r.counts || {}))) return;
        const v = keys.reduce((s, k) => s + Number(r.counts[k] || 0), 0);
        b.textContent = TK.fa(v); b.classList.toggle('zero', !v); sbSr(b, v);
      });
      if (navigator.setAppBadge && r.counts && 'chat' in r.counts) { try { r.counts.chat ? navigator.setAppBadge(r.counts.chat) : navigator.clearAppBadge(); } catch { /* */ } }
      if (r.msg_base !== null && r.msg_base !== undefined) ls.set(KEY, String(r.msg_base));
      (r.messages || []).forEach((m) => {
        ls.set(KEY, String(m.id));                                          // نشانگر = شناسه‌ی عمومی آخرین پیام
        if (ls.get('tk_msg_n_' + m.id)) return;                             // تب دیگری قبلاً اعلان داده
        ls.set('tk_msg_n_' + m.id, '1');
        if (TK.state.chatUser === m.sender && document.hasFocus() && !document.hidden) return;   // همین گفتگو باز است
        TK.notify({ title: m.title, body: m.body, url: m.url, tag: 'msg-' + m.sender });
      });
      // یادآوری‌ها: نمایش و بعد ثبت «نمایش داده شد» با POST (poll خودش فقط می‌خواند)؛ تب‌های هم‌زمان با localStorage تکرار نمی‌کنند
      const dueNew = (r.due || []).filter((t) => { const k = 'tk_due_' + t.id; if (ls.get(k)) return false; ls.set(k, '1'); return true; });
      dueNew.forEach((t) => TK.notify({ title: t.title, body: t.body, url: t.url, tag: 'task-' + t.id }));
      if (dueNew.length) TK.post('/notifications/ack', { ids: dueNew.map((t) => t.id) });
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
  let bell = TK.qs('#notif-btn');
  const paintBell = () => {
    if (!bell) return;
    const supported = 'Notification' in window, p = supported ? Notification.permission : 'denied';
    bell.dataset.state = p;
    bell.querySelector('i').className = p === 'granted' ? 'fa-solid fa-bell' : p === 'denied' ? 'fa-solid fa-bell-slash' : 'fa-regular fa-bell';
    bell.title = p === 'granted' ? 'اعلان‌های مرورگر فعال است (کلیک: اعلان آزمایشی)' : p === 'denied' ? 'اعلان‌ها مسدود است' : 'فعال‌سازی اعلان مرورگر';
  };
  paintBell();
  TK.afterSwap.push(() => { bell = TK.qs('#notif-btn'); paintBell(); });
  TK.on('click', '#notif-btn', async () => {
    if (!('Notification' in window)) return TK.toast('مرورگر شما از اعلان پشتیبانی نمی‌کند.', 'warning');
    if (Notification.permission === 'denied') return TK.toast('اعلان‌ها در مرورگر مسدود است؛ از آیکن قفل کنار آدرس، اعلان‌ها را «مجاز» کنید.', 'warning', 7000);
    if (TK.pwa.pushSupported()) {
      const r = await TK.pwa.enablePush(); paintBell();
      if (!r.ok) return TK.toast(r.msg, 'warning', 7000);
      const t = await TK.post('/push/test'); return TK.toast(TK.ok(t) ? 'اعلان‌ها فعال است؛ یک اعلان آزمایشی ارسال شد.' : r.msg, 'success');
    }
    if (Notification.permission === 'default') {
      const p = await Notification.requestPermission(); paintBell();
      return TK.toast(p === 'granted' ? 'اعلان‌های مرورگر فعال شد.' : 'اجازه‌ی اعلان داده نشد.', p === 'granted' ? 'success' : 'warning');
    }
    TK.notify({ title: 'پیام جدید – تسکام', body: 'اعلان‌های مرورگر فعال است ✅', url: '' });
  });
  if (boot.live && 'Notification' in window && Notification.permission === 'default' && !sessionStorage.getItem('tk_notif_hint')) {
    sessionStorage.setItem('tk_notif_hint', '1');
    setTimeout(() => TK.toast('برای دریافت اعلان پیام جدید و یادآوری تسک، روی زنگ بالای صفحه کلیک کنید.', 'info', 7000), 1500);
  }

  /* ───── دکمه‌ی × پاک‌کردن جستجو ─────
     روی همه‌ی فیلدهای جستجو (.search / .dx-search). جستجوی سمت سرور (فرم GET با پارامتر در آدرس): حذف پارامتر و بازگشت به همه‌ی داده‌ها
     با حفظ بقیه‌ی فیلترها. فیلتر زنده (سمت مرورگر): خالی کردن فیلد و ارسال رویداد input تا فهرست کامل برگردد. */
  const SEARCH_SEL = '.search > input.input, .dx-search > input.input';
  const inUrl = (inp) => {
    const f = inp.form;
    return !!(f && inp.name && (f.getAttribute('method') || 'get').toLowerCase() === 'get' && new URLSearchParams(location.search).get(inp.name));
  };
  const clearBtn = (inp) => inp.parentElement.querySelector(':scope > .search-clear');
  const syncClear = (inp) => { const b = clearBtn(inp); if (b) b.hidden = !(inp.value !== '' || inUrl(inp)); };
  TK.searchClear = (root = document) => TK.qsa(SEARCH_SEL, root).forEach((inp) => {
    if (!clearBtn(inp)) {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'search-clear'; b.title = 'پاک کردن جستجو'; b.setAttribute('aria-label', 'پاک کردن جستجو');
      b.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      inp.after(b); inp.parentElement.classList.add('has-clear');
    }
    syncClear(inp);
  });
  document.addEventListener('input', (e) => { if (e.target.matches?.(SEARCH_SEL)) syncClear(e.target); });
  TK.on('click', '.search-clear', (e, b) => {
    const inp = b.parentElement.querySelector('input.input');
    if (!inp) return;
    if (inUrl(inp)) {
      const u = new URL(location.href);
      u.searchParams.delete(inp.name); u.searchParams.delete('page');
      TK.visit(u.toString());
      return;
    }
    inp.value = '';
    inp.dispatchEvent(new Event('input', { bubbles: true }));
    syncClear(inp); inp.focus();
  });
  document.addEventListener('tk:ready', () => TK.searchClear());
  document.addEventListener('tk:live', () => TK.searchClear());

  /* ───── نوار پیشرفت باریک سبز بالای صفحه ───── */
  TK.progress = (() => {
    let bar = null, timer = null, val = 0;
    const el = () => {
      if (!bar || !bar.isConnected) { bar = document.createElement('div'); bar.id = 'tk-progress'; bar.innerHTML = '<i></i>'; document.body.appendChild(bar); }
      return bar;
    };
    const set = (v) => { val = v; el().firstChild.style.transform = `scaleX(${v})`; };
    return {
      start() {
        clearInterval(timer); const b = el(); b.classList.remove('done'); b.classList.add('on'); set(0.08);
        // پیشروی آهسته تا ~۹۰٪ تا پاسخ برسد
        timer = setInterval(() => set(val + (0.9 - val) * 0.12), 180);
      },
      done() { clearInterval(timer); if (!bar) return; set(1); bar.classList.add('done'); setTimeout(() => bar && bar.classList.remove('on', 'done'), 320); },
    };
  })();

  /*
   * ───── ناوبری نرم (بدون بارگذاری کامل صفحه) ─────
   * کلیک روی لینک‌های داخلی پنل و فرم‌های GET: صفحه‌ی بعد با fetch گرفته و فقط <body> عوض می‌شود (CSS، app.js و حالت مرورگر می‌مانند).
   * هنگام hover روی لینک، صفحه از قبل گرفته می‌شود تا کلیک تقریباً بی‌انتظار باشد.
   * اسکریپت‌های بعد از app.js (widgets و ماژول‌های صفحه) دوباره اجرا می‌شوند؛ شنونده‌های document/window و setIntervalهایی که
   * آن‌ها ساخته‌اند هنگام ترک صفحه حذف می‌شوند تا تکراری نشوند. هر جا شک باشد (layout یا نسخه‌ی app.js متفاوت، redirect، خطا) بارگذاری معمولی.
   */
  const nativeAdd = EventTarget.prototype.addEventListener;
  const nativeInterval = window.setInterval.bind(window);
  const pageScope = { on: false, listeners: [], intervals: [] };
  EventTarget.prototype.addEventListener = function (type, fn, opts) {
    if (pageScope.on && (this === document || this === window)) pageScope.listeners.push([this, type, fn, opts]);
    return nativeAdd.call(this, type, fn, opts);
  };
  window.setInterval = function (...args) {
    const id = nativeInterval(...args);
    if (pageScope.on) pageScope.intervals.push(id);
    return id;
  };
  const teardown = () => {
    pageScope.listeners.forEach(([t, type, fn, o]) => t.removeEventListener(type, fn, o));
    pageScope.intervals.forEach((id) => clearInterval(id));
    pageScope.listeners = []; pageScope.intervals = [];
    TK.state.chatUser = null;
    // تابع‌هایی که ماژول صفحه روی TK عوض کرده (مثلاً TK.reload در تقویم) به نسخه‌ی هسته برمی‌گردند و افزوده‌های صفحه حذف می‌شوند
    if (coreTK) {
      Object.keys(TK).forEach((k) => { if (!(k in coreTK)) delete TK[k]; });
      Object.entries(coreTK).forEach(([k, v]) => { if (k !== 'csrf') TK[k] = v; });
    }
  };
  let coreTK = null;

  const SKIP = /^\/(?:files|api|logout|tour|docs|install|push|service-worker|manifest|offline|assets)(?:\/|$)|\.(?:pdf|zip|rar|7z|png|jpe?g|gif|webp|svg|csv|xlsx?|docx?|txt)$/i;
  const basePath = new URL(TK.base || '/', location.origin).pathname.replace(/\/$/, '');
  const navUrl = (href) => {
    let u;
    try { u = new URL(href, location.href); } catch { return null; }
    if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return null;
    const path = basePath && u.pathname.startsWith(basePath) ? u.pathname.slice(basePath.length) || '/' : u.pathname;
    if (basePath && !u.pathname.startsWith(basePath)) return null;
    if (SKIP.test(path)) return null;
    if (u.hash && u.pathname === location.pathname && u.search === location.search) return null;   // پرش داخل همین صفحه
    return u;
  };
  // لینک تسک (data-open-task) در صفحه‌ای که پنجره‌ی جزئیات تسک دارد (TK.tasks)، همان‌جا باز می‌شود نه با ناوبری
  const linkOk = (a, e) => a && a.href && !a.hasAttribute('download') && (!a.target || a.target === '_self') && !a.closest('[data-no-nav]') && !(a.hasAttribute('data-open-task') && TK.tasks)
    && !(e && (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) && navUrl(a.getAttribute('href') ? a.href : '');

  const cache = new Map();                                 // پیش‌گرفته‌ها: url → { t, p }
  const get = (url) => {
    const hit = cache.get(url);
    if (hit && Date.now() - hit.t < 15000) { cache.delete(url); return hit.p; }
    return fetch(url, { credentials: 'same-origin', redirect: 'manual', headers: { Accept: 'text/html', 'X-Soft-Nav': '1' } })
      .then(async (res) => ({ res, html: res.type === 'opaqueredirect' ? '' : await res.text() }));
  };
  const prefetch = (url) => {
    if (cache.has(url) || cache.size > 20) return;
    const p = get(url);
    cache.set(url, { t: Date.now(), p });
    p.catch(() => cache.delete(url));
  };

  const layoutOf = (doc) => doc.body?.dataset.layout || '';
  const appSrc = (doc) => [...doc.querySelectorAll('script[src]')].find((s) => /\/js\/app\.js/.test(s.getAttribute('src')))?.getAttribute('src') || '';
  const APP_SRC = appSrc(document);                       // نسخه‌ی app.js همین صفحه (بعد از جابه‌جایی‌ها دیگر در DOM نیست)
  let navSeq = 0;

  TK.visit = async (href, { push = true, scroll = 0 } = {}) => {
    const u = navUrl(href);
    if (!u || !document.body.dataset.layout) { location.href = href; return; }
    const url = u.toString(), seq = ++navSeq;
    TK.progress.start();
    let out;
    try { out = await get(url); } catch { location.href = url; return; }
    if (seq !== navSeq) return;                            // کلیک جدیدتری آمده
    const { res, html } = out;
    const type = res.headers.get('content-type') || '';
    if (res.type === 'opaqueredirect' || !type.includes('text/html') || res.status >= 500 || !html) { location.href = url; return; }
    const doc = new DOMParser().parseFromString(html, 'text/html');
    if (layoutOf(doc) !== layoutOf(document) || appSrc(doc) !== APP_SRC) { location.href = url; return; }

    if (push) history.replaceState({ ...(history.state || {}), tkScroll: window.scrollY }, '');
    TK.qsa('.modal.open').forEach((m) => m.classList.remove('open'));
    document.body.style.overflow = '';
    teardown();

    // سر صفحه: عنوان، تم، توکن CSRF
    document.title = doc.title;
    ['data-theme', 'lang', 'dir'].forEach((k) => { const v = doc.documentElement.getAttribute(k); if (v !== null) document.documentElement.setAttribute(k, v); });
    const csrf = doc.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) { TK.csrf = csrf; const m = document.querySelector('meta[name="csrf-token"]'); if (m) m.content = csrf; }

    // بدنه: اسکریپت‌های فایل جدا می‌شوند تا به ترتیب دوباره اجرا شوند؛ toastها و نوار پیشرفت می‌مانند
    const srcs = [...doc.body.querySelectorAll('script[src]')].map((sc) => { const v = sc.getAttribute('src'); sc.remove(); return v; })
      .filter((v) => !/\/js\/app\.js/.test(v));
    const keep = TK.qsa('body > .toasts, body > #tk-progress');
    document.body.className = doc.body.className;
    [...doc.body.attributes].forEach((a) => document.body.setAttribute(a.name, a.value));
    document.body.replaceChildren(...[...doc.body.childNodes].map((n) => document.adoptNode(n)), ...keep);

    if (push) history.pushState({ tk: 1 }, '', res.url && !res.redirected ? url : url);
    window.scrollTo(0, scroll);

    pageScope.on = true;
    for (const src of srcs) {
      await new Promise((ok) => { const sc = document.createElement('script'); sc.src = src; sc.async = false; sc.onload = sc.onerror = ok; document.body.appendChild(sc); });
    }
    TK.afterSwap.forEach((fn) => { try { fn(); } catch (err) { console.error(err); } });
    document.dispatchEvent(new CustomEvent('tk:ready'));
    TK.progress.done();
  };

  if (document.body?.dataset.layout) {
    history.scrollRestoration = 'manual';
    if (!history.state?.tk) history.replaceState({ ...(history.state || {}), tk: 1 }, '');
    TK.on('click', 'a[href]', async (e, a) => {
      if (!linkOk(a, e)) return; e.preventDefault();
      // صفحه‌ای با تغییر ذخیره‌نشده (TK.leaveGuard را ماژول صفحه تعیین می‌کند) اول تأیید می‌گیرد
      if (TK.leaveGuard && TK.leaveGuard() && !(await TK.confirm('تغییرات ذخیره نشده‌اند؛ از این صفحه خارج می‌شوید؟', { okText: 'خروج بدون ذخیره' }))) return;
      TK.qs('.sidebar')?.classList.remove('open'); TK.qs('.overlay')?.classList.remove('show'); TK.visit(a.href);
    });
    // فرم‌های GET (جستجو و فیلترها) هم بدون بارگذاری
    TK.on('submit', 'form', (e, f) => {
      if (e.defaultPrevented || f.hasAttribute('data-ajax') || (f.getAttribute('method') || 'get').toLowerCase() !== 'get' || f.closest('[data-no-nav]')) return;
      const u = navUrl(f.getAttribute('action') || location.pathname); if (!u) return;
      e.preventDefault();
      const q = new URLSearchParams(); new FormData(f, e.submitter).forEach((v, k) => typeof v === 'string' && q.append(k, v));
      u.search = q.toString();
      TK.visit(u.toString());
    });
    // پیش‌گرفتن صفحه با hover/لمس (کمترین انتظار هنگام کلیک)
    let hoverT = null;
    document.addEventListener('mouseover', (e) => {
      const a = e.target.closest?.('a[href]'); if (!linkOk(a)) return;
      clearTimeout(hoverT); hoverT = setTimeout(() => prefetch(navUrl(a.href).toString()), 65);
    });
    document.addEventListener('mouseout', (e) => { if (e.target.closest?.('a[href]')) clearTimeout(hoverT); });
    document.addEventListener('touchstart', (e) => { const a = e.target.closest?.('a[href]'); if (linkOk(a)) prefetch(navUrl(a.href).toString()); }, { passive: true });
    window.addEventListener('popstate', (e) => TK.visit(location.href, { push: false, scroll: e.state?.tkScroll || 0 }));
    // بارگذاری معمولی (لینک‌های خارج از ناوبری نرم، فرم‌های POST) هم نوار پیشرفت دارد
    window.addEventListener('beforeunload', () => TK.progress.start());
    window.addEventListener('pageshow', (e) => { if (e.persisted) TK.progress.done(); });
  } else {
    TK.visit = (href) => { location.href = href; };
  }

  /* ───── Page module loader ─────
     ماژول‌های صفحه بعد از app.js لود می‌شوند؛ پس رویداد باید بعد از اجرای همه‌ی اسکریپت‌ها (DOMContentLoaded) ارسال شود.
     از این‌جا به بعد شنونده‌های document/window و setIntervalها مال «صفحه» هستند (widgets و ماژول‌ها) و با ترک صفحه حذف می‌شوند. */
  const fireReady = () => document.dispatchEvent(new CustomEvent('tk:ready'));
  if (document.body?.dataset.layout) { coreTK = { ...TK }; pageScope.on = true; }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fireReady);
  else setTimeout(fireReady, 0);
})();
