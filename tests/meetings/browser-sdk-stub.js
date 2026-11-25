/* فقط برای آزمون UI؛ انتقال رسانه شبیه‌سازی است و این فایل در برنامه بارگذاری نمی‌شود. */
(() => {
  const source = { Camera: 'camera', Microphone: 'microphone', ScreenShare: 'screen_share' };
  const events = Object.fromEntries(['ParticipantConnected','ParticipantDisconnected','TrackSubscribed','TrackUnsubscribed','TrackMuted','TrackUnmuted','LocalTrackPublished','LocalTrackUnpublished','ConnectionQualityChanged','ActiveSpeakersChanged','Reconnecting','Reconnected','Disconnected','AudioPlaybackStatusChanged','MediaDevicesError','ParticipantPermissionsChanged'].map((x) => [x,x]));
  class Room {
    constructor() {
      this.handlers = {}; this.remoteParticipants = new Map(); this.canPlaybackAudio = true;
      this.localParticipant = { identity: '', isSpeaking: false, connectionQuality: 'excellent', isMicrophoneEnabled: false, isCameraEnabled: false, isScreenShareEnabled: false, audioTrackPublications: new Map(), getTrackPublication: () => null,
        setMicrophoneEnabled: async (v) => { this.localParticipant.isMicrophoneEnabled = v; this.emit(events.LocalTrackPublished); },
        setCameraEnabled: async (v) => { this.localParticipant.isCameraEnabled = v; this.emit(events.LocalTrackPublished); },
        setScreenShareEnabled: async (v) => { this.localParticipant.isScreenShareEnabled = v; this.emit(events.LocalTrackPublished); } };
      window.__meetingTestRoom = this;
    }
    on(event, fn) { (this.handlers[event] ||= []).push(fn); return this; }
    emit(event) { for (const fn of this.handlers[event] || []) fn(); }
    async connect(url, token) { this.localParticipant.identity = JSON.parse(atob(token.split('.')[1])).sub; }
    async disconnect() { this.emit(events.Disconnected); }
    async switchActiveDevice() {}
    async startAudio() {}
  }
  window.LivekitClient = { Room, RoomEvent: events, Track: { Source: source } };
})();
