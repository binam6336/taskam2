<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\DB;
use App\Core\ContentCipher as C;
use App\Services\ContentEncryptionQueue as Q;
if (!str_ends_with((string) env('DB_NAME'), '_test') || !C::enabled()) { exit(1); }
$file=tempnam(sys_get_temp_dir(),'queue-test');unlink($file);
try {
 DB::pdo()->beginTransaction();
 $u=DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'صف','password_hash'=>'test','status'=>'active']);
 $insert=DB::pdo()->prepare('INSERT INTO texts (id,user_id,title,content,color) VALUES (?,?,?,?,?)');
 for($i=0;$i<130;$i++)$insert->execute(['queue_'.bin2hex(random_bytes(10)),$u,'عنوان '.$i,'محتوا '.$i,'yellow']);
 $q=new Q($file);$s=$q->start();$initial=$s['remaining'];
 if($initial<260)throw new RuntimeException('fixture incomplete');
 if($q->start()!==$s)throw new RuntimeException('duplicate start reset queue');
 $n=0;
 do {
  $s=$q->tick();$n++;
  if($s['state']==='error')throw new RuntimeException('queue error');
  if($s['state']==='running') {
    $s['next_at']=0;file_put_contents($file,json_encode($s));
    $q=new Q($file); // بازیابی وضعیت پس از بسته‌شدن صفحه یا worker
  }
  if($n>100)throw new RuntimeException('queue stalled');
 }while($s['state']==='running');
 if($s['done']!==$initial||C::pending()!==0||$n<3)throw new RuntimeException('counts invalid');
 if($q->tick()!==$s)throw new RuntimeException('completed job ran again');
 echo "PASS صف چندبسته‌ای، شروع تکراری، بازیابی وضعیت، پایان خودکار و عدم اجرای دوباره\n";
}finally {
 if(DB::pdo()->inTransaction())DB::pdo()->rollBack();
 foreach([$file,$file.'.lock'] as $p)if(is_file($p))unlink($p);
}
