<?php
declare(strict_types=1);
// تست غیرمخرب روی دیتابیس آزمایشی؛ Fake فقط جای شبکه است، مجوز/رمزگذاری/SQL واقعی‌اند.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use KarlixLaravel\Services\Meetings\MeetingService;
use KarlixLaravel\Services\Meetings\LiveKitClient;
use App\Core\HttpException;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
final class MeetingTestCloud extends LiveKitClient
{
    public array $rooms = [], $calls = [], $participants = [];
    public bool $fail = false;
    public function requireConfigured(): void {}
    public function url(): string { return 'wss://test.livekit.cloud'; }
    public function call(string $method, array $data, array $grant): array
    {
        if ($this->fail) { throw new HttpException(503, 'آزمایش قطع سرویس', 'MEETING_PROVIDER_ERROR'); }
        $this->calls[] = [$method, $data, $grant];
        $name = $data['room'] ?? $data['name'] ?? '';
        if ($method === 'CreateRoom') { return $this->rooms[$name] = ['name' => $name, 'sid' => 'RM_' . $name]; }
        if ($method === 'ListRooms') { return ['rooms' => array_values(array_intersect_key($this->rooms, array_flip($data['names'])))]; }
        if ($method === 'ListParticipants') { return ['participants' => array_values($this->participants[$name] ?? [])]; }
        if ($method === 'RemoveParticipant') { unset($this->participants[$name][$data['identity']]); }
        if ($method === 'DeleteRoom') { unset($this->rooms[$name]); }
        return [];
    }
}
function verify(bool $ok, string $text): void { if (!$ok) { throw new RuntimeException($text); } echo "PASS $text\n"; }
function denied(callable $fn, int $http, string $text): void { try { $fn(); } catch (HttpException $e) { verify($e->status === $http, $text); return; } throw new RuntimeException('Not denied: ' . $text); }
function claims(string $token): array { return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true); }
putenv('LIVEKIT_API_KEY=meeting_test_key'); putenv('LIVEKIT_API_SECRET=meeting_test_secret_not_production');
$cloud = new MeetingTestCloud(); $s = new MeetingService($cloud);
DB::pdo()->beginTransaction();
try {
    $uids = [];
    foreach (['میزبان','عضو','بیگانه','مدیر دیگر'] as $name) { $uids[] = DB::insert('users', ['mobile' => '099' . random_int(10000000, 99999999), 'first_name' => $name, 'password_hash' => 'test', 'status' => 'active']); }
    [$host,$member,$stranger,$other] = $uids;
    $p = DB::insert('projects', ['user_id' => $host, 'title' => 'جلسهٔ تست']);
    $q = DB::insert('projects', ['user_id' => $other, 'title' => 'پروژهٔ دیگر']);
    DB::insert('project_members', ['project_id' => $p, 'user_id' => $member, 'permissions' => '[]']);
    DB::insert('project_members', ['project_id' => $p, 'user_id' => $other, 'permissions' => '[]']);
    denied(fn() => $s->create($p, $member, 'audio'), 403, 'عضو عادی سازندهٔ جلسه نیست');
    denied(fn() => $s->create($p, $other, 'audio'), 403, 'مدیر پروژهٔ دیگر در این پروژه اختیار ندارد');
    $created = $s->create($p, $host, 'video'); $id = App\Core\Pid::id('project_meetings', $created['id']);
    verify($created['status'] === 'waiting', 'ساخت جلسهٔ تصویری در انتظار شروع');
    verify($s->create($p, $host, 'audio')['id'] === $created['id'], 'فقط یک جلسهٔ باز برای پروژه');
    denied(fn() => $s->state($id, $stranger), 404, 'شناسهٔ جلسه دسترسی بیگانه نمی‌سازد');
    $h = $s->join($id, $host); $hostClaims = claims($h['token']);
    verify($hostClaims['video']['canPublishSources'] === ['microphone','camera','screen_share','screen_share_audio'] && !isset($hostClaims['video']['roomAdmin']), 'توکن میزبان فقط رسانه دارد و مجوز مدیریت Cloud به مرورگر نمی‌رود');
    verify($hostClaims['exp'] - $hostClaims['iat'] === App\Core\LiveKitConfig::TOKEN_SECONDS, 'توکن کوتاه‌عمر و مختص Room');
    denied(fn() => $s->invite($id, $host, [pid('users', $stranger)]), 422, 'دعوت بیگانه رد می‌شود');
    $s->invite($id, $host, [pid('users', $member)]);
    verify(count($s->invitations($member)) === 1, 'دعوت ماندگار به عضو پروژه');
    $s->respond($id, $member, false); verify(count($s->invitations($member)) === 0, 'رد دعوت ثبت می‌شود');
    $s->invite($id, $host, [pid('users', $member)]); $s->respond($id, $member, true);
    $join = $s->join($id, $member); $mClaims = claims($join['token']);
    verify(!$mClaims['video']['canPublish'] && !$mClaims['video']['canPublishData'] && $mClaims['video']['canPublishSources'] === [], 'عضو تازه شنونده است؛ مسیر چت LiveKit مجاز نیست');
    $room = DB::value('SELECT room_name FROM project_meetings WHERE id = ?', [$id]);
    $cloud->participants[$room][$join['identity']] = ['identity' => $join['identity'], 'tracks' => [['sid' => 'TR_mic', 'source' => 2]]];
    $s->hand($id, $member, true);
    denied(fn() => $s->moderate($id, $member, $host, 'remove'), 403, 'مدیریت جلسه فقط با میزبان');
    $s->moderate($id, $host, $member, 'speaker');
    verify(claims($s->join($id, $member)['token'])['video']['canPublish'], 'ارتقا به سخنران با مجوز سرور');
    $s->moderate($id, $host, $member, 'request_microphone');
    $self = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id,$member]);
    denied(fn() => $s->consent($id, $member, 'wrong', true), 409, 'درخواست رضایت قابل جعل نیست');
    $s->consent($id, $member, $self['request_ref'], false);
    verify(!DB::value('SELECT activation_request FROM meeting_members WHERE id = ?', [$self['id']]), 'رد رضایت بدون روشن‌کردن رسانه');
    $s->moderate($id, $host, $member, 'request_microphone');
    $staleRef = DB::value('SELECT request_ref FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$id,$member]);
    $s->moderate($id, $host, $member, 'mute');
    denied(fn() => $s->consent($id, $member, $staleRef, true), 409, 'محدودسازی تازهٔ میزبان، درخواست رضایت قبلی را باطل می‌کند');
    $sources = claims($s->join($id, $member)['token'])['video']['canPublishSources']; verify(!in_array('microphone', $sources, true), 'محدودیت میکروفن در توکن و API سرور اعمال می‌شود');
    $ref = bin2hex(random_bytes(16)); $sent = $s->send($id, $member, 'پیام فارسی <script>آزمون</script>', $ref);
    verify($s->send($id, $member, 'پیام فارسی <script>آزمون</script>', $ref)['duplicate'], 'ارسال مجدد پیام، ردیف تکراری نمی‌سازد');
    $raw = DB::pdo()->query('SELECT body FROM meeting_messages WHERE meeting_id = ' . $id)->fetchColumn();
    verify(str_starts_with($raw, App\Core\ContentCipher::PREFIX), 'پیام جلسه در دیتابیس رمزگذاری شده است');
    verify($s->messages($id, $member)[0]['body'] === 'پیام فارسی <script>آزمون</script>', 'تاریخچهٔ واقعی با رمزگشایی صحیح');
    denied(fn() => $s->messages($id, $stranger), 404, 'عدم افشای گفتگو بین پروژه‌ها');
    $firstIdentity = $join['identity']; $s->moderate($id, $host, $member, 'remove');
    denied(fn() => $s->join($id, $member), 403, 'عضو اخراج‌شده توکن جدید نمی‌گیرد');
    denied(fn() => $s->messages($id, $member), 403, 'اخراج‌شده تاریخچه را هم نمی‌خواند');
    $s->invite($id, $host, [pid('users', $member)]); $join = $s->join($id, $member);
    verify($join['identity'] !== $firstIdentity, 'دعوت دوباره هویت تازه دارد و توکن اخراج‌شده احیا نمی‌شود');
    DB::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$p,$member]);
    $s->sync($id, true); denied(fn() => $s->join($id, $member), 404, 'حذف عضویت پروژه، اتصال و ورود مجدد را می‌بندد');
    $cloud->fail = true; denied(fn() => $s->end($id, $host), 503, 'خطای شبکه گزارش می‌شود');
    verify(DB::value('SELECT status FROM project_meetings WHERE id = ?', [$id]) === 'ended' && (int) DB::value('SELECT cleanup_pending FROM project_meetings WHERE id = ?', [$id]) === 1, 'پایان محلی حتی با قطع Cloud ثبت و پاک‌سازی صف می‌شود');
    denied(fn() => $s->join($id, $host), 409, 'جلسهٔ پایان‌یافته توکن نمی‌دهد');
    $cloud->fail = false; $s->sync($id, true);
    verify(!DB::value('SELECT cleanup_pending FROM project_meetings WHERE id = ?', [$id]), 'پاک‌سازی Cloud پس از بازیابی شبکه کامل می‌شود');
    verify(count($s->messages($id, $host)) === 1, 'حفظ تاریخچه پس از پایان');
    $a = $s->create($p, $host, 'audio'); $aid = App\Core\Pid::id('project_meetings', $a['id']); $audio = claims($s->join($aid, $host)['token']);
    verify(!in_array('camera', $audio['video']['canPublishSources'], true), 'جلسه صوتی در سرور اجازهٔ دوربین ندارد');
    DB::insert('project_members', ['project_id' => $p, 'user_id' => $member, 'permissions' => '[]']); $s->join($aid, $member);
    $s->moderate($aid, $host, $member, 'request_microphone'); $u = DB::one('SELECT * FROM meeting_members WHERE meeting_id = ? AND user_id = ?', [$aid,$member]);
    verify($s->consent($aid, $member, $u['request_ref'], true)['activate'] === 'microphone', 'فعال‌سازی فقط با رضایت همان عضو');
    denied(fn() => $s->consent($aid, $member, $u['request_ref'], true), 409, 'رضایت یک‌بار مصرف است');
    for ($i=0; $i<205; $i++) { $s->send($aid,$host,'پیام '.$i,bin2hex(random_bytes(16))); }
    $newest=$s->messages($aid,$host); $older=$s->messages($aid,$host,$newest[0]['id']); $oldest=$s->messages($aid,$host,$older[0]['id']);
    verify(count($newest)===100 && count($older)===100 && count($oldest)===5 && $oldest[0]['body']==='پیام 0' && $newest[99]['body']==='پیام 204','تاریخچهٔ ۲۰۵ پیام بدون حذف یا تکرار صفحه‌بندی می‌شود');
    $tail=$s->messages($aid,$host,'',$older[99]['id']);
    verify(count($tail)===100 && $tail[0]['id']===$newest[0]['id'],'دریافت پیام‌های جدید با after ترتیب را حفظ می‌کند');
    denied(fn()=>$s->messages($aid,$host,$sent['id']),422,'نشانگر پیام پروژهٔ دیگر پذیرفته نمی‌شود');
    $long=str_repeat('گ',App\Core\LiveKitConfig::CHAT_MAX); $s->send($aid,$host,$long,bin2hex(random_bytes(16)));
    verify($s->messages($aid,$host)[99]['body']===$long,'پیام فارسی در مرز ۴۰۰۰ نویسه سالم ذخیره و رمزگشایی می‌شود');
    denied(fn()=>$s->send($aid,$host,$long.'گ',bin2hex(random_bytes(16))),422,'متن بیش از سقف پیام رد می‌شود');
    $event = ['id' => 'EV_' . bin2hex(random_bytes(8)), 'event' => 'room_finished', 'room' => ['name' => DB::value('SELECT room_name FROM project_meetings WHERE id = ?', [$aid]), 'sid' => DB::value('SELECT room_sid FROM project_meetings WHERE id = ?', [$aid])], 'createdAt' => time()];
    $raw = json_encode($event); $auth = $cloud->token(['sha256' => base64_encode(hash('sha256', $raw, true))]);
    denied(fn() => $s->webhook($raw . ' ', $auth), 401, 'بدنهٔ دستکاری‌شدهٔ webhook رد می‌شود');
    $presence = ['id'=>'EV_'.bin2hex(random_bytes(8)),'event'=>'participant_joined','room'=>$event['room'],'participant'=>['identity'=>$u['identity']],'createdAt'=>time()];
    $presenceRaw=json_encode($presence); $s->webhook($presenceRaw,$cloud->token(['sha256'=>base64_encode(hash('sha256',$presenceRaw,true))]));
    $oldLeave=$presence; $oldLeave['id']='EV_'.bin2hex(random_bytes(8)); $oldLeave['event']='participant_left'; $oldLeave['createdAt']=time()-20;
    $oldRaw=json_encode($oldLeave); $s->webhook($oldRaw,$cloud->token(['sha256'=>base64_encode(hash('sha256',$oldRaw,true))]));
    verify((int)DB::value('SELECT last_event_at FROM meeting_members WHERE id = ?',[$u['id']])===$presence['createdAt'],'رویداد قدیمی‌تر، نسخهٔ تازهٔ حضور را بازنویسی نمی‌کند');
    $s->webhook($raw, $auth); $s->webhook($raw, $auth);
    verify(DB::value('SELECT status FROM project_meetings WHERE id = ?', [$aid]) === 'ended' && (int) DB::value('SELECT COUNT(*) FROM meeting_webhook_events WHERE event_id = ?', [$event['id']]) === 1, 'webhook امضاشده و تکراری بدون اثر دوباره');
    echo "PASS تمام آزمون‌های دامنه با دیتابیس واقعی؛ انتقال رسانهٔ Cloud در این تست شبیه‌سازی شده است.\n";
} finally { if (DB::pdo()->inTransaction()) { DB::pdo()->rollBack(); } }
