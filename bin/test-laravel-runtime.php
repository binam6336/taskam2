<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require BASE_PATH.'/laravel/vendor/autoload.php';
if (!str_ends_with((string)env('DB_NAME'),'_test')) { exit(1); }
$app=KarlixLaravel\Bridge::application();
$app->instance('request', Illuminate\Http\Request::create('/'));
$routes=$app->make('panel.routes');
$baseline = json_decode(file_get_contents(BASE_PATH.'/docs/migration/user-routes.json'), true, 512, JSON_THROW_ON_ERROR);
if (count($baseline) !== count($routes)) { throw new RuntimeException('route count differs'); }
foreach ($routes as $i => $route) {
    foreach (['method','path','mw'] as $key) {
        if ($route[$key] !== $baseline[$i][$key === 'mw' ? 'middleware' : $key]) { throw new RuntimeException('route contract differs: '.$route['path'].' '.$key); }
    }
}
// Session روی هر دو driver اصلی و بدون دست زدن به هویت نشست کار می‌کند.
$store = $app->make('session.store');
$store->put('nested.name', 'مقدار');
$store->flash('notice', 'پیام');
$store->save();
$store->save(); // ذخیره دوباره نباید flash را همین حالا پاک کند
$store = new KarlixLaravel\Session\SharedStore();
if ($store->get('nested.name') !== 'مقدار' || $store->get('notice') !== 'پیام') { throw new RuntimeException('session persistence failed'); }
App\Core\Session::scope('native-test-window');
$scoped = new KarlixLaravel\Session\SharedStore();
if ($scoped->has('notice')) { throw new RuntimeException('session scope leak'); }
$scoped->put('nested.name', 'پنجره'); $scoped->save();
App\Core\Session::scope(null);
$store = new KarlixLaravel\Session\SharedStore();
if ($store->get('nested.name') !== 'مقدار') { throw new RuntimeException('root session modified'); }
App\Core\Session::destroy();

foreach($routes as $route){
 if(str_starts_with($route['path'],'/admin'))throw new RuntimeException('admin route migrated');
 $c=$route['handler'][0];
 if(!str_starts_with($c,'KarlixLaravel\\Http\\Controllers\\')||!is_subclass_of($c,Illuminate\Routing\Controller::class))throw new RuntimeException('legacy controller '.$c);
}
echo "PASS route contracts/shared Store persistence/flash/impersonation scopes\n";
echo 'PASS native Laravel handlers: '.count($routes)."\n";
try {
 App\Core\DB::pdo()->beginTransaction();
 $owner=KarlixLaravel\Database\DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'بومی','password_hash'=>'test','status'=>'active']);
 $member=KarlixLaravel\Database\DB::insert('users',['mobile'=>'099'.random_int(10000000,99999999),'first_name'=>'عضو','password_hash'=>'test','status'=>'active']);
 $p=KarlixLaravel\Database\DB::insert('projects',['user_id'=>$owner,'title'=>'سیاست']);
 $t=KarlixLaravel\Database\DB::insert('tasks',['user_id'=>$owner,'assignee_id'=>$owner,'project_id'=>$p,'title'=>'متن بومی']);
 if (App\Core\ContentCipher::enabled() && !str_starts_with((string) App\Core\DB::pdo()->query('SELECT title FROM tasks WHERE id='.(int)$t)->fetchColumn(), App\Core\ContentCipher::PREFIX)) { throw new RuntimeException('native repository stored plaintext'); }
 $user=KarlixLaravel\Models\User::find($owner);$task=KarlixLaravel\Models\Task::find($t);
 if($task->title!=='متن بومی'||$task->toArray()['title']!=='متن بومی')throw new RuntimeException('model decryption failed');
 if(!Illuminate\Support\Facades\Gate::forUser($user)->allows('update',$task))throw new RuntimeException('owner policy rejected');
 if(Illuminate\Support\Facades\Gate::forUser(KarlixLaravel\Models\User::find($member))->allows('update',$task))throw new RuntimeException('policy allowed unrelated user');
 if($user->toArray()['password_hash']??false)throw new RuntimeException('hash leaked');
 Illuminate\Support\Facades\Auth::guard('karlix')->setUser($user);
 if(Illuminate\Support\Facades\Auth::id()!==$owner)throw new RuntimeException('guard failed');

 KarlixLaravel\Database\DB::update('users', ['task_link_access'=>'all'], 'id=?', [$owner]);
 KarlixLaravel\Database\DB::update('tasks', ['is_confidential'=>1], 'id=?', [$t]);
 $n=KarlixLaravel\Database\DB::insert('task_notes',['task_id'=>$t,'user_id'=>$owner,'note'=>'خصوصی']);
 $fileController=new KarlixLaravel\Http\Controllers\FileController(App\Core\Request::capture());
 $allowed=new ReflectionMethod($fileController,'allowed');
 foreach (['task_attachments'=>['task_id',$t], 'note_attachments'=>['note_id',$n]] as $type=>[$column,$parent]) {
     $name='native_security_'.bin2hex(random_bytes(6)).'.txt';
     KarlixLaravel\Database\DB::insert($type,[$column=>$parent,'user_id'=>$owner,'file_name'=>'secret.txt','file_path'=>$name,'file_size'=>1,'file_ext'=>'txt','file_mime'=>'text/plain']);
     if ($allowed->invoke($fileController,$type,$name,$member)) { throw new RuntimeException('confidential attachment leaked via shared link'); }
     if (!$allowed->invoke($fileController,$type,$name,$owner)) { throw new RuntimeException('owner attachment rejected'); }
 }
 echo "PASS Eloquent/decryption/hidden hashes/native Policies and Guard/shared transaction\n";
 echo "PASS confidential task/note attachments remain private with public share links\n";
}finally{if(App\Core\DB::pdo()->inTransaction())App\Core\DB::pdo()->rollBack();}
