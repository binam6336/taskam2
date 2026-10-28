/* تور آموزشی پنل: پرسش اولیه، سپس مرحله‌به‌مرحله با تار شدن صفحه و اشاره به هر بخش.
   وضعیت در users.tour ذخیره می‌شود (NULL = پرسیده نشده، skip، done یا شماره‌ی مرحله) تا بین صفحه‌ها ادامه پیدا کند. */
(() => {
  'use strict';
  const boot = TK.boot().tour;
  if (!boot) return;
  const mobile = window.matchMedia('(max-width: 960px)');
  const sidebarOpen = () => TK.qs('.sidebar')?.classList.contains('open');
  const tasksGroup = () => TK.qs('#nav-g-tasks')?.closest('[data-nav-group]');
  const field = (name, text, extra = {}) => ({ page: 'tasks', target: `#f-task [data-tour="${name}"]`, action: 'ok', back: 4, text, ...extra });

  /* مرحله‌ها با شماره‌ی ثابت (همان که ذخیره می‌شود).
     page: صفحه‌ای که مرحله در آن اجرا می‌شود | when: شرط نمایش | target: بخشی که به آن اشاره می‌شود (اولین مورد نمایان)
     action: click = با کلیک کاربر روی بخش جلو می‌رود، go = لینک بخش باز می‌شود و تور در صفحه‌ی بعد ادامه دارد،
             ok = دکمه‌ی «فهمیدم» (کاربر می‌تواند همان بخش را پر کند)، submit = بعد از ذخیره‌ی موفق فرم جلو می‌رود
     back: اگر بخش پنهان شد (مثلاً پنجره بسته شد) به این مرحله برمی‌گردد */
  const STEPS = {
    1: {
      page: 'dashboard', when: () => mobile.matches, target: '.menu-btn', action: 'click', next: 2,
      text: 'برای باز کردن منو و دسترسی به گزینه‌ها روی این گزینه کلیک کن.',
    },
    2: {
      page: 'dashboard', target: '.nav-parent[aria-controls="nav-g-tasks"]', action: 'click', next: 3,
      prepare: () => { if (mobile.matches && !sidebarOpen()) TK.qs('.menu-btn')?.click(); },
      text: 'با کلیک روی این گزینه می‌تونی وظایف فعلیت رو مدیریت، زمان‌بندی و بررسی کنی.',
    },
    3: {
      page: 'dashboard', target: '#nav-g-tasks a.nav-sub[href$="/tasks"]', action: 'go', next: 4,
      prepare: () => {
        if (mobile.matches && !sidebarOpen()) TK.qs('.menu-btn')?.click();
        const g = tasksGroup();
        if (g && !g.classList.contains('open')) g.querySelector('.nav-parent').click();
      },
      text: 'وظایف اول باید تو قسمت تسک‌ها ایجاد بشن؛ اینجا بخش اصلی و ساده‌ی مدیریت وظایف هست.',
    },
    4: {
      page: 'tasks', target: '#btn-new-task', action: 'click', next: 5,
      text: 'با کلیک روی این دکمه می‌تونی تسک جدید ایجاد کنی.',
    },
    5: field('title', 'تو این بخش می‌تونی برای تسک عنوان بذاری.', { next: 6 }),
    6: field('desc', 'توضیحات یا جزئیات مربوط به تسک رو اینجا بنویس.', { next: 7 }),
    7: field('project', 'اینجا پروژه‌ی مربوط به تسک رو انتخاب کنید. پروژه‌ها برای مدیریت بهتر موضوعات تسک‌ها استفاده می‌شن و می‌تونید تنظیمات مدنظر خودتون رو روی پروژه اعمال کنید.', { next: 8 }),
    8: field('assignee', 'اینجا مشخص می‌کنی این تسک رو کی باید انجام بده و مسئولش چه شخصیه.', { next: 9 }),
    9: field('priority', 'این فیلد مشخص می‌کنه اولویت این تسک برای تیم چقدره.', { next: 10 }),
    10: field('label', 'لیبل به شما کمک می‌کنه تگ‌های مدنظر رو برای تسک‌ها ایجاد کنید.', { next: 11 }),
    11: field('due', 'تاریخ انجام تسک اینجا مشخص می‌شه و تو تاریخ مربوط به انجام، برای شما نوتیفیکیشن یادآوری ارسال می‌شه.', { next: 12 }),
    12: field('time', 'حتی می‌تونید ساعت دقیق یادآوری رو هم اینجا مشخص کنید.', { next: 13 }),
    13: field('files', 'اگه فایلی باید به تسک اضافه بشه، اینجا می‌تونید اضافه کنید.', { next: 14 }),
    14: field('save', 'با کلیک روی ذخیره، تسک شما ایجاد می‌شه.', {
      action: 'submit', next: 15, celebrate: 'تسک شما ایجاد شد!',
      // فیلد اجباری خالی → برگشت به مرحله‌ی همان فیلد و بعد از «فهمیدم» دوباره همین‌جا
      check: () => {
        const f = TK.qs('#f-task');
        if (!f.elements.title.value.trim()) return 5;
        if (!f.elements.project_id.value) return 7;
        if (!f.elements.subject_id.value || f.elements.subject_id.value === '__new') return 10;
        return 0;
      },
    }),
  };
  const ids = Object.keys(STEPS).map(Number).sort((a, b) => a - b);
  const active = () => ids.filter((i) => !STEPS[i].when || STEPS[i].when());
  const visible = (el) => { const r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; };
  const find = (sel) => TK.qsa(sel).find(visible) || null;
  // منوهای بازشونده‌ی داخل بخش (انتخاب پروژه/مسئول، تقویم، ساعت) هم باید دیده و کلیک شوند
  const POPUPS = '.rselect.open .rselect-menu, .picker-pop:not([hidden])';

  const save = (state) => TK.post('/tour', { state });

  /* ───── پرسش اولیه ───── */
  const ask = () => {
    const box = document.createElement('div');
    box.className = 'tour-ask';
    box.innerHTML = `<div class="tour-ask-box" role="dialog" aria-modal="true" aria-labelledby="tour-ask-t">
        <div class="tour-ask-ico"><i class="fa-solid fa-graduation-cap"></i></div>
        <h3 id="tour-ask-t">به تسکام خوش اومدی!</h3>
        <p>قبلاً با این پنل کار کردی؟</p>
        <div class="tour-ask-btns">
          <button type="button" class="btn btn-primary" data-tour-learn><i class="fa-solid fa-route"></i> نه، آموزش رو شروع کن</button>
          <button type="button" class="btn" data-tour-skip>آره، آشنا هستم</button>
        </div>
      </div>`;
    document.body.appendChild(box);
    box.querySelector('[data-tour-learn]').focus();
    box.addEventListener('click', async (e) => {
      const learn = e.target.closest('[data-tour-learn]'), skip = e.target.closest('[data-tour-skip]');
      if (!learn && !skip) return;
      box.querySelectorAll('button').forEach((b) => (b.disabled = true));
      const first = active()[0];
      await save(skip ? 'skip' : String(first));
      box.remove();
      if (skip) return;
      if (boot.page !== STEPS[first].page) location.href = TK.url('/' + STEPS[first].page);
      else run(first);
    });
  };

  /* ───── تیک موفقیت (انیمیشنی) ───── */
  const celebrate = (text) => {
    const box = document.createElement('div');
    box.className = 'tour-done';
    box.innerHTML = `<div class="tour-done-box"><svg viewBox="0 0 52 52" aria-hidden="true"><circle cx="26" cy="26" r="24"/><path d="M15 27l7 7 15-16"/></svg><b>${TK.esc(text)}</b></div>`;
    document.body.appendChild(box);
    setTimeout(() => { box.classList.add('out'); setTimeout(() => box.remove(), 350); }, 2000);
  };

  /* ───── اجرای مرحله‌ها ───── */
  let ui = null, raf = 0, cur = null, form = null, resume = null;

  const build = () => {
    ui = document.createElement('div');
    ui.className = 'tour';
    ui.innerHTML = '<div class="tour-shade"></div><div class="tour-shade"></div><div class="tour-shade"></div><div class="tour-shade"></div>'
      + '<div class="tour-ring"></div><div class="tour-pop" role="dialog" aria-live="polite"><span class="tour-arrow"></span><div class="tour-body"></div></div>';
    // کلیک روی تور به صفحه نمی‌رسد (منوی شناور هم بسته نمی‌شود)
    ui.addEventListener('click', (e) => {
      e.stopPropagation();
      if (e.target.closest('[data-tour-ok]')) advance();
      else if (e.target.closest('[data-tour-exit]')) exit();
    });
    document.body.appendChild(ui);
    document.addEventListener('click', onClick, true);
  };

  const finish = () => {
    cancelAnimationFrame(raf);
    document.removeEventListener('click', onClick, true);
    form?.removeEventListener('tk:done', onSaved);
    ui?.remove();
    ui = cur = form = null;
  };

  const exit = async () => {
    const id = cur;
    if (!(await TK.confirm('آموزش بسته شود؟ دیگر نمایش داده نمی‌شود.'))) { if (id) run(id); return; } // کلیک پنجره‌ی تأیید ممکن است منو را بسته باشد
    finish();
    save('skip');
  };

  // رفتن به مرحله‌ی بعد؛ اگر در همین صفحه نباشد، ذخیره می‌شود تا در صفحه‌ی خودش ادامه پیدا کند
  const advance = () => {
    const step = STEPS[cur];
    if (resume && step.action === 'ok') { const r = resume; resume = null; run(r); return; }
    let n = step.next;
    while (n && STEPS[n] && STEPS[n].when && !STEPS[n].when()) n = STEPS[n].next;
    if (!n) { finish(); save('done'); return; }
    if (!STEPS[n] || STEPS[n].page !== boot.page) { finish(); save(String(n)); return; }
    run(n);
  };

  // ذخیره‌ی موفق فرم در مرحله‌ی submit: تیک انیمیشنی و آماده‌ی مرحله‌ی بعد؛ خطا (مثلاً لیبل انتخاب نشده) = ماندن در همین مرحله
  const onSaved = (e) => {
    if (cur === null || STEPS[cur].action !== 'submit' || !TK.ok(e.detail)) return;
    const step = STEPS[cur];
    if (step.celebrate) celebrate(step.celebrate);
    advance();
  };

  const onClick = (e) => {
    if (cur === null) return;
    const step = STEPS[cur];
    const t = e.target.closest?.(step.target);
    if (!t || step.action === 'ok') return;
    if (step.action === 'submit') {
      const miss = step.check?.();
      if (miss) {
        e.preventDefault();
        e.stopPropagation();
        TK.toast('اول این بخش رو کامل کن.', 'warning');
        resume = step.id;
        run(miss);
      }
      return;
    }
    if (step.action === 'go') {
      e.preventDefault();
      e.stopPropagation();
      const href = t.href;
      finish();
      save(String(step.next)).then(() => { location.href = href; });
      return;
    }
    cur = null; // جلوگیری از دو بار رفتن
    setTimeout(() => { cur = Number(step.id); advance(); }, 380); // تا انیمیشن باز شدن منو/پنجره تمام شود
  };

  const run = (id) => {
    const step = STEPS[id];
    if (!step) return;
    step.id = id;
    if (!ui) build();
    cur = null;
    step.prepare?.();
    const list = active();
    const pos = list.indexOf(id) + 1;
    const hint = step.action === 'ok'
      ? '<button type="button" class="btn btn-primary btn-sm" data-tour-ok>فهمیدم</button>'
      : '<span class="tour-hint"><i class="fa-solid fa-hand-pointer"></i> روی بخش مشخص‌شده کلیک کن</span>';
    ui.querySelector('.tour-body').innerHTML = `<div class="tour-step">مرحله‌ی ${TK.fa(pos)} از ${TK.fa(list.length)}</div>
      <p>${TK.esc(step.text)}</p>
      <div class="tour-foot">${hint}<button type="button" class="tour-exit" data-tour-exit>رد کردن آموزش</button></div>`;
    const pop = ui.querySelector('.tour-pop');
    pop.classList.remove('in');
    // صبر تا بخش موردنظر ظاهر شود (باز شدن منو/پنجره)؛ اگر پیدا نشد، به مرحله‌ی برگشت یا بعدی می‌رود
    const t0 = performance.now();
    const wait = () => {
      const el = find(step.target);
      if (!el) {
        if (performance.now() - t0 > 1800) { if (step.back) run(step.back); else { cur = id; advance(); } return; }
        raf = requestAnimationFrame(wait);
        return;
      }
      el.scrollIntoView({ block: 'nearest' });
      cur = id;
      if (step.action === 'submit') { form = el.closest('form'); form?.addEventListener('tk:done', onSaved); }
      void pop.offsetWidth;
      pop.classList.add('in');
      track(el);
    };
    cancelAnimationFrame(raf);
    wait();
  };

  // جای بخش هر فریم دوباره خوانده می‌شود (منو با انیمیشن باز می‌شود، صفحه ممکن است تغییر اندازه دهد)
  const track = (el) => {
    const [top, right, bottom, left] = ui.querySelectorAll('.tour-shade');
    const ring = ui.querySelector('.tour-ring'), pop = ui.querySelector('.tour-pop'), arrow = ui.querySelector('.tour-arrow');
    let shown = new Set();
    const set = (n, x, y, w, h) => Object.assign(n.style, { left: x + 'px', top: y + 'px', width: Math.max(0, w) + 'px', height: Math.max(0, h) + 'px' });
    const frame = () => {
      if (cur !== null && !visible(el)) {
        const step = STEPS[cur];
        const again = find(step.target);
        if (again) el = again;
        else if (step.back) { run(step.back); return; } // مثلاً پنجره‌ی تسک بسته شد
        else { raf = requestAnimationFrame(frame); return; }
      }
      const vw = window.innerWidth, vh = window.innerHeight, pad = 6;
      let b = el.getBoundingClientRect();
      let l = b.left, t = b.top, r = b.right, btm = b.bottom;
      const open = new Set();
      el.querySelectorAll(POPUPS).forEach((p) => {
        const q = p.getBoundingClientRect();
        if (!q.width) return;
        open.add(p);
        if (!shown.has(p)) p.scrollIntoView({ block: 'nearest' }); // سایه‌ها جلوی اسکرول را می‌گیرند؛ منوی تازه‌باز خودش دیده شود
        l = Math.min(l, q.left); t = Math.min(t, q.top); r = Math.max(r, q.right); btm = Math.max(btm, q.bottom);
      });
      shown = open;
      const x1 = Math.max(0, l - pad), y1 = Math.max(0, t - pad), x2 = Math.min(vw, r + pad), y2 = Math.min(vh, btm + pad);
      set(top, 0, 0, vw, y1);
      set(bottom, 0, y2, vw, vh - y2);
      set(left, 0, y1, x1, y2 - y1);
      set(right, x2, y1, vw - x2, y2 - y1);
      set(ring, x1, y1, x2 - x1, y2 - y1);

      // جای پنجره‌ی توضیح: سمت چپ بخش (سایدبار سمت راست است)، بعد راست، پایین، بالا
      const pw = pop.offsetWidth, ph = pop.offsetHeight, gap = 16, m = 12;
      const cx = (x1 + x2) / 2, cy = (y1 + y2) / 2;
      let side, px, py;
      if (x1 - gap - pw >= m) { side = 'left'; px = x1 - gap - pw; py = cy - ph / 2; }
      else if (vw - x2 - gap - pw >= m) { side = 'right'; px = x2 + gap; py = cy - ph / 2; }
      else if (vh - y2 - gap - ph >= m) { side = 'bottom'; py = y2 + gap; px = cx - pw / 2; }
      else { side = 'top'; py = y1 - gap - ph; px = cx - pw / 2; }
      px = Math.max(m, Math.min(px, vw - pw - m));
      py = Math.max(m, Math.min(py, vh - ph - m));
      pop.style.left = px + 'px';
      pop.style.top = py + 'px';
      pop.dataset.side = side;
      if (side === 'left' || side === 'right') { arrow.style.top = Math.max(14, Math.min(cy - py, ph - 14)) + 'px'; arrow.style.left = ''; }
      else { arrow.style.left = Math.max(14, Math.min(cx - px, pw - 14)) + 'px'; arrow.style.top = ''; }
      raf = requestAnimationFrame(frame);
    };
    cancelAnimationFrame(raf);
    frame();
  };

  if (boot.state === null) { ask(); return; }
  const id = Number(boot.state);
  const step = STEPS[id];
  if (!step || step.page !== boot.page) return; // مرحله‌ی صفحه‌ی دیگر: وقتی کاربر به آن صفحه برود ادامه پیدا می‌کند
  if (step.when && !step.when()) { cur = id; build(); advance(); return; }
  run(step.back && !find(step.target) ? step.back : id); // بعد از رفرش، پنجره‌ی فرم بسته است → از دکمه‌ی تسک جدید
})();
