<?php
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\Env;
use App\Core\ContentCipher as C;
$f=tempnam(sys_get_temp_dir(),'crypto-diagnostics');
try {
 file_put_contents($f,"decode_key=\nENCODE_PASSWORD=\nencode_password=\n");Env::load($f);
 if(C::diagnostics()['source']!=='none'||!str_contains((string)C::configurationError(),'خوانده نشد'))throw new RuntimeException('missing diagnosis');
 file_put_contents($f,"decode_key=short\nENCODE_PASSWORD=sample-valid-password\n");Env::load($f);
 if(!str_contains((string)C::configurationError(),'کمتر از'))throw new RuntimeException('explicit short diagnosis');
 // نمونه معتبر با نویسه‌های ویژه، بدون استفاده از کلید واقعی کاربر.
 file_put_contents($f,"decode_key=\nENCODE_PASSWORD=sample#value!with@special".chr(92)."\n");Env::load($f);
 if(C::diagnostics()['source']!=='ENCODE_PASSWORD')throw new RuntimeException('source diagnosis');
 if(!function_exists('openssl_encrypt')) {
   if(!str_contains((string)C::configurationError(),'توابع لازم'))throw new RuntimeException('disabled OpenSSL diagnosis');
 } elseif(C::configurationError()!==null)throw new RuntimeException('valid configuration rejected');
 if(str_contains(json_encode(C::diagnostics()),C::key()))throw new RuntimeException('secret leaked');
 echo "PASS تشخیص کلید خالی، کلید کوتاه صریح، کلید قدیمی و نبود توابع OpenSSL؛ بدون نشت مقدار کلید\n";
} finally {unlink($f);}
