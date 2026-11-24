<?php
/** Bootstrap مشترک وب و CLI؛ helperهای فعلی باید پیش از Composer بارگذاری شوند. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
return KarlixLaravel\Bridge::application();
