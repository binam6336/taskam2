<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Models\TaskModel;
use App\Services\Access;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
try {
 DB::pdo()->beginTransaction();$ids=[];
 for($i=0;$i<2;$i++)$ids[]=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'دسترسی','password_hash'=>'test','status'=>'active']);
 [$actor,$owner]=$ids;
 $a=DB::insert('projects',['user_id'=>$actor,'title'=>'A']);$b=DB::insert('projects',['user_id'=>$owner,'title'=>'B']);
 DB::insert('project_members',['project_id'=>$b,'user_id'=>$actor,'permissions'=>'[]','task_permissions'=>'[]','board_permissions'=>'[]']);
 $ta=DB::insert('tasks',['user_id'=>$actor,'assignee_id'=>$actor,'project_id'=>$a,'title'=>'A']);
 $tb=DB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$b,'title'=>'B']);
 if(!Access::canTask(TaskModel::raw($ta),$actor,'edit_info')||Access::canTask(TaskModel::raw($tb),$actor,'edit_info'))throw new RuntimeException('cross-project privilege escalation');
 DB::update('tasks',['is_confidential'=>1],'id=?',[$tb]);
 if(Access::canTask(TaskModel::raw($tb),$actor,'view'))throw new RuntimeException('confidential task disclosed');
 echo "PASS مدیر پروژه A در پروژه B مجوز ویرایش ندارد؛ تسک محرمانه نیز افشا نمی‌شود\n";
}finally{if(DB::pdo()->inTransaction())DB::pdo()->rollBack();}
