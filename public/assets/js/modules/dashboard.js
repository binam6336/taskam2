document.addEventListener('tk:ready', () => {
  // «منتظر اقدام» ← برگرداندن تسک «بررسی سرپرست» برای اصلاح، بدون باز کردن تسک
  TK.on('click', '[data-dash-rework]', async (e, b) => {
    e.preventDefault();
    const why = await TK.prompt('چه چیزی باید اصلاح شود؟ (اختیاری؛ به‌صورت یادداشت روی تسک ثبت و برای مسئول فرستاده می‌شود)', { title: 'برگرداندن برای اصلاح', okText: 'برگرداندن', multiline: true });
    if (why === null) return;
    const r = await TK.post('/tasks/' + b.dataset.dashRework + '/status', { status: 'rework', reason: why });
    TK.toast(r.message, TK.ok(r) ? 'success' : 'error'); if (TK.ok(r)) TK.reload();
  });
  const d = TK.boot().d; if (!d) return;
  TK.charts.line(TK.qs('#ch-trend'), { labels: d.trend.labels, series: [{ name: 'تسک‌های جدید', data: d.trend.tasks }] });
  const ratio = d.stats.total ? Math.round((d.stats.completed / d.stats.total) * 100) : 0;
  TK.charts.donut(TK.qs('#ch-ratio'), { items: [{ label: 'انجام‌شده', value: d.stats.completed, color: TK.charts.colors[0] }, { label: 'در جریان', value: d.stats.pending, color: '#fbbf24' }], center: TK.fa(ratio) + '٪' });
  const po = d.prio_open || d.stats;   // پایه‌ی شمارش: فقط تسک‌های باز (همان تب «در جریان»)
  TK.charts.bars(TK.qs('#ch-prio'), { height: 190, items: [{ label: 'زیاد', value: po.high, color: '#f87171' }, { label: 'متوسط', value: po.medium, color: '#fbbf24' }, { label: 'کم', value: po.low, color: TK.charts.colors[0] }] });
});
