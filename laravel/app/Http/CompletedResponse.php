<?php
declare(strict_types=1);
namespace KarlixLaravel\Http;
final class CompletedResponse extends \RuntimeException
{
    public function __construct(public \Symfony\Component\HttpFoundation\Response $response) { parent::__construct('HTTP response completed'); }
}
