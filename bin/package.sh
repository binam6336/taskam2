#!/bin/sh
# ساخت فایل zip سورس برای ارسال/استقرار — فقط فایل‌های commit‌شده‌ی git.
# هرگز از پوشه‌ی کاری zip نگیرید: .env، storage/vapid.json، نشست‌ها، لاگ‌ها و فایل‌های آپلودی هم داخلش می‌روند.
#   sh bin/package.sh            → taskam-src-<نسخه>.zip
set -e
cd "$(dirname "$0")/.."
ver=$(cat VERSION 2>/dev/null || echo dev)
out="taskam-src-$ver.zip"
git archive --format=zip -o "$out" HEAD
if unzip -l "$out" | grep -E '\.env$|vapid\.json|sess_|\.log$' >/dev/null 2>&1; then
    echo "خطا: فایل حساس داخل $out پیدا شد؛ ارسال نکنید." >&2
    rm -f "$out"
    exit 1
fi
echo "$out ساخته شد."
