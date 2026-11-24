<?php
declare(strict_types=1);

namespace KarlixLaravel\Http\Controllers;

use KarlixLaravel\Http\Controllers\Controller;
use KarlixLaravel\Http\Response;
use KarlixLaravel\Services\GlobalSearch;

/** جستجوی سراسری نوار بالا (v7.13): GET /search?q=… → گروه‌های نتیجه (KarlixLaravel\Services\GlobalSearch) */
final class SearchController extends Controller
{
    public const RATE = 90;     // درخواست در دقیقه برای هر کاربر (پالت با تأخیر تایپ درخواست می‌دهد)

    public function global()
    {
        $this->rateLimit('global_search', self::RATE, 60);
        Response::json(['success' => true, 'data' => GlobalSearch::run($this->uid(), $this->request->str('q', GlobalSearch::MAX_LEN))]);
    }
}
