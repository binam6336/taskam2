<?php
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';require BASE_PATH.'/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;use KarlixLaravel\Services\GlobalSearch;
if(!str_ends_with((string)env('DB_NAME'),'_test'))exit(1);
DB::pdo()->beginTransaction();
try{
 $a=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'bench','status'=>'active','password_hash'=>'test']);
 $b=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'other','status'=>'active','password_hash'=>'test']);
 $p=DB::insert('projects',['user_id'=>$a,'title'=>'پروژه سنجه']);
 for($i=0;$i<2000;$i++){
  $owner=$i<400?$a:$b;
  $t=DB::insert('tasks',['user_id'=>$owner,'project_id'=>$owner===$a?$p:null,'title'=>'کارلیکس شماره '.$i,'description'=>'سنجه '.str_repeat('متن رمزگذاری شده ',10).$i,'is_completed'=>$i%2]);
  DB::insert('task_notes',['task_id'=>$t,'user_id'=>$owner,'note'=>'کارلیکس سنجه گزارش '.$i]);
 }
 $times=[];for($i=0;$i<3;$i++){ $start=microtime(true);$r=GlobalSearch::run($a,'کارلیکس سنجه');$times[]=round((microtime(true)-$start)*1000,2);}
 echo json_encode(['rows'=>2000,'own_tasks'=>400,'foreign_tasks'=>1600,'reports'=>2000,'ms'=>$times,'groups'=>array_map(fn($g)=>[$g['key'],count($g['items'])],$r['groups'])],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
}finally{DB::pdo()->rollBack();}
