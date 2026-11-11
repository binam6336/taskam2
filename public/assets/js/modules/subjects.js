document.addEventListener('tk:ready', () => {
  const f = TK.qs('#f-subject');
  if (!f) return;
  const sel = f.elements.project_id, box = TK.qs('#subj-project'), note = TK.qs('#subj-project-note');
  // لیبل جدید: انتخاب پروژه (فقط پروژه‌های دارای «دسترسی ایجاد لیبل»)
  TK.qs('#btn-new-subject')?.addEventListener('click', () => {
    TK.resetForm(f); const c = f.querySelector('[name=color][value="#22c55e"]'); if (c) c.checked = true; f.setAttribute('action', TK.url('/labels'));
    box.hidden = false; sel.disabled = false; sel.required = true; note.textContent = 'فقط پروژه‌هایی که در آن‌ها «دسترسی ایجاد لیبل» دارید.';
    if (sel.options.length === 2) sel.selectedIndex = 1;
  });
  // ویرایش: پروژه‌ی لیبل ثابت است؛ لیبل شخصی قدیمی را می‌شود به یک پروژه منتقل کرد
  TK.qs('#m-subject').addEventListener('tk:fill', (e) => {
    const d = e.detail || {};
    box.hidden = !d.legacy; sel.disabled = !d.legacy; sel.required = !!d.legacy;
    if (d.legacy) { sel.value = ''; note.textContent = 'لیبل شخصی قدیمی به پروژه‌ی انتخابی منتقل می‌شود و از آن به بعد مال همان پروژه است.'; }
  });
});
