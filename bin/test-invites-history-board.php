<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use KarlixLaravel\Repositories\TaskModel;
use KarlixLaravel\Services\BoardService;
use KarlixLaravel\Services\TaskHistory;
use KarlixLaravel\Services\NotificationCenter;
use KarlixLaravel\Services\Meetings\MeetingService;
use KarlixLaravel\Services\Meetings\LiveKitClient;
if (!str_ends_with((string)env('DB_NAME'),'_test')) { exit(1); }
class InviteCloudFixture extends LiveKitClient {
 public function requireConfigured():void{}
 public function call(string $method,array $data,array $grant):array {return $method==='CreateRoom'?['sid'=>'RM_fixture']:[];}
}
function verifyFix(bool $ok,string $text):void {if(!$ok)throw new RuntimeException($text);echo "PASS $text\n";}
DB::pdo()->beginTransaction();
try {
 $ids=[];
 foreach(['میزبان','دعوت‌شده','بیگانه'] as $name)$ids[]=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>$name,'status'=>'active','password_hash'=>'fixture']);
 [$owner,$member,$outsider]=$ids;
 $p=DB::insert('projects',['user_id'=>$owner,'title'=>'جلسهٔ پروژهٔ آزمایشی']);
 DB::insert('project_members',['project_id'=>$p,'user_id'=>$member,'permissions'=>'[]']);
 $s=new MeetingService(new InviteCloudFixture());$meeting=$s->create($p,$owner,'audio');$mid=App\Core\Pid::id('project_meetings',$meeting['id']);
 $s->invite($mid,$owner,[pid('users',$member)]);
 $invites=$s->invitations($member);
 verifyFix(count($invites)===1 && $invites[0]['host_name']==='میزبان' && $invites[0]['project_title']==='جلسهٔ پروژهٔ آزمایشی','دعوت پیشخوان با نام میزبان و پروژه');
 verifyFix($s->invitations($outsider)===[],'دعوت پروژه برای بیگانه نمایش داده نمی‌شود');
 $feed=NotificationCenter::feed($member);$item=$feed['items'][0];
 verifyFix($item['kind']==='meeting' && $item['meeting_id']===$meeting['id'] && $item['action_label']==='پیوستن به جلسه' && str_ends_with($item['url'],'/meetings/'.$meeting['id']),'اعلان زنگ با دکمه و مسیر مستقیم جلسه');
 $bot=KarlixLaravel\Repositories\UserModel::botId();
 $msg=DB::one('SELECT * FROM chat_messages WHERE receiver_id=? AND sender_id=? ORDER BY id DESC LIMIT 1',[$member,$bot]);
 verifyFix(str_contains(KarlixLaravel\Repositories\ChatModel::open($msg['message'],$bot,$member),'[[meeting:'.$meeting['id'].']]'),'پیام خصوصی رمزگذاری‌شده حاوی پیوند پیوستن به جلسه است');
 $s->invite($mid,$owner,[pid('users',$member)]);
 verifyFix(count(NotificationCenter::feed($member)['items'])===1,'تکرار دعوت باز، پیام و اعلان تکراری نمی‌سازد');
 $s->respond($mid,$member,false);verifyFix($s->invitations($member)===[],'رد دعوت از پیشخوان حذف می‌شود');
 $s->invite($mid,$owner,[pid('users',$member)]);
 verifyFix($s->invitations($member)[0]['invitation_ref']!==$invites[0]['invitation_ref'],'دعوت دوباره پس از رد، دور تازه‌ای برای پنجرهٔ دعوت دارد');
 $t=DB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$p,'title'=>'تسک عادی','due_date'=>date('Y-m-d')]);
 $r=DB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$p,'title'=>'تکراری','due_date'=>date('Y-m-d')]);
 $series=DB::insert('task_recurrences',['current_task_id'=>$r,'anchor_day'=>(int)date('j')]);DB::update('tasks',['recurrence_id'=>$series],'id=?',[$r]);
 $old=DB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$p,'title'=>'تکرار قدیمی','recurrence_id'=>$series,'recurrence_previous_id'=>$r,'due_date'=>date('Y-m-d')]);
 [$cols]=$columns=BoardService::columns($owner,['project'=>$p,'focus'=>$r]);
 $visible=array_merge(...array_values($cols));
 verifyFix(array_column($visible,'id')===[$t],'نوبت‌های تکراری جدید و قدیمی حتی با focus در بورد نیستند');
 [$search]=BoardService::columns($owner,['project'=>$p,'q'=>'تکراری']);verifyFix(count(array_merge(...array_values($search)))===0,'جستجو هم تسک تکراری را به بورد برنمی‌گرداند');
 DB::update('tasks',['archived_at'=>date('Y-m-d H:i:s'),'board_status'=>'done','is_completed'=>1],'id=?',[$r]);
 verifyFix(BoardService::archive($owner,['project'=>$p])['total']===0,'آرشیو و شمارندهٔ بورد تسک تکراری ندارند');
 $rows=TaskModel::calendar($owner,date('Y-m-d'),date('Y-m-d'),'all',true);
 verifyFix(in_array($r,array_column($rows,'id')) && TaskModel::raw($r)!==null,'تسک تکراری حذف نشده و در تقویم باقی است');
 TaskHistory::log($t,$owner,'title',['from'=>'نام قدیمی','to'=>'تسک عادی']);
 TaskHistory::log($t,$member,'confidential_on',['count'=>2]);
 TaskHistory::log($t,$owner,'group_changed',['from'=>12,'to'=>14,'name'=>'واحد قدیمی']);
 TaskHistory::log($t,$member,'note_edited',['author'=>$owner]);
 $history=TaskHistory::forTask(TaskModel::raw($t));
 verifyFix(count($history)===5 && in_array('group_changed',array_column($history,'type')),'تمام رویدادهای ذخیره‌شده حتی رویداد قابلیت قدیمی در تاریخچه هستند');
 verifyFix(!isset($history[0]['data']['assignee']) && $history[0]['data']['assignee_user']['name']==='میزبان','هویت مسئول در تاریخچه با نام نمایش داده می‌شود و شناسه داخلی نمی‌رود');
 verifyFix((bool)preg_match('/[۰-۹]{2}:[۰-۹]{2}:[۰-۹]{2}$/u',$history[0]['at']) && isset($history[0]['at_iso']),'تاریخچه تاریخ و ساعت با ثانیه دارد');
} finally {if(DB::pdo()->inTransaction())DB::pdo()->rollBack();}
