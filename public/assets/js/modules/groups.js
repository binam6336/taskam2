/* مدیریت تیم ← دپارتمان / گروه (v5.10): فرم ساخت/ویرایش گروه — اعضا از اعضای پروژه، سرگروه از بین اعضای انتخاب‌شده */
document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().groups;
  const form = TK.qs('#f-group');
  if (!cfg || !form) return;
  const E = TK.esc;
  const pick = TK.qs('#grp-pick', form), head = TK.qs('#grp-head', form);

  pick.innerHTML = (cfg.members || []).map((m) => `<label class="grp-opt"><input type="checkbox" name="members[]" value="${E(m.id)}">
      <span class="avatar sm">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(TK.initial(m.name))}</span><span class="truncate">${E(m.name)}</span></label>`).join('')
    || '<p class="faint small">این پروژه عضوی ندارد.</p>';

  // سرگروه فقط از اعضای تیک‌خورده
  const syncHead = (keep) => {
    const cur = keep ?? head.value;
    const chosen = TK.qsa('input[type=checkbox]:checked', pick).map((c) => c.value);
    head.innerHTML = '<option value="">بدون سرگروه</option>' + (cfg.members || []).filter((m) => chosen.includes(String(m.id)))
      .map((m) => `<option value="${E(m.id)}">${E(m.name)}</option>`).join('');
    head.value = chosen.includes(String(cur)) ? cur : '';
  };
  pick.addEventListener('change', () => syncHead());

  const open = (g) => {
    TK.resetForm(form);
    TK.qs('#m-group-title').textContent = g ? 'ویرایش گروه «' + g.title + '»' : 'گروه جدید';
    form.setAttribute('action', TK.url(g ? '/groups/' + g.id + '/update' : '/groups'));
    form.elements.title.value = g ? g.title : '';
    form.elements.description.value = g ? g.description : '';
    TK.qsa('input[type=checkbox]', pick).forEach((c) => { c.checked = !!g && g.members.map(String).includes(c.value); });
    syncHead(g ? g.head_id || '' : '');
    TK.modal.open('m-group');
    setTimeout(() => form.elements.title.focus(), 50);
  };
  TK.qs('#btn-new-group')?.addEventListener('click', () => open(null));
  // داده‌ی هر گروه روی دکمه‌ی خودش (داخل بخش زنده؛ بعد از ذخیره بدون رفرش کامل هم تازه است)
  TK.on('click', '[data-edit-group]', (e, b) => { try { open(JSON.parse(b.dataset.group)); } catch { open(null); } });
  form.addEventListener('submit', (e) => {
    if (!TK.qs('input[type=checkbox]:checked', pick)) { e.preventDefault(); e.stopImmediatePropagation(); TK.toast('دست‌کم یک عضو برای گروه انتخاب کنید.', 'error'); }
  });
});
