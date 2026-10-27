<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Uploader;
use App\Services\Access;
use App\Services\TaskService;

/** سرو فایل‌های آپلودی با کنترل دسترسی (فایل‌ها داخل webroot نیستند). */
final class FileController extends Controller
{
    public function serve(string $type, string $name)
    {
        $uid = $this->uid();
        $path = in_array($type, ['avatars', 'projects', 'task_attachments', 'note_attachments', 'chat_files'], true) ? Uploader::path($type, $name) : null;
        if ($path === null) {
            $this->abort(404, 'فایل یافت نشد.', 'NOT_FOUND');
        }
        if (!$this->allowed($type, basename($name), $uid)) {
            $this->abort(403, 'دسترسی به این فایل مجاز نیست.', 'FORBIDDEN');
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $inline = isset(Uploader::SERVE_MIME[$ext]);
        $mime = Uploader::SERVE_MIME[$ext] ?? 'application/octet-stream';
        $orig = $this->originalName($type, basename($name)) ?: basename($name);

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('Content-Disposition: ' . ($inline && !isset($this->request->query['dl']) ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($orig));
        readfile($path);
        exit;
    }

    private function originalName(string $type, string $name): ?string
    {
        if ($type === 'task_attachments') {
            return DB::value('SELECT file_name FROM task_attachments WHERE file_path = ? LIMIT 1', [$name]);
        }
        if ($type === 'note_attachments') {
            return DB::value('SELECT file_name FROM note_attachments WHERE file_path = ? LIMIT 1', [$name]);
        }
        if ($type === 'chat_files') {
            $m = DB::one('SELECT attachment_name, sender_id, receiver_id FROM chat_messages WHERE attachment = ? LIMIT 1', [$name]);
            return $m ? \App\Models\ChatModel::open($m['attachment_name'], (int) $m['sender_id'], (int) $m['receiver_id']) : null;
        }
        return null;
    }

    private function allowed(string $type, string $name, int $uid): bool
    {
        switch ($type) {
            case 'avatars':
            case 'projects':
                return true; // تصاویر پروفایل برای کاربران واردشده قابل مشاهده است
            case 'chat_files':
                return (bool) DB::value('SELECT 1 FROM chat_messages WHERE attachment = ? AND (sender_id = ? OR receiver_id = ?) LIMIT 1', [$name, $uid, $uid]);
            case 'task_attachments':
                $t = DB::one('SELECT t.id, t.user_id, t.assignee_id, t.project_id FROM task_attachments a INNER JOIN tasks t ON t.id = a.task_id WHERE a.file_path = ? LIMIT 1', [$name]);
                break;
            default:
                $t = DB::one('SELECT t.id, t.user_id, t.assignee_id, t.project_id FROM note_attachments a INNER JOIN task_notes n ON n.id = a.note_id INNER JOIN tasks t ON t.id = n.task_id WHERE a.file_path = ? LIMIT 1', [$name]);
        }
        return $t !== null && TaskService::canViewShared($t, $uid);
    }
}
