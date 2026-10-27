document.addEventListener('tk:ready', () => {
  const cfg = TK.boot().docs; if (!cfg) return;
  const E = TK.esc;
  const getTok = () => sessionStorage.getItem('tk_doc_token') || '';

  /* منوی موبایل */
  TK.on('click', '[data-dx-menu]', () => TK.qs('#dx-side')?.classList.toggle('open'));

  /* تب‌ها */
  TK.on('click', '[data-tabbed] .code-tabs button', (e, b) => {
    const box = b.closest('[data-tabbed]');
    TK.qsa('.code-tabs button', box).forEach((x) => x.classList.toggle('active', x === b));
    TK.qsa(':scope > .dx-pane', box).forEach((p) => (p.hidden = p.dataset.pane !== b.dataset.pane));
  });

  /* کپی بلوک کد (متن خام بدون شماره‌ی خط) */
  TK.on('click', '[data-copy-raw]', (e, b) => { const pre = b.closest('.codebox').querySelector('pre'); TK.copy(pre.dataset.raw || pre.textContent); });

  /* ───── جستجو ───── */
  const q = TK.qs('#dx-q'), box = TK.qs('#dx-results'), idx = cfg.index || [];
  const hide = () => { box.hidden = true; };
  q?.addEventListener('input', () => {
    const t = q.value.trim().toLowerCase(); if (!t) return hide();
    const hits = idx.filter((x) => (x.t + ' ' + x.p + ' ' + x.g + ' ' + x.m).toLowerCase().includes(t)).slice(0, 12);
    box.innerHTML = hits.length ? hits.map((x) => `<a href="${E(x.u)}"><span class="dx-method m-${x.m.toLowerCase()}" style="padding:.1rem .5rem;font-size:.65rem">${x.m}</span><span class="truncate grow">${E(x.t)}</span><code class="xs ltr">${E(x.p)}</code></a>`).join('') : '<div class="faint small" style="padding:.7rem">نتیجه‌ای پیدا نشد.</div>';
    box.hidden = false;
  });
  q?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { const a = box.querySelector('a'); if (a) location.href = a.href; } if (e.key === 'Escape') { hide(); q.blur(); } });
  document.addEventListener('click', (e) => { if (!e.target.closest('.dx-search')) hide(); });
  document.addEventListener('keydown', (e) => { if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); q?.focus(); } });

  /* ───── صفحه‌ی endpoint: نمونه‌کد و امتحان زنده ───── */
  const ep = TK.boot().ep;
  if (ep) {
    const qs = new URLSearchParams(Object.entries(ep.query || {})).toString();
    const url = ep.url + (qs ? '?' + qs : '');
    const body = ep.body && Object.keys(ep.body).length ? ep.body : null;
    const isPost = ep.method === 'POST';
    const bodyJson = body ? JSON.stringify(body, null, 2) : '';
    const samples = {
      curl: `curl -X ${ep.method} "${url}" \\\n  -H "Authorization: Bearer YOUR_TOKEN"` + (isPost ? ` \\\n  -H "Content-Type: application/json"` + (body ? ` \\\n  -d '${JSON.stringify(body)}'` : '') : ''),
      js: `const res = await fetch("${url}", {\n  method: "${ep.method}",\n  headers: {\n    "Authorization": "Bearer YOUR_TOKEN",\n    "Content-Type": "application/json"\n  }` + (body ? `,\n  body: JSON.stringify(${bodyJson.replace(/\n/g, '\n  ')})` : '') + `\n});\nconst data = await res.json();`,
      php: `<?php\n$ch = curl_init("${url}");\ncurl_setopt_array($ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_CUSTOMREQUEST  => "${ep.method}",\n    CURLOPT_HTTPHEADER     => ["Authorization: Bearer YOUR_TOKEN", "Content-Type: application/json"],` + (body ? `\n    CURLOPT_POSTFIELDS     => json_encode(${JSON.stringify(body)}, JSON_UNESCAPED_UNICODE),` : '') + `\n]);\n$data = json_decode(curl_exec($ch), true);`,
      py: `import requests\n\nres = requests.${ep.method.toLowerCase()}(\n    "${url}",\n    headers={"Authorization": "Bearer YOUR_TOKEN"}` + (body ? `,\n    json=${JSON.stringify(body)}` : '') + `\n)\nprint(res.json())`,
    };
    TK.qsa('[data-sample]').forEach((pre) => {
      const t = samples[pre.dataset.sample]; pre.dataset.raw = t;
      pre.innerHTML = t.split('\n').map((l) => `<span class="l">${E(l) || ' '}</span>`).join('');
    });

    const form = TK.qs('[data-try]'), tokIn = TK.qs('[data-token]', form);
    if (tokIn) { tokIn.value = getTok(); tokIn.addEventListener('input', () => sessionStorage.setItem('tk_doc_token', tokIn.value.trim())); }
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const tok = (tokIn?.value || '').trim();
      if (!tok && !cfg.loggedIn) return TK.toast('ابتدا توکن خود را وارد کنید.', 'warning');
      const q2 = new URLSearchParams(), b2 = {};
      TK.qsa('[data-in]', form).forEach((i) => {
        const v = i.value.trim(); if (v === '') return;
        const val = i.dataset.type === 'integer' ? Number(v) : i.dataset.type === 'boolean' ? (v === 'true' || v === '1') : i.dataset.type === 'array' ? v.split(',').map((x) => (isNaN(x) ? x.trim() : Number(x))) : v;
        if (i.dataset.in === 'query') q2.set(i.dataset.name, v); else b2[i.dataset.name] = val;
      });
      const headers = { Accept: 'application/json' }; if (tok) headers.Authorization = 'Bearer ' + tok; else headers['X-CSRF-Token'] = TK.csrf;
      const opt = { method: ep.method, headers };
      if (isPost) { headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(b2); }
      const out = TK.qs('.try-out', form), st = TK.qs('[data-status]', form), pre = TK.qs('[data-out]', form);
      out.classList.remove('hidden'); st.textContent = '…'; st.className = 'badge'; pre.innerHTML = '';
      try {
        const res = await fetch(ep.url + (q2.toString() ? '?' + q2 : ''), opt); const j = await res.json();
        st.textContent = res.status; st.className = 'badge ' + (res.ok ? 'green' : 'red');
        pre.innerHTML = JSON.stringify(j, null, 2).split('\n').map((l) => `<span class="l">${E(l)}</span>`).join('');
      } catch (err) { st.textContent = 'خطا'; st.className = 'badge red'; pre.textContent = String(err); }
    });
  }

  /* ───── صفحه‌ی توکن: آزمایش GET /me ───── */
  const test = TK.qs('#tok-test');
  if (test) {
    const ti = TK.qs('#tok-input'); ti.value = getTok();
    test.addEventListener('click', async () => {
      const tok = ti.value.trim(); if (!tok) return TK.toast('توکن را وارد کنید.', 'warning');
      sessionStorage.setItem('tk_doc_token', tok);
      TK.busy(test, true);
      try {
        const res = await fetch(cfg.base + '/me', { headers: { Authorization: 'Bearer ' + tok, Accept: 'application/json' } });
        const j = await res.json();
        TK.qs('#tok-out').hidden = false; const st = TK.qs('#tok-status'); st.textContent = res.status; st.className = 'badge ' + (res.ok ? 'green' : 'red');
        TK.qs('#tok-pre').innerHTML = JSON.stringify(j, null, 2).split('\n').map((l) => `<span class="l">${E(l)}</span>`).join('');
      } catch (err) { TK.toast('ارتباط برقرار نشد: ' + err, 'error'); }
      TK.busy(test, false);
    });
  }
});
