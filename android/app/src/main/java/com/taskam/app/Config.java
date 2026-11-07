package com.taskam.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.Uri;

/** آدرس سرور تسکام: ذخیره‌شده در تنظیمات برنامه، وگرنه آدرس پیش‌فرض زمان ساخت (BuildConfig.SERVER_URL). */
final class Config {
    private static final String PREFS = "taskam";
    private static final String KEY = "server";

    private Config() {}

    static String server(Context c) {
        String s = c.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(KEY, null);
        if (s != null && !s.isEmpty()) return s;
        String def = BuildConfig.SERVER_URL;
        return def == null || def.isEmpty() ? null : clean(def);
    }

    static void save(Context c, String url) {
        SharedPreferences.Editor e = c.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit();
        e.putString(KEY, clean(url)).apply();
    }

    /** بدون / انتهایی، query و fragment */
    static String clean(String url) {
        Uri u = Uri.parse(url.trim());
        String path = u.getPath() == null ? "" : u.getPath();
        while (path.endsWith("/")) path = path.substring(0, path.length() - 1);
        return u.getScheme() + "://" + u.getEncodedAuthority() + path;
    }
}
