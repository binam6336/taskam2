<?php
declare(strict_types=1);

/** @var App\Core\Router $router */

use App\Controllers\AdminController;
use App\Controllers\BoardController;
use App\Controllers\Api\V1\BoardApi;
use App\Controllers\Api\V1\CallApi;
use App\Controllers\Api\V1\ColleagueApi;
use App\Controllers\Api\V1\MetaApi;
use App\Controllers\Api\V1\LabelApi;
use App\Controllers\Api\V1\ProjectApi;
use App\Controllers\Api\V1\TaskApi;
use App\Controllers\Api\V1\TextApi;
use App\Controllers\AuthController;
use App\Controllers\CallController;
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
    $r->post('/register', [AuthController::class, 'register']);
});
$router->post('/logout', [AuthController::class, 'logout'], ['authany']);
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
    $r->post('/tasks', [TaskController::class, 'store']);
    $r->get('/tasks/{id}', [TaskController::class, 'show']);
    $r->post('/tasks/{id}/update', [TaskController::class, 'update']);
    $r->post('/tasks/{id}/toggle', [TaskController::class, 'toggle']);
    $r->post('/tasks/{id}/delete', [TaskController::class, 'destroy']);
    $r->get('/tasks/{id}/share', [TaskController::class, 'share']);
    $r->post('/tasks/{id}/notes', [TaskController::class, 'addNote']);
    $r->post('/tasks/{id}/attachments', [TaskController::class, 'addFiles']);
    $r->post('/notes/{id}/update', [TaskController::class, 'updateNote']);
    $r->post('/notes/{id}/delete', [TaskController::class, 'deleteNote']);
    $r->post('/files/{kind}/{id}/delete', [TaskController::class, 'deleteFile']);
    $r->get('/notifications/due', [TaskController::class, 'due']);

    // برد تیمی (کانبان، دمو)
    $r->get('/board', [BoardController::class, 'index']);
    $r->post('/board/tasks/{id}/move', [BoardController::class, 'move']);
    $r->get('/board/archive', [BoardController::class, 'archive']);
    $r->post('/board/archive-done', [BoardController::class, 'archiveDone']);

    // موضوعات
    $r->get('/labels', [SubjectController::class, 'index']);
    $r->post('/labels', [SubjectController::class, 'store']);
    $r->post('/labels/{id}/update', [SubjectController::class, 'update']);
    $r->post('/labels/{id}/delete', [SubjectController::class, 'destroy']);
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
    $r->post('/calls/{id}/update', [CallController::class, 'update']);
    $r->post('/calls/{id}/complete', [CallController::class, 'complete']);
    $r->post('/calls/{id}/delete', [CallController::class, 'destroy']);
    $r->get('/calls/{id}/share', [CallController::class, 'share']);

    // همکاران
    $r->get('/colleagues', [ColleagueController::class, 'index']);
    $r->get('/colleagues/search', [ColleagueController::class, 'search']);
    $r->post('/colleagues', [ColleagueController::class, 'add']);
    $r->post('/colleagues/requests/{id}/accept', [ColleagueController::class, 'accept']);
    $r->post('/colleagues/requests/{id}/reject', [ColleagueController::class, 'reject']);
    $r->post('/colleagues/{userId}/remove', [ColleagueController::class, 'remove']);
    $r->post('/colleagues/{userId}/block', [ColleagueController::class, 'block']);
    $r->post('/colleagues/{userId}/unblock', [ColleagueController::class, 'unblock']);

    // پروژه‌ها
    $r->get('/projects', [ProjectController::class, 'index']);
    $r->post('/projects', [ProjectController::class, 'store']);
    $r->post('/projects/history/clear', [ProjectController::class, 'clearHistory']);
    $r->post('/projects/requests/{id}/respond', [ProjectController::class, 'respondRequest']);
    $r->get('/projects/{id}/analytics', [ProjectController::class, 'analytics']);
    $r->get('/projects/{id}/analytics/speed', [ProjectController::class, 'speed']);
    $r->post('/projects/{id}/update', [ProjectController::class, 'update']);
    $r->post('/projects/{id}/delete', [ProjectController::class, 'destroy']);
    $r->post('/projects/{id}/leave', [ProjectController::class, 'leave']);
    $r->post('/projects/{id}/block', [ProjectController::class, 'block']);
    $r->post('/projects/{id}/unblock', [ProjectController::class, 'unblock']);
    $r->post('/projects/{id}/members/{userId}/permissions', [ProjectController::class, 'memberPermissions']);
    $r->post('/projects/{id}/members/{userId}/task-permissions', [ProjectController::class, 'memberTaskPermissions']);
    $r->post('/projects/{id}/members/{userId}/board-permissions', [ProjectController::class, 'memberBoardPermissions']);
    $r->post('/projects/{id}/members/{userId}/remove', [ProjectController::class, 'removeMember']);

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
    $r->post('/chat/api/send_file', [ChatController::class, 'sendFile']);
    $r->post('/chat/api/mark_read', [ChatController::class, 'markRead']);
    $r->post('/chat/api/edit', [ChatController::class, 'edit']);
    $r->post('/chat/api/delete', [ChatController::class, 'delete']);
    $r->post('/chat/api/forward', [ChatController::class, 'forward']);
    $r->post('/chat/api/clear_history', [ChatController::class, 'clearHistory']);

    // توکن‌های API
    $r->get('/tokens', [TokenController::class, 'index']);
    $r->post('/tokens', [TokenController::class, 'store']);
    $r->post('/tokens/{id}/toggle', [TokenController::class, 'toggle']);
    $r->post('/tokens/{id}/delete', [TokenController::class, 'destroy']);

    // پشتیبانی
    $r->get('/support', [SupportController::class, 'index']);
    $r->post('/support', [SupportController::class, 'store']);
    $r->get('/support/{id}', [SupportController::class, 'show']);
    $r->post('/support/{id}/reply', [SupportController::class, 'reply']);
    $r->post('/support/{id}/close', [SupportController::class, 'close']);
    $r->post('/support/{id}/reopen', [SupportController::class, 'reopen']);
    $r->post('/support/{id}/delete', [SupportController::class, 'destroy']);
});

// پروفایل و فایل‌ها: ادمین هم دسترسی دارد
$router->group(['auth'], function ($r) {
    $r->get('/profile', [ProfileController::class, 'index']);
    $r->post('/profile/info', [ProfileController::class, 'updateInfo']);
    $r->post('/profile/password', [ProfileController::class, 'updatePassword']);
    $r->post('/profile/avatar', [ProfileController::class, 'avatar']);
    $r->post('/profile/avatar/delete', [ProfileController::class, 'removeAvatar']);
    $r->post('/profile/privacy', [ProfileController::class, 'privacy']);
    $r->post('/profile/quick-links', [ProfileController::class, 'quickLinks']);
    $r->post('/profile/task-security', [ProfileController::class, 'taskSecurity']);
    $r->post('/profile/appearance', [ProfileController::class, 'appearance']);
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
    $r->post('/admin/users/{id}/update', [AdminController::class, 'updateUser']);
    $r->post('/admin/users/{id}/status', [AdminController::class, 'setStatus']);
    $r->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
    $r->post('/admin/users/{id}/impersonate', [AdminController::class, 'impersonate']);
    $r->get('/admin/support', [AdminController::class, 'support']);
    $r->get('/admin/support/{id}', [AdminController::class, 'supportShow']);
    $r->post('/admin/support/{id}/reply', [AdminController::class, 'supportReply']);
    $r->post('/admin/support/{id}/status', [AdminController::class, 'supportStatus']);
    $r->post('/admin/support/{id}/delete', [AdminController::class, 'supportDelete']);
    $r->get('/admin/analysis', [AdminController::class, 'analysis']);
    $r->get('/admin/content/tour', [AdminController::class, 'contentTour']);
    $r->post('/admin/content/tour/{id}', [AdminController::class, 'contentTourSave']);
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
    $r->post('/tasks/delete', [TaskApi::class, 'delete']);
    $r->post('/tasks/toggle', [TaskApi::class, 'toggle']);

    $r->get('/calls/list', [CallApi::class, 'list']);
    $r->get('/calls/show', [CallApi::class, 'show']);
    $r->get('/calls/stats', [CallApi::class, 'stats']);
    $r->post('/calls/create', [CallApi::class, 'create']);
    $r->post('/calls/update', [CallApi::class, 'update']);
    $r->post('/calls/status', [CallApi::class, 'status']);
    $r->post('/calls/delete', [CallApi::class, 'delete']);


    $r->get('/texts/list', [TextApi::class, 'list']);
    $r->get('/texts/show', [TextApi::class, 'show']);
    $r->get('/texts/stats', [TextApi::class, 'stats']);
    $r->post('/texts/create', [TextApi::class, 'create']);
    $r->post('/texts/update', [TextApi::class, 'update']);
    $r->post('/texts/delete', [TextApi::class, 'delete']);

    $r->get('/colleagues/list', [ColleagueApi::class, 'list']);
    $r->get('/colleagues/followers', [ColleagueApi::class, 'followers']);
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
    $r->post('/labels/delete', [LabelApi::class, 'delete']);

    $r->get('/board/list', [BoardApi::class, 'list']);
    $r->get('/board/archive', [BoardApi::class, 'archive']);
    $r->post('/board/move', [BoardApi::class, 'move']);
    $r->post('/board/archive-done', [BoardApi::class, 'archiveDone']);

    $r->get('/projects/list', [ProjectApi::class, 'list']);
    $r->get('/projects/show', [ProjectApi::class, 'show']);
    $r->get('/projects/members', [ProjectApi::class, 'membersList']);
    $r->get('/projects/stats', [ProjectApi::class, 'stats']);
    $r->post('/projects/create', [ProjectApi::class, 'create']);
    $r->post('/projects/update', [ProjectApi::class, 'update']);
    $r->post('/projects/delete', [ProjectApi::class, 'delete']);
    $r->post('/projects/leave', [ProjectApi::class, 'leave']);
    $r->post('/projects/block', [ProjectApi::class, 'block']);
    $r->post('/projects/member-add', [ProjectApi::class, 'memberAdd']);
    $r->post('/projects/member-remove', [ProjectApi::class, 'memberRemove']);
    $r->post('/projects/member-permissions', [ProjectApi::class, 'memberPermissions']);
    $r->post('/projects/task-permissions', [ProjectApi::class, 'memberTaskPermissions']);
    $r->post('/projects/board-permissions', [ProjectApi::class, 'memberBoardPermissions']);
    $r->post('/projects/member-role', [ProjectApi::class, 'memberRole']);
}, '/api/v1');
