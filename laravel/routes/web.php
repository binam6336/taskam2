<?php
// قرارداد مسیرهای کاربری؛ مسیرهای ادمین در این مجموعه نیستند.
return array (
  0 =>
  array (
    'path' => '/',
    'method' => 'GET',
    'regex' => '#^/$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'home',
    ),
    'mw' =>
    array (
    ),
  ),
  1 =>
  array (
    'path' => '/login',
    'method' => 'GET',
    'regex' => '#^/login$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'showLogin',
    ),
    'mw' =>
    array (
      0 => 'guest',
    ),
  ),
  2 =>
  array (
    'path' => '/login',
    'method' => 'POST',
    'regex' => '#^/login$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'login',
    ),
    'mw' =>
    array (
      0 => 'guest',
      1 => 'csrf',
    ),
  ),
  3 =>
  array (
    'path' => '/captcha',
    'method' => 'GET',
    'regex' => '#^/captcha$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'captcha',
    ),
    'mw' =>
    array (
      0 => 'guest',
    ),
  ),
  4 =>
  array (
    'path' => '/register',
    'method' => 'POST',
    'regex' => '#^/register$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'register',
    ),
    'mw' =>
    array (
      0 => 'guest',
      1 => 'csrf',
    ),
  ),
  5 =>
  array (
    'path' => '/logout',
    'method' => 'POST',
    'regex' => '#^/logout$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'logout',
    ),
    'mw' =>
    array (
      0 => 'authany',
      1 => 'csrf',
    ),
  ),
  6 =>
  array (
    'path' => '/reauth',
    'method' => 'GET',
    'regex' => '#^/reauth$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'showReauth',
    ),
    'mw' =>
    array (
    ),
  ),
  7 =>
  array (
    'path' => '/reauth',
    'method' => 'POST',
    'regex' => '#^/reauth$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'reauth',
    ),
    'mw' =>
    array (
      0 => 'csrf',
    ),
  ),
  8 =>
  array (
    'path' => '/reauth/cancel',
    'method' => 'POST',
    'regex' => '#^/reauth/cancel$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'reauthCancel',
    ),
    'mw' =>
    array (
      0 => 'csrf',
    ),
  ),
  9 =>
  array (
    'path' => '/pending',
    'method' => 'GET',
    'regex' => '#^/pending$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'pending',
    ),
    'mw' =>
    array (
      0 => 'authany',
    ),
  ),
  10 =>
  array (
    'path' => '/pending/status',
    'method' => 'GET',
    'regex' => '#^/pending/status$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\AuthController',
      1 => 'pendingStatus',
    ),
    'mw' =>
    array (
      0 => 'authany',
    ),
  ),
  11 =>
  array (
    'path' => '/manifest',
    'method' => 'GET',
    'regex' => '#^/manifest$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'manifest',
    ),
    'mw' =>
    array (
    ),
  ),
  12 =>
  array (
    'path' => '/service-worker',
    'method' => 'GET',
    'regex' => '#^/service-worker$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'serviceWorker',
    ),
    'mw' =>
    array (
    ),
  ),
  13 =>
  array (
    'path' => '/offline',
    'method' => 'GET',
    'regex' => '#^/offline$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'offline',
    ),
    'mw' =>
    array (
    ),
  ),
  14 =>
  array (
    'path' => '/docs',
    'method' => 'GET',
    'regex' => '#^/docs$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'index',
    ),
    'mw' =>
    array (
    ),
  ),
  15 =>
  array (
    'path' => '/docs/authentication',
    'method' => 'GET',
    'regex' => '#^/docs/authentication$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'auth',
    ),
    'mw' =>
    array (
    ),
  ),
  16 =>
  array (
    'path' => '/docs/errors',
    'method' => 'GET',
    'regex' => '#^/docs/errors$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'errors',
    ),
    'mw' =>
    array (
    ),
  ),
  17 =>
  array (
    'path' => '/docs/openapi.json',
    'method' => 'GET',
    'regex' => '#^/docs/openapi.json$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'openapi',
    ),
    'mw' =>
    array (
    ),
  ),
  18 =>
  array (
    'path' => '/docs/swagger',
    'method' => 'GET',
    'regex' => '#^/docs/swagger$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'swagger',
    ),
    'mw' =>
    array (
    ),
  ),
  19 =>
  array (
    'path' => '/docs/swagger/token',
    'method' => 'POST',
    'regex' => '#^/docs/swagger/token$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'swaggerToken',
    ),
    'mw' =>
    array (
      0 => 'csrf',
    ),
  ),
  20 =>
  array (
    'path' => '/docs/{group}',
    'method' => 'GET',
    'regex' => '#^/docs/(?P<group>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'group',
    ),
    'mw' =>
    array (
    ),
  ),
  21 =>
  array (
    'path' => '/docs/{group}/{slug}',
    'method' => 'GET',
    'regex' => '#^/docs/(?P<group>[^/]+)/(?P<slug>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DocsController',
      1 => 'endpoint',
    ),
    'mw' =>
    array (
    ),
  ),
  22 =>
  array (
    'path' => '/dashboard',
    'method' => 'GET',
    'regex' => '#^/dashboard$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\DashboardController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  23 =>
  array (
    'path' => '/search',
    'method' => 'GET',
    'regex' => '#^/search$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SearchController',
      1 => 'global',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  24 =>
  array (
    'path' => '/tasks',
    'method' => 'GET',
    'regex' => '#^/tasks$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  25 =>
  array (
    'path' => '/calendar',
    'method' => 'GET',
    'regex' => '#^/calendar$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\CalendarController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  26 =>
  array (
    'path' => '/calendar/data',
    'method' => 'GET',
    'regex' => '#^/calendar/data$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\CalendarController',
      1 => 'data',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  27 =>
  array (
    'path' => '/tasks',
    'method' => 'POST',
    'regex' => '#^/tasks$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  28 =>
  array (
    'path' => '/tasks/quick-options',
    'method' => 'GET',
    'regex' => '#^/tasks/quick-options$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'quickOptions',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  29 =>
  array (
    'path' => '/blocked/{kind}',
    'method' => 'GET',
    'regex' => '#^/blocked/(?P<kind>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BlockController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  30 =>
  array (
    'path' => '/block/{kind}/{id}',
    'method' => 'POST',
    'regex' => '#^/block/(?P<kind>[^/]+)/(?P<id>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BlockController',
      1 => 'block',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  31 =>
  array (
    'path' => '/unblock/{kind}/{id}',
    'method' => 'POST',
    'regex' => '#^/unblock/(?P<kind>[^/]+)/(?P<id>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BlockController',
      1 => 'unblock',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  32 =>
  array (
    'path' => '/tasks/review/pending',
    'method' => 'GET',
    'regex' => '#^/tasks/review/pending$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'reviewPending',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  33 =>
  array (
    'path' => '/tasks/review/bulk-approve',
    'method' => 'POST',
    'regex' => '#^/tasks/review/bulk-approve$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'reviewBulkApprove',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  34 =>
  array (
    'path' => '/tasks/bulk-options',
    'method' => 'GET',
    'regex' => '#^/tasks/bulk-options$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'bulkOptions',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  35 =>
  array (
    'path' => '/tasks/bulk',
    'method' => 'POST',
    'regex' => '#^/tasks/bulk$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'bulk',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  36 =>
  array (
    'path' => '/tasks/{task}',
    'method' => 'GET',
    'regex' => '#^/tasks/(?P<task>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  37 =>
  array (
    'path' => '/tasks/{task}/update',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  38 =>
  array (
    'path' => '/tasks/{task}/toggle',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/toggle$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'toggle',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  39 =>
  array (
    'path' => '/tasks/{task}/withdraw',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/withdraw$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'withdraw',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  40 =>
  array (
    'path' => '/tasks/{task}/undo-done',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/undo-done$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'undoDone',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  41 =>
  array (
    'path' => '/tasks/{task}/share',
    'method' => 'GET',
    'regex' => '#^/tasks/(?P<task>[^/]+)/share$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'share',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  42 =>
  array (
    'path' => '/tasks/{task}/history',
    'method' => 'GET',
    'regex' => '#^/tasks/(?P<task>[^/]+)/history$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'history',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  43 =>
  array (
    'path' => '/tasks/{task}/advance',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/advance$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'advance',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  44 =>
  array (
    'path' => '/tasks/{task}/status',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/status$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'status',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  45 =>
  array (
    'path' => '/tasks/{task}/notes',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/notes$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'addNote',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  46 =>
  array (
    'path' => '/tasks/{task}/notes/{note}/karlix',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/notes/(?P<note>[^/]+)/karlix$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'assistantRun',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  47 =>
  array (
    'path' => '/tasks/{task}/attachments',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/attachments$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'addFiles',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  48 =>
  array (
    'path' => '/tasks/{task}/attachments/{file}/delete',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/attachments/(?P<file>[^/]+)/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'removeFile',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  49 =>
  array (
    'path' => '/notes/{note}/update',
    'method' => 'POST',
    'regex' => '#^/notes/(?P<note>[^/]+)/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'updateNote',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  50 =>
  array (
    'path' => '/notifications/due',
    'method' => 'GET',
    'regex' => '#^/notifications/due$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'due',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  51 =>
  array (
    'path' => '/notifications/ack',
    'method' => 'POST',
    'regex' => '#^/notifications/ack$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'dueAck',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  52 =>
  array (
    'path' => '/notifications/feed',
    'method' => 'GET',
    'regex' => '#^/notifications/feed$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\NotificationController',
      1 => 'feed',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  53 =>
  array (
    'path' => '/notifications/read',
    'method' => 'POST',
    'regex' => '#^/notifications/read$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\NotificationController',
      1 => 'read',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  54 =>
  array (
    'path' => '/tasks/quick',
    'method' => 'POST',
    'regex' => '#^/tasks/quick$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'quick',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  55 =>
  array (
    'path' => '/board',
    'method' => 'GET',
    'regex' => '#^/board$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  56 =>
  array (
    'path' => '/board/tasks/{task}/move',
    'method' => 'POST',
    'regex' => '#^/board/tasks/(?P<task>[^/]+)/move$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'move',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  57 =>
  array (
    'path' => '/board/archive',
    'method' => 'GET',
    'regex' => '#^/board/archive$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'archive',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  58 =>
  array (
    'path' => '/board/archive-done',
    'method' => 'POST',
    'regex' => '#^/board/archive-done$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'archiveDone',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  59 =>
  array (
    'path' => '/board/projects',
    'method' => 'GET',
    'regex' => '#^/board/projects$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'projects',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  60 =>
  array (
    'path' => '/board/settings',
    'method' => 'GET',
    'regex' => '#^/board/settings$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'settings',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  61 =>
  array (
    'path' => '/board/settings',
    'method' => 'POST',
    'regex' => '#^/board/settings$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardController',
      1 => 'saveSettings',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  62 =>
  array (
    'path' => '/board/ai/session',
    'method' => 'GET',
    'regex' => '#^/board/ai/session$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'session',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  63 =>
  array (
    'path' => '/board/ai/session/new',
    'method' => 'POST',
    'regex' => '#^/board/ai/session/new$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'newSession',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  64 =>
  array (
    'path' => '/board/ai/session/delete',
    'method' => 'POST',
    'regex' => '#^/board/ai/session/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'deleteSession',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  65 =>
  array (
    'path' => '/board/ai/message',
    'method' => 'POST',
    'regex' => '#^/board/ai/message$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'message',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  66 =>
  array (
    'path' => '/board/ai/status',
    'method' => 'GET',
    'regex' => '#^/board/ai/status$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'status',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  67 =>
  array (
    'path' => '/board/ai/set',
    'method' => 'GET',
    'regex' => '#^/board/ai/set$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'setPage',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  68 =>
  array (
    'path' => '/board/ai/quick',
    'method' => 'POST',
    'regex' => '#^/board/ai/quick$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'quick',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  69 =>
  array (
    'path' => '/board/ai/apply',
    'method' => 'POST',
    'regex' => '#^/board/ai/apply$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'apply',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  70 =>
  array (
    'path' => '/board/ai/undo',
    'method' => 'POST',
    'regex' => '#^/board/ai/undo$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'undo',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  71 =>
  array (
    'path' => '/board/ai/cancel',
    'method' => 'POST',
    'regex' => '#^/board/ai/cancel$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'cancel',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  72 =>
  array (
    'path' => '/board/ai/export',
    'method' => 'POST',
    'regex' => '#^/board/ai/export$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'export',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  73 =>
  array (
    'path' => '/board/ai/download',
    'method' => 'GET',
    'regex' => '#^/board/ai/download$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\BoardAiController',
      1 => 'download',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  74 =>
  array (
    'path' => '/labels',
    'method' => 'GET',
    'regex' => '#^/labels$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SubjectController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  75 =>
  array (
    'path' => '/labels',
    'method' => 'POST',
    'regex' => '#^/labels$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SubjectController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  76 =>
  array (
    'path' => '/labels/{label}/update',
    'method' => 'POST',
    'regex' => '#^/labels/(?P<label>[^/]+)/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SubjectController',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  77 =>
  array (
    'path' => '/labels/{label}/delete',
    'method' => 'POST',
    'regex' => '#^/labels/(?P<label>[^/]+)/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SubjectController',
      1 => 'destroy',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  78 =>
  array (
    'path' => '/subjects',
    'method' => 'GET',
    'regex' => '#^/subjects$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SubjectController',
      1 => 'legacy',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  79 =>
  array (
    'path' => '/notes',
    'method' => 'GET',
    'regex' => '#^/notes$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  80 =>
  array (
    'path' => '/texts',
    'method' => 'GET',
    'regex' => '#^/texts$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'legacy',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  81 =>
  array (
    'path' => '/notes/data',
    'method' => 'GET',
    'regex' => '#^/notes/data$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'data',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  82 =>
  array (
    'path' => '/notes',
    'method' => 'POST',
    'regex' => '#^/notes$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  83 =>
  array (
    'path' => '/notes/reorder',
    'method' => 'POST',
    'regex' => '#^/notes/reorder$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'reorder',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  84 =>
  array (
    'path' => '/notes/item/{id}/update',
    'method' => 'POST',
    'regex' => '#^/notes/item/(?P<id>[^/]+)/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  85 =>
  array (
    'path' => '/notes/item/{id}/delete',
    'method' => 'POST',
    'regex' => '#^/notes/item/(?P<id>[^/]+)/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TextController',
      1 => 'destroy',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  86 =>
  array (
    'path' => '/colleagues',
    'method' => 'GET',
    'regex' => '#^/colleagues$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  87 =>
  array (
    'path' => '/colleagues/search',
    'method' => 'GET',
    'regex' => '#^/colleagues/search$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'search',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  88 =>
  array (
    'path' => '/colleagues',
    'method' => 'POST',
    'regex' => '#^/colleagues$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'add',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  89 =>
  array (
    'path' => '/colleagues/requests/{colreq}/accept',
    'method' => 'POST',
    'regex' => '#^/colleagues/requests/(?P<colreq>[^/]+)/accept$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'accept',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  90 =>
  array (
    'path' => '/colleagues/requests/{colreq}/reject',
    'method' => 'POST',
    'regex' => '#^/colleagues/requests/(?P<colreq>[^/]+)/reject$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'reject',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  91 =>
  array (
    'path' => '/colleagues/{user}/remove',
    'method' => 'POST',
    'regex' => '#^/colleagues/(?P<user>[^/]+)/remove$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'remove',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  92 =>
  array (
    'path' => '/colleagues/followers/{user}/remove',
    'method' => 'POST',
    'regex' => '#^/colleagues/followers/(?P<user>[^/]+)/remove$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'removeFollower',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  93 =>
  array (
    'path' => '/colleagues/{user}/block',
    'method' => 'POST',
    'regex' => '#^/colleagues/(?P<user>[^/]+)/block$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'block',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  94 =>
  array (
    'path' => '/colleagues/unblock-key',
    'method' => 'POST',
    'regex' => '#^/colleagues/unblock-key$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'unblockKey',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  95 =>
  array (
    'path' => '/colleagues/{user}/unblock',
    'method' => 'POST',
    'regex' => '#^/colleagues/(?P<user>[^/]+)/unblock$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ColleagueController',
      1 => 'unblock',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  96 =>
  array (
    'path' => '/tasks/{task}/confidential',
    'method' => 'POST',
    'regex' => '#^/tasks/(?P<task>[^/]+)/confidential$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TaskController',
      1 => 'confidential',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  97 =>
  array (
    'path' => '/projects',
    'method' => 'GET',
    'regex' => '#^/projects$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  98 =>
  array (
    'path' => '/projects',
    'method' => 'POST',
    'regex' => '#^/projects$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  99 =>
  array (
    'path' => '/projects/history/clear',
    'method' => 'POST',
    'regex' => '#^/projects/history/clear$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'clearHistory',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  100 =>
  array (
    'path' => '/projects/requests/{projreq}/respond',
    'method' => 'POST',
    'regex' => '#^/projects/requests/(?P<projreq>[^/]+)/respond$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'respondRequest',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  101 =>
  array (
    'path' => '/projects/{project}/analytics',
    'method' => 'GET',
    'regex' => '#^/projects/(?P<project>[^/]+)/analytics$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'analytics',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  102 =>
  array (
    'path' => '/projects/{project}/analytics/speed',
    'method' => 'GET',
    'regex' => '#^/projects/(?P<project>[^/]+)/analytics/speed$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'speed',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  103 =>
  array (
    'path' => '/projects/{project}/analytics/export',
    'method' => 'GET',
    'regex' => '#^/projects/(?P<project>[^/]+)/analytics/export$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'analyticsExport',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  104 =>
  array (
    'path' => '/projects/{project}/workload',
    'method' => 'GET',
    'regex' => '#^/projects/(?P<project>[^/]+)/workload$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'workload',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  105 =>
  array (
    'path' => '/projects/{project}/workload/reassign',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/workload/reassign$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'workloadReassign',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  106 =>
  array (
    'path' => '/projects/{project}/workload/capacity',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/workload/capacity$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'workloadCapacity',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  107 =>
  array (
    'path' => '/projects/{project}/update',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  108 =>
  array (
    'path' => '/projects/{project}/leave',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/leave$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'leave',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  109 =>
  array (
    'path' => '/projects/{project}/block',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/block$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'block',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  110 =>
  array (
    'path' => '/projects/{project}/unblock',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/unblock$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'unblock',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  111 =>
  array (
    'path' => '/projects/{project}/members/{user}/permissions',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/members/(?P<user>[^/]+)/permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'memberPermissions',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  112 =>
  array (
    'path' => '/projects/{project}/members/{user}/task-permissions',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/members/(?P<user>[^/]+)/task-permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'memberTaskPermissions',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  113 =>
  array (
    'path' => '/projects/{project}/members/{user}/board-permissions',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/members/(?P<user>[^/]+)/board-permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'memberBoardPermissions',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  114 =>
  array (
    'path' => '/projects/{project}/members/add',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/members/add$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'addMember',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  115 =>
  array (
    'path' => '/projects/{project}/members/{user}/remove',
    'method' => 'POST',
    'regex' => '#^/projects/(?P<project>[^/]+)/members/(?P<user>[^/]+)/remove$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProjectController',
      1 => 'removeMember',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  116 =>
  array (
    'path' => '/chat',
    'method' => 'GET',
    'regex' => '#^/chat$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  117 =>
  array (
    'path' => '/chat/api/time',
    'method' => 'GET',
    'regex' => '#^/chat/api/time$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'time',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  118 =>
  array (
    'path' => '/chat/api/list',
    'method' => 'GET',
    'regex' => '#^/chat/api/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'contacts',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  119 =>
  array (
    'path' => '/chat/api/fetch',
    'method' => 'GET',
    'regex' => '#^/chat/api/fetch$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'fetch',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  120 =>
  array (
    'path' => '/chat/api/search',
    'method' => 'GET',
    'regex' => '#^/chat/api/search$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'search',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  121 =>
  array (
    'path' => '/chat/api/unread_counts',
    'method' => 'GET',
    'regex' => '#^/chat/api/unread_counts$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'unreadCounts',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  122 =>
  array (
    'path' => '/chat/api/total_unread',
    'method' => 'GET',
    'regex' => '#^/chat/api/total_unread$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'totalUnread',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  123 =>
  array (
    'path' => '/chat/api/latest_unread',
    'method' => 'GET',
    'regex' => '#^/chat/api/latest_unread$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'latestUnread',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  124 =>
  array (
    'path' => '/chat/api/heartbeat',
    'method' => 'GET',
    'regex' => '#^/chat/api/heartbeat$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'heartbeat',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  125 =>
  array (
    'path' => '/chat/api/user_status',
    'method' => 'GET',
    'regex' => '#^/chat/api/user_status$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'userStatus',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  126 =>
  array (
    'path' => '/chat/api/users_status',
    'method' => 'GET',
    'regex' => '#^/chat/api/users_status$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'usersStatus',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  127 =>
  array (
    'path' => '/chat/api/saved_info',
    'method' => 'GET',
    'regex' => '#^/chat/api/saved_info$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'savedInfo',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  128 =>
  array (
    'path' => '/chat/api/send',
    'method' => 'POST',
    'regex' => '#^/chat/api/send$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'send',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  129 =>
  array (
    'path' => '/chat/api/start',
    'method' => 'POST',
    'regex' => '#^/chat/api/start$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'start',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  130 =>
  array (
    'path' => '/chat/api/accept',
    'method' => 'POST',
    'regex' => '#^/chat/api/accept$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'accept',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  131 =>
  array (
    'path' => '/chat/api/send_file',
    'method' => 'POST',
    'regex' => '#^/chat/api/send_file$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'sendFile',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  132 =>
  array (
    'path' => '/chat/api/mark_read',
    'method' => 'POST',
    'regex' => '#^/chat/api/mark_read$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'markRead',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  133 =>
  array (
    'path' => '/chat/api/edit',
    'method' => 'POST',
    'regex' => '#^/chat/api/edit$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'edit',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  134 =>
  array (
    'path' => '/chat/api/forward',
    'method' => 'POST',
    'regex' => '#^/chat/api/forward$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'forward',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  135 =>
  array (
    'path' => '/chat/api/clear_history',
    'method' => 'POST',
    'regex' => '#^/chat/api/clear_history$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ChatController',
      1 => 'clearHistory',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  136 =>
  array (
    'path' => '/tokens',
    'method' => 'GET',
    'regex' => '#^/tokens$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TokenController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  137 =>
  array (
    'path' => '/tokens',
    'method' => 'POST',
    'regex' => '#^/tokens$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TokenController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  138 =>
  array (
    'path' => '/tokens/{token}/toggle',
    'method' => 'POST',
    'regex' => '#^/tokens/(?P<token>[^/]+)/toggle$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TokenController',
      1 => 'toggle',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  139 =>
  array (
    'path' => '/tokens/{token}/delete',
    'method' => 'POST',
    'regex' => '#^/tokens/(?P<token>[^/]+)/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TokenController',
      1 => 'destroy',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  140 =>
  array (
    'path' => '/support',
    'method' => 'GET',
    'regex' => '#^/support$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  141 =>
  array (
    'path' => '/support',
    'method' => 'POST',
    'regex' => '#^/support$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'store',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  142 =>
  array (
    'path' => '/support/{ticket}',
    'method' => 'GET',
    'regex' => '#^/support/(?P<ticket>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'panel',
    ),
  ),
  143 =>
  array (
    'path' => '/support/{ticket}/reply',
    'method' => 'POST',
    'regex' => '#^/support/(?P<ticket>[^/]+)/reply$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'reply',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  144 =>
  array (
    'path' => '/support/{ticket}/close',
    'method' => 'POST',
    'regex' => '#^/support/(?P<ticket>[^/]+)/close$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'close',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  145 =>
  array (
    'path' => '/support/{ticket}/reopen',
    'method' => 'POST',
    'regex' => '#^/support/(?P<ticket>[^/]+)/reopen$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\SupportController',
      1 => 'reopen',
    ),
    'mw' =>
    array (
      0 => 'panel',
      1 => 'csrf',
    ),
  ),
  146 =>
  array (
    'path' => '/profile',
    'method' => 'GET',
    'regex' => '#^/profile$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  147 =>
  array (
    'path' => '/profile/info',
    'method' => 'POST',
    'regex' => '#^/profile/info$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'updateInfo',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  148 =>
  array (
    'path' => '/profile/password',
    'method' => 'POST',
    'regex' => '#^/profile/password$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'updatePassword',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  149 =>
  array (
    'path' => '/profile/avatar',
    'method' => 'POST',
    'regex' => '#^/profile/avatar$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'avatar',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  150 =>
  array (
    'path' => '/profile/avatar/delete',
    'method' => 'POST',
    'regex' => '#^/profile/avatar/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'removeAvatar',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  151 =>
  array (
    'path' => '/profile/privacy',
    'method' => 'POST',
    'regex' => '#^/profile/privacy$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'privacy',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  152 =>
  array (
    'path' => '/profile/task-security',
    'method' => 'POST',
    'regex' => '#^/profile/task-security$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'taskSecurity',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  153 =>
  array (
    'path' => '/profile/appearance',
    'method' => 'POST',
    'regex' => '#^/profile/appearance$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'appearance',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  154 =>
  array (
    'path' => '/profile/storage/{project}',
    'method' => 'GET',
    'regex' => '#^/profile/storage/(?P<project>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'storageFiles',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  155 =>
  array (
    'path' => '/profile/ai-usage',
    'method' => 'GET',
    'regex' => '#^/profile/ai-usage$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'aiUsage',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  156 =>
  array (
    'path' => '/profile/sessions/revoke',
    'method' => 'POST',
    'regex' => '#^/profile/sessions/revoke$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'revokeSession',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  157 =>
  array (
    'path' => '/profile/sessions/revoke-others',
    'method' => 'POST',
    'regex' => '#^/profile/sessions/revoke-others$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\ProfileController',
      1 => 'revokeOtherSessions',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  158 =>
  array (
    'path' => '/tour',
    'method' => 'POST',
    'regex' => '#^/tour$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TourController',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  159 =>
  array (
    'path' => '/tour/start',
    'method' => 'GET',
    'regex' => '#^/tour/start$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\TourController',
      1 => 'start',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  160 =>
  array (
    'path' => '/files/{type}/{name}',
    'method' => 'GET',
    'regex' => '#^/files/(?P<type>[^/]+)/(?P<name>[^/]+)$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\FileController',
      1 => 'serve',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  161 =>
  array (
    'path' => '/live/poll',
    'method' => 'GET',
    'regex' => '#^/live/poll$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\LiveController',
      1 => 'poll',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  162 =>
  array (
    'path' => '/install',
    'method' => 'GET',
    'regex' => '#^/install$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'install',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  163 =>
  array (
    'path' => '/install/android/check',
    'method' => 'GET',
    'regex' => '#^/install/android/check$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'apkCheck',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  164 =>
  array (
    'path' => '/install/android',
    'method' => 'GET',
    'regex' => '#^/install/android$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'apk',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  165 =>
  array (
    'path' => '/push/key',
    'method' => 'GET',
    'regex' => '#^/push/key$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'pushKey',
    ),
    'mw' =>
    array (
      0 => 'auth',
    ),
  ),
  166 =>
  array (
    'path' => '/push/subscribe',
    'method' => 'POST',
    'regex' => '#^/push/subscribe$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'subscribe',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  167 =>
  array (
    'path' => '/push/unsubscribe',
    'method' => 'POST',
    'regex' => '#^/push/unsubscribe$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'unsubscribe',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
  168 =>
  array (
    'path' => '/push/test',
    'method' => 'POST',
    'regex' => '#^/push/test$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\PwaController',
      1 => 'test',
    ),
    'mw' =>
    array (
      0 => 'auth',
      1 => 'csrf',
    ),
  ),
);
