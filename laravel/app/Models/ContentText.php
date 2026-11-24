<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class ContentText extends Record
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'content_texts';
}
