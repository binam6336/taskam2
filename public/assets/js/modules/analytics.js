document.addEventListener('tk:ready', () => {
  const a = TK.boot().analytics; if (!a) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: a.trend.labels, series: [{ name: 'ثبت‌شده', data: a.trend.created, color: '#3b82f6' }, { name: 'تحویل‌شده', data: a.trend.done, color: TK.charts.colors[0] }], empty: 'در ۱۴ روز اخیر تسکی ثبت یا تحویل نشده است' });
  TK.charts.donut(TK.qs('#ch-prio'), { items: [{ label: 'زیاد', value: a.totals.high, color: '#f87171' }, { label: 'متوسط', value: a.totals.medium, color: '#fbbf24' }, { label: 'کم', value: a.totals.low, color: TK.charts.colors[0] }], center: TK.fa(a.totals.total), empty: 'هنوز تسکی در این پروژه ثبت نشده است' });
  if (a.subjects?.length) TK.charts.donut(TK.qs('#ch-subjects'), { items: a.subjects.map((s) => ({ label: s.label, value: s.value, color: s.color, pct: s.pct })), center: '<div>' + TK.fa(a.label_count ?? a.subjects.length) + '<div class="donut-l">لیبل</div></div>', showPct: true });

  /* ───── مقایسه‌ی سرعت ───── */
  // پالت متمایز با کنتراست کافی در تم روشن و تیره (DATA-013: زرد کم‌رنگ حذف شد)
  const SC = [TK.charts.colors[0], '#0ea5e9', '#f97316', '#db2777', '#8b5cf6'];
  const st = { range: a.speed.range, metric: a.speed.metric };
  const draw = (d) => TK.charts.line(TK.qs('#ch-speed'), {
    // DATA-013: بدون connect؛ بازه‌ی بی‌تحویل جای خالی در خط است (نه روند ساختگی)
    labels: d.labels, height: 240, max: d.metric === 'score' ? 100 : null, connect: false, emptyZero: d.metric === 'count',
    empty: 'در این بازه هیچ‌کدام از اعضای انتخاب‌شده تسکی تحویل نداده‌اند',
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
