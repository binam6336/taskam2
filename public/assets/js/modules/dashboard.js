document.addEventListener('tk:ready', () => {
  const d = TK.boot().d; if (!d) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: d.trend.labels, series: [{ name: 'تسک‌های جدید', data: d.trend.tasks }] });
  const ratio = d.stats.total ? Math.round((d.stats.completed / d.stats.total) * 100) : 0;
  TK.charts.donut(TK.qs('#ch-ratio'), { items: [{ label: 'انجام‌شده', value: d.stats.completed, color: '#4ade80' }, { label: 'در جریان', value: d.stats.pending, color: '#fbbf24' }], center: TK.fa(ratio) + '٪' });
  TK.charts.bars(TK.qs('#ch-prio'), { height: 190, items: [{ label: 'زیاد', value: d.stats.high, color: '#f87171' }, { label: 'متوسط', value: d.stats.medium, color: '#fbbf24' }, { label: 'کم', value: d.stats.low, color: '#4ade80' }] });
});
