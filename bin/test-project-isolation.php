<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$native = in_array('--laravel', $argv, true);
if ($native) { require BASE_PATH . '/laravel/bootstrap/app.php'; }
$testDB = $native ? \KarlixLaravel\Database\DB::class : \App\Core\DB::class;
$testTaskModel = $native ? \KarlixLaravel\Repositories\TaskModel::class : \App\Models\TaskModel::class;
$testAccess = $native ? \KarlixLaravel\Services\Access::class : \App\Services\Access::class;

if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
try {
 $testDB::pdo()->beginTransaction();$ids=[];
 for($i=0;$i<2;$i++)$ids[]=$testDB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'دسترسی','password_hash'=>'test','status'=>'active']);
 [$actor,$owner]=$ids;
 $a=$testDB::insert('projects',['user_id'=>$actor,'title'=>'A']);$b=$testDB::insert('projects',['user_id'=>$owner,'title'=>'B']);
 $testDB::insert('project_members',['project_id'=>$b,'user_id'=>$actor,'permissions'=>'[]','task_permissions'=>'[]','board_permissions'=>'[]']);
 $ta=$testDB::insert('tasks',['user_id'=>$actor,'assignee_id'=>$actor,'project_id'=>$a,'title'=>'A']);
 $tb=$testDB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$b,'title'=>'B']);
 if(!$testAccess::canTask($testTaskModel::raw($ta),$actor,'edit_info')||$testAccess::canTask($testTaskModel::raw($tb),$actor,'edit_info'))throw new RuntimeException('cross-project privilege escalation');
 $testDB::update('tasks',['is_confidential'=>1],'id=?',[$tb]);
 if($testAccess::canTask($testTaskModel::raw($tb),$actor,'view'))throw new RuntimeException('confidential task disclosed');
 echo "PASS مدیر پروژه A در پروژه B مجوز ویرایش ندارد؛ تسک محرمانه نیز افشا نمی‌شود\n";
}finally{if($testDB::pdo()->inTransaction())$testDB::pdo()->rollBack();}
