<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Services\GlobalSearch;

/** جستجوی سراسری (v7.13) — همان نتایج نوار جستجوی بالای پنل */
final class SearchApi extends ApiController
{
    public function search()
    {
        $this->ok(GlobalSearch::run($this->uid, $this->request->str('q', GlobalSearch::MAX_LEN)));
    }
}
