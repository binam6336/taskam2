/* «تست API» (v7.8): Swagger UI + اعمال توکن (دستی یا ساخته‌شده با POST /docs/swagger/token). توکن فقط در sessionStorage همین تب. */
(() => {
  const box = document.getElementById('sw-auth');
  const state = document.getElementById('sw-state');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const KEY = 'kx_swagger_token';
  let token = '';
  try { token = sessionStorage.getItem(KEY) || ''; } catch (e) { /* حالت خصوصی */ }
  const ui = window.SwaggerUIBundle({
    url: box.dataset.openapi, dom_id: '#swagger', deepLinking: true, docExpansion: 'none', defaultModelsExpandDepth: -1,
    displayRequestDuration: true, tryItOutEnabled: true, persistAuthorization: false, filter: true,
    presets: [window.SwaggerUIBundle.presets.apis], layout: 'BaseLayout',
    requestInterceptor: (req) => { if (token && !req.headers.Authorization) req.headers.Authorization = 'Bearer ' + token; req.headers.Accept = 'application/json'; return req; },
    onComplete: () => { if (token) apply(token, false); },
  });
  const show = (msg, ok) => { state.textContent = msg; state.className = 'sw-state ' + (ok ? 'ok' : 'err'); };
  function apply(t, announce = true) {
    token = t.trim();
    try { token ? sessionStorage.setItem(KEY, token) : sessionStorage.removeItem(KEY); } catch (e) { /* */ }
    if (token) ui.preauthorizeApiKey('bearerAuth', token);
    if (announce) show(token ? 'توکن اعمال شد (…' + token.slice(-6) + ')؛ حالا هر endpoint را باز کنید و «Try it out» ← «Execute» را بزنید.' : 'توکن پاک شد.', !!token);
  }
  if (token) show('توکن قبلی همین تب اعمال شد (…' + token.slice(-6) + ').', true);
  document.getElementById('sw-tok').addEventListener('submit', (e) => { e.preventDefault(); apply(document.getElementById('sw-tok-in').value); });

  const capUrl = box.dataset.captchaUrl, img = document.getElementById('sw-cap-img');
  const newCap = () => { if (img && capUrl) img.src = capUrl + (capUrl.includes('?') ? '&' : '?') + 't=' + Date.now(); };
  newCap(); document.getElementById('sw-cap-new')?.addEventListener('click', newCap);

  document.getElementById('sw-gen').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.currentTarget, btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    let j = {};
    try {
      const res = await fetch(box.dataset.tokenUrl, { method: 'POST', body: new FormData(f), credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
      j = await res.json();
    } catch (err) { j = { status: 'error', message: 'ارتباط با سرور برقرار نشد.' }; }
    btn.disabled = false;
    if (j.status === 'success' && j.token) { apply(j.token, false); show((j.message || 'توکن ساخته شد.') + ' (…' + j.token.slice(-6) + '، تا ۲۴ ساعت)', true); f.reset(); }
    else { show(j.message || 'خطا', false); newCap(); const c = f.querySelector('[name=captcha]'); if (c) c.value = ''; }
  });
})();
