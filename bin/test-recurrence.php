<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Services\TaskRecurrence as R;
use App\Services\Access;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
function recurrence_check(bool $ok, string $name): void { if (!$ok) { throw new RuntimeException($name); } echo "PASS $name\n"; }
try {
 DB::pdo()->beginTransaction();
 $u=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'تکرار','password_hash'=>'test','status'=>'active']);
 $p=DB::insert('projects',['user_id'=>$u,'title'=>'تکرار']);
 DB::insert('project_members',['project_id'=>$p,'user_id'=>$u,'permissions'=>json_encode(array_keys(Access::PROJECT_PERMS))]);
 foreach (['daily'=>1,'weekly'=>7,'custom'=>3,'monthly'=>null] as $kind=>$days) {
  $t=DB::insert('tasks',['user_id'=>$u,'assignee_id'=>$u,'project_id'=>$p,'title'=>'سری '.$kind,'due_date'=>'2026-01-31']);
  $r=DB::insert('task_recurrences',['current_task_id'=>$t,'enabled'=>1,'kind'=>$kind,'interval_days'=>$kind==='custom'?$days:null,'anchor_day'=>31]);
  DB::update('tasks',['recurrence_id'=>$r],'id=?',[$t]);
  recurrence_check(R::generate($t, '2026-01-31')===null,$kind.': رعایت افق تولید');
  $next=R::generate($t);
  recurrence_check($next!==null && !(bool)DB::value('SELECT is_completed FROM tasks WHERE id=?',[$t]),$kind.': صدور نوبت مستقل پیش از تکمیل');
  recurrence_check(R::generate($t)===null,$kind.': جلوگیری از جانشین تکراری');
  $third=R::generate($next);
  recurrence_check($third!==null,$kind.': نوبت‌های باز مانع ادامه نیستند');
  if($kind==='monthly')recurrence_check(DB::value('SELECT due_date FROM tasks WHERE id=?',[$next])==='2026-02-28' && DB::value('SELECT due_date FROM tasks WHERE id=?',[$third])==='2026-03-31','حفظ روز اصلی ماه');
  DB::update('tasks',['is_completed'=>1],'id=?',[$t]);
  recurrence_check(!(bool)DB::value('SELECT is_completed FROM tasks WHERE id=?',[$next]),$kind.': تکمیل گذشته وضعیت آینده را تغییر نمی‌دهد');
  DB::update('task_recurrences',['enabled'=>0],'id=?',[$r]);
  recurrence_check(R::generate($third)===null,$kind.': توقف سری بدون حذف تاریخچه');
 }
} finally { if(DB::pdo()->inTransaction())DB::pdo()->rollBack(); }
