document.addEventListener('tk:ready', () => {
  /* جستجوی کاربران (سمت سرور، همه‌ی صفحه‌ها): کمی بعد از توقف تایپ، فرم GET ارسال می‌شود */
  const tools = TK.qs('#users-tools'), qIn = tools?.querySelector('[name=q]');
  let qt = null;
  qIn?.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(() => (tools.requestSubmit ? tools.requestSubmit() : tools.submit()), 450); });

  /* فرم کاربر */
  const f = TK.qs('#f-user');
  if (f) {
    const role = TK.qs('#role-select'), perm = TK.qs('#perm-field');
    const sync = () => perm?.classList.toggle('hidden', !role || role.value === 'user');
    role?.addEventListener('change', sync);
    TK.qs('#btn-new-user')?.addEventListener('click', () => { TK.resetForm(f); f.setAttribute('action', TK.url('/admin/users')); TK.qs('#user-modal-title').textContent = 'کاربر جدید'; TK.qs('#pw-hint').textContent = '(الزامی)'; f.password.required = true; TK.qs('#status-field').classList.remove('hidden'); TK.qs('#reauth-field').classList.add('hidden'); if (role) role.value = 'user'; sync(); TK.modal.open('m-user'); });
    TK.on('click', '[data-edit-user]', (e, t) => {
      const d = JSON.parse(t.dataset.editUser); TK.resetForm(f); TK.fillForm(f, d); f.setAttribute('action', TK.url('/admin/users/' + t.dataset.uid + '/update'));
      TK.qs('#user-modal-title').textContent = 'ویرایش کاربر'; TK.qs('#pw-hint').textContent = '(خالی = بدون تغییر)'; f.password.required = false; TK.qs('#status-field').classList.add('hidden'); TK.qs('#reauth-field').classList.remove('hidden'); sync(); TK.modal.open('m-user');
    });
  }

  /* پنجره‌های باز ورود به پنل کاربر (همین نشست مدیر) */
  const ib = TK.qs('#imp-open');
  if (ib) {
    const E = TK.esc;
    const load = async () => {
      const r = await TK.get('/admin/impersonations'); if (!TK.ok(r)) return;
      ib.hidden = !r.items.length; TK.qs('#imp-open-n').textContent = TK.fa(r.items.length);
      TK.qs('#imp-open-list').innerHTML = r.items.map((x) => `<div class="item"><span class="avatar sm">${x.avatar ? `<img loading="lazy" decoding="async" src="${E(x.avatar)}" alt="">` : E(TK.initial(x.name))}</span>
        <div class="grow min0"><b>${E(x.name)}</b> <span class="ltr small muted">${E(x.mobile)}</span><div class="xs faint">شروع ${E(x.started_fa)} • آخرین فعالیت ${E(x.seen_fa)} • ${TK.fa(x.actions)} تغییر • پایان قطعی ${E(x.expires_fa)}</div></div>
        <button type="button" class="btn btn-sm" data-imp-end="${E(x.id)}"><i class="fa-solid fa-xmark"></i> بستن</button></div>`).join('');
    };
    load();
    document.addEventListener('tk:impersonated', load);
    setInterval(load, 30000);
    TK.on('click', '[data-imp-end]', async (e, b) => { const r = await TK.post('/admin/impersonations/' + b.dataset.impEnd + '/end'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); load(); });
    TK.on('click', '[data-imp-end-all]', async () => {
      if (!(await TK.confirm('همه‌ی پنجره‌های ورود به پنل کاربر شما (روی همه‌ی دستگاه‌ها) بسته شوند؟', { okText: 'بستن همه' }))) return;
      const r = await TK.post('/admin/impersonations/end-all'); TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); load();
    });
  }

  /* نمودارهای تحلیل */
  const an = TK.boot().an;
  if (an) {
    TK.charts.line(TK.qs('#ch-days'), { labels: an.days.labels, series: [{ name: 'تسک جدید', data: an.days.tasks }, { name: 'کاربر جدید', data: an.days.users, color: '#60a5fa' }] });
    TK.charts.donut(TK.qs('#ch-tasks'), { items: [{ label: 'انجام‌شده', value: an.tasks.done, color: TK.charts.colors[0] }, { label: 'باز', value: an.tasks.open, color: '#fbbf24' }], center: TK.fa(an.tasks.total) });
    TK.charts.bars(TK.qs('#ch-subj'), { height: 200, items: an.subjects.map((s) => ({ label: s.title, value: +s.cnt })) });
  }
});
