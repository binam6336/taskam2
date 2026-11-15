<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Pid;
use App\Services\Karlix\Agnes;
use App\Services\Karlix\BoardAgent;
use App\Services\Karlix\BoardContext;
use App\Services\Karlix\BoardOps;
use App\Services\Karlix\BoardTools;
use App\Services\Karlix\Export;

/**
 * پنل «هوش مصنوعی» بورد (v7.20) — همه‌ی مسیرها زیر /board/ai، پنل کاربر با CSRF.
 * کاربر = Auth::user() (در «ورود مدیر به پنل کاربر» همان کاربر مشاهده‌شده)؛ مدیر واقعی در ستون‌های real_user_id و لاگ ممیزی ثبت می‌شود.
 */
final class BoardAiController extends Controller
{
    private function realUid(): ?int
    {
        $real = Auth::realUser();
        return $real !== null && (int) $real['id'] !== $this->uid() ? (int) $real['id'] : null;
    }

    /** گفتگوی کاربر در پروژه (از query یا بدنه) — پروژه باید از بوردهای خود کاربر باشد */
    private function ctx(bool $new = false): BoardContext
    {
        $r = $this->request;
        // «project» در Pid::INPUTS است: Request آن را پیش‌تر به شناسه‌ی عددی (یا ‎-1) ترجمه کرده
        $project = (int) ($r->query['project'] ?? $r->input('project', 0));
        $project = $project > 0 ? $project : null;
        $session = (string) ($r->query['session'] ?? $r->input('session', ''));
        $c = $project !== null ? BoardAgent::session($this->uid(), $this->realUid(), $project, $new ? null : $session, $new) : null;
        if ($c === null) {
            $this->fail('گفتگو یا پروژه پیدا نشد.', 404, ['code' => 'NOT_FOUND']);
        }
        return $c;
    }

    private function out(array $res): void
    {
        $res['ok'] ? $this->ok($res['message'], (array) $res['data']) : $this->fail($res['message'], $res['http'] ?: 422, ['code' => $res['code']]);
    }

    /** GET /board/ai/session?project=&session= — گفتگو + پیام‌ها */
    public function session()
    {
        $this->rateLimit('board_ai_read', 60);
        $c = $this->ctx();
        $this->ok('', $this->sessionData($c));
    }

    private function sessionData(BoardContext $c): array
    {
        $list = BoardAgent::sessions($c->uid, $c->projectId);
        $data = ['session' => $c->session['pid'], 'project' => ['id' => pid('projects', $c->projectId), 'title' => (string) $c->project['title']],
            'user' => \App\Services\Karlix\Common::who($c->uid), 'enabled' => Agnes::enabled(), 'reason' => Agnes::enabled() ? null : Agnes::ERRORS['not_configured'],
            'prompts' => BoardAgent::prompts($c), 'messages' => BoardAgent::transcript($c), 'sessions' => $list,
            'impersonating' => $c->realUid !== null];
        $c->saveRefs();
        return $data;
    }

    /** POST /board/ai/session/new */
    public function newSession()
    {
        $this->rateLimit('board_ai_new', 20);
        $c = $this->ctx(true);
        $this->ok('', $this->sessionData($c));
    }

    /** POST /board/ai/session/delete — {project, session (گفتگوی فعلی رابط), target: pid | "others"} ⇒ همان داده‌ی session (گفتگوی باقی‌مانده یا تازه) */
    public function deleteSession()
    {
        $this->rateLimit('board_ai_new', 20);
        $project = (int) $this->request->input('project', 0);
        if ($project <= 0 || BoardAgent::project($this->uid(), $project) === null) {
            $this->fail('گفتگو یا پروژه پیدا نشد.', 404, ['code' => 'NOT_FOUND']);
        }
        $current = (string) $this->request->input('session', '');
        $target = (string) $this->request->input('target', '');
        $others = $target === 'others';
        $n = BoardAgent::deleteSessions($this->uid(), $this->realUid(), $project, $others ? null : $target, $others, $current);
        if ($n === 0) {
            $this->fail($others ? 'گفتگوی دیگری برای حذف نیست.' : 'این گفتگو پیدا نشد یا هنوز در حال پردازش است.', 404, ['code' => 'NOT_FOUND']);
        }
        // اگر گفتگوی فعلی حذف شد، آخرین گفتگوی باقی‌مانده (یا یک گفتگوی تازه) باز می‌شود
        $keep = !$others && $target === $current ? null : $current;
        $c = BoardAgent::session($this->uid(), $this->realUid(), $project, $keep);
        if ($c === null) {
            $c = BoardAgent::session($this->uid(), $this->realUid(), $project);
        }
        $this->ok($others ? fa_num($n) . ' گفتگو حذف شد.' : 'گفتگو حذف شد.', $this->sessionData($c) + ['deleted' => $n]);
    }

    /** POST /board/ai/message — {project, session, text, filters (JSON), selection (JSON)} */
    public function message()
    {
        $this->rateLimit('board_ai_msg', 20);
        $c = $this->ctx();
        $f = json_decode((string) $this->request->input('filters', ''), true);
        $s = json_decode((string) $this->request->input('selection', ''), true);
        $this->out(BoardAgent::message($c, (string) $this->request->input('text', ''), is_array($f) ? $f : [], is_array($s) ? $s : []));
    }

    /** GET /board/ai/set?project=&session=&set=&page= — صفحه‌ی دیگر یک لیست */
    public function setPage()
    {
        $this->rateLimit('board_ai_read', 60);
        $c = $this->ctx();
        $pid = (string) ($this->request->query['set'] ?? '');
        $set = Pid::valid($pid) ? \App\Core\DB::one('SELECT * FROM ai_result_sets WHERE pid = ? AND session_id = ?', [$pid, (int) $c->session['id']]) : null;
        if ($set === null) {
            $this->fail('این لیست پیدا نشد.', 404);
        }
        $block = BoardTools::setBlock($c, $set, max(1, (int) ($this->request->query['page'] ?? 1)));
        if (!empty($this->request->query['all'])) {
            $block['all_ids'] = array_map(static fn($id) => pid('tasks', $id), BoardTools::setIds($c, $set));
        }
        $this->ok('', ['block' => $block]);
    }

    /** POST /board/ai/quick — {kind: user|label|column, key} */
    public function quick()
    {
        $this->rateLimit('board_ai_quick', 30);
        $c = $this->ctx();
        $this->out(BoardAgent::quick($c, (string) $this->request->input('kind', ''), mb_substr((string) $this->request->input('key', ''), 0, 40)));
    }

    /** POST /board/ai/apply — {action, token, edits (JSON، فقط ساخت تسک)} */
    public function apply()
    {
        $this->rateLimit('board_ai_apply', 20);
        $c = $this->ctx();
        $edits = json_decode((string) $this->request->input('edits', ''), true);
        $this->out(BoardOps::apply($c, (string) $this->request->input('action', ''), (string) $this->request->input('token', ''), is_array($edits) ? array_slice($edits, 0, BoardOps::CREATE_MAX) : []));
    }

    /** POST /board/ai/undo — {action} */
    public function undo()
    {
        $this->rateLimit('board_ai_apply', 20);
        $c = $this->ctx();
        $this->out(BoardOps::undo($c, (string) $this->request->input('action', '')));
    }

    /** POST /board/ai/cancel — {action} */
    public function cancel()
    {
        $c = $this->ctx();
        $this->out(BoardOps::cancel($c, (string) $this->request->input('action', '')));
    }

    /** POST /board/ai/export — {kind, format, set} (دکمه‌ی خروجی جدول/گفتگو، بدون مدل) */
    public function export()
    {
        $this->rateLimit('board_ai_export', 10);
        $c = $this->ctx();
        $r = Export::make($c, (string) $this->request->input('kind', 'board'), (string) $this->request->input('format', 'csv'), ['set' => (string) $this->request->input('set', 'last')]);
        $c->saveRefs();
        $r['ok'] ? $this->ok('', ['block' => $r['block']]) : $this->fail($r['message']);
    }

    /** GET /board/ai/download?f=&e=&s= — فایل خروجی (لینک امضاشده، فقط صاحب فایل) */
    public function download()
    {
        $q = $this->request->query;
        $hit = Export::resolve((string) ($q['f'] ?? ''), (int) ($q['e'] ?? 0), (string) ($q['s'] ?? ''), $this->uid());
        if ($hit === null) {
            $this->abort(404, 'لینک دانلود نامعتبر یا منقضی است؛ خروجی را دوباره بسازید.', 'EXPORT_EXPIRED');
        }
        [$path, $name, $format] = $hit;
        header('Content-Type: ' . ($format === 'pdf' ? 'application/pdf' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
