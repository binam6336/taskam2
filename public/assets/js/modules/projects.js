document.addEventListener('tk:ready', () => {
  if (!TK.qs('#f-project')) return;
  const data = () => { try { return JSON.parse(TK.qs('#proj-data').textContent); } catch { return { list: {}, colleagues: [], permDefs: {}, me: 0 }; } };
  const E = TK.esc, form = TK.qs('#f-project'), list = TK.qs('#members-list');

  /* لیست اعضا: سازنده و خودِ کاربر هرگز نمایش داده نمی‌شوند (همیشه عضوند) و نام اعضا از داده‌ی خود پروژه می‌آید */
  const memberChecks = (members = [], pendingIds = []) => {
    const hide = new Set([data().me, ...members.filter((m) => m.is_creator).map((m) => m.id)]);
    const names = new Map(data().colleagues.map((c) => [c.id, c.name]));
    members.forEach((m) => names.set(m.id, m.name));
    pendingIds.forEach((id) => { if (!names.has(id)) names.set(id, 'کاربر در انتظار تأیید'); });
    const selected = new Set(members.map((m) => m.id));
    const rows = [...names].filter(([id]) => !hide.has(id));
    const keep = members.filter((m) => hide.has(m.id)).map((m) => `<input type="hidden" name="members[]" value="${m.id}">`).join('');
    list.innerHTML = keep + (rows.map(([id, name]) => `<label class="check"><input type="checkbox" name="members[]" value="${id}" ${selected.has(id) || pendingIds.includes(id) ? 'checked' : ''}><div><b>${E(name)}</b>${pendingIds.includes(id) ? '<span>در انتظار تأیید کاربر</span>' : ''}</div></label>`).join('') || '<div class="muted small">همکار دیگری برای افزودن وجود ندارد. از بخش «همکاران» همکار اضافه کنید.</div>');
  };

  TK.qs('#btn-new-project').addEventListener('click', () => {
    TK.resetForm(form); form.setAttribute('action', TK.url('/projects')); form.max_members.value = 10; form.priority_type.value = 'category';
    TK.qs('#proj-title-h').textContent = 'پروژه جدید'; TK.qs('#members-field').classList.remove('hidden'); memberChecks(); TK.modal.open('m-project');
  });

  /* مدیریت پروژه */
  const manage = (id) => {
    const p = data().list[id]; const can = (k) => p.perms.includes(k);
    TK.qs('#mem-title').textContent = p.title;
    TK.qs('#mem-body').innerHTML = `
      <div class="flex gap-1 wrap mb-2">${can('edit_project') || can('add_member') || can('remove_member') ? `<button class="btn btn-primary btn-sm" data-edit-proj="${id}"><i class="fa-solid fa-pen"></i> ویرایش اطلاعات و اعضا</button>` : ''}</div>
      <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>دسترسی‌ها</th><th></th></tr></thead><tbody>
      ${p.members.map((m) => `<tr><td><div class="flex items-center gap-1"><span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span><div><div>${E(m.name)} ${m.is_creator ? '<span class="badge green">سازنده</span>' : ''}</div><div class="xs muted ltr">${E(m.mobile)}</div></div></div></td>
        <td>${m.is_creator ? '<span class="faint small">همه‌ی دسترسی‌ها</span>' : (m.perms.map((k) => `<span class="chip" style="margin:2px">${E(data().permDefs[k]?.label || k)}</span>`).join('') || '<span class="faint small">بدون دسترسی ویژه</span>')}</td>
        <td class="nowrap">${!m.is_creator && can('manage_permissions') ? `<button class="btn btn-xs" data-mperm="${id}:${m.id}"><i class="fa-solid fa-key"></i></button>` : ''}
          ${!m.is_creator && can('remove_member') ? `<button class="btn btn-xs btn-danger" data-post="${TK.url('/projects/' + id + '/members/' + m.id + '/remove')}" data-confirm="«${E(m.name)}» از پروژه حذف شود؟"><i class="fa-solid fa-user-minus"></i></button>` : ''}</td></tr>`).join('')}
      </tbody></table></div>
      <section class="tperm-box">
        <div class="tperm-head"><h4><i class="fa-solid fa-list-check"></i> دسترسی تسک‌ها</h4><span class="xs muted">پیش‌فرض: بدون دسترسی</span></div>
        <p class="xs muted">دسترسی هر عضو روی تسک‌های همین پروژه. سازنده‌ی پروژه همه‌ی دسترسی‌ها را دارد و هر کس روی تسک‌هایی که خودش ساخته دسترسی کامل دارد.</p>
        <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>دسترسی روی تسک‌ها</th><th></th></tr></thead><tbody>
        ${p.members.map((m) => `<tr><td><div class="flex items-center gap-1"><span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span><div>${E(m.name)}</div></div></td>
          <td>${m.is_creator ? '<span class="faint small">همه‌ی دسترسی‌ها (سازنده)</span>' : (m.task_perms.map((k) => `<span class="chip" style="margin:2px">${E(data().taskPermDefs[k]?.label || k)}</span>`).join('') || '<span class="faint small">بدون دسترسی</span>')}</td>
          <td class="nowrap">${!m.is_creator && can('manage_permissions') ? `<button class="btn btn-xs" data-tperm="${id}:${m.id}" title="تعیین دسترسی تسک‌ها"><i class="fa-solid fa-sliders"></i></button>` : ''}</td></tr>`).join('')}
        </tbody></table></div>
      </section>`;
    TK.modal.open('m-members');
  };
  TK.on('click', '[data-project]', (e, t) => manage(t.dataset.project));

  TK.on('click', '[data-edit-proj]', (e, t) => {
    const p = data().list[t.dataset.editProj]; TK.modal.close('m-members');
    TK.resetForm(form); form.setAttribute('action', TK.url('/projects/' + p.id + '/update'));
    TK.qs('#proj-title-h').textContent = 'ویرایش پروژه';
    form.project_title.value = p.title; form.project_description.value = p.description || ''; form.max_members.value = p.max_members; form.priority_type.value = p.priority_type || 'category';
    const canMem = p.perms.includes('add_member') || p.perms.includes('remove_member');
    TK.qs('#members-field').classList.toggle('hidden', !canMem); TK.qs('#members-present').disabled = !canMem;
    memberChecks(p.members, p.pending);
    ['project_title', 'project_description', 'max_members', 'profile_image'].forEach((n) => (form[n].disabled = !p.perms.includes('edit_project')));
    TK.qsa('[name=priority_type]', form).forEach((r) => (r.disabled = !p.perms.includes('edit_project')));
    TK.modal.open('m-project');
  });

  /* مجوز عضو */
  let target = null; const pf = TK.qs('#f-mperm');
  TK.on('click', '[data-mperm]', (e, t) => {
    const [pid, uid] = t.dataset.mperm.split(':').map(Number), p = data().list[pid], m = p.members.find((x) => x.id === uid);
    target = { pid, uid }; TK.qs('#mp-name').textContent = m.name;
    TK.qs('#mp-list').innerHTML = Object.entries(data().permDefs).filter(([k]) => p.perms.includes(k) || m.perms.includes(k)).map(([k, d]) => `<label class="check"><input type="checkbox" name="permissions[]" value="${k}" ${m.perms.includes(k) ? 'checked' : ''} ${p.perms.includes(k) ? '' : 'disabled'}><div><b><i class="fa-solid ${d.icon}"></i> ${E(d.label)}</b><span>${E(d.desc)}</span></div></label>`).join('');
    TK.modal.open('m-mperm');
  });
  /* دسترسی تسک‌های عضو در این پروژه (فقط دسترسی‌هایی که خودتان دارید قابل تغییر است) */
  let tTarget = null; const tf = TK.qs('#f-tperm');
  TK.on('click', '[data-tperm]', (e, t) => {
    const [pid, uid] = t.dataset.tperm.split(':').map(Number), p = data().list[pid], m = p.members.find((x) => x.id === uid);
    tTarget = { pid, uid }; TK.qs('#tp-name').textContent = m.name; TK.qs('#tp-project').textContent = '«' + p.title + '»';
    TK.qs('#tp-list').innerHTML = Object.entries(data().taskPermDefs).map(([k, d]) => `<label class="check"><input type="checkbox" name="permissions[]" value="${k}" ${m.task_perms.includes(k) ? 'checked' : ''} ${p.task_perms.includes(k) ? '' : 'disabled'}><div><b><i class="fa-solid ${d.icon}"></i> ${E(d.label)}</b><span>${E(d.desc)}</span></div></label>`).join('');
    TK.modal.open('m-tperm');
  });
  tf.addEventListener('submit', async (e) => {
    e.preventDefault();
    const perms = TK.qsa('[name="permissions[]"]:checked', tf).map((c) => c.value);
    const r = await TK.post('/projects/' + tTarget.pid + '/members/' + tTarget.uid + '/task-permissions', { permissions: perms });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { TK.modal.close('m-tperm'); TK.modal.close('m-members'); TK.reload(); }
  });

  pf.addEventListener('submit', async (e) => {
    e.preventDefault();
    const perms = TK.qsa('[name="permissions[]"]:checked', pf).map((c) => c.value);
    const r = await TK.post('/projects/' + target.pid + '/members/' + target.uid + '/permissions', { permissions: perms });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) { TK.modal.close('m-mperm'); TK.modal.close('m-members'); TK.reload(); }
  });
});
