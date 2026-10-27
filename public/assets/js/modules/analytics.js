document.addEventListener('tk:ready', () => {
  const a = TK.boot().analytics; if (!a) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: a.trend.labels, series: [{ name: 'ایجادشده', data: a.trend.created, color: '#60a5fa' }, { name: 'انجام‌شده', data: a.trend.done }] });
  TK.charts.donut(TK.qs('#ch-prio'), { items: [{ label: 'زیاد', value: a.totals.high, color: '#f87171' }, { label: 'متوسط', value: a.totals.medium, color: '#fbbf24' }, { label: 'کم', value: a.totals.low, color: '#4ade80' }], center: TK.fa(a.totals.total) });
});
