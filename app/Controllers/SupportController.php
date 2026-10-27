<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Throttle;
use App\Core\Validate;
use App\Models\SupportModel;

/** پشتیبانی — سمت کاربر. */
final class SupportController extends Controller
{
    public function index()
    {
        $this->view('support/index', ['tickets' => SupportModel::userTickets($this->uid()), 'priorities' => SupportModel::PRIORITIES], ['title' => 'پشتیبانی', 'page' => 'support']);
    }

    private function owned(string $id): array
    {
        $t = DB::one('SELECT * FROM support_tickets WHERE id = ? AND user_id = ?', [(int) $id, $this->uid()]);
        if ($t === null) {
            $this->abort(404, 'تیکت پشتیبانی یافت نشد.', 'NOT_FOUND');
        }
        return $t;
    }

    public function show(string $id)
    {
        $t = $this->owned($id);
        DB::run("UPDATE support_messages SET is_read_by_user = 1 WHERE ticket_id = ? AND sender_role = 'admin' AND is_read_by_user = 0", [(int) $t['id']]);
        $this->view('support/show', ['ticket' => $t, 'messages' => SupportModel::messages((int) $t['id'])], ['title' => 'تیکت #' . $t['id'], 'page' => 'support']);
    }

    public function store()
    {
        $uid = $this->uid();
        $subject = $this->request->str('subject', 255);
        $message = $this->request->str('message', 5000);
        if ($subject === '' || $message === '') {
            $this->respond(false, 'موضوع و متن پیام الزامی است.', '/support');
        }
        if (!Throttle::attempt('support_new', (string) $uid, 10, 3600)) {
            $this->respond(false, 'تعداد تیکت‌های جدید در یک ساعت از حد مجاز گذشته است.', '/support');
        }
        $id = DB::transaction(function () use ($uid, $subject, $message): int {
            $id = DB::insert('support_tickets', ['user_id' => $uid, 'subject' => $subject, 'priority' => Validate::enum($this->request->input('priority'), array_keys(SupportModel::PRIORITIES), 'medium'), 'status' => 'open', 'last_reply_at' => date('Y-m-d H:i:s')]);
            DB::insert('support_messages', ['ticket_id' => $id, 'sender_id' => $uid, 'sender_role' => 'user', 'message' => $message, 'is_read_by_user' => 1, 'is_read_by_admin' => 0]);
            return $id;
        });
        $this->flash('تیکت پشتیبانی ثبت شد.');
        $this->redirect('/support/' . $id);
    }

    public function reply(string $id)
    {
        $t = $this->owned($id);
        $message = $this->request->str('message', 5000);
        if ($message === '') {
            $this->respond(false, 'متن پیام را وارد کنید.', '/support/' . $id);
        }
        DB::insert('support_messages', ['ticket_id' => (int) $t['id'], 'sender_id' => $this->uid(), 'sender_role' => 'user', 'message' => $message, 'is_read_by_user' => 1, 'is_read_by_admin' => 0]);
        DB::run("UPDATE support_tickets SET status = 'open', closed_at = NULL, last_reply_at = NOW() WHERE id = ?", [(int) $t['id']]);
        $this->respond(true, 'پاسخ ارسال شد.', '/support/' . $id);
    }

    public function close(string $id)
    {
        $t = $this->owned($id);
        DB::run("UPDATE support_tickets SET status = 'closed', closed_at = NOW() WHERE id = ?", [(int) $t['id']]);
        $this->respond(true, 'تیکت بسته شد.', '/support/' . $id);
    }

    public function reopen(string $id)
    {
        $t = $this->owned($id);
        DB::run("UPDATE support_tickets SET status = 'open', closed_at = NULL WHERE id = ?", [(int) $t['id']]);
        $this->respond(true, 'تیکت دوباره باز شد.', '/support/' . $id);
    }

    public function destroy(string $id)
    {
        $t = $this->owned($id);
        DB::run('DELETE FROM support_tickets WHERE id = ?', [(int) $t['id']]);
        $this->respond(true, 'تیکت حذف شد.', '/support');
    }
}
