/* تور آموزشی پنل: پرسش اولیه، سپس مرحله‌به‌مرحله با تار شدن صفحه و اشاره به هر بخش.
   وضعیت در users.tour ذخیره می‌شود (NULL = پرسیده نشده، skip، done یا شماره‌ی مرحله) تا بین صفحه‌ها ادامه پیدا کند. */
(() => {
  'use strict';
  const boot = TK.boot().tour;
  if (!boot) return;
  const T = boot.texts || {}; // متن‌ها از «محتوا ← تور آموزشی» در پنل ادمین (config/tour_texts.php)
  const mobile = window.matchMedia('(max-width: 960px)');
  const sidebarOpen = () => TK.qs('.sidebar')?.classList.contains('open');
  // جای «تسک‌ها» در سایدبار را ادمین تعیین می‌کند (محتوا ← سایدبار): داخل هر گروهی یا بیرون از گروه‌ها
  const TASKS_LINK = '.sidebar .nav a[href$="/tasks"]';
  const tasksGroup = () => TK.qs(TASKS_LINK)?.closest('[data-nav-group]');
  const hasLabels = () => !!TK.qs('#f-task [name=subject_id] option:not([value=""]):not([value="__new"])');
  const field = (name, text, extra = {}) => ({ page: 'tasks', target: `#f-task [data-tour="${name}"]`, action: 'ok', back: 4, text, ...extra });

  /* مرحله‌ها با شماره‌ی ثابت (همان که ذخیره می‌شود).
     page: صفحه‌ای که مرحله در آن اجرا می‌شود | when: شرط نمایش | target: بخشی که به آن اشاره می‌شود (اولین مورد نمایان)
     action: click = با کلیک کاربر روی بخش جلو می‌رود، go = لینک بخش باز می‌شود و تور در صفحه‌ی بعد ادامه دارد،
             ok = دکمه‌ی «فهمیدم» (کاربر می‌تواند همان بخش را پر کند)، submit = بعد از ذخیره‌ی موفق فرم جلو می‌رود،
             wait = وقتی شرط until برقرار شد جلو می‌رود (hint: راهنمای زیر متن)
     back: اگر بخش پنهان شد (مثلاً پنجره بسته شد) به این مرحله برمی‌گردد */
  const STEPS = {
    1: {
      page: 'dashboard', when: () => mobile.matches, target: '.menu-btn', action: 'click', next: 2,
      text: T.step_1,
    },
    2: {
      page: 'dashboard', when: () => !!tasksGroup(), action: 'click', next: 3,
      target: () => `.nav-parent[aria-controls="${tasksGroup()?.querySelector('.nav-flyout')?.id}"]`,
      prepare: () => { if (mobile.matches && !sidebarOpen()) TK.qs('.menu-btn')?.click(); },
      text: T.step_2,
    },
    3: {
      page: 'dashboard', target: TASKS_LINK, action: 'go', next: 4,
      prepare: () => {
        if (mobile.matches && !sidebarOpen()) TK.qs('.menu-btn')?.click();
        const g = tasksGroup();
        if (g && !g.classList.contains('open')) g.querySelector('.nav-parent').click();
      },
      text: T.step_3,
    },
    4: {
      page: 'tasks', target: '#btn-new-task', action: 'click', next: 5,
      text: T.step_4,
    },
    5: field('title', T.step_5, { next: 6 }),
    6: field('desc', T.step_6, { next: 7 }),
    7: field('project', T.step_7, { next: 8 }),
    8: field('assignee', T.step_8, { next: 9 }),
    9: field('priority', T.step_9, { next: 10 }),
    // بدون لیبل: کلیک روی لیبل → مرحله‌ی ساخت اولین لیبل؛ با لیبل موجود: انتخاب از لیست و «فهمیدم»
    10: field('label', T.step_10, {
      action: () => (hasLabels() ? 'ok' : 'click'), next: () => (hasLabels() ? 12 : 11),
    }),
    11: {
      page: 'tasks', target: '#subj-pick .subj-new', action: 'wait', back: 10, next: 12,
      text: T.step_11, hint: T.hint_label,
      // باز کردن بخش «افزودن لیبل جدید» (بستن لیست بازشده‌ی انتخاب لیبل)
      prepare: () => {
        const sel = TK.qs('#f-task [name=subject_id]');
        if (!sel || sel.classList.contains('hidden')) return;
        sel.blur();
        sel.value = '__new';
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      },
      until: () => { const v = TK.qs('#f-task [name=subject_id]')?.value; return !!v && v !== '__new'; }, // لیبل ساخته و انتخاب شد
    },
    12: field('due', T.step_12, { next: 13 }),
    13: field('time', T.step_13, { next: 14 }),
    14: field('files', T.step_14, { next: 15 }),
    15: field('save', T.step_15, {
      action: 'submit', next: 16, celebrate: T.task_created,
      // فیلد اجباری خالی → برگشت به مرحله‌ی همان فیلد و بعد از آن دوباره همین‌جا
      check: () => {
        const f = TK.qs('#f-task');
        if (!f.elements.title.value.trim()) return 5;
        if (!f.elements.project_id.value) return 7;
        const subj = f.elements.subject_id;
        if (!subj.value || subj.value === '__new') return hasLabels() ? 10 : 11; // بدون لیبل → مستقیم ساخت لیبل
        return 0;
      },
    }),
  };
  const sel = (step) => (typeof step.target === 'function' ? step.target() : step.target);
  const act = (step) => (typeof step.action === 'function' ? step.action() : step.action);
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
        <h3 id="tour-ask-t">${TK.esc(T.ask_title)}</h3>
        <p>${TK.esc(T.ask_question)}</p>
        <div class="tour-ask-btns">
          <button type="button" class="btn btn-primary" data-tour-learn><i class="fa-solid fa-route"></i> ${TK.esc(T.ask_learn)}</button>
          <button type="button" class="btn" data-tour-skip>${TK.esc(T.ask_skip)}</button>
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
    document.addEventListener('mousedown', noNative, true);
  };

  const finish = () => {
    cancelAnimationFrame(raf);
    document.removeEventListener('click', onClick, true);
    document.removeEventListener('mousedown', noNative, true);
    form?.removeEventListener('tk:done', onSaved);
    ui?.remove();
    ui = cur = form = null;
  };

  const exit = async () => {
    const id = cur;
    if (!(await TK.confirm(T.exit_confirm))) { if (id) run(id); return; } // کلیک پنجره‌ی تأیید ممکن است منو را بسته باشد
    finish();
    save('skip');
  };

  // رفتن به مرحله‌ی بعد؛ اگر در همین صفحه نباشد، ذخیره می‌شود تا در صفحه‌ی خودش ادامه پیدا کند
  const advance = () => {
    const step = STEPS[cur];
    if (resume && ['ok', 'wait'].includes(act(step))) { const r = resume; resume = null; run(r); return; }
    let n = typeof step.next === 'function' ? step.next() : step.next;
    while (n && STEPS[n] && STEPS[n].when && !STEPS[n].when()) n = STEPS[n].next;
    if (!n) { finish(); save('done'); return; }
    if (!STEPS[n] || STEPS[n].page !== boot.page) { finish(); save(String(n)); return; }
    run(n);
  };

  // ذخیره‌ی موفق فرم در مرحله‌ی submit: تیک انیمیشنی و آماده‌ی مرحله‌ی بعد؛ خطا (مثلاً لیبل انتخاب نشده) = ماندن در همین مرحله
  const onSaved = (e) => {
    if (cur === null || act(STEPS[cur]) !== 'submit' || !TK.ok(e.detail)) return;
    const step = STEPS[cur];
    if (step.celebrate) celebrate(step.celebrate);
    advance();
  };

  // در مرحله‌ی «کلیک کن» روی یک select، لیست خود مرورگر باز نشود (مرحله‌ی بعد جای آن را می‌گیرد)
  const noNative = (e) => {
    if (cur !== null && act(STEPS[cur]) === 'click' && e.target.closest?.('select') && e.target.closest(sel(STEPS[cur]))) e.preventDefault();
  };

  const onClick = (e) => {
    if (cur === null) return;
    const step = STEPS[cur];
    const t = e.target.closest?.(sel(step));
    const action = act(step);
    if (!t || action === 'ok' || action === 'wait') return;
    if (action === 'submit') {
      const miss = step.check?.();
      if (miss) {
        e.preventDefault();
        e.stopPropagation();
        TK.toast(T.fill_first, 'warning');
        resume = step.id;
        run(miss);
      }
      return;
    }
    if (action === 'go') {
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
    const hint = act(step) === 'ok'
      ? '<button type="button" class="btn btn-primary btn-sm" data-tour-ok>' + TK.esc(T.ok) + '</button>'
      : `<span class="tour-hint"><i class="fa-solid fa-hand-pointer"></i> ${TK.esc(step.hint || T.hint_click)}</span>`;
    ui.querySelector('.tour-body').innerHTML = `<div class="tour-step">${TK.esc(T.counter.replace('{n}', TK.fa(pos)).replace('{total}', TK.fa(list.length)))}</div>
      <p>${TK.esc(step.text)}</p>
      <div class="tour-foot">${hint}<button type="button" class="tour-exit" data-tour-exit>${TK.esc(T.exit)}</button></div>`;
    const pop = ui.querySelector('.tour-pop');
    pop.classList.remove('in');
    // صبر تا بخش موردنظر ظاهر شود (باز شدن منو/پنجره)؛ اگر پیدا نشد، به مرحله‌ی برگشت یا بعدی می‌رود
    const t0 = performance.now();
    const wait = () => {
      const el = find(sel(step));
      if (!el) {
        if (performance.now() - t0 > 1800) { if (step.back) run(step.back); else { cur = id; advance(); } return; }
        raf = requestAnimationFrame(wait);
        return;
      }
      el.scrollIntoView({ block: 'nearest' });
      cur = id;
      if (act(step) === 'submit') { form = el.closest('form'); form?.addEventListener('tk:done', onSaved); }
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
      if (cur !== null && STEPS[cur].until?.()) { advance(); return; }
      if (cur !== null && !visible(el)) {
        const step = STEPS[cur];
        const again = find(sel(step));
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
  run(step.back && !find(sel(step)) ? step.back : id); // بعد از رفرش، پنجره‌ی فرم بسته است → از دکمه‌ی تسک جدید
})();
