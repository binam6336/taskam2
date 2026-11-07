<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AdminController;
use App\Controllers\BlockController;
use App\Controllers\BoardController;
use App\Controllers\Api\V1\BlockApi;
use App\Controllers\Api\V1\BoardApi;
use App\Controllers\Api\V1\CallApi;
use App\Controllers\Api\V1\ColleagueApi;
use App\Controllers\Api\V1\MetaApi;
use App\Controllers\Api\V1\LabelApi;
use App\Controllers\Api\V1\ProjectApi;
use App\Controllers\Api\V1\TaskApi;
use App\Controllers\Api\V1\TextApi;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CallController;
use App\Controllers\GroupController;
use App\Controllers\ChatController;
use App\Controllers\ColleagueController;
use App\Controllers\DashboardController;
use App\Controllers\DocsController;
use App\Controllers\FileController;
use App\Controllers\LiveController;
use App\Controllers\ProfileController;
use App\Controllers\ProjectController;
use App\Controllers\PwaController;
use App\Controllers\SubjectController;
use App\Controllers\SupportController;
use App\Controllers\TaskController;
use App\Controllers\TextController;
use App\Controllers\TokenController;
use App\Controllers\TourController;

// ───────────────────────── عمومی / احراز هویت ─────────────────────────
$router->get('/', [AuthController::class, 'home']);
$router->group(['guest'], function ($r) {
    $r->get('/login', [AuthController::class, 'showLogin']);
    $r->post('/login', [AuthController::class, 'login']);
    $r->get('/captcha', [AuthController::class, 'captcha']);
    $r->post('/register', [AuthController::class, 'register']);
});
$router->post('/logout', [AuthController::class, 'logout'], ['authany']);
$router->get('/reauth', [AuthController::class, 'showReauth']);
$router->post('/reauth', [AuthController::class, 'reauth']);
$router->post('/reauth/cancel', [AuthController::class, 'reauthCancel']);
$router->get('/pending', [AuthController::class, 'pending'], ['authany']);
$router->get('/pending/status', [AuthController::class, 'pendingStatus'], ['authany']);
// نسخه‌ی PWA (مسیرها عمداً بدون پسوند فایل‌اند تا وب‌سرور آن‌ها را فایل ایستا فرض نکند)
$router->get('/manifest', [PwaController::class, 'manifest']);
$router->get('/service-worker', [PwaController::class, 'serviceWorker']);
$router->get('/offline', [PwaController::class, 'offline']);
$router->get('/docs', [DocsController::class, 'index']);
$router->get('/docs/authentication', [DocsController::class, 'auth']);
$router->get('/docs/errors', [DocsController::class, 'errors']);
$router->get('/docs/{group}', [DocsController::class, 'group']);
$router->get('/docs/{group}/{slug}', [DocsController::class, 'endpoint']);

// ───────────────────────── پنل کاربری ─────────────────────────
$router->group(['panel'], function ($r) {
    $r->get('/dashboard', [DashboardController::class, 'index']);

    // تسک‌ها
    $r->get('/tasks', [TaskController::class, 'index']);
    $r->get('/calendar', [CalendarController::class, 'index']);
    $r->get('/calendar/data', [CalendarController::class, 'data']);
    $r->post('/tasks', [TaskController::class, 'store']);
    $r->get('/tasks/quick-options', [TaskController::class, 'quickOptions']);
    // مسدود کردن به‌جای حذف (تسک، پروژه، لیبل، درخواست تماس) + پنجره‌ی «مسدودشده‌ها»
    $r->get('/blocked/{kind}', [BlockController::class, 'index']);
    $r->post('/block/{kind}/{id}', [BlockController::class, 'block']);
    $r->post('/unblock/{kind}/{id}', [BlockController::class, 'unblock']);
    $r->get('/tasks/{task}', [TaskController::class, 'show']);
    $r->post('/tasks/{task}/update', [TaskController::class, 'update']);
    $r->post('/tasks/{task}/toggle', [TaskController::class, 'toggle']);
    $r->post('/tasks/{task}/withdraw', [TaskController::class, 'withdraw']);
    $r->post('/tasks/{task}/undo-done', [TaskController::class, 'undoDone']);
    $r->get('/tasks/{task}/share', [TaskController::class, 'share']);
    $r->get('/tasks/{task}/history', [TaskController::class, 'history']);
    $r->post('/tasks/{task}/advance', [TaskController::class, 'advance']);
    $r->post('/tasks/{task}/status', [TaskController::class, 'status']);
    $r->post('/tasks/{task}/notes', [TaskController::class, 'addNote']);
    $r->post('/tasks/{task}/attachments', [TaskController::class, 'addFiles']);
    $r->post('/notes/{note}/update', [TaskController::class, 'updateNote']);
    $r->get('/notifications/due', [TaskController::class, 'due']);
    $r->post('/notifications/ack', [TaskController::class, 'dueAck']);
    $r->post('/tasks/quick', [TaskController::class, 'quick']);

    // بورد پروژه (کانبان، دمو)
    $r->get('/board', [BoardController::class, 'index']);
    $r->post('/board/tasks/{task}/move', [BoardController::class, 'move']);
    $r->get('/board/archive', [BoardController::class, 'archive']);
    $r->post('/board/archive-done', [BoardController::class, 'archiveDone']);
    $r->post('/board/order', [BoardController::class, 'order']);

    // موضوعات
    $r->get('/labels', [SubjectController::class, 'index']);
    $r->post('/labels', [SubjectController::class, 'store']);
    $r->post('/labels/{label}/update', [SubjectController::class, 'update']);
    $r->get('/subjects', [SubjectController::class, 'legacy']);   // آدرس قدیمی → /labels


    // متن‌های آماده
    $r->get('/texts', [TextController::class, 'index']);
    $r->get('/texts/data', [TextController::class, 'data']);
    $r->post('/texts', [TextController::class, 'store']);
    $r->post('/texts/reorder', [TextController::class, 'reorder']);
    $r->post('/texts/{id}/update', [TextController::class, 'update']);
    $r->post('/texts/{id}/delete', [TextController::class, 'destroy']);

    // درخواست‌های تماس
    $r->get('/calls', [CallController::class, 'index']);
    $r->post('/calls', [CallController::class, 'store']);
    $r->post('/calls/{call}/update', [CallController::class, 'update']);
    $r->post('/calls/{call}/complete', [CallController::class, 'complete']);
    $r->get('/calls/{call}/share', [CallController::class, 'share']);

    // همکاران
    $r->get('/colleagues', [ColleagueController::class, 'index']);
    $r->get('/colleagues/search', [ColleagueController::class, 'search']);
    $r->post('/colleagues', [ColleagueController::class, 'add']);
    $r->post('/colleagues/requests/{colreq}/accept', [ColleagueController::class, 'accept']);
    $r->post('/colleagues/requests/{colreq}/reject', [ColleagueController::class, 'reject']);
    $r->post('/colleagues/{user}/remove', [ColleagueController::class, 'remove']);
    $r->post('/colleagues/followers/{user}/remove', [ColleagueController::class, 'removeFollower']);
    $r->post('/colleagues/{user}/block', [ColleagueController::class, 'block']);
    $r->post('/colleagues/unblock-key', [ColleagueController::class, 'unblockKey']);
    $r->post('/colleagues/{user}/unblock', [ColleagueController::class, 'unblock']);

    // دپارتمان / گروه (v5.10)
    $r->get('/groups', [GroupController::class, 'index']);
    $r->get('/groups/members', [GroupController::class, 'members']);
    $r->post('/groups', [GroupController::class, 'store']);
    $r->post('/groups/{group}/update', [GroupController::class, 'update']);
    $r->post('/groups/{group}/delete', [GroupController::class, 'delete']);

    // پروژه‌ها
    $r->get('/projects', [ProjectController::class, 'index']);
    $r->post('/projects', [ProjectController::class, 'store']);
    $r->post('/projects/history/clear', [ProjectController::class, 'clearHistory']);
    $r->post('/projects/requests/{projreq}/respond', [ProjectController::class, 'respondRequest']);
    $r->get('/projects/{project}/analytics', [ProjectController::class, 'analytics']);
    $r->get('/projects/{project}/analytics/speed', [ProjectController::class, 'speed']);
    $r->post('/projects/{project}/update', [ProjectController::class, 'update']);
    $r->post('/projects/{project}/leave', [ProjectController::class, 'leave']);
    $r->post('/projects/{project}/block', [ProjectController::class, 'block']);
    $r->post('/projects/{project}/unblock', [ProjectController::class, 'unblock']);
    $r->post('/projects/{project}/members/{user}/permissions', [ProjectController::class, 'memberPermissions']);
    $r->post('/projects/{project}/members/{user}/task-permissions', [ProjectController::class, 'memberTaskPermissions']);
    $r->post('/projects/{project}/members/{user}/board-permissions', [ProjectController::class, 'memberBoardPermissions']);
    $r->post('/projects/{project}/members/add', [ProjectController::class, 'addMember']);
    $r->post('/projects/{project}/members/{user}/remove', [ProjectController::class, 'removeMember']);

    // گفتگو (JSON)
    $r->get('/chat', [ChatController::class, 'index']);
    $r->get('/chat/api/time', [ChatController::class, 'time']);
    $r->get('/chat/api/list', [ChatController::class, 'contacts']);
    $r->get('/chat/api/fetch', [ChatController::class, 'fetch']);
    $r->get('/chat/api/search', [ChatController::class, 'search']);
    $r->get('/chat/api/unread_counts', [ChatController::class, 'unreadCounts']);
    $r->get('/chat/api/total_unread', [ChatController::class, 'totalUnread']);
    $r->get('/chat/api/latest_unread', [ChatController::class, 'latestUnread']);
    $r->get('/chat/api/heartbeat', [ChatController::class, 'heartbeat']);
    $r->get('/chat/api/user_status', [ChatController::class, 'userStatus']);
    $r->get('/chat/api/users_status', [ChatController::class, 'usersStatus']);
    $r->get('/chat/api/saved_info', [ChatController::class, 'savedInfo']);
    $r->post('/chat/api/send', [ChatController::class, 'send']);
    $r->post('/chat/api/start', [ChatController::class, 'start']);
    $r->post('/chat/api/accept', [ChatController::class, 'accept']);
    $r->post('/chat/api/send_file', [ChatController::class, 'sendFile']);
    $r->post('/chat/api/mark_read', [ChatController::class, 'markRead']);
    $r->post('/chat/api/edit', [ChatController::class, 'edit']);
    $r->post('/chat/api/forward', [ChatController::class, 'forward']);
    $r->post('/chat/api/clear_history', [ChatController::class, 'clearHistory']);

    // توکن‌های API
    $r->get('/tokens', [TokenController::class, 'index']);
    $r->post('/tokens', [TokenController::class, 'store']);
    $r->post('/tokens/{token}/toggle', [TokenController::class, 'toggle']);
    $r->post('/tokens/{token}/delete', [TokenController::class, 'destroy']);

    // پشتیبانی
    $r->get('/support', [SupportController::class, 'index']);
    $r->post('/support', [SupportController::class, 'store']);
    $r->get('/support/{ticket}', [SupportController::class, 'show']);
    $r->post('/support/{ticket}/reply', [SupportController::class, 'reply']);
    $r->post('/support/{ticket}/close', [SupportController::class, 'close']);
    $r->post('/support/{ticket}/reopen', [SupportController::class, 'reopen']);
});

// پروفایل و فایل‌ها: ادمین هم دسترسی دارد
$router->group(['auth'], function ($r) {
    $r->get('/profile', [ProfileController::class, 'index']);
    $r->post('/profile/info', [ProfileController::class, 'updateInfo']);
    $r->post('/profile/password', [ProfileController::class, 'updatePassword']);
    $r->post('/profile/avatar', [ProfileController::class, 'avatar']);
    $r->post('/profile/avatar/delete', [ProfileController::class, 'removeAvatar']);
    $r->post('/profile/privacy', [ProfileController::class, 'privacy']);
    $r->post('/profile/task-security', [ProfileController::class, 'taskSecurity']);
    $r->post('/profile/appearance', [ProfileController::class, 'appearance']);
    $r->post('/profile/sessions/revoke', [ProfileController::class, 'revokeSession']);
    $r->post('/profile/sessions/revoke-others', [ProfileController::class, 'revokeOtherSessions']);
    $r->post('/tour', [TourController::class, 'update']);
    $r->get('/tour/start', [TourController::class, 'start']);
    $r->get('/files/{type}/{name}', [FileController::class, 'serve']);
    $r->get('/live/poll', [LiveController::class, 'poll']);
    $r->get('/install', [PwaController::class, 'install']);
    $r->get('/push/key', [PwaController::class, 'pushKey']);
    $r->post('/push/subscribe', [PwaController::class, 'subscribe']);
    $r->post('/push/unsubscribe', [PwaController::class, 'unsubscribe']);
    $r->post('/push/test', [PwaController::class, 'test']);
});

// ───────────────────────── مدیریت ─────────────────────────
$router->post('/admin/impersonate/exit', [AdminController::class, 'stopImpersonate'], ['auth']);
$router->group(['admin'], function ($r) {
    $r->get('/admin', [AdminController::class, 'users']);
    $r->post('/admin/users', [AdminController::class, 'createUser']);
    $r->post('/admin/users/{user}/update', [AdminController::class, 'updateUser']);
    $r->post('/admin/users/{user}/status', [AdminController::class, 'setStatus']);
    $r->post('/admin/users/{user}/delete', [AdminController::class, 'deleteUser']);
    $r->post('/admin/users/{user}/impersonate', [AdminController::class, 'impersonate']);
    $r->get('/admin/impersonations', [AdminController::class, 'impersonations']);
    $r->post('/admin/impersonations/end-all', [AdminController::class, 'impersonationEndAll']);
    $r->post('/admin/impersonations/{imp}/end', [AdminController::class, 'impersonationEnd']);
    $r->get('/admin/users/{user}/details', [AdminController::class, 'userDetails']);
    $r->get('/admin/users/{user}/tasks', [AdminController::class, 'userTasks']);
    $r->get('/admin/support', [AdminController::class, 'support']);
    $r->get('/admin/support/{ticket}', [AdminController::class, 'supportShow']);
    $r->post('/admin/support/{ticket}/reply', [AdminController::class, 'supportReply']);
    $r->post('/admin/support/{ticket}/status', [AdminController::class, 'supportStatus']);
    $r->post('/admin/support/{ticket}/delete', [AdminController::class, 'supportDelete']);
    $r->get('/admin/analysis', [AdminController::class, 'analysis']);
    $r->get('/admin/list/{kind}', [AdminController::class, 'listing']);
    $r->get('/admin/list/projects/{project}', [AdminController::class, 'projectInfo']);
    $r->get('/admin/list/projects/{project}/{part}', [AdminController::class, 'projectPart']);
    $r->get('/admin/docs', [AdminController::class, 'panelDocs']);
    $r->get('/admin/security', [AdminController::class, 'security']);
    $r->get('/admin/content/sidebar', [AdminController::class, 'contentSidebar']);
    $r->post('/admin/content/sidebar', [AdminController::class, 'contentSidebarSave']);
    $r->post('/admin/content/sidebar/reset', [AdminController::class, 'contentSidebarReset']);
    $r->get('/admin/content/tour', [AdminController::class, 'contentTour']);
    $r->post('/admin/content/tour/{id}', [AdminController::class, 'contentTourSave']);
    $r->get('/admin/content/placeholders', [AdminController::class, 'contentPlaceholders']);
    $r->post('/admin/content/placeholders/{id}', [AdminController::class, 'contentPlaceholderSave']);
    $r->get('/admin/content/errors', [AdminController::class, 'contentErrors']);
    $r->post('/admin/content/errors/{id}', [AdminController::class, 'contentErrorSave']);
    $r->post('/admin/chat/encrypt', [AdminController::class, 'chatEncrypt']);
});

// ───────────────────────── API v1 ─────────────────────────
$router->group(['api'], function ($r) {
    $r->get('/', [MetaApi::class, 'index']);
    $r->get('/me', [MetaApi::class, 'me']);
    $r->get('/time', [MetaApi::class, 'time']);

    $r->get('/tasks/list', [TaskApi::class, 'list']);
    $r->get('/tasks/show', [TaskApi::class, 'show']);
    $r->get('/tasks/stats', [TaskApi::class, 'stats']);
    $r->post('/tasks/create', [TaskApi::class, 'create']);
    $r->post('/tasks/update', [TaskApi::class, 'update']);
    $r->post('/tasks/toggle', [TaskApi::class, 'toggle']);
    $r->get('/tasks/blocked', [BlockApi::class, 'taskList']);
    $r->post('/tasks/block', [BlockApi::class, 'taskBlock']);
    $r->post('/tasks/unblock', [BlockApi::class, 'taskUnblock']);

    $r->get('/calls/list', [CallApi::class, 'list']);
    $r->get('/calls/show', [CallApi::class, 'show']);
    $r->get('/calls/stats', [CallApi::class, 'stats']);
    $r->post('/calls/create', [CallApi::class, 'create']);
    $r->post('/calls/update', [CallApi::class, 'update']);
    $r->post('/calls/status', [CallApi::class, 'status']);
    $r->get('/calls/blocked', [BlockApi::class, 'callList']);
    $r->post('/calls/block', [BlockApi::class, 'callBlock']);
    $r->post('/calls/unblock', [BlockApi::class, 'callUnblock']);


    $r->get('/texts/list', [TextApi::class, 'list']);
    $r->get('/texts/show', [TextApi::class, 'show']);
    $r->get('/texts/stats', [TextApi::class, 'stats']);
    $r->post('/texts/create', [TextApi::class, 'create']);
    $r->post('/texts/update', [TextApi::class, 'update']);
    $r->post('/texts/delete', [TextApi::class, 'delete']);

    $r->get('/colleagues/list', [ColleagueApi::class, 'list']);
    $r->get('/colleagues/followers', [ColleagueApi::class, 'followers']);
    $r->post('/colleagues/followers/remove', [ColleagueApi::class, 'followerRemove']);
    $r->get('/colleagues/blocked', [ColleagueApi::class, 'blocked']);
    $r->get('/colleagues/show', [ColleagueApi::class, 'show']);
    $r->get('/colleagues/stats', [ColleagueApi::class, 'stats']);
    $r->get('/colleagues/search', [ColleagueApi::class, 'search']);
    $r->post('/colleagues/create', [ColleagueApi::class, 'create']);
    $r->post('/colleagues/delete', [ColleagueApi::class, 'delete']);
    $r->post('/colleagues/permissions', [ColleagueApi::class, 'permissions']);
    $r->post('/colleagues/block', [ColleagueApi::class, 'block']);
    $r->post('/colleagues/unblock', [ColleagueApi::class, 'unblock']);

    $r->get('/labels/list', [LabelApi::class, 'list']);
    $r->post('/labels/create', [LabelApi::class, 'create']);
    $r->post('/labels/update', [LabelApi::class, 'update']);
    $r->get('/labels/blocked', [BlockApi::class, 'labelList']);
    $r->post('/labels/block', [BlockApi::class, 'labelBlock']);
    $r->post('/labels/unblock', [BlockApi::class, 'labelUnblock']);

    $r->get('/board/list', [BoardApi::class, 'list']);
    $r->get('/board/archive', [BoardApi::class, 'archive']);
    $r->post('/board/move', [BoardApi::class, 'move']);
    $r->post('/board/order', [BoardApi::class, 'order']);
    $r->post('/board/archive-done', [BoardApi::class, 'archiveDone']);

    $r->get('/projects/list', [ProjectApi::class, 'list']);
    $r->get('/projects/show', [ProjectApi::class, 'show']);
    $r->get('/projects/members', [ProjectApi::class, 'membersList']);
    $r->get('/projects/stats', [ProjectApi::class, 'stats']);
    $r->post('/projects/create', [ProjectApi::class, 'create']);
    $r->post('/projects/update', [ProjectApi::class, 'update']);
    $r->post('/projects/leave', [ProjectApi::class, 'leave']);
    $r->post('/projects/block', [ProjectApi::class, 'block']);
    $r->get('/projects/blocked-projects', [BlockApi::class, 'projectList']);
    $r->post('/projects/block-project', [BlockApi::class, 'projectBlock']);
    $r->post('/projects/unblock-project', [BlockApi::class, 'projectUnblock']);
    $r->post('/projects/member-add', [ProjectApi::class, 'memberAdd']);
    $r->post('/projects/member-remove', [ProjectApi::class, 'memberRemove']);
    $r->post('/projects/member-permissions', [ProjectApi::class, 'memberPermissions']);
    $r->post('/projects/task-permissions', [ProjectApi::class, 'memberTaskPermissions']);
    $r->post('/projects/board-permissions', [ProjectApi::class, 'memberBoardPermissions']);
    $r->post('/projects/member-role', [ProjectApi::class, 'memberRole']);
}, '/api/v1');
