# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Taskam v2 (تسکام) is a Persian (RTL) task/team management panel: plain PHP MVC, no Composer, no build step, no frameworks, no test suite. UI text, comments and commit discussion with the user are in Persian.

## نسخه‌گذاری (الزامی در هر کامیت)
- نسخه‌ی فعلی برنامه در فایل `VERSION` است (Semantic Versioning: MAJOR.MINOR.PATCH).
- در هر کامیت، نسخه را بر اساس نوع تغییر بالا ببرید، فایل `VERSION` را به‌روز کنید و نسخه را ابتدای عنوان کامیت بنویسید، مثل: `v2.1.0: ...`
  - **PATCH** (2.0.0 → 2.0.1): رفع باگ، اصلاح ظاهری یا متنی، بدون قابلیت جدید.
  - **MINOR** (2.0.0 → 2.1.0): قابلیت جدید یا تغییر سازگار با قبل (از جمله migration افزایشی دیتابیس).
  - **MAJOR** (2.0.0 → 3.0.0): تغییر ناسازگار (شکستن API، حذف قابلیت، تغییر ساختار دیتابیس که مهاجرت دستی می‌خواهد).
  - تغییرات فقط مستندات/ابزار که روی برنامه اثری ندارند نسخه را بالا نمی‌برند؛ عنوان کامیت همان نسخه‌ی فعلی را دارد.
- در هر کامیتی که نسخه را بالا می‌برد، تغییرات همان نسخه را به فارسی و به زبان کاربر (نه جزئیات فنی) در بالای `docs/changes.md` بنویسید: `## X.Y.Z — تاریخ شمسی` و فهرست تغییرات.

## Running locally
- Requirements: PHP 7.4+ (8.2 recommended) with `pdo_mysql`, `mbstring`, `fileinfo`, `gd` (+ `openssl` for Web Push); MySQL 5.7+/MariaDB 10.3+.
- Setup: `cp .env.example .env` (fill `DB_*`, `APP_KEY`), create DB `utf8mb4_unicode_ci`, `mysql taskam < database/schema.sql`, `php bin/create-admin.php 09120000000 "مدیر" "سیستم"`.
- Source zip for sharing: `sh bin/package.sh` (git archive; never zip the working dir). Migrations without web ALTER rights: `SCHEMA_AUTO=false` + `php bin/migrate.php`.
- Dev server: `php -S 127.0.0.1:8080 -t public router.php`, where `router.php` serves existing files under `public/` and otherwise sets `$_SERVER['SCRIPT_NAME'] = '/index.php'` and requires `public/index.php` (there is no router file in the repo; Apache uses `.htaccess`).
- Syntax check: `php -l <file>` for PHP, `node --check <file>` for JS. Verification is done by driving the app in a browser (Playwright), not unit tests.
- Never commit `.env` or `storage/vapid.json`.

## Architecture
Request flow: `public/index.php` → `App::run()` (security headers, session) → `Router` (routes in `config/routes.php`, middleware groups `guest`, `auth`, `authany`, `panel`, `admin`, `csrf` (automatic for web POST), `api`) → Controller → Service → Model → View or JSON. See `docs/ARCHITECTURE.md`, `docs/SECURITY.md`, `docs/DEPLOY.md`.

- **Services are shared by the web panel and the REST API** (`app/Controllers/Api/V1/*`). Business rules live once in a Service and return `['ok','message','code','http','data']` (`res(...)`); web controllers call `$this->respond(...)` (JSON for AJAX, flash+redirect otherwise), API controllers map via `ApiController::fromService` (http 422 → 400).
- **`app/Services/Access.php`** is the single place for permission decisions (creator / assignee / project member perms, task perms, board perms). Don't re-implement checks in controllers.
- **API docs are generated**: `config/api_spec.php` (endpoints/examples) + `config/api_fields.php` (field descriptions) drive both the `/docs` page and `GET /api/v1/`. Any API change must update these.
- **Database changes**: add `database/migrations/NNN_*.sql`, update `database/schema.sql`, and register the column/table in `app/Core/Schema.php` (`COLUMNS`/`TABLES`, bump `VERSION`) — it auto-applies missing additive changes at runtime and writes a flag file to `storage/cache/schema-NNN`.
- **Admin-editable content** lives in `content_texts` (key/body) with defaults in config: tour texts (`config/tour_texts.php`, `ContentModel`) and the user-panel sidebar layout (`config/sidebar.php`, `SidebarModel`, key `sidebar.user`). The user sidebar menu is built from `SidebarModel::nav()`, not hard-coded in `layouts/app.php`.
- **User-facing error messages** are catalogued in `config/error_texts.php` (id → group, exact default text, explanation) and admin-editable in «محتوا ← خطاها». `App\Core\ErrorText::apply()` swaps the text by matching the message itself (`{var}` placeholders capture dynamic parts) in `Response::json`, `Session::flash`, `ErrorHandler`, `Uploader` and client-side `TK.toast`. When you add or change an error string in code, add/update its entry there with the exact same text.
- **Input placeholders** in the user panel come from `config/placeholder_texts.php` (id → group, location path, URL, default) and are admin-editable in «محتوا ← Placeholder»: use `ph('id')` in views and `TK.ph('id', 'default')` in JS (overrides reach JS via `boot.ph`). New user-facing placeholders should be added there instead of hard-coded.
- **Security rules (from the v3.25 audit, see `docs/SECURITY.md` §3-1)**: admin permissions are `Auth::ADMIN_PERMS` (super admin = all; `impersonate`, `edit_content` are separate); sensitive account actions call `denyImpersonation()`; completing a project task goes through `BoardService::move` (board step perms + column limits); colleague adds are always request-based; mobiles/emails shown to people without consent go through `mask_mobile()`; web push endpoints must pass `Push::allowedEndpoint()`; heavy panel routes call `$this->rateLimit()`; user uploads pass `$uid` to `Uploader::saveAttachment()` (daily quota); `Throttle::attempt` is hit-then-count; sessions start lazily (only with a session cookie or on first write) and are stored in the `user_sessions` table by `DbSessionHandler` (SHA-256 of the id only), bound at login to the device's IP prefix and browser (`Auth::sameNetwork`, `SESSION_IP_BIND`); client IP comes from proxy headers only via `Request::fromTrustedProxy()` (`TRUSTED_PROXY`=cloudflare|true + `TRUSTED_PROXY_IPS`); project tasks can only be assigned to members of that project.
- Layouts: `app/Views/layouts/app.php` (user panel), `admin.php` (admin panel), both render `partials/sidebar_nav.php`. Page key (`'page' => ...` in controller view meta) drives the active menu item, badges and `boot.live.scope`.
- Labels were renamed from "subjects": DB table `subjects` and fields `subject_id/subject_title/subject_color` are kept for compatibility, UI says لیبل and the panel URL is `/labels`.

## Frontend conventions
- One stylesheet `public/assets/css/app.css` (design tokens on `:root`; light theme overrides under `html[data-theme="sage"]` — check new UI in both themes, themes defined in `config/themes.php`).
- One core script `public/assets/js/app.js` exposing the `TK` namespace (`TK.http/post/get`, `TK.toast`, `TK.modal`, `TK.confirm`, `TK.rselect`, AJAX forms via `form[data-ajax]` + `tk:done` event, `TK.after` with `data-after`, live refresh of `[data-live]` regions). Per-page modules in `public/assets/js/modules/`, added from a view with `$this->scripts[] = 'name'`; server data is passed through `$this->boot[...]` and read with `TK.boot()`.
- Strict CSP (`script-src 'self'`): no inline scripts or inline event handlers. Escape dynamic text with `e()` in PHP and `TK.esc()` in JS.
- Onboarding tour: `public/assets/js/modules/tour.js` (numbered `STEPS`, state stored in `users.tour`: NULL = not asked, `skip`, `done`, or step number; form fields targeted via `data-tour="..."` attributes).

## Collaboration notes
- Reply to the user in Persian.
- When something seems missing in permissions/roles, report it to the user instead of adding it.
