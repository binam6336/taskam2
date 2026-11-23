<?php
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Core\Uploader;
use App\Services\StorageQuota as S;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$uid=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'تست','password_hash'=>'test','status'=>'active']);
$files=[];
try {
 $a=DB::insert('projects',['user_id'=>$uid,'title'=>'الف']);$b=DB::insert('projects',['user_id'=>$uid,'title'=>'ب']);
 S::setUser($uid,1,true);
 foreach ([$a,$b] as $index=>$pid) {
  $name='test_shared_'.bin2hex(random_bytes(6)).'.txt';$files[]=$name;file_put_contents(Uploader::dir('task_attachments').'/'.$name,str_repeat('x',700000));
  $error=Uploader::account($uid,'task_attachments',$name,$pid);
  if (($index===0 && $error!==null)||($index===1 && $error===null)) throw new RuntimeException('shared quota failed');
 }
 echo "PASS سقف مشترک دو پروژه\n";
 S::setUser($uid,null,true);
 $name='test_shared_'.bin2hex(random_bytes(6)).'.txt';$files[]=$name;file_put_contents(Uploader::dir('task_attachments').'/'.$name,str_repeat('x',700000));
 if(Uploader::account($uid,'task_attachments',$name,$b)!==null) throw new RuntimeException('unlimited failed');
 echo "PASS سقف نامحدود اختصاصی\n";
 S::setUser($uid,0,true);
 if(S::status($a)['limit']!==0) throw new RuntimeException('zero failed');
 echo "PASS سقف صفر\n";
 DB::pdo()->beginTransaction();
 S::setDefault(2,$uid); S::setUser($uid,null,false);
 if(S::status($a)['limit']!==2*S::MB) throw new RuntimeException('default failed');
 DB::pdo()->rollBack();
 echo "PASS پیروی از سقف پیش‌فرض\n";
} finally {foreach($files as $name) Uploader::delete('task_attachments',$name);DB::run('DELETE FROM users WHERE id=?',[$uid]);}
