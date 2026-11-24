<?php
// قرارداد مسیرهای کاربری؛ مسیرهای ادمین در این مجموعه نیستند.
return array (
  0 =>
  array (
    'path' => '/api/v1',
    'method' => 'GET',
    'regex' => '#^/api/v1$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\MetaApi',
      1 => 'index',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  1 =>
  array (
    'path' => '/api/v1/me',
    'method' => 'GET',
    'regex' => '#^/api/v1/me$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\MetaApi',
      1 => 'me',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  2 =>
  array (
    'path' => '/api/v1/time',
    'method' => 'GET',
    'regex' => '#^/api/v1/time$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\MetaApi',
      1 => 'time',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  3 =>
  array (
    'path' => '/api/v1/tasks/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/tasks/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  4 =>
  array (
    'path' => '/api/v1/tasks/show',
    'method' => 'GET',
    'regex' => '#^/api/v1/tasks/show$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  5 =>
  array (
    'path' => '/api/v1/tasks/stats',
    'method' => 'GET',
    'regex' => '#^/api/v1/tasks/stats$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'stats',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  6 =>
  array (
    'path' => '/api/v1/tasks/create',
    'method' => 'POST',
    'regex' => '#^/api/v1/tasks/create$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'create',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  7 =>
  array (
    'path' => '/api/v1/tasks/update',
    'method' => 'POST',
    'regex' => '#^/api/v1/tasks/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  8 =>
  array (
    'path' => '/api/v1/tasks/toggle',
    'method' => 'POST',
    'regex' => '#^/api/v1/tasks/toggle$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'toggle',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  9 =>
  array (
    'path' => '/api/v1/tasks/blocked',
    'method' => 'GET',
    'regex' => '#^/api/v1/tasks/blocked$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'taskList',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  10 =>
  array (
    'path' => '/api/v1/tasks/block',
    'method' => 'POST',
    'regex' => '#^/api/v1/tasks/block$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'taskBlock',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  11 =>
  array (
    'path' => '/api/v1/tasks/unblock',
    'method' => 'POST',
    'regex' => '#^/api/v1/tasks/unblock$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'taskUnblock',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  12 =>
  array (
    'path' => '/api/v1/reviews/pending',
    'method' => 'GET',
    'regex' => '#^/api/v1/reviews/pending$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'reviewsPending',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  13 =>
  array (
    'path' => '/api/v1/reviews/bulk-approve',
    'method' => 'POST',
    'regex' => '#^/api/v1/reviews/bulk-approve$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TaskApi',
      1 => 'reviewsBulkApprove',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  14 =>
  array (
    'path' => '/api/v1/search',
    'method' => 'GET',
    'regex' => '#^/api/v1/search$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\SearchApi',
      1 => 'search',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  15 =>
  array (
    'path' => '/api/v1/texts/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/texts/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  16 =>
  array (
    'path' => '/api/v1/texts/show',
    'method' => 'GET',
    'regex' => '#^/api/v1/texts/show$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  17 =>
  array (
    'path' => '/api/v1/texts/stats',
    'method' => 'GET',
    'regex' => '#^/api/v1/texts/stats$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'stats',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  18 =>
  array (
    'path' => '/api/v1/texts/create',
    'method' => 'POST',
    'regex' => '#^/api/v1/texts/create$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'create',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  19 =>
  array (
    'path' => '/api/v1/texts/update',
    'method' => 'POST',
    'regex' => '#^/api/v1/texts/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  20 =>
  array (
    'path' => '/api/v1/texts/delete',
    'method' => 'POST',
    'regex' => '#^/api/v1/texts/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\TextApi',
      1 => 'delete',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  21 =>
  array (
    'path' => '/api/v1/colleagues/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  22 =>
  array (
    'path' => '/api/v1/colleagues/followers',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/followers$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'followers',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  23 =>
  array (
    'path' => '/api/v1/colleagues/followers/remove',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/followers/remove$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'followerRemove',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  24 =>
  array (
    'path' => '/api/v1/colleagues/blocked',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/blocked$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'blocked',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  25 =>
  array (
    'path' => '/api/v1/colleagues/show',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/show$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  26 =>
  array (
    'path' => '/api/v1/colleagues/stats',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/stats$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'stats',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  27 =>
  array (
    'path' => '/api/v1/colleagues/search',
    'method' => 'GET',
    'regex' => '#^/api/v1/colleagues/search$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'search',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  28 =>
  array (
    'path' => '/api/v1/colleagues/create',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/create$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'create',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  29 =>
  array (
    'path' => '/api/v1/colleagues/delete',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'delete',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  30 =>
  array (
    'path' => '/api/v1/colleagues/permissions',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'permissions',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  31 =>
  array (
    'path' => '/api/v1/colleagues/block',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/block$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'block',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  32 =>
  array (
    'path' => '/api/v1/colleagues/unblock',
    'method' => 'POST',
    'regex' => '#^/api/v1/colleagues/unblock$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ColleagueApi',
      1 => 'unblock',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  33 =>
  array (
    'path' => '/api/v1/labels/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/labels/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\LabelApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  34 =>
  array (
    'path' => '/api/v1/labels/create',
    'method' => 'POST',
    'regex' => '#^/api/v1/labels/create$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\LabelApi',
      1 => 'create',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  35 =>
  array (
    'path' => '/api/v1/labels/update',
    'method' => 'POST',
    'regex' => '#^/api/v1/labels/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\LabelApi',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  36 =>
  array (
    'path' => '/api/v1/labels/delete',
    'method' => 'POST',
    'regex' => '#^/api/v1/labels/delete$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\LabelApi',
      1 => 'delete',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  37 =>
  array (
    'path' => '/api/v1/board/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/board/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BoardApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  38 =>
  array (
    'path' => '/api/v1/board/archive',
    'method' => 'GET',
    'regex' => '#^/api/v1/board/archive$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BoardApi',
      1 => 'archive',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  39 =>
  array (
    'path' => '/api/v1/board/move',
    'method' => 'POST',
    'regex' => '#^/api/v1/board/move$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BoardApi',
      1 => 'move',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  40 =>
  array (
    'path' => '/api/v1/board/archive-done',
    'method' => 'POST',
    'regex' => '#^/api/v1/board/archive-done$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BoardApi',
      1 => 'archiveDone',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  41 =>
  array (
    'path' => '/api/v1/projects/list',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/list$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'list',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  42 =>
  array (
    'path' => '/api/v1/projects/show',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/show$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'show',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  43 =>
  array (
    'path' => '/api/v1/projects/members',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/members$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'membersList',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  44 =>
  array (
    'path' => '/api/v1/projects/stats',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/stats$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'stats',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  45 =>
  array (
    'path' => '/api/v1/projects/workload',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/workload$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'workload',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  46 =>
  array (
    'path' => '/api/v1/projects/workload/reassign',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/workload/reassign$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'workloadReassign',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  47 =>
  array (
    'path' => '/api/v1/projects/workload/capacity',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/workload/capacity$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'workloadCapacity',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  48 =>
  array (
    'path' => '/api/v1/projects/create',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/create$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'create',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  49 =>
  array (
    'path' => '/api/v1/projects/update',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/update$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'update',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  50 =>
  array (
    'path' => '/api/v1/projects/leave',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/leave$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'leave',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  51 =>
  array (
    'path' => '/api/v1/projects/block',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/block$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'block',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  52 =>
  array (
    'path' => '/api/v1/projects/blocked-projects',
    'method' => 'GET',
    'regex' => '#^/api/v1/projects/blocked-projects$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'projectList',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  53 =>
  array (
    'path' => '/api/v1/projects/block-project',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/block-project$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'projectBlock',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  54 =>
  array (
    'path' => '/api/v1/projects/unblock-project',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/unblock-project$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\BlockApi',
      1 => 'projectUnblock',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  55 =>
  array (
    'path' => '/api/v1/projects/member-add',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/member-add$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberAdd',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  56 =>
  array (
    'path' => '/api/v1/projects/member-remove',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/member-remove$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberRemove',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  57 =>
  array (
    'path' => '/api/v1/projects/member-permissions',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/member-permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberPermissions',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  58 =>
  array (
    'path' => '/api/v1/projects/task-permissions',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/task-permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberTaskPermissions',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  59 =>
  array (
    'path' => '/api/v1/projects/board-permissions',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/board-permissions$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberBoardPermissions',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
  60 =>
  array (
    'path' => '/api/v1/projects/member-role',
    'method' => 'POST',
    'regex' => '#^/api/v1/projects/member-role$#u',
    'handler' =>
    array (
      0 => 'KarlixLaravel\\Http\\Controllers\\Api\\V1\\ProjectApi',
      1 => 'memberRole',
    ),
    'mw' =>
    array (
      0 => 'api',
    ),
  ),
);
