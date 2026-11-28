<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__,2).'/app/bootstrap.php';require BASE_PATH.'/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;use App\Core\Pid;
if (!str_ends_with((string)env('DB_NAME'),'_test')) { exit(1); }
$args=json_decode($argv[1]??'{}',true);
if (($args['action']??'')==='fixture') {
 $users=[];$password='Test'.bin2hex(random_bytes(6)).'!';
 foreach(['سازنده یادآوری','عضو فقط مشاهده'] as $name){$mobile='099'.random_int(10000000,99999999);$id=DB::insert('users',['mobile'=>$mobile,'first_name'=>$name,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'status'=>'active','tour'=>'done']);$users[]=['pid'=>pid('users',$id),'mobile'=>$mobile,'password'=>$password];}
 $uid=Pid::id('users',$users[0]['pid']);$project=DB::insert('projects',['user_id'=>$uid,'title'=>'پروژه آزمون یادآوری']);
 DB::insert('project_members',['project_id'=>$project,'user_id'=>Pid::id('users',$users[1]['pid']),'permissions'=>'["view"]','task_permissions'=>'["view"]']);
 $task=DB::insert('tasks',['user_id'=>$uid,'assignee_id'=>$uid,'project_id'=>$project,'title'=>'تسک تکرار اعلان مرورگر','due_date'=>date('Y-m-d',strtotime('-1 day')),'due_time'=>'09:00','reminder_every'=>2,'reminder_unit'=>'minute','notified_at'=>date('Y-m-d H:i:s')]);
 $token=bin2hex(random_bytes(24));DB::insert('api_tokens',['user_id'=>$uid,'name'=>'آزمون یادآوری','token_hash'=>hash('sha256',$token),'token_prefix'=>substr($token,0,8),'scope'=>'write']);
 $extra=[];
 if (!empty($args['extra'])) {
  $member=Pid::id('users',$users[1]['pid']);
  DB::insert('colleagues',['user_id'=>$uid,'colleague_user_id'=>$member]); DB::insert('colleagues',['user_id'=>$member,'colleague_user_id'=>$uid]);
  $other=DB::insert('projects',['user_id'=>$uid,'title'=>'پروژه اولویت درصدی','priority_type'=>'percent']);
  DB::insert('project_members',['project_id'=>$other,'user_id'=>$member,'permissions'=>'["view"]','task_permissions'=>'["view"]']);
  $label=DB::insert('subjects',['user_id'=>$uid,'project_id'=>$project,'title'=>'لیبل آزمون']);
  $extra=['otherProject'=>pid('projects',$other),'label'=>pid('subjects',$label)];
 }
 echo json_encode($extra+['users'=>$users,'task'=>pid('tasks',$task),'project'=>pid('projects',$project),'token'=>$token,'testDatabase'=>env('DB_NAME')]);
} elseif (($args['action']??'')==='age') {
 $id=Pid::id('tasks',$args['task']);DB::run('UPDATE tasks SET notified_at=NOW()-INTERVAL 3 MINUTE WHERE id=?',[$id]);echo '{}';
} elseif (($args['action']??'')==='cleanup') {
 foreach(($args['users']??[]) as $user){$uid=Pid::id('users',$user['pid']);if($uid)DB::run('DELETE FROM users WHERE id=?',[$uid]);}echo '{}';
}
