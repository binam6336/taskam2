/* مدیریت تیم ← دپارتمان / گروه — ساخت دومرحله‌ای (v5.11):
   ۱) پنجره‌ی انتخاب پروژه  ۲) نام + اعضای همان پروژه با پروفایل (تیک = عضو، تاج = سرگروه؛ فقط یک نفر)؛ ویرایش مستقیم مرحله‌ی ۲ */
document.addEventListener('tk:ready', () => {
  const form = TK.qs('#f-group');
  if (!form) return;
  const E = TK.esc;
  const list = TK.qs('#grp-pick', form), count = TK.qs('#grp-count', form), q = TK.qs('#grp-mem-q', form);
  const projName = TK.qs('#grp-proj-name', form), changeBtn = TK.qs('#grp-change-proj', form);
  let members = [];

  const paintCount = () => {
    const n = TK.qsa('input[name="members[]"]:checked', list).length;
    const h = TK.qs('input[name=head_id]:checked', list);
    const head = h && h.value ? members.find((m) => String(m.id) === h.value) : null;
    count.textContent = TK.fa(n) + ' عضو انتخاب شده' + (head ? ' · سرگروه: ' + head.name : '');
  };
  const render = (sel = [], head = '') => {
    list.innerHTML = `<label class="grp-row grp-nohead"><span class="grp-row-n faint">بدون سرگروه</span>
        <span class="grp-head-pick"><input type="radio" name="head_id" value=""${head ? '' : ' checked'} aria-label="بدون سرگروه"><i class="fa-solid fa-crown" aria-hidden="true"></i></span></label>`
      + members.map((m) => `<div class="grp-row" data-name="${E(m.name)}">
        <label class="grp-row-main"><input type="checkbox" name="members[]" value="${E(m.id)}"${sel.includes(String(m.id)) ? ' checked' : ''}>
          <span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span>
          <span class="grp-row-n truncate">${E(m.name)}${m.role ? `<small class="faint"> · ${E(m.role)}</small>` : ''}</span></label>
        <label class="grp-head-pick" title="سرگروه"><input type="radio" name="head_id" value="${E(m.id)}"${String(m.id) === String(head) ? ' checked' : ''} aria-label="سرگروه: ${E(m.name)}"><i class="fa-solid fa-crown" aria-hidden="true"></i></label>
      </div>`).join('');
    if (!members.length) list.insertAdjacentHTML('beforeend', '<p class="faint small grp-empty">این پروژه عضوی ندارد.</p>');
    q.value = '';
    paintCount();
  };
  // سرگروه = حتماً عضو؛ برداشتن عضویتِ سرگروه، سرگروه را هم برمی‌دارد
  list.addEventListener('change', (e) => {
    const t = e.target;
    if (t.name === 'head_id' && t.value) { const c = TK.qs(`input[name="members[]"][value="${CSS.escape(t.value)}"]`, list); if (c) c.checked = true; }
    if (t.name === 'members[]' && !t.checked) { const h = TK.qs(`input[name=head_id][value="${CSS.escape(t.value)}"]`, list); if (h?.checked) TK.qs('input[name=head_id][value=""]', list).checked = true; }
    paintCount();
  });
  q.addEventListener('input', () => {
    const s = q.value.trim().replace(/ي/g, 'ی').replace(/ك/g, 'ک');
    TK.qsa('.grp-row[data-name]', list).forEach((r) => { r.hidden = !!s && !r.dataset.name.replace(/ي/g, 'ی').replace(/ك/g, 'ک').includes(s); });
  });

  const load = async (project) => {
    list.innerHTML = '<p class="faint small grp-empty">در حال بارگذاری اعضا…</p>';
    const r = await TK.get('/groups/members', { project });
    if (!TK.ok(r)) { TK.toast(r.message || 'خطا', 'error'); return null; }
    members = r.members || [];
    return r.project;
  };

  // مرحله‌ی ۲: فرم گروه برای یک پروژه (ساخت یا ویرایش)
  const openForm = async (project, g) => {
    TK.resetForm(form);
    form.elements.project_id.value = project;
    TK.qs('#m-group-title').textContent = g ? 'ویرایش گروه «' + g.title + '»' : 'گروه جدید';
    form.setAttribute('action', TK.url(g ? '/groups/' + g.id + '/update' : '/groups'));
    changeBtn.hidden = !!g;
    TK.modal.open('m-group');
    const p = await load(project);
    if (!p) return;
    projName.textContent = p.title;
    form.elements.title.value = g ? g.title : '';
    form.elements.description.value = g ? g.description : '';
    render(g ? g.members.map(String) : [], g ? g.head_id || '' : '');
    setTimeout(() => form.elements.title.focus(), 30);
  };

  // مرحله‌ی ۱: فقط انتخاب پروژه
  TK.qs('#btn-new-group')?.addEventListener('click', () => {
    TK.modal.open('m-group-proj');
    setTimeout(() => TK.qs('#m-group-proj input:checked')?.focus(), 30);
  });
  TK.qs('#grp-next')?.addEventListener('click', () => {
    const r = TK.qs('#m-group-proj input[name=grp_project]:checked');
    if (!r) { TK.toast('یک پروژه انتخاب کنید.', 'error'); return; }
    TK.modal.close('m-group-proj');
    openForm(r.value, null);
  });
  changeBtn.addEventListener('click', () => { TK.modal.close('m-group'); TK.modal.open('m-group-proj'); });
  // داده‌ی هر گروه روی دکمه‌ی خودش (داخل بخش زنده؛ بعد از ذخیره بدون رفرش کامل هم تازه است)
  TK.on('click', '[data-edit-group]', (e, b) => { try { const g = JSON.parse(b.dataset.group); openForm(g.project, g); } catch { /* داده‌ی نامعتبر */ } });
  form.addEventListener('submit', (e) => {
    if (!TK.qs('input[name="members[]"]:checked', list)) { e.preventDefault(); e.stopImmediatePropagation(); TK.toast('دست‌کم یک عضو برای گروه انتخاب کنید.', 'error'); }
  });
});
