<?php
declare(strict_types=1);
namespace KarlixLaravel\Services\Meetings;

use App\Core\HttpException;
use App\Core\LiveKitConfig as Config;
use App\Core\Pid;
use KarlixLaravel\Database\DB;
use KarlixLaravel\Services\Access;
use KarlixLaravel\Services\Notifier;

/** وضعیت و مجوز جلسه همیشه در کارلیکس است؛ Cloud فقط انتقال رسانه را انجام می‌دهد. */
final class MeetingService
{
    private LiveKitClient $cloud;
    private array $held = [];
    public function __construct(?LiveKitClient $cloud = null) { $this->cloud = $cloud ?? new LiveKitClient(); }
    private static function error(int $http, string $text, string $code): never { throw new HttpException($http, $text, $code); }
    public static function schemaReady(): bool
    {
        static $ready = null;
        return $ready ??= (int) DB::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('project_meetings','meeting_members','meeting_messages','meeting_webhook_events')") === 4;
    }
    private function requireSchema(): void
    {
        if (!self::schemaReady()) { self::error(503, 'جدول‌های جلسات آماده نیستند؛ تغییرات دیتابیس نسخهٔ جدید را اعمال کنید.', 'MEETING_SCHEMA_MISSING'); }
    }
    public static function memberAllowed(int $project, int $uid): bool
    {
        return Access::canJoinMeeting($project, $uid);
    }
    public static function canCreate(int $project, int $uid): bool
    {
        return Access::canCreateMeeting($project, $uid);
    }
    public function meeting(int $id, int $uid, bool $live = false): array
    {
        $this->requireSchema();
        $m = DB::one('SELECT * FROM project_meetings WHERE id = ?', [$id]);
        if (!$m || !self::memberAllowed((int) $m['project_id'], $uid) || Access::isBlockedBetween((int) $m['host_id'], $uid)) {
            self::error(404, 'جلسه یافت نشد یا دسترسی ندارید.', 'MEETING_NOT_FOUND');
        }
        $member = DB::one('SELECT invite_state FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $uid]);
        if (($member['invite_state'] ?? '') === 'removed') { self::error(403, 'دسترسی شما به این جلسه بسته شده است.', 'MEETING_REMOVED'); }
        if ($live && $m['status'] === 'ended') { self::error(409, 'این جلسه پایان یافته است.', 'MEETING_ENDED'); }
        return $m;
    }
    private function host(array $m, int $uid): void
    {
        if ((int) $m['host_id'] !== $uid || !self::canCreate((int) $m['project_id'], $uid)) { self::error(403, 'فقط میزبان مجاز به مدیریت این جلسه است.', 'MEETING_HOST_REQUIRED'); }
    }
    /** قفل نام‌دار فقط جلسهٔ مربوط را سری می‌کند؛ حذف/تغییر مجوز و صدور توکن از هم سبقت نمی‌گیرند. */
    private function locked(int $id, callable $fn)
    {
        if (isset($this->held[$id])) { return $fn(); }
        $key = 'kx_meet_' . substr(hash('sha256', (string) env('DB_NAME')), 0, 16) . '_' . $id;
        if ((int) DB::value('SELECT GET_LOCK(?, 2)', [$key]) !== 1) { self::error(409, 'جلسه در حال به‌روزرسانی است؛ دوباره تلاش کنید.', 'MEETING_BUSY'); }
        $this->held[$id] = true;
        try { return $fn(); } finally { unset($this->held[$id]); DB::value('SELECT RELEASE_LOCK(?)', [$key]); }
    }
    public function create(int $project, int $uid, string $mode): array
    {
        if (!self::canCreate($project, $uid)) { self::error(403, 'برای ایجاد جلسه به دسترسی ویرایش پروژه نیاز دارید.', 'MEETING_CREATE_FORBIDDEN'); }
        if (!in_array($mode, ['audio','video'], true)) { self::error(422, 'نوع جلسه معتبر نیست.', 'MEETING_MODE_INVALID'); }
        $this->cloud->requireConfigured();
        $this->requireSchema();
        $id = DB::transaction(function () use ($project, $uid, $mode): int {
            DB::one('SELECT id FROM projects WHERE id = ? FOR UPDATE', [$project]);
            $old = DB::one('SELECT id FROM project_meetings WHERE active_project_id = ?', [$project]);
            if ($old) { return (int) $old['id']; }
            $id = DB::insert('project_meetings', ['project_id' => $project, 'active_project_id' => $project, 'host_id' => $uid,
                'mode' => $mode, 'room_name' => 'kx_' . bin2hex(random_bytes(20))]);
            $this->addMember($id, $uid, 'host', 'accepted', $mode === 'video');
            return $id;
        }, 2);
        return $this->locked($id, function () use ($id, $uid): array {
            $m = $this->meeting($id, $uid, true); $this->provision($m);
            return $this->state($id, $uid);
        });
    }
    private function provision(array $m): void
    {
        if ($m['room_sid']) { return; }
        $room = $this->cloud->call('CreateRoom', ['name' => $m['room_name'], 'empty_timeout' => 900, 'departure_timeout' => 90, 'max_participants' => Config::maxParticipants()], ['roomCreate' => true]);
        DB::update('project_meetings', ['room_sid' => $room['sid'] ?? null], 'id = ? AND status <> ?', [$m['id'], 'ended']);
    }
    private function addMember(int $id, int $uid, string $role = 'listener', string $state = 'accepted', bool $camera = false): void
    {
        DB::insert('meeting_members', ['meeting_id' => $id, 'user_id' => $uid, 'identity' => bin2hex(random_bytes(20)), 'role' => $role, 'invite_state' => $state,
            'can_microphone' => $role === 'host' ? 1 : 0, 'can_camera' => $camera ? 1 : 0, 'can_screen' => $role === 'host' ? 1 : 0]);
    }
    public static function sources(array $m, array $u): array
    {
        if ($u['role'] === 'listener') { return []; }
        $s = [];
        if ($u['can_microphone']) { $s[] = 'microphone'; }
        if ($m['mode'] === 'video' && $u['can_camera']) { $s[] = 'camera'; }
        if ($u['can_screen']) { $s[] = 'screen_share'; $s[] = 'screen_share_audio'; }
        return $s;
    }
    private static function grant(array $m, array $u): array
    {
        $sources = self::sources($m, $u);
        return ['roomJoin' => true, 'room' => $m['room_name'], 'canSubscribe' => true, 'canPublish' => (bool) $sources,
            'canPublishSources' => $sources, 'canPublishData' => false, 'canUpdateOwnMetadata' => false];
    }
    public function join(int $id, int $uid): array
    {
        $this->cloud->requireConfigured();
        return $this->locked($id, function () use ($id, $uid): array {
            $m = $this->meeting($id, $uid, true);
            if (!self::canCreate((int) $m['project_id'], (int) $m['host_id'])) { $this->endInternal($m); self::error(409, 'این جلسه پایان یافته است.', 'MEETING_ENDED'); }
            $this->provision($m);
            $member = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $uid]);
            if (!$member) {
                if ((int) DB::value("SELECT COUNT(*) FROM meeting_members WHERE meeting_id = ? AND invite_state IN ('accepted','invited')", [$id]) >= Config::maxParticipants()) {
                    self::error(409, 'ظرفیت جلسه پر شده است.', 'MEETING_FULL');
                }
                $this->addMember($id, $uid);
            } else { DB::update('meeting_members', ['invite_state' => 'accepted'], 'id = ?', [$member['id']]); }
            $member = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $uid]);
            return ['url' => $this->cloud->url(), 'token' => $this->cloud->token(['sub' => $member['identity'], 'video' => self::grant($m, $member)]),
                'identity' => $member['identity'], 'state' => $this->state($id, $uid)];
        });
    }
    public function state(int $id, int $uid): array
    {
        $m = $this->meeting($id, $uid);
        $people = DB::all('SELECT mm.*, u.first_name, u.last_name, u.avatar FROM meeting_members mm JOIN users u ON u.id = mm.user_id WHERE mm.meeting_id = ? ORDER BY mm.id', [$id]);
        $out = [];
        foreach ($people as $u) {
            if (!self::memberAllowed((int) $m['project_id'], (int) $u['user_id']) || Access::isBlockedBetween((int) $m['host_id'], (int) $u['user_id'])) { continue; }
            $out[] = ['user_id' => pid('users', $u['user_id']), 'name' => full_name($u), 'avatar' => avatar_url($u['avatar']), 'identity' => $u['identity'],
                'role' => $u['role'], 'invite_state' => $u['invite_state'], 'present' => (bool) $u['present'], 'sources' => self::sources($m, $u),
                'raised_hand' => (bool) $u['raised_hand'], 'activation_request' => (int) $u['user_id'] === $uid ? $u['activation_request'] : null,
                'request_ref' => (int) $u['user_id'] === $uid ? $u['request_ref'] : null];
        }
        return ['id' => pid('project_meetings', $m), 'project_id' => pid('projects', $m['project_id']), 'project_title' => DB::value('SELECT title FROM projects WHERE id = ?', [$m['project_id']]),
            'host_id' => pid('users', $m['host_id']), 'mode' => $m['mode'], 'status' => $m['status'], 'created_at' => $m['created_at'], 'ended_at' => $m['ended_at'],
            'is_host' => (int) $m['host_id'] === $uid && self::canCreate((int) $m['project_id'], $uid), 'me' => pid('users', $uid), 'members' => $out, 'sync_pending' => (bool) $m['cleanup_pending']];
    }
    public function projectList(int $project, int $uid): array
    {
        $this->requireSchema();
        if (!self::memberAllowed($project, $uid)) { self::error(404, 'جلسه یافت نشد یا دسترسی ندارید.', 'MEETING_NOT_FOUND'); }
        $rows = DB::all('SELECT id, pid, mode, status, host_id, created_at FROM project_meetings WHERE project_id = ? ORDER BY id DESC LIMIT 30', [$project]);
        return ['configured' => Config::configured(), 'can_create' => self::canCreate($project, $uid), 'meetings' => array_values(array_filter(array_map(function ($m) use ($uid) {
            try { $this->meeting((int) $m['id'], $uid); return ['id' => $m['pid'], 'mode' => $m['mode'], 'status' => $m['status'], 'host_id' => pid('users', $m['host_id']), 'created_at' => $m['created_at']]; }
            catch (HttpException $e) { if ($e->status >= 500) { throw $e; } return null; }
        }, $rows)))];
    }
    public function invite(int $id, int $uid, array $pids): array
    {
        return $this->locked($id, function () use ($id, $uid, $pids): array {
            $m = $this->meeting($id, $uid, true); $this->host($m, $uid);
            if (!$pids || count($pids) > Config::maxParticipants()) { self::error(422, 'اعضای دعوت‌شونده معتبر نیستند.', 'MEETING_INVITE_INVALID'); }
            $ids = [];
            foreach ($pids as $pid) {
                $target = is_string($pid) ? Pid::id('users', $pid) : null;
                if (!$target || !self::memberAllowed((int) $m['project_id'], $target) || Access::isBlockedBetween($uid, $target)) { self::error(422, 'اعضای دعوت‌شونده معتبر نیستند.', 'MEETING_INVITE_INVALID'); }
                if ($target !== $uid) { $ids[$target] = $target; }
            }
            $existing = DB::column("SELECT user_id FROM meeting_members WHERE meeting_id = ? AND invite_state IN ('accepted','invited')", [$id]);
            if (count(array_unique(array_merge($existing, array_values($ids)))) > Config::maxParticipants()) { self::error(409, 'ظرفیت جلسه پر شده است.', 'MEETING_FULL'); }
            foreach ($ids as $target) {
                $old = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $target]);
                if ($old && in_array($old['invite_state'], ['accepted','invited'], true)) { continue; }
                if ($old) {
                    // هویت قبلی پس از اخراج برای همیشه کنار گذاشته می‌شود.
                    $this->cloud->call('RemoveParticipant', ['room' => $m['room_name'], 'identity' => $old['identity']], ['roomAdmin' => true, 'room' => $m['room_name']]);
                    DB::run('DELETE FROM meeting_members WHERE id = ?', [$old['id']]);
                }
                $this->addMember($id, $target, 'listener', 'invited');
                Notifier::send($target, 'دعوت به جلسهٔ ' . ($m['mode'] === 'audio' ? 'صوتی' : 'تصویری') . ' پروژه؛ برای پذیرش یا رد، بخش دعوت‌های جلسه در پیشخوان را باز کنید.');
            }
            return $this->state($id, $uid);
        });
    }
    public function invitations(int $uid): array
    {
        if (!self::schemaReady()) { return []; }
        $rows = DB::all("SELECT m.id FROM project_meetings m JOIN meeting_members mm ON mm.meeting_id = m.id WHERE mm.user_id = ? AND mm.invite_state = 'invited' AND m.status <> 'ended' ORDER BY m.id DESC LIMIT 30", [$uid]);
        $out = [];
        foreach ($rows as $r) { try { $out[] = $this->state((int) $r['id'], $uid); } catch (HttpException $e) { if ($e->status >= 500) { throw $e; } } }
        return $out;
    }
    public function respond(int $id, int $uid, bool $accept): array
    {
        return $this->locked($id, function () use ($id, $uid, $accept): array {
            $this->meeting($id, $uid, true);
            DB::run("UPDATE meeting_members SET invite_state = ? WHERE meeting_id = ? AND user_id = ? AND invite_state = 'invited'", [$accept ? 'accepted' : 'declined', $id, $uid]);
            return $this->state($id, $uid);
        });
    }
    public function members(int $id, int $uid): array
    {
        $m = $this->meeting($id, $uid, true); $this->host($m, $uid);
        $rows = DB::all("SELECT DISTINCT u.id, u.pid, u.first_name, u.last_name, u.avatar FROM users u JOIN projects p ON p.id = ? LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = u.id WHERE (u.id = p.user_id OR pm.user_id IS NOT NULL) AND u.status = 'active' AND u.is_bot = 0", [$m['project_id']]);
        return array_values(array_map(static fn($u) => ['user_id' => $u['pid'], 'name' => full_name($u), 'avatar' => avatar_url($u['avatar'])], array_filter($rows, static fn($u) => !Access::isBlockedBetween($uid, (int) $u['id']))));
    }
    public function moderate(int $id, int $uid, int $target, string $action): array
    {
        return $this->locked($id, function () use ($id, $uid, $target, $action): array {
            $m = $this->meeting($id, $uid, true); $this->host($m, $uid);
            $u = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $target]);
            if (!$u || $target === $uid || !self::memberAllowed((int) $m['project_id'], $target) || $u['invite_state'] === 'removed') { self::error(422, 'عضو جلسه معتبر نیست.', 'MEETING_MEMBER_INVALID'); }
            $change = match ($action) {
                'listener' => ['role' => 'listener', 'can_microphone' => 0, 'can_camera' => 0, 'can_screen' => 0, 'activation_request' => null],
                'speaker' => ['role' => 'speaker', 'can_microphone' => 1, 'can_camera' => $m['mode'] === 'video' ? 1 : 0, 'can_screen' => 1, 'raised_hand' => 0],
                'mute' => ['can_microphone' => 0], 'stop_camera' => ['can_camera' => 0], 'stop_screen' => ['can_screen' => 0],
                'allow_microphone' => ['role' => 'speaker', 'can_microphone' => 1],
                'allow_camera' => ['role' => 'speaker', 'can_camera' => $m['mode'] === 'video' ? 1 : 0],
                'allow_screen' => ['role' => 'speaker', 'can_screen' => 1],
                'request_microphone', 'request_camera' => ['activation_request' => substr($action, 8), 'request_ref' => bin2hex(random_bytes(16))],
                'remove' => ['invite_state' => 'removed', 'present' => 0, 'activation_request' => null],
                default => self::error(422, 'عملیات جلسه معتبر نیست.', 'MEETING_ACTION_INVALID'),
            };
            if (!str_starts_with($action, 'request_')) { $change['activation_request'] = null; $change['request_ref'] = null; }
            if ($action === 'request_camera' && $m['mode'] === 'audio') { self::error(422, 'جلسه صوتی از دوربین پشتیبانی نمی‌کند.', 'MEETING_AUDIO_ONLY'); }
            DB::update('meeting_members', $change + ['sync_needed' => 1], 'id = ?', [$u['id']]);
            // قصد محدودسازی پیش از تماس شبکه ذخیره می‌شود؛ خطای شبکه با همگام‌سازی بعدی قابل بازیابی است.
            $this->sync($id, true);
            return $this->state($id, $uid);
        });
    }
    public function consent(int $id, int $uid, string $ref, bool $accept): array
    {
        return $this->locked($id, function () use ($id, $uid, $ref, $accept): array {
            $m = $this->meeting($id, $uid, true);
            $u = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $uid]);
            if (!$u || !$u['request_ref'] || !hash_equals($u['request_ref'], $ref) || !$u['activation_request']) { self::error(409, 'درخواست فعال‌سازی دیگر معتبر نیست.', 'MEETING_REQUEST_EXPIRED'); }
            $change = ['activation_request' => null, 'request_ref' => null];
            $kind = $u['activation_request'];
            if ($accept) { $change += ['role' => 'speaker', 'can_' . $kind => 1, 'sync_needed' => 1]; }
            DB::update('meeting_members', $change, 'id = ?', [$u['id']]);
            if ($accept) { $this->sync($id, true); }
            return ['activate' => $accept ? $kind : null, 'state' => $this->state($id, $uid)];
        });
    }
    public function hand(int $id, int $uid, bool $on): array
    {
        $this->meeting($id, $uid, true);
        if (!DB::value("SELECT 1 FROM meeting_members WHERE meeting_id = ? AND user_id = ? AND invite_state = 'accepted'", [$id, $uid])) { self::error(403, 'ابتدا وارد جلسه شوید.', 'MEETING_JOIN_REQUIRED'); }
        DB::update('meeting_members', ['raised_hand' => $on ? 1 : 0], 'meeting_id = ? AND user_id = ?', [$id, $uid]);
        return $this->state($id, $uid);
    }
    public function leave(int $id, int $uid): void
    {
        $this->locked($id, function () use ($id, $uid): void {
            $m = $this->meeting($id, $uid);
            $u = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id, $uid]);
            if (!$u) { return; }
            $this->cloud->call('RemoveParticipant', ['room' => $m['room_name'], 'identity' => $u['identity']], ['roomAdmin' => true, 'room' => $m['room_name']]);
            DB::update('meeting_members', ['present' => 0, 'raised_hand' => 0], 'id = ?', [$u['id']]);
        });
    }
    private function endInternal(array $m): void
    {
        DB::run("UPDATE project_meetings SET status = 'ended', active_project_id = NULL, ended_at = COALESCE(ended_at, NOW()), cleanup_pending = 1 WHERE id = ?", [$m['id']]);
        DB::run('UPDATE meeting_members SET present = 0, sync_needed = 1 WHERE meeting_id = ?', [$m['id']]);
    }
    public function end(int $id, int $uid): array
    {
        return $this->locked($id, function () use ($id, $uid): array {
            $m = $this->meeting($id, $uid); $this->host($m, $uid); $this->endInternal($m); $this->sync($id, true);
            return $this->state($id, $uid);
        });
    }
    /** اجرای کوتاه از heartbeat، webhook و cron؛ هیچ worker دائمی لازم نیست. */
    public function sync(int $id, bool $force = false): void
    {
        $this->locked($id, function () use ($id, $force): void {
            $m = DB::one('SELECT * FROM project_meetings WHERE id = ?', [$id]);
            if (!$m || (!$force && $m['sync_at'] && strtotime($m['sync_at']) > time() - Config::SYNC_SECONDS)) { return; }
            if ($m['status'] !== 'ended' && !self::canCreate((int) $m['project_id'], (int) $m['host_id'])) { $this->endInternal($m); $m['status'] = 'ended'; $m['cleanup_pending'] = 1; }
            if ($m['status'] === 'ended' && !$m['cleanup_pending']) { return; }
            $rows = DB::all('SELECT * FROM meeting_members WHERE meeting_id = ?', [$id]);
            $grant = ['roomAdmin' => true, 'room' => $m['room_name']];
            if ($m['status'] === 'ended') {
                foreach ($rows as $u) { $this->cloud->call('RemoveParticipant', ['room' => $m['room_name'], 'identity' => $u['identity']], $grant); }
                $this->cloud->call('DeleteRoom', ['room' => $m['room_name']], ['roomCreate' => true]);
                DB::update('project_meetings', ['cleanup_pending' => 0, 'sync_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
                return;
            }
            if (!$m['room_sid']) { return; }
            $rooms = $this->cloud->call('ListRooms', ['names' => [$m['room_name']]], ['roomList' => true]);
            if (empty($rooms['rooms'])) { $this->endInternal($m); $this->sync($id, true); return; }
            $online = $this->cloud->call('ListParticipants', ['room' => $m['room_name']], $grant)['participants'] ?? [];
            $byIdentity = array_column($rows, null, 'identity');
            foreach ($online as $remote) {
                if (!isset($byIdentity[$remote['identity']])) { $this->cloud->call('RemoveParticipant', ['room' => $m['room_name'], 'identity' => $remote['identity']], $grant); }
            }
            $online = array_column($online, null, 'identity');
            $hasPresent = false;
            foreach ($rows as $u) {
                $valid = $u['invite_state'] === 'accepted' && self::memberAllowed((int) $m['project_id'], (int) $u['user_id']) && !Access::isBlockedBetween((int) $m['host_id'], (int) $u['user_id']);
                if (!$valid) {
                    // هم اتصال فعلی و هم توکن اتصال قبلی باطل می‌شوند، حتی اگر مرورگر بسته باشد.
                    if (isset($online[$u['identity']]) || $u['sync_needed'] || $u['invite_state'] === 'accepted') {
                        $this->cloud->call('RemoveParticipant', ['room' => $m['room_name'], 'identity' => $u['identity']], $grant);
                    }
                    DB::update('meeting_members', ['present' => 0, 'sync_needed' => 0, 'invite_state' => $u['invite_state'] === 'accepted' ? 'removed' : $u['invite_state']], 'id = ?', [$u['id']]);
                    continue;
                }
                $remote = $online[$u['identity']] ?? null;
                if ($remote) { $hasPresent = true; }
                if ($remote && $u['sync_needed']) {
                    $sources = self::sources($m, $u); $enums = ['camera' => 1, 'microphone' => 2, 'screen_share' => 3, 'screen_share_audio' => 4];
                    $this->cloud->call('UpdateParticipant', ['room' => $m['room_name'], 'identity' => $u['identity'], 'permission' => [
                        'can_subscribe' => true, 'can_publish' => (bool) $sources, 'can_publish_data' => false, 'can_update_metadata' => false,
                        'can_publish_sources' => array_map(static fn($s) => $enums[$s], $sources)]], $grant);
                    foreach ($remote['tracks'] ?? [] as $track) {
                        $source = $track['source'] ?? 0; $source = is_numeric($source) ? (int) $source : ($enums[strtolower($source)] ?? 0);
                        if (!in_array($source, array_map(static fn($s) => $enums[$s], $sources), true)) {
                            $this->cloud->call('MutePublishedTrack', ['room' => $m['room_name'], 'identity' => $u['identity'], 'track_sid' => $track['sid'], 'muted' => true], $grant);
                        }
                    }
                }
                DB::update('meeting_members', ['present' => $remote ? 1 : 0, 'sync_needed' => 0], 'id = ?', [$u['id']]);
            }
            if ($hasPresent) { DB::run("UPDATE project_meetings SET status = 'active', started_at = COALESCE(started_at, NOW()) WHERE id = ? AND status = 'waiting'", [$id]); }
            DB::update('project_meetings', ['sync_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
    }
    public function heartbeat(int $id, int $uid): array
    {
        $this->meeting($id, $uid); $this->sync($id);
        return $this->state($id, $uid);
    }
    private function chatMember(int $id, int $uid, bool $write): array
    {
        $m = $this->meeting($id, $uid, $write);
        if (!DB::value("SELECT 1 FROM meeting_members WHERE meeting_id = ? AND user_id = ? AND invite_state = 'accepted'", [$id, $uid])) {
            self::error(403, 'ابتدا وارد جلسه شوید.', 'MEETING_JOIN_REQUIRED');
        }
        return $m;
    }
    public function messages(int $id, int $uid, string $before = '', string $after = ''): array
    {
        $this->chatMember($id, $uid, false); $params = [$id]; $where = '';
        foreach (['before' => $before, 'after' => $after] as $kind => $pid) {
            if ($pid === '') { continue; }
            $cursor = DB::value('SELECT id FROM meeting_messages WHERE pid = ? AND meeting_id = ?', [$pid, $id]);
            if (!$cursor) { self::error(422, 'نشانگر پیام معتبر نیست.', 'MEETING_CURSOR_INVALID'); }
            $where .= ' AND x.id ' . ($kind === 'before' ? '<' : '>') . ' ?'; $params[] = (int) $cursor;
        }
        $rows = DB::all('SELECT x.*, u.first_name, u.last_name, u.avatar FROM meeting_messages x JOIN users u ON u.id = x.user_id WHERE x.meeting_id = ?' . $where . ' ORDER BY x.id ' . ($after === '' ? 'DESC' : 'ASC') . ' LIMIT 100', $params);
        if ($after === '') { $rows = array_reverse($rows); }
        return array_map(static fn($r) => ['id' => $r['pid'], 'user_id' => pid('users', $r['user_id']), 'name' => full_name($r), 'avatar' => avatar_url($r['avatar']), 'body' => $r['body'], 'created_at' => $r['created_at'], 'client_ref' => $r['client_ref']], $rows);
    }
    public function send(int $id, int $uid, string $body, string $ref): array
    {
        return $this->locked($id, function () use ($id, $uid, $body, $ref): array {
            $this->chatMember($id, $uid, true); $body = trim($body);
            if ($body === '' || mb_strlen($body) > Config::CHAT_MAX || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D', $ref)) { self::error(422, 'متن یا شناسهٔ پیام معتبر نیست.', 'MEETING_MESSAGE_INVALID'); }
            $old = DB::one('SELECT pid FROM meeting_messages WHERE meeting_id = ? AND user_id = ? AND client_ref = ?', [$id, $uid, $ref]);
            if ($old) { return ['id' => $old['pid'], 'duplicate' => true]; }
            $mid = DB::insert('meeting_messages', ['meeting_id' => $id, 'user_id' => $uid, 'client_ref' => $ref, 'body' => $body]);
            return ['id' => pid('meeting_messages', $mid), 'duplicate' => false];
        });
    }
    public function webhook(string $raw, string $authorization): void
    {
        $event = $this->cloud->verifyWebhook($raw, $authorization);
        $room = $event['room'] ?? []; $m = DB::one('SELECT * FROM project_meetings WHERE room_name = ?', [$room['name'] ?? '']);
        if (!$m) { return; }
        $id = (int) $m['id'];
        $this->locked($id, function () use ($event, $m, $id): void {
            $fresh = DB::transaction(function () use ($event, $m, $id): bool {
                if (!DB::run('INSERT IGNORE INTO meeting_webhook_events (event_id) VALUES (?)', [$event['id']])) { return false; }
                if (!empty($m['room_sid']) && ($event['room']['sid'] ?? '') !== $m['room_sid']) { return false; }
                $type = $event['event'] ?? ''; $ts = (int) ($event['createdAt'] ?? $event['created_at'] ?? time());
                if ($type === 'room_finished') { $this->endInternal($m); }
                if (in_array($type, ['participant_joined','participant_left'], true)) {
                    DB::run('UPDATE meeting_members SET present = ?, last_event_at = ? WHERE meeting_id = ? AND identity = ? AND last_event_at <= ?',
                        [$type === 'participant_joined' && $m['status'] !== 'ended' ? 1 : 0, $ts, $id, $event['participant']['identity'] ?? '', $ts]);
                }
                return true;
            });
            // تکرار webhook پس از خطای شبکه باید همگام‌سازی نیمه‌تمام را هم دوباره امتحان کند.
            $this->sync($id, true);
        });
    }
    /** تغییر عضویت/مجوز از پنل و API، فوراً برای Cloud هم اعمال می‌شود؛ خطای شبکه مجوز محلی را برنمی‌گرداند. */
    public static function projectAccessChanged(int $project): void
    {
        if (!Config::configured() || !self::schemaReady()) { return; }
        foreach (DB::column("SELECT id FROM project_meetings WHERE project_id = ? AND (status <> 'ended' OR cleanup_pending = 1)", [$project]) as $id) {
            try { (new self())->sync((int) $id, true); }
            catch (\Throwable $e) { DB::update('project_meetings', ['sync_at' => null], 'id = ?', [$id]); \App\Core\Log::warning('meeting.sync_pending', ['meeting' => (int) $id]); }
        }
    }
    public function sweep(): array
    {
        $out = ['ok' => 0, 'failed' => 0];
        foreach (DB::column("SELECT id FROM project_meetings WHERE status <> 'ended' OR cleanup_pending = 1 ORDER BY sync_at, id LIMIT 100") as $id) {
            try { $this->sync((int) $id); $out['ok']++; } catch (\Throwable $e) { $out['failed']++; }
        }
        DB::run('DELETE FROM meeting_webhook_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
        return $out;
    }
}
