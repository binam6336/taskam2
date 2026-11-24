<?php
namespace KarlixLaravel\Http\Controllers;
use KarlixLaravel\Http\Requests\PanelRequest;
final class EndpointController extends \Illuminate\Routing\Controller
{
    public function __invoke(PanelRequest $incoming)
    {
        [$class,$action]=$incoming->route()->defaults['_panel_handler'];
        if(!str_starts_with($class,'KarlixLaravel\\Http\\Controllers\\'))throw new \LogicException('Non-native controller refused');
        $request=$incoming->panel();
        $level=ob_get_level();ob_start();
        try {
            $controller=new $class($request);
            $result=$controller->$action(...array_values($request->params === [] ? [] : array_filter($request->params,static fn($key)=>!str_starts_with($key,'_'),ARRAY_FILTER_USE_KEY)));
            return $result instanceof \Symfony\Component\HttpFoundation\Response ? $result : response((string)ob_get_contents());
        } finally {while(ob_get_level()>$level)ob_end_clean();}
    }
}
