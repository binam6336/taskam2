<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use KarlixLaravel\Repositories\TaskModel;
use KarlixLaravel\Services\TaskService;
use App\Core\ReminderSchedule as R;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
function reminderCheck(bool $ok,string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
DB::pdo()->beginTransaction();
try {
 $users=[];foreach(['مالک','عضو','بیگانه'] as $name) { $users[]=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>$name,'status'=>'active','password_hash'=>'fixture']); }
 [$a,$b,$c]=$users;$project=DB::insert('projects',['user_id'=>$a,'title'=>'آزمون یادآوری']);
 DB::insert('project_members',['project_id'=>$project,'user_id'=>$b,'permissions'=>'[]','task_permissions'=>'["view"]']);
 $create=static fn(array $data)=>TaskService::create($a,$data+['project_id'=>$project,'title'=>'تسک یادآوری','due_date'=>date('Y-m-d',strtotime('-1 day')),'due_time'=>'09:00','reminder_every'=>'5','reminder_unit'=>'minute']);
 $result=$create([]);reminderCheck($result['ok'],'ثبت با فاصله پنج دقیقه');$id=(int)$result['data']['id'];
 $row=TaskModel::raw($id);reminderCheck((int)$row['reminder_every']===5 && $row['reminder_unit']==='minute','ذخیره تنظیمات در دیتابیس');
 $ready=TaskModel::dueNotifications($a);$item=array_values(array_filter($ready,static fn($r)=>(int)$r['id']===$id))[0];$refs=[pid('tasks',$id)=>$item['reminder_ref']];
 reminderCheck(TaskModel::claimNotifications($c,[$id],$refs)===[],'بیگانه نمی‌تواند نوبت اعلان را بگیرد');
 reminderCheck(TaskModel::claimNotifications($a,[$id],$refs)===[$id] && TaskModel::claimNotifications($a,[$id],$refs)===[],'رقابت دو کانال فقط یک برنده دارد');
 reminderCheck(!in_array($id,array_column(TaskModel::dueNotifications($a),'id')),'پیش از فاصله انتخابی اعلان آماده نیست');
 DB::run('UPDATE tasks SET notified_at=NOW()-INTERVAL 6 MINUTE WHERE id=?',[$id]);
 $ready=TaskModel::dueNotifications($a);$next=array_values(array_filter($ready,static fn($r)=>(int)$r['id']===$id))[0];
 reminderCheck($next['reminder_ref']!==$item['reminder_ref'],'نوبت بعد شناسه تازه دارد');
 reminderCheck(TaskModel::claimNotifications($a,[$id],$refs)===[],'تأیید قدیمی نمی‌تواند نوبت تازه را مصرف کند');
 reminderCheck(TaskModel::claimNotifications($a,[$id],[pid('tasks',$id)=>$next['reminder_ref']])===[$id],'تکرار پس از شش دقیقه');
 foreach(['hour'=>'2 HOUR','day'=>'2 DAY','week'=>'2 WEEK','month'=>'2 MONTH'] as $unit=>$age){
  DB::run("UPDATE tasks SET reminder_every=1, reminder_unit=?, notified_at=NOW()-INTERVAL $age WHERE id=?",[$unit,$id]);
  reminderCheck(in_array($id,array_column(TaskModel::dueNotifications($a),'id')),'بازه '.$unit.' در SQL آماده می‌شود');
 }
 $day=$create(['due_time'=>'']);$dayId=(int)$day['data']['id'];reminderCheck(in_array($dayId,array_column(TaskModel::dueNotifications($a),'id')),'تاریخ بدون ساعت پس از پایان روز یادآوری دارد');
 $today=$create(['due_date'=>date('Y-m-d'),'due_time'=>'']);reminderCheck(!in_array((int)$today['data']['id'],array_column(TaskModel::dueNotifications($a),'id')),'بدون ساعت در میانه همان روز عقب‌افتاده نیست');
 foreach([['is_completed'=>1],['board_status'=>'review'],['blocked_at'=>date('Y-m-d H:i:s')],['archived_at'=>date('Y-m-d H:i:s')]] as $stop){
  DB::update('tasks',$stop,'id=?',[$id]);reminderCheck(!in_array($id,array_column(TaskModel::dueNotifications($a),'id')),'توقف با '.array_key_first($stop));DB::update('tasks',['is_completed'=>0,'board_status'=>'todo','blocked_at'=>null,'archived_at'=>null],'id=?',[$id]);
 }
 DB::run('UPDATE tasks SET notified_at=NOW(),reminder_every=5,reminder_unit="minute" WHERE id=?',[$id]);
 $off=TaskService::update($a,$id,['reminder_every'=>'']);reminderCheck($off['ok'] && TaskModel::raw($id)['reminder_every']===null && !in_array($id,array_column(TaskModel::dueNotifications($a),'id')),'خاموش‌کردن تکرار اعلان تازه تولید نمی‌کند');
 $denied=TaskService::update($b,$id,['reminder_every'=>'2','reminder_unit'=>'hour']);reminderCheck(!$denied['ok'] && $denied['http']===403,'ویرایش بازه نیازمند مجوز edit_due همان تسک است');
 foreach([['reminder_every'=>0],['reminder_every'=>-1],['reminder_every'=>'1.5'],['reminder_every'=>1001],['reminder_every'=>[]],['reminder_unit'=>'year']] as $bad){$invalid=$create($bad);reminderCheck(!$invalid['ok'] && $invalid['code']==='REMINDER_INVALID','رد ورودی نامعتبر');}
 reminderCheck(R::validate(['reminder_every'=>'۱۲','reminder_unit'=>'hour'],null,'2026-01-01')['reminder_every']===12,'اعداد فارسی پذیرفته می‌شوند');
 $on=TaskService::update($a,$id,['reminder_every'=>'2','reminder_unit'=>'hour']);$clear=TaskService::update($a,$id,['due_date'=>'']);reminderCheck($on['ok'] && $clear['ok'] && TaskModel::raw($id)['reminder_every']===null,'حذف تاریخ تنظیم تکرار را پاک می‌کند');
 DB::run('UPDATE projects SET blocked_at=NOW() WHERE id=?',[$project]);reminderCheck(!TaskModel::dueNotifications($a),'پروژه مسدود اعلان ندارد');
 DB::run('UPDATE projects SET blocked_at=NULL WHERE id=?',[$project]);
 $month=$create(['due_date'=>'2026-01-01','reminder_every'=>'1','reminder_unit'=>'month']);$monthId=(int)$month['data']['id'];
 DB::run('UPDATE tasks SET notified_at="2026-01-31 10:00:00" WHERE id=?',[$monthId]);
 DB::pdo()->exec("SET timestamp=UNIX_TIMESTAMP('2026-02-28 09:59:00')");
 reminderCheck(!in_array($monthId,array_column(TaskModel::dueNotifications($a),'id')),'ماه کوتاه‌تر: پیش از لحظه مقرر اعلان نمی‌شود');
 DB::pdo()->exec("SET timestamp=UNIX_TIMESTAMP('2026-02-28 10:00:00')");
 reminderCheck(in_array($monthId,array_column(TaskModel::dueNotifications($a),'id')),'۳۱ ژانویه به پایان فوریه تنظیم می‌شود');
 DB::pdo()->exec('SET timestamp=0');
 if (!\KarlixLaravel\Services\Push::enabled()) { throw new RuntimeException('Push در محیط آزمایش آماده نیست'); }
 $endpoint='https://example.invalid/reminder-fixture-'.bin2hex(random_bytes(4));
 DB::insert('push_subscriptions',['user_id'=>$a,'endpoint'=>$endpoint,'endpoint_hash'=>hash('sha256',$endpoint),'p256dh'=>'synthetic-fixture','auth'=>'synthetic-fixture']);
 $pushTask=$create([]);$pushId=(int)$pushTask['data']['id'];
 $queued=\KarlixLaravel\Services\Push::sweepDue(true);
 $queue=new ReflectionProperty(\KarlixLaravel\Services\Push::class,'queue');$pending=$queue->getValue();
 reminderCheck($queued>0 && count(array_filter($pending,static fn($n)=>$n[1]['tag']==='task'.pid('tasks',$pushId)))===1,'مسیر واقعی sweepDue یک اعلان برای نوبت صف می‌کند؛ انتقال شبکه اجرا نمی‌شود');
 $queue->setValue(null,[]);
 reminderCheck(\KarlixLaravel\Services\Push::sweepDue(true)===0,'اجرای دوباره کران نوبت مصرف‌شده را صف نمی‌کند');
 DB::run('UPDATE tasks SET notified_at=NOW()-INTERVAL 6 MINUTE WHERE id=?',[$pushId]);
 reminderCheck(\KarlixLaravel\Services\Push::sweepDue(true)===1,'کران نوبت بعد را پس از بازه دوباره صف می‌کند');
 $queue->setValue(null,[]);
 $seriesTask=$create(['due_date'=>'2026-01-01','reminder_every'=>'7','reminder_unit'=>'hour']);$seriesId=(int)$seriesTask['data']['id'];
 $series=DB::insert('task_recurrences',['current_task_id'=>$seriesId,'enabled'=>1,'kind'=>'daily','anchor_day'=>1]);DB::update('tasks',['recurrence_id'=>$series],'id=?',[$seriesId]);
 $nextSeries=\KarlixLaravel\Services\TaskRecurrence::generate($seriesId);
 $nextRow=TaskModel::raw($nextSeries);
 reminderCheck((int)$nextRow['reminder_every']===7 && $nextRow['reminder_unit']==='hour' && $nextRow['notified_at']===null,'نوبت تازه تسک تکرارشونده فاصله را با وضعیت اعلان مستقل کپی می‌کند');
 reminderCheck(DB::value('SELECT COUNT(*) FROM task_events WHERE task_id=? AND type="reminder"',[$id])>0,'تغییرات فاصله در تاریخچه ثبت می‌شود');
} finally { (new ReflectionProperty(\KarlixLaravel\Services\Push::class,'queue'))->setValue(null,[]); DB::pdo()->exec('SET timestamp=0'); DB::pdo()->rollBack(); }
