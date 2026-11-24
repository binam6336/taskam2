<?php
namespace KarlixLaravel\Session;

/** مدیر نشست Laravel؛ ذخیره‌سازی به نشست مشترک و محدودهٔ impersonation واگذار می‌شود. */
final class SessionManager extends \Illuminate\Session\SessionManager
{
    protected function createKarlixSharedDriver() { return new SharedStore(); }
}
