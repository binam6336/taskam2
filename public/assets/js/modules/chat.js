document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().chat; const root = TK.qs('#chat'); if (!cfg || !root) return;
  const E = TK.esc, ME = cfg.me, API = '/chat/api/';
  const el = { contacts: TK.qs('#contacts'), msgs: TK.qs('#msgs'), conv: TK.qs('#chat-conv'), empty: TK.qs('#chat-empty'), text: TK.qs('#chat-text'), name: TK.qs('#ch-name'), status: TK.qs('#ch-status'), avatar: TK.qs('#ch-avatar') };
  let contacts = [], cur = null, lastId = 0, firstId = 0, hasOlder = false, loading = false, filter = '';
  const msgData = new Map();   // شناسه‌ی پیام → داده (برای منوی راست‌کلیک)
  // مهلت ۱ ساعته‌ی حذف/ویرایش؛ ts زمان مطلق سرور است. اختلاف ساعت دستگاه با سرور هنگام بارگذاری جبران می‌شود
  const skew = cfg.now ? Date.now() / 1000 - cfg.now : 0;
  const inWindow = (m) => (m.ts ? Date.now() / 1000 - skew - m.ts : (Date.now() - new Date(m.created_at.replace(' ', 'T')).getTime()) / 1000) < 3600;
  const dayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'long' });
  const dayLabel = (s) => { const [y, m, d] = s.slice(0, 10).split('-').map(Number); return dayFmt.format(new Date(y, m - 1, d)); };

  /* لینک‌های [[task:..]] و [[call:..]] */
  const body = (m) => {
    let t = E(m.message || ''); const links = [];
    t = t.replace(/\[\[(task|call):([a-f0-9]{16,64})\]\]/gi, (_, k, tok) => { links.push(`<a class="btn btn-sm btn-primary msg-link" href="${TK.url(k === 'task' ? '/tasks?task=' + tok : '/calls?call=' + tok)}"><i class="fa-solid fa-arrow-up-right-from-square"></i> ${k === 'task' ? 'مشاهده‌ی تسک' : 'مشاهده‌ی درخواست'}</a>`); return ''; });
    let att = '';
    if (m.attachment) {
      const url = TK.url('/files/chat_files/' + encodeURIComponent(m.attachment));
      att = /^(png|jpe?g)$/i.test(m.attachment_type || '') ? `<button type="button" class="msg-img" data-view-img="${url}" data-name="${E(m.attachment_name || 'image')}" title="نمایش تصویر"><img src="${url}" alt="" loading="lazy"></button>` : `<a class="file-chip" href="${url}?dl=1"><i class="fa-solid fa-file"></i>${E(m.attachment_name || 'فایل')}</a>`;
    }
    const fwd = m.forwarded_from ? `<div class="msg-fwd"><i class="fa-solid fa-share"></i> بازارسال‌شده از ${E(m.forwarded_name || '')}</div>` : '';
    return fwd + att + (t.trim() ? `<div class="txt">${t.trim()}</div>` : '') + links.join('');
  };
  const msgHtml = (m) => {
    const mine = m.sender_id === ME;
    msgData.set(m.id, m);
    return `<div class="msg ${mine ? 'mine' : ''}" data-id="${m.id}">
      <button type="button" class="msg-more" data-msg-menu="${m.id}" aria-label="گزینه‌های پیام"><i class="fa-solid fa-ellipsis-vertical"></i></button>
      ${body(m)}<div class="meta">${m.is_edited ? '<span>ویرایش‌شده</span>' : ''}<span>${TK.fa(m.created_at.slice(11, 16))}</span>${mine && m.sender_id !== m.receiver_id ? `<i class="fa-solid ${m.is_read ? 'fa-check-double' : 'fa-check'}" style="color:${m.is_read ? 'var(--g-400)' : 'inherit'}"></i>` : ''}</div></div>`;
  };
  const withDays = (list, prevDay) => { let out = '', d = prevDay; list.forEach((m) => { const day = m.created_at.slice(0, 10); if (day !== d) { out += `<div class="day-sep" data-day="${day}">${E(dayLabel(day))}</div>`; d = day; } out += msgHtml(m); }); return out; };
  const atBottom = () => el.msgs.scrollHeight - el.msgs.scrollTop - el.msgs.clientHeight < 80;
  const toBottom = () => (el.msgs.scrollTop = el.msgs.scrollHeight);

  /* مخاطبان */
  const timeShort = (s) => (s ? TK.fa(s.slice(11, 16)) : '');
  const drawContacts = () => {
    const q = filter.trim().toLowerCase();
    const saved = { user_id: ME, first_name: 'پیام‌های ذخیره‌شده', last_name: '', mobile: '', saved: true, unread_count: 0 };
    const rows = [saved, ...contacts].filter((c) => !q || (c.first_name + ' ' + c.last_name + ' ' + c.mobile).toLowerCase().includes(q));
    el.contacts.innerHTML = rows.map((c) => {
      const nm = c.saved ? c.first_name : (c.first_name + ' ' + c.last_name).trim() || c.mobile;
      return `<div class="contact ${cur && cur.user_id === c.user_id ? 'active' : ''}" data-uid="${c.user_id}"><span class="avatar">${c.saved ? '<i class="fa-regular fa-bookmark"></i>' : c.is_bot ? '<i class="fa-solid fa-robot"></i>' : c.avatar_url ? `<img src="${E(c.avatar_url)}" alt="">` : E(TK.initial(nm))}${!c.saved && !c.is_bot ? `<span class="dot ${c.online ? 'on' : ''}"></span>` : ''}</span>
        <div class="grow"><div class="flex between"><b class="truncate">${E(nm)}</b><span class="xs faint">${timeShort(c.last_msg_time)}</span></div>
        <div class="flex between"><span class="xs muted">${c.is_bot ? 'ربات اطلاع‌رسانی' : c.is_blocked_by_me ? 'مسدود' : ''}</span>${c.unread_count ? `<span class="badge-count">${TK.fa(c.unread_count)}</span>` : ''}</div></div></div>`;
    }).join('') || '<div class="empty small">مخاطبی یافت نشد.</div>';
  };
  const loadContacts = async () => {
    const r = await TK.get(API + 'list'); if (r.status !== 'success') return;
    contacts = r.list;
    const ids = contacts.filter((c) => !c.is_bot).map((c) => c.user_id);
    if (ids.length) { const s = await TK.get(API + 'users_status', { user_ids: ids.join(',') }); if (s.status === 'success') contacts.forEach((c) => (c.online = !!s.users[c.user_id]?.is_online)); }
    drawContacts();
  };

  /* باز کردن گفتگو */
  const open = async (uid, target = 0) => {
    const c = uid === ME ? { user_id: ME, first_name: 'پیام‌های ذخیره‌شده', last_name: '', saved: true } : contacts.find((x) => x.user_id === uid);
    if (!c) return;
    cur = c; TK.state.chatUser = c.user_id; root.classList.add('in-conv'); el.empty.style.display = 'none'; el.conv.style.display = 'flex';
    const nm = c.saved ? c.first_name : (c.first_name + ' ' + c.last_name).trim() || c.mobile;
    el.name.textContent = nm; el.avatar.innerHTML = c.saved ? '<i class="fa-regular fa-bookmark"></i>' : c.is_bot ? '<i class="fa-solid fa-robot"></i>' : c.avatar_url ? `<img src="${E(c.avatar_url)}" alt="">` : E(TK.initial(nm));
    TK.qs('#chat-form').style.display = c.is_bot || c.is_blocked_by_me || c.has_blocked_me ? 'none' : 'flex';
    history.replaceState(null, '', TK.url('/chat?user=' + uid));
    el.msgs.innerHTML = '<div class="chat-empty muted">در حال بارگذاری…</div>'; lastId = firstId = 0;
    const r = await TK.get(API + 'fetch', target ? { user_id: uid, mode: 'around', target_id: target, limit: 40 } : { user_id: uid, limit: 30 });
    if (r.status !== 'success') { el.msgs.innerHTML = `<div class="chat-empty">${E(r.message || 'خطا')}</div>`; return; }
    hasOlder = r.has_more_before; draw(r.messages, true);
    if (target) { const m = TK.qs(`.msg[data-id="${target}"]`, el.msgs); m?.scrollIntoView({ block: 'center' }); m?.classList.add('hl'); } else toBottom();
    c.unread_count = 0; drawContacts(); statusLine(); el.text.focus();
  };
  const draw = (list, replace) => {
    if (!list.length) { if (replace) el.msgs.innerHTML = '<div class="chat-empty muted">هنوز پیامی نیست. اولین پیام را بفرستید 👋</div>'; return; }
    el.msgs.innerHTML = withDays(list, ''); lastId = list[list.length - 1].id; firstId = list[0].id;
  };
  const statusLine = async () => {
    if (!cur || cur.saved) { el.status.textContent = cur?.saved ? 'فقط برای شما قابل مشاهده است' : ''; return; }
    const r = await TK.get(API + 'user_status', { user_id: cur.user_id }); if (r.status !== 'success') return;
    el.status.innerHTML = r.is_bot ? 'ربات اطلاع‌رسانی' : r.is_online ? '<span style="color:var(--g-400)">آنلاین</span>' : E(r.last_seen_text || '');
  };

  /* بارگذاری قدیمی‌ترها با اسکرول */
  el.msgs.addEventListener('scroll', async () => {
    if (el.msgs.scrollTop > 60 || !hasOlder || loading || !cur) return; loading = true;
    const h = el.msgs.scrollHeight;
    const r = await TK.get(API + 'fetch', { user_id: cur.user_id, mode: 'older', before_id: firstId, limit: 30 });
    if (r.status === 'success' && r.messages.length) { hasOlder = r.has_more_before; firstId = r.messages[0].id; el.msgs.insertAdjacentHTML('afterbegin', withDays(r.messages, '')); el.msgs.scrollTop = el.msgs.scrollHeight - h; }
    loading = false;
  });

  /* ارسال */
  const send = async () => {
    const t = el.text.value.trim(), f = TK.qs('#chat-file').files[0]; if (!cur || (!t && !f)) return;
    el.text.value = ''; el.text.style.height = '';
    let r;
    if (f) { const fd = new FormData(); fd.append('receiver_id', cur.user_id); fd.append('message', t); fd.append('attachment', f); r = await TK.http(API + 'send_file', { method: 'POST', form: fd }); TK.qs('#chat-file').value = ''; }
    else r = await TK.post(API + 'send', { receiver_id: cur.user_id, message: t });
    if (r.status !== 'success') { TK.toast(r.message, 'error'); el.text.value = t; return; }
    if (el.msgs.querySelector('.chat-empty')) el.msgs.innerHTML = '';
    el.msgs.insertAdjacentHTML('beforeend', withDays([r.message], TK.qsa('.msg', el.msgs).length ? lastDay() : '')); lastId = r.message.id; toBottom(); loadContacts();
  };
  const lastDay = () => { const s = TK.qsa('.day-sep', el.msgs).pop(); return s ? s.dataset.day : ''; };
  TK.qs('#chat-form').addEventListener('submit', (e) => { e.preventDefault(); send(); });
  TK.qs('#chat-file').addEventListener('change', (e) => { if (e.target.files[0]) { TK.toast('فایل انتخاب شد: ' + e.target.files[0].name + ' — ارسال را بزنید', 'info', 2500); } });
  el.text.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
  el.text.addEventListener('input', () => { el.text.style.height = 'auto'; el.text.style.height = Math.min(el.text.scrollHeight, 140) + 'px'; });

  /* ذخیره/انصراف ویرایش (ویرایش از منوی پیام شروع می‌شود) */
  el.msgs.addEventListener('click', async (e) => {
    const sv = e.target.closest('[data-save]');
    if (sv) { const ta = sv.closest('.txt-edit').querySelector('textarea'); const r = await TK.post(API + 'edit', { message_id: sv.dataset.save, message: ta.value }); if (r.status === 'success') { const box = sv.closest('.msg'); sv.closest('.txt-edit').outerHTML = `<div class="txt">${E(r.message.message)}</div>`; const d = msgData.get(Number(sv.dataset.save)); if (d) { d.message = r.message.message; d.is_edited = 1; } if (!box.querySelector('.meta span')?.textContent.includes('ویرایش')) box.querySelector('.meta').insertAdjacentHTML('afterbegin', '<span>ویرایش‌شده</span>'); } else TK.toast(r.message, 'error'); }
    if (e.target.closest('[data-cancel]')) open(cur.user_id);
  });

  /* انتخاب مخاطب / بازگشت */
  el.contacts.addEventListener('click', (e) => { const c = e.target.closest('.contact'); if (c) open(Number(c.dataset.uid)); });
  TK.qs('#chat-back').addEventListener('click', () => { root.classList.remove('in-conv'); cur = null; TK.state.chatUser = null; drawContacts(); });

  /* جستجو */
  const searchAll = TK.debounce(async (q) => {
    filter = q; drawContacts(); if (q.trim().length < 2) return;
    const r = await TK.get(API + 'search', { q: q.trim(), scope: 'all', limit: 30 });
    if (r.status === 'success' && r.results.length) { TK.qs('#results-body').innerHTML = r.results.map((m) => `<div class="item" style="cursor:pointer" data-jump="${m.other_user_id}:${m.id}"><div class="grow"><b class="small">${E(m.from_name)}</b><div class="small muted truncate">${E((m.message || m.attachment_name || '').replace(/\[\[.*?\]\]/g, ''))}</div></div><span class="xs faint">${E(dayLabel(m.created_at))}</span></div>`).join(''); TK.modal.open('m-results'); }
  }, 450);
  TK.qs('#chat-search').addEventListener('input', (e) => searchAll(e.target.value));
  const searchConv = async (q) => {
    q = q.trim(); if (!cur) return;
    if (q.length < 2) return TK.toast('حداقل ۲ نویسه وارد کنید', 'warning');
    const r = await TK.get(API + 'search', { q, scope: 'conversation', user_id: cur.user_id });
    TK.qs('#results-body').innerHTML = r.status === 'success' && r.results.length ? r.results.map((m) => `<div class="item" style="cursor:pointer" data-jump="${m.other_user_id}:${m.id}"><div class="grow small">${E((m.message || m.attachment_name || '').replace(/\[\[.*?\]\]/g, ''))}</div><span class="xs faint">${E(dayLabel(m.created_at))}</span></div>`).join('') : '<div class="empty small">نتیجه‌ای پیدا نشد.</div>';
    TK.modal.close('m-conv-search'); TK.modal.open('m-results');
  };
  TK.qs('#conv-search').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); searchConv(e.target.value); } });
  // موبایل: آیکون ذره‌بین → پاپ‌آپ جستجو
  TK.qs('#conv-search-btn').addEventListener('click', () => { TK.modal.open('m-conv-search'); setTimeout(() => TK.qs('#conv-search-m').focus(), 60); });
  TK.qs('#f-conv-search').addEventListener('submit', (e) => { e.preventDefault(); searchConv(TK.qs('#conv-search-m').value); });
  TK.on('click', '[data-jump]', (e, t) => { const [u, id] = t.dataset.jump.split(':').map(Number); TK.modal.close('m-results'); open(u, id); });

  /* ───── منوی راست‌کلیک ───── */
  const ctx = document.createElement('div');
  ctx.className = 'ctx-menu'; ctx.hidden = true; ctx.setAttribute('role', 'menu');
  document.body.appendChild(ctx);
  const closeCtx = () => { ctx.hidden = true; };
  const showCtx = (x, y, items) => {
    ctx.innerHTML = items.map((it) => it === '-' ? '<div class="ctx-sep"></div>' : `<button type="button" role="menuitem" class="${it.danger ? 'danger' : ''}" ${it.disabled ? 'disabled' : ''} title="${E(it.hint || '')}"><i class="fa-solid ${it.icon}"></i><span>${E(it.label)}</span>${it.hint ? `<small>${E(it.hint)}</small>` : ''}</button>`).join('');
    const acts = items.filter((it) => it !== '-');
    TK.qsa('button', ctx).forEach((b, i) => b.addEventListener('click', () => { closeCtx(); acts[i].run(); }));
    ctx.hidden = false;
    const w = ctx.offsetWidth, h = ctx.offsetHeight;
    ctx.style.left = Math.max(8, Math.min(x - w, window.innerWidth - w - 8)) + 'px';     // RTL: منو سمت چپ نشانگر
    ctx.style.top = Math.max(8, Math.min(y, window.innerHeight - h - 8)) + 'px';
    ctx.querySelector('button:not([disabled])')?.focus();
  };
  document.addEventListener('click', (e) => { if (!ctx.contains(e.target)) closeCtx(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCtx(); });
  window.addEventListener('resize', closeCtx);
  el.msgs.addEventListener('scroll', closeCtx, { passive: true });

  const copyText = (m) => TK.copy(String(m.message || '').replace(/\[\[(task|call):[a-f0-9]+\]\]/gi, '').trim());
  const fileUrl = (m) => TK.url('/files/chat_files/' + encodeURIComponent(m.attachment));
  const download = (m) => { const a = document.createElement('a'); a.href = fileUrl(m) + '?dl=1'; a.download = m.attachment_name || ''; document.body.appendChild(a); a.click(); a.remove(); };
  const deleteMsg = async (id) => {
    const r = await TK.post(API + 'delete', { message_id: id });
    if (r.status === 'success') { TK.qs(`.msg[data-id="${id}"]`, el.msgs)?.remove(); msgData.delete(id); TK.toast('پیام برای هر دو طرف حذف شد', 'success', 2500); } else TK.toast(r.message, 'error');
  };
  const msgMenu = (m, x, y) => {
    const mine = m.sender_id === ME, hasText = String(m.message || '').replace(/\[\[.*?\]\]/g, '').trim() !== '';
    const items = [];
    if (hasText) items.push({ icon: 'fa-copy', label: 'کپی متن', run: () => copyText(m) });
    if (m.attachment) items.push({ icon: 'fa-download', label: 'دانلود فایل', run: () => download(m) });
    items.push({ icon: 'fa-share', label: 'بازارسال', run: () => openForward(m) });
    if (mine && !m.attachment && !m.forwarded_from && inWindow(m) && !cur?.is_bot) items.push({ icon: 'fa-pen', label: 'ویرایش', run: () => TK.qs(`.msg[data-id="${m.id}"] .txt`, el.msgs) && editStart(m.id) });
    if (mine) {
      const ok = inWindow(m);
      items.push('-', { icon: 'fa-trash', label: 'حذف پیام', danger: true, disabled: !ok, hint: ok ? '' : 'بیش از ۱ ساعت گذشته', run: () => deleteMsg(m.id) });
    }
    showCtx(x, y, items);
  };
  el.msgs.addEventListener('contextmenu', (e) => {
    const box = e.target.closest('.msg'); if (!box) return;
    const m = msgData.get(Number(box.dataset.id)); if (!m) return;
    e.preventDefault(); msgMenu(m, e.clientX, e.clientY);
  });
  el.msgs.addEventListener('click', (e) => {
    const b = e.target.closest('[data-msg-menu]'); if (!b) return;
    e.stopPropagation(); const r = b.getBoundingClientRect(); const m = msgData.get(Number(b.dataset.msgMenu)); if (m) msgMenu(m, r.left + r.width, r.bottom + 4);
  });
  // ویرایش از منو
  const editStart = (id) => {
    const box = TK.qs(`.msg[data-id="${id}"]`, el.msgs), txt = box?.querySelector('.txt'); if (!txt) return;
    const old = txt.textContent; txt.outerHTML = `<div class="txt-edit"><textarea class="input" rows="2">${E(old)}</textarea><div class="flex gap-1 mt-1"><button class="btn btn-xs btn-primary" data-save="${id}">ذخیره</button><button class="btn btn-xs" data-cancel>انصراف</button></div></div>`;
    box.querySelector('textarea').focus();
  };

  // منوی راست‌کلیک روی مخاطب: حذف تاریخچه (فقط برای خود کاربر)
  el.contacts.addEventListener('contextmenu', (e) => {
    const c = e.target.closest('.contact'); if (!c) return;
    e.preventDefault();
    const uid = Number(c.dataset.uid), name = c.querySelector('b')?.textContent || '';
    showCtx(e.clientX, e.clientY, [
      { icon: 'fa-comment-dots', label: 'باز کردن گفتگو', run: () => open(uid) },
      '-',
      { icon: 'fa-broom', label: 'حذف تاریخچه', danger: true, hint: 'فقط برای شما', run: async () => {
        if (!(await TK.confirm(`تاریخچه‌ی گفتگو با «${name}» فقط برای شما پاک شود؟ طرف مقابل پیام‌ها را همچنان می‌بیند.`))) return;
        const r = await TK.post(API + 'clear_history', { user_id: uid });
        TK.toast(r.message || 'خطا', r.status === 'success' ? 'success' : 'error');
        if (r.status === 'success') { if (cur && cur.user_id === uid) open(uid); loadContacts(); }
      } },
    ]);
  });

  /* ───── بازارسال ───── */
  let fwdMsg = null;
  const fwdList = TK.qs('#fwd-list'), fwdSearch = TK.qs('#fwd-search');
  const drawFwd = () => {
    const q = (fwdSearch.value || '').trim().toLowerCase();
    const rows = [{ user_id: ME, first_name: 'پیام‌های ذخیره‌شده', last_name: '', saved: true }, ...contacts.filter((c) => !c.is_bot && !c.is_blocked_by_me && !c.has_blocked_me)]
      .filter((c) => !q || (c.first_name + ' ' + c.last_name + ' ' + (c.mobile || '')).toLowerCase().includes(q));
    fwdList.innerHTML = rows.map((c) => { const nm = c.saved ? c.first_name : (c.first_name + ' ' + c.last_name).trim() || c.mobile; return `<button type="button" class="fwd-item" data-fwd-to="${c.user_id}"><span class="avatar sm">${c.saved ? '<i class="fa-regular fa-bookmark"></i>' : c.avatar_url ? `<img src="${E(c.avatar_url)}" alt="">` : E(TK.initial(nm))}</span><span class="grow truncate">${E(nm)}</span><i class="fa-solid fa-share faint"></i></button>`; }).join('') || '<div class="empty small">مخاطبی یافت نشد.</div>';
  };
  const openForward = (m) => { fwdMsg = m; fwdSearch.value = ''; drawFwd(); TK.modal.open('m-forward'); setTimeout(() => fwdSearch.focus(), 50); };
  fwdSearch?.addEventListener('input', drawFwd);
  fwdList?.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-fwd-to]'); if (!b || !fwdMsg) return;
    TK.busy(b, true);
    const to = Number(b.dataset.fwdTo), r = await TK.post(API + 'forward', { message_id: fwdMsg.id, receiver_id: to });
    TK.busy(b, false);
    if (r.status !== 'success') return TK.toast(r.message || 'خطا', 'error');
    TK.modal.close('m-forward'); TK.toast(r.info || 'پیام بازارسال شد', 'success', 2500);
    if (cur && cur.user_id === to) { if (el.msgs.querySelector('.chat-empty')) el.msgs.innerHTML = ''; el.msgs.insertAdjacentHTML('beforeend', withDays([r.message], lastDay())); lastId = r.message.id; toBottom(); }
    loadContacts();
  });

  /* ───── نمایشگر تصویر داخل پنل: زوم، جابه‌جایی و دانلود ───── */
  const vw = TK.qs('#img-viewer'), vImg = TK.qs('#iv-img'), vStage = TK.qs('#iv-stage'), vZoom = TK.qs('#iv-zoom');
  const iv = { s: 1, x: 0, y: 0, url: '', name: '', drag: null };
  const ivApply = () => { vImg.style.transform = `translate(${iv.x}px, ${iv.y}px) scale(${iv.s})`; vZoom.textContent = TK.fa(Math.round(iv.s * 100)) + '٪'; vStage.classList.toggle('zoomed', iv.s > 1); };
  const ivZoom = (f) => { iv.s = Math.min(6, Math.max(0.25, +(iv.s * f).toFixed(3))); if (iv.s <= 1) { iv.x = 0; iv.y = 0; } ivApply(); };
  const ivOpen = (url, name) => { Object.assign(iv, { s: 1, x: 0, y: 0, url, name }); vImg.src = url; TK.qs('#iv-name').textContent = name; ivApply(); vw.hidden = false; document.body.classList.add('iv-open'); TK.qs('#iv-close').focus(); };
  const ivClose = () => { vw.hidden = true; vImg.src = ''; document.body.classList.remove('iv-open'); };
  el.msgs.addEventListener('click', (e) => { const b = e.target.closest('[data-view-img]'); if (b) ivOpen(b.dataset.viewImg, b.dataset.name); });
  TK.qs('#iv-in').addEventListener('click', () => ivZoom(1.25));
  TK.qs('#iv-out').addEventListener('click', () => ivZoom(0.8));
  TK.qs('#iv-reset').addEventListener('click', () => { iv.s = 1; iv.x = 0; iv.y = 0; ivApply(); });
  TK.qs('#iv-dl').addEventListener('click', () => { const a = document.createElement('a'); a.href = iv.url + '?dl=1'; a.download = iv.name; document.body.appendChild(a); a.click(); a.remove(); });
  TK.qs('#iv-close').addEventListener('click', ivClose);
  vStage.addEventListener('click', (e) => { if (e.target === vStage && !iv.moved) ivClose(); });
  vStage.addEventListener('wheel', (e) => { e.preventDefault(); ivZoom(e.deltaY < 0 ? 1.15 : 0.87); }, { passive: false });
  vImg.addEventListener('dblclick', () => (iv.s > 1 ? (iv.s = 1, iv.x = 0, iv.y = 0, ivApply()) : ivZoom(2)));
  vImg.addEventListener('pointerdown', (e) => { if (iv.s <= 1) return; e.preventDefault(); iv.drag = { px: e.clientX, py: e.clientY, x: iv.x, y: iv.y }; iv.moved = false; vImg.setPointerCapture(e.pointerId); });
  vImg.addEventListener('pointermove', (e) => { if (!iv.drag) return; iv.x = iv.drag.x + e.clientX - iv.drag.px; iv.y = iv.drag.y + e.clientY - iv.drag.py; iv.moved = true; ivApply(); });
  vImg.addEventListener('pointerup', () => { iv.drag = null; setTimeout(() => (iv.moved = false), 0); });
  document.addEventListener('keydown', (e) => {
    if (vw.hidden) return;
    if (e.key === 'Escape') { e.stopPropagation(); ivClose(); }
    if (e.key === '+' || e.key === '=') ivZoom(1.25);
    if (e.key === '-') ivZoom(0.8);
    if (e.key === '0') { iv.s = 1; iv.x = 0; iv.y = 0; ivApply(); }
  }, true);

  /* polling */
  const poll = async () => {
    if (document.hidden) return;
    if (cur) {
      const r = await TK.get(API + 'fetch', { user_id: cur.user_id, mode: 'newer', after_id: lastId, limit: 50 });
      if (r.status === 'success' && r.messages.length) { const stick = atBottom(); if (el.msgs.querySelector('.chat-empty')) el.msgs.innerHTML = ''; el.msgs.insertAdjacentHTML('beforeend', withDays(r.messages, lastDay())); lastId = r.messages[r.messages.length - 1].id; if (stick) toBottom(); }
    }
  };
  setInterval(poll, 3000);
  setInterval(() => TK.get(API + 'heartbeat'), 12000);
  setInterval(() => { loadContacts(); statusLine(); }, 12000);

  loadContacts().then(() => { if (cfg.open) open(cfg.open); });
});
