<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\NotificationCenter;

/** زنگ «اعلان‌ها»ی نوار بالا (v7.15): فهرست و خوانده‌شدن اعلان‌ها (پیام‌های ربات، NotificationCenter). */
final class NotificationController extends Controller
{
    /** GET /notifications/feed?before=<pid> */
    public function feed(): void
    {
        $uid = (int) $this->user()['id'];
        $this->json(['status' => 'success'] + NotificationCenter::feed($uid, substr((string) ($this->request->query['before'] ?? ''), 0, 16)));
    }

    /** POST /notifications/read — id=<pid> یک اعلان، all=1 همه */
    public function read(): void
    {
        $uid = (int) $this->user()['id'];
        $all = $this->request->str('all', 2) === '1';
        $id = $this->request->str('id', 16);
        if (!$all && $id === '') {
            $this->json(['status' => 'error', 'message' => 'اعلان مشخص نشده است.'], 422);
        }
        $n = NotificationCenter::markRead($uid, $all ? null : $id);
        $this->json(['status' => 'success', 'changed' => $n, 'unread' => NotificationCenter::unread($uid)]);
    }
}
