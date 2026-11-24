<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
abstract class Record extends \Illuminate\Database\Eloquent\Model
{
    protected $connection = 'karlix';
    public $timestamps = false;
    protected $guarded = ['*'];
    protected $hidden = ['password_hash', 'token_hash', 'secret', 'private_key', 'session_hash', 'ua_hash', 'admin_pv', 'auth', 'p256dh'];
    public function setAttribute($key, $value)
    {
        if (in_array($key, \App\Core\ContentCipher::FIELDS[$this->getTable()] ?? [], true)) {
            $value = \App\Core\ContentCipher::prepare($this->getTable(), [$key => $value])[$key];
        }
        return parent::setAttribute($key, $value);
    }
    public function jsonSerialize(): mixed { return \App\Core\Pid::out($this->toArray()); }
    public function attributesToArray() { return \App\Core\ContentCipher::open(parent::attributesToArray()); }
    public function getAttributeValue($key) { return \App\Core\ContentCipher::open(parent::getAttributeValue($key)); }
}
