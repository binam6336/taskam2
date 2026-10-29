/* ادمین ← محتوا ← سایدبار: ویرایش نام/مقصد گزینه‌ها و جابه‌جایی با کشیدن (موس و لمس، با Pointer Events) */
document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().sb;
  const root = TK.qs('#sb-root');
  if (!cfg || !root) return;
  const E = TK.esc;
  const pages = Object.fromEntries(cfg.pages.map((p) => [p.key, p]));
  const pageOpts = (sel) => cfg.pages.map((p) => `<option value="${E(p.key)}"${p.key === sel ? ' selected' : ''}>${E(p.label)} — ${E(p.path)}</option>`).join('');
  const handle = '<button type="button" class="sb-handle" aria-label="جابه‌جایی" title="بکشید تا جابه‌جا شود"><i class="fa-solid fa-grip-vertical"></i></button>';

  const leaf = (i) => {
    const li = document.createElement('li');
    li.className = 'sb-node';
    li.dataset.t = 'leaf';
    const isLink = i.t === 'link';
    const page = isLink ? cfg.pages[0].key : i.page;
    li.innerHTML = `<div class="sb-row">${handle}
        <span class="sb-ico"><i class="fa-solid ${E(isLink ? cfg.linkIcon : pages[page]?.icon || 'fa-circle')}"></i></span>
        <input class="input sb-label" maxlength="40" value="${E(i.label || '')}" placeholder="نام گزینه" aria-label="نام">
        <select class="input sb-type" aria-label="نوع"><option value="page"${isLink ? '' : ' selected'}>صفحه</option><option value="link"${isLink ? ' selected' : ''}>لینک</option></select>
        <select class="input sb-page${isLink ? ' hidden' : ''}" aria-label="صفحه">${pageOpts(page)}</select>
        <input class="input sb-url ltr${isLink ? '' : ' hidden'}" maxlength="300" value="${E(i.url || '')}" placeholder="https://example.com" aria-label="لینک">
        <label class="sb-soon${isLink ? ' hidden' : ''}" title="نمایش با برچسب «به‌زودی» و غیرقابل کلیک"><input type="checkbox"${i.soon ? ' checked' : ''}> به‌زودی</label>
        <button type="button" class="btn btn-ghost btn-icon btn-sm sb-del" aria-label="حذف" title="حذف"><i class="fa-solid fa-trash"></i></button>
      </div>`;
    return li;
  };
  const group = (g) => {
    const li = document.createElement('li');
    li.className = 'sb-node sb-group';
    li.dataset.t = 'group';
    li.dataset.id = g.id;
    li.dataset.icon = g.icon;
    li.innerHTML = `<div class="sb-row">${handle}
        <span class="sb-ico"><i class="fa-solid ${E(g.icon)}"></i></span>
        <input class="input sb-label" maxlength="40" value="${E(g.label)}" placeholder="نام گروه" aria-label="نام گروه">
        <span class="badge">گروه</span>
      </div><ul class="sb-list sb-children"></ul>`;
    const ul = li.querySelector('.sb-children');
    g.items.forEach((c) => ul.appendChild(leaf(c)));
    return li;
  };
  cfg.menu.forEach((i) => root.appendChild(i.t === 'group' ? group(i) : leaf(i)));

  /* تغییر نوع / صفحه */
  root.addEventListener('change', (e) => {
    const li = e.target.closest('.sb-node');
    if (e.target.matches('.sb-type')) {
      const link = e.target.value === 'link';
      li.querySelector('.sb-page').classList.toggle('hidden', link);
      li.querySelector('.sb-soon').classList.toggle('hidden', link);
      li.querySelector('.sb-url').classList.toggle('hidden', !link);
      li.querySelector('.sb-ico i').className = 'fa-solid ' + (link ? cfg.linkIcon : pages[li.querySelector('.sb-page').value].icon);
      if (link) li.querySelector('.sb-url').focus();
    }
    if (e.target.matches('.sb-page')) {
      const p = pages[e.target.value], lab = li.querySelector('.sb-label');
      li.querySelector('.sb-ico i').className = 'fa-solid ' + p.icon;
      // نام پیش‌فرض صفحه‌ی قبلی (یا خالی) → نام پیش‌فرض صفحه‌ی جدید
      if (!lab.value.trim() || cfg.pages.some((x) => x.label === lab.value.trim())) lab.value = p.label;
    }
  });
  root.addEventListener('click', async (e) => {
    const del = e.target.closest('.sb-del');
    if (del && (await TK.confirm('این گزینه از سایدبار حذف شود؟ (تا ذخیره نکنید اعمال نمی‌شود)'))) del.closest('.sb-node').remove();
  });
  TK.qs('#sb-add').addEventListener('click', () => {
    const li = leaf({ t: 'link', label: '', url: '' });
    root.appendChild(li);
    li.scrollIntoView({ block: 'center', behavior: 'smooth' });
    li.querySelector('.sb-label').focus();
  });

  /* ذخیره */
  const serialize = () => TK.qsa(':scope > .sb-node', root).map((li) => {
    const label = li.querySelector('.sb-label').value.trim();
    if (li.dataset.t === 'group') return { t: 'group', id: li.dataset.id, label, items: TK.qsa(':scope > .sb-children > .sb-node', li).map(leafData) };
    return leafData(li);
  });
  const leafData = (li) => {
    const label = li.querySelector('.sb-label').value.trim();
    if (li.querySelector('.sb-type').value === 'link') return { t: 'link', label, url: li.querySelector('.sb-url').value.trim() };
    return { t: 'page', page: li.querySelector('.sb-page').value, label, soon: li.querySelector('.sb-soon input').checked };
  };
  TK.qs('#sb-save').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    TK.busy(btn, true);
    const r = await TK.post('/admin/content/sidebar', { menu: JSON.stringify(serialize()) });
    TK.busy(btn, false);
    TK.toast(r.message || (TK.ok(r) ? 'ذخیره شد' : 'خطا'), TK.ok(r) ? 'success' : 'error');
    if (TK.ok(r)) setTimeout(() => location.reload(), 600);
  });

  /* ───── کشیدن و رها کردن ───── */
  let drag = null;
  // نزدیک لبه‌ی بالا/پایین صفحه، تا وقتی اشاره‌گر آنجاست صفحه اسکرول می‌شود
  const edgeLoop = () => {
    if (!drag) return;
    const m = 70, y = drag.y;
    if (y < m) window.scrollBy(0, -Math.ceil((m - y) / 5));
    else if (y > innerHeight - m) window.scrollBy(0, Math.ceil((y - innerHeight + m) / 5));
    requestAnimationFrame(edgeLoop);
  };
  root.addEventListener('pointerdown', (e) => {
    const h = e.target.closest('.sb-handle');
    if (!h || e.button > 0) return;
    e.preventDefault();
    const node = h.closest('.sb-node'), r = node.getBoundingClientRect();
    const ph = document.createElement('li');
    ph.className = 'sb-ph';
    ph.style.height = r.height + 'px';
    node.after(ph);
    Object.assign(node.style, { width: r.width + 'px', left: r.left + 'px', top: r.top + 'px' });
    node.classList.add('sb-dragging');               // position: fixed؛ جای خالی را placeholder نگه می‌دارد
    drag = { node, ph, dy: e.clientY - r.top, isGroup: node.dataset.t === 'group', y: e.clientY };
    requestAnimationFrame(edgeLoop);
    h.setPointerCapture(e.pointerId);
  });
  document.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const { node, ph, dy, isGroup } = drag;
    node.style.top = e.clientY - dy + 'px';
    drag.y = e.clientY;
    const under = document.elementFromPoint(e.clientX, e.clientY);
    if (!under || !root.contains(under)) return;
    let target = under.closest('.sb-node');
    if (isGroup) {
      // گروه فقط در سطح اول جابه‌جا می‌شود
      while (target && target.parentElement !== root) target = target.parentElement.closest('.sb-node');
      if (!target) { if (under === root) root.appendChild(ph); return; }
      const r = target.getBoundingClientRect();
      e.clientY < r.top + r.height / 2 ? target.before(ph) : target.after(ph);
      return;
    }
    if (!target) {
      const list = under.closest('.sb-list');
      if (list && !list.querySelector(':scope > .sb-node')) list.appendChild(ph);   // گروه خالی
      return;
    }
    const row = target.querySelector(':scope > .sb-row').getBoundingClientRect();
    if (target.dataset.t === 'group' && e.clientY > row.top + row.height / 2) {
      const kids = target.querySelector('.sb-children');
      if (e.clientY <= row.bottom) kids.prepend(ph);          // نیمه‌ی پایین سرگروه → اول همان گروه
      return;
    }
    if (target.dataset.t === 'group') { target.before(ph); return; }
    e.clientY < row.top + row.height / 2 ? target.before(ph) : target.after(ph);
  });
  const drop = () => {
    if (!drag) return;
    const { node, ph } = drag;
    node.classList.remove('sb-dragging');
    node.removeAttribute('style');
    ph.replaceWith(node);
    drag = null;
  };
  document.addEventListener('pointerup', drop);
  document.addEventListener('pointercancel', drop);
});
