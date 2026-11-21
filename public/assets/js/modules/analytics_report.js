document.addEventListener('tk:ready', () => {
  const menu = document.querySelector('#an-reports');
  if (!menu) return;
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
  const reportRows = () => {
    const a = TK.boot().analytics;
    const speed = TK.analyticsSpeed || a.speed;
    return [...a.reportRows, [], ['تنظیم مقایسه', 'مقدار'], ['بازه', speed.range], ['معیار', speed.metric], [],
      ['تاریخ', ...speed.series.map((x) => x.name)],
      ...speed.labels.map((label, i) => [label, ...speed.series.map((x) => x.data[i])])];
  };
  const csv = () => {
    const text = reportRows().map((row) => row.map((value) => {
      let text = String(value ?? '');
      if (/^[=+@-]/.test(text)) text = "'" + text;
      return '"' + text.replace(/"/g, '""') + '"';
    }).join(',')).join('\r\n');
    save(new Blob(['\uFEFF', text], { type: 'text/csv;charset=utf-8' }), `karlix-${project}.csv`);
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
      const width = Math.max(13, Math.min(55, values.reduce((max, row) => Math.max(max, String(row[i] ?? '').length), header.length) + 3));
      ws.getColumn(i + 1).width = width;
      ws.getColumn(i + 1).alignment = { horizontal: numeric.includes(i) ? 'center' : 'right', vertical: 'middle', wrapText: true };
      if (numeric.includes(i)) ws.getColumn(i + 1).numFmt = '0.0#';
    });
    ws.getRow(1).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    return ws;
  };
  const excel = async () => {
    await loadScript(menu.dataset.excelScript, 'ExcelJS');
    const book = new ExcelJS.Workbook();
    book.creator = 'کارلیکس';
    const groups = []; let group = [];
    reportRows().forEach((row) => {
      if (!row.length) { if (group.length) groups.push(group); group = []; }
      else group.push(row);
    });
    if (group.length) groups.push(group);
    groups.forEach((data, i) => {
      const headers = data[0].map((value, j) => String(value || `ستون ${j + 1}`));
      const rows = data.slice(1);
      const numeric = headers.map((_, j) => j).filter((j) => rows.some((row) => typeof row[j] === 'number'));
      const ws = sheet(book, `گزارش ${i + 1}`, headers, rows, numeric);
      headers.forEach((header, j) => {
        if (header.includes('٪') || header === 'درصد') {
          ws.getColumn(j + 1).numFmt = '0.0%';
          for (let r = 2; r <= ws.rowCount; r++) {
            const cell = ws.getCell(r, j + 1);
            if (typeof cell.value === 'number') cell.value /= 100;
          }
        }
      });
    });
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
    root.innerHTML = `<header><h1></h1><p>گزارش تحلیل پروژه · ${new Intl.DateTimeFormat('fa-IR', { dateStyle: 'long' }).format(new Date())}</p><p>مقایسه سرعت · ${range} · ${metric}</p></header>`;
    root.querySelector('h1').textContent = document.querySelector('.page-head h2').textContent;
    const content = document.querySelector('.an-cards');
    for (let section = content; section; section = section.nextElementSibling) {
      const copy = section.cloneNode(true);
      copy.querySelectorAll('button, a.btn, .hint, .an-controls').forEach((node) => node.remove());
      copy.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
      copy.querySelectorAll('details').forEach((node) => { node.open = true; });
      root.append(copy);
    }
    document.body.append(root);
    try {
      await html2pdf().set({ margin: 10, filename: `karlix-${project}-${new Date().toISOString().slice(0, 10)}.pdf`,
        image: { type: 'jpeg', quality: .96 }, html2canvas: { scale: 2, useCORS: true, backgroundColor: root.dataset.mode === 'light' ? '#ffffff' : '#11151d' },
        pagebreak: { mode: ['css', 'legacy'], avoid: ['.card', '.an-row', '.an-kpi', 'tr'] },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }).from(root).toCanvas().get('canvas').then((canvas) => {
        if (!canvas.width || !canvas.height || canvas.height > 32000) throw new Error('اندازه گزارش برای PDF مناسب نیست.');
        const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        let changed = false;
        for (let i = 0; i < pixels.length; i += 4) {
          if (pixels[i] !== pixels[0] || pixels[i + 1] !== pixels[1] || pixels[i + 2] !== pixels[2]) { changed = true; break; }
        }
        if (!changed) throw new Error('رندر گزارش خالی است؛ فایل تولید نشد.');
      }).toPdf().get('pdf').then((documentPdf) => {
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
    event.preventDefault();
    button.disabled = true;
    status.textContent = 'در حال آماده‌سازی گزارش…';
    try { await (button.dataset.report === 'excel' ? excel() : button.dataset.report === 'csv' ? csv() : pdf()); status.textContent = 'گزارش آماده شد.'; }
    catch (error) { status.textContent = 'ساخت گزارش انجام نشد.'; TK.toast(error.message || status.textContent, 'error'); }
    finally { button.disabled = false; }
  });
});
