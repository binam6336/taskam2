<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\BlockService;

/**
 * «مسدود کردن» به‌جای حذف (تسک، پروژه، لیبل، درخواست تماس) و پنجره‌ی «مسدودشده‌ها» — منطق در BlockService.
 * آدرس‌ها: POST /block/{kind}/{id}، POST /unblock/{kind}/{id}، GET /blocked/{kind} (kind: tasks|projects|labels|calls)
 */
final class BlockController extends Controller
{
    /** سقف مسدود/رفع مسدودی برای هر کاربر در دقیقه (مستندات پنل) */
    public const PER_MIN = 60;
    private const URL = ['tasks' => 'task', 'projects' => 'project', 'labels' => 'label', 'calls' => 'call'];
    private const BACK = ['task' => '/tasks', 'project' => '/projects', 'label' => '/labels', 'call' => '/calls'];

    private function kind(string $k): string
    {
        $kind = self::URL[$k] ?? null;
        if ($kind === null) {
            $this->fail('نوع نامعتبر است.', 404);
        }
        return $kind;
    }

    public function block(string $k, string $id)
    {
        $kind = $this->kind($k);
        $this->rateLimit('block', self::PER_MIN);
        $res = BlockService::block($kind, $this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], self::BACK[$kind], $res['ok'] ? ['data' => $res['data']] : []);
    }

    public function unblock(string $k, string $id)
    {
        $kind = $this->kind($k);
        $this->rateLimit('block', self::PER_MIN);
        $res = BlockService::unblock($kind, $this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], self::BACK[$kind], $res['ok'] ? ['data' => $res['data']] : []);
    }

    public function index(string $k)
    {
        $kind = $this->kind($k);
        $this->ok('', BlockService::list($kind, $this->uid()));
    }
}
