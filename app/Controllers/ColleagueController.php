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
        ], ['title' => 'همکاران', 'page' => 'colleagues']);
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

    public function block(string $userId)
    {
        $uid = $this->uid();
        $t = (int) $userId;
        if ($t <= 0 || $t === $uid || !DB::value('SELECT 1 FROM users WHERE id = ? AND is_bot = 0', [$t])) {
            $this->fail('کاربر نامعتبر است.');
        }
        DB::run('INSERT IGNORE INTO colleague_blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)', [$uid, $t]);
        $this->ok('کاربر مسدود شد.');
    }

    public function unblock(string $userId)
    {
        DB::run('DELETE FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ?', [$this->uid(), (int) $userId]);
        $this->ok('کاربر از حالت مسدود خارج شد.');
    }
}
