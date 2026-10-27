/* Taskam service worker — نسخه‌ی PWA
   • کش فایل‌های ایستا (CSS/JS/تصاویر) برای باز شدن سریع، صفحه‌ی آفلاین وقتی اینترنت نیست
   • صفحه‌ها و داده‌ها همیشه از شبکه می‌آیند (اطلاعات کاربر کش نمی‌شود)
   • Web Push: اعلان پیام، تسک و یادآوری حتی وقتی برنامه بسته است */
'use strict';
const VERSION = '__VERSION__';
const STATIC = 'tk-static-' + VERSION;
const SCOPE = self.registration.scope;                 // مثل https://example.com/taskam2/
const abs = (p) => new URL(p || '', SCOPE).href;
const OFFLINE = abs('offline');
const PRECACHE = [OFFLINE, abs('assets/img/icon-192.png'), abs('assets/img/badge-96.png')];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) if (k.startsWith('tk-static-') && k !== STATIC) await caches.delete(k);
    if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin || !req.url.startsWith(SCOPE)) return;   // فونت/آیکون CDN و آدرس‌های بیرونی دست‌نخورده
  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try {
        return (await e.preloadResponse) || (await fetch(req));
      } catch {
        return (await caches.match(OFFLINE)) || new Response('آفلاین هستید', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
      }
    })());
    return;
  }
  if (url.pathname.includes('/assets/')) {
    // stale-while-revalidate: سریع از کش، در پس‌زمینه به‌روز
    e.respondWith((async () => {
      const cache = await caches.open(STATIC);
      const hit = await cache.match(req);
      const net = fetch(req).then((res) => { if (res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
      return hit || net;
    })());
  }
});

/* ───── اعلان‌ها ───── */
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch { d = { title: 'تسکام', body: e.data ? e.data.text() : '' }; }
  e.waitUntil((async () => {
    if (typeof d.unread === 'number' && self.navigator.setAppBadge) {
      try { d.unread > 0 ? await self.navigator.setAppBadge(d.unread) : await self.navigator.clearAppBadge(); } catch { /* پشتیبانی نمی‌شود */ }
    }
    // اگر برنامه همین الان باز و جلوی چشم کاربر است، خود صفحه پیام را نشان می‌دهد (اعلان تکراری نمی‌سازیم)
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const visible = wins.find((c) => c.visibilityState === 'visible' && c.focused);
    if (visible && d.kind !== 'test') { visible.postMessage({ type: 'tk-push', payload: d }); return; }
    const kind = d.kind || 'info';
    await self.registration.showNotification(d.title || 'تسکام', {
      body: d.body || '',
      icon: abs(d.icon || 'assets/img/icon-192.png'),
      badge: abs(d.badge || 'assets/img/badge-96.png'),       // آیکن تک‌رنگ نوار وضعیت اندروید
      tag: d.tag || undefined,
      renotify: !!d.tag,                                       // پیام جدید در همان گفتگو دوباره صدا/لرزش دارد
      requireInteraction: kind === 'due',                      // یادآوری تا وقتی کاربر نبیند می‌ماند
      timestamp: d.ts || Date.now(),
      vibrate: kind === 'due' ? [200, 100, 200, 100, 200] : [120, 60, 120],
      dir: 'rtl', lang: 'fa',
      data: { url: abs(d.url || 'dashboard'), kind },
      actions: kind === 'chat' ? [{ action: 'open', title: 'پاسخ' }] : [{ action: 'open', title: 'مشاهده' }],
    });
  })());
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = (e.notification.data && e.notification.data.url) || SCOPE;
  e.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const win = wins.find((c) => c.url.startsWith(SCOPE));
    if (win) {
      await win.focus();
      try { await win.navigate(target); } catch { win.postMessage({ type: 'tk-open', url: target }); }
      return;
    }
    await self.clients.openWindow(target);
  })());
});

// اگر مرورگر اشتراک را عوض کرد، دوباره مشترک شو؛ صفحه در بارگذاری بعدی آن را با سرور همگام می‌کند
self.addEventListener('pushsubscriptionchange', (e) => {
  const opts = e.oldSubscription && e.oldSubscription.options;
  if (!opts) return;
  e.waitUntil(self.registration.pushManager.subscribe(opts).catch(() => null));
});

self.addEventListener('message', (e) => {
  if (e.data === 'skipWaiting') self.skipWaiting();
});
