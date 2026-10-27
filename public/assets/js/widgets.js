/* Taskam — ویجت‌های مشترک: تقویم شمسی، ساعت آنالوگ (AM/PM)، فیلد موبایل. فضای نام TK (بعد از app.js). */
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
    today() { const n = new Date(); return J.g2j(n.getFullYear(), n.getMonth() + 1, n.getDate()); },
  };
  J.MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  J.WD = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
  TK.jalali = J;
  const p2 = (n) => String(n).padStart(2, '0');

  /* ═════════════ تقویم شمسی ═════════════ */
  TK.datepicker = (host, { name, placeholder = 'انتخاب تاریخ', defaultToday = false } = {}) => {
    host.classList.add('picker');
    host.innerHTML = `<input type="hidden" name="${E(name)}" value=""><button type="button" class="input picker-btn"><span class="picker-val"></span><i class="fa-regular fa-calendar"></i></button><div class="picker-pop jcal" hidden></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('.picker-btn'), val = host.querySelector('.picker-val'), pop = host.querySelector('.picker-pop');
    let sel = null, view = { y: 0, m: 0, mode: 'days' };           // sel = [jy,jm,jd]
    const paintVal = () => { val.innerHTML = sel ? `${FA(`${sel[0]}/${p2(sel[1])}/${p2(sel[2])}`)} <span class="faint xs">${J.WD[J.weekday(...sel)] ? ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'][J.weekday(...sel)] : ''}</span>` : `<span class="faint">${E(placeholder)}</span>`; };
    const set = (iso) => {
      const g = J.parseIso(iso);
      sel = g ? J.g2j(...g) : null;
      inp.value = g ? iso : '';
      paintVal();
    };
    const commit = (jy, jm, jd) => { sel = [jy, jm, jd]; const [gy, gm, gd] = J.j2g(jy, jm, jd); inp.value = J.iso(gy, gm, gd); paintVal(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); };
    const close = () => { host.classList.remove('open'); pop.hidden = true; };
    const draw = () => {
      const t = J.today();
      let head = '', body = '';
      if (view.mode === 'days') {
        const first = J.weekday(view.y, view.m, 1), len = J.monthLen(view.y, view.m);
        head = `<button type="button" data-nav="1" aria-label="ماه بعد"><i class="fa-solid fa-chevron-right"></i></button><button type="button" class="jcal-title" data-mode="months">${J.MONTHS[view.m - 1]} ${FA(view.y)}</button><button type="button" data-nav="-1" aria-label="ماه قبل"><i class="fa-solid fa-chevron-left"></i></button>`;
        body = `<div class="jcal-wd">${J.WD.map((d) => `<span>${d}</span>`).join('')}</div><div class="jcal-grid">${'<i></i>'.repeat(first)}${Array.from({ length: len }, (_, i) => {
          const d = i + 1, isT = t[0] === view.y && t[1] === view.m && t[2] === d, isS = sel && sel[0] === view.y && sel[1] === view.m && sel[2] === d;
          return `<button type="button" class="jcal-day${isT ? ' today' : ''}${isS ? ' sel' : ''}${(first + i) % 7 === 6 ? ' fri' : ''}" data-d="${d}">${FA(d)}</button>`;
        }).join('')}</div>`;
      } else if (view.mode === 'months') {
        head = `<button type="button" data-ynav="1"><i class="fa-solid fa-chevron-right"></i></button><button type="button" class="jcal-title" data-mode="years">${FA(view.y)}</button><button type="button" data-ynav="-1"><i class="fa-solid fa-chevron-left"></i></button>`;
        body = `<div class="jcal-months">${J.MONTHS.map((n, i) => `<button type="button" class="${t[0] === view.y && t[1] === i + 1 ? 'today' : ''}${sel && sel[0] === view.y && sel[1] === i + 1 ? ' sel' : ''}" data-m="${i + 1}">${n}</button>`).join('')}</div>`;
      } else {
        const base = view.y - (view.y % 12);
        head = `<button type="button" data-yrange="12"><i class="fa-solid fa-chevron-right"></i></button><span class="jcal-title">${FA(base)} – ${FA(base + 11)}</span><button type="button" data-yrange="-12"><i class="fa-solid fa-chevron-left"></i></button>`;
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
      if (b.hasAttribute('data-clear')) { sel = null; inp.value = ''; paintVal(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) close(); });
    paintVal();
    if (defaultToday) { const t = J.today(); const [gy, gm, gd] = J.j2g(...t); set(J.iso(gy, gm, gd)); }
    return { input: inp, host, set, get: () => inp.value, today: () => { const [gy, gm, gd] = J.j2g(...J.today()); return J.iso(gy, gm, gd); }, disable(off) { host.classList.toggle('disabled', !!off); btn.disabled = !!off; inp.disabled = !!off; } };
  };

  /* ═════════════ ساعت آنالوگ با AM/PM ═════════════ */
  TK.timepicker = (host, { name, placeholder = 'انتخاب ساعت' } = {}) => {
    host.classList.add('picker');
    host.innerHTML = `<input type="hidden" name="${E(name)}" value=""><button type="button" class="input picker-btn"><span class="picker-val"></span><i class="fa-regular fa-clock"></i></button><div class="picker-pop jclock" hidden></div>`;
    const inp = host.querySelector('input'), btn = host.querySelector('.picker-btn'), val = host.querySelector('.picker-val'), pop = host.querySelector('.picker-pop');
    let cur = null;                 // {h:1..12, m:0..59, pm:bool}
    let draft = null, mode = 'h';
    const to24 = (c) => (c.h % 12) + (c.pm ? 12 : 0);
    const from24 = (h24, m) => ({ h: h24 % 12 === 0 ? 12 : h24 % 12, m, pm: h24 >= 12 });
    const paintVal = () => { val.innerHTML = cur ? `<span class="ltr">${FA(`${p2(cur.h)}:${p2(cur.m)}`)}</span> <b class="xs" style="color:var(--g-300)">${cur.pm ? 'PM' : 'AM'}</b>` : `<span class="faint">${E(placeholder)}</span>`; };
    const set = (hhmm) => {
      const m = /^(\d{1,2}):(\d{2})/.exec(hhmm || '');
      if (m) { cur = from24(+m[1], +m[2]); inp.value = `${p2(to24(cur))}:${p2(cur.m)}`; } else { cur = null; inp.value = ''; }
      paintVal();
    };
    const close = () => { host.classList.remove('open'); pop.hidden = true; };
    const R = 88, C = 110;
    const pos = (i, r) => { const a = (i * 30 - 90) * Math.PI / 180; return [C + r * Math.cos(a), C + r * Math.sin(a)]; };
    const draw = () => {
      const val = mode === 'h' ? draft.h : draft.m;
      const ang = mode === 'h' ? (draft.h % 12) * 30 : draft.m * 6;
      const [hx, hy] = (() => { const a = (ang - 90) * Math.PI / 180; return [C + (R - 8) * Math.cos(a), C + (R - 8) * Math.sin(a)]; })();
      const nums = mode === 'h' ? Array.from({ length: 12 }, (_, i) => [i + 1, i + 1]) : Array.from({ length: 12 }, (_, i) => [i * 5, i * 5]);
      const dial = nums.map(([label, v]) => {
        const idx = mode === 'h' ? label % 12 : label / 5; const [x, y] = pos(idx, R);
        const on = mode === 'h' ? draft.h === label : draft.m === v;
        return `<g class="jc-n${on ? ' on' : ''}"><circle cx="${x}" cy="${y}" r="17"/><text x="${x}" y="${y + 4}" text-anchor="middle">${mode === 'h' ? FA(label) : FA(p2(label))}</text></g>`;
      }).join('');
      const extra = mode === 'm' && draft.m % 5 !== 0 ? `<circle cx="${hx}" cy="${hy}" r="17" class="jc-fine"/>` : '';
      pop.innerHTML = `
        <div class="jc-top ltr">
          <button type="button" class="jc-seg${mode === 'h' ? ' on' : ''}" data-mode="h">${FA(p2(draft.h))}</button><span>:</span><button type="button" class="jc-seg${mode === 'm' ? ' on' : ''}" data-mode="m">${FA(p2(draft.m))}</button>
          <div class="jc-ampm"><button type="button" class="${!draft.pm ? 'on' : ''}" data-ap="am">AM</button><button type="button" class="${draft.pm ? 'on' : ''}" data-ap="pm">PM</button></div>
        </div>
        <svg class="jc-dial" viewBox="0 0 220 220" data-dial><circle cx="${C}" cy="${C}" r="104" class="jc-face"/><line x1="${C}" y1="${C}" x2="${hx}" y2="${hy}" class="jc-hand"/><circle cx="${C}" cy="${C}" r="4" class="jc-pin"/>${extra}${dial}</svg>
        <div class="jc-hint xs faint">${mode === 'h' ? 'ساعت را روی صفحه انتخاب کنید' : 'دقیقه را انتخاب کنید (برای دقت بیشتر، کشیدن/کلیک روی هر نقطه)'}</div>
        <div class="jcal-foot"><button type="button" class="btn btn-xs" data-now>اکنون</button><button type="button" class="btn btn-xs" data-clear>پاک کردن</button><button type="button" class="btn btn-xs btn-primary" data-ok>تأیید</button></div>`;
    };
    const fromPointer = (e, svg) => {
      const r = svg.getBoundingClientRect(), x = e.clientX - (r.left + r.width / 2), y = e.clientY - (r.top + r.height / 2);
      const a = (Math.atan2(x, -y) * 180 / Math.PI + 360) % 360;
      if (mode === 'h') { let h = Math.round(a / 30) % 12; draft.h = h === 0 ? 12 : h; }
      else { let m = Math.round(a / 6) % 60; const near = Math.round(m / 5) * 5; if (Math.abs(near - m) <= 1) m = near % 60; draft.m = m; }
    };
    let dragging = false;
    pop.addEventListener('pointerdown', (e) => { const svg = e.target.closest('[data-dial]'); if (!svg) return; dragging = true; fromPointer(e, svg); draw(); });
    pop.addEventListener('pointermove', (e) => { if (!dragging) return; const svg = pop.querySelector('[data-dial]'); if (svg) { fromPointer(e, svg); draw(); } });
    document.addEventListener('pointerup', () => { if (dragging) { dragging = false; if (mode === 'h') { mode = 'm'; draw(); } } });
    pop.addEventListener('click', (e) => {
      e.stopPropagation();
      const b = e.target.closest('button'); if (!b) return;
      if (b.dataset.mode) { mode = b.dataset.mode; return draw(); }
      if (b.dataset.ap) { draft.pm = b.dataset.ap === 'pm'; return draw(); }
      if (b.hasAttribute('data-now')) { const n = new Date(); draft = from24(n.getHours(), n.getMinutes()); return draw(); }
      if (b.hasAttribute('data-clear')) { cur = null; inp.value = ''; paintVal(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); return; }
      if (b.hasAttribute('data-ok')) { cur = { ...draft }; inp.value = `${p2(to24(cur))}:${p2(cur.m)}`; paintVal(); close(); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    btn.addEventListener('click', (e) => {
      e.stopPropagation(); if (btn.disabled) return;
      if (host.classList.contains('open')) return close();
      const n = new Date(); draft = cur ? { ...cur } : from24(n.getHours(), n.getMinutes()); mode = 'h'; draw();
      TK.qsa('.picker.open').forEach((x) => x !== host && x.classList.remove('open'));
      host.classList.add('open'); pop.hidden = false;
    });
    document.addEventListener('click', (e) => { if (!host.contains(e.target)) close(); });
    paintVal();
    return { input: inp, host, set, get: () => inp.value, disable(off) { host.classList.toggle('disabled', !!off); btn.disabled = !!off; inp.disabled = !!off; } };
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
