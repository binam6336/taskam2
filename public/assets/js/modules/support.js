/* میز تیکت پشتیبانی: کادر ارسال (پیوست، Ctrl+Enter، پیش‌نویس)، متن‌های آماده، به‌روزرسانی زنده */
(() => {
  'use strict';
  const desk = TK.qs('#desk');
  if (!desk) return;
  const ls = { get: (k) => { try { return localStorage.getItem(k); } catch { return null; } }, set: (k, v) => { try { v ? localStorage.setItem(k, v) : localStorage.removeItem(k); } catch { /* */ } } };
  const msgs = () => TK.qs('#desk-msgs');
  const form = TK.qs('#desk-form');
  const text = TK.qs('#desk-text');
  const file = TK.qs('#desk-file');
  const chip = TK.qs('#desk-file-chip');
  const MAX = 10 * 1024 * 1024;

  /* ───── پیام‌ها: همیشه آخرین پیام در دید، مگر کاربر بالا رفته باشد ───── */
  let stick = true;
  const toBottom = (smooth) => { const m = msgs(); if (m) m.scrollTo({ top: m.scrollHeight, behavior: smooth ? 'smooth' : 'auto' }); };
  const box = msgs();
  if (box) {
    toBottom(false);
    // تصاویر بعد از بارگذاری ارتفاع را عوض می‌کنند
    box.addEventListener('load', () => { if (stick) toBottom(false); }, true);
    box.addEventListener('scroll', () => { stick = box.scrollHeight - box.scrollTop - box.clientHeight < 80; }, { passive: true });
  }
  document.addEventListener('tk:live', (e) => {
    if (e.detail.regions.includes('desk-msgs') && stick) toBottom(true);
  });

  /* ───── کادر ارسال ───── */
  const draftKey = form ? 'tk_desk_draft_' + form.getAttribute('action') : '';
  const grow = () => { if (!text) return; text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight + 2, 200) + 'px'; };
  const setFile = (f) => {
    if (!file || !chip) return;
    if (f && f.size > MAX) { TK.toast('حجم فایل بیشتر از ۱۰ مگابایت است.', 'error'); f = null; }
    if (f && file.files[0] !== f) { const dt = new DataTransfer(); dt.items.add(f); file.files = dt.files; }
    if (!f) file.value = '';
    chip.hidden = !f;
    TK.qs('#desk-file-name').textContent = f ? f.name : '';
  };
  if (form && text) {
    text.value = ls.get(draftKey) || '';
    grow();
    text.addEventListener('input', TK.debounce(() => ls.set(draftKey, text.value.trim() ? text.value : ''), 400));
    text.addEventListener('input', grow);
    text.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
    });
    // چسباندن تصویر از کلیپ‌بورد = پیوست
    text.addEventListener('paste', (e) => {
      const f = [...(e.clipboardData?.files || [])][0];
      if (f) { e.preventDefault(); setFile(f); }
    });
    file.addEventListener('change', () => setFile(file.files[0] || null));
    TK.qs('#desk-file-x')?.addEventListener('click', () => setFile(null));
    // کشیدن و رها کردن فایل روی گفتگو
    const main = desk.querySelector('.desk-main');
    let depth = 0;
    main.addEventListener('dragenter', (e) => { if ([...e.dataTransfer.types].includes('Files')) { depth++; main.classList.add('desk-drop'); } });
    main.addEventListener('dragleave', () => { if (--depth <= 0) { depth = 0; main.classList.remove('desk-drop'); } });
    main.addEventListener('dragover', (e) => { if ([...e.dataTransfer.types].includes('Files')) e.preventDefault(); });
    main.addEventListener('drop', (e) => {
      depth = 0; main.classList.remove('desk-drop');
      const f = e.dataTransfer.files[0];
      if (f) { e.preventDefault(); setFile(f); text.focus(); }
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!text.value.trim() && !file.files.length) { TK.toast('متن پیام را بنویسید یا فایلی پیوست کنید.', 'warning'); text.focus(); return; }
      const btn = form.querySelector('.desk-send');
      btn.disabled = true;
      const r = await TK.http(form.getAttribute('action'), { method: 'POST', form: new FormData(form) });
      btn.disabled = false;
      if (!TK.ok(r)) { TK.toast(r.message || 'ارسال نشد.', 'error'); return; }
      text.value = ''; ls.set(draftKey, ''); setFile(null); grow();
      stick = true;
      while (TK.live.busy) await new Promise((res) => setTimeout(res, 120));
      await TK.reload();
      toBottom(true);
      text.focus();
    });
  }

  // تیکت بازشده در فهرست دیده شود
  TK.qs('.tcard.active')?.scrollIntoView({ block: 'nearest' });

  /* ───── فهرست جمع‌شده (لپ‌تاپ/تبلت/مانیتور کم‌عرض): کشوی روی گفتگو ───── */
  const setList = (open) => {
    desk.classList.toggle('list-open', open);
    TK.qsa('[data-desk-list="toggle"]').forEach((b) => b.setAttribute('aria-expanded', open ? 'true' : 'false'));
    if (open) TK.qs('.tcard.active')?.scrollIntoView({ block: 'nearest' });
  };
  TK.on('click', '[data-desk-list]', (e, b) => setList(b.dataset.deskList === 'toggle' ? !desk.classList.contains('list-open') : false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && desk.classList.contains('list-open')) setList(false); });

  /* ───── تعداد در صفحه ───── */
  TK.qs('#desk-per')?.addEventListener('change', (e) => e.target.form.submit());

  /* ───── به‌روزرسانی دستی ───── */
  TK.on('click', '#desk-refresh', async (e, b) => {
    b.querySelector('i')?.classList.add('fa-spin');
    while (TK.live.busy) await new Promise((res) => setTimeout(res, 120));
    await TK.reload();
    setTimeout(() => TK.qs('#desk-refresh i')?.classList.remove('fa-spin'), 300);
  });

  /* ───── پاپ‌آپ‌ها: متن‌های آماده و راهنما ───── */
  const pops = { 'desk-texts-btn': '#desk-texts', 'desk-help-btn': '#desk-help' };
  const closePops = () => Object.values(pops).forEach((s) => { const p = TK.qs(s); if (p) p.hidden = true; });
  const texts = (TK.boot().support || {}).texts || [];
  const renderTexts = (q = '') => {
    const list = TK.qs('#desk-texts-list');
    if (!list) return;
    const k = q.trim();
    const rows = texts.filter((t) => !k || t.title.includes(k) || t.content.includes(k));
    list.innerHTML = rows.length
      ? rows.map((t) => `<button type="button" class="desk-pop-item" data-i="${texts.indexOf(t)}"><b>${TK.esc(t.title)}</b><small>${TK.esc(t.content.slice(0, 90))}</small></button>`).join('')
      : `<div class="faint small" style="padding:.5rem">${texts.length ? 'موردی پیدا نشد.' : 'هنوز متن آماده‌ای نساخته‌اید.'}</div>`;
  };
  document.addEventListener('click', (e) => {
    const b = e.target.closest('#desk-texts-btn, #desk-help-btn');
    if (b) {
      const p = TK.qs(pops[b.id]);
      const open = p.hidden;
      closePops();
      p.hidden = !open;
      if (open && b.id === 'desk-texts-btn') { renderTexts(); const q = TK.qs('#desk-texts-q'); q.value = ''; q.focus(); }
      return;
    }
    const item = e.target.closest('.desk-pop-item');
    if (item && text) {
      const t = texts[+item.dataset.i];
      const s = text.selectionStart ?? text.value.length, en = text.selectionEnd ?? s;
      text.value = text.value.slice(0, s) + t.content + text.value.slice(en);
      text.selectionStart = text.selectionEnd = s + t.content.length;
      text.dispatchEvent(new Event('input'));
      closePops();
      text.focus();
      return;
    }
    if (!e.target.closest('.desk-pop')) closePops();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closePops(); });
  TK.qs('#desk-texts-q')?.addEventListener('input', (e) => renderTexts(e.target.value));
})();
