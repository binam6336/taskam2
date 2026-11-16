<?php
declare(strict_types=1);

/**
 * اجراکننده‌ی سمت سرور درخواست‌های کارلیکس در یادداشت تسک (v7.26، AI-07) — فقط CLI.
 *
 * بدون این اسکریپت، درخواست «@کارلیکس» فقط وقتی اجرا می‌شود که مرورگر خود کاربر باز باشد. با آن:
 *   - درخواست‌ها و تلاش‌های مجددشان حتی با بسته بودن مرورگر اجرا می‌شوند،
 *   - مرورگر فقط وضعیت را می‌پرسد و هیچ worker وب (PHP-FPM) چند ده ثانیه برای فراخوانی مدل اشغال نمی‌شود،
 *   - درخواستی که بیش از AI_REQUEST_EXPIRE_MINUTES در صف بماند «منقضی شد» می‌گیرد.
 *
 * اجرا با cron هر دقیقه (هر دور حدود ۵۵ ثانیه کار می‌کند و تمام می‌شود):
 *   * * * * * php /path/to/karlix/bin/ai-worker.php --procs=4 >/dev/null 2>&1
 * گزینه‌ها: --procs=N تعداد فرایند موازی (۱ تا ۱۶، پیش‌فرض AI_WORKER_PROCS یا ۲) | --seconds=N مدت هر دور (پیش‌فرض ۵۵) | --once یک دور و خروج.
 * هم‌زمانی امن است: هر درخواست به‌صورت اتمی ادعا می‌شود و حداکثر N فرایند (قفل نام‌دار) هم‌زمان کار می‌کنند، پس هم‌پوشانی دو cron مشکلی ندارد.
 * مرورگر وقتی سراغ اجرا نمی‌آید که تپش این اسکریپت (storage/cache/ai-worker.beat) کمتر از ۹۰ ثانیه پیش باشد؛ اگر متوقف شود، رفتار قبلی برمی‌گردد.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Log;
use App\Services\Karlix\Assistant;

$opt = static function (string $name, $default) use ($argv) {
    foreach ($argv as $a) {
        if (strpos($a, '--' . $name . '=') === 0) {
            return substr($a, strlen($name) + 3);
        }
        if ($a === '--' . $name) {
            return true;
        }
    }
    return $default;
};

$procs = max(1, min(16, (int) $opt('procs', env('AI_WORKER_PROCS', 2))));
$seconds = max(5, min(3600, (int) $opt('seconds', 55)));
$once = (bool) $opt('once', false);

if (!filter_var(env('AI_WORKER', true), FILTER_VALIDATE_BOOLEAN)) {
    echo "AI_WORKER=false — اجراکننده خاموش است.\n";
    exit(0);
}

// فرایند اصلی با --procs > 1 همان اسکریپت را N بار به‌صورت فرزند اجرا می‌کند و منتظر می‌ماند
if ($procs > 1 && !$opt('child', false)) {
    $children = [];
    for ($i = 0; $i < $procs; $i++) {
        $ini = php_ini_loaded_file();                          // فرزندها همان php.ini را بگیرند
        $cmd = array_merge([PHP_BINARY], $ini ? ['-c', $ini] : [], [__FILE__, '--child', '--procs=' . $procs, '--seconds=' . $seconds], $once ? ['--once'] : []);
        $p = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
        if (is_resource($p)) {
            $children[] = $p;
        }
    }
    foreach ($children as $p) {
        proc_close($p);
    }
    exit(0);
}

// حداکثر $procs فرایند هم‌زمان در کل سرور (cron های هم‌پوشان)
$base = 'kx_aiw_' . substr(md5((string) env('DB_NAME', 'taskam') . '|' . (string) env('DB_HOST', '')), 0, 10) . '_';
$slot = null;
for ($i = 0; $i < $procs; $i++) {
    if ((int) DB::value('SELECT GET_LOCK(?, 0)', [$base . $i]) === 1) {
        $slot = $i;
        break;
    }
}
if ($slot === null) {
    exit(0);                                                    // همه‌ی جای‌ها پر است؛ دور قبلی هنوز کار می‌کند
}

$end = microtime(true) + $seconds;
$done = 0;
do {
    try {
        $n = Assistant::workerTick(5);
        $done += $n;
    } catch (\Throwable $e) {
        Log::error($e, 'karlix', ['worker' => $slot]);
        $n = 0;
    }
    if ($once) {
        break;
    }
    if ($n === 0) {
        usleep(random_int(700000, 1300000));                    // صف خالی: کمی صبر (درخواست تازه حداکثر ~۱ ثانیه بعد برداشته می‌شود)
    }
} while (microtime(true) < $end);

DB::value('SELECT RELEASE_LOCK(?)', [$base . $slot]);
if ($done > 0) {
    Log::info('karlix.worker', ['slot' => $slot, 'done' => $done]);
}
