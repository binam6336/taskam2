/* تنظیمات ← مصرف AI (v7.21): «نامحدود» فیلد عدد را غیرفعال می‌کند؛ دکمه‌ی «سهمیه» پنجره‌ی ویرایش را با مقدار فعلی کاربر پر می‌کند. */
(() => {
  const sync = (root) => root.querySelectorAll('[data-ai-unl]').forEach((c) => {
    const inp = c.closest('.aiq-lim').querySelector('[data-ai-lim]');
    inp.disabled = c.checked;
    if (c.checked) inp.value = '';
  });
  document.addEventListener('change', (e) => { if (e.target.matches?.('[data-ai-unl]')) sync(e.target.closest('form')); });

  const m = document.getElementById('m-ai-quota');
  if (!m) return;
  const f = m.querySelector('form');
  document.addEventListener('click', (e) => {
    const b = e.target.closest?.('[data-ai-edit]');
    if (b) {
      f.action = TK.url('/admin/ai-usage/' + b.dataset.user + '/quota');
      m.querySelector('[data-ai-name]').textContent = b.dataset.name || '';
      ['daily', 'monthly', 'total', 'storage'].forEach((k) => {
        const v = b.dataset[k];
        f.querySelector('[name="' + k + '"]').value = v;
        f.querySelector('[name="' + k + '_unlimited"]').checked = v === '';
      });
      f.querySelector('[data-ai-reset]').value = '0';
      sync(f);
      TK.modal.open(m);
      return;
    }
    if (e.target.closest?.('[data-ai-default]')) {
      f.querySelector('[data-ai-reset]').value = '1';
      f.requestSubmit();
    }
  });
})();
