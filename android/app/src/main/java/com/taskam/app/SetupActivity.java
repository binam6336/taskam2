package com.taskam.app;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.KeyEvent;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.widget.Button;
import android.widget.EditText;
import android.widget.TextView;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.regex.Pattern;

/** صفحه‌ی اول: گرفتن آدرس پنل و بررسی اینکه واقعاً سرور تسکام است (GET {آدرس}/manifest). */
public class SetupActivity extends Activity {
    private static final Pattern LOCAL = Pattern.compile("^(localhost|\\d{1,3}(\\.\\d{1,3}){3})(:\\d+)?(/.*)?$");

    private EditText input;
    private TextView error;
    private Button connect, force;
    private final Handler ui = new Handler(Looper.getMainLooper());

    @Override
    protected void onCreate(Bundle b) {
        super.onCreate(b);
        setContentView(R.layout.activity_setup);
        input = findViewById(R.id.url);
        error = findViewById(R.id.error);
        connect = findViewById(R.id.connect);
        force = findViewById(R.id.force);
        String cur = Config.server(this);
        if (cur != null) input.setText(cur);
        connect.setOnClickListener(v -> check());
        force.setOnClickListener(v -> {
            String raw = input.getText().toString().trim();
            if (!raw.isEmpty()) done(candidates(raw).get(0));
        });
        input.setOnEditorActionListener((TextView v, int id, KeyEvent e) -> {
            if (id == EditorInfo.IME_ACTION_GO || id == EditorInfo.IME_ACTION_DONE) { check(); return true; }
            return false;
        });
    }

    private void check() {
        final String raw = input.getText().toString().trim();
        if (raw.isEmpty()) { showError("آدرس پنل را وارد کنید."); return; }
        error.setVisibility(View.GONE);
        connect.setEnabled(false);
        connect.setText(R.string.setup_checking);
        new Thread(() -> {
            String found = null;
            for (String c : candidates(raw)) {
                if (isTaskam(c)) { found = c; break; }
            }
            final String ok = found;
            ui.post(() -> {
                connect.setEnabled(true);
                connect.setText(R.string.setup_connect);
                if (ok != null) done(ok);
                else {
                    showError("در این آدرس پنل تسکام پیدا نشد یا سرور در دسترس نیست. آدرس را بررسی کنید.");
                    force.setVisibility(View.VISIBLE);
                }
            });
        }).start();
    }

    /** آدرس واردشده و پوشه‌های بالاترش (اگر کاربر آدرس صفحه‌ای مثل /login را چسبانده باشد)، با https و در صورت نبود scheme با http. */
    private static List<String> candidates(String raw) {
        List<String> schemes = new ArrayList<>();
        String rest = raw;
        if (raw.matches("(?i)^https?://.*")) {
            schemes.add(raw.substring(0, raw.indexOf("://")).toLowerCase());
            rest = raw.substring(raw.indexOf("://") + 3);
        } else if (LOCAL.matcher(raw).matches()) {
            schemes.add("http"); schemes.add("https");
        } else {
            schemes.add("https"); schemes.add("http");
        }
        List<String> out = new ArrayList<>();
        for (String s : schemes) {
            Uri u = Uri.parse(s + "://" + rest);
            if (u.getHost() == null || u.getHost().isEmpty()) continue;
            String base = s + "://" + u.getEncodedAuthority();
            List<String> segs = u.getPathSegments();
            for (int n = segs.size(); n >= 0; n--) {
                StringBuilder p = new StringBuilder(base);
                for (int i = 0; i < n; i++) p.append('/').append(Uri.encode(segs.get(i)));
                if (!out.contains(p.toString())) out.add(p.toString());
            }
        }
        if (out.isEmpty()) out.add(raw);
        return out;
    }

    private static boolean isTaskam(String base) {
        HttpURLConnection c = null;
        try {
            c = (HttpURLConnection) new URL(base + "/manifest").openConnection();
            c.setConnectTimeout(8000);
            c.setReadTimeout(8000);
            c.setInstanceFollowRedirects(true);
            if (c.getResponseCode() != 200) return false;
            InputStream in = c.getInputStream();
            ByteArrayOutputStream buf = new ByteArrayOutputStream();
            byte[] b = new byte[4096];
            int r;
            while ((r = in.read(b)) > 0 && buf.size() < 65536) buf.write(b, 0, r);
            String body = buf.toString("UTF-8");
            return body.contains("\"start_url\"") && body.contains("\"scope\"");
        } catch (Exception e) {
            return false;
        } finally {
            if (c != null) c.disconnect();
        }
    }

    private void showError(String m) {
        error.setText(m);
        error.setVisibility(View.VISIBLE);
    }

    private void done(String url) {
        Config.save(this, url);
        Intent i = new Intent(this, MainActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK | Intent.FLAG_ACTIVITY_NEW_TASK);
        startActivity(i);
        finish();
    }
}
