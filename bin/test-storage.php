<?php
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$native = in_array('--laravel', $argv, true);
if ($native) { require BASE_PATH . '/laravel/bootstrap/app.php'; }
$testDB = $native ? \KarlixLaravel\Database\DB::class : \App\Core\DB::class;
$testUploader = $native ? \KarlixLaravel\Files\Uploader::class : \App\Core\Uploader::class;
$testS = $native ? \KarlixLaravel\Services\StorageQuota::class : \App\Services\StorageQuota::class;

if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$uid=$testDB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'تست','password_hash'=>'test','status'=>'active']);
$files=[];
try {
 $a=$testDB::insert('projects',['user_id'=>$uid,'title'=>'الف']);$b=$testDB::insert('projects',['user_id'=>$uid,'title'=>'ب']);
 $testS::setUser($uid,1,true);
 foreach ([$a,$b] as $index=>$pid) {
  $name='test_shared_'.bin2hex(random_bytes(6)).'.txt';$files[]=$name;file_put_contents($testUploader::dir('task_attachments').'/'.$name,str_repeat('x',700000));
  $error=$testUploader::account($uid,'task_attachments',$name,$pid);
  if (($index===0 && $error!==null)||($index===1 && $error===null)) throw new RuntimeException('shared quota failed');
 }
 echo "PASS سقف مشترک دو پروژه\n";
 $testS::setUser($uid,null,true);
 $name='test_shared_'.bin2hex(random_bytes(6)).'.txt';$files[]=$name;file_put_contents($testUploader::dir('task_attachments').'/'.$name,str_repeat('x',700000));
 if($testUploader::account($uid,'task_attachments',$name,$b)!==null) throw new RuntimeException('unlimited failed');
 echo "PASS سقف نامحدود اختصاصی\n";
 $testS::setUser($uid,0,true);
 if($testS::status($a)['limit']!==0) throw new RuntimeException('zero failed');
 echo "PASS سقف صفر\n";
 $testDB::pdo()->beginTransaction();
 $testS::setDefault(2,$uid); $testS::setUser($uid,null,false);
 if($testS::status($a)['limit']!==2*$testS::MB) throw new RuntimeException('default failed');
 $testDB::pdo()->rollBack();
 echo "PASS پیروی از سقف پیش‌فرض\n";
} finally {foreach($files as $name) $testUploader::delete('task_attachments',$name);$testDB::run('DELETE FROM users WHERE id=?',[$uid]);}
