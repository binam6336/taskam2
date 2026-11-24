<?php
namespace KarlixLaravel\Http\Middleware;
final class ResolvePanelRoute
{
    public function handle(\Illuminate\Http\Request $request, \Closure $next)
    {
        $request->setLaravelSession(app('session.store'));
        $route=$request->route();$params=[];
        if ((\App\Core\Request::capture()->method() === 'HEAD' ? 'GET' : \App\Core\Request::capture()->method()) !== $route->defaults['_panel_method']) { throw new \App\Core\HttpException(405,'متد درخواست مجاز نیست.','METHOD_NOT_ALLOWED'); }
        preg_match_all('/\{(\w+)\}/',$route->defaults['_panel_path'],$names);
        foreach($names[1] as $name){
            $value=(string)$route->parameter($name);
            if(isset(\App\Core\Pid::ROUTE[$name])){
                $id=\App\Core\Pid::id(\App\Core\Pid::ROUTE[$name],$value);
                if($id===null)throw new \App\Core\HttpException(404,'صفحه‌ی مورد نظر یافت نشد.','NOT_FOUND');
                $value=(string)$id;
            }
            $params[$name]=$value;
        }
        \App\Core\Request::capture()->params=$params;
        return $next($request);
    }
}
