<?php
declare(strict_types=1);

namespace App\Core;

/** استثنای HTTP: توسط ErrorHandler به پاسخ مناسب (HTML یا JSON) تبدیل می‌شود. */
final class HttpException extends \RuntimeException
{
    public int $status;
    public string $errorCode;
    public array $extra;

    public function __construct(int $status, string $message = '', string $errorCode = 'ERROR', array $extra = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->extra = $extra;
    }
}
