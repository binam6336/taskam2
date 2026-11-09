/* پنجره‌ی «مسدودشده‌ها»: مسدود کردن به‌جای حذف (تسک، پروژه، لیبل).
   دکمه‌ی [data-blocked-list="tasks|projects|labels"] فهرست را از GET /blocked/{kind} می‌گیرد؛ «رفع مسدودی» = POST /unblock/{kind}/{id}. */
document.addEventListener('tk:ready', () => {
  const m = TK.qs('#m-blocked'); if (!m) return;
  const list = TK.qs('#blk-list'), title = TK.qs('#blk-title'), E = TK.esc;
  const ICON = { tasks: 'fa-list-check', projects: 'fa-diagram-project', labels: 'fa-tag' };
  const EMPTY = { tasks: 'تسک مسدودشده‌ای ندارید.', projects: 'پروژه‌ی مسدودشده‌ای ندارید.', labels: 'لیبل مسدودشده‌ای ندارید.' };
  let kind = '', changed = false;

  const row = (it) => {
    const ico = it.color ? `<span class="blk-ico" data-css="background:${E(it.color)}"><i class="fa-solid fa-tag"></i></span>` : `<span class="blk-ico"><i class="fa-solid ${ICON[kind]}"></i></span>`;
    const name = kind === 'tasks' && it.token ? `<a href="${E(TK.url('/tasks?task=' + it.token))}" class="bold" data-blk-open>${E(it.title)}</a>` : `<span class="bold">${E(it.title)}</span>`;
    const by = it.blocked_by_name ? ` توسط ${E(it.blocked_by_name)}` : '';
    return `<div class="item blk-item" data-id="${E(it.id)}">${ico}<div class="grow min0"><div class="truncate">${name}</div>`
      + `<div class="xs muted">${it.sub ? E(it.sub) + ' • ' : ''}مسدود شده در ${E(it.blocked_at_fa)}${by}</div></div>`
      + (it.can_unblock ? `<button type="button" class="btn btn-sm" data-blk-unblock="${E(it.id)}"><i class="fa-solid fa-lock-open"></i> رفع مسدودی</button>` : '<span class="xs faint">بدون دسترسی رفع مسدودی</span>')
      + '</div>';
  };
  const load = async () => {
    list.innerHTML = '<div class="muted small p-2"><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری…</div>';
    const r = await TK.get('/blocked/' + kind);
    if (!TK.ok(r)) { list.innerHTML = `<div class="text-danger small">${E(r.message || 'خطا در دریافت فهرست')}</div>`; return; }
    const items = r.items || r.data?.items || [];
    list.innerHTML = items.length ? items.map(row).join('') : `<div class="empty"><i class="fa-solid fa-ban"></i><p>${EMPTY[kind]}</p></div>`;
  };

  TK.on('click', '[data-blocked-list]', (e, b) => {
    kind = b.dataset.blockedList; changed = false;
    title.textContent = b.dataset.blockedTitle || 'مسدودشده‌ها';
    TK.modal.open('m-blocked'); load();
  });
  list.addEventListener('click', async (e) => {
    const open = e.target.closest('[data-blk-open]');
    if (open) { e.preventDefault(); TK.modal.close(m); TK.visit(open.getAttribute('href')); return; }
    const b = e.target.closest('[data-blk-unblock]'); if (!b) return;
    b.disabled = true;
    const r = await TK.post(`/unblock/${kind}/${b.dataset.blkUnblock}`);
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) { changed = true; b.closest('.blk-item').remove(); if (!TK.qs('.blk-item', list)) list.innerHTML = `<div class="empty"><i class="fa-solid fa-ban"></i><p>${EMPTY[kind]}</p></div>`; }
    else b.disabled = false;
  });
  // بعد از رفع مسدودی، صفحه (فهرست و شمارنده‌ها) تازه شود
  m.addEventListener('tk:close', () => { if (changed) { changed = false; TK.reload(); } });
});
