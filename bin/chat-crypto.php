<?php
declare(strict_types=1);

/**
 * مدیریت رمزگذاری پیام‌های چت (فقط CLI).
 *
 *   php bin/chat-crypto.php status           وضعیت: چند پیام رمزشده/متن‌ساده است و آیا با رمز فعلی خوانده می‌شود
 *   php bin/chat-crypto.php selftest         آزمون خودکار الگوریتم (رمز اشتباه، دست‌کاری، بردار مستقل)
 *   php bin/chat-crypto.php encrypt-all      رمزگذاری همه‌ی پیام‌های متن‌ساده‌ی موجود (با ENCODE_PASSWORD فعلی)
 *   OLD_ENCODE_PASSWORD='رمز قدیمی' php bin/chat-crypto.php rotate
 *                                            تغییر رمز: با رمز قدیمی دیکد و با رمز جدید (.env) دوباره انکد می‌شود
 *   php bin/chat-crypto.php decrypt-all --yes   برگرداندن همه به متن‌ساده (فقط برای غیرفعال‌کردن رمزگذاری)
 *
 * قبل از rotate / decrypt-all حتماً از دیتابیس بکاپ بگیرید.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Crypto;
use App\Core\DB;
use App\Models\ChatModel;

$cmd = $argv[1] ?? 'status';
$say = static function (string $m): void { fwrite(STDOUT, $m . "\n"); };
$die = static function (string $m): void { fwrite(STDERR, '✖ ' . $m . "\n"); exit(1); };

/** پردازش دسته‌ای پیام‌ها؛ $fn(row) باید آرایه‌ی ستون‌های تغییرکرده (یا []) برگرداند. */
$each = static function (callable $fn): array {
    $last = 0;
    $stat = ['rows' => 0, 'changed' => 0, 'failed' => 0];
    while (true) {
        $rows = DB::all('SELECT id, sender_id, receiver_id, message, attachment_name FROM chat_messages WHERE id > ? ORDER BY id ASC LIMIT 500', [$last]);
        if (!$rows) {
            break;
        }
        DB::transaction(static function () use ($rows, $fn, &$stat, &$last): void {
            foreach ($rows as $r) {
                $last = (int) $r['id'];
                $stat['rows']++;
                $set = $fn($r);
                if ($set === null) {
                    $stat['failed']++;
                } elseif ($set) {
                    DB::update('chat_messages', $set, 'id = ?', [(int) $r['id']]);
                    $stat['changed']++;
                }
            }
        });
    }
    return $stat;
};

switch ($cmd) {
    case 'selftest':
        $ok = true;
        foreach (Crypto::selfTest() as [$label, $pass]) {
            $say(($pass ? '✔ ' : '✖ ') . $label);
            $ok = $ok && $pass;
        }
        exit($ok ? 0 : 1);

    case 'status':
        $total = (int) DB::value('SELECT COUNT(*) FROM chat_messages');
        $enc = (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE message LIKE 'enc:v1:%'");
        $plain = (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE message <> '' AND message NOT LIKE 'enc:v1:%'");
        $say('رمزگذاری فعال (ENCODE_PASSWORD معتبر + OpenSSL): ' . (Crypto::configured() ? 'بله' : 'خیر'));
        $say("کل پیام‌ها: $total | رمزشده: $enc | متن‌ساده: $plain");
        if (Crypto::configured() && $enc > 0) {
            $bad = 0;
            $sample = DB::all("SELECT sender_id, receiver_id, message FROM chat_messages WHERE message LIKE 'enc:v1:%' ORDER BY id DESC LIMIT 300");
            foreach ($sample as $r) {
                if (Crypto::decode($r['message'], null, ChatModel::aad((int) $r['sender_id'], (int) $r['receiver_id'])) === null) {
                    $bad++;
                }
            }
            $say('نمونه‌ی ' . count($sample) . ' پیام اخیر: قابل‌خواندن با رمز فعلی = ' . (count($sample) - $bad) . '، ناخوانا = ' . $bad . ($bad ? '  ⚠️ رمز .env با رمز زمان انکد یکی نیست' : ''));
        }
        if ($plain > 0) {
            $say('→ برای رمزگذاری پیام‌های متن‌ساده: php bin/chat-crypto.php encrypt-all');
        }
        exit(0);

    case 'encrypt-all':
        if (!Crypto::configured()) {
            $die('ENCODE_PASSWORD معتبر (حداقل ۱۲ نویسه) تنظیم نشده یا OpenSSL فعال نیست.');
        }
        $st = $each(static function (array $r): array {
            $set = [];
            if ($r['message'] !== '' && !Crypto::isEncoded($r['message'])) {
                $set['message'] = ChatModel::seal($r['message'], (int) $r['sender_id'], (int) $r['receiver_id']);
            }
            if ($r['attachment_name'] !== null && $r['attachment_name'] !== '' && !Crypto::isEncoded($r['attachment_name'])) {
                $set['attachment_name'] = ChatModel::seal($r['attachment_name'], (int) $r['sender_id'], (int) $r['receiver_id']);
            }
            return $set;
        });
        $say("انجام شد: بررسی {$st['rows']} پیام، رمزگذاری‌شده {$st['changed']}.");
        exit(0);

    case 'rotate':
        $old = (string) getenv('OLD_ENCODE_PASSWORD');
        if (!Crypto::configured($old)) {
            $die('رمز قدیمی را با متغیر محیطی OLD_ENCODE_PASSWORD بدهید (حداقل ۱۲ نویسه).');
        }
        if (!Crypto::configured()) {
            $die('ENCODE_PASSWORD جدید در .env تنظیم نشده یا نامعتبر است.');
        }
        if (hash_equals($old, Crypto::envPassword())) {
            $die('رمز قدیمی و جدید یکی است.');
        }
        $re = static function (?string $v, int $s, int $rcv, int &$bad) use ($old): ?string {
            if ($v === null || $v === '') {
                return $v;
            }
            if (Crypto::isEncoded($v)) {
                $plain = Crypto::decode($v, $old, ChatModel::aad($s, $rcv));
                if ($plain === null) {
                    $bad++;
                    return null;
                }
                $v = $plain;
            }
            return ChatModel::seal($v, $s, $rcv);
        };
        $st = $each(static function (array $r) use ($re): ?array {
            $bad = 0;
            $m = $re($r['message'], (int) $r['sender_id'], (int) $r['receiver_id'], $bad);
            $f = $re($r['attachment_name'], (int) $r['sender_id'], (int) $r['receiver_id'], $bad);
            return $bad > 0 ? null : ['message' => (string) $m, 'attachment_name' => $f];
        });
        $say("انجام شد: {$st['rows']} پیام بررسی، {$st['changed']} با رمز جدید دوباره انکد شد، {$st['failed']} پیام با رمز قدیمی دیکد نشد (دست‌نخورده ماند).");
        exit($st['failed'] > 0 ? 2 : 0);

    case 'decrypt-all':
        if (!in_array('--yes', $argv, true)) {
            $die('این عمل همه‌ی پیام‌ها را متن‌ساده می‌کند. برای تأیید --yes بدهید.');
        }
        if (!Crypto::configured()) {
            $die('برای دیکد، ENCODE_PASSWORD فعلی باید معتبر باشد.');
        }
        $st = $each(static function (array $r): ?array {
            $set = [];
            foreach (['message', 'attachment_name'] as $col) {
                if ($r[$col] !== null && Crypto::isEncoded($r[$col])) {
                    $plain = Crypto::decode($r[$col], null, ChatModel::aad((int) $r['sender_id'], (int) $r['receiver_id']));
                    if ($plain === null) {
                        return null;
                    }
                    $set[$col] = $plain;
                }
            }
            return $set;
        });
        $say("انجام شد: {$st['rows']} پیام بررسی، {$st['changed']} متن‌ساده شد، {$st['failed']} دیکد نشد.");
        exit($st['failed'] > 0 ? 2 : 0);

    default:
        $die('دستور نامعتبر. گزینه‌ها: status | selftest | encrypt-all | rotate | decrypt-all --yes');
}
