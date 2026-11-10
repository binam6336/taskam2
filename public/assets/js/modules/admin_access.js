/* تنظیمات ← دسترسی (v7.4): «طبق نقش» = تیک‌ها همان دسترسی نقش (فقط‌خواندنی)؛ «اختصاصی» = قابل تغییر. */
document.addEventListener('tk:ready', () => {
  const sync = (form) => {
    const role = form.querySelector('[data-ac-role]')?.value, modeSel = form.querySelector('[data-ac-mode]'), box = form.querySelector('[data-ac-perms]'), hint = form.querySelector('[data-ac-hint]');
    if (!box || !modeSel) return;
    let roles = {}; try { roles = JSON.parse(box.dataset.rolePerms || '{}'); } catch { /* */ }
    const fixed = role === 'super' || role === 'user';
    modeSel.disabled = fixed;
    const custom = !fixed && modeSel.value === 'custom';
    box.hidden = role === 'user';
    box.querySelectorAll('input[type=checkbox]').forEach((c) => {
      if (!custom) c.checked = role === 'user' ? false : (roles[role] || []).includes(c.value);
      c.disabled = !custom;
    });
    if (hint) hint.textContent = role === 'super' ? 'مدیر کل همه‌ی دسترسی‌ها را دارد.' : role === 'user' ? 'با ذخیره، این حساب کاربر عادی می‌شود و به پنل مدیر دسترسی ندارد.'
      : custom ? 'فقط تیک‌خورده‌ها برای این مدیر فعال است (مستقل از تغییرهای بعدی نقش).' : 'دسترسی‌ها همان دسترسی نقش است و با تغییر جدول نقش‌ها عوض می‌شود.';
  };
  const forms = TK.qsa('form[action*="/admin/access/users/"]');
  forms.forEach((f) => { sync(f); f.addEventListener('change', (e) => { if (e.target.matches('[data-ac-role], [data-ac-mode]')) sync(f); }); });
});
