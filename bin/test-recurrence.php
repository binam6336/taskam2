<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$native = in_array('--laravel', $argv, true);
if ($native) { require BASE_PATH . '/laravel/bootstrap/app.php'; }
$testDB = $native ? \KarlixLaravel\Database\DB::class : \App\Core\DB::class;
$testR = $native ? \KarlixLaravel\Services\TaskRecurrence::class : \App\Services\TaskRecurrence::class;
$testAccess = $native ? \KarlixLaravel\Services\Access::class : \App\Services\Access::class;

if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
function recurrence_check(bool $ok, string $name): void { if (!$ok) { throw new RuntimeException($name); } echo "PASS $name\n"; }
try {
 $testDB::pdo()->beginTransaction();
 $u=$testDB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'تکرار','password_hash'=>'test','status'=>'active']);
 $p=$testDB::insert('projects',['user_id'=>$u,'title'=>'تکرار']);
 $testDB::insert('project_members',['project_id'=>$p,'user_id'=>$u,'permissions'=>json_encode(array_keys($testAccess::PROJECT_PERMS))]);
 foreach (['daily'=>1,'weekly'=>7,'custom'=>3,'monthly'=>null] as $kind=>$days) {
  $t=$testDB::insert('tasks',['user_id'=>$u,'assignee_id'=>$u,'project_id'=>$p,'title'=>'سری '.$kind,'due_date'=>'2026-01-31']);
  $r=$testDB::insert('task_recurrences',['current_task_id'=>$t,'enabled'=>1,'kind'=>$kind,'interval_days'=>$kind==='custom'?$days:null,'anchor_day'=>31]);
  $testDB::update('tasks',['recurrence_id'=>$r],'id=?',[$t]);
  recurrence_check($testR::generate($t, '2026-01-31')===null,$kind.': رعایت افق تولید');
  $next=$testR::generate($t);
  recurrence_check($next!==null && !(bool)$testDB::value('SELECT is_completed FROM tasks WHERE id=?',[$t]),$kind.': صدور نوبت مستقل پیش از تکمیل');
  recurrence_check($testR::generate($t)===null,$kind.': جلوگیری از جانشین تکراری');
  $third=$testR::generate($next);
  recurrence_check($third!==null,$kind.': نوبت‌های باز مانع ادامه نیستند');
  if($kind==='monthly')recurrence_check($testDB::value('SELECT due_date FROM tasks WHERE id=?',[$next])==='2026-02-28' && $testDB::value('SELECT due_date FROM tasks WHERE id=?',[$third])==='2026-03-31','حفظ روز اصلی ماه');
  $testDB::update('tasks',['is_completed'=>1],'id=?',[$t]);
  recurrence_check(!(bool)$testDB::value('SELECT is_completed FROM tasks WHERE id=?',[$next]),$kind.': تکمیل گذشته وضعیت آینده را تغییر نمی‌دهد');
  $testDB::update('task_recurrences',['enabled'=>0],'id=?',[$r]);
  recurrence_check($testR::generate($third)===null,$kind.': توقف سری بدون حذف تاریخچه');
 }
} finally { if($testDB::pdo()->inTransaction())$testDB::pdo()->rollBack(); }
