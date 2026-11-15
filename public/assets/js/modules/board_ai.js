/* دستیار هوش مصنوعی بورد (v7.20) — پنل چت RTL کنار بورد (تمام‌صفحه در موبایل).
   همه‌ی داده‌ها (جدول تسک، آمار، پیش‌نمایش، نتیجه) بلوک‌های ساخت سرورند؛ متن مدل فقط به‌صورت متن ساده با پیوندهای اعتبارسنجی‌شده
   (قطعه‌های segs) نمایش داده می‌شود — هیچ HTML خامی از سرور یا مدل در صفحه گذاشته نمی‌شود (همه با TK.esc). */
(() => {
  const btn = document.getElementById('kb-ai');
  if (!btn) return;
  const project = btn.dataset.project;
  const esc = TK.esc;
  let panel = null;
  let state = { session: null, sending: false, loaded: false, sel: { set: null, tasks: new Set() } };

  const icon = (n) => '<i class="fa-solid fa-' + n + '" aria-hidden="true"></i>';

  // ───────── قطعه‌های متن (پیوندها) ─────────
  const inline = (s) => esc(s).replace(/\*\*([^*\n]{1,200})\*\*/g, '<strong>$1</strong>');
  const seg = (x) => {
    if (!x || typeof x !== 'object') return inline(String(x ?? ''));
    if (x.t === 's') return inline(x.v || '');
    const v = esc(x.v || '');
    if (x.t === 'task') return '<a class="ai-lnk ai-lnk-task" href="' + esc(TK.url('/tasks?task=' + (x.tok || ''))) + '" data-open-task="' + esc(x.id) + '">' + icon('square-check') + v + '</a>';
    if (x.t === 'project') return '<a class="ai-lnk" href="' + esc(TK.url('/board?project=' + x.id)) + '">' + icon('table-columns') + v + '</a>';
    if (x.t === 'user' || x.t === 'label' || x.t === 'column') {
      const ic = x.t === 'user' ? 'user' : x.t === 'label' ? 'tag' : 'table-columns';
      return '<button type="button" class="ai-lnk" data-ai-quick="' + x.t + '" data-key="' + esc(x.id) + '" title="نمایش تسک‌های مرتبط">' + icon(ic) + v + '</button>';
    }
    return v;
  };
  /** متن چندخطی: خط‌هایی که با «- » شروع می‌شوند فهرست می‌شوند */
  const richText = (segs) => {
    const html = (segs || []).map(seg).join('');
    const lines = html.split('\n');
    let out = '', list = false;
    lines.forEach((l) => {
      const m = l.match(/^\s*[-•]\s+(.*)$/);
      if (m) { if (!list) { out += '<ul>'; list = true; } out += '<li>' + m[1] + '</li>'; return; }
      if (list) { out += '</ul>'; list = false; }
      if (l.trim() !== '') out += '<p>' + l + '</p>';
    });
    return out + (list ? '</ul>' : '');
  };

  // ───────── بلوک‌ها ─────────
  const R = {};
  R.text = (b) => '<div class="ai-text' + (b.error ? ' is-err' : '') + '">' + richText(b.segs) + '</div>';
  R.kpis = (b) => '<div class="ai-kpis">' + (b.items || []).map((k) => '<div class="ai-kpi' + (k.tone ? ' is-' + esc(k.tone) : '') + '"><b>' + esc(k.value) + '</b><span>' + esc(k.label) + '</span></div>').join('') + '</div>';
  R.notes = (b) => '<div class="ai-card ai-notes"><h4>' + icon('triangle-exclamation') + esc(b.title || '') + '</h4><ul>' + (b.items || []).map((i) => '<li>' + esc(i) + '</li>').join('') + '</ul></div>';
  R.table = (b) => '<div class="ai-card"><h4>' + esc(b.title || '') + '</h4>'
    + ((b.rows || []).length ? '<div class="ai-tw"><table class="ai-t"><thead><tr>' + b.cols.map((c) => '<th>' + esc(c) + '</th>').join('') + '</tr></thead><tbody>'
      + b.rows.map((r) => '<tr>' + r.map((c, i) => '<td data-label="' + esc(b.cols[i] || '') + '">' + seg(typeof c === 'object' ? c : { t: 's', v: String(c) }) + '</td>').join('') + '</tr>').join('') + '</tbody></table></div>'
      : '<p class="ai-muted">موردی نیست.</p>')
    + (b.note ? '<p class="ai-muted ai-note">' + esc(b.note) + '</p>' : '') + '</div>';

  R.tasks = (b) => {
    const sel = state.sel;
    const on = (id) => sel.set === b.set && sel.tasks.has(id);
    const fa0 = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
    // هر تسک یک کارت (پنل باریک است): عنوان + ردیف ستون، مسئول، سررسید، پیشرفت، لیبل
    const rows = (b.rows || []).map((r) => '<li class="ai-row' + (r.overdue ? ' is-late' : '') + '">'
      + '<input type="checkbox" class="ai-ck" data-ai-pick="' + esc(r.id) + '"' + (on(r.id) ? ' checked' : '') + ' aria-label="انتخاب ' + esc(r.title) + '">'
      + '<div class="ai-row-b"><div class="ai-row-t">' + seg({ t: 'task', id: r.id, tok: r.tok, v: r.title }) + '</div>'
      + '<div class="ai-row-m">'
      + '<button type="button" class="ai-chip tone-' + esc(r.tone || '') + '" data-ai-quick="column" data-key="' + esc(r.col_key) + '" title="ستون">' + esc(r.col) + '</button>'
      + (r.assignee ? seg({ t: 'user', id: r.assignee_id, v: r.assignee }) : '<span class="ai-muted">بدون مسئول</span>')
      + '<span class="ai-due" title="سررسید">' + icon('calendar') + (r.due ? esc(r.due) : 'بدون سررسید') + (r.overdue ? ' <span class="ai-late">عقب‌افتاده</span>' : '') + '</span>'
      + '<span class="ai-pg" title="پیشرفت"><span class="ai-prog"><span data-css="width:' + Math.max(0, Math.min(100, r.progress | 0)) + '%"></span></span>' + fa0(r.progress | 0) + '٪</span>'
      + (r.labels || []).map((l) => seg({ t: 'label', id: l.id, v: l.title })).join('')
      + '</div></div></li>').join('');
    const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const count = sel.set === b.set ? sel.tasks.size : 0;
    return '<div class="ai-card ai-set" data-set="' + esc(b.set) + '" data-page="' + b.page + '">'
      + '<div class="ai-set-head"><h4>' + icon('list-check') + esc(b.title) + '</h4><span class="ai-ref" title="شناسه‌ی لیست برای ارجاع در پیام بعدی">' + esc(b.ref) + '</span></div>'
      + '<div class="ai-set-bar"><span class="ai-total">' + fa(b.total) + ' تسک' + (b.hidden ? ' <span class="ai-muted">(' + fa(b.hidden) + ' مورد دیگر در دسترس نیست)</span>' : '') + '</span>'
      + '<span class="ai-sel-n" data-ai-seln>' + (count ? fa(count) + ' انتخاب‌شده' : '') + '</span>'
      + '<span class="ai-grow"></span>'
      + (b.total ? '<button type="button" class="ai-mini" data-ai-all title="انتخاب همه‌ی تسک‌های این لیست">' + icon('check') + 'همه</button>'
        + '<button type="button" class="ai-mini" data-ai-export="csv" title="خروجی CSV">' + icon('file-csv') + 'CSV</button>'
        + '<button type="button" class="ai-mini" data-ai-export="pdf" title="خروجی PDF">' + icon('file-pdf') + 'PDF</button>' : '')
      + '</div>'
      + (b.total ? '<label class="ai-pickpage"><input type="checkbox" class="ai-ck" data-ai-pickpage> انتخاب همه‌ی این صفحه</label><ul class="ai-rows">' + rows + '</ul>'
        : '<p class="ai-muted">تسکی با این شرایط نیست.</p>')
      + (b.pages > 1 ? '<div class="ai-pager"><button type="button" class="ai-mini" data-ai-page="' + (b.page - 1) + '"' + (b.page <= 1 ? ' disabled' : '') + '>' + icon('chevron-right') + 'قبلی</button><span>صفحه‌ی ' + fa(b.page) + ' از ' + fa(b.pages) + '</span><button type="button" class="ai-mini" data-ai-page="' + (b.page + 1) + '"' + (b.page >= b.pages ? ' disabled' : '') + '>بعدی' + icon('chevron-left') + '</button></div>' : '')
      + (count ? '<div class="ai-sel-hint">' + icon('circle-check') + 'در پیام بعدی بنویسید با «انتخاب‌شده‌ها» چه کنم؛ مثلاً «سررسید انتخاب‌شده‌ها را بگذار فردا ساعت ۱۰».</div>' : '')
      + '</div>';
  };

  const stateLabel = { applied: 'اجرا شد', undone: 'بازگردانی شد', cancelled: 'لغو شد', expired: 'منقضی شد', applying: 'در حال اجرا', undoing: 'در حال بازگردانی' };
  R.preview = (b) => {
    const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const live = b.status === 'preview' && b.token;
    return '<div class="ai-card ai-preview" data-action="' + esc(b.action || '') + '" data-token="' + esc(b.token || '') + '">'
      + '<h4>' + icon('eye') + 'پیش‌نمایش تغییر گروهی' + (b.source ? ' <small class="ai-muted">— ' + esc(b.source) + '</small>' : '') + '</h4>'
      + '<div class="ai-pv-sum"><span class="ai-badge is-ok">' + fa(b.count || 0) + ' تسک تغییر می‌کند</span>'
      + (b.bad && b.bad.length ? '<span class="ai-badge is-bad">' + fa(b.bad.length) + ' تسک مجاز نیست</span>' : '')
      + (b.same ? '<span class="ai-badge">' + fa(b.same) + ' بدون تغییر</span>' : '') + '</div>'
      + ((b.rows || []).length ? '<ul class="ai-pv">' + b.rows.map((r) => '<li><div class="ai-pv-t">' + seg(r.task) + '</div>' + r.changes.map((c) => '<div class="ai-pv-c"><span class="ai-f">' + esc(c.f) + '</span><span class="ai-from">' + esc(c.from) + '</span>' + icon('arrow-left') + '<span class="ai-to">' + esc(c.to) + '</span>' + (c.note ? '<small class="ai-muted">' + esc(c.note) + '</small>' : '') + '</div>').join('') + '</li>').join('') + '</ul>' : '')
      + ((b.bad || []).length ? '<details class="ai-bad"><summary>تسک‌هایی که تغییر نمی‌کنند و دلیلشان</summary><ul>' + b.bad.map((x) => '<li>' + (x.task ? seg(x.task) : esc(x.title || '')) + ' — <span>' + esc(x.reason) + '</span></li>').join('') + '</ul></details>' : '')
      + (live ? '<div class="ai-acts"><button type="button" class="btn btn-primary btn-sm" data-ai-apply>' + icon('check') + 'تأیید و اجرا</button><button type="button" class="btn btn-sm" data-ai-cancel>لغو</button><span class="ai-muted ai-exp">اعتبار تا ' + esc(TK.jalali && b.expires ? b.expires.slice(11, 16) : '') + '</span></div>'
        : (b.count ? '<p class="ai-state">' + esc(stateLabel[b.status] || 'غیرفعال') + '</p>' : ''))
      + '</div>';
  };

  R.create_preview = (b) => {
    const live = b.status === 'preview' && b.token;
    const opts = (list, cur, key, none) => '<option value="">' + esc(none) + '</option>' + list.map((x) => '<option value="' + esc(x.id) + '"' + (x.id === cur ? ' selected' : '') + '>' + esc(x[key]) + '</option>').join('');
    return '<div class="ai-card ai-create" data-action="' + esc(b.action || '') + '" data-token="' + esc(b.token || '') + '">'
      + '<h4>' + icon('wand-magic-sparkles') + 'پیش‌نمایش ساخت تسک (قابل ویرایش)</h4>'
      + b.items.map((it, i) => '<div class="ai-ci" data-i="' + i + '">'
        + '<label class="ai-ci-on"><input type="checkbox" data-f="include" checked' + (live ? '' : ' disabled') + '> ساخته شود</label>'
        + '<input class="input" data-f="title" value="' + esc(it.title) + '" maxlength="255" aria-label="عنوان"' + (live ? '' : ' disabled') + '>'
        + '<textarea class="input" data-f="description" rows="2" aria-label="توضیحات" placeholder="توضیحات"' + (live ? '' : ' disabled') + '>' + esc(it.description || '') + '</textarea>'
        + '<div class="ai-ci-row">'
        + '<select class="input" data-f="label" aria-label="لیبل"' + (live ? '' : ' disabled') + '>' + opts(b.labels || [], it.label, 'title', 'بدون لیبل') + '</select>'
        + '<select class="input" data-f="assignee" aria-label="مسئول"' + (live ? '' : ' disabled') + '>' + opts(b.members || [], it.assignee, 'name', 'خودم') + '</select>'
        + '<input class="input" data-f="estimate" type="number" min="0.25" max="400" step="0.25" value="' + esc(it.estimate ?? '') + '" placeholder="تخمین (ساعت)" aria-label="تخمین ساعت"' + (live ? '' : ' disabled') + '>'
        + '<input class="input" data-f="phase" value="' + esc(it.phase || '') + '" placeholder="فاز" aria-label="فاز" maxlength="60"' + (live ? '' : ' disabled') + '>'
        + '</div>'
        + ((it.dups || []).length ? '<div class="ai-dup">' + icon('triangle-exclamation') + 'مشابه تسک موجود: ' + it.dups.map((d) => seg(d) + ' <small>(' + esc(String(d.score)) + '٪)</small>').join('، ') + '</div>' : '')
        + '</div>').join('')
      + (live ? '<div class="ai-acts"><button type="button" class="btn btn-primary btn-sm" data-ai-apply>' + icon('check') + 'تأیید و ساخت</button><button type="button" class="btn btn-sm" data-ai-cancel>لغو</button></div>'
        : '<p class="ai-state">' + esc(stateLabel[b.status] || 'غیرفعال') + '</p>')
      + '</div>';
  };

  R.result = (b) => {
    const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const undone = b.state === 'undone';
    return '<div class="ai-card ai-result' + (b.fail ? ' has-fail' : '') + '" data-action="' + esc(b.action) + '">'
      + '<h4>' + icon(undone ? 'clock-rotate-left' : 'circle-check') + (undone ? 'نتیجه‌ی بازگردانی' : 'نتیجه‌ی اجرا') + '</h4>'
      + '<div class="ai-pv-sum"><span class="ai-badge is-ok">' + fa(b.ok) + ' موفق</span>' + (b.fail ? '<span class="ai-badge is-bad">' + fa(b.fail) + ' ناموفق</span>' : '') + '</div>'
      + ((b.errors || []).length ? '<ul class="ai-errs">' + b.errors.map((x) => '<li>' + icon('circle-xmark') + (x.task ? seg(x.task) : esc(x.title || '')) + ' — ' + esc(x.text) + '</li>').join('') + '</ul>' : '')
      + ((b.done || []).length && b.kind === 'create_tasks' && !undone ? '<ul class="ai-oks">' + b.done.map((x) => '<li>' + icon('check') + (x.task ? seg(x.task) : esc(x.title || '')) + '</li>').join('') + '</ul>' : '')
      + (b.can_undo ? '<div class="ai-acts"><button type="button" class="btn btn-sm" data-ai-undo>' + icon('clock-rotate-left') + 'بازگردانی (Undo)</button></div>' : '')
      + '</div>';
  };

  R.export = (b) => '<div class="ai-card ai-export">' + icon(b.format === 'pdf' ? 'file-pdf' : 'file-csv')
    + '<div><strong>' + esc(b.title || b.name) + '</strong><small class="ai-muted">' + esc(b.name) + ' — لینک تا ' + esc(b.expires_fa || '') + ' معتبر است</small></div>'
    + '<a class="btn btn-primary btn-sm" href="' + esc(b.url) + '" download data-no-nav>' + icon('download') + 'دانلود</a></div>';

  const block = (b) => (R[b.type] ? R[b.type](b) : '');
  const msgHtml = (m) => m.role === 'user'
    ? '<div class="ai-msg is-user" data-id="' + m.id + '"><div class="ai-bub">' + esc(m.text) + '</div></div>'
    : '<div class="ai-msg is-ai" data-id="' + m.id + '"><span class="ai-av">' + icon('brain') + '</span><div class="ai-body">' + (m.blocks || []).map(block).join('') + '</div></div>';

  // ───────── پنل ─────────
  const build = () => {
    panel = document.createElement('aside');
    panel.id = 'ai-panel';
    panel.className = 'ai-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'دستیار هوش مصنوعی بورد');
    panel.hidden = true;
    panel.innerHTML = '<header class="ai-head"><span class="ai-head-ic">' + icon('brain') + '</span><div class="ai-head-t"><strong>دستیار هوش مصنوعی</strong><small data-ai-proj></small></div>'
      + '<select class="ai-hist" data-ai-hist aria-label="گفتگوهای قبلی"></select>'
      + '<button type="button" class="kb-ib" data-ai-new title="گفتگوی جدید" aria-label="گفتگوی جدید">' + icon('plus') + '</button>'
      + '<button type="button" class="kb-ib" data-ai-close title="بستن" aria-label="بستن">' + icon('xmark') + '</button></header>'
      + '<div class="ai-ctx" data-ai-ctx></div>'
      + '<div class="ai-log" data-ai-log aria-live="polite"></div>'
      + '<div class="ai-status" data-ai-status hidden><span class="ai-dots"><span></span><span></span><span></span></span><span data-ai-status-t>در حال تحلیل داده‌های بورد…</span></div>'
      + '<form class="ai-form" data-ai-form><div class="ai-selbar" data-ai-selbar hidden></div><textarea class="input" data-ai-input rows="2" maxlength="4000" placeholder="' + esc(TK.ph('board_ai', 'مثلاً: تسک‌های عقب‌افتاده را لیست کن')) + '" aria-label="پیام به دستیار"></textarea>'
      + '<button type="submit" class="btn btn-primary ai-send" aria-label="ارسال">' + icon('paper-plane') + '</button></form>';
    document.body.appendChild(panel);
  };

  const $ = (s) => panel.querySelector(s);
  const log = () => $('[data-ai-log]');
  const scrollEnd = () => { const l = log(); l.scrollTop = l.scrollHeight; };

  const filters = () => {
    const f = document.getElementById('kb-filters');
    return { mine: !!f?.querySelector('[name="mine"]')?.checked, q: f?.querySelector('[name="q"]')?.value || '' };
  };
  const showCtx = () => {
    const f = filters();
    const parts = [];
    if (f.mine) parts.push('فقط مسئولیت من');
    if (f.q) parts.push('جستجو: «' + f.q + '»');
    $('[data-ai-ctx]').innerHTML = parts.length ? icon('filter') + 'فیلتر فعال بورد: ' + esc(parts.join('، ')) : '';
    $('[data-ai-ctx]').hidden = !parts.length;
  };
  const selBar = () => {
    const n = state.sel.tasks.size;
    const bar = $('[data-ai-selbar]');
    bar.hidden = !n;
    bar.innerHTML = n ? icon('list-check') + String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]) + ' تسک انتخاب شده؛ پیام بعدی روی همین‌ها اجرا می‌شود. <button type="button" class="ai-mini" data-ai-clearsel>لغو انتخاب</button>' : '';
  };

  const empty = (d) => '<div class="ai-empty"><span class="ai-empty-ic">' + icon('brain') + '</span><p>سلام ' + esc(d.user || '') + '! درباره‌ی بورد «' + esc(d.project?.title || '') + '» بپرسید یا بخواهید چند تسک را با هم تغییر دهم (همیشه اول پیش‌نمایش و تأیید شما).</p>'
    + (d.enabled ? '<div class="ai-sugs">' + (d.suggestions || []).map((s) => '<button type="button" class="ai-sug" data-ai-sug="' + esc(s) + '">' + esc(s) + '</button>').join('') + '</div>' : '<p class="ai-err">' + esc(d.reason || '') + '</p>') + '</div>';

  let data = null;
  const render = (d) => {
    data = d;
    state.session = d.session;
    $('[data-ai-proj]').textContent = d.project?.title || '';
    $('[data-ai-hist]').innerHTML = (d.sessions || []).map((s) => '<option value="' + esc(s.id) + '"' + (s.id === d.session ? ' selected' : '') + '>' + esc(s.title.length > 40 ? s.title.slice(0, 40) + '…' : s.title) + '</option>').join('');
    $('[data-ai-hist]').hidden = (d.sessions || []).length < 2;
    log().innerHTML = d.messages.length ? d.messages.map(msgHtml).join('') : empty(d);
    $('[data-ai-input]').disabled = !d.enabled;
    showCtx(); selBar();
    TK.applyCss && TK.applyCss(panel);
    scrollEnd();
  };

  const load = async (session) => {
    log().innerHTML = '<div class="ai-loading"><span class="ai-dots"><span></span><span></span><span></span></span></div>';
    const r = await TK.get('/board/ai/session', session ? { project, session } : { project });
    if (r.status !== 'success') { log().innerHTML = '<p class="ai-err">' + esc(r.message || 'بارگذاری نشد.') + '</p>'; return; }
    state.sel = { set: null, tasks: new Set() };
    state.loaded = true;
    render(r);
  };

  const open = () => {
    if (!panel) build();
    panel.hidden = false;
    document.documentElement.classList.add('ai-open');
    btn.setAttribute('aria-expanded', 'true');
    if (!state.loaded) load(); else { showCtx(); }
    setTimeout(() => $('[data-ai-input]').focus(), 50);
  };
  const close = () => {
    if (!panel) return;
    panel.hidden = true;
    document.documentElement.classList.remove('ai-open');
    btn.setAttribute('aria-expanded', 'false');
    btn.focus();
  };

  const busy = (on, text) => {
    state.sending = on;
    $('[data-ai-status]').hidden = !on;
    if (text) $('[data-ai-status-t]').textContent = text;
    panel.querySelector('.ai-send').disabled = on;
  };

  const append = (m) => {
    const l = log();
    l.querySelector('.ai-empty')?.remove();
    l.insertAdjacentHTML('beforeend', msgHtml(m));
    TK.applyCss && TK.applyCss(l.lastElementChild);
    scrollEnd();
  };

  const send = async (text) => {
    text = (text || '').trim();
    if (!text || state.sending) return;
    append({ id: 'u' + Date.now(), role: 'user', text });
    $('[data-ai-input]').value = '';
    busy(true, 'در حال تحلیل داده‌های بورد…');
    const t2 = setTimeout(() => state.sending && ($('[data-ai-status-t]').textContent = 'در حال اجرای ابزارها و آماده‌سازی پاسخ…'), 6000);
    const sel = state.sel.tasks.size ? { set: state.sel.set, tasks: [...state.sel.tasks] } : {};
    const r = await TK.post('/board/ai/message', { project, session: state.session, text, filters: JSON.stringify(filters()), selection: JSON.stringify(sel) });
    clearTimeout(t2);
    busy(false);
    if (r.status !== 'success') {
      append({ id: 'e' + Date.now(), role: 'assistant', blocks: [{ type: 'text', error: true, segs: [{ t: 's', v: r.message || 'ارتباط برقرار نشد؛ دوباره امتحان کنید.' }] }] });
      return;
    }
    state.sel = { set: null, tasks: new Set() };
    selBar();
    append(r.reply);
    // آخرین پیام را دوباره برای بروز بودن وضعیت انتخاب‌ها رسم کن
  };

  // ───────── رویدادها ─────────
  btn.addEventListener('click', () => (panel && !panel.hidden ? close() : open()));

  document.addEventListener('submit', (e) => {
    if (!e.target.matches?.('[data-ai-form]')) return;
    e.preventDefault();
    send($('[data-ai-input]').value);
  });
  document.addEventListener('keydown', (e) => {
    if (!panel || panel.hidden) return;
    // Esc فقط وقتی پنجره‌ی دیگری (جزئیات تسک، تأیید، …) روی پنل باز نیست
    if (e.key === 'Escape' && !e.target.closest?.('select') && !document.querySelector('.modal.open, .ri-overlay:not([hidden])')) { e.stopPropagation(); close(); return; }
    if (e.target.matches?.('[data-ai-input]') && e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(e.target.value); }
  }, true);

  const post = async (url, extra, cardEl) => {
    cardEl?.querySelectorAll('button').forEach((b) => (b.disabled = true));
    const r = await TK.post(url, { project, session: state.session, ...extra });
    if (r.status !== 'success') {
      TK.toast(r.message || 'انجام نشد.', 'error');
      cardEl?.querySelectorAll('button').forEach((b) => (b.disabled = false));
      return null;
    }
    return r;
  };

  document.addEventListener('click', async (e) => {
    if (!panel || panel.hidden) return;
    const t = e.target.closest?.('button, input[type=checkbox]');
    if (!t || !panel.contains(t)) return;
    if (t.matches('[data-ai-close]')) return close();
    if (t.matches('[data-ai-new]')) {
      const r = await TK.post('/board/ai/session/new', { project });
      if (r.status === 'success') { state.sel = { set: null, tasks: new Set() }; render(r); $('[data-ai-input]').focus(); }
      return;
    }
    if (t.matches('[data-ai-sug]')) return send(t.dataset.aiSug);
    if (t.matches('[data-ai-clearsel]')) { state.sel = { set: null, tasks: new Set() }; panel.querySelectorAll('[data-ai-pick]').forEach((c) => (c.checked = false)); panel.querySelectorAll('[data-ai-seln]').forEach((x) => (x.textContent = '')); selBar(); return; }
    if (t.matches('[data-ai-quick]')) {
      if (state.sending) return;
      append({ id: 'q' + Date.now(), role: 'user', text: t.textContent.trim() });
      busy(true, 'در حال آماده‌سازی لیست…');
      const r = await TK.post('/board/ai/quick', { project, session: state.session, kind: t.dataset.aiQuick, key: t.dataset.key });
      busy(false);
      if (r.status !== 'success') { TK.toast(r.message || 'انجام نشد.', 'error'); return; }
      append(r.messages[1]);
      return;
    }
    const setCard = t.closest('.ai-set');
    if (setCard) {
      const sid = setCard.dataset.set;
      const take = () => { if (state.sel.set !== sid) { state.sel = { set: sid, tasks: new Set() }; panel.querySelectorAll('.ai-set').forEach((c) => c !== setCard && c.querySelectorAll('[data-ai-pick]').forEach((x) => (x.checked = false))); } };
      const fa = (n) => String(n).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
      const sync = () => { setCard.querySelector('[data-ai-seln]').textContent = state.sel.tasks.size ? fa(state.sel.tasks.size) + ' انتخاب‌شده' : ''; selBar(); };
      if (t.matches('[data-ai-pick]')) { take(); t.checked ? state.sel.tasks.add(t.dataset.aiPick) : state.sel.tasks.delete(t.dataset.aiPick); return sync(); }
      if (t.matches('[data-ai-pickpage]')) { take(); setCard.querySelectorAll('[data-ai-pick]').forEach((c) => { c.checked = t.checked; t.checked ? state.sel.tasks.add(c.dataset.aiPick) : state.sel.tasks.delete(c.dataset.aiPick); }); return sync(); }
      if (t.matches('[data-ai-all]')) {
        const r = await TK.get('/board/ai/set', { project, session: state.session, set: sid, page: setCard.dataset.page, all: 1 });
        if (r.status !== 'success') return TK.toast(r.message, 'error');
        take(); r.block.all_ids.forEach((id) => state.sel.tasks.add(id));
        setCard.querySelectorAll('[data-ai-pick]').forEach((c) => (c.checked = true));
        return sync();
      }
      if (t.matches('[data-ai-page]')) {
        const r = await TK.get('/board/ai/set', { project, session: state.session, set: sid, page: t.dataset.aiPage });
        if (r.status !== 'success') return TK.toast(r.message, 'error');
        setCard.outerHTML = R.tasks(r.block);
        TK.applyCss && TK.applyCss(panel);
        return;
      }
      if (t.matches('[data-ai-export]')) {
        t.disabled = true;
        const r = await TK.post('/board/ai/export', { project, session: state.session, kind: 'tasks', format: t.dataset.aiExport, set: setCard.querySelector('.ai-ref').textContent });
        t.disabled = false;
        if (r.status !== 'success') return TK.toast(r.message || 'خروجی ساخته نشد.', 'error');
        setCard.insertAdjacentHTML('afterend', R.export(r.block));
        return;
      }
    }
    const card = t.closest('[data-action]');
    if (!card) return;
    const action = card.dataset.action;
    if (t.matches('[data-ai-apply]')) {
      let edits = '';
      if (card.classList.contains('ai-create')) {
        edits = JSON.stringify([...card.querySelectorAll('.ai-ci')].map((row) => {
          const o = {};
          row.querySelectorAll('[data-f]').forEach((f) => (o[f.dataset.f] = f.type === 'checkbox' ? f.checked : f.value));
          return o;
        }));
        if (!JSON.parse(edits).some((x) => x.include)) return TK.toast('هیچ تسکی برای ساخت انتخاب نشده است.', 'warning');
      }
      busy(true, 'در حال اجرای تغییرات…');
      const r = await post('/board/ai/apply', { action, token: card.dataset.token, edits }, card);
      busy(false);
      if (!r) return;
      card.querySelector('.ai-acts')?.replaceWith(Object.assign(document.createElement('p'), { className: 'ai-state', textContent: 'اجرا شد' }));
      card.querySelectorAll('input, select, textarea').forEach((f) => (f.disabled = true));
      append({ id: 'r' + Date.now(), role: 'assistant', blocks: [r.block] });
      TK.toast('تغییرات اجرا شد: ' + (r.block.ok) + ' موفق' + (r.block.fail ? '، ' + r.block.fail + ' ناموفق' : ''), r.block.fail ? 'warning' : 'success');
      TK.live && TK.live.refresh && TK.live.refresh();
      return;
    }
    if (t.matches('[data-ai-cancel]')) {
      const r = await post('/board/ai/cancel', { action }, card);
      if (r) card.querySelector('.ai-acts')?.replaceWith(Object.assign(document.createElement('p'), { className: 'ai-state', textContent: 'لغو شد' }));
      return;
    }
    if (t.matches('[data-ai-undo]')) {
      if (!(await TK.confirm('همه‌ی تغییرات این عملیات به مقدار قبلی برگردد؟'))) return;
      busy(true, 'در حال بازگردانی…');
      const r = await post('/board/ai/undo', { action }, card);
      busy(false);
      if (!r) return;
      card.querySelector('.ai-acts')?.remove();
      append({ id: 'u' + Date.now(), role: 'assistant', blocks: [r.block] });
      TK.toast('بازگردانی انجام شد.', r.block.fail ? 'warning' : 'success');
      TK.live && TK.live.refresh && TK.live.refresh();
    }
  });

  document.addEventListener('change', (e) => {
    if (panel && e.target.matches?.('[data-ai-hist]')) load(e.target.value);
  });
  // تغییر فیلتر بورد در حالی که پنل باز است
  document.addEventListener('input', (e) => { if (panel && !panel.hidden && e.target.closest?.('#kb-filters')) showCtx(); });

  // ?ai=1 در آدرس = باز شدن پنل
  if (new URLSearchParams(location.search).get('ai') === '1') open();
})();
