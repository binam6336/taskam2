document.addEventListener('tk:ready', () => {
  const menu = document.querySelector('#an-reports');
  if (!menu) return;
  const base = menu.dataset.export;
  const project = menu.dataset.project;
  const status = document.querySelector('#an-report-status');
  const save = (blob, filename) => {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.append(link);
    link.click();
    setTimeout(() => { URL.revokeObjectURL(link.href); link.remove(); }, 60000);
  };
  const loadScript = (src, globalName) => new Promise((resolve, reject) => {
    if (window[globalName]) return resolve();
    const script = document.createElement('script');
    script.src = src;
    script.onload = resolve;
    script.onerror = reject;
    document.head.append(script);
  });
  const parseCsv = (source) => {
    const rows = []; let row = [], cell = '', quoted = false;
    for (let i = 0; i < source.length; i++) {
      const char = source[i];
      if (quoted && char === '"' && source[i + 1] === '"') { cell += '"'; i++; }
      else if (char === '"') quoted = !quoted;
      else if (!quoted && char === ',') { row.push(cell); cell = ''; }
      else if (!quoted && (char === '\n' || char === '\r')) {
        if (char === '\r' && source[i + 1] === '\n') i++;
        row.push(cell); rows.push(row); row = []; cell = '';
      } else cell += char;
    }
    if (cell || row.length) { row.push(cell); rows.push(row); }
    return rows;
  };
  const sheet = (workbook, name, headers, rows, numeric = []) => {
    const ws = workbook.addWorksheet(name, { views: [{ state: 'frozen', ySplit: 1, rightToLeft: true }] });
    const values = rows.length ? rows : [headers.map(() => '')];
    ws.addTable({ name: `Report${workbook.worksheets.length}`, ref: 'A1', headerRow: true,
      style: { theme: 'TableStyleMedium2', showRowStripes: true },
      columns: headers.map((x) => ({ name: x, filterButton: true })),
      rows: values.map((row) => headers.map((_, i) => numeric.includes(i) && row[i] !== '' && row[i] != null ? Number(row[i]) : String(row[i] ?? ''))),
    });
    headers.forEach((header, i) => {
      const width = Math.max(13, Math.min(55, Math.max(header.length, ...values.map((row) => String(row[i] ?? '').length)) + 3));
      ws.getColumn(i + 1).width = width;
      ws.getColumn(i + 1).alignment = { horizontal: numeric.includes(i) ? 'center' : 'right', vertical: 'middle', wrapText: true };
      if (numeric.includes(i)) ws.getColumn(i + 1).numFmt = '0.0#';
    });
    ws.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    return ws;
  };
  const excel = async () => {
    await loadScript(menu.dataset.excelScript, 'ExcelJS');
    const response = await fetch(base, { credentials: 'same-origin', headers: { Accept: 'text/csv' } });
    if (!response.ok) throw new Error('دریافت داده‌های گزارش ممکن نشد.');
    const rows = parseCsv((await response.text()).replace(/^\uFEFF/, ''));
    const book = new ExcelJS.Workbook();
    book.creator = 'کارلیکس';
    const info = book.addWorksheet('مشخصات');
    info.addRows([['پروژه', document.querySelector('.page-head h2').textContent], ['تاریخ گزارش', new Date()]]);
    info.getColumn(1).width = 22; info.getColumn(2).width = 55;
    info.getCell('B2').numFmt = 'yyyy/mm/dd hh:mm';
    const groups = []; let group = [];
    rows.slice(3).forEach((row) => { if (!row.length || row.every((x) => !x)) { if (group.length) groups.push(group); group = []; } else group.push(row); });
    if (group.length) groups.push(group);
    ['خلاصه', 'عملکرد اعضا', 'تأیید سرپرست'].forEach((name, i) => {
      const data = groups[i]; if (!data) return;
      const numeric = data[0].map((_, j) => j).filter((j) => i === 0 ? j < 10 : i === 1 ? j >= 2 && j <= 13 : j >= 1);
      sheet(book, name, data[0], data.slice(1), numeric);
    });
    const a = TK.boot().analytics;
    sheet(book, 'روند ۱۴ روزه', ['تاریخ', 'ثبت‌شده', 'تحویل‌شده'], a.trend.labels.map((label, i) => [label, a.trend.created[i], a.trend.done[i]]), [1, 2]);
    sheet(book, 'اولویت‌ها', ['اولویت', 'تعداد'], [['زیاد', a.totals.high], ['متوسط', a.totals.medium], ['کم', a.totals.low]], [1]);
    if (a.subjects.length) sheet(book, 'لیبل‌ها', ['لیبل', 'تعداد', 'درصد'], a.subjects.map((s) => [s.label, s.value, s.pct]), [1, 2]);
    const buffer = await book.xlsx.writeBuffer();
    save(new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), `karlix-${project}-${new Date().toISOString().slice(0, 10)}.xlsx`);
  };
  const pdf = async () => {
    await loadScript(menu.dataset.pdfScript, 'html2pdf');
    await document.fonts.ready;
    const root = document.createElement('div');
    root.className = 'an-pdf';
    root.dataset.mode = document.documentElement.dataset.mode || 'dark';
    const range = document.querySelector('#sp-range button.on')?.textContent || '';
    const metric = document.querySelector('#sp-metric button.on')?.textContent || '';
    root.innerHTML = `<header><h1>${document.querySelector('.page-head h2').textContent}</h1><p>گزارش تحلیل پروژه · ${new Intl.DateTimeFormat('fa-IR', { dateStyle: 'long' }).format(new Date())}</p><p>مقایسه سرعت · ${range} · ${metric}</p></header>`;
    const content = document.querySelector('.an-cards');
    for (let section = content; section; section = section.nextElementSibling) {
      const copy = section.cloneNode(true);
      copy.querySelectorAll('button, a.btn, .hint, .an-controls').forEach((node) => node.remove());
      root.append(copy);
    }
    document.body.append(root);
    try {
      await html2pdf().set({ margin: 10, filename: `karlix-${project}-${new Date().toISOString().slice(0, 10)}.pdf`,
        image: { type: 'jpeg', quality: .96 }, html2canvas: { scale: 2, useCORS: true, backgroundColor: root.dataset.mode === 'light' ? '#ffffff' : '#11151d' },
        pagebreak: { mode: ['css', 'legacy'], avoid: ['.card', '.an-row', '.an-kpi', 'tr'] },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }).from(root).toPdf().get('pdf').then((documentPdf) => {
        const pages = documentPdf.internal.getNumberOfPages();
        for (let page = 1; page <= pages; page++) {
          documentPdf.setPage(page);
          documentPdf.setFontSize(8);
          documentPdf.text(`${page} / ${pages}`, 105, 289, { align: 'center' });
        }
      }).save();
    } finally { root.remove(); }
  };
  menu.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-report]');
    if (!button) return;
    button.disabled = true;
    status.textContent = 'در حال آماده‌سازی گزارش…';
    try { await (button.dataset.report === 'excel' ? excel() : pdf()); status.textContent = 'گزارش آماده شد.'; }
    catch (error) { status.textContent = 'ساخت گزارش انجام نشد.'; TK.toast(error.message || status.textContent, 'error'); }
    finally { button.disabled = false; }
  });
});
