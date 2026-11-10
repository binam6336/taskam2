<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Throttle;
use App\Core\Validate;
use App\Models\SupportModel;
use App\Services\SupportDesk;

/** پشتیبانی — سمت کاربر (میز تیکت: فهرست + گفتگو). */
final class SupportController extends Controller
{
    private function desk(?array $ticket): void
    {
        $this->view('support/desk', SupportDesk::data(false, $this->uid(), $this->request, $ticket),
            ['title' => $ticket ? 'تیکت #' . pid('support_tickets', $ticket) : 'پشتیبانی', 'page' => 'support', 'bodyClass' => 'page-desk']);
    }

    public function index()
    {
        $this->desk(null);
    }

    private function owned(string $id): array
    {
        $t = SupportModel::find((int) $id, $this->uid());
        if ($t === null) {
            $this->abort(404, 'تیکت پشتیبانی یافت نشد.', 'NOT_FOUND');
        }
        return $t;
    }

    public function show(string $id)
    {
        $this->desk($this->owned($id));
    }

    public function store()
    {
        $uid = $this->uid();
        $subject = $this->request->str('subject', 255);
        $message = $this->request->str('message', SupportModel::MSG_MAX);
        if ($subject === '' || $message === '') {
            $this->respond(false, 'موضوع و متن پیام الزامی است.', '/support');
        }
        if (!Throttle::attempt('support_new', (string) $uid, 10, 3600)) {
            $this->respond(false, 'تعداد تیکت‌های جدید در یک ساعت از حد مجاز گذشته است.', '/support');
        }
        $id = DB::insert('support_tickets', ['user_id' => $uid, 'subject' => $subject, 'priority' => Validate::enum($this->request->input('priority'), array_keys(SupportModel::PRIORITIES), 'medium'), 'status' => 'open', 'last_reply_at' => date('Y-m-d H:i:s')]);
        [$ok, $err] = SupportModel::addMessage(['id' => $id, 'user_id' => $uid, 'status' => 'open'], $uid, 'user', $message, $this->request->file('attachment'));
        if (!$ok) {
            // پیوست نامعتبر: تیکت بدون پیوست ثبت می‌شود و خطا به کاربر گفته می‌شود
            SupportModel::addMessage(['id' => $id, 'user_id' => $uid, 'status' => 'open'], $uid, 'user', $message, null);
        }
        $this->respond(true, $ok ? 'تیکت پشتیبانی ثبت شد.' : 'تیکت ثبت شد ولی پیوست ذخیره نشد: ' . $err, '/support/' . pid('support_tickets', $id), ['redirect' => url('/support/' . pid('support_tickets', $id))]);
    }

    public function reply(string $id)
    {
        $t = $this->owned($id);
        if ($t['status'] === 'closed') {
            $this->respond(false, 'این تیکت بسته شده است؛ برای ادامه یک تیکت جدید ثبت کنید.', '/support/' . pid('support_tickets', $id));
        }
        if (!Throttle::attempt('support_reply', (string) $this->uid(), 60, 3600)) {
            $this->respond(false, 'تعداد پیام‌ها در یک ساعت از حد مجاز گذشته است.', '/support/' . pid('support_tickets', $id));
        }
        [$ok, $msg] = SupportModel::addMessage($t, $this->uid(), 'user', $this->request->str('message', SupportModel::MSG_MAX), $this->request->file('attachment'));
        $this->respond($ok, $msg, '/support/' . pid('support_tickets', $id));
    }

    public function close(string $id)
    {
        $t = $this->owned($id);
        DB::run("UPDATE support_tickets SET status = 'closed', closed_at = NOW() WHERE id = ?", [(int) $t['id']]);
        $this->respond(true, 'تیکت بسته شد.', '/support/' . pid('support_tickets', $id));
    }

    public function reopen(string $id)
    {
        // v7.8: تیکت بسته (توسط پشتیبان یا خود کاربر) را کاربر نمی‌تواند دوباره باز کند؛ فقط پنل مدیریت (وضعیت تیکت)
        $this->owned($id);
        $this->respond(false, 'تیکت بسته‌شده را فقط پشتیبانی می‌تواند دوباره باز کند؛ برای ادامه یک تیکت جدید ثبت کنید.', '/support/' . pid('support_tickets', $id));
    }
}
