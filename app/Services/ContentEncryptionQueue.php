<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\ContentCipher;
use App\Core\Log;

/** صف پایدار مشترک؛ flock مانع اجرای هم‌زمان دو بسته در مرورگر و cron می‌شود. */
final class ContentEncryptionQueue
{
    public const BATCH = 100;
    public const INTERVAL = 3;
    private string $file;
    public function __construct(?string $file = null)
    {
        $this->file = $file ?? STORAGE_PATH . '/app/content-encryption-job.json';
    }
    private function access(callable $fn, bool $write = false): array
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) { throw new \RuntimeException('Cannot create content encryption queue directory'); }
        $f = fopen($this->file . '.lock', 'c+');
        if (!$f) { throw new \RuntimeException('Cannot open content encryption queue'); }
        try {
            if (!flock($f, $write ? LOCK_EX : LOCK_SH)) { throw new \RuntimeException('Cannot lock content encryption queue'); }
            $raw = is_file($this->file) ? file_get_contents($this->file) : '';
            $state = $raw === '' ? ['state' => 'idle', 'done' => 0, 'remaining' => 0] : json_decode($raw, true);
            if (!is_array($state)) { throw new \RuntimeException('Invalid content encryption queue state'); }
            $state = $fn($state);
            if ($write) {
                $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $tmp = tempnam($dir, 'content-job-');
                try {
                    if (file_put_contents($tmp, $json) !== strlen($json)) { throw new \RuntimeException('Cannot save content encryption queue'); }
                    @chmod($tmp, 0600);
                    if (!rename($tmp, $this->file)) { throw new \RuntimeException('Cannot publish content encryption queue'); }
                } finally { if (is_file($tmp)) { unlink($tmp); } }

            }
            return $state;
        } finally { flock($f, LOCK_UN); fclose($f); }
    }
    public function status(): array { return $this->access(static fn($s) => $s); }
    public function start(): array
    {
        return $this->access(static function ($s) {
            if ($s['state'] === 'running') { return $s; }
            $left = ContentCipher::pending();
            return ['state' => $left ? 'running' : 'done', 'done' => 0, 'remaining' => $left, 'started_at' => time(), 'next_at' => 0];
        }, true);
    }
    public function tick(): array
    {
        return $this->access(static function ($s) {
            if ($s['state'] !== 'running' || ($s['next_at'] ?? 0) > time()) { return $s; }
            try {
                if (ContentCipher::configurationError() !== null) { throw new \RuntimeException('Content encryption configuration is not valid'); }
                $n = ContentCipher::batch(self::BATCH);
                $left = ContentCipher::pending();
                $s['done'] += $n; $s['remaining'] = $left; $s['next_at'] = time() + self::INTERVAL;
                $s['state'] = $left === 0 ? 'done' : 'running';
                $s['updated_at'] = time();
                if ($left === 0) { $s['finished_at'] = time(); }
            } catch (\Throwable $e) {
                Log::error($e, 'database');
                $s['state'] = 'error'; $s['updated_at'] = time();
            }
            return $s;
        }, true);
    }
}
