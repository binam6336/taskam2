<?php
namespace KarlixLaravel\Console\Commands;
use KarlixLaravel\Database\DB;
use KarlixLaravel\Services\Karlix\Assistant;
use App\Core\Log;

/** همان صف و قفل‌های موجود؛ بدون جدول jobs جدید. */
final class AIWorker extends \Illuminate\Console\Command
{
    protected $signature = 'karlix:ai-worker {--procs=} {--seconds=55} {--once} {--child}';
    protected $description = 'اجرای صف دستیار کارلیکس با سرویس‌های بومی Laravel';
    public function handle(): int
    {
        $opt = fn(string $name, $default) => $this->option($name) ?? $default;
        $procs = max(1, min(16, (int) $opt('procs', env('AI_WORKER_PROCS', 2))));
        $seconds = max(5, min(3600, (int) $opt('seconds', 55)));
        $once = (bool) $opt('once', false);

        if (!filter_var(env('AI_WORKER', true), FILTER_VALIDATE_BOOLEAN)) {
            echo "AI_WORKER=false — اجراکننده خاموش است.\n";
            return self::SUCCESS;
        }

        // فرایند اصلی با --procs > 1 همان اسکریپت را N بار به‌صورت فرزند اجرا می‌کند و منتظر می‌ماند
        if ($procs > 1 && !$opt('child', false)) {
            $children = [];
            for ($i = 0; $i < $procs; $i++) {
                $ini = php_ini_loaded_file();                          // فرزندها همان php.ini را بگیرند
                $cmd = array_merge([PHP_BINARY], $ini ? ['-c', $ini] : [], [BASE_PATH . '/laravel/artisan', 'karlix:ai-worker', '--child', '--procs=' . $procs, '--seconds=' . $seconds], $once ? ['--once'] : []);
                $p = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
                if (is_resource($p)) {
                    $children[] = $p;
                }
            }
            foreach ($children as $p) {
                proc_close($p);
            }
            return self::SUCCESS;
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
            return self::SUCCESS;                                                    // همه‌ی جای‌ها پر است؛ دور قبلی هنوز کار می‌کند
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

        return self::SUCCESS;
    }
}
