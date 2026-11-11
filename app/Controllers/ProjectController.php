<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Uploader;
use App\Models\ProjectModel;
use App\Services\Access;
use App\Services\ColleagueService;
use App\Services\ProjectService;
use App\Services\TaskAnalytics;

final class ProjectController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $projects = ProjectModel::forUser($uid);
        foreach ($projects as &$p) {
            $p['members'] = ProjectModel::members((int) $p['id']);
            $p['pending_ids'] = $p['is_creator'] || in_array('add_member', $p['perms'], true) ? ProjectModel::pendingUserIds((int) $p['id']) : [];
        }
        unset($p);
        $colleagues = array_map(static fn($c) => ['id' => pid('users', $c['user_id']), 'name' => full_name($c), 'mobile' => $c['mobile'], 'avatar' => avatar_url($c['avatar'])], ColleagueService::listFor($uid));
        $this->view('projects/index', [
            'projects' => $projects, 'colleagues' => $colleagues, 'requests' => ProjectModel::pendingRequestsFor($uid),
            'history' => ProjectModel::leaveHistory($uid), 'blockedCount' => \App\Services\BlockService::list('project', $uid, 1)['total'], 'permDefs' => Access::PROJECT_PERMS, 'taskPermDefs' => Access::TASK_PERMS, 'boardPermDefs' => Access::BOARD_PERMS, 'roles' => Access::ROLES + ['custom' => Access::ROLE_CUSTOM],
        ], ['title' => 'پروژه‌ها', 'page' => 'projects']);
    }

    private function image(): ?array
    {
        return $this->request->file('profile_image');
    }

    public function store()
    {
        $r = $this->request;
        [$ok, $msg] = ProjectService::create($this->uid(), $r->str('project_title', 255), $r->str('project_description', 3000), $r->int('max_members', 10), array_map('intval', $r->arr('members')), $this->image(), $r->str('priority_type', 20) ?: null);
        $this->respond($ok, $msg, '/projects');
    }

    public function update(string $id)
    {
        $r = $this->request;
        $members = $r->has('members_present') ? array_map('intval', $r->arr('members')) : null;
        [$ok, $msg] = ProjectService::update((int) $id, $this->uid(), $r->str('project_title', 255), $r->str('project_description', 3000), $r->int('max_members', 10), $members, $this->image(), $r->str('priority_type', 20) ?: null);
        $this->respond($ok, $msg, '/projects');
    }

    public function leave(string $id)
    {
        [$ok, $msg] = ProjectService::leave((int) $id, $this->uid());
        $this->respond($ok, $msg, '/projects');
    }

    public function block(string $id)
    {
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || (int) $p['user_id'] === $uid || !Access::isProjectMember((int) $id, $uid)) {
            $this->respond(false, 'امکان مسدودسازی این پروژه وجود ندارد.', '/projects');
        }
        DB::run('INSERT IGNORE INTO project_blocks (project_id, user_id) VALUES (?, ?)', [(int) $id, $uid]);
        $this->respond(true, 'پروژه مسدود شد؛ دیگر دعوت جدیدی از آن دریافت نمی‌کنید.', '/projects');
    }

    public function unblock(string $id)
    {
        DB::run('DELETE FROM project_blocks WHERE project_id = ? AND user_id = ?', [(int) $id, $this->uid()]);
        $this->respond(true, 'مسدودیت پروژه برداشته شد.', '/projects');
    }

    public function memberPermissions(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::setPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')));
        $this->respond($ok, $msg, '/projects');
    }

    public function memberTaskPermissions(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::setTaskPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')));
        $this->respond($ok, $msg, '/projects');
    }

    public function memberBoardPermissions(string $id, string $userId)
    {
        $limits = $this->request->input('limits') !== null ? json_decode((string) $this->request->input('limits'), true) : null;
        if ($limits !== null && ($err = ProjectService::cleanBoardLimits(is_array($limits) ? $limits : [], (int) $id)[1])) {
            $this->respond(false, $err, '/projects');                 // سقف نامعتبر → هیچ چیز ذخیره نمی‌شود
        }
        $wc = $this->request->input('weekly_capacity');
        if ($wc !== null && ($wcErr = ProjectService::weeklyCapacityError($wc))) {
            $this->respond(false, $wcErr, '/projects');
        }
        [$ok, $msg] = ProjectService::setBoardPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')),
            !$this->request->input('view_present') ? null : ($this->request->input('view_all') ? 'all' : array_map('intval', $this->request->arr('view_users'))));
        // سقف تعداد تسک (پاپ‌آپ «سقف تعداد تسک»): JSON {status: n|""}
        if ($ok && $limits !== null) {
            [$ok, $msg2] = ProjectService::setBoardLimits((int) $id, $this->uid(), (int) $userId, is_array($limits) ? $limits : []);
            $msg = $ok ? $msg : $msg2;
        }
        // ظرفیت هفتگی بار کاری (v7.10): خالی = پیش‌فرض پروژه
        if ($ok && $this->request->input('weekly_capacity') !== null) {
            [$ok, $msg3] = ProjectService::setWeeklyCapacity((int) $id, $this->uid(), (int) $userId, $this->request->input('weekly_capacity'));
            $msg = $ok ? $msg : $msg3;
        }
        $this->respond($ok, $msg, '/projects');
    }

    /** افزودن همکار به پروژه (از کارت همکار در صفحه‌ی همکاران)؛ همان قواعد API: دسترسی add_member، همکار تأییدشده، ظرفیت و تأیید دستی */
    public function addMember(string $id)
    {
        $pid = (int) $id;
        $target = $this->request->int('user_id');
        if (ProjectModel::find($pid) === null || !Access::isProjectMember($pid, $this->uid())) {
            $this->respond(false, 'پروژه یافت نشد.', '/colleagues');
        }
        if (!Access::projectCan($pid, $this->uid(), 'add_member')) {
            $this->respond(false, 'شما دسترسی افزودن کاربر ندارید.', '/colleagues');
        }
        [$ok, $msg, $state] = ProjectService::invite($pid, $this->uid(), $target);
        $this->respond($ok, $msg, '/colleagues', ['state' => $state]);
    }

    public function removeMember(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::removeMember((int) $id, $this->uid(), (int) $userId);
        $this->respond($ok, $msg, '/projects');
    }

    public function respondRequest(string $id)
    {
        $action = $this->request->str('action');
        if (!in_array($action, ['accept', 'reject'], true)) {
            $this->respond(false, 'نوع پاسخ مشخص نیست.', '/projects');
        }
        [$ok, $msg] = ProjectService::respond((int) $id, $this->uid(), $action === 'accept');
        $this->respond($ok, $msg, '/projects');
    }

    public function clearHistory()
    {
        DB::run('DELETE FROM project_leave_history WHERE user_id = ?', [$this->uid()]);
        $this->respond(true, 'تاریخچه‌ی پروژه‌های سابق پاک شد.', '/projects');
    }

    public function analytics(string $id)
    {
        $this->rateLimit('heavy_analytics', 30);
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || !Access::isProjectMember((int) $id, $uid)) {
            $this->abort(404, 'پروژه یافت نشد.', 'NOT_FOUND');
        }
        if (!Access::projectCan((int) $id, $uid, 'view_analytics')) {
            $this->abort(403, 'دسترسی مشاهده‌ی تحلیل‌های این پروژه را ندارید.', 'FORBIDDEN');
        }
        $people = array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u)], TaskAnalytics::people((int) $id));
        $this->view('projects/analytics', [
            'project' => $p, 'a' => ProjectModel::analytics((int) $id, $uid), 'k' => TaskAnalytics::project((int) $id), 'people' => $people,
            // DATA-013: انتخاب پیش‌فرض = پرکارترین اعضا (نه ترتیب الفبا)
            'speed' => TaskAnalytics::speedSeries((int) $id, TaskAnalytics::busiest((int) $id, 5), '1m', 'score'),
        ], ['title' => 'تحلیل پروژه', 'page' => 'projects']);
    }

    /**
     * «بار کاری» پروژه (v7.10): HTML صفحه یا با Accept: application/json داده‌ی خام (شکل sampleData در workload.js).
     * مشاهده = سازنده یا «مشاهده‌ی تحلیل‌ها»؛ هیچ داده‌ای از پروژه‌ی دیگر وارد نمی‌شود.
     */
    public function workload(string $id)
    {
        $this->rateLimit('heavy_analytics', 30);
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || !Access::isProjectMember((int) $id, $uid)) {
            $this->abort(404, 'پروژه یافت نشد.', 'NOT_FOUND');
        }
        if (!\App\Services\WorkloadService::canView((int) $id, $uid)) {
            $this->abort(403, 'دسترسی مشاهده‌ی بار کاری این پروژه را ندارید.', 'FORBIDDEN');
        }
        if (stripos((string) $this->request->header('Accept'), 'application/json') !== false) {
            $this->json(\App\Services\WorkloadService::build((int) $id, $uid));
        }
        $this->view('projects/workload', [
            'project' => $p,
            'subjects' => \App\Models\SubjectModel::forUser($uid),
            'projects' => ProjectModel::forUser($uid),
            'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'بار کاری', 'page' => 'projects']);
    }

    /** پاسخ JSON مخصوص «بار کاری»: {success, data} یا {success:false, message, error_code} */
    private function workloadOut(array $r): void
    {
        $this->json($r['ok'] ? ['success' => true, 'message' => $r['message'], 'data' => $r['data']]
            : ['success' => false, 'message' => $r['message'], 'error_code' => $r['code'] ?: 'ERROR'], $r['ok'] ? 200 : (int) ($r['http'] ?: 422));
    }

    /** POST /projects/{project}/workload/reassign — {task_id, assignee_id, due_date?, source} */
    public function workloadReassign(string $id)
    {
        $this->rateLimit('workload_write', 60);
        $uid = $this->uid();
        if (ProjectModel::find((int) $id) === null || !Access::isProjectMember((int) $id, $uid)) {
            $this->workloadOut(['ok' => false, 'message' => 'پروژه یافت نشد.', 'code' => 'NOT_FOUND', 'http' => 404]);
        }
        $j = $this->request->json() + $this->request->post;
        $this->workloadOut(\App\Services\WorkloadService::reassign((int) $id, $uid, (int) ($j['task_id'] ?? 0), (int) ($j['assignee_id'] ?? 0),
            isset($j['due_date']) ? (string) $j['due_date'] : null, array_key_exists('due_date', $j), (string) ($j['source'] ?? '')));
    }

    /** POST /projects/{project}/workload/capacity — {capacity: 4..30} */
    public function workloadCapacity(string $id)
    {
        $this->rateLimit('workload_write', 60);
        $uid = $this->uid();
        if (ProjectModel::find((int) $id) === null || !Access::isProjectMember((int) $id, $uid)) {
            $this->workloadOut(['ok' => false, 'message' => 'پروژه یافت نشد.', 'code' => 'NOT_FOUND', 'http' => 404]);
        }
        $this->workloadOut(\App\Services\WorkloadService::setCapacity((int) $id, $uid, $this->request->input('capacity')));
    }

    /** داده‌ی نمودار مقایسه‌ی سرعت انجام (AJAX). */
    public function speed(string $id)
    {
        $this->rateLimit('heavy_analytics', 30);
        $uid = $this->uid();
        if (ProjectModel::find((int) $id) === null || !Access::isProjectMember((int) $id, $uid) || !Access::projectCan((int) $id, $uid, 'view_analytics')) {
            $this->fail('دسترسی مشاهده‌ی تحلیل‌های این پروژه را ندارید.', 403);
        }
        $q = $this->request->query;
        $users = \App\Core\Pid::ids('users', array_slice(explode(',', (string) ($q['users'] ?? '')), 0, 50));
        $this->ok('', self::speedOut(TaskAnalytics::speedSeries((int) $id, $users, (string) ($q['range'] ?? '1m'), (string) ($q['metric'] ?? 'score'))));
    }

    /**
     * خروجی CSV تحلیل پروژه (v7.7، DATA-018): خلاصه + جدول «عملکرد اعضا» + KPI زمان تأیید.
     * UTF-8 با BOM (اکسل فارسی را درست باز کند)؛ همان دسترسی «مشاهده‌ی تحلیل» پروژه.
     */
    public function analyticsExport(string $id)
    {
        $this->rateLimit('heavy_analytics', 30);
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || !Access::isProjectMember((int) $id, $uid) || !Access::projectCan((int) $id, $uid, 'view_analytics')) {
            $this->abort(404, 'پروژه یافت نشد.', 'NOT_FOUND');
        }
        $a = ProjectModel::analytics((int) $id, $uid);
        $k = TaskAnalytics::project((int) $id);
        $h = static fn(?float $x) => $x === null ? '' : (string) round($x, 1);
        $rows = [];
        $rows[] = ['تحلیل پروژه', (string) $p['title']];
        $rows[] = ['تاریخ خروجی', \App\Core\Jalali::format(date('Y-m-d H:i:s'))];
        $rows[] = [];
        $t = $a['totals'];
        $rows[] = ['کل تسک‌ها', 'تأییدشده', 'منتظر تأیید', 'تحویل‌نشده', 'عقب‌افتاده', 'میانگین زمان انجام (ساعت)', 'میانه‌ی زمان انجام (ساعت)', 'تعداد نمونه', 'نرخ برگشت برای اصلاح (٪)', 'امتیاز کلی', 'ارزیابی کلی'];
        $d = $k['durations'];
        $rows[] = [(int) $t['total'], (int) $t['done'], (int) $t['review'], (int) $t['open'] - (int) $t['review'], (int) $t['overdue'], $h($d['a']), $h($d['med']), $d['c'],
            $k['summary']['rework_pct'] ?? '', $k['summary']['rating']['score'] ?? '', $k['summary']['rating']['label']];
        $rows[] = [];
        $rows[] = ['عضو', 'موبایل', 'کل', 'تأییدشده', 'منتظر تأیید', 'تحویل‌نشده', 'عقب‌افتاده', 'تحویل با تأخیر', 'به‌موقع (٪)', 'برگشت برای اصلاح (٪)', 'میانگین زمان تا تحویل (ساعت)', 'میانه (ساعت)', 'تعداد تحویل‌شده', 'امتیاز سرعت', 'ارزیابی'];
        foreach ($k['members'] as $m) {
            $rows[] = [full_name($m), $m['is_orphan'] ? '' : (string) $m['mobile'], $m['total'], $m['done'], $m['waiting'], $m['open'], $m['overdue'], $m['late'], $m['on_time'] ?? '', $m['rework_pct'] ?? '',
                $h($m['avg_hours']), $h($m['median_hours']), $m['rating']['n'] ?? 0, $m['rating']['score'] ?? '', $m['rating']['label']];
        }
        if ($k['reviewers']) {
            $rows[] = [];
            $rows[] = ['تأییدکننده', 'تعداد تأیید', 'میانگین زمان تأیید (ساعت)', 'میانه (ساعت)'];
            foreach ($k['reviewers'] as $rv) {
                $rows[] = [full_name($rv), $rv['count'], $h($rv['avg_hours']), $h($rv['median_hours'])];
            }
        }
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            // جلوگیری از اجرای فرمول در اکسل (CSV injection)
            fputcsv($out, array_map(static fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v, $r));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $name = 'karlix-analytics-' . pid('projects', $p) . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
        exit;
    }

    /** سری‌های نمودار سرعت با شناسه‌ی عمومی کاربر */
    public static function speedOut(array $s): array
    {
        foreach ($s['series'] ?? [] as $i => $row) {
            $s['series'][$i]['id'] = pid('users', $row['id']);
        }
        return $s;
    }
}
