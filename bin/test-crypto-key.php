<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\Crypto;
use App\Core\ContentCipher as C;
use App\Core\Env;
$f=tempnam('/tmp','key12');
try {
 $pw='test-key-012'; // ۱۲ بایت
 file_put_contents($f,"decode_key=$pw\n");Env::load($f);
 if(strlen($pw)!==12||!C::enabled())throw new RuntimeException('12 byte key rejected');
 $old=Crypto::encode('پیام قبلی',$pw,'chat:1:2');
 $content=C::PREFIX.Crypto::encode('نام تسک',$pw,'karlix-content-v1');
 if(C::open($content)!=='نام تسک'||Crypto::decode($old,$pw,'chat:1:2')!=='پیام قبلی')throw new RuntimeException('compatibility failed');
 file_put_contents($f,"decode_key=short-key\n");Env::load($f);if(C::enabled())throw new RuntimeException('short key accepted');
 echo "PASS کلید دقیقاً ۱۲ بایتی، خواندن محتوای رمز‌شده و پیام قبلی، رد کلید کوتاه\n";
} finally {unlink($f);}
