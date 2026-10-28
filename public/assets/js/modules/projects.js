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

  /* ستون دسترسی‌ها: دو گروه تسک و پروژه */
  const chips = (keys, defs, empty) => keys.map((k) => `<span class="chip" style="margin:2px">${E(defs[k]?.label || k)}</span>`).join('') || `<span class="faint small">${empty}</span>`;
  const permCell = (m) => `<div class="perm-cell"><span class="perm-cell-l">تسک:</span>${chips(m.task_perms, data().taskPermDefs, 'بدون دسترسی')}</div>
    <div class="perm-cell"><span class="perm-cell-l">پروژه:</span>${chips(m.perms, data().permDefs, 'بدون دسترسی ویژه')}</div>`;

  /* مدیریت پروژه */
  const manage = (id) => {
    const p = data().list[id]; const can = (k) => p.perms.includes(k);
    TK.qs('#mem-title').textContent = p.title;
    TK.qs('#mem-body').innerHTML = `
      <div class="flex gap-1 wrap mb-2">${can('edit_project') || can('add_member') || can('remove_member') ? `<button class="btn btn-primary btn-sm" data-edit-proj="${id}"><i class="fa-solid fa-pen"></i> ویرایش اطلاعات و اعضا</button>` : ''}</div>
      <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>دسترسی‌ها</th><th></th></tr></thead><tbody>
      ${p.members.map((m) => `<tr><td><div class="flex items-center gap-1"><span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span><div><div>${E(m.name)} ${m.is_creator ? '<span class="badge green">سازنده</span>' : ''}</div><div class="xs muted ltr">${E(m.mobile)}</div></div></div></td>
        <td>${m.is_creator ? '<span class="faint small">همه‌ی دسترسی‌ها</span>' : permCell(m)}</td>
        <td class="nowrap">${!m.is_creator && can('manage_permissions') ? `<button class="btn btn-xs" data-mperm="${id}:${m.id}" title="دسترسی‌های تسک و پروژه"><i class="fa-solid fa-key"></i></button>` : ''}
          ${!m.is_creator && can('remove_member') ? `<button class="btn btn-xs btn-danger" data-post="${TK.url('/projects/' + id + '/members/' + m.id + '/remove')}" data-confirm="«${E(m.name)}» از پروژه حذف شود؟"><i class="fa-solid fa-user-minus"></i></button>` : ''}</td></tr>`).join('')}
      </tbody></table></div>`;
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

  /* دسترسی‌های عضو: دو تب «تسک» (اول) و «پروژه»؛ فقط دسترسی‌هایی که خودتان دارید قابل تغییر است */
  let target = null; const pf = TK.qs('#f-mperm');
  const setTab = (name) => {
    TK.qsa('[data-ptab]', pf).forEach((b) => { const on = b.dataset.ptab === name; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    TK.qsa('[data-ppanel]', pf).forEach((p) => (p.hidden = p.dataset.ppanel !== name));
  };
  TK.on('click', '[data-ptab]', (e, b) => setTab(b.dataset.ptab));
  const box = (name, k, d, checked, enabled) => `<label class="check"><input type="checkbox" name="${name}[]" value="${k}" ${checked ? 'checked' : ''} ${enabled ? '' : 'disabled'}><div><b><i class="fa-solid ${d.icon}"></i> ${E(d.label)}</b><span>${E(d.desc)}</span></div></label>`;
  TK.on('click', '[data-mperm]', (e, t) => {
    const [pid, uid] = t.dataset.mperm.split(':').map(Number), p = data().list[pid], m = p.members.find((x) => x.id === uid);
    target = { pid, uid }; TK.qs('#mp-name').textContent = m.name; TK.qs('#mp-project').textContent = '«' + p.title + '»';
    TK.qs('#mp-task-list').innerHTML = Object.entries(data().taskPermDefs).map(([k, d]) => box('task_permissions', k, d, m.task_perms.includes(k), p.task_perms.includes(k))).join('');
    TK.qs('#mp-list').innerHTML = Object.entries(data().permDefs).filter(([k]) => p.perms.includes(k) || m.perms.includes(k)).map(([k, d]) => box('permissions', k, d, m.perms.includes(k), p.perms.includes(k))).join('');
    setTab('task');
    TK.modal.open('m-mperm');
  });
  pf.addEventListener('submit', async (e) => {
    e.preventDefault();
    const vals = (n) => TK.qsa(`[name="${n}[]"]:checked`, pf).map((c) => c.value);
    const btn = pf.querySelector('.modal-foot .btn-primary'); TK.busy(btn, true);
    const base = '/projects/' + target.pid + '/members/' + target.uid;
    const r1 = await TK.post(base + '/task-permissions', { permissions: vals('task_permissions') });
    const r2 = TK.ok(r1) ? await TK.post(base + '/permissions', { permissions: vals('permissions') }) : null;
    TK.busy(btn, false);
    const ok = TK.ok(r1) && TK.ok(r2);
    TK.toast(ok ? 'دسترسی‌ها ذخیره شد.' : ((r2 || r1).message || 'خطا'), ok ? 'success' : 'error');
    if (ok) { TK.modal.close('m-mperm'); TK.modal.close('m-members'); TK.reload(); }
  });
});
