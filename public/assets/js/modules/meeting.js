/* رسانه با SDK رسمی LiveKit؛ مجوزها و گفتگوی ماندگار از بک‌اند کارلیکس. */
(() => {
  const T = window.TK, LK = window.LivekitClient, root = document.getElementById('meeting-room');
  if (!root || !T) return;
  const $ = (id) => document.getElementById('meeting-' + id), E = T.esc;
  let state = JSON.parse($('data').textContent), room = null, preview = null, audioContext = null, analyser = null;
  let joined = false, closed = false, endedShown = false, joining = false, pulseBusy = false, pollBusy = false, asking = false;
  let lastPulse = 0, messageRef = '', messageText = '', messages = [], oldest = '', latest = '', historyLoaded = false;
  const base = '/meetings/' + state.id, media = new Map();
  const alert = (text = '') => { $('alert').textContent = text; $('alert').hidden = !text; };
  const status = (text) => { $('connection').textContent = text; };
  const call = async (action, data = null) => {
    let res, out;
    if (data !== null) { out = await T.post(base + '/' + action, data); }
    else {
      res = await fetch(T.url(base + '/' + action), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      out = await res.json();
    }
    if (!T.ok(out)) { const err = new Error(out.message || 'درخواست جلسه انجام نشد.'); err.code = out.error_code; throw err; }
    return out.data;
  };
  const failure = (err) => {
    const names = { NotAllowedError: 'اجازهٔ میکروفن یا دوربین داده نشد؛ از تنظیمات مرورگر دسترسی این سایت را فعال کنید.', NotFoundError: 'میکروفن یا دوربین در دسترس نیست.', NotReadableError: 'دستگاه در برنامهٔ دیگری مشغول است یا قابل استفاده نیست.' };
    alert(names[err.name] || (/[\u0600-\u06ff]/.test(err.message || '') ? err.message : 'ارتباط جلسه قطع شد؛ دوباره تلاش کنید.'));
  };
  const me = () => state.members.find((m) => m.user_id === state.me);
  const can = (source) => me()?.sources.includes(source);
  const person = (identity) => state.members.find((m) => m.identity === identity);
  const stopPreview = () => {
    preview?.getTracks().forEach((t) => t.stop()); preview = null;
    $('preview-video').srcObject = null;
    if (audioContext) { audioContext.close().catch(() => {}); audioContext = null; analyser = null; }
    $('mic-level').value = 0;
  };
  const devices = async () => {
    const inputs = await navigator.mediaDevices.enumerateDevices();
    for (const [kind, el] of [['audioinput', $('audio-device')], ['videoinput', $('video-device')]]) {
      if (!el) continue; const previous = el.value;
      el.replaceChildren(new Option('دستگاه پیش‌فرض', ''));
      inputs.filter((d) => d.kind === kind).forEach((d, i) => el.add(new Option(d.label || 'دستگاه ' + T.fa(i + 1), d.deviceId)));
      el.value = [...el.options].some((o) => o.value === previous) ? previous : '';
    }
  };
  $('test').addEventListener('click', async () => {
    alert(); stopPreview();
    try {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw new Error('دسترسی به میکروفن و دوربین به HTTPS و مرورگر سازگار نیاز دارد.');
      const audio = $('pre-mic').checked, video = !!$('pre-camera')?.checked;
      if (!audio && !video) { $('preview-label').textContent = 'بدون میکروفن و دوربین وارد می‌شوید.'; await devices(); return; }
      preview = await navigator.mediaDevices.getUserMedia({ audio: audio ? { deviceId: $('audio-device').value ? { exact: $('audio-device').value } : undefined, echoCancellation: true, noiseSuppression: true } : false,
        video: video ? { deviceId: $('video-device').value ? { exact: $('video-device').value } : undefined } : false });
      $('preview-video').srcObject = preview; $('preview-label').textContent = 'دستگاه‌های انتخاب‌شده آماده‌اند.';
      if (audio) {
        audioContext = new AudioContext(); analyser = audioContext.createAnalyser(); analyser.fftSize = 256;
        audioContext.createMediaStreamSource(preview).connect(analyser);
      }
      await devices();
    } catch (err) { stopPreview(); failure(err); }
  });
  const levelTimer = setInterval(() => {
    if (!analyser) return; const a = new Uint8Array(analyser.fftSize); analyser.getByteTimeDomainData(a);
    $('mic-level').value = Math.min(1, Math.sqrt(a.reduce((sum, v) => sum + ((v - 128) / 128) ** 2, 0) / a.length) * 4);
  }, 100);
  const bindMedia = (track, parent, local = false) => {
    const key = track.sid || track.mediaStreamTrack?.id;
    let el = media.get(key);
    if (!el) { el = track.attach(); el.autoplay = true; el.playsInline = true; if (local) el.muted = true; media.set(key, el); }
    parent.append(el); return key;
  };
  const drawMedia = () => {
    if (!room || !joined) return;
    const grid = $('grid'), audio = $('audio-tracks'), keep = new Set();
    grid.replaceChildren();
    for (const p of [room.localParticipant, ...room.remoteParticipants.values()]) {
      const profile = person(p.identity), local = p === room.localParticipant;
      const tile = document.createElement('article'); tile.className = 'meeting-tile' + (p.isSpeaking ? ' is-speaking' : ''); tile.dataset.identity = p.identity;
      const camera = p.getTrackPublication(LK.Track.Source.Camera), screen = p.getTrackPublication(LK.Track.Source.ScreenShare);
      const name = profile?.name || 'عضو جلسه';
      tile.innerHTML = `<div class="meeting-person-avatar">${profile?.avatar ? `<img src="${E(profile.avatar)}" alt="">` : E(T.initial(name))}</div><div class="meeting-tile-caption"><b>${E(name)}${local ? ' (شما)' : ''}</b><span>${p.isMicrophoneEnabled ? 'میکروفن روشن' : 'میکروفن خاموش'}${state.mode === 'video' ? ' · ' + (p.isCameraEnabled ? 'دوربین روشن' : 'دوربین خاموش') : ''} · ${({ excellent: 'اتصال عالی', good: 'اتصال خوب', poor: 'اتصال ضعیف', lost: 'ارتباط قطع' })[p.connectionQuality] || 'در حال اتصال'}</span></div>`;
      if (state.mode === 'video' && camera?.track && !camera.isMuted) { keep.add(bindMedia(camera.track, tile, local)); tile.classList.add('has-video'); }
      grid.append(tile);
      if (screen?.track && !screen.isMuted) {
        const share = document.createElement('article'); share.className = 'meeting-tile meeting-screen-tile';
        const caption = document.createElement('div'); caption.className = 'meeting-tile-caption'; caption.textContent = 'صفحهٔ ' + name; share.append(caption);
        keep.add(bindMedia(screen.track, share, local)); grid.append(share);
      }
      if (!local) {
        for (const pub of p.audioTrackPublications.values()) if (pub.track) keep.add(bindMedia(pub.track, audio));
      }
    }
    for (const [key, el] of media) if (!keep.has(key)) { el.srcObject = null; el.remove(); media.delete(key); }
    controls();
  };
  const controls = () => {
    for (const [id, source, enabled] of [['mic', 'microphone', room?.localParticipant.isMicrophoneEnabled], ['camera', 'camera', room?.localParticipant.isCameraEnabled], ['screen', 'screen_share', room?.localParticipant.isScreenShareEnabled]]) {
      const button = $(id); if (!button) continue;
      const unsupported = source === 'screen_share' && !navigator.mediaDevices?.getDisplayMedia;
      button.disabled = !joined || !can(source) || unsupported; button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
      const icon = button.querySelector('i'); if (icon) icon.className = 'fa-solid ' + (id === 'mic' ? (enabled ? 'fa-microphone' : 'fa-microphone-slash') : (enabled ? 'fa-video' : 'fa-video-slash'));
      button.title = unsupported ? 'مرورگر شما از اشتراک صفحه پشتیبانی نمی‌کند.' : !can(source) ? 'اجازهٔ میزبان لازم است.' : enabled ? 'خاموش کردن' : 'روشن کردن';
    }
    $('hand').setAttribute('aria-pressed', me()?.raised_hand ? 'true' : 'false');
  };
  let memberFingerprint = '';
  const drawMembers = () => {
    const fingerprint = JSON.stringify([state.members, state.is_host]);
    if (fingerprint === memberFingerprint) { controls(); return; }
    memberFingerprint = fingerprint;
    const openDetails = new Set([...$('members').querySelectorAll('details[open]')].map((d) => d.dataset.memberDetails));
    $('members').innerHTML = state.members.map((m) => `<div class="meeting-member"><div><b>${E(m.name)}</b><span class="xs muted">${({ host: 'میزبان', speaker: 'سخنران', listener: 'شنونده' })[m.role]} · ${({ invited: 'دعوت‌شده', accepted: m.present ? 'حاضر' : 'خارج از جلسه', declined: 'دعوت رد شده', removed: 'دسترسی بسته' })[m.invite_state]}${m.raised_hand ? ' · ✋ درخواست صحبت' : ''}</span></div>
      ${state.is_host && m.user_id !== state.me && m.invite_state !== 'removed' ? `<details data-member-details="${E(m.user_id)}"><summary class="btn btn-xs">مدیریت</summary><div class="meeting-member-actions">${[['speaker','سخنران'],['listener','شنونده'],['mute','بستن میکروفن'],['allow_microphone','اجازهٔ میکروفن'],['request_microphone','درخواست روشن‌کردن میکروفن'], ...(state.mode === 'video' ? [['stop_camera','بستن دوربین'],['allow_camera','اجازهٔ دوربین'],['request_camera','درخواست روشن‌کردن دوربین']] : []), ['allow_screen','اجازهٔ اشتراک صفحه'],['stop_screen','بستن اشتراک صفحه'],['remove','اخراج']].map(([action, label]) => `<button type="button" class="btn btn-xs" data-meeting-moderate="${E(action)}" data-member="${E(m.user_id)}">${label}</button>`).join('')}</div></details>` : ''}</div>`).join('');
    $('members').querySelectorAll('details').forEach((d) => { d.open = openDetails.has(d.dataset.memberDetails); });
    $('end').hidden = !state.is_host; $('invite').hidden = !state.is_host;
    controls();
  };
  const requestConsent = async () => {
    const self = me(); if (!joined || asking || !self?.activation_request || !self.request_ref) return;
    asking = true; const ref = self.request_ref, kind = self.activation_request;
    try {
      const accept = await T.confirm('میزبان درخواست روشن‌شدن ' + (kind === 'camera' ? 'دوربین' : 'میکروفن') + ' شما را دارد. اجازه می‌دهید؟', { okText: 'اجازه می‌دهم', cancelText: 'رد درخواست' });
      const r = await call('consent', { ref, accept: accept ? '1' : '0' }); state = r.state; drawMembers();
      if (r.activate && joined) {
        // روشن‌شدن فقط پس از کلیک رضایت خود کاربر؛ تغییر مجوز به تنهایی رسانه را روشن نمی‌کند.
        await new Promise((resolve) => setTimeout(resolve, 500));
        if (r.activate === 'camera') await room.localParticipant.setCameraEnabled(true);
        else await room.localParticipant.setMicrophoneEnabled(true);
      }
    } catch (err) { failure(err); } finally { asking = false; }
  };
  const applyState = async (next) => {
    state = next; drawMembers();
    if (state.status === 'ended') { if (!state.sync_pending) clearInterval(poll); await finish(); return; }
    if (joined && room) {
      if (!can('microphone')) await room.localParticipant.setMicrophoneEnabled(false);
      if (!can('camera')) await room.localParticipant.setCameraEnabled(false);
      if (!can('screen_share')) await room.localParticipant.setScreenShareEnabled(false);
      requestConsent();
    }
  };
  const disconnect = async () => {
    joined = false; stopPreview();
    if (room) { const old = room; room = null; await old.disconnect(); }
    for (const el of media.values()) { el.srcObject = null; el.remove(); } media.clear();
    $('join').disabled = false; $('prejoin').hidden = false; $('workspace').hidden = true;
  };
  const finish = async () => { if (endedShown) return; endedShown = true; await disconnect(); $('prejoin').hidden = true; $('ended').hidden = false; status('پایان‌یافته'); };
  $('join').addEventListener('click', async () => {
    if (joining || joined) return; joining = true; $('join').disabled = true; alert();
    const wantMic = $('pre-mic').checked, wantCam = !!$('pre-camera')?.checked;
    stopPreview();
    try {
      if (!LK) throw new Error('فایل‌های رابط جلسه کامل دریافت نشده‌اند؛ صفحه را دوباره بارگذاری کنید.');
      if (!window.isSecureContext || !navigator.mediaDevices) throw new Error('دسترسی به میکروفن و دوربین به HTTPS و مرورگر سازگار نیاز دارد.');
      status('در حال اتصال…'); const connection = await call('join', {}); state = connection.state;
      room = new LK.Room({ adaptiveStream: true, dynacast: true, publishDefaults: { simulcast: true } });
      for (const event of [LK.RoomEvent.ParticipantConnected, LK.RoomEvent.ParticipantDisconnected, LK.RoomEvent.TrackSubscribed, LK.RoomEvent.TrackUnsubscribed, LK.RoomEvent.TrackMuted, LK.RoomEvent.TrackUnmuted, LK.RoomEvent.LocalTrackPublished, LK.RoomEvent.LocalTrackUnpublished, LK.RoomEvent.ConnectionQualityChanged, LK.RoomEvent.ActiveSpeakersChanged]) room.on(event, drawMedia);
      room.on(LK.RoomEvent.Reconnecting, () => status('اتصال قطع شده؛ در حال اتصال مجدد…'));
      room.on(LK.RoomEvent.Reconnected, () => { status('متصل'); pulse(); });
      room.on(LK.RoomEvent.Disconnected, () => {
        if (!room) return; disconnect(); status('ارتباط قطع شد؛ برای ورود دوباره تلاش کنید.');
      });
      room.on(LK.RoomEvent.AudioPlaybackStatusChanged, () => { if (room) $('play-audio').hidden = room.canPlaybackAudio; });
      room.on(LK.RoomEvent.MediaDevicesError, failure);
      room.on(LK.RoomEvent.ParticipantPermissionsChanged, () => pulse(true));
      await room.connect(connection.url, connection.token); joined = true; status('متصل');
      $('prejoin').hidden = true; $('workspace').hidden = false; $('ended').hidden = true;
      drawMembers(); drawMedia();
      if ($('audio-device').value) await room.switchActiveDevice('audioinput', $('audio-device').value);
      if ($('video-device')?.value) await room.switchActiveDevice('videoinput', $('video-device').value);
      try {
        if (wantMic && can('microphone')) await room.localParticipant.setMicrophoneEnabled(true);
        if (wantCam && can('camera')) await room.localParticipant.setCameraEnabled(true);
      } catch (err) { failure(err); }
      await loadChat(); await pulse(true);
    } catch (err) { await disconnect(); status('ورود انجام نشد'); failure(err); }
    finally { joining = false; $('join').disabled = false; }
  });
  const toggle = (button, action) => button?.addEventListener('click', async () => { try { alert(); await action(); drawMedia(); } catch (err) { failure(err); } });
  toggle($('mic'), () => room.localParticipant.setMicrophoneEnabled(!room.localParticipant.isMicrophoneEnabled));
  toggle($('camera'), () => room.localParticipant.setCameraEnabled(!room.localParticipant.isCameraEnabled));
  toggle($('screen'), () => room.localParticipant.setScreenShareEnabled(!room.localParticipant.isScreenShareEnabled));
  toggle($('play-audio'), () => room.startAudio());
  $('hand').addEventListener('click', async () => { try { await applyState(await call('hand', { raised: me()?.raised_hand ? '0' : '1' })); } catch (err) { failure(err); } });
  $('layout').addEventListener('click', () => root.classList.toggle('meeting-focus'));
  $('panels').addEventListener('click', () => root.classList.toggle('meeting-show-side'));
  $('leave').addEventListener('click', async () => {
    await disconnect(); status('از جلسه خارج شدید.');
    try { await call('leave', {}); } catch (err) { failure(err); }
  });
  const endMeeting = async () => {
    if (!(await T.confirm('جلسه برای همه پایان یابد؟', { okText: 'پایان جلسه' }))) return;
    try { await applyState(await call('end', {})); }
    catch (err) { failure(err); try { await applyState(await call('state')); } catch { /* همگام‌سازی بعدی */ } }
  };
  $('end').addEventListener('click', endMeeting);
  $('cancel')?.addEventListener('click', endMeeting);
  T.on('click', '[data-meeting-moderate]', async (e, b) => {
    T.busy(b, true);
    try { await applyState(await call('moderate', { user_id: b.dataset.member, action: b.dataset.meetingModerate })); }
    catch (err) { failure(err); } finally { T.busy(b, false); }
  });
  $('invite').addEventListener('click', async () => {
    try {
      const members = await call('members');
      $('invite-members').innerHTML = members.filter((m) => m.user_id !== state.me).map((m) => `<label class="check meeting-invite-member"><input type="checkbox" value="${E(m.user_id)}"><span class="avatar">${m.avatar ? `<img src="${E(m.avatar)}" alt="">` : E(T.initial(m.name))}</span><span class="meeting-invite-member-name"><b>${E(m.name)}</b><small>عضو همین پروژه</small></span></label>`).join('') || '<p>عضو دیگری در پروژه نیست.</p>';
      T.modal.open('m-meeting-invite');
    } catch (err) { failure(err); }
  });
  $('invite-send').addEventListener('click', async (e) => {
    const button = e.currentTarget; T.busy(button, true);
    try { await applyState(await call('invite', { invitees: [...$('invite-members').querySelectorAll('input:checked')].map((x) => x.value) })); T.modal.close('m-meeting-invite'); T.toast('دعوت ارسال شد.', 'success'); }
    catch (err) { failure(err); } finally { T.busy(button, false); }
  });
  const drawChat = (target = $('chat-list')) => {
    const bottom = target.scrollHeight - target.scrollTop - target.clientHeight < 80;
    target.innerHTML = messages.map((m) => `<li class="meeting-message"><div><b>${E(m.name)}</b><time>${E(m.created_at)}</time></div><p>${E(m.body)}</p></li>`).join('');
    if (bottom) target.scrollTop = target.scrollHeight;
  };
  const loadChat = async (older = false) => {
    if (pollBusy) return; pollBusy = true;
    try {
      const query = older && oldest ? '?before=' + encodeURIComponent(oldest) : latest ? '?after=' + encodeURIComponent(latest) : '';
      const rows = await call('messages' + query), known = new Set(messages.map((m) => m.id));
      const fresh = rows.filter((m) => !known.has(m.id)); messages = older ? [...fresh, ...messages] : [...messages, ...fresh];
      oldest = messages[0]?.id || ''; latest = messages.at(-1)?.id || ''; historyLoaded = true;
      if (older || !query) $('chat-older').hidden = rows.length < 100;
      if (fresh.length || !query) drawChat();
    } finally { pollBusy = false; }
  };
  $('chat-older').addEventListener('click', () => loadChat(true).catch(failure));
  $('chat-form').addEventListener('submit', async (e) => {
    e.preventDefault(); const input = $('message'), body = input.value.trim(); if (!body) return;
    const button = e.currentTarget.querySelector('button'); if (button.disabled) return; T.busy(button, true);
    if (messageText !== body || !messageRef) { messageText = body; messageRef = crypto.randomUUID().replaceAll('-', ''); }
    try { await call('send', { body, client_ref: messageRef }); input.value = ''; messageRef = ''; messageText = ''; await loadChat(); }
    catch (err) { failure(err); } finally { T.busy(button, false); }
  });
  $('message').addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); $('chat-form').requestSubmit(); } });
  $('history-chat').addEventListener('click', async () => {
    try {
      // همان صفحهٔ گفتگو با صفحه‌بندی؛ پایان جلسه فقط نوشتن و رسانه را می‌بندد.
      $('workspace').hidden = false; $('grid').hidden = true; $('side').hidden = false; root.classList.add('meeting-show-side');
      root.querySelector('.meeting-controls').hidden = true; $('chat-form').hidden = true;
      await loadChat();
    } catch (err) { failure(err); }
  });
  async function pulse(force = false) {
    if (closed || pulseBusy || !root.isConnected) return; pulseBusy = true;
    try {
      if (force || Date.now() - lastPulse >= 20000) { lastPulse = Date.now(); await applyState(await call('heartbeat', {})); }
      else await applyState(await call('state'));
      if (joined && historyLoaded) await loadChat();
    } catch (err) {
      if (['MEETING_REMOVED','MEETING_NOT_FOUND','ACCOUNT_INACTIVE','UNAUTHORIZED','REAUTH_REQUIRED'].includes(err.code)) { closed = true; clearInterval(poll); await disconnect(); $('join').disabled = true; status('دسترسی یا نشست شما پایان یافته است.'); }
      failure(err);
    } finally { pulseBusy = false; }
  }
  const poll = setInterval(() => { if (!document.hidden || joined) pulse(); }, 2500);
  // خارج‌شدن از سند همیشه ضبط دستگاه‌ها را متوقف می‌کند؛ ناوبری نرم این صفحه غیرفعال است.
  root.querySelectorAll('a[href]').forEach((a) => a.setAttribute('data-no-nav', ''));
  const navigation = (e) => {
    const link = e.target.closest('a[href]'); if (!link || !joined || e.ctrlKey || e.metaKey || link.target === '_blank') return;
    e.preventDefault(); e.stopImmediatePropagation();
    T.confirm('با خروج از این صفحه ارتباط جلسه قطع می‌شود. ادامه می‌دهید؟', { okText: 'خروج از جلسه' }).then(async (yes) => { if (yes) { await disconnect(); location.assign(link.href); } });
  };
  document.addEventListener('click', navigation, true);
  const cleanup = () => { closed = true; clearInterval(poll); clearInterval(levelTimer); stopPreview(); if (room) room.disconnect(); };
  window.addEventListener('pagehide', cleanup);
  window.addEventListener('beforeunload', (e) => { if (joined) { e.preventDefault(); e.returnValue = ''; } });
  // مسیر خروج حتی پیش از ورود باید سند کامل بگیرد تا هدرهای رسانه به صفحهٔ دیگری منتقل نشوند.
  document.querySelectorAll('a[href]').forEach((a) => a.setAttribute('data-no-nav', ''));
  drawMembers();
  if (state.status === 'ended') finish();
  else if (!LK) failure(new Error('فایل‌های رابط جلسه کامل دریافت نشده‌اند؛ صفحه را دوباره بارگذاری کنید.'));
})();
