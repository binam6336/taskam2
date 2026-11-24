<?php
namespace KarlixLaravel\Http;
final class ExceptionRenderer
{
    public static function render(\Throwable $error): \Symfony\Component\HttpFoundation\Response
    {
        if($error instanceof CompletedResponse)return $error->response;
        if($error instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException)$error=new \App\Core\HttpException(404,'صفحه‌ی مورد نظر یافت نشد.','NOT_FOUND');
        if($error instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException){
            $found=false;foreach(app('panel.routes') as $route){if(preg_match($route['regex'],\App\Core\Request::capture()->path())){$found=true;break;}}
            $error=$found?new \App\Core\HttpException(405,'متد درخواست مجاز نیست.','METHOD_NOT_ALLOWED'):new \App\Core\HttpException(404,'صفحه‌ی مورد نظر یافت نشد.','NOT_FOUND');
        }
        if ($error instanceof \Illuminate\Auth\Access\AuthorizationException || $error instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) { $error = new \App\Core\HttpException(403,'دسترسی به این بخش مجاز نیست.','FORBIDDEN'); }
        $status=$error instanceof \App\Core\HttpException?$error->status:500;
        $message=$status<500?$error->getMessage():'خطای داخلی سرور. لطفاً دوباره تلاش کنید.';
        $code=$error instanceof \App\Core\HttpException?$error->errorCode:'SERVER_ERROR';
        if($status>=500)\App\Core\Log::error($error);
        $message=\App\Core\ErrorText::apply($message);
        $headers=Response::headers();
        if(\App\Core\Request::capture()->wantsJson()){
            $data=['success'=>false,'status'=>'error','message'=>$message,'error_code'=>$code];
            if($status>=500)$data['ref']=\App\Core\Log::requestId();
            $headers['Content-Type']='application/json; charset=utf-8';
            $response=new \Illuminate\Http\JsonResponse($data,$status,$headers,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }else{
            $body=\KarlixLaravel\View\PhpView::render('errors/error',['status'=>$status,'message'=>$message,'detail'=>null,'ref'=>$status>=500?\App\Core\Log::requestId():null],null);
            $headers['Content-Type']='text/html; charset=utf-8';$response=new \Illuminate\Http\Response($body,$status,$headers);
        }
        if(!isset($headers['Cache-Control']))$response->headers->remove('Cache-Control');
        return $response;
    }
}
