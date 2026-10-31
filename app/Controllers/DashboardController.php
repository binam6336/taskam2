<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\ColleagueService;

final class DashboardController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $this->view('tasks/dashboard', [
            'd' => TaskModel::dashboard($uid),
            'me' => Auth::user() ?? [],
            // باکس مهم: تسک‌های امروز و عقب‌افتاده
            'focus' => TaskModel::focus($uid),
            // منتظر اقدام: منشن‌ها، پیام‌های چت، درخواست همکاری و دعوت به پروژه
            'waiting' => TaskModel::waiting($uid) + [
                'colleagues' => ColleagueService::incomingRequests($uid),
                'invites' => ProjectModel::pendingRequestsFor($uid),
            ],
            // پنجره‌ی جزئیات/ویرایش تسک داخل خود پیشخوان
            'subjects' => SubjectModel::forUser($uid), 'projects' => ProjectModel::forUser($uid), 'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'پیشخوان', 'page' => 'dashboard']);
    }
}
