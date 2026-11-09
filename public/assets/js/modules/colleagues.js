document.addEventListener('tk:ready', () => {
  const E = TK.esc, res = TK.qs('#search-result');
  // «دعوت» نوار بالا: /colleagues?add=1 پنجره‌ی افزودن همکار را باز می‌کند
  if (new URLSearchParams(location.search).get('add') === '1' && TK.qs('#m-add-col')) { TK.modal.open('m-add-col'); history.replaceState(history.state, '', location.pathname); }
  TK.qs('#f-search')?.addEventListener('submit', async (e) => {
    e.preventDefault(); res.innerHTML = '<div class="muted center">در حال جستجو…</div>';
    const r = await TK.get('/colleagues/search', { mobile: e.target.mobile.value });
    if (!TK.ok(r)) { res.innerHTML = `<div class="alert error">${E(r.message)}</div>`; return; }
    const u = r.found; let act = '';
    if (u.already_added) act = '<span class="badge green">در لیست شما هست</span>';
    else if (u.is_blocked_by_me) act = '<span class="badge red">مسدود شده توسط شما</span>';
    else if (u.has_blocked_me) act = '<span class="badge red">شما را مسدود کرده</span>';
    else if (u.has_pending_request) act = '<span class="badge yellow">درخواست ارسال شده</span>';
    else if (!u.accepts_requests) act = '<span class="badge yellow">درخواست همکاری نمی‌پذیرد</span>';
    else act = `<button class="btn btn-primary btn-sm" data-add="${u.id}"><i class="fa-solid fa-plus"></i> افزودن</button>`;
    res.innerHTML = `<div class="item"><span class="avatar">${u.avatar_url ? `<img loading="lazy" decoding="async" src="${E(u.avatar_url)}" alt="">` : E(TK.initial(u.name))}</span><div class="grow"><b>${E(u.name)}</b><div class="xs muted ltr">${E(u.mobile)}</div></div>${act}</div>`;
  });
  res?.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-add]'); if (!b) return;
    TK.busy(b, true); const r = await TK.post('/colleagues', { colleague_user_id: b.dataset.add });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { TK.modal.close('m-add-col'); TK.qs('#search-result').innerHTML = ''; TK.reload(); } else TK.busy(b, false);
  });

  /* «افزودن به پروژه» روی کارت همکار: پروژه‌هایی که کاربر اجازه‌ی افزودن عضو دارد */
  const projs = (TK.boot().colleagues || {}).projects || [];
  let apUser = 0;
  const apDraw = () => {
    const list = TK.qs('#ap-list');
    list.innerHTML = projs.map((p) => {
      const st = p.members.includes(apUser) ? '<span class="badge green">عضو است</span>'
        : p.pending.includes(apUser) ? '<span class="badge yellow">در انتظار تأیید</span>'
        : p.full ? '<span class="badge red">ظرفیت تکمیل</span>'
        : `<button type="button" class="btn btn-sm btn-primary" data-ap-add="${p.id}"><i class="fa-solid fa-user-plus"></i> عضو کن</button>`;
      return `<div class="item"><span class="avatar sm sq">${p.image ? `<img loading="lazy" decoding="async" src="${E(p.image)}" alt="">` : E(TK.initial(p.title))}</span><div class="grow bold">${E(p.title)}</div>${st}</div>`;
    }).join('') || '<div class="empty small"><i class="fa-solid fa-diagram-project"></i><p>پروژه‌ای ندارید که در آن اجازه‌ی افزودن عضو داشته باشید.</p></div>';
  };
  TK.on('click', '[data-add-to-project]', (e, b) => {
    apUser = b.dataset.addToProject;
    TK.qs('#ap-name').textContent = '«' + (b.dataset.name || '') + '»';
    apDraw(); TK.modal.open('m-add-proj');
  });
  TK.on('click', '[data-ap-add]', async (e, b) => {
    const pid = b.dataset.apAdd;
    TK.busy(b, true);
    const r = await TK.post('/projects/' + pid + '/members/add', { user_id: apUser });
    TK.busy(b, false);
    TK.toast(r.message || 'خطا', TK.ok(r) ? 'success' : 'error');
    if (!TK.ok(r)) return;
    const p = projs.find((x) => x.id === pid);
    if (p) (r.state === 'requested' ? p.pending : p.members).push(apUser);
    apDraw();
  });
});
