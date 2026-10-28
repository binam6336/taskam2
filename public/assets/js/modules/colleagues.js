document.addEventListener('tk:ready', () => {
  const E = TK.esc, res = TK.qs('#search-result');
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
    res.innerHTML = `<div class="item"><span class="avatar">${u.avatar_url ? `<img src="${E(u.avatar_url)}" alt="">` : E(TK.initial(u.name))}</span><div class="grow"><b>${E(u.name)}</b><div class="xs muted ltr">${E(u.mobile)}</div></div>${act}</div>`;
  });
  res?.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-add]'); if (!b) return;
    TK.busy(b, true); const r = await TK.post('/colleagues', { colleague_user_id: b.dataset.add });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { TK.modal.close('m-add-col'); TK.qs('#search-result').innerHTML = ''; TK.reload(); } else TK.busy(b, false);
  });
});
