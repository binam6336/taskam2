<?php
namespace KarlixLaravel\Console\Commands;
final class RecoverOccurrences extends \Illuminate\Console\Command
{
    protected $signature = 'karlix:recurring';
    protected $description = 'صدور نوبت‌های مستقل تسک‌های تکرارشونده';
    public function handle(): int
    {
        $this->line('Created ' . \KarlixLaravel\Services\TaskRecurrence::recover() . ' recurring task occurrence(s).');
        return self::SUCCESS;
    }
}
