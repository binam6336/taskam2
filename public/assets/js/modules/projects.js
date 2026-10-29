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

  /* ستون دسترسی‌ها: سه گروه تسک، پروژه و برد تیمی */
  const chips = (keys, defs, empty) => keys.map((k) => `<span class="chip" style="margin:2px">${E(defs[k]?.label || k)}</span>`).join('') || `<span class="faint small">${empty}</span>`;
  const permCell = (m) => `<div class="perm-cell"><span class="perm-cell-l">تسک:</span>${chips(m.task_perms, data().taskPermDefs, 'بدون دسترسی')}</div>
    <div class="perm-cell"><span class="perm-cell-l">پروژه:</span>${chips(m.perms, data().permDefs, 'بدون دسترسی ویژه')}</div>
    <div class="perm-cell"><span class="perm-cell-l">برد:</span><span class="chip" style="margin:2px"><i class="fa-solid fa-eye"></i> ${E(m.board_view_all ? 'تسک‌های همه‌ی اعضا' : viewSummary(m.board_view.length))}</span>${m.board_perms.map((k) => `<span class="chip" style="margin:2px">${E(data().boardPermDefs[k]?.label || k)}</span>`).join('')}${limitChip(m.board_limits)}</div>`;
  const limitText = (l) => Object.entries(data().limitDefs).filter(([k]) => l && l[k] !== undefined && l[k] !== '').map(([k, d]) => `${d.label}: ${TK.fa(l[k])}`).join('، ');
  const limitChip = (l) => { const t = limitText(l); return t ? `<span class="chip" style="margin:2px" title="سقف تعداد تسک"><i class="fa-solid fa-gauge-high"></i> ${E(t)}</span>` : ''; };
  const viewSummary = (n) => (n ? `تسک‌های خودش + ${TK.fa(n)} نفر` : 'فقط تسک‌های خودش');
  const roleBadge = (k) => { const r = data().roles[k] || data().roles.custom; return `<span class="badge role-badge ${E(r.tone)}" title="${E(r.desc)}"><i class="fa-solid ${E(r.icon)}"></i> ${E(r.label)}</span>`; };

  /* مدیریت پروژه */
  const manage = (id) => {
    const p = data().list[id]; const can = (k) => p.perms.includes(k);
    TK.qs('#mem-title').textContent = p.title;
    TK.qs('#mem-body').innerHTML = `
      <div class="flex gap-1 wrap mb-2">${can('edit_project') || can('add_member') || can('remove_member') ? `<button class="btn btn-primary btn-sm" data-edit-proj="${id}"><i class="fa-solid fa-pen"></i> ویرایش اطلاعات و اعضا</button>` : ''}</div>
      <div class="table-wrap"><table class="table"><thead><tr><th>عضو</th><th>دسترسی‌ها</th><th></th></tr></thead><tbody>
      ${p.members.map((m) => `<tr><td><div class="flex items-center gap-1"><span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span><div><div>${E(m.name)} ${m.is_creator ? '<span class="badge green">سازنده</span>' : roleBadge(m.role)}</div><div class="xs muted ltr">${E(m.mobile)}</div></div></div></td>
        <td>${m.is_creator ? '<span class="faint small">همه‌ی دسترسی‌ها</span>' : permCell(m)}</td>
        <td class="nowrap">${!m.is_creator && can('manage_permissions') ? `<button class="btn btn-xs" data-mperm="${id}:${m.id}" title="دسترسی‌های تسک، پروژه و برد تیمی"><i class="fa-solid fa-key"></i></button>` : ''}
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

  /* دسترسی‌های عضو: تب «نقش کاربر» (اول)، «تسک»، «پروژه» و «برد تیمی»؛ فقط دسترسی‌هایی که خودتان دارید قابل تغییر است */
  let target = null, limits = {}; const pf = TK.qs('#f-mperm');
  const setTab = (name) => {
    TK.qsa('[data-ptab]', pf).forEach((b) => { const on = b.dataset.ptab === name; b.classList.toggle('active', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
    TK.qsa('[data-ppanel]', pf).forEach((p) => (p.hidden = p.dataset.ppanel !== name));
  };
  TK.on('click', '[data-ptab]', (e, b) => setTab(b.dataset.ptab));
  const bvAll = () => TK.qs('#bv-all', pf), bvOthers = () => TK.qsa('.bv-member', pf);   // سایر اعضا (بدون خودِ عضو و ردیف «همه»)
  const bvSync = () => {
    const all = bvAll()?.checked;
    bvOthers().forEach((l) => l.classList.toggle('bv-covered', !!all));
    TK.qs('#bv-summary').textContent = all ? 'تسک‌های همه‌ی اعضا (حتی اعضای بعدی)' : viewSummary(bvOthers().filter((l) => l.querySelector('input').checked).length);
  };
  TK.on('change', '#bv-list input', () => { bvSync(); roleSync(); });

  /* نقش: از روی وضعیت فعلی فرم تشخیص داده می‌شود؛ انتخاب نقش، تیک‌های سه تب دیگر را پر می‌کند */
  const checked = (n) => TK.qsa(`[name="${n}[]"]:checked`, pf).map((c) => c.value);
  const same = (a, b) => a.length === b.length && a.every((x) => b.includes(x));
  const currentRole = () => {
    const all = !!bvAll()?.checked, extra = bvOthers().some((l) => l.querySelector('input').checked);
    const hit = Object.entries(data().roles).find(([k, r]) => r.pkg && same(checked('task_permissions'), r.pkg[0]) && same(checked('permissions'), r.pkg[1]) && same(checked('board_permissions'), r.pkg[2]) && all === r.pkg[3] && (all || !extra));
    return hit ? hit[0] : 'custom';
  };
  const roleSync = () => {
    const k = currentRole();
    TK.qsa('[name="role_pick"]', pf).forEach((r) => (r.checked = r.value === k));
    TK.qs('#role-custom', pf)?.classList.toggle('hidden', k !== 'custom');
  };
  TK.on('change', '#mp-task-list input, #mp-list input, #mp-board-list input', roleSync);
  TK.on('change', '[name="role_pick"]', (e, r) => {
    const pkg = data().roles[r.value].pkg;
    const set = (n, keys) => TK.qsa(`[name="${n}[]"]`, pf).forEach((c) => { if (!c.disabled) c.checked = keys.includes(c.value); });
    set('task_permissions', pkg[0]); set('permissions', pkg[1]); set('board_permissions', pkg[2]);
    if (bvAll() && !bvAll().disabled) bvAll().checked = pkg[3];
    bvOthers().forEach((l) => { const c = l.querySelector('input'); if (!c.disabled) c.checked = false; });
    bvSync(); roleSync();
    if (currentRole() !== r.value) TK.toast('بخشی از این نقش را نمی‌توانید اعطا کنید (فقط دسترسی‌هایی که خودتان دارید قابل اعطاست)؛ نقش «سفارشی» شد.', 'warning', 6000);
  });
  TK.on('click', '#bv-toggle', (e, b) => { const l = TK.qs('#bv-list'); l.hidden = !l.hidden; b.setAttribute('aria-expanded', l.hidden ? 'false' : 'true'); });
  const box = (name, k, d, checked, enabled) => `<label class="check"><input type="checkbox" name="${name}[]" value="${k}" ${checked ? 'checked' : ''} ${enabled ? '' : 'disabled'}><div><b><i class="fa-solid ${d.icon}"></i> ${E(d.label)}</b><span>${E(d.desc)}</span></div></label>`;
  TK.on('click', '[data-mperm]', (e, t) => {
    const [pid, uid] = t.dataset.mperm.split(':').map(Number), p = data().list[pid], m = p.members.find((x) => x.id === uid);
    target = { pid, uid }; TK.qs('#mp-name').textContent = m.name; TK.qs('#mp-project').textContent = '«' + p.title + '»';
    TK.qs('#mp-task-list').innerHTML = Object.entries(data().taskPermDefs).map(([k, d]) => box('task_permissions', k, d, m.task_perms.includes(k), p.task_perms.includes(k))).join('');
    // «مشاهده‌ی تسک‌های»: خودِ عضو در صدر (همیشه)، بعد بقیه‌ی اعضا؛ غیرسازنده فقط اعضایی را که خودش می‌بیند تغییر می‌دهد
    const scope = p.board_view === null ? null : new Set(p.board_view), sel = new Set(m.board_view);
    const people = [m, ...p.members.filter((x) => x.id !== m.id)];
    TK.qs('#bv-list').innerHTML = '<input type="hidden" name="view_present" value="1">'
      + `<label class="check bv-item bv-all-row"><input type="checkbox" id="bv-all" name="view_all" value="1" ${m.board_view_all ? 'checked' : ''} ${scope === null ? '' : 'disabled'}><i class="fa-solid fa-users bv-ic"></i><div><b>همه‌ی اعضای پروژه</b><span>شامل اعضایی که بعداً اضافه می‌شوند</span></div></label>`
      + people.map((x) => {
      const self = x.id === m.id, can = !self && (scope === null || scope.has(x.id));
      return `<label class="check bv-item ${self ? 'bv-self' : 'bv-member'}"><input type="checkbox" name="view_users[]" value="${x.id}" ${self || sel.has(x.id) ? 'checked' : ''} ${can ? '' : 'disabled'}>
        <span class="avatar sm">${x.avatar ? `<img src="${E(x.avatar)}" alt="">` : E(TK.initial(x.name))}</span>
        <div><b>${E(x.name)}${self ? ' <span class="badge green">خودش</span>' : ''}${x.is_creator ? ' <span class="badge">سازنده</span>' : ''}</b><span class="ltr">${E(x.mobile)}</span></div></label>`;
    }).join('');
    TK.qs('#bv-list').hidden = true; TK.qs('#bv-toggle').setAttribute('aria-expanded', 'false');
    bvSync();
    limits = { ...(m.board_limits || {}) }; blSummary();
    TK.qs('#mp-board-list').innerHTML = Object.entries(data().boardPermDefs).map(([k, d]) => box('board_permissions', k, d, m.board_perms.includes(k), p.board_perms.includes(k))).join('');
    TK.qs('#mp-list').innerHTML = Object.entries(data().permDefs).filter(([k]) => p.perms.includes(k) || m.perms.includes(k)).map(([k, d]) => box('permissions', k, d, m.perms.includes(k), p.perms.includes(k))).join('');
    TK.qs('#mp-role-list').innerHTML = Object.entries(data().roles).filter(([k]) => k !== 'custom').map(([k, r]) => `<label class="check role-opt"><input type="radio" name="role_pick" value="${E(k)}">
        <div><b><span class="badge role-badge ${E(r.tone)}"><i class="fa-solid ${E(r.icon)}"></i> ${E(r.label)}</span></b><span>${E(r.desc)}</span></div></label>`).join('')
      + `<div class="alert info hidden" id="role-custom"><i class="fa-solid fa-sliders"></i><div>نقش فعلی: <b>سفارشی</b> — دسترسی‌ها دستی تنظیم شده‌اند. با انتخاب یک نقش، همه‌ی دسترسی‌ها با پکیج آن جایگزین می‌شود.</div></div>`;
    bvSync(); roleSync();
    setTab('role');
    TK.modal.open('m-mperm');
  });
  /* سقف تعداد تسک: پاپ‌آپ جدا؛ مقدارها با «ذخیره»ی همین پنجره‌ی دسترسی‌ها ثبت می‌شوند */
  const blSummary = () => { TK.qs('#bl-summary').textContent = limitText(limits) || 'بدون محدودیت'; };
  TK.on('click', '#bl-open', () => {
    TK.qs('#bl-name').textContent = TK.qs('#mp-name').textContent;
    TK.qs('#bl-fields').innerHTML = Object.entries(data().limitDefs).map(([k, d]) => `<div class="field bl-row"><label for="bl-${k}"><i class="fa-solid ${E(d.icon)}"></i> ${E(d.label)}</label>
      <input class="input ltr" type="number" id="bl-${k}" name="${k}" min="0" max="999" step="1" inputmode="numeric" placeholder="بدون محدودیت" value="${limits[k] ?? ''}"></div>`).join('');
    TK.modal.open('m-blimit');
  });
  TK.qs('#f-blimit').addEventListener('submit', (e) => {
    e.preventDefault();
    const next = {};
    for (const inp of TK.qsa('#bl-fields input')) {
      const v = inp.value.trim();
      if (v === '') continue;
      if (!/^\d+$/.test(v) || +v > 999) { TK.toast('سقف باید عدد صحیح بین ۰ تا ۹۹۹ باشد (یا خالی برای بدون محدودیت).', 'error'); inp.focus(); return; }
      next[inp.name] = +v;
    }
    limits = next; blSummary();
    TK.modal.close('m-blimit');
  });

  pf.addEventListener('submit', async (e) => {
    e.preventDefault();
    const vals = (n) => TK.qsa(`[name="${n}[]"]:checked`, pf).map((c) => c.value);
    const btn = pf.querySelector('.modal-foot .btn-primary'); TK.busy(btn, true);
    const base = '/projects/' + target.pid + '/members/' + target.uid;
    const r1 = await TK.post(base + '/task-permissions', { permissions: vals('task_permissions') });
    const r2 = TK.ok(r1) ? await TK.post(base + '/permissions', { permissions: vals('permissions') }) : null;
    const r3 = TK.ok(r2) ? await TK.post(base + '/board-permissions', { limits: JSON.stringify(limits), permissions: vals('board_permissions'), view_present: 1, view_all: bvAll()?.checked ? 1 : 0, view_users: vals('view_users').map(Number).filter((id) => id !== target.uid) }) : null;
    TK.busy(btn, false);
    const ok = TK.ok(r1) && TK.ok(r2) && TK.ok(r3);
    TK.toast(ok ? 'دسترسی‌ها ذخیره شد.' : ((r3 || r2 || r1).message || 'خطا'), ok ? 'success' : 'error');
    if (ok) { TK.modal.close('m-mperm'); TK.modal.close('m-members'); TK.reload(); }
  });
});
