<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class Text extends Record
{
    protected $table = 'texts';
    public $incrementing = false;
    protected $keyType = 'string';
}
