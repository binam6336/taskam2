<?php
namespace KarlixLaravel\Http\Middleware;
final class PanelAccess
{
    private const CHECKS=['auth'=>'Authenticate','authany'=>'AuthAny','panel'=>'UserPanel','guest'=>'Guest','csrf'=>'VerifyCsrf','api'=>'ApiAuth'];
    public function handle(\Illuminate\Http\Request $request, \Closure $next, string $kind)
    {
        if(!isset(self::CHECKS[$kind]))throw new \LogicException('Unknown panel middleware');
        $class='KarlixLaravel\\Http\\Middleware\\Checks\\'.self::CHECKS[$kind];
        $class::handle(\App\Core\Request::capture());
        $request->setUserResolver(static fn() => \Illuminate\Support\Facades\Auth::guard('karlix')->user());
        return $next($request);
    }
}
