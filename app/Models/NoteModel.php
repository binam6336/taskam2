<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Uploader;

/** یادداشت‌ها و پیوست‌های تسک/یادداشت. */
final class NoteModel
{
    public static function forTask(int $taskId): array
    {
        $notes = DB::all('SELECT n.*, u.first_name, u.last_name, u.avatar FROM task_notes n INNER JOIN users u ON u.id = n.user_id WHERE n.task_id = ? ORDER BY n.created_at ASC, n.id ASC', [$taskId]);
        if (!$notes) {
            return [];
        }
        $ids = array_map(static fn($n) => (int) $n['id'], $notes);
        $files = DB::all('SELECT * FROM note_attachments WHERE note_id IN (' . DB::in($ids) . ') ORDER BY id', $ids);
        $by = [];
        foreach ($files as $f) {
            $by[(int) $f['note_id']][] = $f;
        }
        foreach ($notes as &$n) {
            $n['attachments'] = $by[(int) $n['id']] ?? [];
        }
        return $notes;
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT n.*, t.user_id AS task_owner_id, t.assignee_id AS task_assignee_id, t.title AS task_title, t.share_token FROM task_notes n INNER JOIN tasks t ON t.id = n.task_id WHERE n.id = ?', [$id]);
    }

    public static function deleteNote(int $id): void
    {
        $files = DB::column('SELECT file_path FROM note_attachments WHERE note_id = ?', [$id]);
        DB::run('DELETE FROM task_notes WHERE id = ?', [$id]);
        foreach ($files as $f) {
            Uploader::delete('note_attachments', $f);
        }
    }

    public static function taskAttachments(int $taskId): array
    {
        return DB::all('SELECT a.*, u.first_name, u.last_name FROM task_attachments a INNER JOIN users u ON u.id = a.user_id WHERE a.task_id = ? ORDER BY a.id', [$taskId]);
    }

    /** ذخیره‌ی پیوست‌ها؛ برمی‌گرداند [saved[], errors[]] */
    /** سقف تعداد پیوست: هر ارسال، و مجموع پیوست‌های هر تسک (v7.3) */
    public const MAX_FILES = 30;

    public static function storeAttachments(string $kind, int $parentId, int $uid, array $files): array
    {
        $table = $kind === 'task' ? 'task_attachments' : 'note_attachments';
        $col = $kind === 'task' ? 'task_id' : 'note_id';
        $type = $kind === 'task' ? 'task_attachments' : 'note_attachments';
        $saved = [];
        $errors = [];
        // v7.3: تا MAX_FILES فایل در هر ارسال؛ پیوست‌های خود تسک روی‌هم حداکثر MAX_FILES
        $room = self::MAX_FILES;
        if ($kind === 'task') {
            $room = max(0, self::MAX_FILES - (int) DB::value('SELECT COUNT(*) FROM task_attachments WHERE task_id = ?', [$parentId]));
        }
        if (count($files) > $room) {
            $errors[] = 'حداکثر ' . fa_num(self::MAX_FILES) . ' فایل برای هر تسک یا یادداشت پذیرفته می‌شود؛ ' . fa_num(count($files) - $room) . ' فایل اضافه ذخیره نشد.';
        }
        foreach (array_slice($files, 0, $room) as $f) {
            $r = Uploader::saveAttachment($f, $type, $kind . $uid, Uploader::ATTACH_TYPES, Uploader::ATTACH_MAX, $uid);
            if (!$r['ok']) {
                $errors[] = $r['error'];
                continue;
            }
            $id = DB::insert($table, [$col => $parentId, 'user_id' => $uid, 'file_name' => $r['original'], 'file_path' => $r['name'], 'file_size' => $r['size'], 'file_ext' => $r['ext'], 'file_mime' => $r['mime']]);
            $saved[] = ['id' => \App\Core\Pid::of($table, $id), 'file_name' => $r['original'], 'file_size' => $r['size'], 'file_ext' => $r['ext']];
        }
        return [$saved, $errors];
    }
}
