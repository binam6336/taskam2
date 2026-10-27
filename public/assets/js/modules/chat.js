document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().chat; const root = TK.qs('#chat'); if (!cfg || !root) return;
  const E = TK.esc, ME = cfg.me, API = '/chat/api/';
  const el = { contacts: TK.qs('#contacts'), msgs: TK.qs('#msgs'), conv: TK.qs('#chat-conv'), empty: TK.qs('#chat-empty'), text: TK.qs('#chat-text'), name: TK.qs('#ch-name'), status: TK.qs('#ch-status'), avatar: TK.qs('#ch-avatar') };
  let contacts = [], cur = null, lastId = 0, firstId = 0, hasOlder = false, loading = false, filter = '';
  const dayFmt = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'long' });
  const dayLabel = (s) => { const [y, m, d] = s.slice(0, 10).split('-').map(Number); return dayFmt.format(new Date(y, m - 1, d)); };

  /* لینک‌های [[task:..]] و [[call:..]] */
  const body = (m) => {
    let t = E(m.message || ''); const links = [];
    t = t.replace(/\[\[(task|call):([a-f0-9]{16,64})\]\]/gi, (_, k, tok) => { links.push(`<a class="btn btn-sm btn-primary msg-link" href="${TK.url(k === 'task' ? '/tasks?task=' + tok : '/calls?call=' + tok)}"><i class="fa-solid fa-arrow-up-right-from-square"></i> ${k === 'task' ? 'مشاهده‌ی تسک' : 'مشاهده‌ی درخواست'}</a>`); return ''; });
    let att = '';
    if (m.attachment) {
      const url = TK.url('/files/chat_files/' + encodeURIComponent(m.attachment));
      att = /^(png|jpe?g)$/i.test(m.attachment_type || '') ? `<a href="${url}" target="_blank"><img src="${url}" alt="" style="max-width:240px;max-height:200px;border-radius:10px;display:block;margin-bottom:.3rem"></a>` : `<a class="file-chip" href="${url}?dl=1"><i class="fa-solid fa-file"></i>${E(m.attachment_name || 'فایل')}</a>`;
    }
    return att + (t.trim() ? `<div class="txt">${t.trim()}</div>` : '') + links.join('');
  };
  const msgHtml = (m) => {
    const mine = m.sender_id === ME, canEdit = mine && (Date.now() - new Date(m.created_at.replace(' ', 'T')).getTime()) < 3600e3 && (!cur?.is_bot);
    return `<div class="msg ${mine ? 'mine' : ''}" data-id="${m.id}">
      ${canEdit ? `<div class="dropdown menu"><button class="btn btn-xs btn-ghost" data-toggle><i class="fa-solid fa-ellipsis"></i></button><div class="dropdown-menu" style="min-width:140px">${m.attachment ? '' : `<button data-edit-msg="${m.id}"><i class="fa-solid fa-pen"></i> ویرایش</button>`}<button data-del-msg="${m.id}"><i class="fa-solid fa-trash"></i> حذف</button></div></div>` : ''}
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

  /* ویرایش / حذف */
  el.msgs.addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit-msg]'), del = e.target.closest('[data-del-msg]');
    if (ed) {
      const box = TK.qs(`.msg[data-id="${ed.dataset.editMsg}"]`), txt = box.querySelector('.txt'); if (!txt) return;
      const old = txt.textContent; txt.outerHTML = `<div class="txt-edit"><textarea class="input" rows="2">${E(old)}</textarea><div class="flex gap-1 mt-1"><button class="btn btn-xs btn-primary" data-save="${ed.dataset.editMsg}">ذخیره</button><button class="btn btn-xs" data-cancel>انصراف</button></div></div>`;
    }
    const sv = e.target.closest('[data-save]');
    if (sv) { const ta = sv.closest('.txt-edit').querySelector('textarea'); const r = await TK.post(API + 'edit', { message_id: sv.dataset.save, message: ta.value }); if (r.status === 'success') { const box = sv.closest('.msg'); sv.closest('.txt-edit').outerHTML = `<div class="txt">${E(r.message.message)}</div>`; if (!box.querySelector('.meta span')?.textContent.includes('ویرایش')) box.querySelector('.meta').insertAdjacentHTML('afterbegin', '<span>ویرایش‌شده</span>'); } else TK.toast(r.message, 'error'); }
    if (e.target.closest('[data-cancel]')) open(cur.user_id);
    if (del && await TK.confirm('پیام برای هر دو طرف حذف شود؟')) { const r = await TK.post(API + 'delete', { message_id: del.dataset.delMsg }); if (r.status === 'success') TK.qs(`.msg[data-id="${del.dataset.delMsg}"]`)?.remove(); else TK.toast(r.message, 'error'); }
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
  TK.qs('#conv-search').addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter' || !cur) return; e.preventDefault();
    const r = await TK.get(API + 'search', { q: e.target.value.trim(), scope: 'conversation', user_id: cur.user_id });
    TK.qs('#results-body').innerHTML = r.status === 'success' && r.results.length ? r.results.map((m) => `<div class="item" style="cursor:pointer" data-jump="${m.other_user_id}:${m.id}"><div class="grow small">${E((m.message || m.attachment_name || '').replace(/\[\[.*?\]\]/g, ''))}</div><span class="xs faint">${E(dayLabel(m.created_at))}</span></div>`).join('') : '<div class="empty small">نتیجه‌ای پیدا نشد.</div>';
    TK.modal.open('m-results');
  });
  TK.on('click', '[data-jump]', (e, t) => { const [u, id] = t.dataset.jump.split(':').map(Number); TK.modal.close('m-results'); open(u, id); });

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
