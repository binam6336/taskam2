/* تور آموزشی پنل: پرسش اولیه، سپس مرحله‌به‌مرحله با تار شدن صفحه و اشاره به هر بخش.
   وضعیت در users.tour ذخیره می‌شود (NULL = پرسیده نشده، skip، done یا شماره‌ی مرحله) تا بین صفحه‌ها ادامه پیدا کند. */
(() => {
  'use strict';
  const boot = TK.boot().tour;
  if (!boot) return;
  const mobile = window.matchMedia('(max-width: 960px)');
  const sidebarOpen = () => TK.qs('.sidebar')?.classList.contains('open');
  const tasksGroup = () => TK.qs('#nav-g-tasks')?.closest('[data-nav-group]');

  /* مرحله‌ها با شماره‌ی ثابت (همان که ذخیره می‌شود).
     page: صفحه‌ای که مرحله در آن اجرا می‌شود | when: شرط نمایش | target: بخشی که به آن اشاره می‌شود
     action: click = با کلیک کاربر روی بخش جلو می‌رود، go = لینک بخش باز می‌شود و تور در صفحه‌ی بعد ادامه دارد، ok = دکمه‌ی «فهمیدم» */
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
  };
  const ids = Object.keys(STEPS).map(Number).sort((a, b) => a - b);
  const active = () => ids.filter((i) => !STEPS[i].when || STEPS[i].when());

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

  /* ───── اجرای مرحله‌ها ───── */
  let ui = null, raf = 0, cur = null;

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
    ui?.remove();
    ui = cur = null;
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
    let n = step.next;
    while (n && STEPS[n] && STEPS[n].when && !STEPS[n].when()) n = STEPS[n].next;
    if (!n) { finish(); save('done'); return; }
    if (!STEPS[n] || STEPS[n].page !== boot.page) { finish(); save(String(n)); return; }
    run(n);
  };

  const onClick = (e) => {
    if (cur === null) return;
    const step = STEPS[cur];
    const t = e.target.closest?.(step.target);
    if (!t || step.action === 'ok') return;
    if (step.action === 'go') {
      e.preventDefault();
      e.stopPropagation();
      const href = t.href;
      finish();
      save(String(step.next)).then(() => { location.href = href; });
      return;
    }
    cur = null; // جلوگیری از دو بار رفتن
    setTimeout(() => { cur = Number(step.id); advance(); }, 380); // تا انیمیشن باز شدن منو تمام شود
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
    // صبر تا بخش موردنظر ظاهر شود (باز شدن منو)؛ اگر پیدا نشد، مرحله رد می‌شود
    const t0 = performance.now();
    const wait = () => {
      const el = TK.qs(step.target);
      const r = el?.getBoundingClientRect();
      if (!r || r.width === 0 || r.height === 0) {
        if (performance.now() - t0 > 1800) { cur = id; advance(); return; }
        raf = requestAnimationFrame(wait);
        return;
      }
      el.scrollIntoView({ block: 'nearest' });
      cur = id;
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
    const set = (n, x, y, w, h) => Object.assign(n.style, { left: x + 'px', top: y + 'px', width: Math.max(0, w) + 'px', height: Math.max(0, h) + 'px' });
    const frame = () => {
      if (!document.contains(el)) { el = TK.qs(STEPS[cur]?.target || '#_'); if (!el) return; }
      const vw = window.innerWidth, vh = window.innerHeight, pad = 6;
      const b = el.getBoundingClientRect();
      const x1 = Math.max(0, b.left - pad), y1 = Math.max(0, b.top - pad), x2 = Math.min(vw, b.right + pad), y2 = Math.min(vh, b.bottom + pad);
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
  run(id);
})();
