/* Karlix — ویجت‌های مشترک: تقویم شمسی، انتخاب ساعت ۲۴ ساعته، فیلد موبایل. فضای نام TK (بعد از app.js). */
(() => {
  'use strict';
  const TK = window.TK;
  const E = TK.esc, FA = TK.fa;

  /* ═════════════ ریاضی تقویم جلالی (گرگوری ↔ شمسی) ═════════════ */
  const div = (a, b) => Math.trunc(a / b);
  const J = {
    g2j(gy, gm, gd) {
      const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
      const gy2 = gm > 2 ? gy + 1 : gy;
      let days = 355666 + 365 * gy + div(gy2 + 3, 4) - div(gy2 + 99, 100) + div(gy2 + 399, 400) + gd + gdm[gm - 1];
      let jy = -1595 + 33 * div(days, 12053); days %= 12053;
      jy += 4 * div(days, 1461); days %= 1461;
      if (days > 365) { jy += div(days - 1, 365); days = (days - 1) % 365; }
      const jm = days < 186 ? 1 + div(days, 31) : 7 + div(days - 186, 30);
      const jd = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);
      return [jy, jm, jd];
    },
    j2g(jy, jm, jd) {
      jy += 1595;
      let days = -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33) + 3, 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
      let gy = 400 * div(days, 146097); days %= 146097;
      if (days > 36524) { days -= 1; gy += 100 * div(days, 36524); days %= 36524; if (days >= 365) days += 1; }
      gy += 4 * div(days, 1461); days %= 1461;
      if (days > 365) { gy += div(days - 1, 365); days = (days - 1) % 365; }
      let gd = days + 1;
      const leap = (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
      const ml = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
      let gm = 0;
      for (gm = 1; gm <= 12; gm++) { if (gd <= ml[gm]) break; gd -= ml[gm]; }
      return [gy, gm, gd];
    },
    isLeap(jy) { const [a, b, c] = J.j2g(jy, 12, 30); const [y, m, d] = J.g2j(a, b, c); return y === jy && m === 12 && d === 30; },
    monthLen(jy, jm) { return jm <= 6 ? 31 : jm <= 11 ? 30 : J.isLeap(jy) ? 30 : 29; },
    iso(gy, gm, gd) { return `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`; },
    parseIso(s) { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ''); return m ? [+m[1], +m[2], +m[3]] : null; },
    /** روز هفته‌ی یک تاریخ شمسی؛ شنبه = 0 */
    weekday(jy, jm, jd) { const [y, m, d] = J.j2g(jy, jm, jd); return (new Date(y, m - 1, d).getDay() + 1) % 7; },
    /** «اکنون» به وقت سرور (v7.7، DATA-009): ساعت دستگاه یا منطقه‌ی زمانی مرورگر روی «امروز» اثر نمی‌گذارد؛
        getterهای محلی همین Date ساعت دیواری سرور (تهران) را نشان می‌دهند. boot.clock = {now: ثانیه‌ی یونیکس سرور، tz: اختلاف ثانیه‌ای منطقه‌ی سرور} */
    now() {
      const ck = TK.boot().clock;
      if (!ck || !Number.isFinite(+ck.now)) return new Date();
      if (J._skew === undefined || J._ck !== ck.now) { J._ck = ck.now; J._skew = ck.now * 1000 - Date.now(); }
      const t = Date.now() + J._skew + (+ck.tz || 0) * 1000;
      return new Date(t + new Date(t).getTimezoneOffset() * 60000);
    },
    today() { const n = J.now(); return J.g2j(n.getFullYear(), n.getMonth() + 1, n.getDate()); },
  };
  J.MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  J.WD = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
  TK.jalali = J;
  const p2 = (n) => String(n).padStart(2, '0');

  /* ═════════════ تقویم شمسی ═════════════ */
  TK.datepicker = (host, { name, placeholder = 'انتخاب تاریخ', defaultToday = false, inline = false } = {}) => {
    host.classList.add('picker');
    host.innerHTML = `<input type="hidden" name="${E(name)}" value=""><button type="button" class="input picker-btn"><span class="picker-val"></span><i class="fa-regular fa-calendar"></i></button><div class="picker-pop jcal" hidden></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('.picker-btn'), val = host.querySelector('.picker-val'), pop = host.querySelector('.picker-pop');
    let sel = null, view = { y: 0, m: 0, mode: 'days' };           // sel = [jy,jm,jd]
    const paintVal = () => { val.innerHTML = sel ? `${FA(`${sel[0]}/${p2(sel[1])}/${p2(sel[2])}`)} <span class="faint xs">${J.WD[J.weekday(...sel)] ? ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'][J.weekday(...sel)] : ''}</span>` : `<span class="faint">${E(placeholder)}</span>`; };
    const set = (iso) => {
      const g = J.parseIso(iso);
      sel = g ? J.g2j(...g) : null;
      inp.value = g ? iso : '';
      paintVal(); if (inline && sel) { view = { y: sel[0], m: sel[1], mode: 'days' }; draw(); }
    };
    const commit = (jy, jm, jd) => { sel = [jy, jm, jd]; const [gy, gm, gd] = J.j2g(jy, jm, jd); inp.value = J.iso(gy, gm, gd); paintVal(); if (inline) draw(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); };
    const close = () => { if (!inline) { host.classList.remove('open'); pop.hidden = true; } };
    const draw = () => {
      const t = J.today();
      let head = '', body = '';
      if (view.mode === 'days') {
        const first = J.weekday(view.y, view.m, 1), len = J.monthLen(view.y, view.m);
        // v7.18.1 (درخواست کاربر): فلش راست = ماه بعد، فلش چپ = ماه قبل (سال‌ها هم همین‌طور)
        head = `<button type="button" data-nav="1" aria-label="ماه بعد"><i class="fa-solid fa-chevron-right"></i></button><button type="button" class="jcal-title" data-mode="months">${J.MONTHS[view.m - 1]} ${FA(view.y)}</button><button type="button" data-nav="-1" aria-label="ماه قبل"><i class="fa-solid fa-chevron-left"></i></button>`;
        body = `<div class="jcal-wd">${J.WD.map((d) => `<span>${d}</span>`).join('')}</div><div class="jcal-grid">${'<i></i>'.repeat(first)}${Array.from({ length: len }, (_, i) => {
          const d = i + 1, isT = t[0] === view.y && t[1] === view.m && t[2] === d, isS = sel && sel[0] === view.y && sel[1] === view.m && sel[2] === d;
          return `<button type="button" class="jcal-day${isT ? ' today' : ''}${isS ? ' sel' : ''}${(first + i) % 7 === 6 ? ' fri' : ''}" data-d="${d}">${FA(d)}</button>`;
        }).join('')}</div>`;
      } else if (view.mode === 'months') {
        head = `<button type="button" data-ynav="1" aria-label="سال بعد"><i class="fa-solid fa-chevron-right"></i></button><button type="button" class="jcal-title" data-mode="years">${FA(view.y)}</button><button type="button" data-ynav="-1" aria-label="سال قبل"><i class="fa-solid fa-chevron-left"></i></button>`;
        body = `<div class="jcal-months">${J.MONTHS.map((n, i) => `<button type="button" class="${t[0] === view.y && t[1] === i + 1 ? 'today' : ''}${sel && sel[0] === view.y && sel[1] === i + 1 ? ' sel' : ''}" data-m="${i + 1}">${n}</button>`).join('')}</div>`;
      } else {
        const base = view.y - (view.y % 12);
        head = `<button type="button" data-yrange="12" aria-label="سال‌های بعد"><i class="fa-solid fa-chevron-right"></i></button><span class="jcal-title">${FA(base)} – ${FA(base + 11)}</span><button type="button" data-yrange="-12" aria-label="سال‌های قبل"><i class="fa-solid fa-chevron-left"></i></button>`;
        body = `<div class="jcal-months">${Array.from({ length: 12 }, (_, i) => base + i).map((y) => `<button type="button" class="${t[0] === y ? 'today' : ''}${sel && sel[0] === y ? ' sel' : ''}" data-y="${y}">${FA(y)}</button>`).join('')}</div>`;
      }
      pop.innerHTML = `<div class="jcal-head">${head}</div>${body}<div class="jcal-foot"><button type="button" class="btn btn-xs btn-primary" data-today>امروز</button><button type="button" class="btn btn-xs" data-clear>پاک کردن</button></div>`;
    };
    const openPop = () => {
      const t = J.today(); const base = sel || t;
      view = { y: base[0], m: base[1], mode: 'days' }; draw();
      TK.qsa('.picker.open').forEach((x) => x !== host && x.classList.remove('open'));
      host.classList.add('open'); pop.hidden = false;
    };
    btn.addEventListener('click', (e) => { e.stopPropagation(); if (btn.disabled) return; host.classList.contains('open') ? close() : openPop(); });
    pop.addEventListener('click', (e) => {
      e.stopPropagation();
      const b = e.target.closest('button'); if (!b) return;
      if (b.dataset.d) return commit(view.y, view.m, +b.dataset.d);
      if (b.dataset.nav) { view.m += +b.dataset.nav; if (view.m > 12) { view.m = 1; view.y++; } if (view.m < 1) { view.m = 12; view.y--; } return draw(); }
      if (b.dataset.ynav) { view.y += +b.dataset.ynav; return draw(); }
      if (b.dataset.yrange) { view.y += +b.dataset.yrange; return draw(); }
      if (b.dataset.mode) { view.mode = b.dataset.mode; return draw(); }
      if (b.dataset.m) { view.m = +b.dataset.m; view.mode = 'days'; return draw(); }
      if (b.dataset.y) { view.y = +b.dataset.y; view.mode = 'months'; return draw(); }
      if (b.hasAttribute('data-today')) { const t = J.today(); return commit(...t); }
      if (b.hasAttribute('data-clear')) { sel = null; inp.value = ''; paintVal(); if (inline) draw(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) close(); });
    paintVal();
    if (defaultToday) { const t = J.today(); const [gy, gm, gd] = J.j2g(...t); set(J.iso(gy, gm, gd)); }
    if (inline) { host.classList.add('picker-inline'); btn.hidden = true; openPop(); }
    return { input: inp, host, set, get: () => inp.value, today: () => { const [gy, gm, gd] = J.j2g(...J.today()); return J.iso(gy, gm, gd); }, disable(off) { host.classList.toggle('disabled', !!off); btn.disabled = !!off; inp.disabled = !!off; } };
  };

  /* ═════════════ ساعت ۲۴ ساعته؛ انتخاب مستقیم، بدون مرحله تأیید ═════════════ */
  TK.timepicker = (host, { name, placeholder = 'انتخاب ساعت', inline = false } = {}) => {
    host.classList.add('picker', 'timepick');
    host.innerHTML = `<input type="hidden" name="${E(name)}"><button type="button" class="input picker-btn"><span class="picker-val"></span><i class="fa-regular fa-clock"></i></button><div class="picker-pop jclock" hidden></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('button'), val = host.querySelector('.picker-val'), pop = host.querySelector('.jclock');
    let hour = J.now().getHours(), minute = 0, mode = 'h';
    const paint = () => { val.innerHTML = inp.value ? `<b class="ltr">${FA(inp.value)}</b>` : `<span class="faint">${E(placeholder)}</span>`; };
    const draw = () => {
      pop.innerHTML = `<div class="clock-display"><span class="clock-caption"><i class="fa-regular fa-clock"></i> ${inp.value ? 'ساعت انجام' : 'بدون ساعت مشخص'}</span><div class="clock-digits" dir="ltr"><label><span class="sr-only">ساعت</span><input type="number" min="0" max="23" inputmode="numeric" aria-label="ساعت" data-clock-hour value="${p2(hour)}"></label><span>:</span><label><span class="sr-only">دقیقه</span><input type="number" min="0" max="59" inputmode="numeric" aria-label="دقیقه" data-clock-minute value="${p2(minute)}"></label></div><small>۲۴ ساعته · انتخاب مستقیم</small></div>`
        + `<div class="clock-tabs"><button type="button" data-clock-mode="h" aria-pressed="${mode === 'h'}">ساعت</button><button type="button" data-clock-mode="m" aria-pressed="${mode === 'm'}">دقیقه</button></div>`
        + `<div class="clock-grid" aria-label="${mode === 'h' ? 'انتخاب ساعت' : 'انتخاب دقیقه'}">${Array.from({ length: mode === 'h' ? 24 : 12 }, (_, i) => mode === 'h' ? i : i * 5).map(n => `<button type="button" data-clock-value="${n}" aria-pressed="${!!inp.value && n === (mode === 'h' ? hour : minute)}">${FA(p2(n))}</button>`).join('')}</div>`
        + '<div class="jcal-foot"><button type="button" class="btn btn-xs" data-clock-now>اکنون</button><button type="button" class="btn btn-xs" data-clock-clear>بدون ساعت</button></div>';
    };
    const commit = () => { inp.value = `${p2(hour)}:${p2(minute)}`; paint(); const caption = pop.querySelector('.clock-caption'); if (caption) caption.innerHTML = '<i class="fa-regular fa-clock"></i> ساعت انجام'; inp.dispatchEvent(new Event('change', { bubbles: true })); };
    const close = () => { if (!inline) { host.classList.remove('open'); pop.hidden = true; } };
    const set = (time) => { const m = /^(\d{1,2}):(\d{2})(?::\d{2})?$/.exec(time || ''); inp.value = m && +m[1] < 24 && +m[2] < 60 ? `${p2(+m[1])}:${m[2]}` : ''; if (inp.value) { hour = +m[1]; minute = +m[2]; } paint(); if (inline) draw(); };
    pop.addEventListener('click', e => {
      e.stopPropagation(); const b = e.target.closest('button'); if (!b || btn.disabled) return;
      if (b.dataset.clockMode) { mode = b.dataset.clockMode; draw(); }
      if (b.dataset.clockValue !== undefined) { if (mode === 'h') { hour = +b.dataset.clockValue; mode = 'm'; } else minute = +b.dataset.clockValue; commit(); draw(); }
      if (b.hasAttribute('data-clock-now')) { const n = J.now(); hour = n.getHours(); minute = n.getMinutes(); commit(); draw(); }
      if (b.hasAttribute('data-clock-clear')) { inp.value = ''; paint(); draw(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    pop.addEventListener('input', e => {
      const input = e.target; if (!input.matches('[data-clock-hour], [data-clock-minute]') || btn.disabled) return;
      if (!input.value || !input.checkValidity() || !/^\d+$/.test(input.value)) { host.dispatchEvent(new CustomEvent('picker:invalid', { bubbles: true })); return; }
      if (input.hasAttribute('data-clock-hour')) hour = +input.value; else minute = +input.value;
      commit();
    });
    btn.addEventListener('click', e => { e.stopPropagation(); if (btn.disabled) return; if (!pop.hidden) return close(); draw(); pop.hidden = false; host.classList.add('open'); });
    document.addEventListener('click', e => { if (!host.contains(e.target)) close(); });
    if (inline) { host.classList.add('picker-inline'); btn.hidden = true; pop.hidden = false; draw(); }
    paint();
    return { input: inp, host, set, get: () => inp.value, valid: () => [...pop.querySelectorAll('[data-clock-hour], [data-clock-minute]')].every(el => el.value !== '' && el.checkValidity()), disable(off) { btn.disabled = !!off; inp.disabled = !!off; host.classList.toggle('disabled', !!off); pop.querySelectorAll('button, input').forEach(el => { el.disabled = !!off; }); } };
  };

  /* ═════════════ فیلدهای رمز: دکمه‌ی نمایش/مخفی، قدرت رمز (data-pw-meter) و بررسی زنده‌ی تکرار رمز ═════════════ */
  const pwScore = (v) => { let s = 0; if (v.length >= 8) s++; if (v.length >= 12) s++; if (/[A-Za-z\u0600-\u06FF]/.test(v) && /\d/.test(v)) s++; if (/[^\w\s]/.test(v)) s++; return s; };
  const initPw = () => TK.qsa('input[type=password]:not([data-pw-ready])').forEach((el) => {
    el.dataset.pwReady = '1';
    const wrap = document.createElement('div'); wrap.className = 'pw-wrap';
    el.parentNode.insertBefore(wrap, el); wrap.appendChild(el);
    const b = document.createElement('button'); b.type = 'button'; b.className = 'pw-eye'; b.setAttribute('aria-label', 'نمایش رمز'); b.title = 'نمایش رمز';
    b.innerHTML = '<i class="fa-regular fa-eye"></i>';
    b.addEventListener('click', () => {
      const show = el.type === 'password'; el.type = show ? 'text' : 'password';
      b.innerHTML = `<i class="fa-regular fa-eye${show ? '-slash' : ''}"></i>`; b.title = show ? 'مخفی کردن رمز' : 'نمایش رمز'; b.setAttribute('aria-label', b.title);
    });
    wrap.appendChild(b);
    // قدرت رمز (مثل ثبت‌نام)
    if (el.hasAttribute('data-pw-meter')) {
      const m = document.createElement('div'); m.className = 'pw-meter'; m.innerHTML = '<i></i>'; wrap.after(m);
      el.addEventListener('input', () => { const s = pwScore(el.value); m.firstChild.style.width = (s * 25) + '%'; m.firstChild.style.background = ['#f87171', '#f87171', '#fbbf24', '#4ade80', '#22c55e'][s]; });
    }
    // تکرار رمز: مقایسه‌ی زنده با فیلد data-pw-match="نام فیلد اصلی"
    const target = el.dataset.pwMatch && el.form?.elements[el.dataset.pwMatch];
    if (target) {
      const h = document.createElement('div'); h.className = 'hint pw-match'; wrap.after(h);
      const check = () => {
        if (!el.value) { h.textContent = ''; h.className = 'hint pw-match'; el.setCustomValidity(''); return; }
        const ok = el.value === target.value;
        h.textContent = ok ? '✓ تکرار رمز درست است.' : 'تکرار رمز با رمز واردشده یکی نیست.'; h.className = 'hint pw-match ' + (ok ? 'ok' : 'bad');
        el.setCustomValidity(ok ? '' : 'تکرار رمز با رمز واردشده یکی نیست.');
      };
      el.addEventListener('input', check); target.addEventListener('input', check);
    }
  });
  initPw(); document.addEventListener('tk:ready', initPw);

  /* ═════════════ زمان انجام و یادآوری: یک فیلد برای تاریخ + ساعت ═════════════
     نمای سریع: امروز / فردا / پس‌فردا (هر کدام با ساعت‌های آماده) و «انتخاب تاریخ و زمان» برای روز و ساعت دلخواه.
     دو ورودی مخفی due_date (میلادی) و due_time (HH:MM) را پر می‌کند؛ خروجی: date و time با همان رابط datepicker/timepicker. */
  const WD_FULL = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
  const TIMES = ['09:00', '12:00', '15:00', '18:00', '21:00'];
  TK.duepicker = (host, { dateName = 'due_date', timeName = 'due_time', placeholder = 'تعیین زمان انجام', recurrence = false, reminders = false, inline = false } = {}) => {
    host.classList.add('picker', 'duepick');
    host.innerHTML = `<input type="hidden" name="${E(dateName)}" value=""><input type="hidden" name="${E(timeName)}" value="">`
      + '<button type="button" class="input picker-btn"><span class="picker-val"></span><i class="fa-regular fa-calendar-check"></i></button><div class="picker-pop dp-pop" hidden></div>';
    const [dIn, tIn] = host.querySelectorAll('input'), btn = host.querySelector('.picker-btn'), val = host.querySelector('.picker-val'), pop = host.querySelector('.picker-pop');
    const reminderBox = document.createElement('section'); reminderBox.className = 'dp-overdue-reminder'; reminderBox.hidden = true;
    if (reminders) {
      reminderBox.innerHTML = '<div class="dp-reminder-title"><span class="dp-reminder-icon"><i class="fa-regular fa-bell" aria-hidden="true"></i></span><div><b>اگر از موعد گذشت…</b><small>یادآوری را تکرار کن، هر</small></div></div><div class="dp-overdue-fields"><label><span class="sr-only">عدد فاصله یادآوری</span><input class="input ltr" type="number" name="reminder_every" min="1" max="1000" step="1" inputmode="numeric" placeholder="' + E(TK.ph('task_reminder_every', 'بدون تکرار')) + '" aria-label="عدد فاصله یادآوری"></label><label><span class="sr-only">واحد فاصله یادآوری</span><select class="input" name="reminder_unit" aria-label="واحد فاصله یادآوری">'
        + [['minute','دقیقه'],['hour','ساعت'],['day','روز'],['week','هفته'],['month','ماه']].map(([key,label]) => `<option value="${key}">${label}</option>`).join('')
        + '</select></label></div><p class="xs faint">خالی بگذارید تا تکرار نشود. بدون ساعت، تأخیر پس از پایان روز شروع می‌شود. ماه، ماه تقویمی میلادی است.</p>';
      host.appendChild(reminderBox);
    }
    const reminderEvery = reminderBox.querySelector('[name="reminder_every"]'), reminderUnit = reminderBox.querySelector('[name="reminder_unit"]');
    let repeat = { enabled: false, kind: 'daily', days: '' };
    const repeatInputs = {};
    if (recurrence) {
      ['enabled', 'kind', 'days'].forEach((key) => {
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'repeat_' + key; host.appendChild(input); repeatInputs[key] = input;
      });
    }
    const syncRepeat = () => {
      if (!recurrence) return;
      repeatInputs.enabled.value = repeat.enabled ? '1' : '0'; repeatInputs.kind.value = repeat.kind;
      repeatInputs.days.value = repeat.kind === 'custom' ? repeat.days : '';
    };
    const repeatHtml = () => !recurrence ? '' : `<section class="dp-repeat"><label class="dp-repeat-toggle"><input type="checkbox" data-repeat-toggle ${repeat.enabled ? 'checked' : ''}> تکرار خودکار</label>`
      + (repeat.enabled ? `<div class="dp-repeat-fields"><label>بازه تکرار<select class="input" data-repeat-kind aria-label="بازه تکرار">${[['daily','روزانه'],['weekly','هفتگی'],['monthly','ماهانه'],['custom','شخصی‌سازی']].map(([key,label]) => `<option value="${key}" ${repeat.kind === key ? 'selected' : ''}>${label}</option>`).join('')}</select></label>`
        + (repeat.kind === 'custom' ? `<label>هر چند روز؟<input class="input ltr" type="number" min="1" step="1" data-repeat-days aria-label="فاصله تکرار به روز" value="${E(repeat.days)}"></label>` : '')
        + '<p class="xs faint">هر نوبت مستقل است؛ حتی اگر نوبت قبلی انجام نشده باشد. تنظیمات برای کل سری است. تکرار ماهانه بر اساس ماه میلادی است.</p></div>' : '') + '</section>';
    syncRepeat();
    const isoOf = (offset) => { const n = J.now(); n.setDate(n.getDate() + offset); return J.iso(n.getFullYear(), n.getMonth() + 1, n.getDate()); };
    const DAYS = [['today', 0, 'امروز', 'fa-sun'], ['tomorrow', 1, 'فردا', 'fa-cloud-sun'], ['after', 2, 'پس‌فردا', 'fa-calendar-day']];
    const jLabel = (iso) => { const g = J.parseIso(iso); if (!g) return ''; const [jy, jm, jd] = J.g2j(...g); return `${WD_FULL[J.weekday(jy, jm, jd)]} ${FA(jd)} ${J.MONTHS[jm - 1]}`; };
    const rel = (iso) => (DAYS.find(([, off]) => isoOf(off) === iso) || [])[2] || '';
    const fT = (t) => FA(t || '');
    const paint = () => {
      const d = dIn.value, t = tIn.value;
      if (reminders) { reminderBox.hidden = !d; if (!d) { reminderEvery.value = ''; reminderUnit.value = 'minute'; } }
      val.innerHTML = d ? `<span>${E(rel(d) ? rel(d) + '، ' + jLabel(d).split(' ').slice(1).join(' ') : jLabel(d))}</span>${t ? `<span class="dp-time ltr">${fT(t)}</span>` : '<span class="faint xs">بدون ساعت</span>'}`
        : `<span class="faint">${E(placeholder)}</span>`;
      if (recurrence && repeat.enabled) val.innerHTML += '<span class="dp-repeat-badge"><i class="fa-solid fa-rotate" aria-hidden="true"></i> تکرار</span>';
      host.classList.toggle('has-val', !!d);
    };
    const fire = () => { dIn.dispatchEvent(new Event('change', { bubbles: true })); };
    const setBoth = (d, t) => { dIn.value = d || ''; tIn.value = d ? (t || '') : ''; if (!d && recurrence) { repeat.enabled = false; syncRepeat(); } paint(); };
    const close = () => { if (!inline) { host.classList.remove('open'); pop.hidden = true; if (recurrence) pop.innerHTML = ''; } };
    const nowMin = () => { const n = J.now(); return n.getHours() * 60 + n.getMinutes(); };
    const mins = (t) => +t.slice(0, 2) * 60 + +t.slice(3, 5);
    // پیش‌فرض ساعت با کلیک روی خود روز: ساعت فعلیِ انتخاب‌شده، وگرنه برای امروز ساعت کامل بعدی و برای بقیه ۰۹:۰۰
    const defTime = (off) => {
      if (tIn.value) return tIn.value;
      if (off > 0) return '09:00';
      const h = J.now().getHours() + 1;
      return h <= 23 ? `${p2(h)}:00` : '';
    };
    let openRow = null;
    const quick = () => {
      const d = dIn.value, t = tIn.value;
      const rows = DAYS.map(([key, off, title, icon]) => {
        const iso = isoOf(off), on = d === iso;
        const times = TIMES.filter((x) => off > 0 || mins(x) > nowMin());
        const chips = openRow === key ? `<div class="dp-times">${times.map((x) => `<button type="button" class="dp-chip${on && t === x ? ' on' : ''}" data-day="${off}" data-time="${x}">${fT(x)}</button>`).join('')}`
          + `<button type="button" class="dp-chip ghost${on && !t ? ' on' : ''}" data-day="${off}" data-time="">بدون ساعت</button></div>` : '';
        return `<div class="dp-row${on ? ' on' : ''}"><button type="button" class="dp-day" data-day="${off}"><span class="dp-ico"><i class="fa-solid ${icon}"></i></span>`
          + `<span class="dp-txt"><b>${title}</b><span class="xs faint">${E(jLabel(iso))}${on && t ? ' • ' + fT(t) : ''}</span></span></button>`
          + `<button type="button" class="dp-tbtn${openRow === key ? ' open' : ''}" data-row="${key}" aria-label="ساعت ${title}"><i class="fa-regular fa-clock"></i> ساعت <i class="fa-solid fa-chevron-down xs"></i></button>${chips}</div>`;
      }).join('');
      const custom = d && !rel(d);
      pop.innerHTML = `<div class="dp-head"><b><i class="fa-regular fa-bell"></i> زمان انجام و یادآوری</b></div>${rows}`
        + `<button type="button" class="dp-row dp-custom${custom ? ' on' : ''}" data-custom><span class="dp-ico"><i class="fa-regular fa-calendar"></i></span><span class="dp-txt"><b>انتخاب تاریخ و زمان</b><span class="xs faint">${custom ? E(jLabel(d)) + (t ? ' • ' + fT(t) : '') : 'روز و ساعت دلخواه'}</span></span><i class="fa-solid fa-chevron-left faint"></i></button>`
        + (d ? '<div class="dp-foot"><button type="button" class="btn btn-xs btn-ghost text-danger" data-clear><i class="fa-solid fa-xmark"></i> حذف زمان</button></div>' : '')
        + repeatHtml() + '<p class="xs faint dp-note">در ساعت تعیین‌شده یادآوری دریافت می‌کنید.</p>';
    };
    let sub = null;
    const customView = () => {
      pop.innerHTML = '<div class="dp-head"><button type="button" class="btn btn-ghost btn-icon btn-sm" data-back aria-label="بازگشت"><i class="fa-solid fa-arrow-right"></i></button><div><b>روز و ساعت دلخواه</b><small>با انتخاب، زمان ثبت می‌شود</small></div></div>'
        + '<div class="dp-sub"><section class="schedule-calendar"><div data-sub-date></div></section><section class="schedule-clock"><div data-sub-time></div></section></div>' + repeatHtml();
      sub = { d: TK.datepicker(pop.querySelector('[data-sub-date]'), { name: '', placeholder: 'تاریخ', inline: true }), t: TK.timepicker(pop.querySelector('[data-sub-time]'), { name: '', placeholder: 'ساعت (اختیاری)', inline: true }) };
      sub.d.set(dIn.value); sub.t.set(tIn.value);
      [sub.d.input, sub.t.input].forEach(input => input.addEventListener('change', e => {
        e.stopPropagation();
        const d = input === sub.t.input && sub.t.get() && !sub.d.get() ? sub.d.today() : sub.d.get();
        if (d && !sub.d.get()) sub.d.set(d);
        setBoth(d, sub.t.get()); if (!d) sub.t.set(''); fire(); place();
      }));
    };
    const place = () => {
      if (pop.hidden || inline) return;
      const viewport = window.visualViewport;
      const width = viewport?.width || innerWidth;
      const height = viewport?.height || innerHeight;
      if (width <= 600) {
        pop.style.removeProperty('top');
        pop.style.removeProperty('left');
        pop.style.removeProperty('max-height');
        return;
      }
      const r = btn.getBoundingClientRect();
      const frame = host.closest('.tf-body');
      const f = frame?.getBoundingClientRect();
      const topEdge = Math.max(8, f?.top || 8);
      const bottomEdge = Math.min(height - 8, f?.bottom || height - 8);
      const panelWidth = Math.min(pop.scrollWidth || 440, width - 16);
      const left = Math.max(8, Math.min(width - panelWidth - 8, r.right - panelWidth));
      const below = bottomEdge - r.bottom - 8;
      const above = r.top - topEdge - 8;
      const room = Math.max(80, Math.max(below, above));
      const panelHeight = Math.min(pop.scrollHeight, room, 620);
      pop.style.left = `${left}px`;
      pop.style.top = `${above > below ? Math.max(topEdge, r.top - panelHeight - 6) : Math.min(bottomEdge - panelHeight, r.bottom + 6)}px`;
      pop.style.maxHeight = `${Math.min(room, height - 16, 620)}px`;
    };
    window.addEventListener('resize', place);
    window.visualViewport?.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
    const openPop = () => { openRow = null; quick(); TK.qsa('.picker.open').forEach((x) => x !== host && x.classList.remove('open')); host.classList.add('open'); pop.hidden = false; place(); };
    btn.addEventListener('click', (e) => { e.stopPropagation(); if (btn.disabled) return; host.classList.contains('open') ? close() : openPop(); });
    pop.addEventListener('click', (e) => {
      e.stopPropagation();
      const b = e.target.closest('button'); if (!b || !pop.contains(b)) return;
      if (b.closest('[data-sub-date], [data-sub-time]')) return;
      if (b.dataset.time !== undefined) { setBoth(isoOf(+b.dataset.day), b.dataset.time); if (inline) quick(); else close(); return fire(); }
      if (b.dataset.row) { openRow = openRow === b.dataset.row ? null : b.dataset.row; quick(); place(); return; }
      if (b.dataset.day !== undefined) { const off = +b.dataset.day; setBoth(isoOf(off), defTime(off)); if (inline) quick(); else close(); return fire(); }
      if (b.hasAttribute('data-custom')) { customView(); place(); return; }
      if (b.hasAttribute('data-back')) { quick(); return place(); }
      if (b.hasAttribute('data-clear')) { setBoth('', ''); if (inline) quick(); else close(); return fire(); }

    });
    pop.addEventListener('change', (e) => {
      if (e.target.matches('[data-repeat-toggle]')) repeat.enabled = e.target.checked;
      else if (e.target.matches('[data-repeat-kind]')) { repeat.kind = e.target.value; if (repeat.kind === 'custom' && !repeat.days) repeat.days = '1'; }
      else return;
      syncRepeat(); pop.querySelector('.dp-repeat').outerHTML = repeatHtml(); paint(); place(); fire();
    });
    pop.addEventListener('input', (e) => {
      if (e.target.matches('[data-repeat-days]')) { repeat.days = e.target.value; syncRepeat(); fire(); }
    });
    document.addEventListener('click', (e) => { if (document.contains(e.target) && !host.contains(e.target)) close(); });
    paint();
    if (inline) { host.classList.add('duepick-inline'); btn.hidden = true; openPop(); }
    let off = false;
    const disable = (v) => { off = !!v; host.classList.toggle('disabled', off); btn.disabled = off; dIn.disabled = off; tIn.disabled = off; if (reminders) { reminderEvery.disabled = off; reminderUnit.disabled = off; } Object.values(repeatInputs).forEach((input) => { input.disabled = off; }); if (off) close(); };
    const date = { input: dIn, host, get: () => dIn.value, set: (iso) => { dIn.value = J.parseIso(iso) ? iso : ''; if (!dIn.value) tIn.value = ''; paint(); }, today: () => isoOf(0), disable };
    const time = { input: tIn, host, valid: () => !sub || !pop.contains(sub.t.host) || sub.t.valid(), get: () => tIn.value, set: (hhmm) => { const m = /^(\d{1,2}):(\d{2})/.exec(hhmm || ''); tIn.value = m ? `${p2(+m[1])}:${m[2]}` : ''; paint(); }, disable };
    return { date, time, set: setBoth, disable, close, reminders: {
      get: () => ({ reminder_every: reminderEvery?.value || '', reminder_unit: reminderUnit?.value || 'minute' }),
      set: (value) => { if (reminders) { reminderEvery.value = value?.reminder_every ?? ''; reminderUnit.value = value?.reminder_unit || 'minute'; paint(); } },
      valid: () => !reminders || reminderEvery.value === '' || (!!dIn.value && /^[0-9]+$/.test(reminderEvery.value) && +reminderEvery.value >= 1 && +reminderEvery.value <= 1000),
    }, recurrence: {
      get: () => ({ ...repeat }),
      set: (value) => { repeat = { enabled: !!value?.enabled, kind: value?.kind || 'daily', days: value?.days == null ? '' : String(value.days) }; syncRepeat(); paint(); },
      valid: () => !repeat.enabled || (!!dIn.value && (repeat.kind !== 'custom' || (/^[0-9]+$/.test(repeat.days) && Number.isSafeInteger(Number(repeat.days)) && Number(repeat.days) > 0))),
      open: openPop,
    } };
  };

  /* ═════════════ فیلد موبایل: «09» ثابت و غیرقابل‌حذف؛ کاربر فقط بقیه‌ی ۹ رقم را می‌نویسد ═════════════ */
  const digits = (v) => String(v).replace(/[۰-۹]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/\D/g, '');
  const PREFIX = '09';
  /** هر ورودی (تایپ، چسباندن، پر‌شدن خودکار، +98 …) را به شکل 09xxxxxxxxx (حداکثر ۱۱ رقم) درمی‌آورد. */
  const normMobile = (raw) => {
    let d = digits(raw);
    if (d.startsWith('0098')) d = '0' + d.slice(4);
    else if (d.startsWith('98') && d.length > 10) d = '0' + d.slice(2);
    if (d.startsWith(PREFIX)) { /* درست است */ }
    else if (d.startsWith('9')) d = '0' + d;
    else if (d.startsWith('0')) d = PREFIX + d.slice(1);
    else d = PREFIX + d;
    return d.slice(0, 11);
  };
  const paintMobile = (el) => {
    el.classList.toggle('is-bad', el.value.length > PREFIX.length && el.value.length < 11);   // در حال تایپ: قرمز
    el.classList.toggle('is-good', el.value.length === 11);                                    // کامل: سبز
  };
  TK.mobileField = (el) => {
    el.setAttribute('maxlength', '11'); el.setAttribute('inputmode', 'numeric'); el.setAttribute('autocomplete', 'tel'); el.setAttribute('dir', 'ltr');
    const prefill = () => { const n = normMobile(el.value); if (el.value !== n) el.value = n; paintMobile(el); };
    const caretOk = () => { if (el.selectionStart !== null && el.selectionStart < PREFIX.length) el.setSelectionRange(PREFIX.length, Math.max(PREFIX.length, el.selectionEnd)); };
    prefill();

    el.addEventListener('input', () => {
      const fromEnd = el.value.length - (el.selectionStart === null ? el.value.length : el.selectionStart);
      const n = normMobile(el.value);
      if (n !== el.value) { el.value = n; const pos = Math.max(PREFIX.length, n.length - fromEnd); try { el.setSelectionRange(pos, pos); } catch (e) { /* برخی انواع input */ } }
      paintMobile(el);
    });
    el.addEventListener('change', prefill);

    // حذف دو رقم اول ممنوع است (کیبورد دسکتاپ)
    el.addEventListener('keydown', (e) => {
      if (e.key !== 'Backspace' && e.key !== 'Delete') return;
      const s = el.selectionStart, en = el.selectionEnd;
      if (s === en && ((e.key === 'Backspace' && s <= PREFIX.length) || (e.key === 'Delete' && s < PREFIX.length))) e.preventDefault();
      else if (s !== en && en <= PREFIX.length) e.preventDefault();
    });
    // چسباندن/پر‌شدن خودکارِ شماره‌ی کامل (۰۹۱۲…، ۹۱۲…، ‎+98…): کلِ مقدار جایگزین شود؛ وگرنه کنار «09» ثابت می‌نشست و maxlength آن را می‌برید (09091277712)
    el.addEventListener('beforeinput', (e) => {
      if (!e.inputType || !e.inputType.startsWith('insert')) return;
      const raw = e.data ?? e.dataTransfer?.getData('text') ?? '';
      const d = digits(raw);
      if (d.length < 10) return;
      e.preventDefault();
      el.value = normMobile(d);
      el.setSelectionRange(el.value.length, el.value.length);
      paintMobile(el);
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    // کیبورد موبایل/IME و برش/حذف انتخاب‌شده
    el.addEventListener('beforeinput', (e) => {
      if (!e.inputType || !e.inputType.startsWith('delete')) return;
      const s = el.selectionStart, en = el.selectionEnd;
      if (en <= PREFIX.length && (s === en ? true : true)) { if (s === en && e.inputType === 'deleteContentForward' && s >= PREFIX.length) return; e.preventDefault(); return; }
      if (s < PREFIX.length) { e.preventDefault(); el.value = el.value.slice(0, PREFIX.length) + el.value.slice(en); el.setSelectionRange(PREFIX.length, PREFIX.length); paintMobile(el); }
    });
    ['focus', 'click', 'mouseup', 'keyup'].forEach((ev) => el.addEventListener(ev, () => setTimeout(caretOk, 0)));
    el.addEventListener('blur', () => paintMobile(el));
    if (el.form) el.form.addEventListener('reset', () => setTimeout(prefill, 0));
  };
  const initMobile = () => TK.qsa('input[data-mobile]').forEach((el) => { if (!el.dataset.mobileReady) { el.dataset.mobileReady = '1'; TK.mobileField(el); } });
  initMobile(); document.addEventListener('tk:ready', initMobile);
  TK.normMobile = normMobile;
  // مانع ارسال فرم با موبایل ناقص (قبل از هندلر AJAX سراسری)
  document.addEventListener('submit', (e) => {
    const bad = TK.qsa('input[data-mobile]', e.target).find((el) => (el.required || el.value.length > 2) && el.value.length !== 11);
    if (bad) { e.preventDefault(); e.stopPropagation(); bad.classList.add('is-bad'); bad.focus(); TK.toast('شماره موبایل باید ۱۱ رقم باشد و با ۰۹ شروع شود.', 'error'); }
  }, true);
})();
