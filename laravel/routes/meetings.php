<?php
/** قراردادهای افزایشی جلسه؛ پنل ادمین و مسیرهای قبلی دست‌نخورده‌اند. */
$routes = [];
$add = static function (string $method, string $path, string $action, array $mw) use (&$routes): void {
    $regex = preg_replace('/\\\{(\w+)\\\}/', '(?P<$1>[^/]+)', preg_quote($path, '#'));
    $routes[] = ['method' => $method, 'path' => $path, 'regex' => '#^' . $regex . '$#u', 'handler' => [KarlixLaravel\Http\Controllers\MeetingController::class, $action], 'mw' => $mw];
};
$add('GET', '/meetings/{meeting}', 'page', ['panel']);
foreach (['' => false, '/api/v1' => true] as $prefix => $api) {
    foreach ([['GET','/projects/{project}/meetings','projectList'], ['POST','/projects/{project}/meetings','create'], ['GET','/meetings/invitations/list','invitations']] as [$method,$path,$action]) {
        $add($method, $prefix . $path, $action, $api ? ['api'] : ($method === 'POST' ? ['panel','csrf'] : ['panel']));
    }
    foreach (['state' => 'GET', 'join' => 'POST', 'heartbeat' => 'POST', 'invite' => 'POST', 'respond' => 'POST', 'members' => 'GET', 'moderate' => 'POST', 'consent' => 'POST', 'hand' => 'POST', 'leave' => 'POST', 'end' => 'POST', 'messages' => 'GET', 'send' => 'POST'] as $action => $method) {
        $add($method, $prefix . '/meetings/{meeting}/' . $action, $action === 'respond' ? 'respondInvite' : $action, $api ? ['api'] : ($method === 'POST' ? ['panel','csrf'] : ['panel']));
    }
}
$add('POST', '/integrations/livekit/webhook', 'webhook', []);
return $routes;
