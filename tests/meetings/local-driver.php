<?php
// فقط در آزمون محلی با سرور LiveKit موقت؛ هرگز در مسیر HTTP بارگذاری نمی‌شود.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
if (PHP_SAPI !== 'cli' || !str_ends_with((string) env('DB_NAME'), '_test')) exit(1);
putenv('LIVEKIT_API_KEY=local_test_key');putenv('LIVEKIT_API_SECRET=local_test_secret_not_production');
class LocalCloud extends KarlixLaravel\Services\Meetings\LiveKitClient {
 public function requireConfigured(): void {}
 public function url(): string {return 'ws://127.0.0.1:7880';}
 public function call(string $method,array $data,array $grant):array {
  $c=stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/json'."\r\n".'Authorization: Bearer '.$this->token(['video'=>$grant]),'content'=>json_encode((object)$data),'ignore_errors'=>true,'timeout'=>10]]);
  $raw=file_get_contents('http://127.0.0.1:7880/twirp/livekit.RoomService/'.$method,false,$c);$out=json_decode($raw,true);
  preg_match('~HTTP/\S+ (\d+)~',$http_response_header[0],$status);
  if((int)$status[1]===404 && in_array($method,['RemoveParticipant','DeleteRoom']))return [];
  if((int)$status[1]>=300)throw new RuntimeException($method.': '.$raw);
  return $out;
 }
}
use KarlixLaravel\Database\DB;
use App\Core\Pid;
$s=new KarlixLaravel\Services\Meetings\MeetingService(new LocalCloud());
$args=json_decode($argv[1]??'{}',true);
try{
 if(($args['action']??'')==='fixture'){
  $pass='Test'.bin2hex(random_bytes(6)).'!';$u=[];
  foreach(['میزبان جلسه','عضو جلسه','بیگانه جلسه'] as $name){$mobile='099'.random_int(10000000,99999999);$id=DB::insert('users',['mobile'=>$mobile,'first_name'=>$name,'password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'status'=>'active','tour'=>'done']);$u[]=['id'=>$id,'pid'=>pid('users',$id),'mobile'=>$mobile,'password'=>$pass];}
  $p=DB::insert('projects',['user_id'=>$u[0]['id'],'title'=>'آزمون تماس زنده']);
  DB::insert('project_members',['project_id'=>$p,'user_id'=>$u[1]['id'],'permissions'=>'[]']);
  $m=$s->create($p,$u[0]['id'],'video');$mid=Pid::id('project_meetings',$m['id']);
  $s->invite($mid,$u[0]['id'],[$u[1]['pid']]);
  $readToken=bin2hex(random_bytes(24));
  DB::insert('api_tokens',['user_id'=>$u[0]['id'],'name'=>'آزمون فقط خواندن جلسه','token_hash'=>hash('sha256',$readToken),'token_prefix'=>substr($readToken,0,8),'scope'=>'read']);
  echo json_encode(['readToken'=>$readToken,'users'=>$u,'project'=>pid('projects',$p),'meeting'=>$m['id'],'testDatabase'=>env('DB_NAME')],JSON_UNESCAPED_UNICODE);exit;
 }
 $id=Pid::id('project_meetings',$args['meeting']??'');$uid=Pid::id('users',$args['uid']??'');$data=$args['data']??[];
 $r=match($args['action']){
  'join'=>$s->join($id,$uid),'state'=>$s->state($id,$uid),'heartbeat'=>$s->heartbeat($id,$uid),
  'members'=>$s->members($id,$uid),'moderate'=>$s->moderate($id,$uid,Pid::id('users',$data['user_id']??'')??0,$data['action']??''),
  'consent'=>$s->consent($id,$uid,$data['ref']??'',($data['accept']??'')==='1'),
  'end'=>$s->end($id,$uid),'leave'=>$s->leave($id,$uid),
  'invite'=>$s->invite($id,$uid,$data['invitees']??[]),
  'create'=>$s->create(Pid::id('projects',$data['project']),$uid,$data['mode']),
  'invitations'=>$s->invitations($uid),
  'projectList'=>$s->projectList(Pid::id('projects',$data['project']),$uid),
  'cleanup'=>(function() use($args){ foreach($args['users'] as $user) DB::run('DELETE FROM users WHERE pid = ?',[$user['pid']]); return []; })(),
  default=>throw new RuntimeException('unknown action'),
 };
 echo json_encode(['success'=>true,'status'=>'success','data'=>$r],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(App\Core\HttpException $e){echo json_encode(['status'=>'error','success'=>false,'message'=>$e->getMessage(),'error_code'=>$e->errorCode,'http'=>$e->status],JSON_UNESCAPED_UNICODE);}
