package com.taskam.app;

import android.app.Activity;
import android.content.ContentResolver;
import android.content.ContentValues;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Environment;
import android.provider.MediaStore;
import android.webkit.MimeTypeMap;
import android.webkit.URLUtil;
import android.widget.Toast;

import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

import javax.net.ssl.HttpsURLConnection;
import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManager;

/**
 * دانلود فایل‌های پنل (پیوست تسک، فایل گفتگو، …) در پوشه‌ی Downloads گوشی، همراه کوکی نشست و همان User-Agent
 * (نشست به مرورگر گره خورده است). اتصال HTTPS با {@link AiaTrust} بررسی می‌شود تا زنجیره‌ی گواهی ناقص سرور هم کار کند؛
 * نام دامنه با بررسی‌کننده‌ی پیش‌فرض اندروید چک می‌شود.
 */
final class Downloader {
    private Downloader() {}

    static void start(Activity a, String url, String ua, String cookie, String cd, String mime) {
        Toast.makeText(a, "در حال دانلود…", Toast.LENGTH_SHORT).show();
        new Thread(() -> {
            String msg;
            Uri saved = null;
            String type = mime;
            try {
                HttpURLConnection con = (HttpURLConnection) new URL(url).openConnection();
                if (con instanceof HttpsURLConnection) {
                    SSLContext ctx = SSLContext.getInstance("TLS");
                    ctx.init(null, new TrustManager[]{AiaTrust.get()}, null);
                    ((HttpsURLConnection) con).setSSLSocketFactory(ctx.getSocketFactory());
                }
                con.setConnectTimeout(15000);
                con.setReadTimeout(30000);
                if (cookie != null) con.setRequestProperty("Cookie", cookie);
                con.setRequestProperty("User-Agent", ua);
                int code = con.getResponseCode();
                if (code != 200) throw new IllegalStateException("HTTP " + code);
                String disp = con.getHeaderField("Content-Disposition");
                if (disp == null) disp = cd;
                if (con.getContentType() != null) type = con.getContentType().split(";")[0].trim();
                String name = fileName(url, disp, type);
                try (InputStream in = con.getInputStream()) {
                    saved = save(a, name, type, in);
                }
                msg = "«" + name + "» در پوشه‌ی Downloads ذخیره شد.";
            } catch (Exception e) {
                msg = "دانلود انجام نشد (" + e.getClass().getSimpleName() + (e.getMessage() != null ? ": " + e.getMessage() : "") + ").";
            }
            final String m = msg;
            final Uri u = saved;
            final String t = type;
            a.runOnUiThread(() -> {
                Toast.makeText(a, m, Toast.LENGTH_LONG).show();
                if (u != null) {
                    try {
                        Intent v = new Intent(Intent.ACTION_VIEW).setDataAndType(u, t != null ? t : "*/*")
                                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION | Intent.FLAG_ACTIVITY_NEW_TASK);
                        a.startActivity(Intent.createChooser(v, "باز کردن فایل"));
                    } catch (Exception ignored) { }
                }
            });
        }).start();
    }

    private static Uri save(Activity a, String name, String mime, InputStream in) throws Exception {
        if (Build.VERSION.SDK_INT >= 29) {
            ContentResolver r = a.getContentResolver();
            ContentValues v = new ContentValues();
            v.put(MediaStore.Downloads.DISPLAY_NAME, name);
            if (mime != null) v.put(MediaStore.Downloads.MIME_TYPE, mime);
            v.put(MediaStore.Downloads.IS_PENDING, 1);
            Uri uri = r.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, v);
            if (uri == null) throw new IllegalStateException("MediaStore");
            try (OutputStream out = r.openOutputStream(uri)) {
                copy(in, out);
            } catch (Exception e) {
                r.delete(uri, null, null);
                throw e;
            }
            v.clear();
            v.put(MediaStore.Downloads.IS_PENDING, 0);
            r.update(uri, v, null, null);
            return uri;
        }
        File dir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS);
        if (!dir.exists() && !dir.mkdirs()) throw new IllegalStateException("Downloads");
        File f = new File(dir, name);
        int n = 1;
        while (f.exists()) {
            int dot = name.lastIndexOf('.');
            f = new File(dir, dot > 0 ? name.substring(0, dot) + " (" + n + ")" + name.substring(dot) : name + " (" + n + ")");
            n++;
        }
        try (OutputStream out = new FileOutputStream(f)) {
            copy(in, out);
        }
        return null;   // روی اندروید قدیمی فقط ذخیره می‌شود (باز کردن file:// مجاز نیست)
    }

    private static void copy(InputStream in, OutputStream out) throws Exception {
        byte[] b = new byte[16384];
        int r;
        while ((r = in.read(b)) > 0) out.write(b, 0, r);
    }

    /** نام فایل از Content-Disposition (filename*=UTF-8''… برای نام‌های فارسی)، وگرنه حدس از آدرس. */
    static String fileName(String url, String cd, String mime) {
        if (cd != null) {
            Matcher m = Pattern.compile("filename\\*\\s*=\\s*(?:UTF-8|utf-8)''([^;]+)").matcher(cd);
            try {
                if (m.find()) return safe(URLDecoder.decode(m.group(1).trim().replace("+", "%2B"), "UTF-8"));
            } catch (Exception ignored) { }
            m = Pattern.compile("filename\\s*=\\s*\"?([^\";]+)\"?").matcher(cd);
            if (m.find()) return safe(m.group(1).trim());
        }
        String n = URLUtil.guessFileName(url, cd, mime);
        if (n.endsWith(".bin") && mime != null) {
            String ext = MimeTypeMap.getSingleton().getExtensionFromMimeType(mime);
            if (ext != null) n = n.substring(0, n.length() - 4) + "." + ext;
        }
        return safe(n);
    }

    private static String safe(String n) {
        n = n.replaceAll("[\\\\/:*?\"<>|\\x00-\\x1f]", "_").trim();
        return n.isEmpty() ? "taskam-file" : n;
    }
}
