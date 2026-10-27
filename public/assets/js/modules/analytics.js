document.addEventListener('tk:ready', () => {
  const a = TK.boot().analytics; if (!a) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: a.trend.labels, series: [{ name: 'ایجادشده', data: a.trend.created, color: '#60a5fa' }, { name: 'انجام‌شده', data: a.trend.done, color: TK.charts.colors[0] }] });
  TK.charts.donut(TK.qs('#ch-prio'), { items: [{ label: 'زیاد', value: a.totals.high, color: '#f87171' }, { label: 'متوسط', value: a.totals.medium, color: '#fbbf24' }, { label: 'کم', value: a.totals.low, color: TK.charts.colors[0] }], center: TK.fa(a.totals.total) });
  if (a.subjects?.length) TK.charts.donut(TK.qs('#ch-subjects'), { items: a.subjects.map((s) => ({ label: s.label, value: s.value, color: s.color })), center: TK.fa(a.subjects.length) + '<div class="donut-l">موضوع</div>', showPct: true });

  /* ───── مقایسه‌ی سرعت ───── */
  const SC = [TK.charts.colors[0], '#60a5fa', '#fbbf24', '#f87171', '#c084fc'];
  const st = { range: a.speed.range, metric: a.speed.metric };
  const draw = (d) => TK.charts.line(TK.qs('#ch-speed'), {
    labels: d.labels, height: 240, max: d.metric === 'score' ? 100 : null,
    series: d.series.map((s, i) => ({ name: s.name, data: s.data, color: SC[i % SC.length] })),
  });
  const box = TK.qs('#sp-people');
  const users = () => TK.qsa('input:checked', box).map((i) => i.value);
  const load = async () => {
    if (!users().length) { TK.qs('#ch-speed').innerHTML = '<p class="muted center">حداقل یک کاربر را انتخاب کنید.</p>'; return; }
    const r = await TK.get('/projects/' + a.id + '/analytics/speed', { users: users().join(','), range: st.range, metric: st.metric });
    if (TK.ok(r)) draw(r); else TK.toast(r.message || 'خطا', 'error');
  };
  draw(a.speed);
  box?.addEventListener('change', (e) => {
    if (users().length > 5) { e.target.checked = false; return TK.toast('حداکثر ۵ کاربر را می‌توانید مقایسه کنید.', 'warning'); }
    load();
  });
  const seg = (id, key) => TK.qs(id)?.addEventListener('click', (e) => {
    const b = e.target.closest('button'); if (!b) return;
    TK.qsa('button', b.parentElement).forEach((x) => x.classList.toggle('on', x === b));
    st[key] = b.dataset.v; load();
  });
  seg('#sp-range', 'range'); seg('#sp-metric', 'metric');
});
