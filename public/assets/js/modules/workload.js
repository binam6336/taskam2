/*!
 * Karlix — «بار کاری» (Workload) — reference implementation
 * - Pure logic (computeLoads, suggestRebalance) is the SPEC: port it 1:1 to PHP (WorkloadService)
 *   so server and client always agree. Node-testable: module.exports at the bottom.
 * - UI: WL.init(sectionEl, { endpoint, csrf })  or  WL.init(sectionEl, { data })  for preview.
 * - Auto-init: <section class="wl" data-endpoint="/projects/{id}/workload" data-csrf="…"> (app/Views/projects/workload.php)
 * - v7.10 (پنل): toast مشترک TK.toast، توقف «اعمال همه» در اولین خطا + بارگذاری دوباره، هشدار کشیدن به بالای ۱۰۰٪، init با tk:ready
 * - CSP-safe: no inline styles in markup; widths/positions are set via element.style (CSSOM).
 */
(function (global) {
  'use strict';

  // ======================= helpers =======================
  var FA = '۰۱۲۳۴۵۶۷۸۹';
  function fa(n) { return String(n).replace(/\d/g, function (d) { return FA[d]; }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function parseYMD(s) { if (!s) return null; var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function diffDays(a, b) { return Math.round((a - b) / 864e5); }
  var hasIntl = typeof Intl !== 'undefined';
  var WD = hasIntl ? new Intl.DateTimeFormat('fa-IR', { weekday: 'long' }) : null;
  var DM = hasIntl ? new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long' }) : null;
  function faDate(d) { return WD ? WD.format(d) + ' ' + DM.format(d) : ymd(d); }

  var DEFAULTS = { capacity: 12, window_days: 7, weights: { high: 3, medium: 2, low: 1 }, free_below: 70, over_above: 100, target_max: 85, daily_share: 5, max_suggestions: 5 };
  var OPEN = { backlog: 1, todo: 1, in_progress: 1, rework: 1 };   // statuses that count as the assignee's load
  var MOVABLE = { backlog: 1, todo: 1 };                           // only not-yet-started work is suggested for moving

  // ======================= core logic (SPEC) =======================
  /** bucket of a task relative to `today`: 'od' overdue | 'wk' due within window | 'nd' no due date | 'later' */
  function bucketOf(task, today, win) {
    if (!task.due_date) return 'nd';
    var dd = diffDays(parseYMD(task.due_date), today);
    return dd < 0 ? 'od' : dd < win ? 'wk' : 'later';
  }
  function weightOf(task, st) { return st.weights[task.priority] || st.weights.medium; }

  /**
   * computeLoads(data, capacityOverride?) → [{ member, capacity, od, wk, nd, later, load, pct, level, open, tasks[] }]
   * load = Σ weight over buckets od + wk + nd ; pct = round(load / capacity * 100)
   */
  function computeLoads(data, capOverride, assignments) {
    var st = Object.assign({}, DEFAULTS, data.settings || {});
    var today = parseYMD(data.today), win = st.window_days;
    var defCap = capOverride || st.capacity;
    var rows = {};
    data.members.forEach(function (m) {
      rows[m.id] = { member: m, capacity: m.capacity || defCap, od: 0, wk: 0, nd: 0, later: 0, load: 0, pct: 0, open: 0, tasks: [] };
    });
    data.tasks.forEach(function (t) {
      if (!OPEN[t.status]) return;
      var aid = assignments && assignments[t.id] != null ? assignments[t.id] : t.assignee_id;
      var r = rows[aid]; if (!r) return;
      var b = bucketOf(t, today, win), w = weightOf(t, st);
      r[b] += w; r.open++; r.tasks.push(t);
    });
    return Object.keys(rows).map(function (k) {
      var r = rows[k]; r.load = r.od + r.wk + r.nd; r.pct = Math.round(r.load / r.capacity * 100);
      r.level = r.pct > st.over_above ? 'bd' : r.pct >= st.free_below ? 'wr' : 'ok';
      return r;
    }).sort(function (a, b) { return b.pct - a.pct || b.open - a.open || a.member.name.localeCompare(b.member.name, 'fa'); });
  }

  /**
   * suggestRebalance(data, capacityOverride?, opts?) → { suggestions:[…], unresolved:[…] }
   * Greedy, deterministic, cascading (each suggestion assumes the previous ones were applied):
   *  1. source = member with the highest pct where pct > over_above (and not marked unresolvable)
   *  2. candidates = source's MOVABLE tasks (backlog/todo) in buckets od/wk/nd, not in opts.ignore;
   *     order: largest weight that is ≤ excess first; otherwise smallest weight above excess;
   *     ties → overdue first → earlier due date → id
   *  3. target = another member with can_receive !== false, open < wip_limit (if set),
   *     and (target.load + w) / target.capacity * 100 ≤ target_max;
   *     order: lowest pct − 10 if the target already has an open task with the same label (affinity) → name
   *  4. overdue task → propose new due date = first day d in [0, window) where target's tasks due that day
   *     weigh ≤ max(ceil(capacity / daily_share), w) − w; fallback = today + window − 1
   *  5. apply in simulation, repeat until nobody is over, no move is possible, or max_suggestions reached
   */
  function suggestRebalance(data, capOverride, opts) {
    opts = opts || {};
    var st = Object.assign({}, DEFAULTS, data.settings || {});
    var today = parseYMD(data.today), win = st.window_days;
    var ignore = opts.ignore || {};
    var assign = {}; data.tasks.forEach(function (t) { assign[t.id] = t.assignee_id; });
    var dueOverride = {};
    var out = [], blocked = {}, unresolved = {};
    var byId = {}; data.members.forEach(function (m) { byId[m.id] = m; });

    function loads() { return computeLoads(data, capOverride, assign); }
    function dueOf(t) { return dueOverride[t.id] || t.due_date; }

    for (var guard = 0; guard < 50 && out.length < st.max_suggestions; guard++) {
      var rows = loads(), idx = {}; rows.forEach(function (r) { idx[r.member.id] = r; });
      var src = rows.filter(function (r) { return r.pct > st.over_above && !blocked[r.member.id]; })[0];
      if (!src) break;
      var excess = src.load - src.capacity;
      var cands = src.tasks.filter(function (t) {
        return MOVABLE[t.status] && !ignore[t.id] && bucketOf({ due_date: dueOf(t) }, today, win) !== 'later';
      }).sort(function (a, b) {
        var wa = weightOf(a, st), wb = weightOf(b, st);
        var ka = wa <= excess ? excess - wa : 100 + (wa - excess), kb = wb <= excess ? excess - wb : 100 + (wb - excess);
        if (ka !== kb) return ka - kb;
        var oa = bucketOf({ due_date: dueOf(a) }, today, win) === 'od' ? 0 : 1, ob = bucketOf({ due_date: dueOf(b) }, today, win) === 'od' ? 0 : 1;
        if (oa !== ob) return oa - ob;
        var da = dueOf(a) || '9999', db = dueOf(b) || '9999';
        if (da !== db) return da < db ? -1 : 1;
        return String(a.id) < String(b.id) ? -1 : 1;
      });
      var moved = false;
      for (var i = 0; i < cands.length && !moved; i++) {
        var t = cands[i], w = weightOf(t, st);
        var targets = rows.filter(function (r) {
          var m = r.member;
          if (m.id === src.member.id || m.can_receive === false) return false;
          if (m.wip_limit != null && r.open >= m.wip_limit) return false;
          return (r.load + w) / r.capacity * 100 <= st.target_max;
        }).map(function (r) {
          var aff = !!t.label && r.tasks.some(function (x) { return x.label === t.label; });
          return { r: r, aff: aff, score: r.pct - (aff ? 10 : 0) };
        }).sort(function (a, b) { return a.score - b.score || a.r.member.name.localeCompare(b.r.member.name, 'fa'); });
        if (!targets.length) continue;
        var tg = targets[0].r, wasOverdue = bucketOf({ due_date: dueOf(t) }, today, win) === 'od';
        var newDue = null;
        if (wasOverdue) {
          var dailyCap = Math.ceil(tg.capacity / st.daily_share);
          for (var d = 0; d < win && !newDue; d++) {
            var day = ymd(addDays(today, d));
            var dayLoad = tg.tasks.filter(function (x) { return dueOf(x) === day; }).reduce(function (s, x) { return s + weightOf(x, st); }, 0);
            if (dayLoad + w <= Math.max(dailyCap, w)) newDue = day;
          }
          newDue = newDue || ymd(addDays(today, win - 1));
        }
        var before = { from: src.pct, to: tg.pct };
        assign[t.id] = tg.member.id; if (newDue) dueOverride[t.id] = newDue;
        var after = loads(), a = {}; after.forEach(function (r) { a[r.member.id] = r.pct; });
        out.push({
          task_id: t.id, task_title: t.title, weight: w, label: t.label || null,
          from_id: src.member.id, from_name: src.member.name, to_id: tg.member.id, to_name: tg.member.name,
          from_before: before.from, from_after: a[src.member.id], to_before: before.to, to_after: a[tg.member.id],
          was_overdue: wasOverdue, new_due_date: newDue, affinity: targets[0].aff
        });
        moved = true;
      }
      if (!moved) {
        blocked[src.member.id] = true;
        var hasMovable = src.tasks.some(function (t) { return MOVABLE[t.status] && !ignore[t.id]; });
        unresolved[src.member.id] = { member_id: src.member.id, name: src.member.name, pct: src.pct,
          reason: hasMovable ? 'no_target' : 'nothing_movable' };
      }
    }
    // anyone still over after the cascade but never examined (max_suggestions reached) is not "unresolved"
    return { suggestions: out, unresolved: Object.keys(unresolved).map(function (k) { return unresolved[k]; }) };
  }

  // ======================= UI =======================
  function WLView(el, opts) {
    this.el = el; this.opts = opts || {};
    this.state = { data: null, cap: null, open: {}, ignore: {}, moved: {}, busy: false };
  }
  WLView.prototype.$ = function (k) { return this.el.querySelector('[data-wl="' + k + '"]'); };

  WLView.prototype.start = function () {
    this.bind();
    return this.load(true);
  };

  // (v7.10) بارگذاری/بارگذاری دوباره از وضعیت واقعی سرور؛ first = باز کردن کارت پربارترین نفر
  WLView.prototype.load = function (first) {
    var self = this;
    var p = this.opts.data ? Promise.resolve(this.opts.data) : this.api('GET', '');
    return p.then(function (data) {
      self.state.data = data;
      if (first || self.state.cap == null) self.state.cap = (data.settings && data.settings.capacity) || DEFAULTS.capacity;
      var rows = computeLoads(data, self.state.cap); if (first && rows[0]) self.state.open[rows[0].member.id] = true;
      var hint = self.$('drag-hint'); if (hint) hint.hidden = !data.can_manage;
      self.$('project-title').textContent = data.project.title;
      if (data.project.board_url) self.$('link-board').href = data.project.board_url;
      if (data.project.analytics_url) self.$('link-analytics').href = data.project.analytics_url;
      var rng = self.$('cap-range'); rng.value = self.state.cap; rng.disabled = !data.can_manage;
      self.render();
    }).catch(function (e) { self.$('list').innerHTML = '<div class="wl-empty">' + esc((e && e.message) || 'بارگذاری بار کاری ناموفق بود.') + '</div>'; });
  };

  WLView.prototype.api = function (method, path, body) {
    var o = this.opts;
    if (!o.endpoint) return Promise.resolve({ ok: true }); // preview mode
    return fetch(o.endpoint + path, {
      method: method, credentials: 'same-origin',
      headers: Object.assign({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body ? { 'Content-Type': 'application/json' } : {}, o.csrf ? { 'X-CSRF-Token': o.csrf } : {}),
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      return r.json().catch(function () { return { success: false, message: r.status === 429 ? 'درخواست‌ها بیش از حد سریع است؛ چند لحظه بعد دوباره تلاش کنید.' : 'جابه‌جایی انجام نشد.' }; })
        .then(function (j) { if (!r.ok || j.success === false || j.status === 'error') throw j; return j.data || j; });
    });
  };

  WLView.prototype.render = function () {
    var s = this.state, d = s.data, st = Object.assign({}, DEFAULTS, d.settings || {});
    var rows = computeLoads(d, s.cap);
    var res = suggestRebalance(d, s.cap, { ignore: s.ignore });
    var over = rows.filter(function (r) { return r.level === 'bd'; }).length, free = rows.filter(function (r) { return r.level === 'ok'; }).length;
    var today = parseYMD(d.today), openTasks = d.tasks.filter(function (t) { return OPEN[t.status]; });
    var odCount = openTasks.filter(function (t) { return bucketOf(t, today, st.window_days) === 'od'; }).length;

    this.$('cap-value').textContent = fa(s.cap) + ' امتیاز';
    this.$('summary').innerHTML =
      '<div class="wl-pill"><b>' + fa(rows.length) + '</b>عضو</div>' +
      '<div class="wl-pill"><b>' + fa(openTasks.length) + '</b>تسک باز</div>' +
      '<div class="wl-pill bd"><b>' + fa(over) + '</b>نفر پربار</div>' +
      '<div class="wl-pill ok"><b>' + fa(free) + '</b>نفر ظرفیت آزاد</div>' +
      '<div class="wl-pill bd"><b>' + fa(odCount) + '</b>تسک عقب‌افتاده</div>';

    // info popover example
    var top = rows[0];
    this.$('info-example').innerHTML = top ? 'مثال از همین پروژه — <b>' + esc(top.member.name) + '</b>: عقب‌افتاده ' + fa(top.od) + ' + این هفته ' + fa(top.wk) + ' + بدون سررسید ' + fa(top.nd) + ' = <b>' + fa(top.load) + ' امتیاز</b>؛ ' + fa(top.load) + ' ÷ ' + fa(top.capacity) + ' = <b>' + fa(top.pct) + '٪</b>' + (top.load > top.capacity ? ' (' + fa(top.load - top.capacity) + ' امتیاز بیش از ظرفیت)' : '') : '';

    this.renderSuggestions(res, d);
    this.renderList(rows, d, st);
  };

  WLView.prototype.renderSuggestions = function (res, d) {
    var box = this.$('suggestions'), s = this.state;
    var hasOver = computeLoads(d, s.cap).some(function (r) { return r.level === 'bd'; });
    if (!res.suggestions.length && !res.unresolved.length) { box.hidden = !hasOver; if (hasOver) box.innerHTML = '<div class="wl-sug-none">پیشنهادی نیست.</div>'; if (!hasOver) box.innerHTML = ''; return; }
    box.hidden = false;
    var can = d.can_manage;
    var html = '<div class="wl-sug-head"><b><i class="fa-solid fa-wand-magic-sparkles"></i> پیشنهاد توزیع دوباره</b><span class="wl-muted">پیشنهادها به ترتیب‌اند؛ هرکدام فرض می‌کند قبلی‌ها اعمال شده‌اند.</span>' +
      (can && res.suggestions.length > 1 ? '<button type="button" class="wl-btn pri" data-act="apply-all"><i class="fa-solid fa-check-double"></i> اعمال همه (' + fa(res.suggestions.length) + ')</button>' : '') + '</div>';
    html += res.suggestions.map(function (g, i) {
      var why = [];
      why.push('تسک هنوز شروع نشده (' + (g.weight === 3 ? 'اولویت زیاد' : g.weight === 1 ? 'اولویت کم' : 'اولویت متوسط') + '، ' + fa(g.weight) + ' امتیاز)');
      if (g.affinity && g.label) why.push(esc(g.to_name) + ' روی لیبل «' + esc(g.label) + '» هم کار دارد');
      if (g.was_overdue) why.push('عقب‌افتاده است؛ سررسید تازه: ' + faDate(parseYMD(g.new_due_date)));
      function lv(p) { return p > 100 ? 'bd' : p >= 70 ? 'wr' : 'ok'; }
      return '<div class="wl-sug-row"><div class="wl-sug-t">' + fa(i + 1) + '. «' + esc(g.task_title) + '» از <b>' + esc(g.from_name) + '</b> به <b>' + esc(g.to_name) + '</b>' +
        '<span class="wl-sug-why">' + why.join('؛ ') + '</span>' +
        '<span class="wl-sug-delta"><span class="wl-delta">' + esc(g.from_name) + ': <span class="a">' + fa(g.from_before) + '٪</span> ← <span class="b ' + lv(g.from_after) + '">' + fa(g.from_after) + '٪</span></span>' +
        '<span class="wl-delta">' + esc(g.to_name) + ': ' + fa(g.to_before) + '٪ ← <span class="b ' + lv(g.to_after) + '">' + fa(g.to_after) + '٪</span></span></span></div>' +
        '<div class="wl-sug-acts">' + (can ? '<button type="button" class="wl-btn pri" data-act="apply" data-i="' + i + '">اعمال</button><button type="button" class="wl-btn ghost" data-act="dismiss" data-task="' + esc(g.task_id) + '">رد</button>' : '') + '</div></div>';
    }).join('');
    html += res.unresolved.map(function (u) {
      return '<div class="wl-sug-row"><div class="wl-sug-t"><b>' + esc(u.name) + '</b> (' + fa(u.pct) + '٪)<span class="wl-sug-why">' +
        (u.reason === 'nothing_movable' ? 'همه‌ی تسک‌های این نفر شروع شده‌اند یا در حال اصلاح‌اند؛ جابه‌جایی پیشنهاد نمی‌شود. سررسیدها یا ظرفیت را بازبینی کنید.' : 'هیچ عضوی ظرفیت کافی برای گرفتن کار این نفر ندارد؛ ظرفیت تیم کم است یا باید عضو جدید اضافه شود.') +
        '</span></div><div></div></div>';
    }).join('');
    box.innerHTML = html;
    this._sug = res.suggestions;
  };

  WLView.prototype.renderList = function (rows, d, st) {
    var s = this.state, today = parseYMD(d.today), win = st.window_days, can = d.can_manage;
    var maxScale = Math.max(s.cap * 1.25, Math.max.apply(null, rows.map(function (r) { return r.load; }).concat([0])) * 1.05);
    function dueBadge(t) {
      if (!t.due_date) return '<span class="wl-due n">بدون سررسید</span>';
      var dd = diffDays(parseYMD(t.due_date), today);
      if (dd < 0) return '<span class="wl-due bd">' + fa(-dd) + ' روز تأخیر</span>';
      if (dd === 0) return '<span class="wl-due wr">امروز</span>';
      if (dd === 1) return '<span class="wl-due wr">فردا</span>';
      return '<span class="wl-due ' + (dd < win ? 'p' : 'n') + '">' + faDate(parseYMD(t.due_date)) + '</span>';
    }
    function row(t) {
      var w = weightOf(t, st), mv = s.moved[t.id];
      return '<div class="wl-tr' + (mv ? ' is-moved' : '') + '"' + (can ? ' draggable="true"' : '') + ' data-task="' + esc(t.id) + '">' +
        '<span class="wl-pr p' + w + '"></span><div class="wl-tt"><a href="' + esc(t.url || '#') + '" data-open-task="' + esc(t.id) + '">' + esc(t.title) + '</a>' +
        '<span class="m">' + (t.label ? '<i class="fa-solid fa-tag"></i> ' + esc(t.label) : 'بدون لیبل') + '<span class="wl-st">' + esc(t.status_label || t.status) + '</span>' + (mv ? '<span class="wl-st">قبلاً: ' + esc(mv) + '</span>' : '') + '</span></div>' +
        dueBadge(t) + '<span class="wl-pts">' + fa(w) + ' امتیاز</span></div>';
    }
    function group(title, cls, arr, note) {
      if (!arr.length) return '';
      return '<div class="wl-grp"><div class="wl-gh ' + cls + '">' + title + ' <span class="n">' + fa(arr.length) + '</span>' + (note ? '<span>' + note + '</span>' : '') + '</div>' + arr.map(row).join('') + '</div>';
    }
    var html = rows.map(function (r) {
      var id = r.member.id, b = { od: [], wk: [], nd: [], later: [] };
      r.tasks.forEach(function (t) { b[bucketOf(t, today, win)].push(t); });
      Object.keys(b).forEach(function (k) { b[k].sort(function (x, y) { return (x.due_date || '9999').localeCompare(y.due_date || '9999') || weightOf(y, st) - weightOf(x, st); }); });
      var extra = r.load - r.capacity;
      return '<article class="wl-pc' + (s.open[id] ? ' is-open' : '') + '" data-member="' + esc(id) + '">' +
        '<button type="button" class="wl-ph" aria-expanded="' + (s.open[id] ? 'true' : 'false') + '" data-act="toggle" data-member="' + esc(id) + '">' +
        '<span class="wl-av">' + esc(r.member.name.charAt(0)) + '</span>' +
        '<span class="wl-nm">' + esc(r.member.name) + '<small>' + fa(r.open) + ' تسک باز' + (b.later.length ? '، ' + fa(b.later.length) + ' تسک دورتر' : '') + (r.member.capacity ? '، ظرفیت اختصاصی ' + fa(r.member.capacity) : '') + '</small></span>' +
        '<span class="wl-mcol"><span class="wl-meter" role="img" aria-label="' + fa(r.load) + ' از ' + fa(r.capacity) + ' امتیاز">' +
        '<span class="wl-seg od" data-r="0" data-w="' + (r.od / maxScale * 100) + '"></span>' +
        '<span class="wl-seg wk" data-r="' + (r.od / maxScale * 100) + '" data-w="' + (r.wk / maxScale * 100) + '"></span>' +
        '<span class="wl-seg nd" data-r="' + ((r.od + r.wk) / maxScale * 100) + '" data-w="' + (r.nd / maxScale * 100) + '"></span>' +
        '<span class="wl-capline" data-r="' + (r.capacity / maxScale * 100) + '"></span></span>' +
        '<span class="wl-mtx">' + fa(r.load) + ' از ' + fa(r.capacity) + ' امتیاز: عقب‌افتاده ' + fa(r.od) + '، این هفته ' + fa(r.wk) + '، بدون سررسید ' + fa(r.nd) +
        (extra > 0 ? ' — <span class="over">' + fa(extra) + ' امتیاز بیش از ظرفیت</span>' : ' — ' + fa(-extra) + ' امتیاز جای خالی') + '</span></span>' +
        '<span class="wl-lvl ' + r.level + '">' + fa(r.pct) + '٪<small>' + (r.level === 'bd' ? 'پربار' : r.level === 'wr' ? 'متعادل' : 'ظرفیت آزاد') + '</small></span>' +
        '<i class="fa-solid fa-chevron-down wl-chev"></i></button>' +
        '<div class="wl-pb">' + group('عقب‌افتاده', 'bd', b.od) + group('سررسید در ۷ روز آینده', '', b.wk) + group('بدون سررسید', '', b.nd) + group('بعد از ۷ روز', '', b.later, '(در بار این هفته حساب نمی‌شود)') +
        (r.tasks.length ? '' : '<div class="wl-empty">تسک بازی در این پروژه ندارد.</div>') + '</div></article>';
    }).join('');
    var list = this.$('list');
    list.innerHTML = html || '<div class="wl-empty">این پروژه عضوی ندارد.</div>';
    Array.prototype.forEach.call(list.querySelectorAll('[data-r]'), function (e) {
      e.style.right = e.getAttribute('data-r') + '%';
      if (e.hasAttribute('data-w')) e.style.width = e.getAttribute('data-w') + '%';
    });
  };

  // ---------- actions ----------
  WLView.prototype.reassign = function (taskId, toId, newDue, opt) {
    var self = this, d = this.state.data, t = d.tasks.find(function (x) { return String(x.id) === String(taskId); });
    if (!t || String(t.assignee_id) === String(toId)) return Promise.resolve(true);
    var prev = { assignee_id: t.assignee_id, due_date: t.due_date };
    var fromName = (d.members.find(function (m) { return String(m.id) === String(t.assignee_id); }) || {}).name;
    var toName = (d.members.find(function (m) { return String(m.id) === String(toId); }) || {}).name;
    return this.api('POST', '/reassign', { task_id: t.id, assignee_id: toId, due_date: newDue || undefined, source: opt && opt.source })
      .then(function () {
        t.assignee_id = d.members.find(function (m) { return String(m.id) === String(toId); }).id;
        if (newDue) t.due_date = newDue;
        self.state.moved[t.id] = self.state.moved[t.id] || fromName;
        self.state.open[t.assignee_id] = true;
        self.render();
        // کشیدن و رها کردن مجاز است حتی اگر مقصد بالای ۱۰۰٪ برود، ولی هشدار می‌دهیم
        var tr = computeLoads(d, self.state.cap).find(function (r) { return String(r.member.id) === String(toId); });
        var warn = opt && opt.source === 'drag' && tr && tr.pct > 100 ? ' — هشدار: بار ' + toName + ' به ' + fa(tr.pct) + '٪ رسید' : '';
        if (!(opt && opt.silent)) self.toast('«' + t.title + '» به ' + toName + ' واگذار شد' + (newDue ? '، سررسید: ' + faDate(parseYMD(newDue)) : '') + warn, function () {
          self.api('POST', '/reassign', { task_id: t.id, assignee_id: prev.assignee_id, due_date: prev.due_date || null, source: 'undo' }).then(function () {
            t.assignee_id = prev.assignee_id; t.due_date = prev.due_date; delete self.state.moved[t.id]; self.render();
            self.toast('بازگردانی شد.');
          }).catch(function (e) { self.toast((e && e.message) || 'بازگردانی انجام نشد.', null, 'error'); self.load(false); });
        }, warn ? 'warning' : 'success');
        return true;
      })
      .catch(function (e) {
        if (opt && opt.silent) throw e;
        self.toast((e && e.message) || 'جابه‌جایی انجام نشد.', null, 'error'); self.render(); return false;
      });
  };

  WLView.prototype.applyAll = function () {
    var self = this, list = (this._sug || []).slice(), i = 0;
    // پشت سر هم؛ با اولین شکست متوقف می‌شود، پیام می‌دهد و از وضعیت واقعی سرور دوباره محاسبه می‌کند
    function next() {
      if (i >= list.length) { self.toast(fa(list.length) + ' پیشنهاد اعمال شد'); return; }
      var g = list[i++];
      return self.reassign(g.task_id, g.to_id, g.new_due_date, { silent: true, source: 'suggestion' }).then(next, function (e) {
        self.toast((i > 1 ? fa(i - 1) + ' پیشنهاد اعمال شد؛ ' : '') + 'پیشنهاد «' + g.task_title + '» اعمال نشد: ' + ((e && e.message) || 'جابه‌جایی انجام نشد.'), null, 'error');
        return self.load(false);
      });
    }
    return next();
  };

  WLView.prototype.saveCap = function () {
    var self = this; clearTimeout(this._capT);
    this._capT = setTimeout(function () {
      self.api('POST', '/capacity', { capacity: self.state.cap }).then(function () {
        if (self.state.data && self.state.data.settings) self.state.data.settings.capacity = self.state.cap;
        var s = self.$('cap-saved'); s.hidden = false; clearTimeout(self._savedT); self._savedT = setTimeout(function () { s.hidden = true; }, 1500);
      }).catch(function (e) { self.toast((e && e.message) || 'ذخیره‌ی ظرفیت انجام نشد.', null, 'error'); });
    }, 600);
  };

  WLView.prototype.toast = function (msg, undo, type) {
    // در پنل از toast مشترک (TK.toast با دکمه‌ی اقدام) استفاده می‌شود؛ [data-wl="toast"] فقط برای پیش‌نمایش مستقل
    if (global.TK && global.TK.toast) { global.TK.toast(msg, type || 'success', undo ? 7000 : 5000, undo ? { action: { label: 'بازگردانی', onClick: undo } } : {}); return; }
    var t = document.querySelector('[data-wl="toast"]'); if (!t) return;
    t.innerHTML = '<span>' + esc(msg) + '</span>' + (undo ? '<button type="button">بازگردانی</button>' : '');
    t.hidden = false; clearTimeout(this._toastT);
    if (undo) t.querySelector('button').onclick = function () { t.hidden = true; undo(); };
    this._toastT = setTimeout(function () { t.hidden = true; }, 5000);
  };

  // ---------- info popover ----------
  WLView.prototype.togglePop = function (open) {
    var btn = this.$('info-btn'), pop = this.$('info-pop');
    var willOpen = open == null ? pop.hidden : open;
    pop.hidden = !willOpen; btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) this.$('info-close').focus(); else btn.focus({ preventScroll: true });
  };

  // ---------- events ----------
  WLView.prototype.bind = function () {
    var self = this, el = this.el;
    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-wl="info-btn"]')) { e.stopPropagation(); return self.togglePop(); }
      if (e.target.closest('[data-wl="info-close"]')) return self.togglePop(false);
      var a = e.target.closest('[data-act]'); if (!a) return;
      var act = a.getAttribute('data-act');
      if (act === 'toggle') {
        var id = a.getAttribute('data-member'), card = a.closest('.wl-pc');
        var key = Object.keys(self.state.open).find(function (k) { return String(k) === id; }) || id;
        self.state.open[key] = !self.state.open[key]; card.classList.toggle('is-open', !!self.state.open[key]); a.setAttribute('aria-expanded', self.state.open[key] ? 'true' : 'false');
      }
      if (act === 'apply') { var g = self._sug[+a.getAttribute('data-i')]; a.disabled = true; self.reassign(g.task_id, g.to_id, g.new_due_date, { source: 'suggestion' }).then(function (ok) { if (!ok) a.disabled = false; }); }
      if (act === 'apply-all') { a.disabled = true; self.applyAll(); }
      if (act === 'dismiss') { self.state.ignore[a.getAttribute('data-task')] = true; self.render(); }
    });
    document.addEventListener('click', function (e) {
      var pop = self.$('info-pop'); if (pop && !pop.hidden && !e.target.closest('.wl-info')) self.togglePop(false);
    });
    document.addEventListener('keydown', function (e) { var pop = self.$('info-pop'); if (e.key === 'Escape' && pop && !pop.hidden) self.togglePop(false); });
    var rng = this.$('cap-range');
    rng.addEventListener('input', function () { self.state.cap = +rng.value; self.render(); });
    rng.addEventListener('change', function () { self.saveCap(); });
    // drag & drop reassign (managers only)
    var dragId = null;
    el.addEventListener('dragstart', function (e) { var r = e.target.closest('.wl-tr[draggable="true"]'); if (!r) return; dragId = r.getAttribute('data-task'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', dragId); });
    el.addEventListener('dragover', function (e) { var c = e.target.closest('.wl-pc'); if (!c || !dragId) return; e.preventDefault(); el.querySelectorAll('.wl-pc.is-drop').forEach(function (x) { if (x !== c) x.classList.remove('is-drop'); }); c.classList.add('is-drop'); });
    el.addEventListener('drop', function (e) { var c = e.target.closest('.wl-pc'); if (!c || !dragId) return; e.preventDefault(); c.classList.remove('is-drop'); self.reassign(dragId, c.getAttribute('data-member'), null, { source: 'drag' }); dragId = null; });
    el.addEventListener('dragend', function () { dragId = null; el.querySelectorAll('.wl-pc.is-drop').forEach(function (x) { x.classList.remove('is-drop'); }); });
  };

  // ======================= sample data (preview) =======================
  function sampleData() {
    var t0 = new Date(); t0.setHours(0, 0, 0, 0);
    function due(n) { return n == null ? null : ymd(addDays(t0, n)); }
    var M = [
      { id: 1, name: 'الهام کریمی' }, { id: 2, name: 'مریم حسینی' }, { id: 3, name: 'سینا محمدی' }, { id: 4, name: 'آرش رضایی' },
      { id: 5, name: 'نیلوفر احمدی' }, { id: 6, name: 'کیان اکبری', wip_limit: 8 }, { id: 7, name: 'ترانه امینی', capacity: 8 }
    ];
    var L = { st: 'وضعیت', backlog: 'برنامه‌ریزی‌نشده', todo: 'آماده انجام', in_progress: 'در حال انجام', rework: 'بازبینی مسئول' };
    var raw = [
      [1, 'باگ آپلود تصویر محصول', 'medium', 'backlog', -21, 'رفع باگ'], [1, 'مرتب‌سازی اشتباه جستجو', 'low', 'todo', -12, 'رفع باگ'],
      [1, 'جلسه‌ی برنامه‌ریزی اسپرینت', 'high', 'in_progress', -9, 'جلسه و هماهنگی'], [1, 'طراحی ایمیل تأیید سفارش', 'low', 'todo', 2, 'طراحی رابط کاربری'],
      [1, 'مستند API سبد خرید', 'medium', 'backlog', null, 'مستندسازی'], [1, 'تست صفحه‌ی پرداخت', 'high', 'todo', 4, 'تست و کنترل کیفیت'], [1, 'بازطراحی فرم ثبت‌نام', 'medium', 'backlog', null, 'طراحی رابط کاربری'], [1, 'به‌روزرسانی کتابخانه‌ها', 'medium', 'backlog', 5, 'بک‌اند و API'],
      [2, 'اتصال به درگاه پرداخت', 'high', 'todo', -5, 'بک‌اند و API'], [2, 'API لیست محصولات', 'medium', 'in_progress', -3, 'بک‌اند و API'],
      [2, 'خطای ۵۰۰ هنگام ثبت سفارش', 'high', 'rework', 1, 'رفع باگ'], [2, 'کش جستجو', 'medium', 'backlog', 3, 'بک‌اند و API'], [2, 'وب‌هوک انبار', 'medium', 'todo', null, 'بک‌اند و API'], [2, 'لاگ خطاها', 'low', 'backlog', 12, 'بک‌اند و API'], [2, 'صفحه‌ی مقایسه‌ی محصولات', 'high', 'todo', null, 'طراحی رابط کاربری'],
      [3, 'رفع باگ صفحه‌ی پرداخت', 'high', 'in_progress', -2, 'رفع باگ'], [3, 'تاریخ شمسی در فاکتور', 'medium', 'in_progress', 0, 'بک‌اند و API'], [3, 'بهینه‌سازی تصاویر', 'medium', 'rework', 2, 'رفع باگ'], [3, 'گزارش فروش هفتگی', 'high', 'in_progress', 5, 'بک‌اند و API'], [3, 'بازبینی کد ماژول سفارش', 'medium', 'in_progress', null, 'تست و کنترل کیفیت'], [3, 'رفع نشت حافظه', 'high', 'in_progress', null, 'رفع باگ'],
      [4, 'جلسه‌ی دمو با مشتری', 'medium', 'todo', 1, 'جلسه و هماهنگی'], [4, 'محدودیت نرخ درخواست', 'medium', 'in_progress', 3, 'بک‌اند و API'], [4, 'تست بار سرور', 'low', 'backlog', 9, 'تست و کنترل کیفیت'],
      [5, 'طراحی حالت آفلاین', 'medium', 'todo', 2, 'طراحی رابط کاربری'], [5, 'پیام‌های خطای فرم', 'low', 'backlog', null, 'طراحی رابط کاربری'],
      [6, 'تست رگرسیون نسخه ۲', 'low', 'todo', 6, 'تست و کنترل کیفیت'],
      [7, 'آیکن‌های جدید اپ', 'low', 'todo', 4, 'طراحی رابط کاربری']
    ];
    return {
      today: ymd(t0),
      project: { id: 'vz4sucpz94n3', title: 'اپلیکیشن فروشگاه آنلاین', board_url: '/taskam/board?project=vz4sucpz94n3', analytics_url: '/taskam/projects/vz4sucpz94n3/analytics' },
      can_manage: true,
      settings: { capacity: 12 },
      members: M,
      tasks: raw.map(function (r, i) { return { id: 't' + (i + 1), assignee_id: r[0], title: r[1], priority: r[2], status: r[3], status_label: L[r[3]], due_date: due(r[4]), label: r[5], url: '#' }; })
    };
  }

  // ======================= public =======================
  var WL = {
    init: function (el, opts) { var v = new WLView(el, opts); v.start(); return v; },
    computeLoads: computeLoads, suggestRebalance: suggestRebalance, bucketOf: bucketOf, sampleData: sampleData, DEFAULTS: DEFAULTS
  };
  global.WL = WL;
  if (typeof module !== 'undefined' && module.exports) module.exports = WL;

  // auto-init (CSP-friendly: no inline <script>). در پنل با tk:ready (ناوبری نرم ماژول را دوباره اجرا می‌کند)؛
  // __wl جلوی init دوباره‌ی همان بخش را می‌گیرد.
  if (typeof document !== 'undefined') {
    var boot = function () {
      document.querySelectorAll('section.wl[data-endpoint]').forEach(function (s) {
        if (s.__wl) return;
        s.__wl = WL.init(s, { endpoint: s.getAttribute('data-endpoint'), csrf: s.getAttribute('data-csrf') || (global.TK && global.TK.csrf) });
      });
    };
    if (global.TK) document.addEventListener('tk:ready', boot);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
  }
})(typeof window !== 'undefined' ? window : globalThis);
