<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Throttle;
use App\Core\Validate;
use App\Services\ColleagueService;

final class ColleagueController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $this->view('colleagues/index', [
            'colleagues' => ColleagueService::listFor($uid),
            'incoming' => ColleagueService::incomingRequests($uid),
            'outgoing' => ColleagueService::outgoingRequests($uid),
            'blocked' => ColleagueService::blocked($uid),
            'followers' => ColleagueService::followers($uid),
            'addProjects' => self::addableProjects($uid),
        ], ['title' => 'همکاران', 'page' => 'colleagues']);
    }

    /** «افزودن به پروژه» روی کارت همکار: پروژه‌هایی که کاربر اجازه‌ی افزودن عضو در آن‌ها را دارد + اعضا/در انتظارهای هرکدام */
    private static function addableProjects(int $uid): array
    {
        $out = [];
        foreach (\App\Models\ProjectModel::forUser($uid) as $p) {
            $pid = (int) $p['id'];
            if (!\App\Services\Access::projectCan($pid, $uid, 'add_member')) {
                continue;
            }
            $members = array_map('intval', \App\Core\DB::column('SELECT user_id FROM project_members WHERE project_id = ?', [$pid]));
            $out[] = ['id' => $pid, 'title' => $p['title'], 'image' => project_image_url($p['profile_image'] ?? null),
                'members' => $members, 'pending' => \App\Models\ProjectModel::pendingUserIds($pid), 'full' => count($members) >= (int) $p['max_members']];
        }
        return $out;
    }

    public function search()
    {
        // جلوگیری از پیمایش شماره‌ها برای پیدا کردن کاربران
        if (!Throttle::attempt('user_lookup', (string) $this->uid(), 30, 600)) {
            $this->fail('تعداد جستجوها زیاد است؛ چند دقیقه بعد دوباره تلاش کنید.', 429);
        }
        $m = Validate::mobile($this->request->str('mobile', 30));
        if ($m === null) {
            $this->fail('شماره موبایل نامعتبر است (۰۹xxxxxxxxx).');
        }
        $r = ColleagueService::search($this->uid(), $m);
        if ($r === null) {
            $this->fail('کاربری با این شماره یافت نشد.', 404);
        }
        $u = $r['user'];
        $this->ok('', ['found' => [
            'id' => (int) $u['id'], 'name' => full_name($u), 'mobile' => $u['mobile'], 'avatar_url' => avatar_url($u['avatar']),
            'already_added' => $r['already_added'], 'is_blocked_by_me' => $r['is_blocked_by_me'], 'has_blocked_me' => $r['has_blocked_me'],
            'has_pending_request' => $r['has_pending_request'], 'accepts_requests' => $r['accepts_requests'],
        ]]);
    }

    public function add()
    {
        [$ok, $msg, $state] = ColleagueService::add($this->uid(), $this->request->int('colleague_user_id'));
        $ok ? $this->ok($msg, ['state' => $state]) : $this->fail($msg);
    }

    public function accept(string $id)
    {
        [$ok, $msg] = ColleagueService::respond((int) $id, $this->uid(), true);
        $ok ? $this->ok($msg) : $this->fail($msg);
    }

    public function reject(string $id)
    {
        [$ok, $msg] = ColleagueService::respond((int) $id, $this->uid(), false);
        $ok ? $this->ok($msg) : $this->fail($msg);
    }

    public function remove(string $userId)
    {
        $n = DB::run('DELETE FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$this->uid(), (int) $userId]);
        $n > 0 ? $this->ok('همکار حذف شد.') : $this->fail('همکار یافت نشد.', 404);
    }

    /** خارج شدن از لیست همکاران کسی که من را همکار کرده (تب «کسانی که مرا همکار کرده‌اند») */
    public function removeFollower(string $userId)
    {
        [$ok, $msg] = ColleagueService::removeFollower($this->uid(), (int) $userId);
        $ok ? $this->ok($msg) : $this->fail($msg, 404);
    }

    public function block(string $userId)
    {
        [$ok, $msg, $code] = ColleagueService::block($this->uid(), (int) $userId);
        $ok ? $this->ok($msg) : $this->fail($msg, $code === 'RATE_LIMITED' ? 429 : 422);
    }

    /** رفع مسدودی ردیف پنهان (کاربر غیرهمکار) با شناسه‌ی مبهم */
    public function unblockKey()
    {
        $ok = ColleagueService::unblockByKey($this->uid(), $this->request->str('key', 40));
        $ok ? $this->ok('کاربر از حالت مسدود خارج شد.') : $this->fail('مسدودی یافت نشد.', 404);
    }

    public function unblock(string $userId)
    {
        DB::run('DELETE FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$this->uid(), (int) $userId]);
        $this->ok('کاربر از حالت مسدود خارج شد.');
    }
}
