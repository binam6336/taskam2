/* جلسه‌ها در همان پنل؛ ورود سند کامل می‌خواهد تا سیاست رسانه فقط همان‌جا باز شود. */
(() => {
  const T = window.TK; if (!T) return;
  const E = T.esc, box = document.getElementById('meeting-invitations');
  let stopped = false, busy = false, fingerprint = '';
  const me = T.boot().live?.uid || '', seenKey = 'kx.meeting.invites.' + me;
  let seen = new Set(); try { seen = new Set(JSON.parse(sessionStorage.getItem(seenKey) || '[]')); } catch {}
  const inviteKey = (m) => m.id + ':' + (m.invitation_ref || 'initial');
  const avatar = (name, src) => `<span class="avatar lg">${src ? `<img src="${E(src)}" alt="">` : E(T.initial(name || '؟'))}</span>`;
  const card = (m) => `<article class="meeting-invite-card" data-meeting-invite="${E(m.id)}">${avatar(m.host_name, m.host_avatar)}<div class="grow"><span class="meeting-invite-kicker">دعوت به جلسهٔ ${m.mode === 'audio' ? 'صوتی' : 'تصویری'}</span><h3>${E(m.project_title)}</h3><p>${E(m.host_name || 'عضو پروژه')} شما را به جلسه دعوت کرده است.</p></div><div class="meeting-invite-buttons"><button type="button" class="btn btn-primary" data-meeting-accept="${E(m.id)}"><i class="fa-solid fa-phone" aria-hidden="true"></i> پیوستن به جلسه</button><button type="button" class="btn btn-ghost" data-meeting-decline="${E(m.id)}">رد دعوت</button></div></article>`;
  const render = (invites) => {
    const key = JSON.stringify(invites); const dashboard = document.getElementById('dashboard-meeting-invites');
    if (dashboard && key !== fingerprint) dashboard.innerHTML = invites.map((m) => `<div class="card">${card(m)}</div>`).join('');
    if (key !== fingerprint && box?.isConnected) {
      fingerprint = key; box.hidden = !invites.length || !!dashboard || !!document.getElementById('meeting-room');
      box.innerHTML = invites.map(card).join('');
    }
    const fresh = invites.filter((m) => !seen.has(inviteKey(m))), modal = document.getElementById('m-meeting-incoming');
    if (!invites.length) { if (modal?.classList.contains('open')) T.modal.close(modal); return; }
    // هنگام ویرایش تسک یا مکالمه، پنجرهٔ دیگری ناگهانی روی کاربر باز نشود؛ باکس دعوت همچنان در دسترس است.
    if (fresh.length && modal && !document.querySelector('.modal.open') && !document.getElementById('meeting-room')) {
      modal.querySelector('[data-meeting-incoming-body]').innerHTML = fresh.map(card).join('');
      T.modal.open(modal.id); modal.querySelector('[data-meeting-accept]')?.focus(); fresh.forEach((m) => seen.add(inviteKey(m)));
      try { sessionStorage.setItem(seenKey, JSON.stringify([...seen])); } catch {}
    }
  };
  const get = async (path) => {
    const res = await fetch(T.url(path), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const out = await res.json(); if (!res.ok || !T.ok(out)) throw new Error(out.message || 'دریافت اطلاعات جلسه انجام نشد.'); return out.data;
  };
  const check = async () => {
    if (busy || stopped || !box?.isConnected || document.hidden) return;
    busy = true;
    try {
      const invites = await get('/meetings/invitations/list');
      if (!box.isConnected) return;
      render(invites);
    } catch { /* اختلال موقت، اعلان‌های دیگر را متوقف نمی‌کند. */ }
    finally { busy = false; }
  };
  T.on('click', '[data-meeting-accept],[data-meeting-decline]', async (e, b) => {
    const id = b.dataset.meetingAccept || b.dataset.meetingDecline, accept = !!b.dataset.meetingAccept;
    T.busy(b, true);
    try { const r = await T.post('/meetings/' + id + '/respond', { action: accept ? 'accept' : 'decline' }); if (!T.ok(r)) throw new Error(r.message); if (accept) { T.modal.close('m-meeting-incoming'); location.assign(T.url('/meetings/' + id)); } else { T.modal.close('m-meeting-incoming'); fingerprint = ''; await check(); } }
    catch (err) { T.toast(err.message, 'error'); } finally { T.busy(b, false); }
  });
  const roomList = async (project) => get('/projects/' + project + '/meetings');
  T.on('click', '[data-meeting-start]', async (e, b) => {
    const project = b.closest('[data-meeting-project]').dataset.meetingProject; T.busy(b, true);
    try {
      const list = await roomList(project), active = list.meetings.find((m) => m.status !== 'ended');
      if (active) { location.assign(T.url('/meetings/' + active.id)); return; }
      if (!list.configured) throw new Error('جلسات آنلاین هنوز تنظیم نشده‌اند؛ با مدیر سامانه تماس بگیرید.');
      if (!list.can_create) throw new Error('برای ایجاد جلسه به دسترسی ویرایش پروژه نیاز دارید.');
      const r = await T.post('/projects/' + project + '/meetings', { mode: b.dataset.meetingStart });
      if (!T.ok(r)) throw new Error(r.message); location.assign(T.url('/meetings/' + r.data.id));
    } catch (err) { T.toast(err.message, 'error'); } finally { T.busy(b, false); }
  });
  T.on('click', '[data-meeting-history]', async (e, b) => {
    try {
      const list = await roomList(b.closest('[data-meeting-project]').dataset.meetingProject);
      let modal = document.getElementById('m-meeting-list');
      if (!modal) { modal = document.createElement('div'); modal.id = 'm-meeting-list'; modal.className = 'modal'; document.body.append(modal); }
      modal.innerHTML = `<div class="modal-box"><div class="modal-head"><h3>جلسه‌های پروژه</h3><button type="button" data-close class="btn btn-icon" aria-label="بستن">×</button></div><div class="modal-body">${list.meetings.map((m) => `<a data-no-nav class="meeting-history-row" href="${E(T.url('/meetings/' + m.id))}"><b>${m.mode === 'audio' ? 'صوتی' : 'تصویری'}</b><span>${m.status === 'ended' ? 'پایان‌یافته · گفتگو' : m.status === 'waiting' ? 'در انتظار شروع · ورود' : 'فعال · پیوستن'}</span><time>${E(m.created_at)}</time></a>`).join('') || '<p class="muted">جلسه‌ای ثبت نشده است.</p>'}</div></div>`;
      T.modal.open(modal.id);
    } catch (err) { T.toast(err.message, 'error'); }
  });
  render(T.boot().meetingInvitations || []);
  check(); const timer = setInterval(check, 5000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
  document.addEventListener('tk:live', check);
  window.addEventListener('pagehide', () => { stopped = true; clearInterval(timer); });
})();
