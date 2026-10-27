document.addEventListener('tk:ready', () => {
  /* فیلتر جدول کاربران */
  const flt = TK.qs('#user-filter');
  const applyFilter = () => { const q = flt.value.trim().toLowerCase(); TK.qsa('#users-table tbody tr').forEach((r) => (r.style.display = !q || r.dataset.search.includes(q) ? '' : 'none')); };
  flt?.addEventListener('input', applyFilter);
  document.addEventListener('tk:live', () => { if (flt) applyFilter(); });

  /* فرم کاربر */
  const f = TK.qs('#f-user');
  if (f) {
    const role = TK.qs('#role-select'), perm = TK.qs('#perm-field');
    const sync = () => perm?.classList.toggle('hidden', role?.value !== 'admin');
    role?.addEventListener('change', sync);
    TK.qs('#btn-new-user')?.addEventListener('click', () => { TK.resetForm(f); f.setAttribute('action', TK.url('/admin/users')); TK.qs('#user-modal-title').textContent = 'کاربر جدید'; TK.qs('#pw-hint').textContent = '(الزامی)'; f.password.required = true; TK.qs('#status-field').classList.remove('hidden'); if (role) role.value = 'user'; sync(); TK.modal.open('m-user'); });
    TK.on('click', '[data-edit-user]', (e, t) => {
      const d = JSON.parse(t.dataset.editUser); TK.resetForm(f); TK.fillForm(f, d); f.setAttribute('action', TK.url('/admin/users/' + t.dataset.uid + '/update'));
      TK.qs('#user-modal-title').textContent = 'ویرایش کاربر'; TK.qs('#pw-hint').textContent = '(خالی = بدون تغییر)'; f.password.required = false; TK.qs('#status-field').classList.add('hidden'); sync(); TK.modal.open('m-user');
    });
  }

  /* نمودارهای تحلیل */
  const an = TK.boot().an;
  if (an) {
    TK.charts.line(TK.qs('#ch-days'), { labels: an.days.labels, series: [{ name: 'تسک جدید', data: an.days.tasks }, { name: 'کاربر جدید', data: an.days.users, color: '#60a5fa' }] });
    TK.charts.donut(TK.qs('#ch-tasks'), { items: [{ label: 'انجام‌شده', value: an.tasks.done, color: '#4ade80' }, { label: 'باز', value: an.tasks.open, color: '#fbbf24' }], center: TK.fa(an.tasks.total) });
    TK.charts.bars(TK.qs('#ch-subj'), { height: 200, items: an.subjects.map((s) => ({ label: s.title, value: +s.cnt })) });
  }
});
