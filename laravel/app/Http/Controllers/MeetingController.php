<?php
declare(strict_types=1);
namespace KarlixLaravel\Http\Controllers;

use App\Core\HttpException;
use App\Core\LiveKitConfig as Config;
use App\Core\Session;
use KarlixLaravel\Auth\Authentication as Auth;
use KarlixLaravel\Services\Meetings\MeetingService;

/** وب و API از یک سرویس و یک تصمیم دسترسی استفاده می‌کنند. */
final class MeetingController extends Controller
{
    protected function uid(): int
    {
        if (Auth::isImpersonating()) { throw new HttpException(403, 'ورود به جلسه در حالت ورود به‌جای کاربر مجاز نیست.', 'MEETING_IMPERSONATION'); }
        return (int) ($this->request->params['_api_user'] ?? parent::uid());
    }
    private function service(bool $write = false): MeetingService
    {
        $this->uid();
        $this->rateLimit($write ? 'meeting_write' : 'meeting_read', $write ? Config::WRITE_PER_MINUTE : 150);
        Session::release();
        return new MeetingService();
    }
    private function out($data): void { $this->json(['success' => true, 'status' => 'success', 'data' => $data]); }
    public function page(string $meeting): void
    {
        $state = (new MeetingService())->state((int) $meeting, $this->uid());
        $this->view('meetings/show', ['meeting' => $state], ['title' => 'جلسهٔ پروژه', 'page' => 'projects']);
    }
    public function projectList(string $project): void { $this->out($this->service()->projectList((int) $project, $this->uid())); }
    public function create(string $project): void { $this->out($this->service(true)->create((int) $project, $this->uid(), $this->request->str('mode', 10))); }
    public function state(string $meeting): void { $this->out($this->service()->state((int) $meeting, $this->uid())); }
    public function join(string $meeting): void { $this->out($this->service(true)->join((int) $meeting, $this->uid())); }
    public function heartbeat(string $meeting): void { $this->out($this->service()->heartbeat((int) $meeting, $this->uid())); }
    public function invite(string $meeting): void { $this->out($this->service(true)->invite((int) $meeting, $this->uid(), $this->request->arr('invitees'))); }
    public function invitations(): void { $this->out($this->service()->invitations($this->uid())); }
    public function respondInvite(string $meeting): void
    {
        $action = $this->request->str('action', 10);
        if (!in_array($action, ['accept','decline'], true)) { $this->abort(422, 'عملیات جلسه معتبر نیست.', 'MEETING_ACTION_INVALID'); }
        $this->out($this->service(true)->respond((int) $meeting, $this->uid(), $action === 'accept'));
    }
    public function members(string $meeting): void { $this->out($this->service()->members((int) $meeting, $this->uid())); }
    public function moderate(string $meeting): void { $this->out($this->service(true)->moderate((int) $meeting, $this->uid(), $this->request->int('user_id'), $this->request->str('action', 40))); }
    public function consent(string $meeting): void { $this->out($this->service(true)->consent((int) $meeting, $this->uid(), $this->request->str('ref', 40), $this->request->str('accept', 1) === '1')); }
    public function hand(string $meeting): void { $this->out($this->service(true)->hand((int) $meeting, $this->uid(), $this->request->str('raised', 1) === '1')); }
    public function leave(string $meeting): void { $this->service(true)->leave((int) $meeting, $this->uid()); $this->out([]); }
    public function end(string $meeting): void { $this->out($this->service(true)->end((int) $meeting, $this->uid())); }
    public function messages(string $meeting): void { $this->out($this->service()->messages((int) $meeting, $this->uid(), $this->request->str('before', 12), $this->request->str('after', 12))); }
    public function send(string $meeting): void { $this->out($this->service(true)->send((int) $meeting, $this->uid(), $this->request->str('body', Config::CHAT_MAX + 1), $this->request->str('client_ref', 65))); }
    public function webhook(): void
    {
        $raw = (string) app('request')->getContent();
        if (strlen($raw) > 262144) { $this->abort(413, 'حجم اعلان جلسه بیش از حد مجاز است.', 'WEBHOOK_TOO_LARGE'); }
        (new MeetingService())->webhook($raw, (string) $this->request->header('Authorization'));
        $this->out([]);
    }
}
