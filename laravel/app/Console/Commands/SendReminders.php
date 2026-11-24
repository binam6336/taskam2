<?php
namespace KarlixLaravel\Console\Commands;
final class SendReminders extends \Illuminate\Console\Command
{
    protected $signature = 'karlix:reminders';
    protected $description = 'یادآوری مهلت تسک‌ها از صف موجود';
    public function handle(): int
    {
        $n = \KarlixLaravel\Services\Push::sweepDue(true);
        \KarlixLaravel\Services\Push::flush();
        $this->line(date('Y-m-d H:i:s') . " — {$n} reminder(s) queued");
        return self::SUCCESS;
    }
}
